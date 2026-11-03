<?php

namespace App\Services\TicketImport;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Support\Facades\DB;

/**
 * Adds an airline or airport a ticket needs but Ticketing does not have yet
 * (owner, 2026-10-05: "automatically add those"). Same format as the rows
 * typed by hand — "Air Arabia (G9)", "Chisinau International Airport (RMO)" —
 * so the matcher, the logos and the route pickers treat them alike.
 *
 * Only codes the Directory knows are added; anything else stays a warning.
 * An airport sits under its country and the state named after its city
 * (that country's first state when no name matches — a state is required).
 * Master data only: no ticket, no money. Each add is its own small write, so
 * one that fails costs that one suggestion and never the import.
 */
class MasterData
{
    /** @return string[] what was added, e.g. "Airline Air Arabia (G9)" */
    public function ensure(array $read): array
    {
        $added = [];
        $airlines = $airports = [];
        foreach ($read['segments'] ?? [] as $s) {
            if ($s['carrier']) $airlines[strtoupper($s['carrier'])] = true;
            foreach (['from', 'to'] as $k) {
                if ($s[$k]) $airports[strtoupper($s[$k])] = true;
            }
        }
        if (! empty($read['carrier'])) {
            $airlines[strtoupper($read['carrier'])] = true;
        }

        foreach (array_keys($airlines) as $code) {
            try {
                if ($row = $this->airline($code)) $added[] = 'Airline ' . $row;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        foreach (array_keys($airports) as $code) {
            try {
                if ($row = $this->airport($code)) $added[] = 'Airport ' . $row;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return $added;
    }

    private function airline(string $code): ?string
    {
        $name = Directory::AIRLINES[$code] ?? null;
        if (! $name || $this->airlineExists($code)) {
            return null;
        }
        $title = $name . ' (' . $code . ')';
        Airline::create(['name' => $title, 'status' => 1]);
        return $title;
    }

    private function airlineExists(string $code): bool
    {
        return Airline::query()->where('name', 'like', '%(' . $code . ')')->get(['name'])
            ->contains(fn ($a) => preg_match('/\(' . preg_quote($code, '/') . '\)\s*$/i', (string) $a->name));
    }

    private function airport(string $code): ?string
    {
        $info = Directory::AIRPORTS[$code] ?? null;
        if (! $info || Airport::query()->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists()) {
            return null;
        }
        [$name, $city, $iso] = $info;
        $country = DB::table('countries')->whereNull('deleted_at')->where('code', $iso)->first(['id']);
        if (! $country) {
            return null;
        }
        $states = DB::table('states')->whereNull('deleted_at')->where('country_id', (string) $country->id);
        $state = (clone $states)->where('name', $city)->first(['id'])
            ?? (clone $states)->where('name', 'like', $city . '%')->orderBy('name')->first(['id'])
            ?? (clone $states)->orderBy('id')->first(['id']);
        $stateId = $state->id ?? DB::table('states')->insertGetId([
            'name' => $city, 'country_id' => (string) $country->id, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $title = $name . ' (' . $code . ')';
        Airport::create(['name' => $title, 'code' => $code, 'state_id' => $stateId, 'country_id' => $country->id]);
        return $title;
    }
}

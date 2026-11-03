<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightStage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The pipeline editor behind the Flight Dashboard journey strip's pencil:
 * reorder, rename, re-icon, add and delete stages. One endpoint takes the
 * whole list as the editor shows it and settles the table to match, in a
 * transaction, so a half-applied edit can never leave the strip and the
 * board disagreeing about what the stages are.
 */
class ContractFlightStageController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:edit contract flight')];
    }

    public function sync(Request $request, string $role)
    {
        if (! Schema::hasTable('contract_flight_stages')) {
            return response()->json(['success' => false, 'message' => 'Stage editing is not installed yet (contract_flight_stages migration pending).'], 409);
        }

        $validated = $request->validate([
            'stages' => ['required', 'array', 'min:1', 'max:40'],
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.label' => ['required', 'string', 'max:80'],
            'stages.*.icon' => ['nullable', 'string', 'max:60', 'regex:/^fa-[a-z0-9-]+$/'],
            'stages.*.is_conditional' => ['nullable', 'boolean'],
        ]);

        $existing = ContractFlightStage::all()->keyBy('id');
        $keptIds = collect($validated['stages'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $removed = $existing->reject(fn ($s) => $keptIds->contains($s->id));

        // Refuse before touching anything: a system stage is one code moves
        // passengers to by key, and a stage with people on it would strand
        // them at a key nothing renders.
        foreach ($removed as $stage) {
            if ($stage->is_system) {
                return response()->json(['success' => false, 'message' => "\"{$stage->label}\" is a built-in stage the system moves passengers to automatically, so it cannot be deleted — rename or reorder it instead."], 422);
            }
            $onStage = ContractFlightPassenger::where('stage', $stage->key)->count();
            if ($onStage > 0) {
                return response()->json(['success' => false, 'message' => "\"{$stage->label}\" still has {$onStage} passenger" . ($onStage === 1 ? '' : 's') . " on it. Move them to another stage first, then delete it."], 422);
            }
        }

        DB::transaction(function () use ($validated, $existing, $removed) {
            $usedKeys = $existing->pluck('key')->all();
            foreach ($validated['stages'] as $order => $item) {
                $label = trim($item['label']);
                $icon = $item['icon'] ?: 'fa-circle';
                $conditional = (bool) ($item['is_conditional'] ?? false);
                $stage = ! empty($item['id']) ? $existing->get((int) $item['id']) : null;
                if ($stage) {
                    $stage->update(['label' => $label, 'icon' => $icon, 'sort_order' => $order, 'is_conditional' => $conditional]);
                    continue;
                }
                $base = Str::slug($label, '_') ?: 'stage';
                $key = $base;
                for ($n = 2; in_array($key, $usedKeys, true); $n++) {
                    $key = "{$base}_{$n}";
                }
                $usedKeys[] = $key;
                ContractFlightStage::create([
                    'key' => $key, 'label' => $label, 'icon' => $icon, 'sort_order' => $order,
                    'is_conditional' => $conditional, 'is_system' => false,
                ]);
            }
            foreach ($removed as $stage) {
                $stage->delete();
            }
        });

        return response()->json(['success' => true, 'stages' => ContractFlightPassenger::stageRoute(true)]);
    }
}

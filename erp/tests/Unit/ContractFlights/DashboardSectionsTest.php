<?php

namespace Tests\Unit\ContractFlights;

use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DashboardSectionsTest extends TestCase
{
    #[DataProvider('sections')]
    public function test_flights_go_to_the_correct_operational_sections(?string $departure, string $status, array $passengers, array $expected): void
    {
        $flight = new ContractFlight;
        $flight->setDateFormat('Y-m-d H:i:s');
        $flight->fill(['departure_at' => $departure, 'status' => $status]);
        $flight->setRelation('passengers', collect(array_map(fn ($p) => new ContractFlightPassenger($p), $passengers)));

        $this->assertSame($expected, $flight->dashboardSections(Carbon::parse('2026-10-29 12:00:00')));
    }

    public static function sections(): array
    {
        $waiting = ['stage' => 'ticket_issued'];
        $missed = ['stage' => 'ticket_issued', 'exception_flag' => 'flight_missed'];
        $offloaded = ['stage' => 'ticket_issued', 'exception_flag' => 'immigration_offload'];

        return [
            'upcoming' => ['2026-10-30 09:00:00', 'open', [$waiting], ['active']],
            'no date yet' => [null, 'open', [$waiting], ['active']],
            'empty upcoming batch' => ['2026-10-30 09:00:00', 'open', [], ['active']],
            'empty overdue batch' => ['2026-10-28 09:00:00', 'open', [], ['overdue']],
            'earlier today needs update' => ['2026-10-29 09:00:00', 'boarding', [$waiting], ['overdue']],
            'past open flight' => ['2026-10-28 09:00:00', 'open', [$waiting], ['overdue']],
            'physical departure overrides stale status' => ['2026-10-28 09:00:00', 'open', [['stage' => 'departed']], ['departed']],
            'closed overrides stale status' => ['2026-10-28 09:00:00', 'open', [['stage' => 'case_closed']], ['departed']],
            'partially flown still needs update' => ['2026-10-28 09:00:00', 'open', [['stage' => 'departed'], $waiting], ['overdue']],
            'explicit departed' => ['2026-10-28 09:00:00', 'departed', [$waiting], ['departed']],
            'missed only' => ['2026-10-28 09:00:00', 'open', [$missed], ['missed']],
            'offloaded only' => ['2026-10-28 09:00:00', 'open', [$offloaded], ['offloaded']],
            'deported company bears' => ['2026-10-28 09:00:00', 'open', [['stage' => 'case_closed', 'exception_flag' => 'deported_we_bear']], ['offloaded']],
            'mixed overdue and exceptions' => ['2026-10-28 09:00:00', 'open', [$missed, $offloaded, $waiting], ['missed', 'offloaded', 'overdue']],
            'mixed flown and missed' => ['2026-10-28 09:00:00', 'open', [$missed, ['stage' => 'landed']], ['missed', 'departed']],
            'cancelled flight' => ['2026-10-28 09:00:00', 'cancelled', [$waiting], []],
            'cancelled passenger only' => ['2026-10-28 09:00:00', 'open', [['stage' => 'ticket_issued', 'exception_flag' => 'flight_cancelled']], []],
        ];
    }
}

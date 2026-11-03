<?php

namespace Tests\Unit\ContractFlights;

use App\Http\Controllers\Dashboard\ManageFlightController;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PassengerKpiCategoryTest extends TestCase
{
    public function test_flights_and_passengers_has_separate_offload_and_deported_cards(): void
    {
        $scopes = ManageFlightController::passengerKpiScopes();

        $this->assertSame('Offload', $scopes['offloaded']['label']);
        $this->assertSame('Deported', $scopes['deported']['label']);
        $this->assertSame(['boarding_denied', 'immigration_offload'], ContractFlightPassenger::OFFLOAD_KPI_FLAGS);
        $this->assertSame(['deported', ContractFlightPassenger::DEPORTED_BORNE_FLAG], ContractFlightPassenger::DEPORTED_KPI_FLAGS);
        $this->assertNotContains('flight_missed', ContractFlightPassenger::OFFLOAD_KPI_FLAGS);
        $this->assertNotContains('flight_missed', ContractFlightPassenger::DEPORTED_KPI_FLAGS);
    }

    #[DataProvider('exceptionFlags')]
    public function test_exception_flags_map_to_their_own_kpi_card(string $flag, string $expected): void
    {
        $passenger = (new ContractFlightPassenger)->forceFill([
            'exception_flag' => $flag,
            'agent_price' => 0,
            'agent_paid_amount' => 0,
        ]);
        $flight = new ContractFlight;
        $flight->forceFill(['status' => 'open']);

        $passenger->setRelation('contractFlight', $flight);
        $passenger->setRelation('ticketPurchases', collect());

        $this->assertSame($expected, $passenger->kpiKey());
    }

    public static function exceptionFlags(): array
    {
        return [
            'airport offload' => ['boarding_denied', 'offloaded'],
            'immigration offload' => ['immigration_offload', 'offloaded'],
            'agent-side deportation' => ['deported', 'deported'],
            'company-side deportation' => [ContractFlightPassenger::DEPORTED_BORNE_FLAG, 'deported'],
        ];
    }
}

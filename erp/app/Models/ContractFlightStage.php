<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step of the contract-flight passenger pipeline, editable from the
 * Flight Dashboard's journey strip. Before the table exists (or if it is
 * empty) ContractFlightPassenger::stageRoute() falls back to DEFAULTS, so
 * every screen keeps working exactly as it did with the old constant.
 */
class ContractFlightStage extends Model
{
    protected $fillable = ['key', 'label', 'icon', 'sort_order', 'is_conditional', 'is_system'];

    protected $casts = ['is_conditional' => 'bool', 'is_system' => 'bool', 'sort_order' => 'int'];

    /**
     * The shipped pipeline. `system` stages are the ones code advances to or
     * reads by key (new passengers start at passenger_received, an edit
     * bumps to information_entered, a linked PNR to ticket_issued, transit
     * is the conditional leg, case_closed is "completed" on the tracker) —
     * they can be renamed and reordered but never deleted.
     */
    public const DEFAULTS = [
        ['passenger_received', 'Passenger Received', 'fa-user-tie', false, true],
        ['information_entered', 'Information Entered', 'fa-keyboard', false, true],
        ['documents_received', 'Documents Received', 'fa-file-arrow-down', false, false],
        ['documents_verified', 'Documents Verified', 'fa-file-circle-check', false, false],
        ['service_defined', 'Service / Category Defined', 'fa-tags', false, false],
        ['route_confirmed', 'Route Confirmed', 'fa-route', false, false],
        ['ticket_issued', 'Ticket Issued', 'fa-ticket', false, true],
        ['ready_for_travel', 'Ready for Travel', 'fa-circle-check', false, false],
        // The ops sheet's "Call Done": the passenger was phoned and confirmed
        // the date, time and reporting instructions before the flight.
        ['confirmation_call', 'Confirmation Call', 'fa-phone-volume', false, false],
        ['preflight_coordination', 'Pre-flight Coordination', 'fa-headset', false, false],
        ['airport_processing', 'Airport Processing', 'fa-building', false, false],
        ['immigration', 'Immigration', 'fa-passport', false, false],
        ['boarding', 'Boarding', 'fa-person-walking-luggage', false, false],
        ['departed', 'Departed', 'fa-plane-departure', false, false],
        ['transit', 'Transit Handling', 'fa-shuffle', true, true],
        ['landed', 'Landed', 'fa-plane-arrival', false, false],
        ['arrival_confirmed', 'Arrival Confirmed', 'fa-location-dot', false, false],
        ['case_closed', 'Case Closed', 'fa-flag-checkered', false, true],
    ];

    /** Icons the stage editor offers. Any FontAwesome solid name is accepted on save. */
    public const ICONS = [
        'fa-user-tie', 'fa-keyboard', 'fa-file-arrow-down', 'fa-file-circle-check', 'fa-tags', 'fa-route',
        'fa-ticket', 'fa-circle-check', 'fa-headset', 'fa-building', 'fa-passport', 'fa-person-walking-luggage',
        'fa-plane-departure', 'fa-shuffle', 'fa-plane-arrival', 'fa-location-dot', 'fa-flag-checkered',
        'fa-stamp', 'fa-id-card', 'fa-money-bill', 'fa-phone', 'fa-phone-volume', 'fa-envelope', 'fa-hotel', 'fa-bus',
        'fa-briefcase-medical', 'fa-clipboard-check', 'fa-hourglass-half', 'fa-star', 'fa-bell', 'fa-handshake',
    ];

    public static function defaultRows(): array
    {
        return array_map(fn ($row, $i) => [
            'id' => null, 'key' => $row[0], 'label' => $row[1], 'icon' => $row[2],
            'sort_order' => $i, 'is_conditional' => $row[3], 'is_system' => $row[4],
        ], self::DEFAULTS, array_keys(self::DEFAULTS));
    }
}

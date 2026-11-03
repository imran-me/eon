<?php

namespace App\Services\TicketTrail;

/**
 * Before against after, per ledger and per account head — the right side of
 * the Trail ("what it is, if changed, where will it hit, what will be
 * changed"). Rows are matched on what they ARE (type, side, amount, day), not
 * their ids: a row posted again at the same figure reads "same".
 */
class Diff
{
    public static function of(array $before, array $after): array
    {
        $ledgers = [];
        foreach (['before' => $before, 'after' => $after] as $side => $fp) {
            $events = collect($fp['events'] ?? [])->keyBy('key');
            foreach ($fp['rows'] as $r) {
                $k = $r['ledger'];
                $ledgers[$k] ??= ['ledger' => $k, 'name' => $r['ledger_name'], 'kind' => $r['ledger_kind'], 'before' => [], 'after' => []];
                $r['label'] = self::label($r);
                $r['event_label'] = self::eventLabel($r['event'], $events);
                $ledgers[$k][$side][] = $r;
            }
        }
        $any = false;
        foreach ($ledgers as &$l) {
            $l['rows'] = self::match($l['before'], $l['after']);
            $net = fn (array $rows) => round(array_sum(array_column($rows, 'debit')) - array_sum(array_column($rows, 'credit')), 2);
            $l['net_before'] = $net($l['before']);
            $l['net_after'] = $net($l['after']);
            $l['changed'] = collect($l['rows'])->contains(fn ($r) => $r['status'] !== 'same');
            $any = $any || $l['changed'];
            unset($l['before'], $l['after']);
        }
        unset($l);

        $acc = [];
        foreach (['before' => $before, 'after' => $after] as $side => $fp) {
            foreach ($fp['journals'] as $j) {
                foreach ($j['lines'] as $line) {
                    $k = $line['account'];
                    $acc[$k] ??= ['account' => $k, 'code' => $line['code'], 'before' => 0.0, 'after' => 0.0];
                    $acc[$k][$side] = round($acc[$k][$side] + $line['debit'] - $line['credit'], 2);
                }
            }
        }
        ksort($acc);
        $acc = array_values(array_map(fn ($a) => $a + ['changed' => abs($a['before'] - $a['after']) >= 0.005], $acc));

        return [
            'changed' => $any || collect($acc)->contains('changed', true),
            'ledgers' => array_values($ledgers),
            'accounts' => $acc,
        ];
    }

    /** Pair rows: same figure and day first, then same kind of row (changed), the rest removed / added. */
    private static function match(array $a, array $b): array
    {
        $side = fn ($r) => $r['debit'] > 0 ? 'Dr' : 'Cr';
        $amt = fn ($r) => $r['debit'] > 0 ? $r['debit'] : $r['credit'];
        $row = fn ($status, $x, $y) => [
            'status' => $status,
            'label' => ($y ?? $x)['label'],
            'event' => ($y ?? $x)['event_label'],
            'side' => $side($y ?? $x),
            'before' => $x ? $amt($x) : null,
            'after' => $y ? $amt($y) : null,
            'date_before' => $x['date'] ?? null,
            'date_after' => $y['date'] ?? null,
            'shared' => (bool) (($y ?? $x)['shared'] ?? false),
        ];
        $out = [];
        $restB = $b;
        $restA = [];
        foreach ($a as $x) {
            $i = collect($restB)->search(fn ($y) => $y['type'] === $x['type'] && $side($y) === $side($x) && abs($amt($y) - $amt($x)) < 0.005 && $y['date'] === $x['date']);
            if ($i !== false) {
                $out[] = $row('same', $x, $restB[$i]);
                unset($restB[$i]);
            } else {
                $restA[] = $x;
            }
        }
        foreach ($restA as $x) {
            $i = collect($restB)->search(fn ($y) => $y['type'] === $x['type'] && $side($y) === $side($x));
            if ($i !== false) {
                $y = $restB[$i];
                $out[] = $row(abs($amt($y) - $amt($x)) < 0.005 ? 'moved' : 'changed', $x, $y);
                unset($restB[$i]);
            } else {
                $out[] = $row('removed', $x, null);
            }
        }
        foreach ($restB as $y) {
            $out[] = $row('added', null, $y);
        }
        usort($out, fn ($p, $q) => strcmp((string) ($p['date_after'] ?? $p['date_before']), (string) ($q['date_after'] ?? $q['date_before'])));

        return $out;
    }

    private static function label(array $r): string
    {
        return match ($r['src'].':'.$r['type']) {
            'tx:ticket_purchase' => $r['bank'] ? 'Paid · '.$r['bank'] : 'Ticket cost',
            'tx:ticket_purchase_refund' => 'Airline refund',
            'tx:ticket_sale' => $r['bank'] ? 'Receipt · '.$r['bank'] : 'Invoice '.$r['ref'].' (whole invoice)',
            'tx:ticket_change_fee' => 'Re-issue charge owed',
            'tx:ticket_change_fare' => 'Fare difference owed',
            'tx:ticket_change_charge' => 'Change charge',
            'tx:ticket_sale_reversal' => 'Agent price taken back',
            'pb:ticket_purchase' => 'Paid from the wallet',
            'pb:ticket_purchase_refund' => 'Refund into the wallet',
            'pb:ticket_change_fee' => 'Re-issue charge from the wallet',
            'pb:ticket_change_fare' => 'Fare difference from the wallet',
            // the contract flight's own lines (whole passenger)
            'tx:contract_flight_passenger_agent' => 'Package (per head)',
            'tx:contract_flight_passenger_agent_reversal' => $r['credit'] > 0 ? 'Package reversed' : 'Package charged again',
            'tx:contract_flight_passenger_reissue' => 'Re-issue charge (flight)',
            'tx:contract_flight_passenger_change' => 'Flight change charge',
            'tx:contract_flight_reissue_fee', 'pb:contract_flight_reissue_fee' => 'Re-issue fee owed (flight)',
            'tx:contract_flight_reissue_fare', 'pb:contract_flight_reissue_fare' => 'Fare difference owed (flight)',
            'tx:contract_flight_service_cost' => 'Trainer / consultant fee',
            'tx:contract_flight_airline_penalty' => 'Airline penalty (flight)',
            'tx:contract_flight_fare_difference' => 'Fare difference (flight)',
            'tx:contract_flight_price_revision' => 'Package revised',
            'tx:contract_flight_deport_cost' => 'Deport cost',
            default => ucfirst(str_replace('_', ' ', $r['type'])),
        };
    }

    private static function eventLabel(string $event, $events): string
    {
        return match (true) {
            $event === 'purchase' => 'Purchase',
            $event === 'payment' => 'Payment',
            $event === 'sale' => 'Sale',
            $event === 'receipt' => 'Receipt',
            $event === 'cancel' => 'Cancelled',
            default => ($events->get($event)['label'] ?? 'Change'),
        };
    }
}

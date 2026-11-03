<?php

namespace App\Services\TicketImport;

/**
 * City / airport wording → IATA code, for the layouts that print a city
 * instead of a code (Galileo's e-ticket prints "Dhaka - Hazrat Shahjalal
 * Intl Arpt"). Most layouts print the code itself and never come here.
 *
 * Longest name first wins, so "Istanbul Sabiha" is SAW before "Istanbul"
 * is IST. A city missing here leaves the sector empty on the card — add it.
 */
final class Cities
{
    public const MAP = [
        'ISTANBUL SABIHA' => 'SAW', 'SABIHA' => 'SAW',
        'ABU DHABI' => 'AUH', 'KUALA LUMPUR' => 'KUL', 'ADDIS ABABA' => 'ADD',
        'HONG KONG' => 'HKG', 'COX\'S BAZAR' => 'CXB', 'COXS BAZAR' => 'CXB',
        'RAS AL KHAIMAH' => 'RKT', 'NEW DELHI' => 'DEL',
        'DHAKA' => 'DAC', 'CHITTAGONG' => 'CGP', 'CHATTOGRAM' => 'CGP', 'SYLHET' => 'ZYL',
        'SAIDPUR' => 'SPD', 'RAJSHAHI' => 'RJH', 'JESSORE' => 'JSR', 'BARISAL' => 'BZL',
        'BAHRAIN' => 'BAH', 'ISTANBUL' => 'IST', 'DOHA' => 'DOH', 'DUBAI' => 'DXB',
        'SHARJAH' => 'SHJ', 'MUSCAT' => 'MCT', 'RIYADH' => 'RUH', 'JEDDAH' => 'JED',
        'DAMMAM' => 'DMM', 'MEDINA' => 'MED', 'MADINAH' => 'MED', 'KUWAIT' => 'KWI',
        'SINGAPORE' => 'SIN', 'BANGKOK' => 'BKK', 'KOLKATA' => 'CCU', 'DELHI' => 'DEL',
        'CHENNAI' => 'MAA', 'MUMBAI' => 'BOM', 'KATHMANDU' => 'KTM', 'COLOMBO' => 'CMB',
        'MALE' => 'MLE', 'LEFKOSA' => 'ECN', 'ERCAN' => 'ECN', 'NICOSIA' => 'ECN',
        'CHISINAU' => 'RMO', 'CAIRO' => 'CAI', 'GUANGZHOU' => 'CAN', 'KUNMING' => 'KMG',
        'BEIJING' => 'PEK', 'SHANGHAI' => 'PVG', 'TOKYO' => 'NRT', 'SEOUL' => 'ICN',
        'LONDON' => 'LHR', 'PARIS' => 'CDG', 'FRANKFURT' => 'FRA', 'ROME' => 'FCO',
        'MILAN' => 'MXP', 'ATHENS' => 'ATH', 'BAKU' => 'GYD', 'TASHKENT' => 'TAS',
        'ALMATY' => 'ALA', 'TBILISI' => 'TBS', 'YEREVAN' => 'EVN', 'BELGRADE' => 'BEG',
        'BUCHAREST' => 'OTP', 'SOFIA' => 'SOF', 'WARSAW' => 'WAW', 'BUDAPEST' => 'BUD',
        'PRAGUE' => 'PRG', 'VIENNA' => 'VIE', 'MINSK' => 'MSQ', 'MOSCOW' => 'SVO',
        'TORONTO' => 'YYZ', 'NEW YORK' => 'JFK', 'SYDNEY' => 'SYD', 'MELBOURNE' => 'MEL',
        'AMMAN' => 'AMM', 'BAGHDAD' => 'BGW', 'BEIRUT' => 'BEY', 'TEHRAN' => 'IKA',
        'KARACHI' => 'KHI', 'LAHORE' => 'LHE', 'ISLAMABAD' => 'ISB', 'PENANG' => 'PEN',
        'JAKARTA' => 'CGK', 'MANILA' => 'MNL', 'HANOI' => 'HAN', 'HO CHI MINH' => 'SGN',
        'NAIROBI' => 'NBO', 'LAGOS' => 'LOS', 'JOHANNESBURG' => 'JNB', 'TRIPOLI' => 'TIP',
        'BENGHAZI' => 'BEN', 'MALTA' => 'MLA', 'TIRANA' => 'TIA', 'SARAJEVO' => 'SJJ',
        'SKOPJE' => 'SKP', 'PODGORICA' => 'TGD', 'ZAGREB' => 'ZAG', 'LJUBLJANA' => 'LJU',
        'RIGA' => 'RIX', 'VILNIUS' => 'VNO', 'TALLINN' => 'TLL', 'BISHKEK' => 'FRU',
        'ANTALYA' => 'AYT', 'IZMIR' => 'ADB', 'ANKARA' => 'ESB', 'SALALAH' => 'SLL',
        'ABHA' => 'AHB', 'TAIF' => 'TIF', 'QASSIM' => 'ELQ', 'TABUK' => 'TUU',
        'HYDERABAD' => 'HYD', 'BANGALORE' => 'BLR', 'BENGALURU' => 'BLR', 'KOCHI' => 'COK',
        'GOA' => 'GOI', 'AHMEDABAD' => 'AMD', 'AMRITSAR' => 'ATQ', 'POKHARA' => 'PKR',
    ];

    /**
     * Every city named in $text, as codes, in the order they appear;
     * repeats next to each other collapse ("Bahrain - Bahrain Intl" = one).
     */
    public static function inOrder(string $text): array
    {
        $upper = strtoupper($text);
        $hits = [];
        $names = array_keys(self::MAP);
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        $taken = [];
        foreach ($names as $name) {
            $offset = 0;
            while (preg_match('/\b' . preg_quote($name, '/') . '\b/', $upper, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $pos = $m[0][1];
                $end = $pos + strlen($name);
                $overlaps = false;
                foreach ($taken as [$a, $b]) {
                    if ($pos < $b && $end > $a) { $overlaps = true; break; }
                }
                if (! $overlaps) {
                    $taken[] = [$pos, $end];
                    $hits[$pos] = self::MAP[$name];
                }
                $offset = $end;
            }
        }
        ksort($hits);
        $codes = [];
        foreach ($hits as $code) {
            if (end($codes) !== $code) {
                $codes[] = $code;
            }
        }
        return $codes;
    }
}

<?php

namespace App\Services\TicketImport;

/**
 * What the importer knows about airlines and airports it may have to add
 * to Ticketing → Airlines / Airport Management by itself (owner,
 * 2026-10-05: "automatically add those").
 *
 * IATA code → name (airline), and → [airport name, city, country ISO-2]
 * (airport — an airport row needs its country and a state). A code missing
 * here is NOT invented: the card keeps its "not in Airlines / Airport
 * Management" warning and staff add it by hand. Add a line here instead.
 */
final class Directory
{
    public const AIRLINES = [
        'G9' => 'Air Arabia', '3L' => 'Air Arabia Abu Dhabi', 'E5' => 'Air Arabia Egypt', '3O' => 'Air Arabia Maroc',
        'FZ' => 'FlyDubai', 'EK' => 'Emirates', 'EY' => 'Etihad Airways', 'QR' => 'Qatar Airways', 'GF' => 'Gulf Air',
        'WY' => 'Oman Air', 'OV' => 'SalamAir', 'KU' => 'Kuwait Airways', 'J9' => 'Jazeera Airways',
        'SV' => 'Saudia', 'XY' => 'flynas', 'F3' => 'flyadeal', 'RJ' => 'Royal Jordanian', 'ME' => 'Middle East Airlines',
        'TK' => 'Turkish Airlines', 'PC' => 'Pegasus Airlines', 'XQ' => 'SunExpress', 'VF' => 'AJet',
        'BG' => 'Biman Bangladesh Airlines', 'BS' => 'Us Bangla', 'VQ' => 'Novoair', '2A' => 'Air Astra',
        'AI' => 'Air India', 'IX' => 'Air India Express', '6E' => 'IndiGo', 'SG' => 'SpiceJet', 'UK' => 'Vistara',
        'UL' => 'SriLankan Airlines', 'H9' => 'Himalaya Airlines', 'RA' => 'Nepal Airlines', 'KB' => 'Druk Air',
        'PK' => 'Pakistan International Airlines', '8D' => 'Fits Air', 'Q2' => 'Maldivian',
        'MH' => 'Malaysia Airlines', 'AK' => 'Air Asia', 'D7' => 'AirAsia X', 'OD' => 'Malindo Air', 'FY' => 'Firefly',
        'SQ' => 'Singapore Airlines', 'TR' => 'Scoot', 'TG' => 'Thai Airways', 'FD' => 'Thai AirAsia', 'WE' => 'Thai Smile',
        'VN' => 'Vietnam Airlines', 'VJ' => 'VietJet Air', 'GA' => 'Garuda Indonesia', 'PR' => 'Philippine Airlines',
        'CX' => 'Cathay Pacific', 'CA' => 'Air China', 'MU' => 'China Eastern Airlines', 'CZ' => 'China Southern Airlines',
        'HU' => 'Hainan Airlines', '3U' => 'Sichuan Airlines', 'KY' => 'Kunming Airlines', 'MF' => 'Xiamen Airlines',
        'JL' => 'Japan Airlines', 'NH' => 'All Nippon Airways', 'KE' => 'Korean Air', 'OZ' => 'Asiana Airlines',
        'MS' => 'EgyptAir', 'ET' => 'Ethiopian Airlines', 'KQ' => 'Kenya Airways', 'AT' => 'Royal Air Maroc',
        'BA' => 'British Airways', 'LH' => 'Lufthansa', 'AF' => 'Air France', 'KL' => 'KLM', 'LX' => 'Swiss',
        'OS' => 'Austrian Airlines', 'AZ' => 'ITA Airways', 'LO' => 'LOT Polish Airlines', 'SU' => 'Aeroflot',
        'HY' => 'Uzbekistan Airways', 'KC' => 'Air Astana', 'J2' => 'Azerbaijan Airlines', 'A9' => 'Georgian Airways',
        'W6' => 'Wizz Air', 'FR' => 'Ryanair', 'U2' => 'easyJet', 'RO' => 'TAROM', 'FB' => 'Bulgaria Air',
        'JU' => 'Air Serbia', '9U' => 'Air Moldova', 'H4' => 'HiSky', 'A3' => 'Aegean Airlines', 'OA' => 'Olympic Air',
        'AC' => 'Air Canada', 'AA' => 'American Airlines', 'UA' => 'United Airlines', 'DL' => 'Delta Air Lines',
        'QF' => 'Qantas', 'NZ' => 'Air New Zealand', 'IR' => 'Iran Air', 'W5' => 'Mahan Air', 'IA' => 'Iraqi Airways',
    ];

    /** code => [airport name, city, country ISO-2] */
    public const AIRPORTS = [
        // Bangladesh
        'DAC' => ['Shahjalal International Airport', 'Dhaka', 'BD'], 'CGP' => ['Shah Amanat International Airport', 'Chittagong', 'BD'],
        'ZYL' => ['Osmani International Airport', 'Sylhet', 'BD'], 'CXB' => ['Cox\'s Bazar Airport', 'Cox\'s Bazar', 'BD'],
        'JSR' => ['Jessore Airport', 'Jessore', 'BD'], 'RJH' => ['Shah Makhdum Airport', 'Rajshahi', 'BD'],
        'SPD' => ['Saidpur Airport', 'Saidpur', 'BD'], 'BZL' => ['Barisal Airport', 'Barisal', 'BD'],
        // Gulf & Middle East
        'DXB' => ['Dubai International Airport', 'Dubai', 'AE'], 'DWC' => ['Al Maktoum International Airport', 'Dubai', 'AE'],
        'SHJ' => ['Sharjah International Airport', 'Sharjah', 'AE'], 'AUH' => ['Abu Dhabi International Airport', 'Abu Dhabi', 'AE'],
        'RKT' => ['Ras Al Khaimah International Airport', 'Ras Al Khaimah', 'AE'], 'DOH' => ['Hamad International Airport', 'Doha', 'QA'],
        'BAH' => ['Bahrain International Airport', 'Muharraq', 'BH'], 'KWI' => ['Kuwait International Airport', 'Kuwait City', 'KW'],
        'MCT' => ['Muscat International Airport', 'Muscat', 'OM'], 'SLL' => ['Salalah Airport', 'Salalah', 'OM'],
        'RUH' => ['King Khalid International Airport', 'Riyadh', 'SA'], 'JED' => ['King Abdulaziz International Airport', 'Jeddah', 'SA'],
        'DMM' => ['King Fahd International Airport', 'Dammam', 'SA'], 'MED' => ['Prince Mohammad bin Abdulaziz International Airport', 'Medina', 'SA'],
        'AHB' => ['Abha International Airport', 'Abha', 'SA'], 'TIF' => ['Taif International Airport', 'Taif', 'SA'],
        'ELQ' => ['Prince Naif bin Abdulaziz International Airport', 'Qassim', 'SA'], 'TUU' => ['Tabuk Regional Airport', 'Tabuk', 'SA'],
        'AMM' => ['Queen Alia International Airport', 'Amman', 'JO'], 'BEY' => ['Beirut–Rafic Hariri International Airport', 'Beirut', 'LB'],
        'BGW' => ['Baghdad International Airport', 'Baghdad', 'IQ'], 'EBL' => ['Erbil International Airport', 'Erbil', 'IQ'],
        'IKA' => ['Imam Khomeini International Airport', 'Tehran', 'IR'],
        // Turkey & Cyprus
        'IST' => ['Istanbul Airport', 'Istanbul', 'TR'], 'SAW' => ['Sabiha Gökçen International Airport', 'Istanbul', 'TR'],
        'ESB' => ['Esenboğa International Airport', 'Ankara', 'TR'], 'AYT' => ['Antalya Airport', 'Antalya', 'TR'],
        'ADB' => ['İzmir Adnan Menderes Airport', 'Izmir', 'TR'], 'ECN' => ['Ercan International Airport', 'Lefkosa', 'CY'],
        'LCA' => ['Larnaca International Airport', 'Larnaca', 'CY'],
        // South Asia
        'CCU' => ['Netaji Subhas Chandra Bose International Airport', 'Kolkata', 'IN'], 'DEL' => ['Indira Gandhi International Airport', 'Delhi', 'IN'],
        'BOM' => ['Chhatrapati Shivaji Maharaj International Airport', 'Mumbai', 'IN'], 'MAA' => ['Chennai International Airport', 'Chennai', 'IN'],
        'BLR' => ['Kempegowda International Airport', 'Bangalore', 'IN'], 'HYD' => ['Rajiv Gandhi International Airport', 'Hyderabad', 'IN'],
        'COK' => ['Cochin International Airport', 'Kochi', 'IN'], 'GAU' => ['Lokpriya Gopinath Bordoloi International Airport', 'Guwahati', 'IN'],
        'KTM' => ['Tribhuvan International Airport', 'Kathmandu', 'NP'], 'CMB' => ['Bandaranaike International Airport', 'Colombo', 'LK'],
        'MLE' => ['Velana International Airport', 'Male', 'MV'], 'PBH' => ['Paro International Airport', 'Paro', 'BT'],
        'KHI' => ['Jinnah International Airport', 'Karachi', 'PK'], 'LHE' => ['Allama Iqbal International Airport', 'Lahore', 'PK'],
        'ISB' => ['Islamabad International Airport', 'Islamabad', 'PK'],
        // South-East & East Asia
        'KUL' => ['Kuala Lumpur International Airport', 'Kuala Lumpur', 'MY'], 'PEN' => ['Penang International Airport', 'Penang', 'MY'],
        'BKI' => ['Kota Kinabalu International Airport', 'Kota Kinabalu', 'MY'], 'JHB' => ['Senai International Airport', 'Johor Bahru', 'MY'],
        'SIN' => ['Singapore Changi Airport', 'Singapore', 'SG'], 'BKK' => ['Suvarnabhumi Airport', 'Bangkok', 'TH'],
        'DMK' => ['Don Mueang International Airport', 'Bangkok', 'TH'], 'HKT' => ['Phuket International Airport', 'Phuket', 'TH'],
        'CGK' => ['Soekarno–Hatta International Airport', 'Jakarta', 'ID'], 'DPS' => ['Ngurah Rai International Airport', 'Bali', 'ID'],
        'MNL' => ['Ninoy Aquino International Airport', 'Manila', 'PH'], 'SGN' => ['Tan Son Nhat International Airport', 'Ho Chi Minh City', 'VN'],
        'HAN' => ['Noi Bai International Airport', 'Hanoi', 'VN'], 'RGN' => ['Yangon International Airport', 'Yangon', 'MM'],
        'HKG' => ['Hong Kong International Airport', 'Hong Kong', 'HK'], 'CAN' => ['Guangzhou Baiyun International Airport', 'Guangzhou', 'CN'],
        'KMG' => ['Kunming Changshui International Airport', 'Kunming', 'CN'], 'PEK' => ['Beijing Capital International Airport', 'Beijing', 'CN'],
        'PVG' => ['Shanghai Pudong International Airport', 'Shanghai', 'CN'], 'CTU' => ['Chengdu Shuangliu International Airport', 'Chengdu', 'CN'],
        'NRT' => ['Narita International Airport', 'Tokyo', 'JP'], 'HND' => ['Haneda Airport', 'Tokyo', 'JP'],
        'ICN' => ['Incheon International Airport', 'Seoul', 'KR'],
        // Central Asia & Caucasus
        'TAS' => ['Tashkent International Airport', 'Tashkent', 'UZ'], 'ALA' => ['Almaty International Airport', 'Almaty', 'KZ'],
        'NQZ' => ['Nursultan Nazarbayev International Airport', 'Astana', 'KZ'], 'FRU' => ['Manas International Airport', 'Bishkek', 'KG'],
        'GYD' => ['Heydar Aliyev International Airport', 'Baku', 'AZ'], 'TBS' => ['Tbilisi International Airport', 'Tbilisi', 'GE'],
        'EVN' => ['Zvartnots International Airport', 'Yerevan', 'AM'],
        // Europe
        'RMO' => ['Chisinau International Airport', 'Chisinau', 'MD'], 'OTP' => ['Henri Coandă International Airport', 'Bucharest', 'RO'],
        'SOF' => ['Sofia Airport', 'Sofia', 'BG'], 'BEG' => ['Belgrade Nikola Tesla Airport', 'Belgrade', 'RS'],
        'BUD' => ['Budapest Ferenc Liszt International Airport', 'Budapest', 'HU'], 'WAW' => ['Warsaw Chopin Airport', 'Warsaw', 'PL'],
        'KRK' => ['Kraków John Paul II International Airport', 'Krakow', 'PL'], 'PRG' => ['Václav Havel Airport Prague', 'Prague', 'CZ'],
        'VIE' => ['Vienna International Airport', 'Vienna', 'AT'], 'BTS' => ['Bratislava Airport', 'Bratislava', 'SK'],
        'ZAG' => ['Zagreb Airport', 'Zagreb', 'HR'], 'LJU' => ['Ljubljana Jože Pučnik Airport', 'Ljubljana', 'SI'],
        'SJJ' => ['Sarajevo International Airport', 'Sarajevo', 'BA'], 'SKP' => ['Skopje International Airport', 'Skopje', 'MK'],
        'TGD' => ['Podgorica Airport', 'Podgorica', 'ME'], 'TIA' => ['Tirana International Airport Nënë Tereza', 'Tirana', 'AL'],
        'ATH' => ['Athens International Airport', 'Athens', 'GR'], 'MLA' => ['Malta International Airport', 'Luqa', 'MT'],
        'RIX' => ['Riga International Airport', 'Riga', 'LV'], 'VNO' => ['Vilnius International Airport', 'Vilnius', 'LT'],
        'TLL' => ['Tallinn Airport', 'Tallinn', 'EE'], 'MSQ' => ['Minsk National Airport', 'Minsk', 'BY'],
        'SVO' => ['Sheremetyevo International Airport', 'Moscow', 'RU'], 'LHR' => ['Heathrow Airport', 'London', 'GB'],
        'CDG' => ['Charles de Gaulle Airport', 'Paris', 'FR'], 'FRA' => ['Frankfurt Airport', 'Frankfurt', 'DE'],
        'MUC' => ['Munich Airport', 'Munich', 'DE'], 'AMS' => ['Amsterdam Airport Schiphol', 'Amsterdam', 'NL'],
        'FCO' => ['Leonardo da Vinci–Fiumicino Airport', 'Rome', 'IT'], 'MXP' => ['Milan Malpensa Airport', 'Milan', 'IT'],
        'MAD' => ['Adolfo Suárez Madrid–Barajas Airport', 'Madrid', 'ES'], 'BCN' => ['Barcelona–El Prat Airport', 'Barcelona', 'ES'],
        'LIS' => ['Humberto Delgado Airport', 'Lisbon', 'PT'], 'ZRH' => ['Zurich Airport', 'Zurich', 'CH'],
        'HEL' => ['Helsinki Airport', 'Helsinki', 'FI'], 'ARN' => ['Stockholm Arlanda Airport', 'Stockholm', 'SE'],
        'CPH' => ['Copenhagen Airport', 'Copenhagen', 'DK'], 'OSL' => ['Oslo Airport, Gardermoen', 'Oslo', 'NO'],
        'DUB' => ['Dublin Airport', 'Dublin', 'IE'],
        // Africa
        'CAI' => ['Cairo International Airport', 'Cairo', 'EG'], 'ADD' => ['Addis Ababa Bole International Airport', 'Addis Ababa', 'ET'],
        'NBO' => ['Jomo Kenyatta International Airport', 'Nairobi', 'KE'], 'JNB' => ['O. R. Tambo International Airport', 'Johannesburg', 'ZA'],
        'LOS' => ['Murtala Muhammed International Airport', 'Lagos', 'NG'], 'CMN' => ['Mohammed V International Airport', 'Casablanca', 'MA'],
        'TIP' => ['Mitiga International Airport', 'Tripoli', 'LY'], 'BEN' => ['Benina International Airport', 'Benghazi', 'LY'],
        'MRU' => ['Sir Seewoosagur Ramgoolam International Airport', 'Mauritius', 'MU'],
        // Americas & Oceania
        'JFK' => ['John F. Kennedy International Airport', 'New York', 'US'], 'YYZ' => ['Toronto Pearson International Airport', 'Toronto', 'CA'],
        'SYD' => ['Sydney Kingsford Smith Airport', 'Sydney', 'AU'], 'MEL' => ['Melbourne Airport', 'Melbourne', 'AU'],
    ];
}

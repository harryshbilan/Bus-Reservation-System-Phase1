<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }


function get_schedules(): array
{
    static $schedules = null;
    if ($schedules !== null) {
        return $schedules;
    }

    $rows = [
        ['101', 'Express',  44, 'WiFi,AC,USB',       'Manila',    'Baguio',        '06:00', '11:30', 330,  550, 'Manila — Avenida Bus Terminal, Bay 4'],
        ['102', 'Premium',  36, 'WiFi,AC,USB,Meals', 'Manila',    'Baguio',        '22:00', '03:30', 330,  850, 'Manila — Avenida Bus Terminal, Bay 9'],
        ['205', 'Standard', 52, 'AC',                'Cubao',     'Dagupan',       '07:00', '11:30', 270,  400, 'Cubao — New York Transit Terminal, Bay 2'],
        ['206', 'Express',  44, 'WiFi,AC,USB',       'Cubao',     'Dagupan',       '14:00', '18:30', 270,  600, 'Cubao — New York Transit Terminal, Bay 5'],
        ['310', 'Standard', 52, 'AC',                'PITX',      'Batangas City', '08:00', '10:30', 150,  250, 'PITX — Paranaque Integrated Terminal, Bay 12'],
        ['311', 'Express',  44, 'WiFi,AC,USB',       'PITX',      'Batangas City', '13:00', '15:30', 150,  450, 'PITX — Paranaque Integrated Terminal, Bay 8'],
        ['417', 'Standard', 52, 'AC',                'Cubao',     'Cabanatuan',    '06:30', '09:30', 180,  300, 'Cubao — Genesis Transport Terminal, Bay 3'],
        ['418', 'Express',  44, 'WiFi,AC,USB',       'Cubao',     'Cabanatuan',    '11:00', '14:00', 180,  500, 'Cubao — Genesis Transport Terminal, Bay 7'],
        ['520', 'Standard', 44, 'AC',                'Manila',    'Vigan',         '20:00', '04:00', 480,  700, 'Manila — Partas Terminal, Sampaloc, Bay 1'],
        ['521', 'Premium',  36, 'WiFi,AC,USB,Meals', 'Manila',    'Vigan',         '22:00', '06:00', 480, 1000, 'Manila — Partas Terminal, Sampaloc, Bay 3'],
        ['633', 'Standard', 52, 'AC',                'PITX',      'Lucena',        '07:00', '11:00', 240,  400, 'PITX — Paranaque Integrated Terminal, Bay 6'],
        ['634', 'Express',  44, 'WiFi,AC,USB',       'PITX',      'Lucena',        '12:00', '16:00', 240,  650, 'PITX — Paranaque Integrated Terminal, Bay 10'],
        ['748', 'Standard', 44, 'AC',                'Cubao',     'Baler',         '05:00', '10:00', 300,  500, 'Cubao — Baliwag Transit Terminal, Bay 1'],
        ['749', 'Express',  44, 'WiFi,AC,USB',       'Cubao',     'Baler',         '08:00', '13:00', 300,  700, 'Cubao — Baliwag Transit Terminal, Bay 4'],
        ['812', 'Standard', 60, 'AC',                'Cebu City', 'Moalboal',      '07:00', '10:00', 180,  150, 'Cebu City — South Bus Terminal, Bay 8'],
        ['813', 'Express',  44, 'WiFi,AC,USB',       'Cebu City', 'Moalboal',      '13:00', '16:00', 180,  250, 'Cebu City — South Bus Terminal, Bay 11'],
    ];

    $schedules = [];
    foreach ($rows as $i => $r) {
        $id = $i + 1;
        $schedules[$id] = [
            'schedule_id'      => $id,
            'bus_id'           => $id, 
            'bus_number'       => $r[0],
            'bus_type'         => $r[1],
            'total_seats'      => $r[2],
            'amenities'        => $r[3],
            'origin'           => $r[4],
            'destination'      => $r[5],
            'departure_time'   => $r[6],
            'arrival_time'     => $r[7],
            'duration_minutes' => $r[8],
            'fare_per_seat'    => $r[9],
            'boarding_point'   => $r[10],
        ];
    }
    return $schedules;
}

function get_schedule(int $scheduleId): ?array
{
    $all = get_schedules();
    return $all[$scheduleId] ?? null;
}

function search_schedules(string $origin, string $destination): array
{
    $matches = array_filter(get_schedules(), function ($s) use ($origin, $destination) {
        $okOrigin = $origin === '' || stripos($s['origin'], $origin) !== false;
        $okDest = $destination === '' || stripos($s['destination'], $destination) !== false;
        return $okOrigin && $okDest;
    });
    return $matches;
}

function sort_schedules(array $schedules, string $sort): array
{
    $key = match ($sort) {
        'fare'     => 'fare_per_seat',
        'duration' => 'duration_minutes',
        default    => 'departure_time',
    };
    usort($schedules, fn($a, $b) => $a[$key] <=> $b[$key]);
    return $schedules;
}

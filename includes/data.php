<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/database.php';

function get_schedules(): array
{
    global $pdo;

    static $schedules = null;

    if ($schedules !== null) {
        return $schedules;
    }

   $stmt = $pdo->query("
    SELECT 
        s.schedule_id,
        b.bus_number,
        b.bus_type,
        b.total_seats,
        b.amenities,
        r.origin,
        r.destination,
        s.departure_time,
        s.arrival_time,
        s.fare,
        r.boarding_point
    FROM schedules s
    JOIN buses b 
        ON s.bus_id = b.bus_id
    JOIN routes r 
        ON s.route_id = r.route_id
");

$rows = $stmt->fetchAll(PDO::FETCH_NUM);

    $schedules = [];
    foreach ($rows as $i => $r) {
        $id = $i + 1;
     $schedules[$id] = [
    'schedule_id' => $r[0],
    'bus_id' => $r[0],
    'bus_number' => $r[1],
    'bus_type' => $r[2],
    'total_seats' => $r[3],
    'amenities' => $r[4],
    'origin' => $r[5],
    'destination' => $r[6],
    'departure_time' => $r[7],
    'arrival_time' => $r[8],
    'fare_per_seat' => $r[9],
'duration_minutes' => (
    strtotime($r[8]) >= strtotime($r[7])
    ? (strtotime($r[8]) - strtotime($r[7])) / 60
    : ((strtotime($r[8]) + 86400) - strtotime($r[7])) / 60
),
'boarding_point' => $r[10],
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
    $origin = strtolower(trim($origin));
    $destination = strtolower(trim($destination));

    return array_filter(get_schedules(), function ($s) use ($origin, $destination) {

        $routeOrigin = strtolower(trim($s['origin']));
        $routeDestination = strtolower(trim($s['destination']));

        return str_contains($routeOrigin, $origin)
            && str_contains($routeDestination, $destination);
    });
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

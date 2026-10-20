<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * One row per origin → destination pair that still has departures ahead,
 * busiest routes first. Used by the home and booking pages.
 */
function upcoming_routes(PDO $pdo, DateTimeImmutable $now): array
{
    $stmt = $pdo->prepare("
        SELECT rt.origin, rt.destination,
               COUNT(*) AS departures,
               MIN(s.fare) AS lowest_fare,
               MIN(TIMESTAMP(s.travel_date, s.departure_time)) AS next_departure,
               GROUP_CONCAT(DISTINCT b.bus_type ORDER BY b.bus_type SEPARATOR ', ') AS bus_types,
               GROUP_CONCAT(DISTINCT o.operator_name ORDER BY o.operator_name SEPARATOR ', ') AS operators
        FROM schedules s
        JOIN routes rt ON rt.route_id = s.route_id
        JOIN buses b ON b.bus_id = s.bus_id
        LEFT JOIN bus_operators o ON o.operator_id = b.operator_id
        WHERE TIMESTAMP(s.travel_date, s.departure_time) > ?
        GROUP BY rt.origin, rt.destination
        ORDER BY departures DESC, rt.origin, rt.destination
    ");
    $stmt->execute([$now->format('Y-m-d H:i:s')]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Every city that appears on a route, for search suggestions. */
function route_cities(PDO $pdo): array
{
    $cities = $pdo->query("SELECT origin FROM routes UNION SELECT destination FROM routes")
        ->fetchAll(PDO::FETCH_COLUMN);
    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);

    return $cities;
}

/** Photo, short label and one-liner for the places we like to show off. */
function destination_profiles(): array
{
    return [
        'el nido' => ['El Nido, Palawan', 'el nido palawan.jpg', 'Island paradise', 'Where crystal waters meet limestone cliffs'],
        'siargao' => ['Siargao', 'siargao.jpg', 'Surf capital', 'Surf, sun and slow island mornings'],
        'coron' => ['Coron, Palawan', 'coron palawan.jpg', 'Dive destination', 'Shipwrecks, lagoons and clear water'],
        'baguio' => ['Baguio City', 'baguio.jpg', 'Summer capital', 'Cool air, pine trees and strawberry stops'],
        'vigan' => ['Vigan, Ilocos Sur', 'vigan.jpg', 'Heritage city', 'Cobblestone streets and Spanish-era houses'],
        'cebu' => ['Cebu City', 'cebu.jpg', 'Queen City of the South', 'Culture, beaches and lechon'],
    ];
}

/** Find the upcoming route that serves a place, matched loosely by name. */
function route_to(array $routes, string $keyword): ?array
{
    $best = null;

    foreach ($routes as $route) {
        if (stripos($route['destination'], $keyword) === false) {
            continue;
        }

        if ($best === null || $route['next_departure'] < $best['next_departure']) {
            $best = $route;
        }
    }

    return $best;
}

/** URL for an image in assets/images, safe for names with spaces. */
function image_url(string $file): string
{
    return '../assets/images/' . rawurlencode($file);
}

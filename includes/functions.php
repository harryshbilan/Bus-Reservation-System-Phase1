<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }

const PROGRESS_STEPS = ['routes', 'bus', 'seats', 'details', 'fare', 'payment', 'ticket'];
const PROGRESS_LABELS = [
    'routes'  => 'Available Buses',
    'bus'     => 'Bus Selection',
    'seats'   => 'Seat Selection',
    'details' => 'Passenger Info',
    'fare'    => 'Fare Summary',
    'payment' => 'Payment',
    'ticket'  => 'Ticket',
];

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function peso($amount): string
{
    return '₱' . number_format((float) $amount, 0);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}


function require_booking_keys(array $keys, string $fallbackUrl): void
{
    foreach ($keys as $key) {
        if (!isset($_SESSION['booking'][$key]) || $_SESSION['booking'][$key] === '') {
            redirect($fallbackUrl);
        }
    }
}

function format_duration(int $minutes): string
{
    $hrs = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return sprintf('%dh %02dm', $hrs, $mins);
}

function fare_breakdown(float $farePerSeat, int $seatCount): array
{
    $base = $farePerSeat * $seatCount;
    $serviceFee = round($base * 0.05);
    $tax = round($base * 0.08);
    $total = $base + $serviceFee + $tax;
    return [$base, $serviceFee, $tax, $total];
}

function cancellation_breakdown(float $totalFare): array
{
    $fee = round($totalFare * 0.1);
    $refund = round($totalFare * 0.9);
    return [$fee, $refund];
}

function badge_class(string $busType): string
{
    return match ($busType) {
        'Express' => 'badge type-express',
        'Premium' => 'badge type-premium',
        default   => 'badge type-standard',
    };
}

function generate_ref(string $prefix): string
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $ref = '';
    for ($i = 0; $i < 6; $i++) {
        $ref .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $prefix . '-' . $ref;
}

function booked_seat_ids(int $busId, string $travelDate): array
{
    $key = $busId . '|' . $travelDate;
    return $_SESSION['seat_bookings'][$key] ?? [];
}

function mark_seats_booked(int $busId, string $travelDate, array $seatIds): void
{
    $key = $busId . '|' . $travelDate;
    $existing = $_SESSION['seat_bookings'][$key] ?? [];
    $_SESSION['seat_bookings'][$key] = array_values(array_unique(array_merge($existing, $seatIds)));
}

function release_seats(int $busId, string $travelDate, array $seatIds): void
{
    $key = $busId . '|' . $travelDate;
    $existing = $_SESSION['seat_bookings'][$key] ?? [];
    $_SESSION['seat_bookings'][$key] = array_values(array_diff($existing, $seatIds));
}

function seat_layout(int $busId, int $totalSeats, string $travelDate): array
{
    $booked = booked_seat_ids($busId, $travelDate);
    $cols = ['A', 'B', 'C', 'D'];
    $layout = [];
    for ($seatNum = 1; $seatNum <= $totalSeats; $seatNum++) {
        $rowIndex = intdiv($seatNum - 1, 4);
        $col = $cols[($seatNum - 1) % 4];
        $layout[$rowIndex][$col] = [
            'id'     => $seatNum,
            'label'  => (($rowIndex) + 1) . $col,
            'booked' => in_array($seatNum, $booked, true),
        ];
    }
    ksort($layout, SORT_NUMERIC);
    $rows = [];
    foreach ($layout as $row) {
        $rows[] = [$row['A'] ?? null, $row['B'] ?? null, $row['C'] ?? null, $row['D'] ?? null];
    }
    return $rows;
}

function available_seat_count(int $busId, int $totalSeats, string $travelDate): int
{
    return $totalSeats - count(booked_seat_ids($busId, $travelDate));
}

function seat_button(?array $seat, array $selected): void
{
    if (!$seat) {
        echo '<div></div>';
        return;
    }
    $cls = 'seat-btn';
    if ($seat['booked']) $cls .= ' booked';
    if (in_array($seat['id'], $selected, true)) $cls .= ' selected';
    printf(
        '<button type="button" class="%s" data-seat-id="%d" data-seat-label="%s"%s>%s</button>',
        $cls,
        $seat['id'],
        h($seat['label']),
        $seat['booked'] ? ' disabled' : '',
        h($seat['label'])
    );
}
function selected_labels(array $layout, array $selectedIds): array
{
    $labelsById = [];
    foreach ($layout as $row) {
        foreach ($row as $seat) {
            if ($seat && in_array($seat['id'], $selectedIds, true)) {
                $labelsById[$seat['id']] = $seat['label'];
            }
        }
    }
    $ordered = [];
    foreach ($selectedIds as $id) {
        if (isset($labelsById[$id])) $ordered[] = $labelsById[$id];
    }
    return $ordered;
}

function seat_labels_ordered(array $seatIds): array
{
    $cols = ['A', 'B', 'C', 'D'];
    return array_map(function ($seatNum) use ($cols) {
        $rowIndex = intdiv($seatNum - 1, 4);
        $col = $cols[($seatNum - 1) % 4];
        return ($rowIndex + 1) . $col;
    }, $seatIds);
}

function save_reservation(array $reservation): int
{
    if (!isset($_SESSION['reservations']) || !is_array($_SESSION['reservations'])) {
        $_SESSION['reservations'] = [];
    }
    $id = count($_SESSION['reservations']) + 1;
    $reservation['reservation_id'] = $id;
    $_SESSION['reservations'][$id] = $reservation;
    return $id;
}

function get_reservation(int $id): ?array
{
    return $_SESSION['reservations'][$id] ?? null;
}

function update_reservation(int $id, array $changes): void
{
    if (isset($_SESSION['reservations'][$id])) {
        $_SESSION['reservations'][$id] = array_merge($_SESSION['reservations'][$id], $changes);
    }
}

function find_reservation_by_ticket(string $ticketNumber, string $email): ?array
{
    foreach ($_SESSION['reservations'] ?? [] as $res) {
        if (
            strcasecmp($res['ticket_number'], $ticketNumber) === 0
            && strcasecmp($res['passenger']['email'], $email) === 0
            && in_array($res['status'], ['CONFIRMED', 'PENDING'], true)
        ) {
            return $res;
        }
    }
    return null;
}

function ticket_number_exists(string $ticketNumber): bool
{
    foreach ($_SESSION['reservations'] ?? [] as $res) {
        if ($res['ticket_number'] === $ticketNumber) {
            return true;
        }
    }
    return false;
}

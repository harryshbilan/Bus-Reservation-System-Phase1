<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';

$admin = require_admin();

$bookings = [];
$error = null;


$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$search = is_string($search) ? trim($search) : '';
$status = is_string($status) ? $status : '';

$statusOptions = [
    '' => 'All statuses',
    'PENDING' => 'Pending',
    'PAID' => 'Paid',
    'CANCELLED' => 'Cancelled',
];

if (!array_key_exists($status, $statusOptions)) {
    $status = '';
}

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(
        LOCATE(?, r.ticket_number) > 0
        OR LOCATE(?, pax.name) > 0
        OR LOCATE(?, pax.email) > 0
    )';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if ($status !== '') {
    $conditions[] = 'r.status = ?';
    $params[] = $status;
}

$whereSql = $conditions
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

try {
    $stmt = $pdo->prepare("
        SELECT
            r.ticket_number,
            r.status,
            pax.name AS passenger_name,
            pax.email AS passenger_email,
            b.bus_number,
            rt.origin,
            rt.destination,
            s.travel_date,
            s.departure_time,
            GROUP_CONCAT(
                r.seat_number
                ORDER BY
                    CAST(r.seat_number AS UNSIGNED),
                    r.seat_number
                SEPARATOR ', '
            ) AS seats,
            SUM(COALESCE(pay.amount_due, 0)) AS amount_due,
            SUM(COALESCE(pay.amount_paid, 0)) AS amount_paid
        FROM reservations r
        JOIN passengers pax
            ON pax.passenger_id = r.passenger_id
        JOIN schedules s
            ON s.schedule_id = r.schedule_id
        JOIN buses b
            ON b.bus_id = s.bus_id
        JOIN routes rt
            ON rt.route_id = s.route_id
        LEFT JOIN (
            SELECT
                reservation_id,
                SUM(
                    CASE WHEN status = 'PENDING'
                        THEN amount ELSE 0 END
                ) AS amount_due,
                SUM(
                    CASE WHEN status = 'PAID'
                        THEN amount ELSE 0 END
                ) AS amount_paid
            FROM payments
            GROUP BY reservation_id
        ) pay ON pay.reservation_id = r.reservation_id
        $whereSql
        GROUP BY
            r.ticket_number,
            r.passenger_id,
            r.schedule_id,
            r.status,
            pax.name,
            pax.email,
            b.bus_number,
            rt.origin,
            rt.destination,
            s.travel_date,
            s.departure_time
        ORDER BY MAX(r.reservation_id) DESC
        LIMIT 100
    ");

    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(
        'BUSWAY reservation list failed: ' . $exception->getMessage()
    );

    $error = 'Reservations could not be loaded. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservations | BUSWAY Admin</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f7f3ee;
            color: #1c1208;
            font-family: Arial, sans-serif;
        }

        header {
            padding: 22px 32px;
            background: white;
            border-bottom: 4px solid #e8940a;
        }

        main {
            max-width: 1400px;
            margin: 32px auto;
            padding: 0 24px;
        }

        a { color: #684000; }

        .table-container {
            overflow-x: auto;
            background: white;
            border: 1px solid #ded6cc;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #fff3dc;
            white-space: nowrap;
        }

        td small {
            display: block;
            margin-top: 6px;
            color: #655c52;
        }

        .money { white-space: nowrap; }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 4px;
            background: #eee;
            font-size: 13px;
            font-weight: bold;
        }

        .pending { background: #fff0c2; color: #704c00; }
        .paid { background: #dff3e4; color: #1d6030; }
        .cancelled { background: #ffe3df; color: #922d22; }

        .error {
            padding: 16px;
            background: #ffe3df;
            color: #922d22;
        }

        .filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
    margin: 24px 0;
    padding: 20px;
    background: white;
    border: 1px solid #ded6cc;
    border-radius: 8px;
}

    .filters .search-field {
    flex: 1 1 280px;
    min-width: 0;
}

    .filters label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

    .filters input,
    .filters select {
    width: 100%;
    min-height: 44px;
    padding: 10px;
    border: 1px solid #aaa;
    border-radius: 4px;
    font: inherit;
}

    .filters button {
    min-height: 44px;
    padding: 10px 20px;
    border: 0;
    border-radius: 4px;
    background: #e8940a;
    color: #1c1208;
    font: inherit;
    font-weight: bold;
    cursor: pointer;
}

    .filters > a {
    padding: 12px 0;
}
    </style>
</head>
<body>
<header>
    <strong>BUSWAY Admin — Reservations</strong>
</header>

<main>
    <p><a href="index.php">← Back to dashboard</a></p>

    <h1>Reservations</h1>
    <p>
    Showing up to 100 most recent matching booking groups.
    Amount due is unpaid; amount paid is recorded as received.
</p>

<form method="get" action="reservations.php" class="filters">
    <div class="search-field">
        <label for="search">Find a reservation</label>
        <input
            type="search"
            id="search"
            name="search"
            placeholder="Booking reference, passenger name or email"
            value="<?= admin_h($search) ?>"
        >
    </div>

    <div>
        <label for="status">Booking status</label>
        <select id="status" name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
                <option
                    value="<?= admin_h($value) ?>"
                    <?= $status === $value ? 'selected' : '' ?>
                >
                    <?= admin_h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit">Apply filters</button>
    <a href="reservations.php">Clear filters</a>
</form>

    <?php if ($error): ?>
        <p class="error" role="alert"><?= admin_h($error) ?></p>
    <?php elseif (!$bookings): ?>
        <p>No reservations match your search and status filter.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Passenger</th>
                    <th scope="col">Trip</th>
                    <th scope="col">Seats</th>
                    <th scope="col">Amount due</th>
                    <th scope="col">Amount paid</th>
                    <th scope="col">Status</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $statusClass = match ($booking['status']) {
                        'PENDING' => 'pending',
                        'PAID' => 'paid',
                        'CANCELLED' => 'cancelled',
                        default => ''
                    };
                    ?>
                    <tr>
                        <td>
                            <?= admin_h($booking['ticket_number']) ?>
                        </td>

                        <td>
                            <?= admin_h($booking['passenger_name']) ?>
                            <small>
                                <?= admin_h($booking['passenger_email']) ?>
                            </small>
                        </td>

                        <td>
                            <?= admin_h($booking['origin']) ?>
                            →
                            <?= admin_h($booking['destination']) ?>

                            <small>
                                Bus <?= admin_h($booking['bus_number']) ?>
                            </small>

                            <small>
                                <?= admin_h($booking['travel_date']) ?>
                                at
                                <?= admin_h($booking['departure_time']) ?>
                            </small>
                        </td>

                        <td><?= admin_h($booking['seats']) ?></td>

                        <td class="money">
                            ₱<?= number_format(
                                (float) $booking['amount_due'],
                                2
                            ) ?>
                        </td>

                        <td class="money">
                            ₱<?= number_format(
                                (float) $booking['amount_paid'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= admin_h($booking['status']) ?>
                            </span>

                            <?php if ($booking['status'] === 'PENDING'): ?>
                    <small>
                         <a href="confirm_payment.php?ticket=<?= rawurlencode(
                             $booking['ticket_number']
                        ) ?>">
                                Review cash payment
                        </a>
                            </small>
                    <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
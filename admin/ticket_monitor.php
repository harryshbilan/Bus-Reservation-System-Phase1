<?php

if (!defined('BUSWAY_ADMIN')) {
    http_response_code(403);
    exit();
}

// Aggregate payments first so multiple payment rows cannot duplicate seats.
function ticket_monitor_sql(): string
{
    return "SELECT r.ticket_number,
        CASE WHEN COUNT(DISTINCT r.status)=1 THEN MIN(r.status) ELSE 'REVIEW' END AS status,
        MAX(r.reservation_id) AS newest_id,
        COUNT(*) AS seat_count,
        GROUP_CONCAT(DISTINCT pax.name SEPARATOR ', ') AS passenger_name,
        GROUP_CONCAT(DISTINCT pax.email SEPARATOR ', ') AS passenger_email,
        GROUP_CONCAT(DISTINCT b.bus_number SEPARATOR ', ') AS buses,
        GROUP_CONCAT(DISTINCT CONCAT(rt.origin,' → ',rt.destination) SEPARATOR ', ') AS route,
        MIN(s.travel_date) AS travel_date, MIN(s.departure_time) AS departure_time,
        SUM(COALESCE(pay.amount_due,0)) AS amount_due,
        SUM(COALESCE(pay.amount_paid,0)) AS amount_paid,
        SUM(COALESCE(pay.amount_recorded,0)) AS amount_recorded,
        GROUP_CONCAT(DISTINCT pay.methods SEPARATOR ', ') AS payment_methods
      FROM reservations r
      JOIN passengers pax ON pax.passenger_id=r.passenger_id
      JOIN schedules s ON s.schedule_id=r.schedule_id
      JOIN buses b ON b.bus_id=s.bus_id
      JOIN routes rt ON rt.route_id=s.route_id
      LEFT JOIN (
        SELECT reservation_id,
          SUM(CASE WHEN status='PENDING' THEN amount ELSE 0 END) AS amount_due,
          SUM(CASE WHEN status='PAID' THEN amount ELSE 0 END) AS amount_paid,
          SUM(amount) AS amount_recorded,
          GROUP_CONCAT(DISTINCT payment_method) AS methods
        FROM payments GROUP BY reservation_id
      ) pay ON pay.reservation_id=r.reservation_id
      GROUP BY r.ticket_number";
}
function ticket_monitor_totals(PDO $pdo): array
{
    $q = $pdo->query(
        "SELECT status, COUNT(*) AS tickets,
        SUM(amount_due) AS due, SUM(amount_paid) AS paid, SUM(amount_recorded) AS recorded
        FROM (" .
            ticket_monitor_sql() .
            ') groups_by_ticket GROUP BY status'
    );

    return $q->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}
function monitor_cards(array $totals, bool $showMoney = true): void
{
    $all = array_sum(array_column($totals, 'tickets'));
    $cards = [
        ['', 'All tickets', $all, 'All booking references'],
        [
            'PENDING',
            'Pending',
            $totals['PENDING']['tickets'] ?? 0,
            '₱' . number_format((float) ($totals['PENDING']['due'] ?? 0), 2) . ' awaiting payment'
        ],
        [
            'PAID',
            'Paid',
            $totals['PAID']['tickets'] ?? 0,
            $showMoney ? '₱' . number_format((float) ($totals['PAID']['paid'] ?? 0), 2) . ' recorded on paid bookings' : 'Fully paid bookings'
        ],
        [
            'CANCELLED',
            'Cancelled',
            $totals['CANCELLED']['tickets'] ?? 0,
            $showMoney ? '₱' . number_format((float) ($totals['CANCELLED']['recorded'] ?? 0), 2) . ' original recorded value' : 'Cancelled bookings'
        ]
    ];
    echo '<div class="monitor-cards">';

    foreach ($cards as [$status, $label, $count, $note]) {
        echo '<a class="monitor-card" href="tickets.php?status=' .
            rawurlencode($status) .
            '"><span>' .
            admin_h($label) .
            '</span><strong>' .
            (int) $count .
            '</strong><small>' .
            admin_h($note) .
            '</small></a>';
    }
    echo '</div>';

    if (isset($totals['REVIEW'])) {
        echo '<p class="notice">' .
            (int) $totals['REVIEW']['tickets'] .
            ' ticket(s) have mixed statuses. <a href="tickets.php?status=REVIEW">Review them</a>.</p>';
    }
}

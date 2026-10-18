<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
$error = null;
$buses = [];
$schedules = [];
$id = filter_var($_GET['bus'] ?? null, FILTER_VALIDATE_INT);
try {
    $buses = $pdo
        ->query('SELECT bus_id,bus_number,bus_type,total_seats FROM buses ORDER BY bus_id')
        ->fetchAll(PDO::FETCH_ASSOC);
    if ($id) {
        $q = $pdo->prepare("SELECT s.travel_date,s.departure_time,s.arrival_time,s.fare,rt.origin,rt.destination,rt.boarding_point
            FROM schedules s JOIN routes rt ON rt.route_id=s.route_id WHERE s.bus_id=? ORDER BY s.travel_date DESC,s.departure_time");
        $q->execute([$id]);
        $schedules = $q->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Fleet records could not be loaded.';
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Fleet | BUSWAY Admin</title>
        <link rel="stylesheet" href="../assets/css/monitor.css" />
    </head>
    <body>
        <header class="admin-topbar">
    <a class="admin-brand" href="index.php">
        <span class="admin-brand-icon" aria-hidden="true">B</span>
        <span class="admin-brand-text">
            <strong>BUSWAY</strong>
            <small>Admin workspace</small>
        </span>
    </a>

    <nav class="admin-navigation" aria-label="Admin navigation">
        <a href="index.php">Dashboard</a>
        <a href="tickets.php">Tickets</a>
        <a href="fleet.php" aria-current="page">Fleet</a>
        <a class="admin-public-link" href="../public/index.php">
            Passenger website <span aria-hidden="true">↗</span>
        </a>
    </nav>
</header>
        <main class="fleet-page">
    <div class="fleet-heading">
        <div>
            <p class="fleet-eyebrow">FLEET OVERVIEW</p>
            <h1>Fleet & departures</h1>
            <p class="muted">
                Explore your buses, seating capacity and departure schedules.
            </p>
        </div>

        <?php if (!$error): ?>
            <div class="fleet-count">
                <strong><?= count($buses) ?></strong>
                <span>Registered buses</span>
            </div>
        <?php endif; ?>
    </div>
            <p class="notice">
                These are BUSWAY's stored records. DEMO buses and terminals are sample data, not
                verified operator services.
            </p>
           <details class="fleet-references">
        <summary>Official operator & terminal references</summary>
                <p>
                    Use published operator details when preparing new fleet records. These links do
                    not verify the sample departures listed below.
                </p>
                <ul>
                    <li>
                        <a
                            href="https://www.victoryliner.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Victory Liner — official booking and service information
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://jacliner.com/schedules"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            JAC Liner — published daily schedules
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://pitx.ph/routes-guide/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            PITX — official routes and terminal guide
                        </a>
                    </li>
                </ul>
            </details>
            <?php if($error): ?>
            <p class="error"><?= admin_h($error) ?></p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Bus number</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Schedules</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($buses as $b): ?>
                        <tr>
                            <td><?= admin_h($b['bus_number']) ?></td>
                            <td><?= admin_h($b['bus_type']) ?></td>
                            <td><?= (int)$b['total_seats'] ?></td>
                            <td>
    <a
        class="fleet-view"
        href="fleet.php?bus=<?= (int)$b['bus_id'] ?>#fleet-departures"
        aria-label="<?= admin_h('View departures for bus ' . $b['bus_number']) ?>"
    >
        View departures <span aria-hidden="true">→</span>
    </a>
    </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if($id): ?>
            <h2 id="fleet-departures">Departures for selected bus</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th>Boarding point</th>
                            <th>Date</th>
                            <th>Departure</th>
                            <th>Arrival time</th>
                            <th>Base fare</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($schedules as $s): ?>
                        <tr>
                            <td><?= admin_h($s['origin'].' → '.$s['destination']) ?></td>
                            <td><?= admin_h($s['boarding_point']) ?></td>
                            <td><?= admin_h($s['travel_date']) ?></td>
                            <td><?= admin_h(date('g:i A',strtotime($s['departure_time']))) ?></td>
                            <td><?= admin_h(date('g:i A',strtotime($s['arrival_time']))) ?><?= $s['arrival_time']<$s['departure_time']?' (+1 day)':'' ?></td>
                            <td>₱<?= number_format((float)$s['fare'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$schedules): ?>
                        <tr><td colspan="6">No schedules found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </body>
</html>

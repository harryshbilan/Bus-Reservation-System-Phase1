<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_role_admin();
require_once __DIR__ . '/layout.php';
$services = [];
$error = null;

try {
    $services = $pdo->query(
        'SELECT * FROM published_services
         ORDER BY operator_name, origin, operating_days, departure_time'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Published timetables could not be loaded. Check that the SQL update was imported.';
}
?>
<?php admin_page_start('Published timetables', 'timetables', $admin); ?>

        <h1>Published operator timetables</h1>
        <p class="notice">
            Reference only — not available for booking through BUSWAY.
            Published times may change. Staff must verify the intended travel date,
            boarding location, fare, assigned vehicle and seat allocation before
            creating a bookable departure. Arrival times have not been supplied.
        </p>
        <?php if ($error): ?>
            <p class="error"><?= admin_h($error) ?></p>
        <?php else: ?>
            <p><?= count($services) ?> published timetable entries.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Operator / reference</th>
                            <th scope="col">Route</th>
                            <th scope="col">Operating days</th>
                            <th scope="col">Departure (PH time)</th>
                            <th scope="col">Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong><?= admin_h($service['operator_name']) ?></strong><br>
                                    <?= admin_h($service['reference_code']) ?>
                                </td>
                                <td>
                                    <?= admin_h($service['origin'] . ' → ' . $service['destination']) ?><br>
                                    <small><?= admin_h($service['service_description']) ?></small>
                                </td>
                                <td><?= admin_h($service['operating_days']) ?></td>
                                <td><?= admin_h(date('g:i A', strtotime($service['departure_time']))) ?></td>
                                <td>
                                    Not bookable<br>
                                    Source checked: <?= admin_h($service['source_checked_on']) ?><br>
                                    <a href="https://jacliner.com/schedules" target="_blank" rel="noopener noreferrer">
                                        Published source ↗
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$services): ?>
                            <tr><td colspan="5">No published timetable entries yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
<?php admin_page_end(); ?>

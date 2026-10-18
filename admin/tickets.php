<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require __DIR__ . '/ticket_monitor.php';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$options = [
    '' => 'All statuses',
    'PENDING' => 'Pending',
    'PAID' => 'Paid',
    'CANCELLED' => 'Cancelled',
    'REVIEW' => 'Needs review'
];
if (!isset($options[$status])) {
    $status = '';
}
$page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$error = null;
$rows = [];
$totals = [];
$count = 0;
$pages = 1;
try {
    $totals = ticket_monitor_totals($pdo);
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] =
            '(LOCATE(?,ticket_number)>0 OR LOCATE(?,passenger_name)>0 OR LOCATE(?,passenger_email)>0 OR LOCATE(?,buses)>0)';
        $params = [$search, $search, $search, $search];
    }
    if ($status !== '') {
        $where[] = 'status=?';
        $params[] = $status;
    }
    $from =
        ' FROM (' .
        ticket_monitor_sql() .
        ') ticket_groups' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $q = $pdo->prepare('SELECT COUNT(*)' . $from);
    $q->execute($params);
    $count = (int) $q->fetchColumn();
    $pages = max(1, (int) ceil($count / 25));
    $page = min($page, $pages);
    $offset = ($page - 1) * 25;
    $q = $pdo->prepare('SELECT *' . $from . ' ORDER BY newest_id DESC LIMIT 25 OFFSET ' . $offset);
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Tickets could not be loaded. Please try again.';
}
function monitor_page_url(int $page, string $search, string $status): string
{
    return 'tickets.php?' . http_build_query(compact('page', 'search', 'status'));
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Ticket monitor | BUSWAY Admin</title>
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
        <a href="tickets.php" aria-current="page">Tickets</a>
        <a href="fleet.php">Fleet</a>

        <a class="admin-public-link" href="../public/index.php">
            Passenger website <span aria-hidden="true">↗</span>
        </a>
    </nav>
</header>
        <main>
            <h1>Ticket monitor</h1>
            <p class="muted">
                Every booking reference, including past and cancelled trips. One ticket may cover
                several seats.
            </p>
            <?php if ($error): ?>
            <p class="error" role="alert"><?= admin_h($error) ?></p>
            <?php else: ?>
            <?php monitor_cards($totals); ?>
            <p class="muted">
                Cards show all-time totals, independent of filters. Cancelled value is not revenue
                or a refund total. Refresh to load the latest changes.
            </p>
            <form class="filters" method="get">
                <div>
                    <label for="search">Find a ticket</label>
                    <input
                        type="search"
                        name="search"
                        id="search"
                        value="<?= admin_h($search) ?>"
                        placeholder="Reference, passenger, email or bus"
                    />
                </div>
                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <?php foreach($options as $value=>$label): ?>
                        <option value="<?= admin_h($value) ?>" <?= $value===$status?'selected':'' ?>><?= admin_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button>Apply filters</button>
                <a href="tickets.php">Clear / refresh</a>
            </form>
            <p>
                <?= $count ?> matching ticket<?= $count===1?'':'s' ?> · Page <?= $page ?> of
                <?= $pages ?>
            </p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Reference / passenger</th>
                            <th>Trip</th>
                            <th>Seats</th>
                            <th>Pending payment</th>
                            <th>Recorded paid</th>
                            <th>Status / actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $row): ?>
                        <tr>
                            <td>
                                <a href="ticket.php?ticket=<?= rawurlencode($row['ticket_number']) ?>"><?= admin_h($row['ticket_number']) ?></a>
                                <small>
                                    <?= admin_h($row['passenger_name']) ?>
                                    <br />
                                    <?= admin_h($row['passenger_email']) ?>
                                </small>
                            </td>
                            <td>
                                <?= admin_h($row['route']) ?>
                                <small>
                                    Bus <?= admin_h($row['buses']) ?>
                                    <br />
                                    <?= admin_h(date('M j, Y',strtotime($row['travel_date']))) ?> · <?= admin_h(date('g:i A',strtotime($row['departure_time']))) ?>
                                </small>
                            </td>
                            <td><?= (int)$row['seat_count'] ?></td>
                            <td>₱<?= number_format((float)$row['amount_due'],2) ?></td>
                            <td>₱<?= number_format((float)$row['amount_paid'],2) ?></td>
                            <td>
                                <span class="status <?= admin_h($row['status']) ?>"><?= admin_h($row['status']) ?></span>
                                <small>
                                    <a href="ticket.php?ticket=<?= rawurlencode($row['ticket_number']) ?>">View ticket</a>
                                </small>
                                <?php if($row['status']==='PENDING'): ?>
                                <small>
                                    <a href="confirm_payment.php?ticket=<?= rawurlencode($row['ticket_number']) ?>">
                                        Review cash payment
                                    </a>
                                </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$rows): ?>
                        <tr><td colspan="6">No tickets match these filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav class="pagination" aria-label="Ticket pages">
                <?php if($page>1): ?>
                <a href="<?= admin_h(monitor_page_url($page-1,$search,$status)) ?>">← Previous</a>
                <?php endif; ?>
                <?php if($page<$pages): ?>
                <a href="<?= admin_h(monitor_page_url($page+1,$search,$status)) ?>">Next →</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </main>
    </body>
</html>

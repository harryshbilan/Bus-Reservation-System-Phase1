<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require __DIR__ . '/ticket_monitor.php';
require_once __DIR__ . '/layout.php';
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

admin_page_start('Tickets', 'tickets', $admin);
?>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Ticket monitor</span>
        <h1>Tickets</h1>
        <p>Every booking reference, past and cancelled included. One ticket can cover several seats.</p>
    </div>
</div>
<?php if ($error): ?>
    <p class="error" role="alert"><?= admin_h($error) ?></p>
<?php else: ?>
    <?php monitor_cards($totals, is_admin_role($admin)); ?>
    <form class="filters" method="get">
        <div>
            <label for="search">Find a ticket</label>
            <input type="search" name="search" id="search" value="<?= admin_h($search) ?>" placeholder="Reference, passenger, email or bus">
        </div>
        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach ($options as $value => $label): ?>
                    <option value="<?= admin_h($value) ?>" <?= $value === $status ? 'selected' : '' ?>><?= admin_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Search</button>
        <a class="button ghost" href="tickets.php">Clear</a>
    </form>
    <p class="muted"><?= $count ?> matching ticket<?= $count === 1 ? '' : 's' ?> · page <?= $page ?> of <?= $pages ?>. The cards above are all-time totals and ignore the search.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference / passenger</th>
                    <th>Trip</th>
                    <th>Seats</th>
                    <th>Payment</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $methods = array_filter(array_map('trim', explode(',', (string) $row['payment_methods'])));
                    $isCash = in_array('Cash', $methods, true);
                    ?>
                    <tr>
                        <td>
                            <a class="ad-ref" href="ticket.php?ticket=<?= rawurlencode($row['ticket_number']) ?>"><?= admin_h($row['ticket_number']) ?></a>
                            <small><?= admin_h($row['passenger_name']) ?><br><?= admin_h($row['passenger_email']) ?></small>
                        </td>
                        <td>
                            <?= admin_h($row['route']) ?>
                            <small>Bus <?= admin_h($row['buses']) ?><br><?= admin_h(admin_when($row['travel_date'], $row['departure_time'])) ?></small>
                        </td>
                        <td><?= (int) $row['seat_count'] ?></td>
                        <td><?php foreach ($methods as $method): ?><span class="method"><?= admin_h($method) ?></span> <?php endforeach; ?></td>
                        <td>₱<?= number_format((float) $row['amount_due'], 2) ?></td>
                        <td>₱<?= number_format((float) $row['amount_paid'], 2) ?></td>
                        <td>
                            <span class="status <?= admin_h($row['status']) ?>"><?= admin_h($row['status']) ?></span>
                            <?php if ($row['status'] === 'PENDING' && $isCash): ?>
                                <small><a href="confirm_payment.php?ticket=<?= rawurlencode($row['ticket_number']) ?>">Confirm cash →</a></small>
                            <?php elseif ($row['status'] === 'PENDING'): ?>
                                <small>Waiting for online payment</small>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'PENDING'): ?>
                                <small><a href="discount.php?ticket=<?= rawurlencode($row['ticket_number']) ?>">Apply discount →</a></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="7">No tickets match this search.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <nav class="pagination" aria-label="Ticket pages">
        <?php if ($page > 1): ?><a class="button ghost" href="<?= admin_h(monitor_page_url($page - 1, $search, $status)) ?>">← Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="button ghost" href="<?= admin_h(monitor_page_url($page + 1, $search, $status)) ?>">Next →</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php admin_page_end(); ?>

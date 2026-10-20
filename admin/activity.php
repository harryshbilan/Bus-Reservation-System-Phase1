<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_role_admin();
require_once __DIR__ . '/layout.php';

$labels = [
    'signed_in' => 'Signed in',
    'sign_in_failed' => 'Failed sign-in',
    'cash_confirmed' => 'Cash confirmed',
    'discount_applied' => 'Discount applied',
    'discount_removed' => 'Discount removed',
    'booking_deleted' => 'Booking deleted',
    'staff_created' => 'Account created',
    'staff_enabled' => 'Account enabled',
    'staff_disabled' => 'Account disabled',
    'staff_role_changed' => 'Role changed',
    'password_reset' => 'Password reset',
    'staff_unlocked' => 'Account unlocked',
];
// Colour hints reuse the ticket status badges.
$tone = [
    'cash_confirmed' => 'PAID', 'discount_applied' => 'PENDING', 'discount_removed' => 'PENDING',
    'booking_deleted' => 'CANCELLED', 'sign_in_failed' => 'CANCELLED', 'staff_disabled' => 'CANCELLED',
];

$action = is_string($_GET['action'] ?? null) && isset($labels[$_GET['action']]) ? $_GET['action'] : '';
$person = filter_var($_GET['person'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$perPage = 50;

$where = [];
$params = [];

if ($action !== '') {
    $where[] = 'a.action = ?';
    $params[] = $action;
}

if ($person) {
    $where[] = 'a.admin_id = ?';
    $params[] = $person;
}

if ($search !== '') {
    $where[] = '(LOCATE(?, a.ticket_number) > 0 OR LOCATE(?, a.details) > 0)';
    $params[] = $search;
    $params[] = $search;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$q = $pdo->prepare("SELECT COUNT(*) FROM admin_activity a $whereSql");
$q->execute($params);
$total = (int) $q->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);

$q = $pdo->prepare("
    SELECT a.*, u.display_name, u.role
    FROM admin_activity a
    LEFT JOIN admin_users u ON u.admin_id = a.admin_id
    $whereSql
    ORDER BY a.activity_id DESC
    LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
$people = $pdo->query('SELECT admin_id, display_name FROM admin_users ORDER BY display_name')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageUrl = static fn (int $p): string => 'activity.php?' . http_build_query(array_filter(['action' => $action, 'person' => $person, 'search' => $search, 'page' => $p]));

admin_page_start('Activity log', 'activity', $admin);
?>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Admins only</span>
        <h1>Activity log</h1>
        <p>Every sign-in, cash confirmation, discount, deletion and account change, newest first.</p>
    </div>
</div>

<form class="filters" method="get">
    <div>
        <label for="search">Ticket or detail</label>
        <input type="search" id="search" name="search" value="<?= admin_h($search) ?>" placeholder="BW-… or a username">
    </div>
    <div>
        <label for="action">What</label>
        <select id="action" name="action">
            <option value="">Everything</option>
            <?php foreach ($labels as $key => $label): ?><option value="<?= $key ?>"<?= $key === $action ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="person">Who</label>
        <select id="person" name="person">
            <option value="">Everyone</option>
            <?php foreach ($people as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $id === $person ? ' selected' : '' ?>><?= admin_h($name) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button type="submit">Filter</button>
    <a class="button ghost" href="activity.php">Clear</a>
</form>

<p class="muted"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?> · page <?= $page ?> of <?= $pages ?></p>
<div class="table-wrap">
    <table>
        <thead><tr><th>When</th><th>Who</th><th>What</th><th>Ticket</th><th>Details</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= admin_h(date('M j, Y', strtotime($r['created_at']))) ?><small><?= admin_h(date('g:i:s A', strtotime($r['created_at']))) ?></small></td>
                    <td><?= admin_h($r['display_name'] ?? 'Deleted account') ?><small><?= $r['role'] ? ucfirst(admin_h($r['role'])) : '' ?></small></td>
                    <td><span class="status <?= $tone[$r['action']] ?? '' ?>"><?= admin_h($labels[$r['action']] ?? $r['action']) ?></span></td>
                    <td><?php if ($r['ticket_number']): ?><a class="ad-ref" href="ticket.php?ticket=<?= rawurlencode($r['ticket_number']) ?>"><?= admin_h($r['ticket_number']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                    <td><?= admin_h($r['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">Nothing recorded yet for this filter.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<nav class="pagination" aria-label="Activity pages">
    <?php if ($page > 1): ?><a class="button ghost" href="<?= admin_h($pageUrl($page - 1)) ?>">← Newer</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="button ghost" href="<?= admin_h($pageUrl($page + 1)) ?>">Older →</a><?php endif; ?>
</nav>
<?php admin_page_end(); ?>

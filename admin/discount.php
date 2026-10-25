<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require_once __DIR__ . '/layout.php';

const DISCOUNT_RATE = 0.20;
const DISCOUNT_TYPES = ['Senior' => 'Senior citizen', 'PWD' => 'Person with disability', 'Student' => 'Student'];

$ticket = is_string($_GET['ticket'] ?? null) ? trim($_GET['ticket']) : '';

if (!preg_match('/^BW-[A-F0-9]{1,64}$/D', $ticket)) {
    http_response_code(400);
    exit('Invalid booking reference.');
}

$errors = [];
$flash = $_SESSION['discount_flash'] ?? null;
unset($_SESSION['discount_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply') {
        $chosen = is_array($_POST['seat'] ?? null) ? $_POST['seat'] : [];
        $toApply = [];

        foreach ($chosen as $paymentId => $fields) {
            $type = is_string($fields['type'] ?? null) ? $fields['type'] : '';

            if ($type === '') {
                continue;
            }
            $last4 = strtoupper(trim(is_string($fields['id4'] ?? null) ? $fields['id4'] : ''));

            if (!isset(DISCOUNT_TYPES[$type])) {
                $errors[] = 'Pick a valid discount type.';
            } elseif (!preg_match('/^[A-Z0-9]{4}$/D', $last4)) {
                $errors[] = 'Enter the last 4 letters or digits of the ID for every discounted seat.';
            } else {
                $toApply[(int) $paymentId] = [$type, $last4];
            }
        }

        if (!$errors && !$toApply) {
            $errors[] = 'Choose a discount type for at least one seat.';
        }

        if (!$errors && ($_POST['id_checked'] ?? '') !== 'yes') {
            $errors[] = 'Confirm that you checked the passenger\'s ID in person.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $find = $pdo->prepare("
                    SELECT p.payment_id, p.amount, r.seat_number, s.fare
                    FROM payments p
                    JOIN reservations r ON r.reservation_id = p.reservation_id
                    JOIN schedules s ON s.schedule_id = r.schedule_id
                    LEFT JOIN fare_discounts d ON d.payment_id = p.payment_id
                    WHERE p.payment_id = ? AND r.ticket_number = ?
                      AND p.status = 'PENDING' AND r.status = 'PENDING' AND d.discount_id IS NULL
                    FOR UPDATE
                ");
                $update = $pdo->prepare("UPDATE payments SET amount = ? WHERE payment_id = ? AND status = 'PENDING'");
                $record = $pdo->prepare("
                    INSERT INTO fare_discounts (payment_id, discount_type, id_last4, original_amount, discount_amount, applied_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $applied = [];

                foreach ($toApply as $paymentId => [$type, $last4]) {
                    $find->execute([$paymentId, $ticket]);
                    $row = $find->fetch(PDO::FETCH_ASSOC);

                    if (!$row) {
                        throw new DomainException('A seat is already paid, cancelled or discounted. Reload and check again.');
                    }
                    // 20% of the seat's base fare, never more than what is still owed.
                    $original = (float) $row['amount'];
                    $discount = min($original, round((float) $row['fare'] * DISCOUNT_RATE, 2));
                    $update->execute([number_format($original - $discount, 2, '.', ''), $paymentId]);
                    $record->execute([$paymentId, $type, $last4, $original, $discount, $admin['admin_id']]);
                    $applied[] = sprintf('%s %s (ID …%s) -PHP %s', $row['seat_number'], $type, $last4, number_format($discount, 2));
                }
                $pdo->commit();
                admin_log($pdo, (int) $admin['admin_id'], 'discount_applied', $ticket, implode('; ', $applied));
                $_SESSION['discount_flash'] = 'Discount applied: ' . implode('; ', $applied) . '.';
                header('Location: discount.php?ticket=' . rawurlencode($ticket), true, 303);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof DomainException) {
                    $errors[] = $e->getMessage();
                } else {
                    error_log('BUSWAY discount failed: ' . $e->getMessage());
                    $errors[] = 'The discount could not be saved. Nothing was changed.';
                }
            }
        }
    } elseif ($action === 'remove' && is_admin_role($admin)) {
        $discountId = filter_var($_POST['discount_id'] ?? null, FILTER_VALIDATE_INT);

        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("
                SELECT d.discount_id, d.payment_id, d.original_amount, d.discount_type, r.seat_number
                FROM fare_discounts d
                JOIN payments p ON p.payment_id = d.payment_id
                JOIN reservations r ON r.reservation_id = p.reservation_id
                WHERE d.discount_id = ? AND r.ticket_number = ? AND p.status = 'PENDING'
                FOR UPDATE
            ");
            $q->execute([$discountId, $ticket]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new DomainException('Only discounts on unpaid seats can be removed.');
            }
            $pdo->prepare("UPDATE payments SET amount = ? WHERE payment_id = ?")->execute([$row['original_amount'], $row['payment_id']]);
            $pdo->prepare("DELETE FROM fare_discounts WHERE discount_id = ?")->execute([$row['discount_id']]);
            $pdo->commit();
            admin_log($pdo, (int) $admin['admin_id'], 'discount_removed', $ticket, $row['seat_number'] . ' ' . $row['discount_type'] . ' removed');
            $_SESSION['discount_flash'] = 'Discount removed from seat ' . $row['seat_number'] . '.';
            header('Location: discount.php?ticket=' . rawurlencode($ticket), true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e instanceof DomainException ? $e->getMessage() : 'The discount could not be removed.';

            if (!$e instanceof DomainException) {
                error_log('BUSWAY discount removal failed: ' . $e->getMessage());
            }
        }
    } else {
        http_response_code(403);
        $errors[] = 'You are not allowed to do that.';
    }
}

$seats = [];
$booking = null;

try {
    $q = $pdo->prepare("
        SELECT p.payment_id, p.amount, p.status AS payment_status, p.payment_method,
               r.seat_number, r.status, s.fare, s.travel_date, s.departure_time,
               pax.name, rt.origin, rt.destination,
               d.discount_id, d.discount_type, d.id_last4, d.discount_amount, d.applied_at, au.display_name AS applied_by
        FROM reservations r
        JOIN payments p ON p.reservation_id = r.reservation_id
        JOIN schedules s ON s.schedule_id = r.schedule_id
        JOIN routes rt ON rt.route_id = s.route_id
        JOIN passengers pax ON pax.passenger_id = r.passenger_id
        LEFT JOIN fare_discounts d ON d.payment_id = p.payment_id
        LEFT JOIN admin_users au ON au.admin_id = d.applied_by
        WHERE r.ticket_number = ?
        ORDER BY CAST(r.seat_number AS UNSIGNED), r.seat_number
    ");
    $q->execute([$ticket]);
    $seats = $q->fetchAll(PDO::FETCH_ASSOC);
    $booking = $seats[0] ?? null;
} catch (PDOException $e) {
    error_log('BUSWAY discount page: ' . $e->getMessage());
    $errors[] = 'This booking could not be loaded.';
}
$open = array_filter($seats, fn ($s) => $s['status'] === 'PENDING' && $s['payment_status'] === 'PENDING' && !$s['discount_id']);

admin_page_start('Discount ' . $ticket, 'tickets', $admin);
?>
<p><a href="ticket.php?ticket=<?= rawurlencode($ticket) ?>">← Back to ticket</a></p>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Fare discount</span>
        <h1>Apply a discount</h1>
        <p>Senior citizens, PWDs and students get 20% off the base fare of their own seat. Check a valid ID in person first.</p>
    </div>
</div>

<?php if ($flash): ?><p class="notice success" role="status"><?= admin_h($flash) ?></p><?php endif; ?>
<?php $cashDue = array_filter($seats, fn ($s) => $s['status'] === 'PENDING' && $s['payment_status'] === 'PENDING' && $s['payment_method'] === 'Cash'); ?>
<?php if ($cashDue): ?><p><a class="button" href="confirm_payment.php?ticket=<?= rawurlencode($ticket) ?>">Continue to collect cash →</a></p><?php endif; ?>
<?php if ($errors): ?><div class="error" role="alert"><?php foreach (array_unique($errors) as $e): ?><div><?= admin_h($e) ?></div><?php endforeach; ?></div><?php endif; ?>

<?php if (!$booking): ?>
    <p class="error">No booking was found with that reference.</p>
<?php else: ?>
    <section class="panel">
        <h2><?= admin_h($booking['name']) ?></h2>
        <p class="muted"><span class="ad-ref"><?= admin_h($ticket) ?></span> · <?= admin_h($booking['origin'] . ' → ' . $booking['destination']) ?> · <?= admin_h(admin_when($booking['travel_date'], $booking['departure_time'])) ?></p>

        <?php if ($open): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="apply">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Seat</th><th>Base fare</th><th>Now owed</th><th>Discount</th><th>ID last 4</th><th>New amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($open as $s):
                                $off = min((float) $s['amount'], round((float) $s['fare'] * DISCOUNT_RATE, 2));
                                ?>
                                <tr>
                                    <td><strong><?= admin_h($s['seat_number']) ?></strong></td>
                                    <td>₱<?= number_format((float) $s['fare'], 2) ?></td>
                                    <td>₱<?= number_format((float) $s['amount'], 2) ?></td>
                                    <td>
                                        <select name="seat[<?= (int) $s['payment_id'] ?>][type]" aria-label="Discount for seat <?= admin_h($s['seat_number']) ?>">
                                            <option value="">No discount</option>
                                            <?php foreach (DISCOUNT_TYPES as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input name="seat[<?= (int) $s['payment_id'] ?>][id4]" maxlength="4" size="6" pattern="[A-Za-z0-9]{4}" autocomplete="off" placeholder="1234" aria-label="Last 4 of ID for seat <?= admin_h($s['seat_number']) ?>"></td>
                                    <td>₱<?= number_format((float) $s['amount'] - $off, 2) ?> <small>−₱<?= number_format($off, 2) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <label style="display: flex; gap: 10px; align-items: flex-start; margin: 18px 0; font-weight: 500">
                    <input type="checkbox" name="id_checked" value="yes" required style="margin-top: 4px">
                    I checked each discounted passenger's valid ID in person, and it matches their seat.
                </label>
                <button type="submit">Apply discount</button>
                <p class="muted" style="margin-top: 10px">Only the last 4 characters of the ID are stored. The discount is logged under your name.</p>
            </form>
        <?php else: ?>
            <p class="notice">No seats can be discounted. Discounts only apply to unpaid seats that don't already have one.</p>
        <?php endif; ?>
    </section>

    <?php $done = array_filter($seats, fn ($s) => $s['discount_id']); ?>
    <?php if ($done): ?>
        <h2>Discounts on this booking</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Seat</th><th>Type</th><th>ID</th><th>Amount off</th><th>Applied by</th><?php if (is_admin_role($admin)): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                    <?php foreach ($done as $s): ?>
                        <tr>
                            <td><?= admin_h($s['seat_number']) ?></td>
                            <td><?= admin_h(DISCOUNT_TYPES[$s['discount_type']] ?? $s['discount_type']) ?></td>
                            <td>…<?= admin_h($s['id_last4']) ?></td>
                            <td>₱<?= number_format((float) $s['discount_amount'], 2) ?></td>
                            <td><?= admin_h($s['applied_by'] ?? '—') ?><small><?= admin_h(date('M j, Y · g:i A', strtotime($s['applied_at']))) ?></small></td>
                            <?php if (is_admin_role($admin)): ?>
                                <td>
                                    <?php if ($s['payment_status'] === 'PENDING'): ?>
                                        <form method="post" onsubmit="return confirm('Remove this discount and restore the full fare?')">
                                            <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="discount_id" value="<?= (int) $s['discount_id'] ?>">
                                            <button type="submit" class="button ghost">Remove</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Paid. Locked</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php admin_page_end(); ?>

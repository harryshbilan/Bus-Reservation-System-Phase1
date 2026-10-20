<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_role_admin();
require_once __DIR__ . '/layout.php';
require __DIR__ . '/booking-cleanup-functions.php';

$reference = is_string($_GET['ticket'] ?? null) ? trim($_GET['ticket']) : '';
$error = null;
$snapshot = null;
$previewId = '';
$success = $_SESSION['cleanup_success'] ?? '';
unset($_SESSION['cleanup_success']);
$_SESSION['cleanup_previews'] ??= [];

foreach ($_SESSION['cleanup_previews'] as $key => $preview) {
    if ($preview['expires'] < time()) {
        unset($_SESSION['cleanup_previews'][$key]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $postedId = is_string($_POST['preview_id'] ?? null) ? $_POST['preview_id'] : '';
    $preview = $_SESSION['cleanup_previews'][$postedId] ?? null;
    $confirmation = is_string($_POST['confirm_reference'] ?? null) ? trim($_POST['confirm_reference']) : '';

    if (!$preview || $preview['expires'] < time()) {
        $error = 'The preview expired. Find the booking again.';
    } elseif ($confirmation !== $preview['reference'] || ($_POST['understand'] ?? '') !== 'yes') {
        $error = 'Enter the exact booking reference and confirm the deletion notice.';
        $reference = $preview['reference'];
    } else {
        $reference = $preview['reference'];
        unset($_SESSION['cleanup_previews'][$postedId]);

        try {
            cleanup_delete($pdo, $reference, $preview['fingerprint']);
            admin_log($pdo, (int) $admin['admin_id'], 'booking_deleted', $reference, 'Permanently deleted the booking and its payment records');
            $_SESSION['cleanup_success'] = 'Deleted booking ' . $reference . ' and its linked payment records.';
            header('Location: booking-cleanup.php');
            exit;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = 'Deletion could not be completed. No changes were saved. Review the booking and retry.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

if ($reference !== '') {
    if (strlen($reference) > 50) {
        $error = 'Enter a valid booking reference.';
    } else {
        try {
            $snapshot = cleanup_snapshot($pdo, $reference);

            if (!$snapshot['reservations']) {
                $error = 'No booking was found with that reference.';
                $snapshot = null;
            } else {
                $previewId = bin2hex(random_bytes(16));

                if (count($_SESSION['cleanup_previews']) >= 10) {
                    array_shift($_SESSION['cleanup_previews']);
                }
                $_SESSION['cleanup_previews'][$previewId] = [
                    'reference' => $reference,
                    'fingerprint' => cleanup_fingerprint($snapshot),
                    'expires' => time() + 600,
                ];
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = 'Booking records could not be loaded.';
        }
    }
}
?>
<?php admin_page_start('Booking cleanup', 'cleanup', $admin); ?>

        <h1>Booking cleanup</h1>
        <p>Find one booking reference to review before permanently deleting it.</p>
        <?php if ($success): ?>
            <p class="notice success" role="status"><?= admin_h($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= admin_h($error) ?></p>
        <?php endif; ?>
        <form method="get" class="panel">
            <label for="ticket">Booking reference</label>
            <input id="ticket" name="ticket" maxlength="50" required value="<?= admin_h($reference) ?>">
            <button type="submit">Preview booking</button>
        </form>

        <?php if ($snapshot): ?>
            <section class="panel">
                <h2><?= admin_h($reference) ?></h2>
                <p>
                    <?= count($snapshot['reservations']) ?> seat record(s),
                    <?= count($snapshot['payments']) ?> payment record(s),
                    <?= count($snapshot['confirmations']) ?> payment confirmation(s).
                </p>
                <p><a href="ticket.php?ticket=<?= rawurlencode($reference) ?>">View full ticket details →</a></p>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Schedule ID</th><th>Seat</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($snapshot['reservations'] as $reservation): ?>
                                <tr>
                                    <td><?= (int) $reservation['schedule_id'] ?></td>
                                    <td><?= admin_h($reservation['seat_number']) ?></td>
                                    <td><?= admin_h($reservation['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($snapshot['payments']): ?>
                    <h3>Payments that will be erased</h3>
                    <ul>
                        <?php foreach ($snapshot['payments'] as $payment): ?>
                            <li>
                                #<?= (int) $payment['payment_id'] ?> —
                                ₱<?= number_format((float) $payment['amount'], 2) ?> —
                                <?= admin_h($payment['status']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="notice">
                    Permanent deletion removes this booking, its payments and payment confirmations.
                    Its passenger records are removed only if no other booking uses them.
                    Pending or paid seats become available again. Dashboard totals will change.
                    This does not issue a refund and cannot be undone from this website.
                </p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
                    <input type="hidden" name="preview_id" value="<?= admin_h($previewId) ?>">
                    <p>
                        <label for="confirm-reference">Type the booking reference to confirm</label><br>
                        <input id="confirm-reference" name="confirm_reference" maxlength="50" autocomplete="off" required>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="understand" value="yes" required>
                            I understand that this permanently erases the booking and its payment history.
                        </label>
                    </p>
                    <button type="submit" class="danger">Permanently delete booking</button>
                    <a class="button ghost" href="tickets.php">Keep booking</a>
                </form>
            </section>
        <?php endif; ?>
<?php admin_page_end(); ?>

<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$error = null;
$reservation = null;
$cancelled = null;

$ticketNumber = trim($_POST['ticket_number'] ?? '');
$email = trim($_POST['email'] ?? '');
$action = $_POST['action'] ?? '';
$cancelReason = trim($_POST['cancel_reason'] ?? '');

// View step state: 'policy' (default) or 'form'
$step = $_GET['step'] ?? 'policy';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If user submitted form, switch active view to 'form'
    $step = 'form';

    // FIND RESERVATION
    if ($ticketNumber === '' || $email === '') {
        $error = 'Enter your ticket number and booking email to continue.';
    } else {
        $reservation = find_database_reservation($ticketNumber, $email);

        if (!$reservation) {
            $error = 'We could not find an active reservation with those details.';
        } elseif (($reservation['status'] ?? '') === 'CANCELLED') {
            $error = 'This reservation has already been cancelled.';
        } elseif ($action === 'cancel') {
            // Make sure a cancellation reason was selected.
            if ($cancelReason === '') {
                $error = 'Please select a reason for cancelling your reservation.';
            } else {
                // Calculate cancellation fee and refund.
                [$fee, $refund] = cancellation_breakdown(
                    (float) ($reservation['total_fare'] ?? 0)
                );

                // Cancel all seats under the same ticket number.
                cancel_database_reservation(
                    $reservation['ticket_number']
                );

                // Update local reservation data.
                $reservation['status'] = 'CANCELLED';
                $reservation['cancellation_fee'] = $fee;
                $reservation['refund_amount'] = $refund;
                $reservation['cancelled_at'] = date('c');

                // Store cancellation reason for this page.
                $reservation['cancel_reason'] = $cancelReason;

                $cancelled = $reservation;
            }
        }
    }
}

$pageTitle = 'Cancel Reservation';
$activeStep = null;
$pageStyles = ['checkout.css', 'cancel.css'];

require __DIR__ . '/../includes/header.php';
?>
<?php
$niceDate = static fn (?string $date): string => $date ? date('M j, Y', strtotime($date)) : '—';
$niceTime = static fn (?string $time): string => $time ? date('g:i A', strtotime($time)) : '—';
?>
<section class="st-page cx-page">
  <?php if ($cancelled): ?>
    <div class="st-narrow cx-done">
      <div class="tk-banner cancelled">
        <span class="tk-icon" aria-hidden="true">✕</span>
        <span class="tk-state">Reservation cancelled</span>
        <h1 class="bw-display">Booking voided</h1>
        <p>Your seats have been released. Any refund goes back through the way you paid.</p>
      </div>
      <article class="tk-ticket">
        <header class="tk-strip cx-strip"><span>Cancellation receipt</span><span>Ref <?= h($cancelled['ticket_number'] ?? '') ?></span></header>
        <div class="tk-body">
          <dl class="tk-grid">
            <div><dt>Passenger</dt><dd><?= h($cancelled['name'] ?? '—') ?></dd></div>
            <div><dt>Route</dt><dd><?= h($cancelled['route'] ?? '—') ?></dd></div>
            <div><dt>Travel date</dt><dd><?= h($niceDate($cancelled['travel_date'] ?? null)) ?></dd></div>
            <div class="tk-wide"><dt>Reason</dt><dd><?= h($cancelled['cancel_reason'] ?? '') ?></dd></div>
          </dl>
          <div class="tk-pay">
            <span class="tk-label">Refund breakdown</span>
            <dl>
              <div><dt>Amount paid</dt><dd><?= peso((float) ($cancelled['total_fare'] ?? 0)) ?></dd></div>
              <div><dt>Cancellation fee (10%)</dt><dd class="due"><?= peso((float) ($cancelled['cancellation_fee'] ?? 0)) ?></dd></div>
              <div><dt>Refund amount</dt><dd class="hl"><?= peso((float) ($cancelled['refund_amount'] ?? 0)) ?></dd></div>
              <div><dt>Refund timeline</dt><dd>1–2 business days</dd></div>
            </dl>
            <?php if ((float) ($cancelled['total_fare'] ?? 0) <= 0): ?>
              <p class="st-note">Nothing was paid on this booking yet, so there's nothing to refund.</p>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <a class="bw-btn cx-full" href="book.php">Book a new trip →</a>
    </div>

  <?php else: ?>
    <div class="st-head cx-head">
      <span class="bw-kicker">Cancel reservation</span>
      <h1 class="bw-display">Find /<br><em>void your booking</em></h1>
      <p>Enter your booking reference and the email you booked with.</p>
    </div>

    <div class="cx-layout">
      <div>
        <?php if ($error): ?>
          <div class="st-alert" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($reservation && $action !== 'cancel'):
            $totalFare = (float) $reservation['total_fare'];
            [$fee, $refund] = cancellation_breakdown($totalFare);
            ?>
          <div class="st-card cx-found">
            <div class="cx-found-top">
              <div>
                <span class="st-card-title">Reservation found</span>
                <div class="bw-display cx-ref"><?= h($reservation['ticket_number'] ?? '') ?></div>
              </div>
              <span class="cx-active">Active</span>
            </div>
            <dl class="st-rows">
              <div><dt>Route</dt><dd><?= h($reservation['route'] ?? '—') ?></dd></div>
              <div><dt>Passenger</dt><dd><?= h($reservation['name']) ?></dd></div>
              <div><dt>Travel date</dt><dd><?= h($niceDate($reservation['travel_date'] ?? null)) ?></dd></div>
              <div><dt>Departure</dt><dd><?= h($niceTime($reservation['departure_time'] ?? null)) ?></dd></div>
              <div><dt>Bus</dt><dd><?= h($reservation['bus_number'] ?? '—') ?></dd></div>
              <div><dt>Amount paid</dt><dd class="hl"><?= peso($totalFare) ?></dd></div>
            </dl>
          </div>

          <form method="post" action="cancellation.php" class="st-card">
            <input type="hidden" name="ticket_number" value="<?= h($ticketNumber) ?>">
            <input type="hidden" name="email" value="<?= h($email) ?>">
            <input type="hidden" name="action" value="cancel">
            <div class="st-field">
              <label for="cancel_reason">Cancellation reason</label>
              <select id="cancel_reason" name="cancel_reason" required>
                <option value="">Select a reason</option>
                <?php foreach (['Change of plans', 'Travel date changed', 'Found another trip', 'Medical emergency', 'Personal reasons', 'Other'] as $reason): ?>
                  <option value="<?= h($reason) ?>"><?= h($reason) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="cx-fee">A <strong>10% cancellation fee (<?= peso($fee) ?>)</strong> is deducted. Estimated refund: <strong class="cx-refund"><?= peso($refund) ?></strong> within 1–2 business days.</p>
            <div class="st-actions">
              <a class="bw-btn-ghost" href="cancellation.php">← Search again</a>
              <button class="bw-btn cx-danger" type="submit">Confirm cancellation</button>
            </div>
          </form>

        <?php else: ?>
          <form method="post" action="cancellation.php" class="st-card">
            <h2 class="st-card-title">Booking lookup</h2>
            <p class="st-note cx-intro">Your reference is on your ticket and in your confirmation email. It starts with <strong>BW-</strong>.</p>
            <div class="cx-fields">
              <div class="st-field">
                <label for="ticket_number">Booking reference</label>
                <input id="ticket_number" name="ticket_number" type="text" value="<?= h($ticketNumber) ?>" placeholder="BW-XXXXXXXX" autocomplete="off" required>
              </div>
              <div class="st-field">
                <label for="email">Booking email</label>
                <input id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="you@example.com" autocomplete="email" required>
              </div>
            </div>
            <input type="hidden" name="action" value="lookup">
            <button class="bw-btn" type="submit">Find booking →</button>
          </form>
        <?php endif; ?>
        <a class="cx-back" href="index.php">← Back to home</a>
      </div>

      <aside class="st-card cx-policy" aria-labelledby="cx-policy-title">
        <h2 id="cx-policy-title" class="st-card-title">Cancellation policy</h2>
        <ul>
          <li><strong>2-hour rule</strong>Cancel at least 2 hours before your bus leaves.</li>
          <li><strong>10% fee</strong>Taken off every refund. It can't be waived.</li>
          <li><strong>Refund window</strong>Back to your original payment method within 1–2 business days.</li>
          <li><strong>Need help?</strong><a href="support.php#contact">Contact support</a> and we'll sort it out with you.</li>
        </ul>
      </aside>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // FIND RESERVATION
    if ($ticketNumber === '' || $email === '') {

        $error = 'Enter your ticket number and booking email to continue.';

    } else {

        $reservation = find_reservation_by_ticket($ticketNumber, $email);

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

                // Release reserved seats.
                $seatIds = array_map(
                    'intval',
                    $reservation['seat_ids'] ?? []
                );

                if (
                    $seatIds &&
                    isset($reservation['bus_id'], $reservation['travel_date'])
                ) {

                    release_seats(
                        (int) $reservation['bus_id'],
                        (string) $reservation['travel_date'],
                        $seatIds
                    );
                }

                // Update reservation.
                update_reservation(
                    (int) $reservation['reservation_id'],
                    [
                        'status' => 'CANCELLED',
                        'cancellation_fee' => $fee,
                        'refund_amount' => $refund,
                        'cancelled_at' => date('c'),
                    ]
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

require __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl" style="margin-left:auto;margin-right:auto;">

  <?php if ($cancelled): ?>

    <!-- =========================================
         CANCELLATION COMPLETE
    ========================================== -->

    <div class="confirm-banner danger">

      <div class="confirm-icon danger">&#10003;</div>

      <div class="confirm-eyebrow danger">
        RESERVATION CANCELLED
      </div>

      <h1 class="confirm-title">
        Your cancellation is complete.
      </h1>

      <p class="confirm-sub">
        Your refund will be processed according to the payment method used.
      </p>

    </div>


    <div class="ticket-card">

      <div class="ticket-head danger">

        <span>
          CANCELLATION RECEIPT
        </span>

        <span>
          <?= h($cancelled['ticket_number'] ?? '') ?>
        </span>

      </div>


      <div class="ticket-body">

        <div class="ticket-detail-grid">

          <div>
            <div class="tdl">PASSENGER</div>
            <div class="tdv">
              <?= h($cancelled['passenger']['name'] ?? '') ?>
            </div>
          </div>


          <div>
            <div class="tdl">TRAVEL DATE</div>
            <div class="tdv">
              <?= h($cancelled['travel_date'] ?? '') ?>
            </div>
          </div>


          <div>
            <div class="tdl">ORIGINAL FARE</div>
            <div class="tdv">
              <?= peso((float) ($cancelled['total_fare'] ?? 0)) ?>
            </div>
          </div>


          <div>
            <div class="tdl">CANCELLATION FEE</div>
            <div class="tdv danger">
              <?= peso((float) ($cancelled['cancellation_fee'] ?? 0)) ?>
            </div>
          </div>


          <div>
            <div class="tdl">REFUND AMOUNT</div>
            <div class="tdv">
              <?= peso((float) ($cancelled['refund_amount'] ?? 0)) ?>
            </div>
          </div>


          <div>
            <div class="tdl">CANCELLATION REASON</div>
            <div class="tdv">
              <?= h($cancelled['cancel_reason'] ?? '') ?>
            </div>
          </div>


          <div>
            <div class="tdl">STATUS</div>
            <div class="tdv danger">
              CANCELLED
            </div>
          </div>

        </div>


        <div style="margin-top:24px;">

          <a class="btn-primary" href="index.php">
            BOOK ANOTHER TRIP
          </a>

        </div>

      </div>

    </div>


  <?php else: ?>


    <!-- =========================================
         PAGE HEADER
    ========================================== -->

    <div class="section-head">

      <div class="eyebrow accent">
        MANAGE YOUR TRIP
      </div>

      <h1 class="section-title">
        CANCEL RESERVATION
      </h1>

      <p class="confirm-sub">
        Find your reservation and review the cancellation details before confirming.
      </p>

    </div>


    <!-- =========================================
         ERROR MESSAGE
    ========================================== -->

    <?php if ($error): ?>

      <div class="error-box">

        <div>

          <div class="etitle">
            CANCELLATION NOT AVAILABLE
          </div>

          <div class="emsg">
            <?= h($error) ?>
          </div>

        </div>

      </div>

    <?php endif; ?>


    <!-- =========================================
         RESERVATION FOUND
    ========================================== -->

    <?php if ($reservation && $action !== 'cancel'): ?>

      <?php
        $totalFare = (float) ($reservation['total_fare'] ?? 0);
        [$fee, $refund] = cancellation_breakdown($totalFare);
      ?>


      <div class="ticket-card">

        <div class="ticket-head">

          <span>
            RESERVATION FOUND
          </span>

          <span>
            <?= h($reservation['ticket_number'] ?? '') ?>
          </span>

        </div>


        <div class="ticket-body">


          <!-- Reservation Details -->

          <div class="ticket-detail-grid">

            <div>
              <div class="tdl">
                PASSENGER
              </div>

              <div class="tdv">
                <?= h($reservation['passenger']['name'] ?? '') ?>
              </div>
            </div>


            <div>
              <div class="tdl">
                TRAVEL DATE
              </div>

              <div class="tdv">
                <?= h($reservation['travel_date'] ?? '') ?>
              </div>
            </div>


            <?php if (!empty($reservation['route'])): ?>

              <div>
                <div class="tdl">
                  ROUTE
                </div>

                <div class="tdv">
                  <?= h($reservation['route']) ?>
                </div>
              </div>

            <?php endif; ?>


            <?php if (!empty($reservation['departure_time'])): ?>

              <div>
                <div class="tdl">
                  DEPARTURE
                </div>

                <div class="tdv">
                  <?= h($reservation['departure_time']) ?>
                </div>
              </div>

            <?php endif; ?>


            <div>
              <div class="tdl">
                TOTAL FARE
              </div>

              <div class="tdv">
                <?= peso($totalFare) ?>
              </div>
            </div>

          </div>


          <!-- Cancellation Reason -->

          <div style="margin-top:28px;">

            <div class="card-title" style="margin-bottom:10px;">
              CANCELLATION REASON
            </div>

            <p class="confirm-sub" style="margin-bottom:14px;">
              Please select a reason for cancelling your reservation.
            </p>


            <form method="post" action="cancellation.php">

              <input
                type="hidden"
                name="ticket_number"
                value="<?= h($ticketNumber) ?>"
              >

              <input
                type="hidden"
                name="email"
                value="<?= h($email) ?>"
              >

              <input
                type="hidden"
                name="action"
                value="cancel"
              >


              <select
                class="input"
                id="cancel_reason"
                name="cancel_reason"
                required
              >

                <option value="">
                  Select a reason
                </option>

                <option value="Change of plans">
                  Change of plans
                </option>

                <option value="Travel date changed">
                  Travel date changed
                </option>

                <option value="Found another trip">
                  Found another trip
                </option>

                <option value="Personal reasons">
                  Personal reasons
                </option>

                <option value="Other">
                  Other
                </option>

              </select>


              <!-- Refund Information -->

              <div
                class="refund-note"
                style="margin-top:20px;"
              >

                Cancellation fee:
                <strong>
                  <?= peso($fee) ?>
                </strong>

                <br>

                Estimated refund:
                <strong>
                  <?= peso($refund) ?>
                </strong>

                <br><br>

                A 10% cancellation fee will be deducted from the
                original fare. The remaining 90% will be refunded.

              </div>


              <!-- Buttons -->

              <div
                style="
                  display:flex;
                  gap:12px;
                  flex-wrap:wrap;
                  margin-top:24px;
                "
              >

                <a
                  class="btn-secondary"
                  href="cancellation.php"
                >
                  SEARCH AGAIN
                </a>


                <button
                  class="btn-primary"
                  type="submit"
                >
                  CONFIRM CANCELLATION
                </button>

              </div>

            </form>

          </div>

        </div>

      </div>


    <?php else: ?>


      <!-- =========================================
           FIND RESERVATION
      ========================================== -->

      <div class="card">

        <div class="card-title">
          FIND YOUR RESERVATION
        </div>

        <p
          class="confirm-sub"
          style="margin-bottom:24px;"
        >
          Enter the ticket number and email address used during booking.
        </p>


        <form
          method="post"
          action="cancellation.php"
        >

          <div class="grid-2 mb-6">


            <!-- Ticket Number -->

            <div>

              <label
                class="label"
                for="ticket_number"
              >
                TICKET NUMBER
              </label>

              <input
                class="input"
                id="ticket_number"
                name="ticket_number"
                type="text"
                value="<?= h($ticketNumber) ?>"
                placeholder="e.g. BR-123456"
                required
              >

            </div>


            <!-- Email -->

            <div>

              <label
                class="label"
                for="email"
              >
                BOOKING EMAIL
              </label>

              <input
                class="input"
                id="email"
                name="email"
                type="email"
                value="<?= h($email) ?>"
                placeholder="you@example.com"
                required
              >

            </div>


          </div>


          <input
            type="hidden"
            name="action"
            value="lookup"
          >


          <button
            class="btn-primary"
            type="submit"
          >
            FIND RESERVATION
          </button>

        </form>

      </div>


    <?php endif; ?>

  <?php endif; ?>

</div>


<?php require __DIR__ . '/../includes/footer.php'; ?>
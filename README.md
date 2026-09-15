# BUSWAY — Bus Reservation System (Phase 1)

This is the **Phase 1** version of the project: plain **PHP + HTML/CSS/JS**
only. There is **no database** — bus/route/schedule data lives in a PHP
array (`includes/data.php`), and anything a user "saves" (a seat hold, a
reservation, a cancellation) is kept only in the PHP **session** for that
browser while the built-in web server is running. Nothing is written to
disk. Closing the browser / restarting the server clears it — that's
expected for this phase.

## What it includes
- Search for a trip → available buses → pick a bus → pick seats →
  passenger details → fare summary → payment (card or pay-at-counter) →
  ticket
- Cancel a reservation by ticket number + email, with a cancellation
  receipt page
- Same look and feel (CSS/JS) as the full version

## What was removed for Phase 1
- MySQL/PDO (`includes/db.php`, `sql/busway.sql`) — replaced by
  `includes/data.php` (hardcoded bus/route/schedule data) and PHP
  sessions for anything that used to be "saved"
- The `admin/` staff login area — it only existed to manage database
  records, so it isn't needed without a database

## How to run it (XAMPP / any local PHP server)
**Option A — PHP's built-in server (simplest):**
1. Open a terminal in this folder.
2. Run: `php -S localhost:8000`
3. Open `http://localhost:8000/public/index.php` in your browser.

**Option B — XAMPP:**
1. Copy this whole folder into `htdocs/`.
2. Start Apache in the XAMPP Control Panel (you do **not** need to start
   MySQL for this phase).
3. Visit `http://localhost/Bus-Reservation-System-Phase1/public/index.php`.

## Sample routes to try searching
Manila, Cubao, PITX, or Cebu City as the origin (destinations include
Baguio, Dagupan, Batangas City, Cabanatuan, Vigan, Lucena, Baler,
Moalboal).

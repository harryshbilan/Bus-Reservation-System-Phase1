# BUSWAY — Bus Reservation System

A PHP + MySQL bus booking site: search trips, pick seats, pay (GCash, card or cash at the counter) and get a ticket. Staff use the `admin/` area to confirm cash payments and watch bookings.

## Running it (XAMPP)
1. Copy this folder into `htdocs/` and start **Apache** and **MySQL**.
2. Create the database. In phpMyAdmin (or the `mysql` CLI) import, in this order:
   1. `database/busway.sql` — all tables
   2. `database/demo_schedules.sql` — sample routes, buses and departures
   3. `database/fleet_names.sql` — gives the sample buses their operators and fleet numbers (AL-101, LX-201, …) and real boarding points
   4. `database/island_routes.sql` — Dagat Coaches routes to El Nido, Siargao, Coron and Cebu (30 days ahead; rerun it to add more days)
   5. `database/admin_users.sql`, `database/payment_confirmations.sql`, `database/online_payments.sql`, then `database/staff_roles.sql` — admin/staff roles, ID discounts and the activity log
   6. `database/luggage.sql` — extra checked bags (₱120 each) bought on the Passenger Info step

### Admin and staff roles
The first account made with `scripts/admin_create.php` is an **admin**. Admins add everyone else under **Staff** in the admin area.

| | Staff | Admin |
|---|---|---|
| Find and view tickets, confirm cash payments | ✅ | ✅ |
| Apply Senior / PWD / Student 20% discount (after checking ID) | ✅ | ✅ |
| Revenue figures, fleet, timetables | | ✅ |
| Staff accounts, activity log, remove discounts, delete bookings | | ✅ |

   **Hero video (optional):** put a short, muted clip at `assets/videos/hero.mp4` (or `hero.webm`). The home page plays it on a loop behind the headline; without it, the photo shows.
3. Create an admin login from the project folder:
   `echo {"username":"admin","display_name":"Admin","password":"choose-a-password"} | php scripts/admin_create.php`

4. Open `http://localhost/Bus-Reservation-System/public/index.php`.
   Staff sign in at `/admin/login.php`.

Database settings live in `includes/database.php` (default: `root`, no password, `busway_db`).

## How booking works
Search → available buses → seats → passenger details → fare → payment → ticket.

| Payment | What happens |
|---|---|
| Pay at counter | Seats are held as **PENDING**. Staff confirm cash in the admin area, then it becomes **PAID**. |
| GCash / Card | Seats are held for **15 minutes**. Paying on `pay-online.php` marks the booking **PAID** at once and emails the ticket. If the time runs out, the seats are released. |

### Online payments run in test mode
`includes/gateway.php` is a sandbox: no real money moves.
- Card `4242 4242 4242 4242` approves, `4000 0000 0000 0002` is declined (any future expiry, any 3-digit code).
- GCash accepts any valid PH mobile number with the code `123456`.
- Card numbers and security codes are never stored — only a reference and a masked hint like `Visa •••• 4242`.

To take real payments, replace the body of `gateway_charge()` with a call to a provider such as PayMongo; the rest of the flow stays the same.

### Emails
`includes/mailer.php` uses PHP `mail()`. On a plain local XAMPP there is no mail server, so the email is logged and skipped. Configure `sendmail` in `php.ini` (or swap in PHPMailer) to send real ones.

## Folder guide
- `public/` — customer pages
- `admin/` — staff area
- `includes/` — shared PHP (database, checkout, gateway, mailer, header/footer)
- `assets/css`, `assets/js`, `assets/images` — front end; each page's styles live in `assets/css/<page>.css`
- `database/` — schema and seed scripts

<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';

// Only signed-in, active admins can view this page.
$admin = require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | BUSWAY</title>

    <style>
        body {
            margin: 0;
            background: #f7f3ee;
            color: #1c1208;
            font-family: Arial, sans-serif;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 20px 32px;
            background: white;
            border-bottom: 4px solid #e8940a;
        }
        main {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 24px;
        }
        .card {
            padding: 28px;
            background: white;
            border-radius: 8px;
        }
        button {
            padding: 12px 20px;
            background: #e8940a;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        a { color: #684000; }
    </style>
</head>
<body>
    <header>
        <strong>BUSWAY Admin</strong>

        <form method="post" action="logout.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= admin_h(admin_csrf_token()) ?>"
            >
            <button type="submit">Sign out</button>
        </form>
    </header>

    <main>
        <div class="card">
            <h1>Welcome, <?= admin_h($admin['display_name']) ?>!</h1>
            <p>You are signed in to the admin area.</p>
            <p>
                Booking management and schedule management
                will be added here next.
            </p>
            <p>
                <a href="reservations.php">Manage reservations</a>
            </p>

            <a href="../public/index.php">View passenger website</a>
        </div>
    </main>
</body>
</html>
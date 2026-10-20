<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_role_admin();
require_once __DIR__ . '/layout.php';

$errors = [];
$flash = $_SESSION['staff_flash'] ?? null;
unset($_SESSION['staff_flash']);
$form = ['display_name' => '', 'username' => '', 'role' => 'staff'];

function staff_done(string $message): never
{
    $_SESSION['staff_flash'] = $message;
    header('Location: staff.php', true, 303);
    exit;
}

function staff_password_error(string $password): ?string
{
    if (strlen($password) < 12 || strlen($password) > 72) {
        return 'Passwords must be 12 to 72 characters long.';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $targetId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'create') {
            $form = [
                'display_name' => trim((string) ($_POST['display_name'] ?? '')),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'role' => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff',
            ];
            $password = (string) ($_POST['password'] ?? '');

            if ($form['display_name'] === '' || mb_strlen($form['display_name']) > 100) {
                $errors[] = 'Enter the person\'s name (up to 100 characters).';
            }

            if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $form['username'])) {
                $errors[] = 'Usernames use 3 to 50 letters, numbers or underscores.';
            }

            if ($e = staff_password_error($password)) {
                $errors[] = $e;
            }

            if (!$errors) {
                $exists = $pdo->prepare('SELECT 1 FROM admin_users WHERE username = ?');
                $exists->execute([$form['username']]);

                if ($exists->fetchColumn()) {
                    $errors[] = 'That username is already taken.';
                }
            }

            if (!$errors) {
                $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
                    ->execute([$form['username'], password_hash($password, PASSWORD_DEFAULT), $form['display_name'], $form['role']]);
                admin_log($pdo, (int) $admin['admin_id'], 'staff_created', null, $form['username'] . ' as ' . $form['role']);
                staff_done(ucfirst($form['role']) . ' account "' . $form['username'] . '" created. Share the password with them privately.');
            }
        } elseif ($targetId) {
            $q = $pdo->prepare('SELECT * FROM admin_users WHERE admin_id = ?');
            $q->execute([$targetId]);
            $target = $q->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                throw new DomainException('That account no longer exists.');
            }
            $isSelf = (int) $target['admin_id'] === (int) $admin['admin_id'];
            $otherActiveAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1 AND admin_id <> " . (int) $target['admin_id'])->fetchColumn();
            $wouldRemoveLastAdmin = $target['role'] === 'admin' && (int) $target['is_active'] === 1 && $otherActiveAdmins === 0;

            switch ($action) {
                case 'toggle_active':
                    if ($isSelf) {
                        throw new DomainException('You can\'t disable your own account.');
                    }

                    if ($wouldRemoveLastAdmin) {
                        throw new DomainException('Keep at least one active admin.');
                    }
                    $active = (int) $target['is_active'] === 1 ? 0 : 1;
                    $pdo->prepare('UPDATE admin_users SET is_active = ? WHERE admin_id = ?')->execute([$active, $target['admin_id']]);
                    admin_log($pdo, (int) $admin['admin_id'], $active ? 'staff_enabled' : 'staff_disabled', null, $target['username']);
                    staff_done($target['username'] . ($active ? ' can sign in again.' : ' is disabled and can no longer sign in.'));
                    // no break: staff_done() exits
                case 'set_role':
                    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';

                    if ($role === $target['role']) {
                        staff_done('No change.');
                    }

                    if ($isSelf) {
                        throw new DomainException('You can\'t change your own role.');
                    }

                    if ($role === 'staff' && $wouldRemoveLastAdmin) {
                        throw new DomainException('Keep at least one active admin.');
                    }
                    $pdo->prepare('UPDATE admin_users SET role = ? WHERE admin_id = ?')->execute([$role, $target['admin_id']]);
                    admin_log($pdo, (int) $admin['admin_id'], 'staff_role_changed', null, $target['username'] . ' is now ' . $role);
                    staff_done($target['username'] . ' is now ' . ($role === 'admin' ? 'an admin.' : 'staff.'));
                    // no break
                case 'reset_password':
                    $password = (string) ($_POST['new_password'] ?? '');

                    if ($e = staff_password_error($password)) {
                        throw new DomainException($e);
                    }
                    $pdo->prepare('UPDATE admin_users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE admin_id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $target['admin_id']]);
                    admin_log($pdo, (int) $admin['admin_id'], 'password_reset', null, $target['username']);
                    staff_done('Password for ' . $target['username'] . ' changed. Share it with them privately.');
                    // no break
                case 'unlock':
                    $pdo->prepare('UPDATE admin_users SET failed_login_attempts = 0, locked_until = NULL WHERE admin_id = ?')->execute([$target['admin_id']]);
                    admin_log($pdo, (int) $admin['admin_id'], 'staff_unlocked', null, $target['username']);
                    staff_done($target['username'] . ' is unlocked.');
                    // no break
                default:
                    throw new DomainException('Unknown action.');
            }
        }
    } catch (DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (PDOException $e) {
        error_log('BUSWAY staff page: ' . $e->getMessage());
        $errors[] = 'The change could not be saved.';
    }
}

$accounts = $pdo->query("
    SELECT u.admin_id, u.username, u.display_name, u.role, u.is_active, u.created_at,
           (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked,
           (SELECT MAX(a.created_at) FROM admin_activity a WHERE a.admin_id = u.admin_id AND a.action = 'signed_in') AS last_sign_in
    FROM admin_users u
    ORDER BY u.is_active DESC, u.role, u.display_name
")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('Staff accounts', 'staff', $admin);
?>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Admins only</span>
        <h1>Staff accounts</h1>
        <p>Staff can find tickets, confirm cash and apply ID discounts. Admins can also see revenue, manage accounts, read the activity log and delete bookings.</p>
    </div>
</div>

<?php if ($flash): ?><p class="notice success" role="status"><?= admin_h($flash) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="error" role="alert"><?php foreach ($errors as $e): ?><div><?= admin_h($e) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="ad-grid">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $u):
                    $self = (int) $u['admin_id'] === (int) $admin['admin_id'];
                    $csrf = admin_h(admin_csrf_token());
                    ?>
                    <tr>
                        <td>
                            <strong><?= admin_h($u['display_name']) ?></strong><?= $self ? ' <span class="method">You</span>' : '' ?>
                            <small>@<?= admin_h($u['username']) ?> · last sign-in <?= $u['last_sign_in'] ? admin_h(date('M j, g:i A', strtotime($u['last_sign_in']))) : 'never recorded' ?></small>
                        </td>
                        <td>
                            <?php if ($self): ?>
                                <span class="status PAID"><?= $u['role'] === 'admin' ? 'ADMIN' : 'STAFF' ?></span>
                            <?php else: ?>
                                <form method="post" class="ad-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="admin_id" value="<?= (int) $u['admin_id'] ?>">
                                    <select name="role" aria-label="Role for <?= admin_h($u['username']) ?>" onchange="this.form.requestSubmit()">
                                        <option value="staff"<?= $u['role'] === 'staff' ? ' selected' : '' ?>>Staff</option>
                                        <option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                                    </select>
                                    <noscript><button type="submit" class="button ghost">Save</button></noscript>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!(int) $u['is_active']): ?><span class="status CANCELLED">DISABLED</span>
                            <?php elseif ((int) $u['is_locked']): ?><span class="status PENDING">LOCKED</span>
                            <?php else: ?><span class="status PAID">ACTIVE</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="ad-row-actions">
                                <?php if (!$self): ?>
                                    <form method="post" class="ad-inline" onsubmit="return confirm('<?= (int) $u['is_active'] ? 'Disable' : 'Enable' ?> <?= admin_h($u['username']) ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="admin_id" value="<?= (int) $u['admin_id'] ?>">
                                        <button type="submit" class="button ghost"><?= (int) $u['is_active'] ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int) $u['is_locked']): ?>
                                    <form method="post" class="ad-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="admin_id" value="<?= (int) $u['admin_id'] ?>">
                                        <button type="submit" class="button ghost">Unlock</button>
                                    </form>
                                <?php endif; ?>
                                <details class="ad-reset">
                                    <summary class="button ghost">Reset password</summary>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="admin_id" value="<?= (int) $u['admin_id'] ?>">
                                        <label for="pw-<?= (int) $u['admin_id'] ?>">New password (12+ characters)</label>
                                        <input id="pw-<?= (int) $u['admin_id'] ?>" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required>
                                        <button type="submit">Set password</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <section class="panel" style="margin: 0">
        <h2>Add an account</h2>
        <form method="post" class="ad-form">
            <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <label for="display_name">Full name</label>
            <input id="display_name" name="display_name" maxlength="100" required value="<?= admin_h($form['display_name']) ?>">
            <label for="username">Username</label>
            <input id="username" name="username" maxlength="50" pattern="[A-Za-z0-9_]{3,50}" required autocomplete="off" value="<?= admin_h($form['username']) ?>">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="staff"<?= $form['role'] === 'staff' ? ' selected' : '' ?>>Staff: counter and boarding work</option>
                <option value="admin"<?= $form['role'] === 'admin' ? ' selected' : '' ?>>Admin: full access</option>
            </select>
            <label for="password">Temporary password</label>
            <input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
            <small>At least 12 characters. Give it to them in person and reset it if it's ever shared.</small>
            <button type="submit">Create account</button>
        </form>
    </section>
</div>
<?php admin_page_end(); ?>

<?php
include('db.php');
include_once('user_archive_helpers.php');
include_once('toast_helpers.php');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Barangay Captain') {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = '';
$archive_columns_ready = ensure_user_archive_columns($conn);

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($edit_id <= 0) {
    header("Location: manage_users.php");
    exit();
}

$active_user_where = $archive_columns_ready ? "AND COALESCE(is_archived, 0) = 0" : "";
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$edit_id' $active_user_where");
$u = mysqli_fetch_assoc($query);

if (!$u) {
    header("Location: manage_users.php?error=user_not_found");
    exit();
}

$captain_exists_elsewhere = active_captain_exists($conn, $edit_id);

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = trim("$first_name $middle_name $last_name");
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $allowed_roles = ['Secretary', 'Barangay Captain', 'Former Captain'];
    $email_col_exists = false;
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        $email_col_exists = true;
    }

    if ($first_name === '' || $last_name === '') $errors[] = 'First and Last names are required.';
    if (!in_array($role, $allowed_roles, true)) $errors[] = 'Invalid role selected.';
    if ($role === 'Barangay Captain' && active_captain_exists($conn, $edit_id)) $errors[] = 'There is already an active Barangay Captain account.';
    if ($username === '') $errors[] = 'Username is required.';
    if ($email_col_exists && $email === '') $errors[] = 'Email is required.';

    if ($password !== '') {
        if (strlen($password) < 4) $errors[] = 'Password must be at least 4 characters.';
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $safe_user = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe_user' AND id != '$edit_id'");
        if ($check && mysqli_num_rows($check) > 0) {
            $errors[] = 'Username is already taken by another account.';
        } else {
            if ($email_col_exists && $email !== '') {
                $safe_email_check = mysqli_real_escape_string($conn, strtolower($email));
                $email_check = mysqli_query(
                    $conn,
                    "SELECT id FROM users WHERE LOWER(email) = '$safe_email_check' AND id != '$edit_id' LIMIT 1"
                );
                if ($email_check && mysqli_num_rows($email_check) > 0) {
                    $errors[] = 'Email is already used by another account.';
                }
            }
        }
    }

    if (empty($errors)) {
        $safe_name = mysqli_real_escape_string($conn, $full_name);
        $safe_role = mysqli_real_escape_string($conn, $role);
        $safe_email = mysqli_real_escape_string($conn, $email);

        $safe_first = mysqli_real_escape_string($conn, $first_name);
        $safe_middle = mysqli_real_escape_string($conn, $middle_name);
        $safe_last = mysqli_real_escape_string($conn, $last_name);

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if ($email_col_exists) {
                $update = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', role = '$safe_role', username = '$safe_user', email = '$safe_email', password = '$hashed' WHERE id = '$edit_id'";
            } else {
                $update = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', role = '$safe_role', username = '$safe_user', password = '$hashed' WHERE id = '$edit_id'";
            }
        } else {
            if ($email_col_exists) {
                $update = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', role = '$safe_role', username = '$safe_user', email = '$safe_email' WHERE id = '$edit_id'";
            } else {
                $update = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', role = '$safe_role', username = '$safe_user' WHERE id = '$edit_id'";
            }
        }

        if (mysqli_query($conn, $update)) {
            $action_desc = mysqli_real_escape_string($conn, "Captain updated user details for ID#$edit_id ($full_name)");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");

            if ($edit_id === (int)($_SESSION['user_id'] ?? 0)) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;
            }

            header("Location: manage_users.php?success=user_updated");
            exit();
        } else {
            $errors[] = 'Failed to update user. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Barangay Captain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-navy: #1e293b; --accent-blue: #2563eb; --logo-orange: #ff9800; --text-gray: #64748b; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .content-body { padding: 16px 20px 20px; max-width: 800px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .panel { background: white; border: 1px solid #e2e8f0; padding: 30px; border-radius: 20px; margin-bottom: 20px; }

        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; }
        .panel-header h3 { margin: 0; font-size: 20px; color: #1e293b; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }
        label { font-weight: 600; color: #1e293b; font-size: 13px; }
        input, select {
            padding: 12px 16px; border: 1px solid #d7dbe1; border-radius: 8px;
            font-family: inherit; font-size: 14px; outline: none; background: #fff;
        }
        input:focus, select:focus { border-color: var(--accent-blue); background: white; }

        .btn-save {
            background: var(--accent-blue); color: white; padding: 12px 28px; border: none;
            border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px;
        }

        .btn-cancel {
            background: #e2e8f0; color: #334155; padding: 12px 28px; border: none;
            border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; margin-right: 12px;
        }

        .error-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; }

        .password-wrap { position: relative; }
        .password-wrap input { width: 100%; box-sizing: border-box; padding-right: 40px; }
        .eye-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 14px; }

        .help-text { font-size: 12px; color: var(--text-gray); margin-top: 4px; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin:0;">Edit User Details</h2>
            <p style="margin:0; color: var(--text-gray);">Update account information and credentials</p>
        </div>
    </header>

    <div class="content-body">

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <h3><i class="fa-solid fa-user-pen" style="color: var(--accent-blue); margin-right: 8px;"></i>Account Details</h3>
            </div>
            <form id="edit-user-form" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $u['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? $u['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? $u['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <?php $selected_role = $_POST['role'] ?? ($u['role'] ?? ''); ?>
                        <select name="role" required>
                            <option value="Secretary" <?php echo ($selected_role === 'Secretary') ? 'selected' : ''; ?>>Secretary</option>
                            <?php if (!$captain_exists_elsewhere || in_array(($u['role'] ?? ''), ['Barangay Captain', 'Captain'], true)): ?>
                                <option value="Barangay Captain" <?php echo in_array($selected_role, ['Barangay Captain', 'Captain'], true) ? 'selected' : ''; ?>>Barangay Captain</option>
                            <?php endif; ?>
                            <option value="Former Captain" <?php echo ($selected_role === 'Former Captain') ? 'selected' : ''; ?>>Former Captain</option>
                        </select>
                        <?php if ($captain_exists_elsewhere && !in_array(($u['role'] ?? ''), ['Barangay Captain', 'Captain'], true)): ?>
                            <div class="help-text">Only one Barangay Captain account can be active.</div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $u['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ($u['email'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group full-width" style="border-top: 1px solid #e2e8f0; padding-top: 16px; margin-top: 8px;">
                        <label>Change Password <span style="font-weight: normal; color: var(--text-gray);">(Leave blank to keep current password)</span></label>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="password-wrap">
                            <input type="password" name="password" id="editPassword" placeholder="Enter new password">
                            <i class="fa-regular fa-eye eye-toggle" onclick="togglePass('editPassword', this)"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="password-wrap">
                            <input type="password" name="confirm_password" id="editConfirmPassword" placeholder="Confirm new password">
                            <i class="fa-regular fa-eye eye-toggle" onclick="togglePass('editConfirmPassword', this)"></i>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
                    <a href="manage_users.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="update_user" class="btn-save"><i class="fa-solid fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    function togglePass(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
<?php render_form_draft_script('#edit-user-form', 'edit-user-' . $edit_id); ?>
</body>
</html>

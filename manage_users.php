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
$captain_exists = active_captain_exists($conn);

if (($_GET['success'] ?? '') === 'user_updated') {
    $success = 'User details updated successfully.';
}

if (($_GET['error'] ?? '') === 'user_not_found') {
    $errors[] = 'User not found.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user_id'])) {
    $archive_id = (int)$_POST['archive_user_id'];

    if (!$archive_columns_ready) {
        $errors[] = 'User archive columns could not be prepared.';
    } elseif ($archive_id === (int)($_SESSION['user_id'] ?? 0)) {
        $errors[] = 'You cannot archive your own account.';
    } elseif ($archive_id > 0) {
        $archive_user = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT id, first_name, middle_name, last_name, role, is_archived FROM users WHERE id = '$archive_id' LIMIT 1"
        ));

        if (!$archive_user) {
            $errors[] = 'User not found.';
        } elseif ((int)($archive_user['is_archived'] ?? 0) === 1) {
            $errors[] = 'This user is already archived.';
        } else {
            $new_role = archived_user_role_label($archive_user['role'] ?? '');
            $safe_new_role = mysqli_real_escape_string($conn, $new_role);
            $update_parts = [
                "role = '$safe_new_role'",
                "is_archived = 1",
                "archived_at = NOW()"
            ];

            if ($new_role === 'Former Captain' && user_column_exists($conn, 'term_end')) {
                $update_parts[] = "term_end = COALESCE(term_end, CURDATE())";
            }

            $update = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE id = '$archive_id'";
            if (mysqli_query($conn, $update)) {
                $archived_name = trim(($archive_user['first_name'] ?? '') . ' ' . ($archive_user['middle_name'] ?? '') . ' ' . ($archive_user['last_name'] ?? ''));
                $archived_name = $archived_name !== '' ? $archived_name : "ID#$archive_id";
                $action_desc = mysqli_real_escape_string($conn, "Captain archived user: $archived_name ($new_role)");
                mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
                $success = 'User archived successfully.';
            } else {
                $errors[] = 'Failed to archive user. Please try again.';
            }
        }
    }
}


$users_where = $archive_columns_ready
    ? "WHERE COALESCE(is_archived, 0) = 0 AND role NOT LIKE 'Former%'"
    : "WHERE role NOT LIKE 'Former%'";
$users_query = mysqli_query($conn, "SELECT id, first_name, middle_name, last_name, role, username FROM users $users_where ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Lists - Barangay Captain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-navy: #1e293b; --accent-blue: #2563eb; --logo-orange: #ff9800; --text-gray: #64748b; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .panel { background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }

        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .panel-header h3 { margin: 0; font-size: 16px; color: #1e293b; font-weight: 700; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        label { font-weight: 600; color: #1e293b; font-size: 13px; }
        input, select {
            padding: 10px 14px; border: 1px solid #d7dbe1; border-radius: 8px;
            font-family: inherit; font-size: 14px; outline: none; background: #fff;
        }
        input:focus, select:focus { border-color: var(--accent-blue); background: white; }

        .btn-save {
            background: var(--accent-blue); color: white; padding: 10px 18px; border: none;
            border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px; margin-top: 8px;
        }

        .toast-stack {
            position: fixed;
            top: 24px;
            right: 28px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: min(380px, calc(100vw - 40px));
        }
        .toast {
            display: grid;
            grid-template-columns: 38px 1fr 24px;
            gap: 12px;
            align-items: start;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
            color: #1e293b;
            animation: toastIn 0.22s ease-out;
        }
        .toast-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .toast-success .toast-icon { background: #dcfce7; color: #166534; }
        .toast-error .toast-icon { background: #fee2e2; color: #991b1b; }
        .toast-title { font-weight: 800; font-size: 14px; margin-bottom: 3px; }
        .toast-message { color: #64748b; font-size: 13px; line-height: 1.45; }
        .toast-close {
            width: 24px;
            height: 24px;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 6px;
        }
        .toast-close:hover { background: #f1f5f9; color: #334155; }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(18px); }
            to { opacity: 1; transform: translateX(0); }
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; color: var(--text-gray); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }

        .role-badge {
            padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;
        }
        .role-captain { background: #fef3c7; color: #92400e; }
        .role-secretary { background: #dbeafe; color: #1e40af; }
        .role-former { background: #e2e8f0; color: #475569; }
        .role-former-secretary { background: #ede9fe; color: #5b21b6; }

        .btn-archive {
            background: none; border: none; color: #b45309; cursor: pointer; font-weight: 600;
            font-size: 13px; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px;
            border-radius: 8px;
        }

        .you-badge { background: #f0fdf4; color: #166534; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; margin-left: 8px; }

        .password-wrap { position: relative; }
        .password-wrap input { width: 100%; box-sizing: border-box; padding-right: 40px; }
        .eye-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 14px; }

        .empty-state { text-align: center; padding: 40px; color: var(--text-gray); }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .toast-stack { top: 16px; right: 20px; left: 20px; width: auto; }
        }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 12px; width: 450px; max-width: 90%; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); transform: scale(0.95); transition: transform 0.2s ease-out; border: 1px solid #e2e8f0; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-body { padding: 24px; text-align: center; }
        
        .confirm-icon { width: 56px; height: 56px; background: #fffbeb; color: #b45309; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px; }
        .confirm-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .confirm-text { color: #64748b; line-height: 1.5; margin-bottom: 24px; font-size: 14px; }
        .confirm-actions { display: flex; gap: 12px; justify-content: center; }
        .btn-cancel { padding: 10px 18px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
        .btn-confirm-archive { padding: 10px 18px; border-radius: 8px; border: none; background: #b45309; color: white; font-weight: 600; cursor: pointer; }
        .btn-confirm-archive:hover { background: #92400e; transform: translateY(-1px); }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<?php if (!empty($errors) || $success !== ''): ?>
    <div class="toast-stack" aria-live="polite">
        <?php if ($success !== ''): ?>
            <div class="toast toast-success">
                <div class="toast-icon"><i class="fa-solid fa-check"></i></div>
                <div>
                    <div class="toast-title">Success</div>
                    <div class="toast-message"><?php echo htmlspecialchars($success); ?></div>
                </div>
                <button type="button" class="toast-close" aria-label="Close notification" onclick="this.closest('.toast').remove()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
            <div class="toast toast-error">
                <div class="toast-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="toast-title">Action Needed</div>
                    <div class="toast-message"><?php echo htmlspecialchars($err); ?></div>
                </div>
                <button type="button" class="toast-close" aria-label="Close notification" onclick="this.closest('.toast').remove()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="main-container">
    <header class="top-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin:0;">Manage Users</h2>
            <p style="margin:0; color: var(--text-gray);">Create and manage system accounts</p>
        </div>
    </header>

    <div class="content-body">


        <div class="panel">
            <div class="panel-header">
                <h3><i class="fa-solid fa-users" style="color: var(--accent-blue); margin-right: 8px;"></i>System Users</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_query && mysqli_num_rows($users_query) > 0): ?>
                        <?php while ($u = mysqli_fetch_assoc($users_query)): ?>
                            <?php
                                $is_self = ((int)$u['id'] === (int)($_SESSION['user_id'] ?? 0));
                                $display_name = trim(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                $display_name = $display_name !== '' ? $display_name : ($u['username'] ?? 'this user');
                                $archive_role = archived_user_role_label($u['role'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($display_name); ?></strong>
                                    <?php if ($is_self): ?><span class="you-badge">You</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td>
                                    <?php $badge_class = user_role_badge_class($u['role'] ?? ''); ?>
                                    <span class="role-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($u['role']); ?></span>
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo (int)$u['id']; ?>" style="background: none; border: none; color: var(--accent-blue); cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 8px; text-decoration: none;">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>
                                    <?php if (!$is_self): ?>
                                        <button type="button" class="btn-archive" onclick="openArchiveModal(<?php echo (int)$u['id']; ?>, <?php echo htmlspecialchars(json_encode($display_name), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($archive_role), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fa-solid fa-box-archive"></i> Archive
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">No users found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal-overlay" id="archiveModal">
    <div class="modal-content">
        <div class="modal-body">
            <div class="confirm-icon">
                <i class="fa-solid fa-box-archive"></i>
            </div>
            <div class="confirm-title">Archive User?</div>
            <div class="confirm-text">
                Are you sure you want to archive <strong id="archiveUserName" style="color: #1e293b;">this user</strong>?<br>
                This account will move to Archived Users and be labeled <strong id="archiveUserRole" style="color: #1e293b;">Former User</strong>.
            </div>
            <form method="POST" id="archiveForm">
                <input type="hidden" name="archive_user_id" id="archiveUserIdInput">
                <div class="confirm-actions">
                    <button type="button" class="btn-cancel" onclick="closeArchiveModal()">Cancel</button>
                    <button type="submit" class="btn-confirm-archive">Yes, Archive User</button>
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

    function openArchiveModal(id, name, roleLabel) {
        document.getElementById('archiveUserIdInput').value = id;
        document.getElementById('archiveUserName').textContent = name;
        document.getElementById('archiveUserRole').textContent = roleLabel;
        const modal = document.getElementById('archiveModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function closeArchiveModal() {
        const modal = document.getElementById('archiveModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    window.onclick = function(event) {
        const modal = document.getElementById('archiveModal');
        if (event.target == modal) {
            closeArchiveModal();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toast').forEach(function(toast) {
            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(18px)';
                toast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                setTimeout(function() {
                    toast.remove();
                }, 220);
            }, 4500);
        });

        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('success') || url.searchParams.has('error')) {
                url.searchParams.delete('success');
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
            }
        }
    });
</script>

</body>
</html>

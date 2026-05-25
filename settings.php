<?php
include('db.php');
session_start();

// Ensure the user is logged in and has a role assigned
if(!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $full_name = trim("$first_name $middle_name $last_name");
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($first_name === '' || $last_name === '' || $username === '') {
            $errors[] = 'First name, last name, and username are required.';
        } else {
            $safe_user = mysqli_real_escape_string($conn, $username);
            $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe_user' AND id != '$user_id'");
            if ($check && mysqli_num_rows($check) > 0) {
                $errors[] = "Username is already taken by another account.";
            } else {
                $col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
                $email_col_exists = ($col_check && mysqli_num_rows($col_check) > 0);

                if ($email_col_exists && $email !== '') {
                    $email_check_value = mysqli_real_escape_string($conn, strtolower($email));
                    $email_check = mysqli_query(
                        $conn,
                        "SELECT id FROM users WHERE LOWER(email) = '$email_check_value' AND id != '$user_id' LIMIT 1"
                    );
                    if ($email_check && mysqli_num_rows($email_check) > 0) {
                        $errors[] = "Email address is already used by another account.";
                    }
                }

                if (empty($errors)) {
                    $safe_name = mysqli_real_escape_string($conn, $full_name);
                    $safe_email = mysqli_real_escape_string($conn, $email);

                    $safe_first = mysqli_real_escape_string($conn, $first_name);
                    $safe_middle = mysqli_real_escape_string($conn, $middle_name);
                    $safe_last = mysqli_real_escape_string($conn, $last_name);

                    if ($email_col_exists) {
                        $sql = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', username = '$safe_user', email = '$safe_email' WHERE id = '$user_id'";
                    } else {
                        $sql = "UPDATE users SET first_name = '$safe_first', middle_name = '$safe_middle', last_name = '$safe_last', username = '$safe_user' WHERE id = '$user_id'";
                    }

                    if (mysqli_query($conn, $sql)) {
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['username'] = $username;
                        $success = "Profile information updated successfully.";
                    } else {
                        $errors[] = "Failed to update profile information.";
                    }
                }
            }
        }
    } elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '') {
            $errors[] = 'All password fields are required.';
        } else {
            $q = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
            $row = mysqli_fetch_assoc($q);
            $current_hash = $row['password'] ?? '';

            if (!password_verify($current_password, $current_hash)) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($new_password) < 4) {
                $errors[] = "New password must be at least 4 characters.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                if (mysqli_query($conn, "UPDATE users SET password = '$new_hash' WHERE id = '$user_id'")) {
                    $action_desc = mysqli_real_escape_string($conn, "User #" . $user_id . " (" . ($_SESSION['full_name'] ?? 'User') . ") updated their password");
                    mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
                    $success = "Password updated successfully.";
                } else {
                    $errors[] = "Failed to update password.";
                }
            }
        }
    }
}

$user_email = '';
if ($user_id) {
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
    if ($q && mysqli_num_rows($q) > 0) {
        $u = mysqli_fetch_assoc($q);
        $user_email = $u['email'] ?? '';
    }
}

// Calculate initials for profile summary avatar
$first_name_initial = mb_substr($u['first_name'] ?? $_SESSION['username'] ?? 'U', 0, 1);
$last_name_initial = mb_substr($u['last_name'] ?? '', 0, 1);
$initials = strtoupper($first_name_initial . $last_name_initial);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background: #f8fafc;
            overflow: hidden;
        }
        
        body.dark-mode {
            background: #0f172a !important;
        }
        
        .main-container {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            background: transparent !important;
        }

        .user-profile-container {
            position: relative;
        }
        
        .user-pill {
            display: flex;
            align-items: center;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-pill:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .avatar {
            background: #824E39;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(130, 78, 57, 0.2);
        }
        
        .logout-dropdown {
            position: absolute;
            top: 110%;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            width: 200px;
            display: none;
            z-index: 100;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .logout-dropdown.show {
            display: block;
        }

        .dropdown-header {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 13px;
        }

        .dropdown-header b {
            display: block;
            color: #1e293b;
            margin-top: 2px;
            font-size: 14px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s ease;
        }

        .logout-btn:hover {
            background: #fef2f2;
        }

        /* Settings layout wrapper */
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 28px;
            align-items: start;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        @media (max-width: 992px) {
            .settings-layout {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        /* Profile Summary Card (Left Column) */
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-avatar-wrapper {
            position: relative;
            margin-bottom: 18px;
        }

        .profile-initials-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #824E39 0%, #693C2A 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(130, 78, 57, 0.25);
            border: 4px solid #ffffff;
            outline: 1px solid #e2e8f0;
            transition: transform 0.3s ease;
        }
        

        .profile-name {
            margin: 0 0 10px 0;
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(130, 78, 57, 0.08);
            color: #824E39;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            text-transform: uppercase;
        }

        .profile-meta-list {
            width: 100%;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .profile-meta-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }

        .meta-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .profile-meta-item:hover .meta-icon {
            background: rgba(130, 78, 57, 0.08);
            color: #824E39;
        }

        .meta-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .meta-label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 13px;
            color: #334155;
            font-weight: 600;
            word-break: break-all;
        }

        /* Settings Cards (Right Column) */
        .settings-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .settings-card:hover {
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(130, 78, 57, 0.08);
            color: #824E39;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .card-header h3 {
            margin: 0 !important;
            font-size: 18px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            border: none !important;
            padding-bottom: 0 !important;
        }

        .card-header p {
            margin: 2px 0 0 0 !important;
            font-size: 13px !important;
            color: #64748b !important;
        }

        /* Form styling */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 600px) {
            .form-grid.two-cols {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 13px;
            color: #475569;
        }
        
        .form-group label i {
            color: #824E39;
            font-size: 13px;
        }

        input {
            width: 100%; 
            padding: 11px 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-family: 'Inter', sans-serif; 
            font-size: 14px; 
            box-sizing: border-box; 
            background: #ffffff; 
            outline: none; 
            color: #0f172a;
            transition: all 0.2s ease;
        }

        input:hover {
            border-color: #94a3b8;
        }

        input:focus {
            border-color: #824E39;
            box-shadow: 0 0 0 3px rgba(130, 78, 57, 0.15);
        }

        input:disabled {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
            border-color: #e2e8f0;
        }

        /* Auto-width Button */
        .btn-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 28px;
        }

        .btn-primary {
            background: #824E39;
            color: #ffffff;
            border: none;
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(130, 78, 57, 0.2);
        }

        .btn-primary:hover {
            background: #693C2A;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(130, 78, 57, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(130, 78, 57, 0.2);
        }

        /* Notifications styling */
        .error-box, .success-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid;
            width: 100%;
            box-sizing: border-box;
        }

        .error-box {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .success-box {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        /* --- DARK MODE OVERRIDES --- */
        body.dark-mode .profile-card {
            background: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
        }

        body.dark-mode .profile-card:hover {
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4) !important;
        }

        body.dark-mode .profile-initials-avatar {
            border-color: #1e293b !important;
            outline-color: #334155 !important;
            box-shadow: 0 8px 20px rgba(130, 78, 57, 0.4) !important;
        }

        body.dark-mode .profile-name {
            color: #ffffff !important;
        }

        body.dark-mode .role-pill {
            background: rgba(130, 78, 57, 0.2) !important;
            color: #d29d8a !important;
        }

        body.dark-mode .profile-meta-list {
            border-top-color: #334155 !important;
        }

        body.dark-mode .meta-icon {
            background: #0f172a !important;
            color: #94a3b8 !important;
        }

        body.dark-mode .profile-meta-item:hover .meta-icon {
            background: rgba(130, 78, 57, 0.2) !important;
            color: #d29d8a !important;
        }

        body.dark-mode .meta-label {
            color: #64748b !important;
        }

        body.dark-mode .meta-value {
            color: #cbd5e1 !important;
        }

        body.dark-mode .settings-card {
            background: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
        }

        body.dark-mode .settings-card:hover {
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4) !important;
        }

        body.dark-mode .card-header {
            border-bottom-color: #334155 !important;
        }

        body.dark-mode .card-icon {
            background: rgba(130, 78, 57, 0.2) !important;
            color: #d29d8a !important;
        }

        body.dark-mode .card-header p {
            color: #94a3b8 !important;
        }

        body.dark-mode .form-group label {
            color: #cbd5e1 !important;
        }

        body.dark-mode .form-group label i {
            color: #d29d8a !important;
        }

        body.dark-mode input {
            background: #0f172a !important;
            border-color: #334155 !important;
            color: #ffffff !important;
        }

        body.dark-mode input:hover {
            border-color: #475569 !important;
        }

        body.dark-mode input:focus {
            border-color: #824E39 !important;
            box-shadow: 0 0 0 3px rgba(130, 78, 57, 0.25) !important;
        }

        body.dark-mode input:disabled {
            background: #1e293b !important;
            color: #64748b !important;
            border-color: #334155 !important;
        }

        body.dark-mode .error-box {
            background: rgba(239, 68, 68, 0.1) !important;
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
        }

        body.dark-mode .success-box {
            background: rgba(34, 197, 94, 0.1) !important;
            color: #86efac !important;
            border-color: rgba(34, 197, 94, 0.2) !important;
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2>Settings</h2>
            <p>Manage your account and system preferences</p>
        </div>

        <div class="user-profile-container">
            <div class="user-pill" onclick="toggleLogout()">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div style="line-height: 1.2; margin-left: 15px; margin-right: 15px;">
                    <div style="font-weight: 600; color: #1e293b;" class="role-text"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                    <div style="color:#64748b; font-size: 13px;">Authorized Personnel</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #94a3b8;"></i>
            </div>
            
            <div class="logout-dropdown" id="logoutDropdown">
                <div class="dropdown-header">Signed in as<br><b><?php echo htmlspecialchars($_SESSION['role']); ?></b></div>
                <a href="logout.php" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="content-body">
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 16px;"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box">
                <i class="fa-solid fa-circle-check" style="font-size: 16px;"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <div class="settings-layout">
            <!-- Left Column: Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-wrapper">
                    <div class="profile-initials-avatar">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                </div>
                
                <h3 class="profile-name"><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . (!empty($u['middle_name']) ? $u['middle_name'] . ' ' : '') . ($u['last_name'] ?? '')); ?></h3>
                
                <span class="role-pill">
                    <i class="fa-solid fa-shield-halved"></i>
                    <?php echo htmlspecialchars($_SESSION['role']); ?>
                </span>
                
                <div class="profile-meta-list">
                    <div class="profile-meta-item">
                        <span class="meta-icon"><i class="fa-regular fa-user"></i></span>
                        <div class="meta-info">
                            <span class="meta-label">Username</span>
                            <span class="meta-value"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="profile-meta-item">
                        <span class="meta-icon"><i class="fa-regular fa-envelope"></i></span>
                        <div class="meta-info">
                            <span class="meta-label">Email Address</span>
                            <span class="meta-value"><?php echo $user_email ? htmlspecialchars($user_email) : 'Not specified'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings Cards -->
            <div class="forms-column">
                <!-- Profile Information Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <span class="card-icon"><i class="fa-solid fa-user-gear"></i></span>
                        <div>
                            <h3>Profile Information</h3>
                            <p>Update your personal details and contact address</p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid two-cols">
                            <div class="form-group">
                                <label><i class="fa-regular fa-circle-user"></i> First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($u['first_name'] ?? ''); ?>" required placeholder="Enter first name">
                            </div>
                            <div class="form-group">
                                <label><i class="fa-regular fa-circle-user"></i> Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($u['middle_name'] ?? ''); ?>" placeholder="Enter middle name (optional)">
                            </div>
                        </div>
                        <div class="form-grid two-cols" style="margin-top: 20px;">
                            <div class="form-group">
                                <label><i class="fa-regular fa-circle-user"></i> Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($u['last_name'] ?? ''); ?>" required placeholder="Enter last name">
                            </div>
                            <div class="form-group">
                                <label><i class="fa-regular fa-id-badge"></i> Username</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" required placeholder="Enter username">
                            </div>
                        </div>
                        <div class="form-grid two-cols" style="margin-top: 20px;">
                            <div class="form-group">
                                <label><i class="fa-solid fa-user-lock"></i> System Role</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label><i class="fa-regular fa-envelope"></i> Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="No email provided">
                            </div>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" name="update_profile" class="btn-primary">
                                <span class="btn-text">Save</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="settings-card">
                    <div class="card-header">
                        <span class="card-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <div>
                            <h3>Security & Password</h3>
                            <p>Change your password to secure your account</p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fa-solid fa-key"></i> Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        <div class="form-grid two-cols" style="margin-top: 20px;">
                            <div class="form-group">
                                <label><i class="fa-solid fa-lock"></i> New Password</label>
                                <input type="password" name="new_password" placeholder="Min. 4 characters" minlength="4" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fa-solid fa-lock-open"></i> Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password" minlength="4" required>
                            </div>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" name="update_password" class="btn-primary">
                                <span class="btn-text">Update Password</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleLogout() {
        document.getElementById('logoutDropdown').classList.toggle('show');
    }

    window.addEventListener('click', function(e) {
        if (!e.target.closest('.user-profile-container')) {
            const dropdown = document.getElementById('logoutDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    });
</script>

</body>
</html>















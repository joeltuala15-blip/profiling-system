<?php 
include('db.php'); 
include_once('user_archive_helpers.php');
include_once('toast_helpers.php');
session_start(); 

$login_error = false;
$archive_columns_ready = ensure_user_archive_columns($conn);
$page_toasts = [];
$success_toast = app_toast_from_success_code($_GET['success'] ?? '');
if ($success_toast) {
    $page_toasts[] = $success_toast;
}

if(isset($_POST['login'])){

    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password'];

    $email_col_exists = false;
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        $email_col_exists = true;
    }

    $archive_filter = $archive_columns_ready ? " AND COALESCE(is_archived, 0) = 0" : "";
    if ($email_col_exists) {
        $query = "SELECT * FROM users WHERE (username='$user' OR email='$user')$archive_filter";
    } else {
        $query = "SELECT * FROM users WHERE username='$user'$archive_filter";
    }
    $res = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($res);

    if($data && password_verify($pass, $data['password'])){
        $role = $data['role'];
  
        $_SESSION['user_id'] = $data['id'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['role'] = $data['role'];
        $_SESSION['full_name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['middle_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        if($role == "Secretary") {
            header("Location: secretary_dashboard.php?success=login_success");
        } else if ($role == "Barangay Captain" || $role == "Former Captain") {
            header("Location: captain_dashboard.php?success=login_success");
        }
        exit();
    } else {
        $login_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Residents Profiling</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #1e293b;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .logo-header {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            margin-bottom: 12px;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }

        .divider {
            width: 100%;
            height: 1px;
            background-color: #e2e8f0;
            margin: 20px 0;
        }

        .form-header {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .form-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-container {
            position: relative;
            width: 100%;
        }

        .input-container i.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .input-container input {
            width: 100%;
            padding: 11px 14px 11px 38px;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #1e293b;
            outline: none;
            font-family: inherit;
        }

        .input-container input::placeholder {
            color: #94a3b8;
        }

        .input-container input:focus {
            border-color: #824E39;
        }

        .input-container .eye-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            z-index: 10;
        }

        .btn-submit {
            background-color: #824E39;
            color: #ffffff;
            border: none;
            padding: 13px;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 700;
            margin-top: 8px;
            font-family: inherit;
            text-align: center;
        }

        .form-footer {
            text-align: center;
            margin-top: 12px;
            font-size: 13.5px;
            color: #64748b;
        }

        .form-footer a {
            color: #824E39;
            text-decoration: none;
            font-weight: 700;
        }

        .login-error-overlay {
            position: fixed;
            inset: 0;
            z-index: 100001;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(4px);
        }

        .login-error-dialog {
            width: min(420px, 100%);
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
            padding: 24px;
            text-align: left;
            animation: loginModalIn 0.18s ease-out;
        }

        .login-error-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fee2e2;
            color: #991b1b;
            font-size: 18px;
            margin-bottom: 16px;
        }

        .login-error-title {
            margin: 0 0 6px 0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
        }

        .login-error-message {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .login-error-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 22px;
        }

        .login-error-ok {
            border: none;
            border-radius: 8px;
            background: #824E39;
            color: #ffffff;
            padding: 10px 18px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .login-error-ok:hover {
            background: #693C2A;
        }

        @keyframes loginModalIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .btn-submit:hover,
        .eye-icon:hover,
        .form-footer a:hover {
            cursor: pointer !important;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 24px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>

<?php render_app_toasts($page_toasts); ?>

<div class="login-card">

    <div class="logo-header">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRFPOnNDg4Y5AhoHbUTqz-33jP3WX2ehWimhg&s" class="brand-logo" alt="Barangay Logo">
        <h1 class="brand-title">Barangay Pulantubig</h1>
        <div class="brand-subtitle">Residents' Profiling System</div>
    </div>
    
    <div class="divider"></div>

    <div class="form-header">
        <h2 class="form-title">Login</h2>
        <p class="form-subtitle">Sign in to access your dashboard</p>
    </div>

    <form class="login-form" method="POST" action="">
        <div class="form-group">
            <label for="username">Username or Email</label>
            <div class="input-container">
                <i class="fa-solid fa-user field-icon"></i>
                <input type="text" id="username" name="username" placeholder="Enter username or email" required>
            </div>
        </div>

        <div class="form-group">
            <label for="pass">Password</label>
            <div class="input-container">
                <i class="fa-solid fa-lock field-icon"></i>
                <input type="password" id="pass" name="password" placeholder="Enter password" required>
                <i class="fa-regular fa-eye eye-icon" onclick="togglePassword()"></i>
            </div>
        </div>

        <button type="submit" name="login" class="btn-submit">Login</button>
        
        <div class="form-footer">
            Don't have an account? <a href="register.php">Create Account</a>
        </div>
    </form>
</div>

<?php if ($login_error): ?>
    <div class="login-error-overlay" id="loginErrorModal" role="dialog" aria-modal="true" aria-labelledby="loginErrorTitle">
        <div class="login-error-dialog">
            <div class="login-error-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h2 class="login-error-title" id="loginErrorTitle">Login Failed</h2>
            <p class="login-error-message">Invalid username or password. Please check your credentials and try again.</p>
            <div class="login-error-actions">
                <button type="button" class="login-error-ok" onclick="closeLoginError()">OK</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    function closeLoginError() {
        const modal = document.getElementById('loginErrorModal');
        if (modal) modal.remove();
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeLoginError();
        }
    });

    function togglePassword() {
        var x = document.getElementById("pass");
        var icon = document.querySelector(".eye-icon");
        if (x.type === "password") {
            x.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            x.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
</script>

</body>
</html>

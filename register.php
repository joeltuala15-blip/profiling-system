<?php 
include('db.php'); 
include_once('user_archive_helpers.php');
include_once('toast_helpers.php');
session_start(); 

$captain_exists = active_captain_exists($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Residents Profiling</title>
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

        .register-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            width: 100%;
            max-width: 600px;
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

        .register-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 20px;
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

        .input-container input,
        .input-container select {
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

        .input-container input:focus,
        .input-container select:focus {
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
            grid-column: span 2;
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
            grid-column: span 2;
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

        .status-card {
            text-align: center;
            padding: 10px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .status-icon.warning {
            background-color: #d97706;
        }

        .status-icon.error {
            background-color: #dc2626;
        }

        .status-icon.success {
            background-color: #16a34a;
        }

        .status-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .status-desc {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 24px;
            max-width: 380px;
        }

        .btn-action {
            background-color: #824E39;
            color: #ffffff;
            border: none;
            padding: 11px 32px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            font-family: inherit;
        }

        .btn-submit:hover,
        .btn-action:hover,
        .input-container select:hover,
        .eye-icon:hover,
        .form-footer a:hover {
            cursor: pointer !important;
        }

        @media (max-width: 600px) {
            .register-card {
                padding: 24px;
                border-radius: 16px;
            }
            
            .register-form {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .btn-submit,
            .form-footer {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="logo-header">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRFPOnNDg4Y5AhoHbUTqz-33jP3WX2ehWimhg&s" class="brand-logo" alt="Barangay Logo">
        <h1 class="brand-title">Barangay Pulantubig</h1>
        <div class="brand-subtitle">Residents' Profiling System</div>
    </div>
    
    <div class="divider"></div>
    
    <?php 
    if(isset($_POST['register'])): 
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $user = mysqli_real_escape_string($conn, $_POST['username']);
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email_col_exists = false;
        $allowed_roles = ['Secretary', 'Barangay Captain'];

        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
        if ($col_check && mysqli_num_rows($col_check) > 0) {
            $email_col_exists = true;
        }

        $check_user = mysqli_query($conn, "SELECT username FROM users WHERE username = '$user'");

        if(!in_array($role, $allowed_roles, true)): ?>
            <div class="status-card">
                <div class="status-icon warning">
                    <i class="fa-solid fa-exclamation"></i>
                </div>
                <h2 class="status-title">Invalid Role</h2>
                <p class="status-desc">Please choose a valid system role before creating an account.</p>
                <a href="register.php" class="btn-action">Go Back</a>
            </div>

        <?php elseif($role === 'Barangay Captain' && active_captain_exists($conn)): ?>
            <div class="status-card">
                <div class="status-icon warning">
                    <i class="fa-solid fa-exclamation"></i>
                </div>
                <h2 class="status-title">Captain Already Exists</h2>
                <p class="status-desc">Only one Barangay Captain account can be active in the system.</p>
                <a href="register.php" class="btn-action">Choose Another Role</a>
            </div>

        <?php elseif(mysqli_num_rows($check_user) > 0): ?>
            <div class="status-card">
                <div class="status-icon warning">
                    <i class="fa-solid fa-exclamation"></i>
                </div>
                <h2 class="status-title">Username Taken</h2>
                <p class="status-desc">The username <strong>"<?php echo htmlspecialchars($user); ?>"</strong> is already in use by another account.</p>
                <a href="register.php" class="btn-action">Choose Another</a>
            </div>

        <?php elseif($email_col_exists && $email === ''): ?>
            <div class="status-card">
                <div class="status-icon warning">
                    <i class="fa-solid fa-exclamation"></i>
                </div>
                <h2 class="status-title">Email Required</h2>
                <p class="status-desc">Please provide a valid email address to complete registration.</p>
                <a href="register.php" class="btn-action">Go Back</a>
            </div>

        <?php elseif($email_col_exists && mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE LOWER(email) = '" . mysqli_real_escape_string($conn, strtolower($email)) . "' LIMIT 1")) > 0): ?>
            <div class="status-card">
                <div class="status-icon warning">
                    <i class="fa-solid fa-exclamation"></i>
                </div>
                <h2 class="status-title">Email Taken</h2>
                <p class="status-desc">The email address <strong>"<?php echo htmlspecialchars($email); ?>"</strong> is already registered.</p>
                <a href="register.php" class="btn-action">Choose Another</a>
            </div>

        <?php elseif($password !== $confirm_password): ?>
            <div class="status-card">
                <div class="status-icon error">
                    <i class="fa-solid fa-xmark"></i>
                </div>
                <h2 class="status-title">Passwords Mismatch</h2>
                <p class="status-desc">The passwords you entered do not match. Please ensure both fields are identical.</p>
                <a href="register.php" class="btn-action">Try Again</a>
            </div>

        <?php else: 
            $safe_email = mysqli_real_escape_string($conn, $email);
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            if ($email_col_exists) {
                $query = "INSERT INTO users (first_name, middle_name, last_name, role, username, email, password) VALUES ('$first_name', '$middle_name', '$last_name', '$role', '$user', '$safe_email', '$hashed_pass')";
            } else {
                $query = "INSERT INTO users (first_name, middle_name, last_name, role, username, password) VALUES ('$first_name', '$middle_name', '$last_name', '$role', '$user', '$hashed_pass')";
            }
            
            if(mysqli_query($conn, $query)): ?>
                <div class="status-card">
                    <div class="status-icon success">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2 class="status-title">Account Created!</h2>
                    <p class="status-desc">Your official account has been successfully registered. You may now access the system.</p>
                    <a href="login.php" class="btn-action">Login Now</a>
                </div>
            <?php else: ?>
                <div class="status-card">
                    <div class="status-icon error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h2 class="status-title">System Error</h2>
                    <p class="status-desc">Something went wrong during registration. Please contact your system administrator.</p>
                    <a href="register.php" class="btn-action">Go Back</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php else: ?>
        <div class="form-header">
            <h2 class="form-title">Create Account</h2>
            <p class="form-subtitle">Register to join the Residents' Profiling System</p>
        </div>

        <form id="register-form" class="register-form" method="POST">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <div class="input-container">
                    <i class="fa-solid fa-user field-icon"></i>
                    <input type="text" id="first_name" name="first_name" placeholder="e.g. Juan" required>
                </div>
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <div class="input-container">
                    <i class="fa-solid fa-user field-icon"></i>
                    <input type="text" id="middle_name" name="middle_name" placeholder="e.g. Dela">
                </div>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <div class="input-container">
                    <i class="fa-solid fa-user field-icon"></i>
                    <input type="text" id="last_name" name="last_name" placeholder="e.g. Cruz" required>
                </div>
            </div>

            <div class="form-group">
                <label for="role">Select Role</label>
                <div class="input-container">
                    <i class="fa-solid fa-user-tag field-icon"></i>
                    <select id="role" name="role" required>
                        <option value="" disabled selected>Select position</option>
                        <option value="Secretary">Secretary</option>
                        <?php if (!$captain_exists): ?>
                            <option value="Barangay Captain">Barangay Captain</option>
                        <?php endif; ?>
                    </select>
                </div>
                <!-- <?php if ($captain_exists): ?>
                    <small style="color:#64748b; margin-top:6px;">Only one Barangay Captain account can be active.</small>
                <?php endif; ?> -->
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-container">
                    <i class="fa-solid fa-at field-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-container">
                    <i class="fa-solid fa-envelope field-icon"></i>
                    <input type="email" id="email" name="email" placeholder="name@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="pass">Password</label>
                <div class="input-container">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input type="password" id="pass" name="password" placeholder="Create password" required>
                    <i class="fa-regular fa-eye eye-icon" onclick="togglePassword('pass', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_pass">Confirm Password</label>
                <div class="input-container">
                    <i class="fa-solid fa-lock-open field-icon"></i>
                    <input type="password" id="confirm_pass" name="confirm_password" placeholder="Repeat password" required>
                    <i class="fa-regular fa-eye eye-icon" onclick="togglePassword('confirm_pass', this)"></i>
                </div>
            </div>

            <button type="submit" name="register" class="btn-submit">Create Account</button>
            
            <div class="form-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    function togglePassword(inputId, icon) {
        const passwordInput = document.getElementById(inputId);
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            passwordInput.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
</script>
<?php render_form_draft_script('#register-form', 'register-account'); ?>

</body>
</html>

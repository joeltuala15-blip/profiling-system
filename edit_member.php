<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

// Security check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$member_id = $_GET['id'] ?? '';
$safe_id = mysqli_real_escape_string($conn, $member_id);

$query = "SELECT * FROM residents WHERE id = '$safe_id'";
$result = mysqli_query($conn, $query);
$member = mysqli_fetch_assoc($result);

$errors = [];
$page_toasts = [];

$proof_col_exists = false;
$proof_col_check = mysqli_query($conn, "SHOW COLUMNS FROM residents LIKE 'deceased_proof_path'");
if ($proof_col_check && mysqli_num_rows($proof_col_check) > 0) {
    $proof_col_exists = true;
}

if (!$member) {
    header("Location: residents.php?error=member_not_found");
    exit();
}

$member_photo_path = trim((string)($member['photo_path'] ?? ''));
$member_photo_file = $member_photo_path !== '' ? __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $member_photo_path) : '';
$member_has_photo = $member_photo_path !== '' && $member_photo_path !== 'uploads/default.png' && is_file($member_photo_file);

$today = date('Y-m-d');
$cancel_url = 'household_members.php?household_no=' . urlencode($member['household_no']);

// Check if another member in this household is already the Head
$safe_hh = mysqli_real_escape_string($conn, $member['household_no']);
$head_check_query = mysqli_query($conn, "SELECT id FROM residents WHERE household_no = '$safe_hh' AND relationship = 'Head' AND id != '$safe_id' AND COALESCE(is_archived, 0) = 0 LIMIT 1");
$another_head_exists = ($head_check_query && mysqli_num_rows($head_check_query) > 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $photo_update_sql = "";
    $proof_update_sql = "";
    if (($_POST['remove_photo'] ?? '') === '1') {
        $photo_update_sql = "photo_path='', ";
    }

    if (!empty($_POST['photo'])) {
        $data = $_POST['photo'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = base64_decode(substr($data, strpos($data, ',') + 1));
            $filename = "res_" . time() . "_" . $safe_id . ".jpg";
            $filepath = "uploads/" . $filename;
            if (file_put_contents($filepath, $data)) { 
                $photo_update_sql = "photo_path='$filepath', "; 
            }
        }
    }

    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $mname = mysqli_real_escape_string($conn, $_POST['middle_name']);
    $age   = mysqli_real_escape_string($conn, $_POST['age']);
    $sex   = mysqli_real_escape_string($conn, $_POST['sex']);
    $dob   = mysqli_real_escape_string($conn, $_POST['dob']);
    $civil_status = mysqli_real_escape_string($conn, $_POST['civil_status']);
    $employment   = mysqli_real_escape_string($conn, $_POST['employment_status']);
    $education    = mysqli_real_escape_string($conn, $_POST['education']);
    $relationship = mysqli_real_escape_string($conn, $_POST['relationship']);
    $allowed_statuses = ['Active', 'Transferred', 'Deceased'];
    $status = $_POST['status'] ?? 'Active';
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'Active';
    }
    $safe_status = mysqli_real_escape_string($conn, $status);

    $existing_proof = $member['deceased_proof_path'] ?? '';
    if ($status === 'Deceased') {
        if (!$proof_col_exists) {
            $errors[] = 'Database is missing the deceased proof column.';
        }

        if (isset($_FILES['deceased_proof']) && $_FILES['deceased_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['deceased_proof']['error'] === UPLOAD_ERR_OK) {
                $proof_dir = 'uploads/deceased_proofs';
                if (!is_dir($proof_dir)) {
                    mkdir($proof_dir, 0777, true);
                }

                $ext = pathinfo($_FILES['deceased_proof']['name'], PATHINFO_EXTENSION);
                $proof_filename = 'deceased_' . (int)$safe_id . '_' . time();
                if ($ext !== '') {
                    $proof_filename .= '.' . $ext;
                }
                $proof_path = $proof_dir . '/' . $proof_filename;

                if (move_uploaded_file($_FILES['deceased_proof']['tmp_name'], $proof_path)) {
                    $safe_proof_path = mysqli_real_escape_string($conn, $proof_path);
                    $proof_update_sql = "deceased_proof_path='$safe_proof_path', ";
                    $existing_proof = $proof_path;
                } else {
                    $errors[] = 'Failed to upload deceased proof file.';
                }
            } else {
                $errors[] = 'Failed to upload deceased proof file.';
            }
        } elseif ($existing_proof === '' && $existing_proof !== '0') {
            $errors[] = 'Proof file is required when status is Deceased.';
        }
    }

    $is_voter  = isset($_POST['is_voter']) ? 1 : 0;
    $is_4ps    = isset($_POST['is_4ps']) ? 1 : 0;
    $is_pwd    = isset($_POST['is_pwd']) ? 1 : 0;
    $is_solo   = isset($_POST['is_solo']) ? 1 : 0; 

    $age_int   = (int)$age;
    $is_senior = ($age_int >= 60) ? 1 : 0;
    $is_minor  = ($age_int < 18) ? 1 : 0;

    $is_archived = 0;
    $archive_reason = '';
    if ($status === 'Deceased') {
        $is_archived = 1;
        $archive_reason = 'Deceased';
    } elseif ($status === 'Transferred') {
        $is_archived = 1;
        $archive_reason = 'Transferred to Another Location';
    }
    $safe_archive_reason = mysqli_real_escape_string($conn, $archive_reason);

    if (!empty($errors)) {
        $page_toasts[] = app_toast_from_message(implode("\n", $errors));
    } else {
        $update_query = "UPDATE residents SET
            $photo_update_sql
            $proof_update_sql
            last_name='$lname', first_name='$fname', middle_name='$mname',
            age='$age', gender='$sex', dob='$dob', civil_status='$civil_status',
            employment_status='$employment', education='$education', relationship='$relationship',
            is_voter='$is_voter', is_4ps='$is_4ps', is_pwd='$is_pwd',
            is_solo='$is_solo', is_senior='$is_senior', is_minor='$is_minor',
            status='$safe_status', is_archived='$is_archived', archive_reason='$safe_archive_reason'
            WHERE id='$safe_id'";

        if (mysqli_query($conn, $update_query)) {
            $success_code = $is_archived ? 'resident_archived' : 'resident_updated';
            header("Location: household_members.php?household_no=" . rawurlencode($member['household_no']) . "&success=" . $success_code);
            exit();
        } else {
            echo "Database Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Resident Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent-blue: #2563eb;
            --accent-blue-hover: #1d4ed8;
            --page-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-gray: #64748b;
            --border-gray: #e5e7eb;
            --soft-blue: #eff6ff;
            --primary: var(--accent-blue);
            --primary-hover: var(--accent-blue-hover);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--page-bg);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: flex-start;
            min-height: 100vh;
            height: 100vh;
            overflow: hidden;
        }

        .main-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border: 0;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            width: 100%;
            background-color: var(--page-bg);
        }

        .content-panel {
            background: var(--card-bg);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            margin: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .content-body {
            padding: 32px 40px 40px;
            box-sizing: border-box;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .member-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .member-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .member-title {
            font-weight: 800;
            font-size: 14px;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 4px solid var(--accent-blue);
            padding-left: 15px;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
        }

        .inputs-column {
            min-width: 0;
        }

        .input-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; min-width: 0; }

        .form-group label,
        .benefits-section h5 {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            text-transform: none;
            margin: 0;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 12px;
            border-radius: 10px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            color: #0f172a;
        }

        .option-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            overflow-x: hidden;
            overflow-y: hidden;
            padding-bottom: 4px;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .option-row::-webkit-scrollbar {
            width: 0;
            height: 0;
        }


        .option-chip {
            position: relative;
            display: block;
            min-width: 0;
        }

        .option-chip input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            border: 0;
            opacity: 0;
            pointer-events: none;
        }

        .option-chip span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            line-height: 1.25;
            text-align: center;
            box-sizing: border-box;
        }

        .option-chip:hover span {
            border-color: var(--accent-blue);
            background: #f8fafc;
        }

        .option-chip input:focus-visible + span {
            outline: 2px solid rgba(37, 99, 235, 0.25);
            outline-offset: 2px;
        }

        .option-chip input:checked + span {
            background: rgba(130, 78, 57, 0.1);
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }

        .form-group input.age-input {
            background: #f8fafc;
            cursor: default;
            max-width: 160px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(130, 78, 57, 0.1);
        }

        .col-span-2 { grid-column: span 2; }

        .benefits-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
        }

        .benefits-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .footer-nav {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 0 0;
            background: transparent;
            border-top: 1px solid #e5e7eb;
            border-radius: 0;
            margin-top: 4px;
        }

        .btn-prev {
            background: #f1f5f9;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            color: #1e293b;
            cursor: pointer;
        }

        .btn-save {
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-save:hover {
            background: var(--accent-blue-hover);
        }

        .photo-wrap { display: flex; flex-direction: column; gap: 12px; align-items: center; }
        .capture-box {
            width: 180px; height: 220px; background: #f8fafc; border: 1px dashed #cbd5e1;
            border-radius: 14px; display: flex; flex-direction: column; align-items: center;
            justify-content: center; cursor: pointer; color: #4b5563; overflow: hidden; position: relative;
        }
        .capture-box video, .capture-box img { width: 100%; height: 100%; object-fit: cover; }
        .photo-remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.82);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.24);
        }
        .btn-upload-small {
            width: 180px;
            background: var(--accent-blue);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-sizing: border-box;
        }
        body.dark-mode { background-color: #0f172a; color: white; }
        body.dark-mode .main-container { background-color: #0f172a; }
        body.dark-mode .content-panel { background: #0f172a; }
        body.dark-mode .section-header { border-bottom-color: #334155; }
        body.dark-mode h1 { color: white !important; }
        body.dark-mode .member-card { background: #1e293b; border-color: #334155; }
        body.dark-mode .capture-box { background: #0f172a; border-color: #334155; color: #94a3b8; }
        body.dark-mode input,
        body.dark-mode select { background: #0f172a !important; border-color: #334155 !important; color: white !important; }
        body.dark-mode .form-group label,
        body.dark-mode .benefits-section h5 { color: #cbd5e1; }
        body.dark-mode .option-chip span { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        body.dark-mode .option-chip:hover span { background: #172033; border-color: #64748b; }
        body.dark-mode .option-chip input:checked + span { background: rgba(130, 78, 57, 0.28); border-color: var(--accent-blue); color: #f8fafc; }
        body.dark-mode .footer-nav { background: transparent; border-top-color: #334155; }
        body.dark-mode .btn-prev { background: #0f172a; color: white; border-color: #334155; }

        @media (max-width: 1100px) {
            .input-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            body {
                height: auto;
                overflow: auto;
                flex-direction: column;
            }

            .main-container {
                height: auto;
                min-height: calc(100vh - 32px);
            }

            .content-body {
                padding: 22px;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 16px;
            }

            .member-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .member-actions {
                width: 100%;
                flex-direction: column;
            }

            .layout-grid,
            .input-grid {
                grid-template-columns: 1fr;
            }

            .col-span-2 {
                grid-column: span 1;
            }

            .footer-nav {
                flex-direction: column-reverse;
                padding: 18px;
            }

            .btn-prev,
            .btn-save {
                width: 100%;
                box-sizing: border-box;
            }
        }

        /* --- SUCCESS MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 30px; width: 400px; max-width: 90%; text-align: center; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0; }
        .modal-overlay.show { display: flex; }
        .modal-overlay.show .modal-content { transform: scale(1); opacity: 1; }

        .success-icon { width: 80px; height: 80px; background: #dcfce7; color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 24px; animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }

        .modal-title { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 12px; }
        .modal-text { color: #64748b; line-height: 1.6; margin-bottom: 32px; font-size: 15px; }
        .btn-modal { background: var(--primary); color: white; padding: 14px 32px; border: none; border-radius: 16px; font-weight: 700; cursor: pointer; font-size: 15px; transition: all 0.2s; width: 100%; box-shadow: 0 10px 15px -3px rgba(130, 78, 57, 0.3); }
        .btn-modal:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(130, 78, 57, 0.4); }

        body.dark-mode .modal-content { background: #1e293b; border: 1px solid #334155; }
        body.dark-mode .modal-title { color: white; }
        body.dark-mode .modal-text { color: #94a3b8; }
        body.dark-mode .success-icon { background: rgba(34, 197, 94, 0.1); }

    </style>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Residents Profile</h2>
            <p style="margin:0; color: var(--text-gray);">Step 2: Update resident information for Household #<?php echo htmlspecialchars($member['household_no']); ?></p>
        </div>
    </header>
    <div class="content-body">
        <form id="edit-member-form" method="POST" enctype="multipart/form-data">
            <div class="member-card">
                <div class="member-header">
                    <div class="member-title">Member Information</div>
                </div>
                <div class="layout-grid">
            <div class="photo-wrap">
                <div class="capture-box" id="preview-box" onclick="<?php echo !$member_has_photo ? 'startCamera()' : ''; ?>">
                    <?php if ($member_has_photo): ?>
                        <img src="<?php echo htmlspecialchars($member_photo_path); ?>">
                        <button type="button" class="photo-remove-btn" onclick="removeEditPhoto(event)" aria-label="Remove profile picture">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php else: ?>
                        <i class="fa-solid fa-camera" style="font-size:32px; margin-bottom:10px; color: #64748b;"></i>
                        <span style="font-size:11px; font-weight:800;">Click to Capture</span>
                    <?php endif; ?>
                </div>
                <div id="camera-controls" style="display:none; width:180px; gap:8px;">
                    <button type="button" class="btn-capture" onclick="capturePhoto()" style="flex:1; background:#10b981; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-camera"></i> Capture</button>
                    <button type="button" class="btn-cancel-cam" onclick="cancelCamera()" style="background:#ef4444; color:white; border:none; padding:8px 14px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div id="recapture-controls" style="display:<?php echo $member_has_photo ? 'block' : 'none'; ?>; width:180px;">
                    <button type="button" class="btn-recapture" onclick="startCamera()" style="width:100%; background:#f59e0b; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-rotate"></i> Recapture</button>
                </div>
                <label class="btn-upload-small" id="upload-label" style="display:inline-flex;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
                    <input type="file" accept="image/*" style="display:none" onchange="handleFileUpload(this)">
                </label>
                <input type="hidden" name="photo" id="photo-data">
                <input type="hidden" name="remove_photo" id="remove-photo-data" value="0">
            </div>

            <div class="inputs-column">
                <div class="input-grid">
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($member['middle_name']); ?>">
            </div>

            <div class="form-group">
                <label>Gender *</label>
                <?php $gender_value = trim($member['gender'] ?? ''); ?>
                <select name="sex" required>
                    <option value="" <?php echo $gender_value === '' ? 'selected' : ''; ?> disabled>Select gender</option>
                    <?php foreach (['Male', 'Female'] as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $gender_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date of Birth *</label>
                <input type="date" name="dob" id="editDob" value="<?php echo $member['dob']; ?>" required onchange="calculateEditAge(this.value)" max="<?php echo $today; ?>">
            </div>
            <div class="form-group">
                <label>Age</label>
                <input type="number" name="age" id="editAge" value="<?php echo htmlspecialchars($member['age']); ?>" class="age-input" readonly tabindex="-1">
            </div>

            <div class="form-group">
                <label>Relationship to Head *</label>
                <?php
                    $relationship_value = trim($member['relationship'] ?? '');
                    // Normalize old relationship values for the new dropdown options
                    if (in_array($relationship_value, ['Son', 'Daughter', 'Child', 'Son/Daughter'], true)) {
                        $relationship_value = 'Son/Daughter';
                    } elseif (in_array($relationship_value, ['Relative', 'Non-relative', 'Other'], true)) {
                        $relationship_value = 'Other';
                    }
                ?>
                <select name="relationship" required>
                    <option value="" <?php echo $relationship_value === '' ? 'selected' : ''; ?> disabled>Select relationship</option>
                    <?php foreach (['Head' => 'Head of the Household', 'Spouse' => 'Spouse', 'Son/Daughter' => 'Son/Daughter', 'Parent' => 'Parent', 'Sibling' => 'Sibling', 'Grandchild' => 'Grandchild', 'Other' => 'Other'] as $option => $label): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $relationship_value === $option ? 'selected' : ''; ?> <?php echo ($option === 'Head' && $another_head_exists && $relationship_value !== 'Head') ? 'disabled' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Civil Status *</label>
                <?php $civil_value = trim($member['civil_status'] ?? ''); ?>
                <select name="civil_status" required>
                    <option value="" <?php echo $civil_value === '' ? 'selected' : ''; ?> disabled>Select civil status</option>
                    <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $civil_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Employment Status *</label>
                <?php $employment_value = trim($member['employment_status'] ?? ''); ?>
                <select name="employment_status" required>
                    <option value="" <?php echo $employment_value === '' ? 'selected' : ''; ?> disabled>Select employment status</option>
                    <?php foreach (['Employed', 'Unemployed'] as $emp_option): ?>
                        <option value="<?php echo htmlspecialchars($emp_option); ?>" <?php echo (strcasecmp($employment_value, $emp_option) === 0 || ($emp_option === 'Employed' && strtolower($employment_value) === 'yes') || ($emp_option === 'Unemployed' && strtolower($employment_value) === 'no')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Educational Attainment *</label>
                <?php $education_value = trim($member['education'] ?? ''); ?>
                <select name="education" required>
                    <option value="" <?php echo $education_value === '' ? 'selected' : ''; ?> disabled>Select educational attainment</option>
                    <?php foreach (['None / Child', 'Elementary', 'High School', 'Vocational', 'College', 'Post-Graduate'] as $edu_option): ?>
                        <option value="<?php echo htmlspecialchars($edu_option); ?>" <?php echo $education_value === $edu_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($edu_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Resident Status *</label>
                <?php $status_value = $member['status'] ?? 'Active'; ?>
                <select name="status" id="statusSelect" required>
                    <option value="Active" <?php echo $status_value === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Transferred" <?php echo $status_value === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                    <option value="Deceased" <?php echo $status_value === 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                </select>
            </div>
            <div class="form-group col-span-2" id="deceasedProofWrap" style="<?php echo (($member['status'] ?? '') === 'Deceased') ? '' : 'display:none;'; ?>">
                <label>Deceased Proof (Death Certificate or Valid ID, required if Deceased)</label>
                <input type="file" name="deceased_proof" id="editDeceasedProof" data-has-existing="<?php echo !empty($member['deceased_proof_path']) ? '1' : '0'; ?>">
                <?php if (!empty($member['deceased_proof_path'])): ?>
                    <div style="font-size:12px; color:#64748b;">Existing proof: <a href="<?php echo htmlspecialchars($member['deceased_proof_path']); ?>" target="_blank" rel="noopener noreferrer">View file</a></div>
                <?php endif; ?>
            </div>

            <div class="benefits-section">
                <h5>Special Classifications</h5>
                <div class="benefits-grid">
                    <label class="option-chip voter-chip">
                        <input type="checkbox" name="is_voter" id="editVoterCheck" <?php echo $member['is_voter'] ? 'checked' : ''; ?>>
                        <span>Registered Voter</span>
                    </label>
                    <label class="option-chip">
                        <input type="checkbox" name="is_pwd" <?php echo $member['is_pwd'] ? 'checked' : ''; ?>>
                        <span>PWD</span>
                    </label>
                    <label class="option-chip">
                        <input type="checkbox" name="is_solo" <?php echo $member['is_solo'] ? 'checked' : ''; ?>>
                        <span>Solo Parent</span>
                    </label>
                    <label class="option-chip">
                        <input type="checkbox" name="is_senior" id="editSeniorCheck" <?php echo $member['is_senior'] ? 'checked' : ''; ?>>
                        <span>Senior Citizen</span>
                    </label>
                    <label class="option-chip">
                        <input type="checkbox" name="is_minor" id="editMinorCheck" <?php echo $member['is_minor'] ? 'checked' : ''; ?>>
                        <span>Minor</span>
                    </label>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-nav">
            <button type="button" class="btn-prev" onclick="window.location.href='<?php echo $cancel_url; ?>'">Cancel</button>
            <button type="submit" class="btn-save">Save Changes</button>
        </div>
    </form>
    </div>
</div>

<script>
    const editPhotoCanvasWidth = 400;
    const editPhotoCanvasHeight = 500;

    function calculateEditAge(dobVal) {
        if (!dobVal) return;
        const birth = new Date(dobVal);
        const now = new Date();
        let age = now.getFullYear() - birth.getFullYear();
        if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) age--;
        age = age >= 0 ? age : 0;
        
        document.getElementById('editAge').value = age;
        updateClassificationFromAge(age);
    }

    function updateClassificationFromAge(ageVal) {
        const age = parseInt(ageVal, 10) || 0;
        const seniorCheck = document.getElementById('editSeniorCheck');
        const minorCheck = document.getElementById('editMinorCheck');
        const voterCheck = document.getElementById('editVoterCheck');
        const isSenior = age >= 60;
        const isMinor = age < 18;

        seniorCheck.checked = isSenior;
        minorCheck.checked = isMinor;

        const form = document.querySelector('form');
        if (form) {
            form.dataset.isSenior = isSenior ? '1' : '0';
            form.dataset.isMinor = isMinor ? '1' : '0';
        }

        if (voterCheck) {
            voterCheck.disabled = isMinor;
            if (isMinor) {
                voterCheck.checked = false;
            }
        }
    }

    function setupClassificationGuards() {
        const form = document.querySelector('form');
        const voterCheck = document.getElementById('editVoterCheck');
        const voterChip = document.querySelector('.voter-chip');
        const seniorCheck = document.getElementById('editSeniorCheck');
        const minorCheck = document.getElementById('editMinorCheck');
        if (!form) return;

        if (!form.dataset.isSenior) {
            form.dataset.isSenior = '0';
            form.dataset.isMinor = '0';
        }

        const blockToggle = (event, message) => {
            event.preventDefault();
            showAppToast(message, 'error', 'Action Needed');
        };

        const handleVoterAttempt = (event) => {
            if (form.dataset.isMinor === '1') {
                blockToggle(event, 'Minors cannot be registered voters.');
            }
        };

        if (voterCheck) {
            voterCheck.addEventListener('click', handleVoterAttempt);
        }

        if (voterChip) {
            voterChip.addEventListener('click', handleVoterAttempt);
        }

        if (seniorCheck) {
            seniorCheck.addEventListener('click', (event) => {
                if (form.dataset.isSenior === '1') {
                    blockToggle(event, 'Senior status is automatic based on age.');
                } else {
                    blockToggle(event, 'Senior status applies only at age 60+.');
                }
            });
        }

        if (minorCheck) {
            minorCheck.addEventListener('click', (event) => {
                if (form.dataset.isMinor === '1') {
                    blockToggle(event, 'Minor status is automatic based on age.');
                } else {
                    blockToggle(event, 'Minor status applies only below 18.');
                }
            });
        }
    }

    function updateDeceasedProofRequirement() {
        const proofInput = document.getElementById('editDeceasedProof');
        const proofWrap = document.getElementById('deceasedProofWrap');
        const statusSelect = document.getElementById('statusSelect');
        if (!proofInput || !proofWrap || !statusSelect) return;
        const isDeceased = statusSelect.value === 'Deceased';
        const hasExisting = proofInput.dataset.hasExisting === '1';
        proofInput.required = isDeceased && !hasExisting;
        proofWrap.style.display = isDeceased ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateClassificationFromAge(document.getElementById('editAge').value);
        setupClassificationGuards();
        updateDeceasedProofRequirement();
        const statusSelect = document.getElementById('statusSelect');
        if (statusSelect) {
            statusSelect.addEventListener('change', updateDeceasedProofRequirement);
        }
    });

    let cameraStream = null;

    function dispatchEditPhotoDraftChange(input) {
        if (!input) return;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setEditPhotoData(value) {
        const input = document.getElementById('photo-data');
        if (!input) return;
        input.value = value || '';
        dispatchEditPhotoDraftChange(input);
    }

    function setEditRemovePhotoFlag(value) {
        const input = document.getElementById('remove-photo-data');
        if (!input) return;
        input.value = value ? '1' : '0';
        dispatchEditPhotoDraftChange(input);
    }

    function emptyEditPhotoPreview() {
        const previewBox = document.getElementById('preview-box');
        const cameraControls = document.getElementById('camera-controls');
        const recaptureControls = document.getElementById('recapture-controls');
        const uploadLabel = document.getElementById('upload-label');
        if (!previewBox) return;

        previewBox.innerHTML = `
            <i class="fa-solid fa-camera" style="font-size:32px; margin-bottom:10px; color: #64748b;"></i>
            <span style="font-size:11px; font-weight:800;">Click to Capture</span>
        `;
        previewBox.onclick = startCamera;
        if (cameraControls) cameraControls.style.display = 'none';
        if (recaptureControls) recaptureControls.style.display = 'none';
        if (uploadLabel) uploadLabel.style.display = 'inline-flex';
    }

    function showEditPhotoPreview(dataUrl) {
        if (!dataUrl || !dataUrl.startsWith('data:image/')) return;

        const previewBox = document.getElementById('preview-box');
        const cameraControls = document.getElementById('camera-controls');
        const recaptureControls = document.getElementById('recapture-controls');
        const uploadLabel = document.getElementById('upload-label');
        if (!previewBox) return;

        previewBox.innerHTML = `
            <img src="${dataUrl}" alt="Resident photo">
            <button type="button" class="photo-remove-btn" onclick="removeEditPhoto(event)" aria-label="Remove profile picture">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewBox.onclick = null;
        if (cameraControls) cameraControls.style.display = 'none';
        if (recaptureControls) recaptureControls.style.display = 'block';
        if (uploadLabel) uploadLabel.style.display = 'inline-flex';
    }

    function showExistingEditPhoto(src) {
        const previewBox = document.getElementById('preview-box');
        if (!previewBox || !src) return;

        previewBox.innerHTML = `
            <img src="${src}" alt="Resident photo">
            <button type="button" class="photo-remove-btn" onclick="removeEditPhoto(event)" aria-label="Remove profile picture">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewBox.onclick = null;
    }

    function removeEditPhoto(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }

        setEditPhotoData('');
        setEditRemovePhotoFlag(true);
        emptyEditPhotoPreview();

        const fileInput = document.querySelector('#upload-label input[type="file"]');
        if (fileInput) {
            fileInput.value = '';
        }
    }

    function restoreEditPhotoDraft() {
        const input = document.getElementById('photo-data');
        if (input && input.value && input.value.startsWith('data:image/')) {
            setEditRemovePhotoFlag(false);
            showEditPhotoPreview(input.value);
            return;
        }

        const removeInput = document.getElementById('remove-photo-data');
        if (removeInput && removeInput.value === '1') {
            emptyEditPhotoPreview();
        }
    }

    function imageFileToEditResidentPhoto(file, callback) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = editPhotoCanvasWidth;
                canvas.height = editPhotoCanvasHeight;

                const ctx = canvas.getContext('2d');
                const targetRatio = editPhotoCanvasWidth / editPhotoCanvasHeight;
                const sourceRatio = img.naturalWidth / img.naturalHeight;
                let sx = 0;
                let sy = 0;
                let sw = img.naturalWidth;
                let sh = img.naturalHeight;

                if (sourceRatio > targetRatio) {
                    sw = img.naturalHeight * targetRatio;
                    sx = (img.naturalWidth - sw) / 2;
                } else if (sourceRatio < targetRatio) {
                    sh = img.naturalWidth / targetRatio;
                    sy = (img.naturalHeight - sh) / 2;
                }

                ctx.drawImage(img, sx, sy, sw, sh, 0, 0, editPhotoCanvasWidth, editPhotoCanvasHeight);
                callback(canvas.toDataURL('image/jpeg', 0.86));
            };
            img.onerror = function() {
                callback(event.target.result);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }

    function handleFileUpload(input) {
        if (input.files && input.files[0]) {
            imageFileToEditResidentPhoto(input.files[0], function(dataUrl) {
                setEditPhotoData(dataUrl);
                setEditRemovePhotoFlag(false);
                showEditPhotoPreview(dataUrl);
            });
        }
    }

    function startCamera() {
        const div = document.getElementById('preview-box');
        const camControls = document.getElementById('camera-controls');
        const recapControls = document.getElementById('recapture-controls');

        div.onclick = null;

        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        div.innerHTML = ''; 
        div.appendChild(video);
        
        navigator.mediaDevices.getUserMedia({ video: true }).then(s => {
            video.srcObject = s; 
            video.play();
            cameraStream = s;
            
            camControls.style.display = 'flex';
            recapControls.style.display = 'none';
        }).catch(err => {
            showAppToast('Camera access denied.', 'error', 'Camera Unavailable');
            div.innerHTML = `<i class="fa-solid fa-camera-slash" style="font-size:32px;"></i>`;
            div.onclick = startCamera;
        });
    }

    function capturePhoto() {
        const div = document.getElementById('preview-box');
        const video = div.querySelector('video');
        if (!video) return;

        const canvas = document.createElement('canvas');
        canvas.width = 400; canvas.height = 500;
        canvas.getContext('2d').drawImage(video, 0, 0, 400, 500);
        const data = canvas.toDataURL('image/jpeg', 0.86);

        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }

        setEditPhotoData(data);
        setEditRemovePhotoFlag(false);
        showEditPhotoPreview(data);

        document.getElementById('camera-controls').style.display = 'none';
        document.getElementById('recapture-controls').style.display = 'block';
    }

    function cancelCamera() {
        const div = document.getElementById('preview-box');
        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }

        setEditPhotoData('');
        const existingPhoto = <?php echo json_encode($member_has_photo ? $member_photo_path : ''); ?>;
        const removePhotoInput = document.getElementById('remove-photo-data');
        const shouldKeepRemoved = removePhotoInput && removePhotoInput.value === '1';
        if (existingPhoto && !shouldKeepRemoved) {
            showExistingEditPhoto(existingPhoto);
            document.getElementById('recapture-controls').style.display = 'block';
        } else {
            emptyEditPhotoPreview();
        }

        document.getElementById('camera-controls').style.display = 'none';
    }

</script>
<?php
render_form_draft_assets();
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('edit-member-form');
        if (!form || !window.AppFormDraft) return;

        window.AppFormDraft.bind(form, {
            key: <?php echo json_encode('edit-member-' . $safe_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            storage: 'session',
            afterRestore: restoreEditPhotoDraft
        });
    });
</script>
</body>
</html>

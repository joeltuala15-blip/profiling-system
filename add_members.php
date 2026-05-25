<?php
session_start();
include('db.php');
include_once('toast_helpers.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$requested_household_no = $_GET['household_no'] ?? ($_SESSION['current_household_no'] ?? '');
$requested_household_no = trim($requested_household_no);

if ($requested_household_no === '') {
    header("Location: add_household.php?error=session_expired");
    exit();
}

$safe_hh_no = mysqli_real_escape_string($conn, $requested_household_no);
$household_query = mysqli_query($conn, "SELECT household_no FROM households WHERE household_no = '$safe_hh_no' LIMIT 1");
$household = $household_query ? mysqli_fetch_assoc($household_query) : null;

if (!$household) {
    header("Location: residents.php?error=household_not_found");
    exit();
}

$hh_no = $household['household_no'];
$_SESSION['current_household_no'] = $hh_no;
$today = date('Y-m-d');
$previous_url = 'residents_list.php';
$page_toasts = [];
$success_toast = app_toast_from_success_code($_GET['success'] ?? '');
if ($success_toast) {
    $page_toasts[] = $success_toast;
}

$last_name_suggestions = [];
$suggestion_query = mysqli_query($conn, "SELECT DISTINCT last_name FROM residents WHERE household_no = '$safe_hh_no' AND COALESCE(is_archived, 0) = 0 AND last_name <> '' ORDER BY last_name ASC");
if ($suggestion_query) {
    while ($row = mysqli_fetch_assoc($suggestion_query)) {
        $last_name_suggestions[] = $row['last_name'];
    }
}

// Check if a Head of the Household already exists in the database for this household
$head_exists_query = mysqli_query($conn, "SELECT id FROM residents WHERE household_no = '$safe_hh_no' AND relationship = 'Head' AND COALESCE(is_archived, 0) = 0 LIMIT 1");
$head_already_exists_in_db = ($head_exists_query && mysqli_num_rows($head_exists_query) > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Profiling - Add Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        .btn-add-member, .btn-upload-small, .btn-prev, .btn-save, .btn-change, .preview-close {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            outline: none;
        }

        .btn-add-member:active, .btn-upload-small:active, .btn-prev:active, .btn-save:active, .btn-change:active {
            transform: translateY(1px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .btn-add-member {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 700;
            text-transform: none;
            font-size: 14px;
            color: #1e293b;
            white-space: nowrap;
        }

        #members-form {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #members-container {
            display: flex;
            flex-direction: column;
        }



        .member-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
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

        .btn-remove-member {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #475569;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .layout-grid { 
            display: grid; 
            grid-template-columns: 200px 1fr; 
            gap: 30px; 
        }

        .inputs-column {
            min-width: 0;
        }

        .photo-wrap { display: flex; flex-direction: column; gap: 12px; align-items: center; }
        
        .capture-box {
            width: 180px;
            height: 220px;
            position: relative;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #4b5563;
            overflow: hidden;
            
        }



        .capture-box video, .capture-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

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

        .photo-remove-btn:hover {
            background: #ef4444;
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
            text-align: center;
        }



        .input-grid { 
            display: grid; 
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px; 
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        
        .form-group label, .benefits-section h5 {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            text-transform: none;
            margin: 0;
        }

        .form-group input, .form-group select {
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

        .suggestion-wrap {
            position: relative;
            width: 100%;
        }

        .suggestion-list {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 30;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }

        .suggestion-list[hidden] {
            display: none;
        }

        .suggestion-option {
            width: 100%;
            padding: 10px 12px;
            border: 0;
            background: #fff;
            text-align: left;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
        }

        .suggestion-option:hover,
        .suggestion-option:focus {
            background: #eff6ff;
            outline: none;
        }

        .form-group input:focus, .form-group select:focus {
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
        
        .check-item { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            font-size: 12px; 
            font-weight: 800; 
            color: #374151; 
            text-transform: none;
            cursor: pointer;
            
        }



        .check-item input { 
            width: 18px; 
            height: 18px; 
            accent-color: var(--accent-blue);
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
        }



        .btn-save {
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
        }

        .btn-save:hover {
            background: var(--accent-blue-hover);
        }

        .btn-change {
            background: white;
            border: 1px solid #cbd5e1;
            padding: 12px 24px;
            border-radius: 12px;
            color: #334155;
            font-weight: 700;
            font-size: 14px;
        }

        body.modal-open {
            overflow: hidden;
        }

        .preview-modal {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.62);
        }

        .preview-modal.is-open {
            display: flex;
        }

        .preview-dialog {
            width: min(1080px, calc(100vw - 48px));
            max-height: min(88vh, 900px);
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            outline: none;
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-gray);
        }

        .preview-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        .preview-header p {
            margin: 4px 0 0;
            color: var(--text-gray);
            font-size: 13px;
            font-weight: 600;
        }

        .preview-close {
            width: 38px;
            height: 38px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #475569;
            font-size: 18px;
        }

        .preview-body {
            overflow-y: auto;
            padding: 22px 24px;
            background: #f8fafc;
        }

        .preview-member {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
        }

        .preview-member:last-child {
            margin-bottom: 0;
        }

        .preview-member-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .preview-photo {
            width: 72px;
            height: 88px;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            background: #f1f5f9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #64748b;
            flex-shrink: 0;
        }

        .preview-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-member-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        .preview-member-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--text-gray);
            font-weight: 600;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .preview-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            min-width: 0;
        }

        .preview-label {
            display: block;
            font-size: 11px;
            line-height: 1.2;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
        }

        .preview-value {
            display: block;
            margin-top: 5px;
            color: #111827;
            font-size: 14px;
            font-weight: 650;
            line-height: 1.35;
            word-break: break-word;
        }

        .preview-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 18px 24px;
            background: #ffffff;
            border-top: 1px solid var(--border-gray);
        }
        body.dark-mode .main-container { background-color: #0f172a; }
        body.dark-mode .content-panel { background: #0f172a; }
        body.dark-mode .section-header { border-bottom-color: #334155; }
        body.dark-mode h1 { color: white !important; }
        body.dark-mode .member-card { background: #1e293b; border-color: #334155; }
        body.dark-mode .capture-box { background: #0f172a; border-color: #334155; color: #94a3b8; }
        body.dark-mode input, body.dark-mode select { background: #0f172a !important; border-color: #334155 !important; color: white !important; }
        body.dark-mode .form-group label, body.dark-mode .benefits-section h5 { color: #cbd5e1; }
        body.dark-mode .check-item { color: #e2e8f0; }
        body.dark-mode .option-chip span { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        body.dark-mode .option-chip:hover span { background: #172033; border-color: #64748b; }
        body.dark-mode .option-chip input:checked + span { background: rgba(130, 78, 57, 0.28); border-color: var(--accent-blue); color: #f8fafc; }
        body.dark-mode .footer-nav { background: transparent; border-top-color: #334155; }
        body.dark-mode .btn-prev { background: #0f172a; color: white; border-color: #334155; }
        body.dark-mode .btn-add-member { background: #0f172a; color: white; border-color: #334155; }
        body.dark-mode .preview-dialog, body.dark-mode .preview-footer { background: #1e293b; }
        body.dark-mode .preview-header { border-bottom-color: #334155; }
        body.dark-mode .preview-header h2, body.dark-mode .preview-member-title { color: #f8fafc; }
        body.dark-mode .preview-body, body.dark-mode .preview-item, body.dark-mode .preview-photo { background: #0f172a; border-color: #334155; }
        body.dark-mode .preview-member { background: #1e293b; border-color: #334155; }
        body.dark-mode .preview-value { color: #e2e8f0; }
        body.dark-mode .preview-footer { border-top-color: #334155; }
        body.dark-mode .btn-change, body.dark-mode .preview-close { background: #0f172a; color: #f8fafc; border-color: #334155; }

        @media (max-width: 1100px) {
            .input-grid,
            .preview-grid {
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

            .btn-add-member {
                width: 100%;
            }

            .layout-grid,
            .input-grid,
            .preview-grid {
                grid-template-columns: 1fr;
            }

            .col-span-2 {
                grid-column: span 1;
            }

            .footer-nav,
            .preview-footer {
                flex-direction: column-reverse;
                padding: 18px;
            }

            .btn-prev,
            .btn-save,
            .btn-change {
                width: 100%;
                box-sizing: border-box;
            }

            .preview-modal {
                padding: 12px;
            }

            .preview-dialog {
                width: 100%;
                max-height: 92vh;
            }
        }

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
            <p style="margin:0; color: var(--text-gray);">Step 2: Add all individuals residing in Household #<?php echo htmlspecialchars($hh_no); ?></p>
        </div>
    </header>
    <div class="content-panel">
        <div class="content-body">
            <form id="members-form" action="process_members.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="household_no" value="<?php echo htmlspecialchars($hh_no); ?>">
                <div id="members-container">
                    <div class="member-card">
                        <div class="member-header">
                            <div class="member-title">Member Information - Entry #1</div>
                            <div class="member-actions" style="display: flex; align-items: center; gap: 12px;">
                                <button type="button" class="btn-add-member" onclick="addMember()">
                                    <i class="fa-solid fa-user-plus"></i> Add Another Member
                                </button>
                                <button type="button" class="btn-remove-member" onclick="removeMember(this)" aria-label="Remove member">&times;</button>
                            </div>
                        </div>
                        <div class="layout-grid">
                        <div class="photo-wrap">
                            <div class="capture-box" id="preview-0" onclick="startCamera(0)">
                                <i class="fa-solid fa-camera" style="font-size:32px; margin-bottom:10px; color: #64748b;"></i>
                                <span style="font-size:11px; font-weight:800;">Click to Capture</span>
                            </div>
                            <div id="camera-controls-0" style="display:none; width:180px; gap:8px;">
                                <button type="button" class="btn-capture" onclick="capturePhoto(0)" style="flex:1; background:#10b981; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-camera"></i> Capture</button>
                                <button type="button" class="btn-cancel-cam" onclick="cancelCamera(0)" style="background:#ef4444; color:white; border:none; padding:8px 14px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <div id="recapture-controls-0" style="display:none; width:180px;">
                                <button type="button" class="btn-recapture" onclick="startCamera(0)" style="width:100%; background:#f59e0b; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-rotate"></i> Recapture</button>
                            </div>
                            <label class="btn-upload-small" id="upload-label-0">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
                                <input type="file" accept="image/*" style="display:none" onchange="handleFileUpload(this, 0)">
                            </label>
                            <input type="hidden" name="members[0][photo]" id="photo-data-0">
                        </div>

                        <div class="inputs-column">
                            <div class="input-grid">
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <div class="suggestion-wrap">
                                        <input type="text" class="last-name-input" name="members[0][last_name]" autocomplete="off" required>
                                        <div class="suggestion-list" hidden></div>
                                    </div>
                                </div>
                                <div class="form-group"><label>First Name *</label><input type="text" name="members[0][first_name]" required></div>
                                <div class="form-group"><label>Middle Name</label><input type="text" name="members[0][middle_name]"></div>

                                <div class="form-group"><label>Suffix Name</label><input type="text" name="members[0][suffix_name]" placeholder="e.g. Jr., Sr., III"></div>
                                <div class="form-group"><label>Gender *</label>
                                    <select name="members[0][gender]" required>
                                        <option value="" selected disabled>Select gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Date of Birth *</label><input type="date" name="members[0][dob]" required onchange="calcAge(this, 0)" max="<?php echo $today; ?>"></div>

                                <div class="form-group"><label>Age</label><input type="number" name="members[0][age]" class="age-input" readonly tabindex="-1"></div>
                                <div class="form-group"><label>Relationship to Head *</label>
                                    <select name="members[0][relationship]" class="relationship-select" required onchange="enforceHeadRestriction()">
                                        <option value="" <?php echo $head_already_exists_in_db ? 'selected' : ''; ?> disabled>Select relationship</option>
                                        <?php foreach (['Head' => 'Head of the Household', 'Spouse' => 'Spouse', 'Son/Daughter' => 'Son/Daughter', 'Parent' => 'Parent', 'Sibling' => 'Sibling', 'Grandchild' => 'Grandchild', 'Other' => 'Other'] as $rel_value => $rel_label): ?>
                                            <option value="<?php echo htmlspecialchars($rel_value); ?>" <?php echo (!$head_already_exists_in_db && $rel_value === 'Head') ? 'selected' : ''; ?> <?php echo ($rel_value === 'Head' && $head_already_exists_in_db) ? 'disabled' : ''; ?>><?php echo htmlspecialchars($rel_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label>Civil Status *</label>
                                    <select name="members[0][civil_status]" required>
                                        <option value="" selected disabled>Select civil status</option>
                                        <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $status_option): ?>
                                            <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group"><label>Employment Status *</label>
                                    <select name="members[0][employment]" required>
                                        <option value="" selected disabled>Select employment status</option>
                                        <option value="Employed">Employed</option>
                                        <option value="Unemployed">Unemployed</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Educational Attainment *</label>
                                    <select name="members[0][education]" required>
                                        <option value="" selected disabled>Select educational attainment</option>
                                        <option value="None / Child">None / Child</option>
                                        <option value="Elementary">Elementary</option>
                                        <option value="High School">High School</option>
                                        <option value="Vocational">Vocational</option>
                                        <option value="College">College</option>
                                        <option value="Post-Graduate">Post-Graduate</option>
                                    </select>
                                </div>
                            </div>

                            <div class="benefits-section">
                                <h5>Special Classifications</h5>
                                <div class="benefits-grid">
                                    <label class="option-chip voter-chip">
                                        <input type="checkbox" name="members[0][voter]" class="voter-check" value="1">
                                        <span>Registered Voter</span>
                                    </label>
                                    <label class="option-chip">
                                        <input type="checkbox" name="members[0][pwd]" value="1">
                                        <span>PWD</span>
                                    </label>
                                    <label class="option-chip">
                                        <input type="checkbox" name="members[0][solo_parent]" value="1">
                                        <span>Solo Parent</span>
                                    </label>
                                    <label class="option-chip">
                                        <input type="checkbox" name="members[0][senior]" class="senior-check" value="1">
                                        <span>Senior Citizen</span>
                                    </label>
                                    <label class="option-chip">
                                        <input type="checkbox" name="members[0][minor]" class="minor-check" value="1">
                                        <span>Minor</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                <div class="footer-nav">
                    <a href="<?php echo htmlspecialchars($previous_url); ?>" class="btn-prev">
                        <i class="fa-solid fa-arrow-left"></i> Previous
                    </a>
                    <button type="button" class="btn-save" onclick="openPreviewModal()">
                        <i class="fa-solid fa-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>

<div class="preview-modal" id="previewModal" aria-hidden="true">
    <div class="preview-dialog" role="dialog" aria-modal="true" aria-labelledby="previewModalTitle" tabindex="-1">
        <div class="preview-header">
            <div>
                <h2 id="previewModalTitle">Preview Resident Records</h2>
                <p>Household #<?php echo htmlspecialchars($hh_no); ?></p>
            </div>
            <button type="button" class="preview-close" onclick="closePreviewModal()" aria-label="Close preview">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="preview-body" id="previewContent"></div>
        <div class="preview-footer">
            <button type="button" class="btn-change" onclick="closePreviewModal()">
                <i class="fa-solid fa-pen-to-square"></i> Change
            </button>
            <button type="submit" class="btn-save" form="members-form">
                <i class="fa-solid fa-floppy-disk"></i> Save All Records
            </button>
        </div>
    </div>
</div>

<script>
    let mIdx = document.querySelectorAll('.member-card').length;
    const lastNameSuggestions = <?php echo json_encode($last_name_suggestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
    const headExistsInDb = <?php echo $head_already_exists_in_db ? 'true' : 'false'; ?>;
    const membersForm = document.getElementById('members-form');
    const previewModal = document.getElementById('previewModal');
    const previewDialog = previewModal ? previewModal.querySelector('.preview-dialog') : null;
    const previewContent = document.getElementById('previewContent');
    const maxDob = '<?php echo $today; ?>';
    const photoCanvasWidth = 400;
    const photoCanvasHeight = 500;

    function addMember() {
        const container = document.getElementById('members-container');
        const isFirstMember = container.querySelectorAll('.member-card').length === 0;
        const card = document.createElement('div');
        card.className = 'member-card';
        card.innerHTML = `
            <div class="member-header">
                <div class="member-title">Member Information - Entry #${mIdx + 1}</div>
                <div class="member-actions" style="display: flex; align-items: center; gap: 12px;">
                    <button type="button" class="btn-remove-member" onclick="removeMember(this)" aria-label="Remove member">&times;</button>
                </div>
            </div>
            <div class="layout-grid">
                <div class="photo-wrap">
                    <div class="capture-box" id="preview-${mIdx}" onclick="startCamera(${mIdx})">
                        <i class="fa-solid fa-camera" style="font-size:32px; margin-bottom:10px; color: #64748b;"></i>
                        <span style="font-size:11px; font-weight:800;">Click to Capture</span>
                    </div>
                    <div id="camera-controls-${mIdx}" style="display:none; width:180px; gap:8px;">
                        <button type="button" class="btn-capture" onclick="capturePhoto(${mIdx})" style="flex:1; background:#10b981; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-camera"></i> Capture</button>
                        <button type="button" class="btn-cancel-cam" onclick="cancelCamera(${mIdx})" style="background:#ef4444; color:white; border:none; padding:8px 14px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div id="recapture-controls-${mIdx}" style="display:none; width:180px;">
                        <button type="button" class="btn-recapture" onclick="startCamera(${mIdx})" style="width:100%; background:#f59e0b; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fa-solid fa-rotate"></i> Recapture</button>
                    </div>
                    <label class="btn-upload-small" id="upload-label-${mIdx}">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
                        <input type="file" accept="image/*" style="display:none" onchange="handleFileUpload(this, ${mIdx})">
                    </label>
                    <input type="hidden" name="members[${mIdx}][photo]" id="photo-data-${mIdx}">
                </div>

                <div class="inputs-column">
                    <div class="input-grid">
                        <div class="form-group">
                            <label>Last Name *</label>
                            <div class="suggestion-wrap">
                                <input type="text" class="last-name-input" name="members[${mIdx}][last_name]" autocomplete="off" required>
                                <div class="suggestion-list" hidden></div>
                            </div>
                        </div>
                        <div class="form-group"><label>First Name *</label><input type="text" name="members[${mIdx}][first_name]" required></div>
                        <div class="form-group"><label>Middle Name</label><input type="text" name="members[${mIdx}][middle_name]"></div>
                        
                        <div class="form-group"><label>Suffix Name</label><input type="text" name="members[${mIdx}][suffix_name]" placeholder="e.g. Jr., Sr., III"></div>
                        <div class="form-group"><label>Gender *</label>
                            <select name="members[${mIdx}][gender]" required>
                                <option value="" selected disabled>Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Date of Birth *</label><input type="date" name="members[${mIdx}][dob]" required onchange="calcAge(this, ${mIdx})" max="${maxDob}"></div>
                        
                        <div class="form-group"><label>Age</label><input type="number" name="members[${mIdx}][age]" class="age-input" readonly tabindex="-1"></div>
                        <div class="form-group"><label>Relationship to Head *</label>
                            <select name="members[${mIdx}][relationship]" class="relationship-select" required onchange="enforceHeadRestriction()">
                                <option value="" selected disabled>Select relationship</option>
                                <option value="Head">Head of the Household</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Son/Daughter">Son/Daughter</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Grandchild">Grandchild</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Civil Status *</label>
                            <select name="members[${mIdx}][civil_status]" required>
                                <option value="" selected disabled>Select civil status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>

                        <div class="form-group"><label>Employment Status *</label>
                            <select name="members[${mIdx}][employment]" required>
                                <option value="" selected disabled>Select employment status</option>
                                <option value="Employed">Employed</option>
                                <option value="Unemployed">Unemployed</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Educational Attainment *</label>
                            <select name="members[${mIdx}][education]" required>
                                <option value="" selected disabled>Select educational attainment</option>
                                <option value="None / Child">None / Child</option>
                                <option value="Elementary">Elementary</option>
                                <option value="High School">High School</option>
                                <option value="Vocational">Vocational</option>
                                <option value="College">College</option>
                                <option value="Post-Graduate">Post-Graduate</option>
                            </select>
                        </div>
                    </div>

                    <div class="benefits-section">
                        <h5>Special Classifications</h5>
                        <div class="benefits-grid">
                            <label class="option-chip voter-chip">
                                <input type="checkbox" name="members[${mIdx}][voter]" class="voter-check" value="1">
                                <span>Registered Voter</span>
                            </label>
                            <label class="option-chip">
                                <input type="checkbox" name="members[${mIdx}][pwd]" value="1">
                                <span>PWD</span>
                            </label>
                            <label class="option-chip">
                                <input type="checkbox" name="members[${mIdx}][solo_parent]" value="1">
                                <span>Solo Parent</span>
                            </label>
                            <label class="option-chip">
                                <input type="checkbox" name="members[${mIdx}][senior]" class="senior-check" value="1">
                                <span>Senior Citizen</span>
                            </label>
                            <label class="option-chip">
                                <input type="checkbox" name="members[${mIdx}][minor]" class="minor-check" value="1">
                                <span>Minor</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);
        setupLastNameSuggestions(card);
        setupClassificationGuards(card);
        updateEntryTitles();
        enforceHeadRestriction();
        mIdx++;
    }

    function removeMember(button) {
        const card = button.closest('.member-card');
        if (!card) return;
        card.remove();
        if (!document.querySelector('.member-card')) {
            addMember();
            return;
        }
        updateEntryTitles();
        enforceHeadRestriction();
    }

    function updateEntryTitles() {
        const cards = document.querySelectorAll('.member-card');
        const shouldShowRemove = cards.length > 1;
        cards.forEach((card, index) => {
            const title = card.querySelector('.member-title');
            if (title) {
                title.textContent = `Member Information - Entry #${index + 1}`;
            }

            const removeButton = card.querySelector('.btn-remove-member');
            if (removeButton) {
                removeButton.style.display = shouldShowRemove ? 'inline-flex' : 'none';
                removeButton.disabled = !shouldShowRemove;
            }

            let actionsContainer = card.querySelector('.member-actions');
            if (!actionsContainer) {
                const header = card.querySelector('.member-header');
                actionsContainer = document.createElement('div');
                actionsContainer.className = 'member-actions';
                actionsContainer.style.cssText = 'display: flex; align-items: center; gap: 12px;';
                if (removeButton) {
                    header.insertBefore(actionsContainer, removeButton);
                    actionsContainer.appendChild(removeButton);
                } else {
                    header.appendChild(actionsContainer);
                }
            }

            let addBtn = actionsContainer.querySelector('.btn-add-member');
            if (index === 0) {
                if (!addBtn) {
                    addBtn = document.createElement('button');
                    addBtn.type = 'button';
                    addBtn.className = 'btn-add-member';
                    addBtn.onclick = addMember;
                    addBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Another Member';
                    actionsContainer.insertBefore(addBtn, actionsContainer.firstChild);
                }
            } else {
                if (addBtn) {
                    addBtn.remove();
                }
            }
        });
    }

    function escapeHtml(value) {
        const htmlMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return String(value ?? '').replace(/[&<>"']/g, (char) => htmlMap[char]);
    }

    function getFieldValue(card, selector, fallback = 'Not provided') {
        const field = card.querySelector(selector);
        const value = field ? field.value.trim() : '';
        return value || fallback;
    }

    function getCheckedValue(card, fieldName, fallback = 'Not provided') {
        const checked = card.querySelector(`input[name$="[${fieldName}]"]:checked`);
        return checked ? checked.value : fallback;
    }

    function formatDateForPreview(value) {
        if (!value) return 'Not provided';

        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function buildPreviewMemberHtml(card, index) {
        const lastName = getFieldValue(card, 'input[name$="[last_name]"]', '');
        const firstName = getFieldValue(card, 'input[name$="[first_name]"]', '');
        const middleName = getFieldValue(card, 'input[name$="[middle_name]"]', '');
        const suffixName = getFieldValue(card, 'input[name$="[suffix_name]"]', '');
        const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
        const displayName = [fullName, suffixName].filter(Boolean).join(' ') || `Member ${index + 1}`;
        const photoValue = getFieldValue(card, 'input[name$="[photo]"]', '');
        const safePhoto = photoValue.startsWith('data:image/') ? photoValue : '';
        const classifications = [
            ['voter', 'Registered Voter'],
            ['pwd', 'PWD'],
            ['solo_parent', 'Solo Parent'],
            ['senior', 'Senior Citizen'],
            ['minor', 'Minor']
        ].filter(([fieldName]) => {
            const input = card.querySelector(`input[name$="[${fieldName}]"]`);
            return input && input.checked;
        }).map(([, label]) => label);

        const details = [
            ['Last Name', lastName || 'Not provided'],
            ['First Name', firstName || 'Not provided'],
            ['Middle Name', middleName || 'Not provided'],
            ['Suffix Name', suffixName || 'Not provided'],
            ['Gender', getFieldValue(card, 'select[name$="[gender]"]')],
            ['Date of Birth', formatDateForPreview(getFieldValue(card, 'input[name$="[dob]"]', ''))],
            ['Age', getFieldValue(card, 'input[name$="[age]"]')],
            ['Relationship to Head', getFieldValue(card, 'select[name$="[relationship]"]')],
            ['Civil Status', getFieldValue(card, 'select[name$="[civil_status]"]')],
            ['Employment Status', getFieldValue(card, 'select[name$="[employment]"]')],
            ['Educational Attainment', getFieldValue(card, 'select[name$="[education]"]')],
            ['Special Classifications', classifications.length ? classifications.join(', ') : 'None']
        ];

        const detailsHtml = details.map(([label, value]) => `
            <div class="preview-item">
                <span class="preview-label">${escapeHtml(label)}</span>
                <span class="preview-value">${escapeHtml(value)}</span>
            </div>
        `).join('');

        return `
            <section class="preview-member">
                <div class="preview-member-header">
                    <div class="preview-photo">
                        ${safePhoto ? `<img src="${safePhoto}" alt="${escapeHtml(displayName)} photo">` : '<i class="fa-solid fa-user"></i>'}
                    </div>
                    <div>
                        <h3 class="preview-member-title">${escapeHtml(displayName)}</h3>
                        <p class="preview-member-subtitle">Entry #${index + 1}</p>
                    </div>
                </div>
                <div class="preview-grid">${detailsHtml}</div>
            </section>
        `;
    }

    function openPreviewModal() {
        if (!membersForm || !previewModal || !previewContent) return;

        let cards = Array.from(document.querySelectorAll('.member-card'));
        if (cards.length === 0) {
            addMember();
            cards = Array.from(document.querySelectorAll('.member-card'));
        }

        cards.forEach((card) => {
            const dobInput = card.querySelector('input[name$="[dob]"]');
            if (dobInput && dobInput.value) {
                calcAge(dobInput);
            }
        });

        if (!membersForm.checkValidity()) {
            membersForm.reportValidity();
            return;
        }

        stopAllCameras();
        previewContent.innerHTML = cards.map((card, index) => buildPreviewMemberHtml(card, index)).join('');
        previewModal.classList.add('is-open');
        previewModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        if (previewDialog) {
            previewDialog.focus();
        }
    }

    function closePreviewModal() {
        if (!previewModal) return;

        previewModal.classList.remove('is-open');
        previewModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function setupClassificationGuards(card) {
        const voterCheck = card.querySelector('.voter-check');
        const voterChip = card.querySelector('.voter-chip');
        const seniorCheck = card.querySelector('.senior-check');
        const minorCheck = card.querySelector('.minor-check');

        card.dataset.isSenior = '0';
        card.dataset.isMinor = '0';

        const blockToggle = (event, message) => {
            event.preventDefault();
            showAppToast(message, 'error', 'Action Needed');
        };

        const handleVoterAttempt = (event) => {
            if (card.dataset.isMinor === '1') {
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
                if (card.dataset.isSenior === '1') {
                    blockToggle(event, 'Senior status is automatic based on age.');
                } else {
                    blockToggle(event, 'Senior status applies only at age 60+.');
                }
            });
        }

        if (minorCheck) {
            minorCheck.addEventListener('click', (event) => {
                if (card.dataset.isMinor === '1') {
                    blockToggle(event, 'Minor status is automatic based on age.');
                } else {
                    blockToggle(event, 'Minor status applies only below 18.');
                }
            });
        }
    }

    function setupLastNameSuggestions(card) {
        const input = card.querySelector('.last-name-input');
        const list = card.querySelector('.suggestion-list');
        if (!input || !list || lastNameSuggestions.length === 0) return;

        const showSuggestions = () => {
            const term = input.value.trim().toLowerCase();
            const matches = lastNameSuggestions.filter((name) => name.toLowerCase().includes(term));

            list.innerHTML = '';
            matches.forEach((name) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'suggestion-option';
                option.textContent = name;
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = name;
                    list.hidden = true;
                });
                list.appendChild(option);
            });

            list.hidden = matches.length === 0;
        };

        input.addEventListener('focus', showSuggestions);
        input.addEventListener('click', showSuggestions);
        input.addEventListener('input', showSuggestions);
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.suggestion-wrap')) {
                list.hidden = true;
            }
        });
    }

    function dispatchPhotoDraftChange(input) {
        if (!input) return;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setPhotoData(index, value) {
        const input = document.getElementById(`photo-data-${index}`);
        if (!input) return;
        input.value = value || '';
        dispatchPhotoDraftChange(input);
    }

    function showPhotoPreview(index, dataUrl) {
        if (!dataUrl || !dataUrl.startsWith('data:image/')) return;

        const previewBox = document.getElementById(`preview-${index}`);
        const cameraControls = document.getElementById(`camera-controls-${index}`);
        const recaptureControls = document.getElementById(`recapture-controls-${index}`);
        const uploadLabel = document.getElementById(`upload-label-${index}`);
        if (!previewBox) return;

        previewBox.innerHTML = `
            <img src="${dataUrl}" alt="Resident photo">
            <button type="button" class="photo-remove-btn" onclick="removePhoto(event, ${index})" aria-label="Remove profile picture">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewBox.onclick = null;
        if (cameraControls) cameraControls.style.display = 'none';
        if (recaptureControls) recaptureControls.style.display = 'block';
        if (uploadLabel) uploadLabel.style.display = 'none';
    }

    function removePhoto(event, index) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (cameraStreams[index]) {
            cameraStreams[index].getTracks().forEach((track) => track.stop());
            delete cameraStreams[index];
        }

        setPhotoData(index, '');
        resetPhotoPreview(index);

        const fileInput = document.querySelector(`#upload-label-${index} input[type="file"]`);
        if (fileInput) {
            fileInput.value = '';
        }
    }

    function resetPhotoPreview(index) {
        const previewBox = document.getElementById(`preview-${index}`);
        if (!previewBox) return;

        previewBox.innerHTML = `
            <i class="fa-solid fa-camera" style="font-size:32px; margin-bottom:10px; color: #64748b;"></i>
            <span style="font-size:11px; font-weight:800;">Click to Capture</span>
        `;
        previewBox.onclick = () => startCamera(index);

        const cameraControls = document.getElementById(`camera-controls-${index}`);
        const recaptureControls = document.getElementById(`recapture-controls-${index}`);
        const uploadLabel = document.getElementById(`upload-label-${index}`);
        if (cameraControls) cameraControls.style.display = 'none';
        if (recaptureControls) recaptureControls.style.display = 'none';
        if (uploadLabel) uploadLabel.style.display = 'flex';
    }

    function restoreMemberPhotoDrafts() {
        document.querySelectorAll('.member-card').forEach((card) => {
            const photoInput = card.querySelector('input[name$="[photo]"]');
            const match = photoInput ? photoInput.name.match(/^members\[(\d+)\]\[photo\]$/) : null;
            if (match && photoInput.value && photoInput.value.startsWith('data:image/')) {
                showPhotoPreview(match[1], photoInput.value);
            }
        });
    }

    function imageFileToResidentPhoto(file, callback) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = photoCanvasWidth;
                canvas.height = photoCanvasHeight;

                const ctx = canvas.getContext('2d');
                const targetRatio = photoCanvasWidth / photoCanvasHeight;
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

                ctx.drawImage(img, sx, sy, sw, sh, 0, 0, photoCanvasWidth, photoCanvasHeight);
                callback(canvas.toDataURL('image/jpeg', 0.86));
            };
            img.onerror = function() {
                callback(event.target.result);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }

    function handleFileUpload(input, index) {
        if (input.files && input.files[0]) {
            imageFileToResidentPhoto(input.files[0], function(dataUrl) {
                setPhotoData(index, dataUrl);
                showPhotoPreview(index, dataUrl);
            });
        }
    }

    let cameraStreams = {};

    function stopAllCameras() {
        Object.keys(cameraStreams).forEach((key) => {
            cameraStreams[key].getTracks().forEach((track) => track.stop());
            delete cameraStreams[key];
        });
    }

    function calcAge(input, index) {
        const card = input.closest('.member-card');
        const ageInp = card.querySelector('.age-input');
        const seniorCheck = card.querySelector('.senior-check');
        const minorCheck = card.querySelector('.minor-check');
        const voterCheck = card.querySelector('.voter-check');
        
        const birth = new Date(input.value);
        const now = new Date();
        let age = now.getFullYear() - birth.getFullYear();
        if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) age--;
        
        age = age >= 0 ? age : 0;
        ageInp.value = age;

        const isSenior = age >= 60;
        const isMinor = age < 18;

        card.dataset.isSenior = isSenior ? '1' : '0';
        card.dataset.isMinor = isMinor ? '1' : '0';

        if (seniorCheck) {
            seniorCheck.checked = isSenior;
        }
        if (minorCheck) {
            minorCheck.checked = isMinor;
        }

        if (voterCheck) {
            voterCheck.disabled = isMinor;
            if (isMinor) {
                voterCheck.checked = false;
            }
        }
    }

    function startCamera(index) {
        const div = document.getElementById(`preview-${index}`);
        const camControls = document.getElementById(`camera-controls-${index}`);
        const recapControls = document.getElementById(`recapture-controls-${index}`);
        const uploadLabel = document.getElementById(`upload-label-${index}`);

        div.onclick = null;

        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        div.innerHTML = ''; 
        div.appendChild(video);
        
        navigator.mediaDevices.getUserMedia({ video: true }).then(s => {
            video.srcObject = s; 
            video.play();
            cameraStreams[index] = s;
            
            camControls.style.display = 'flex';
            recapControls.style.display = 'none';
            uploadLabel.style.display = 'none';
        }).catch(err => {
            showAppToast('Camera access denied.', 'error', 'Camera Unavailable');
            div.innerHTML = `<i class="fa-solid fa-camera-slash" style="font-size:32px;"></i>`;
            div.onclick = () => startCamera(index);
        });
    }

    function capturePhoto(index) {
        const div = document.getElementById(`preview-${index}`);
        const video = div.querySelector('video');
        if (!video) return;

        const canvas = document.createElement('canvas');
        canvas.width = 400; canvas.height = 500;
        canvas.getContext('2d').drawImage(video, 0, 0, 400, 500);
        const data = canvas.toDataURL('image/jpeg', 0.86);

        if (cameraStreams[index]) {
            cameraStreams[index].getTracks().forEach(t => t.stop());
            delete cameraStreams[index];
        }

        setPhotoData(index, data);
        showPhotoPreview(index, data);

        document.getElementById(`camera-controls-${index}`).style.display = 'none';
        document.getElementById(`recapture-controls-${index}`).style.display = 'block';
        document.getElementById(`upload-label-${index}`).style.display = 'none';
    }

    function cancelCamera(index) {
        const div = document.getElementById(`preview-${index}`);
        if (cameraStreams[index]) {
            cameraStreams[index].getTracks().forEach(t => t.stop());
            delete cameraStreams[index];
        }

        setPhotoData(index, '');
        resetPhotoPreview(index);
    }

    if (previewModal) {
        previewModal.addEventListener('click', (event) => {
            if (event.target === previewModal) {
                closePreviewModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && previewModal && previewModal.classList.contains('is-open')) {
            closePreviewModal();
        }
    });

    if (membersForm) {
        membersForm.addEventListener('submit', (event) => {
            if (!previewModal || !previewModal.classList.contains('is-open')) {
                event.preventDefault();
                openPreviewModal();
                return;
            }

            stopAllCameras();
        });
    }

    function enforceHeadRestriction() {
        const allSelects = document.querySelectorAll('.relationship-select');
        let headSelectedInForm = false;
        let headSelectElement = null;

        // First pass: find if any select in the form currently has "Head" selected
        allSelects.forEach((sel) => {
            if (sel.value === 'Head') {
                headSelectedInForm = true;
                headSelectElement = sel;
            }
        });

        // Head is blocked if it exists in the database OR if another card in this form has it
        const headTaken = headExistsInDb || headSelectedInForm;

        // Second pass: disable/enable "Head" option on all selects
        allSelects.forEach((sel) => {
            const headOption = sel.querySelector('option[value="Head"]');
            if (!headOption) return;

            if (headExistsInDb) {
                // DB already has a head — disable on ALL cards
                headOption.disabled = true;
            } else if (headSelectedInForm && sel !== headSelectElement) {
                // Another card in this form has Head — disable on other cards
                headOption.disabled = true;
            } else {
                headOption.disabled = false;
            }
        });
    }

    function initializeMembersPage() {
        const cards = Array.from(document.querySelectorAll('.member-card'));

        if (cards.length === 0) {
            addMember();
            return;
        }

        cards.forEach((card) => {
            if (card.dataset.enhanced === '1') return;
            setupLastNameSuggestions(card);
            setupClassificationGuards(card);
            card.dataset.enhanced = '1';
        });

        updateEntryTitles();
        enforceHeadRestriction();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMembersPage);
    } else {
        initializeMembersPage();
    }
</script>
<?php render_form_draft_assets(); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('members-form');
        if (!form || !window.AppFormDraft) return;

        <?php if (($_GET['success'] ?? '') === 'household_added'): ?>
        window.AppFormDraft.clear('add-household');
        <?php endif; ?>

        window.AppFormDraft.bind(form, {
            key: <?php echo json_encode('add-members-' . $hh_no, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            storage: 'session',
            beforeRestore: function(currentForm, draft) {
                const fieldNames = Object.keys((draft && draft.fields) || {});
                const maxIndex = fieldNames.reduce(function(max, name) {
                    const match = name.match(/^members\[(\d+)\]/);
                    return match ? Math.max(max, parseInt(match[1], 10)) : max;
                }, 0);

                while (document.querySelectorAll('.member-card').length <= maxIndex) {
                    addMember();
                }
                initializeMembersPage();
            },
            afterRestore: function() {
                initializeMembersPage();
                document.querySelectorAll('.member-card').forEach(function(card) {
                    const dobInput = card.querySelector('input[name$="[dob]"]');
                    if (dobInput && dobInput.value) {
                        calcAge(dobInput);
                    }
                });
                restoreMemberPhotoDrafts();
            }
        });
    });
</script>
</body>
</html>

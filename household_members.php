<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

// Security check: Only Secretary role
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$household_no = $_GET['household_no'] ?? ($_SESSION['current_household_no'] ?? '');
$household_no = trim($household_no);

if ($household_no === '') {
    header("Location: residents.php?error=household_not_found");
    exit();
}

$safe_hh_no = mysqli_real_escape_string($conn, $household_no);

$query_h = "SELECT * FROM households WHERE household_no = '$safe_hh_no'";
$result_h = mysqli_query($conn, $query_h);
$household = mysqli_fetch_assoc($result_h);

if (!$household) {
    header("Location: residents.php?error=household_not_found");
    exit();
}

$_SESSION['current_household_no'] = $household_no;
$page_toasts = [];
$success_toast = app_toast_from_success_code($_GET['success'] ?? '');
if ($success_toast) {
    $page_toasts[] = $success_toast;
}

$query_m = "SELECT * FROM residents WHERE household_no = '$safe_hh_no' AND COALESCE(is_archived, 0) = 0 ORDER BY id ASC";
$result_m = mysqli_query($conn, $query_m);

$act_query = mysqli_query($conn, "SELECT ap.resident_id, a.activity_name FROM activity_participants ap JOIN activities a ON ap.activity_id = a.id");
$resident_activities = [];
if ($act_query) {
    while ($row_act = mysqli_fetch_assoc($act_query)) {
        if (!empty($row_act['resident_id'])) {
            $resident_activities[$row_act['resident_id']][] = $row_act['activity_name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Details | Profiling System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --sidebar-navy: #1e293b; 
            --accent-blue: #2563eb; 
            --logo-orange: #ff9800;
            --text-gray: #64748b; 
            --border-color: #e2e8f0;
            --main-bg: #f1f5f9;
            --danger-red: #ef4444;
            --table-font-size: 13px;
            --primary-text: #1e293b;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: var(--main-bg); overflow: hidden; }

        .sidebar { width: 280px; background: var(--sidebar-navy); color: white; display: flex; flex-direction: column; position: relative; flex-shrink: 0; transition: width 0.3s ease; overflow: hidden; }
        .sidebar.collapsed { width: 80px; }
        
        
        .sidebar-header { padding: 15px 15px; display: flex; align-items: center; position: relative; height: 70px; justify-content: flex-start; }
        .brand-group { display: flex; align-items: center;  }
        .brand-logo-container { border: 3px solid var(--logo-orange); border-radius: 14px; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .brand-logo-container i { color: var(--logo-orange); font-size: 30px; }
        .brand-text { margin-left: 15px; white-space: nowrap; }
        .brand-text b { display: block; font-size: 20px; line-height: 1.1; color: white; }
        .brand-text span { color: #94a3b8; font-size: 14px; }
        .toggle-icon { cursor: pointer; color: #64748b; font-size: 28px;  margin-left: auto; }

        .sidebar.collapsed .brand-group { display: none; }
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar.collapsed .toggle-icon { margin-left: 0; color: white; }

        .nav-menu { padding: 10px 12px; flex-grow: 1; }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; color: #cbd5e1; text-decoration: none; border-radius: 12px; margin-bottom: 4px; }
        .nav-item.active { background: var(--accent-blue); color: white; }
        .nav-item i { font-size: 15px; min-width: 28px; text-align: center; }
        .nav-text { font-size: 16px; font-weight: 600; margin-left: 15px; }
        
        /* Centering icons when collapsed */
        .sidebar.collapsed .nav-item { justify-content: center; padding: 18px 0; }
        .sidebar.collapsed .nav-item i { min-width: unset; }
        .sidebar.collapsed .nav-text { display: none; }

        /* --- MAIN LAYOUT --- */
        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .top-header { background: #ffffff; padding: 20px 40px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        
        .user-pill { display: flex; align-items: center; background: #f8fafc; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; }
        .avatar { background: var(--accent-blue); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        
        .logout-dropdown { position: absolute; top: 110%; right: 40px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; width: 220px;  display: none; z-index: 100; }
        .logout-dropdown.show { display: block; }

        .content-body { padding: 16px 20px 20px; }
        .hh-card, .panel { background: white; border: 1px solid #e5e7eb; padding: 18px; border-radius: 20px; border: 1px solid var(--border-color); margin-bottom: 25px; }
        
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; }
        .info-item label { display: block; font-size: 11px; font-weight: 800; color: var(--text-gray); text- margin-bottom: 6px; }
        .info-item span { font-size: 15px; color: #1e293b; font-weight: 600; }

        /* --- DATA TABLE --- */
        .table-wrapper { overflow-x: scroll; overflow-y: hidden; border: 1px solid var(--border-color); border-radius: 12px; margin-top: 20px; scrollbar-gutter: stable; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: left; padding: 16px; color: var(--text-gray); font-size: 11px; text- background: #f8fafc; border-bottom: 2px solid var(--border-color); }
        
        td { padding: 16px; border-bottom: 1px solid #e5e7eb; font-size: var(--table-font-size); color: var(--primary-text); vertical-align: middle; }

        .res-photo,
        .res-photo-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            flex: 0 0 45px;
        }

        .res-photo {
            object-fit: cover;
            cursor: pointer;
        }

        .res-photo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 18px;
        }

        .member-row { cursor: default; }
        .details-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .details-item { background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 10px 12px; min-width: 0; }
        .details-item label { display: block; font-size: 10px; font-weight: 800; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.4px; }
        .details-item span { display: block; margin-top: 6px; font-size: 13px; font-weight: 600; color: var(--primary-text); word-break: break-word; }
        .details-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
        .details-badge { background: #eef2ff; color: #3730a3; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .details-muted { color: #cbd5e1; font-size: 12px; font-weight: 600; }

        .details-modal {
            position: fixed;
            inset: 0;
            z-index: 1500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.6);
        }

        .details-modal.is-open { display: flex; }

        .details-dialog {
            width: min(900px, calc(100vw - 48px));
            max-height: 86vh;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            outline: none;
        }

        .details-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .details-header h3 { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
        .details-header p { margin: 4px 0 0; color: var(--text-gray); font-size: 12px; font-weight: 600; }

        .details-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: #475569;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .details-body {
            padding: 20px 24px;
            background: #f8fafc;
            overflow-y: auto;
        }

        body.modal-open { overflow: hidden; }

        .table-wrapper::-webkit-scrollbar { height: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .table-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 999px; }

        .action-btns { display: flex; gap: 10px; }
        .action-btn { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; border-radius: 10px; text-decoration: none; border: 1px solid var(--border-color); cursor: pointer;  }
        .btn-edit { color: var(--accent-blue); background-color: #eff6ff; }
        .btn-view { color: #10b981; background-color: #ecfdf5; border-color: #a7f3d0; }

        .btn-archive { color: var(--danger-red); background-color: #fef2f2; border-color: #fecaca; }
        .btn-archive-text { width: auto; padding: 0 12px; gap: 7px; font-weight: 700; font-size: 12px; font-family: inherit; }
        .status-pill { display: inline-flex; padding: 6px 12px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 800; }
        .status-muted { background: #fef3c7; color: #92400e; }

        .btn-add { background: var(--accent-blue); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary { background: #f1f5f9; color: #1e293b; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--border-color); }

        .lightbox-modal { display: none; position: fixed; z-index: 2000; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); justify-content: center; align-items: center; }
        .lightbox-content { max-width: 90%; max-height: 90%; border-radius: 16px; }

        .class-container { display: flex; flex-direction: column; justify-content: flex-start; }
        .class-text { 
            display: block; 
            font-size: var(--table-font-size); 
            font-weight: 500; 
            color: var(--primary-text); 
            line-height: 1.5;
        }

        .activity-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        /* Dark Mode Overrides */
        body.dark-mode .hh-card, body.dark-mode .panel { background: #1e293b; border-color: #334155; }
        body.dark-mode .info-item span { color: white; }
        body.dark-mode .info-item label { color: #94a3b8; }
        body.dark-mode .table-wrapper { border-color: #334155; }
        body.dark-mode th { background: #0f172a; border-bottom-color: #334155; color: #94a3b8; }
        body.dark-mode td { border-bottom-color: #334155; color: #e2e8f0; }
        body.dark-mode .class-text { color: #e2e8f0; }
        body.dark-mode .activity-badge { background: #03294f; color: #38bdf8; }
        body.dark-mode .activity-badge { background: #03294f; color: #38bdf8; }
        body.dark-mode .details-item { background: #1e293b; border-color: #334155; }
        body.dark-mode .details-item span { color: #e2e8f0; }
        body.dark-mode .details-badge { background: #1e1b4b; color: #c7d2fe; }
        body.dark-mode .details-muted { color: #94a3b8; }
        body.dark-mode .table-wrapper::-webkit-scrollbar-thumb { background: #475569; }
        body.dark-mode .table-wrapper::-webkit-scrollbar-track { background: #0f172a; }
        body.dark-mode .details-dialog { background: #1e293b; }
        body.dark-mode .details-header { border-bottom-color: #334155; }
        body.dark-mode .details-header h3 { color: #f8fafc; }
        body.dark-mode .details-body { background: #0f172a; }
        body.dark-mode .details-close { background: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .btn-secondary { background: #0f172a; color: white; border-color: #334155; }
        body.dark-mode h2, body.dark-mode h3 { color: white !important; }
        body.dark-mode p { color: #94a3b8 !important; }

        /* --- SUCCESS MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 24px; width: 400px; max-width: 90%; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.95); transition: transform 0.2s ease-out; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-body { padding: 32px; text-align: center; }
        
        .success-icon { width: 64px; height: 64px; background: #dcfce7; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 20px; }
        .success-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
        .success-text { color: #64748b; line-height: 1.6; margin-bottom: 30px; font-size: 15px; }
        .btn-dismiss { padding: 12px 32px; border-radius: 12px; border: none; background: var(--accent-blue); color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; width: 100%; font-size: 15px; }
        .btn-dismiss:hover { background: var(--accent-blue-hover); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(130, 78, 57, 0.3); }

        body.dark-mode .modal-content { background: #1e293b; }
        body.dark-mode .success-title { color: white; }
        body.dark-mode .success-text { color: #94a3b8; }
        body.dark-mode .success-icon { background: #064e3b; color: #34d399; }

        @media (max-width: 1100px) {
            .details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 700px) {
            .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox()">
    <img class="lightbox-content" id="lightboxImg" src="">
</div>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0; font-weight:700;">Household Profile</h2>
            <p style="margin:0; color: var(--text-gray);">Manage members of Household #<?php echo htmlspecialchars($household['household_no']); ?></p>
        </div>

        <div class="user-profile-container">
            <div class="user-pill" onclick="toggleLogout()">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div style="margin: 0 15px; line-height: 1.2;">
                    <div style="font-weight: 600; font-size: 15px;">Secretary</div>
                    <div style="color:var(--text-gray); font-size: 12px;">Barangay Secretary</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #94a3b8;"></i>
            </div>
            <div class="logout-dropdown" id="logoutDropdown">
                <div style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb; color: #64748b; font-size: 14px;">Signed in as<br><b>Secretary</b></div>
                <a href="logout.php" class="logout-btn" style="display:flex; align-items:center; justify-content:center; padding:15px; color:var(--danger-red); text-decoration:none; font-weight:600;">
                    <i class="fa-solid fa-right-from-bracket" style="margin-right:10px;"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="content-body">
        <div class="hh-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:16px;">
                <h3 style="margin:0;">Household Information</h3>
                <a href="edit_household.php?household_no=<?php echo urlencode($household['household_no']); ?>" class="btn-secondary">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Household
                </a>
            </div>
            <div class="info-grid">
                <div class="info-item"><label>Household No.</label><span>#<?php echo htmlspecialchars($household['household_no']); ?></span></div>
                <div class="info-item"><label>Purok</label><span><?php echo htmlspecialchars($household['purok'] ?? '---'); ?></span></div>
                <div class="info-item"><label>Survey Date</label><span><?php echo !empty($household['survey_date']) ? date("M d, Y", strtotime($household['survey_date'])) : '---'; ?></span></div>
                <div class="info-item"><label>Complete Address</label><span><?php echo htmlspecialchars($household['address'] ?? '---'); ?></span></div>
                <div class="info-item"><label>House Ownership</label><span><?php echo htmlspecialchars($household['house'] ?? '---'); ?></span></div>
                <div class="info-item"><label>House Type</label><span><?php echo htmlspecialchars($household['house_type'] ?? '---'); ?></span></div>
                <div class="info-item"><label>Electricity</label><span><?php echo htmlspecialchars($household['electricity'] ?? '---'); ?></span></div>
                <div class="info-item"><label>Water Source</label><span><?php echo htmlspecialchars($household['water'] ?? '---'); ?></span></div>
            </div>
        </div>

        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin:0;">Registered Family Members</h3>
                <a href="add_members.php?household_no=<?php echo urlencode($safe_hh_no); ?>" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Add Member
                </a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Relation</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($m = mysqli_fetch_assoc($result_m)): ?>
                        <?php
                            $activities = $resident_activities[$m['id']] ?? [];
                            $all_labels = [];
                            if (isset($m['is_4ps']) && $m['is_4ps'] == 1) $all_labels[] = "4Ps";
                            if (isset($m['is_pwd']) && $m['is_pwd'] == 1) $all_labels[] = "PWD";
                            if (isset($m['is_senior']) && $m['is_senior'] == 1) $all_labels[] = "Senior Citizen";
                            if (isset($m['is_solo']) && $m['is_solo'] == 1) $all_labels[] = "Solo Parent";
                            if (isset($m['is_voter']) && $m['is_voter'] == 1) $all_labels[] = "Voter";
                            if (isset($m['is_minor']) && $m['is_minor'] == 1) $all_labels[] = "Minor";

                            $employment_raw = trim($m['employment_status'] ?? '');
                            $employment_norm = strtoupper($employment_raw);
                            if ($employment_norm === 'YES' || $employment_norm === 'EMPLOYED') {
                                $employment_label = 'Employed';
                            } elseif ($employment_norm === 'NO' || $employment_norm === 'UNEMPLOYED') {
                                $employment_label = 'Unemployed';
                            } else {
                                $employment_label = $employment_raw !== '' ? $employment_raw : '---';
                            }

                            $full_name = trim($m['last_name'] . ', ' . $m['first_name'] . ', ' . $m['middle_name']);
                            $relation = $m['relationship'] ?? '---';
                            $age = $m['age'] ?? '---';
                            $status_label = $m['status'] ?? 'Active';
                            $sex = $m['gender'] ?? '---';
                            $birth_date = !empty($m['dob']) ? date("M d, Y", strtotime($m['dob'])) : '---';
                            $civil_status = $m['civil_status'] ?? '---';
                            $education = $m['education'] ?? '---';
                            $photo_path = trim((string)($m['photo_path'] ?? ''));
                            $photo_file = $photo_path !== '' ? __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $photo_path) : '';
                            $has_photo = $photo_path !== '' && $photo_path !== 'uploads/default.png' && is_file($photo_file);

                            $class_json = htmlspecialchars(json_encode($all_labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            $act_json = htmlspecialchars(json_encode($activities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="member-row"
                            data-photo="<?php echo htmlspecialchars($photo_path, ENT_QUOTES); ?>"
                            data-hh-no="<?php echo htmlspecialchars($safe_hh_no, ENT_QUOTES); ?>"
                            data-last-name="<?php echo htmlspecialchars($m['last_name'], ENT_QUOTES); ?>"
                            data-first-name="<?php echo htmlspecialchars($m['first_name'], ENT_QUOTES); ?>"
                            data-middle-name="<?php echo htmlspecialchars($m['middle_name'] ?? '---', ENT_QUOTES); ?>"
                            data-suffix-name="<?php echo htmlspecialchars($m['suffix_name'] ?? '---', ENT_QUOTES); ?>"
                            data-relation="<?php echo htmlspecialchars($relation, ENT_QUOTES); ?>"
                            data-age="<?php echo htmlspecialchars($age, ENT_QUOTES); ?>"
                            data-status="<?php echo htmlspecialchars($status_label, ENT_QUOTES); ?>"
                            data-sex="<?php echo htmlspecialchars($sex, ENT_QUOTES); ?>"
                            data-birth-date="<?php echo htmlspecialchars($birth_date, ENT_QUOTES); ?>"
                            data-civil-status="<?php echo htmlspecialchars($civil_status, ENT_QUOTES); ?>"
                            data-education="<?php echo htmlspecialchars($education, ENT_QUOTES); ?>"
                            data-employment="<?php echo htmlspecialchars($employment_label, ENT_QUOTES); ?>"
                            data-classification='<?php echo $class_json; ?>'
                            data-activities='<?php echo $act_json; ?>'>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if ($has_photo): ?>
                                        <img src="<?php echo htmlspecialchars($photo_path); ?>" class="res-photo" onclick="openLightbox(this.src)" alt="Resident photo">
                                    <?php else: ?>
                                        <span class="res-photo-placeholder" aria-label="No profile picture">
                                            <i class="fa-solid fa-user"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div style="font-weight:700;">
                                        <?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name'] . ', ' . $m['middle_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($m['relationship']); ?></td>
                            <td><?php echo htmlspecialchars($m['age']); ?></td>
                            <td>
                                <span class="status-pill <?php echo ($m['status'] ?? 'Active') !== 'Active' ? 'status-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($m['status'] ?? 'Active'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="action-btn btn-view" title="View Details" onclick="openMemberModal(this.closest('.member-row'))">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <a href="edit_member.php?id=<?php echo $m['id']; ?>" class="action-btn btn-edit" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="action-btn btn-archive btn-archive-text" title="Archive Member" 
                                            onclick="openArchivePage('<?php echo $m['id']; ?>')">
                                        <i class="fa-solid fa-box-archive"></i> Archive
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="memberDetailsModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div class="panel modal-container" style="background:white; width:650px; max-width:90%; border-radius:24px; padding:32px; max-height:85vh; overflow-y:auto; position:relative;">
        <button onclick="closeMemberModal()" style="position:absolute; top:24px; right:24px; background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;"><i class="fa-solid fa-xmark"></i></button>
        <div style="display:flex; align-items:center; gap:20px; margin-bottom:24px; border-bottom:1px solid #e2e8f0; padding-bottom:20px;">
            <img id="modalResPhoto" src="uploads/default.png" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-blue);">
            <div>
                <h2 id="modalResName" style="margin:0 0 4px 0; font-size:22px; color:inherit;"></h2>
                <p id="modalResHH" style="margin:0; color:#64748b; font-size:14px;"></p>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Last Name</label><div id="modalResLastName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">First Name</label><div id="modalResFirstName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Middle Name</label><div id="modalResMiddleName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Suffix Name</label><div id="modalResSuffix" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Gender</label><div id="modalResGender" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Date of Birth</label><div id="modalResDob" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Age</label><div id="modalResAge" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Relationship to Head</label><div id="modalResRel" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Civil Status</label><div id="modalResCivil" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Employment Status</label><div id="modalResEmp" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div style="grid-column: span 2;"><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Educational Attainment</label><div id="modalResEdu" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div style="grid-column: span 2;"><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Special Classifications</label><div id="modalResClassifications" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; margin-bottom:8px; display:block;">Included Activities</label>
            <div id="modalResActivities" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
        </div>
    </div>
</div>

<script>
    const detailsModal = document.getElementById('memberDetailsModal');
    const detailsDialog = detailsModal ? detailsModal.querySelector('.details-dialog') : null;
    const detailsTitle = document.getElementById('memberDetailsTitle');
    const detailsSubtitle = document.getElementById('memberDetailsSubtitle');
    const detailsGrid = document.getElementById('memberDetailsGrid');

    document.addEventListener("DOMContentLoaded", function() {
        wireMemberModal();
    });

    function toggleLogout() { document.getElementById('logoutDropdown').classList.toggle('show'); }
    function openLightbox(src) { document.getElementById('lightboxImg').src = src; document.getElementById('lightboxModal').style.display = "flex"; }
    function closeLightbox() { document.getElementById('lightboxModal').style.display = "none"; }

    function openArchivePage(id) {
        window.location.href = "archived.php?id=" + id + "&household_no=<?php echo urlencode($household_no); ?>";
    }

    function escapeHtml(value) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
    }

    function parseJsonList(value) {
        if (!value) return [];
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function buildTextItem(label, value) {
        const safeValue = value && value.trim() ? value : '---';
        return `
            <div class="details-item">
                <label>${escapeHtml(label)}</label>
                <span>${escapeHtml(safeValue)}</span>
            </div>
        `;
    }

    function buildBadgeItem(label, items) {
        const badges = items.length
            ? items.map((item) => `<span class="details-badge">${escapeHtml(item)}</span>`).join('')
            : `<span class="details-muted">None</span>`;

        return `
            <div class="details-item">
                <label>${escapeHtml(label)}</label>
                <div class="details-badges">${badges}</div>
            </div>
        `;
    }

    function openMemberModal(row) {
        if (!detailsModal) return;

        const data = row.dataset;
        const classifications = parseJsonList(data.classification);
        const activities = parseJsonList(data.activities);

        document.getElementById('modalResPhoto').src = data.photo || 'uploads/default.png';
        document.getElementById('modalResName').innerText = `${data.lastName}, ${data.firstName} ${data.middleName !== '---' ? data.middleName : ''}`;
        document.getElementById('modalResHH').innerText = `Household No: #${data.hhNo || 'N/A'}`;
        
        document.getElementById('modalResLastName').innerText = data.lastName || '—';
        document.getElementById('modalResFirstName').innerText = data.firstName || '—';
        document.getElementById('modalResMiddleName').innerText = data.middleName || '—';
        document.getElementById('modalResSuffix').innerText = data.suffixName || '—';
        
        document.getElementById('modalResGender').innerText = data.sex || '—';
        document.getElementById('modalResDob').innerText = data.birthDate || '—';
        document.getElementById('modalResAge').innerText = data.age || '—';
        document.getElementById('modalResRel').innerText = data.relation || '—';
        
        document.getElementById('modalResCivil').innerText = data.civilStatus || '—';
        document.getElementById('modalResEmp').innerText = data.employment || '—';
        document.getElementById('modalResEdu').innerText = data.education || '—';
        
        document.getElementById('modalResClassifications').innerText = classifications.length ? classifications.join(', ') : 'None';

        let actBadges = activities.map(act => `<span class="activity-badge" style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600;">${act}</span>`);
        document.getElementById('modalResActivities').innerHTML = actBadges.length ? actBadges.join(' ') : '<span style="color:#64748b; font-size:14px;">None</span>';

        detailsModal.style.display = 'flex';
        document.body.classList.add('modal-open');
    }

    function closeMemberModal() {
        if (!detailsModal) return;
        detailsModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    function wireMemberModal() {
        if (detailsModal) {
            detailsModal.addEventListener('click', (event) => {
                if (event.target === detailsModal) {
                    closeMemberModal();
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && detailsModal && detailsModal.classList.contains('is-open')) {
                closeMemberModal();
            }
        });
    }

    window.onclick = function(e) {
        if (!e.target.closest('.user-profile-container')) {
            const dropdown = document.getElementById('logoutDropdown');
            if (dropdown && dropdown.classList.contains('show')) { dropdown.classList.remove('show'); }
        }
    }
</script>
<?php if (($_GET['success'] ?? '') === 'residents_added'): ?>
<?php render_form_draft_assets(); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.AppFormDraft) {
            window.AppFormDraft.clear(<?php echo json_encode('add-members-' . $household_no, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
        }
    });
</script>
<?php endif; ?>
</body>
</html>









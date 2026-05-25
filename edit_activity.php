<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

$allowed_roles = ['Captain', 'Barangay Captain', 'Admin', 'Secretary'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'User';

// AJAX endpoint: fetch resident details for "View" modal
if (isset($_GET['ajax_view_resident'])) {
    $res_id = (int)($_GET['resident_id'] ?? 0);
    if ($res_id > 0) {
        $res_query = mysqli_query($conn, "SELECT r.*, h.household_no as hh_no FROM residents r LEFT JOIN households h ON r.household_no = h.household_no WHERE r.id = '$res_id' LIMIT 1");
        $res = $res_query ? mysqli_fetch_assoc($res_query) : null;
        if ($res) {
            echo json_encode([
                'id' => $res['id'],
                'photo_path' => $res['photo_path'] ?? '',
                'last_name' => $res['last_name'] ?? '',
                'first_name' => $res['first_name'] ?? '',
                'middle_name' => $res['middle_name'] ?? '',
                'gender' => $res['gender'] ?? '',
                'dob' => $res['dob'] ?? '',
                'age' => $res['age'] ?? '',
                'civil_status' => $res['civil_status'] ?? '',
                'relationship' => $res['relationship'] ?? '',
                'employment_status' => $res['employment_status'] ?? '',
                'education' => $res['education'] ?? '',
                'household_no' => $res['household_no'] ?? 'N/A',
                'status' => $res['status'] ?? 'Active'
            ]);
        } else {
            echo json_encode(['error' => 'Resident not found']);
        }
    } else {
        echo json_encode(['error' => 'Invalid ID']);
    }
    exit();
}
$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($activity_id <= 0) {
    header("Location: activities.php");
    exit();
}

$errors = [];
$success = '';

$activity = null;
$activity_query = mysqli_query($conn, "SELECT * FROM activities WHERE id = '$activity_id' LIMIT 1");
if ($activity_query) {
    $activity = mysqli_fetch_assoc($activity_query);
}

if (!$activity) {
    header("Location: activities.php");
    exit();
}

$activity_title = $activity['activity_name'] ?? '';
$activity_date = $activity['activity_date'] ?? '';
$activity_description = $activity['description'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['remove_participant'])) {
    $activity_title = ucwords(trim($_POST['activity_title'] ?? ''));
    $activity_description = trim($_POST['activity_description'] ?? '');

    if ($activity_title === '') {
        $errors[] = 'Activity title is required.';
    }

    if (empty($errors)) {
        $safe_title = mysqli_real_escape_string($conn, $activity_title);
        $safe_desc = mysqli_real_escape_string($conn, $activity_description);

        $update_sql = "UPDATE activities SET activity_name = '$safe_title', description = '$safe_desc' WHERE id = '$activity_id'";
        if (mysqli_query($conn, $update_sql)) {
            $new_selected_res = $_POST['new_beneficiaries'] ?? [];
            $new_selected_hh = $_POST['new_household_beneficiaries'] ?? [];
            $is_4ps_act = (strtolower(trim($activity_title)) === '4ps beneficiary');
            $participant_records = [];

            if ($is_4ps_act) {
                $hh_selected = array_filter(array_map('trim', $new_selected_hh));
                if (!empty($hh_selected)) {
                    $hh_escaped = implode("','", array_map(function($h) use ($conn) { return mysqli_real_escape_string($conn, $h); }, $hh_selected));
                    mysqli_query($conn, "UPDATE residents SET is_4ps = 1 WHERE household_no IN ('$hh_escaped')");
                    $res_query = mysqli_query($conn, "SELECT id, household_no FROM residents WHERE household_no IN ('$hh_escaped') AND COALESCE(is_archived, 0) = 0");
                    if ($res_query) {
                        while ($r = mysqli_fetch_assoc($res_query)) {
                            $participant_records[] = ['id' => (int)$r['id'], 'hh' => $r['household_no']];
                        }
                    }
                }
            } else {
                $res_ids = array_values(array_filter(array_map('intval', $new_selected_res), function ($id) { return $id > 0; }));
                if (!empty($res_ids)) {
                    $id_list = implode(',', $res_ids);
                    $res_query = mysqli_query($conn, "SELECT id, household_no FROM residents WHERE id IN ($id_list)");
                    if ($res_query) {
                        while ($r = mysqli_fetch_assoc($res_query)) {
                            $participant_records[] = ['id' => (int)$r['id'], 'hh' => $r['household_no']];
                        }
                    }
                }
            }

            if (!empty($participant_records)) {
                $stmt = $conn->prepare("INSERT INTO activity_participants (activity_id, resident_id, household_no, status) VALUES (?, ?, ?, 'Pending')");
                if ($stmt) {
                    foreach ($participant_records as $rec) {
                        $stmt->bind_param('iis', $activity_id, $rec['id'], $rec['hh']);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
            }

            $action_desc = mysqli_real_escape_string($conn, "Updated activity #$activity_id ($activity_title)");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
            $success = 'Activity updated successfully.';
        } else {
            $errors[] = 'Failed to update activity. Please try again.';
        }
    }
}

$is_4ps_act_edit = (strtolower(trim($activity_title)) === '4ps beneficiary');

if ($is_4ps_act_edit) {
    $count_query = mysqli_query($conn, "SELECT COUNT(DISTINCT household_no) AS cnt FROM activity_participants WHERE activity_id = '$activity_id'");
} else {
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM activity_participants WHERE activity_id = '$activity_id'");
}
$beneficiaries_count = $count_query ? (int)mysqli_fetch_assoc($count_query)['cnt'] : 0;

if (isset($_POST['remove_participant'])) {
    $participant_id = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $remove_hh = $_POST['remove_household_no'] ?? '';

    if ($remove_hh !== '') {
        $safe_hh = mysqli_real_escape_string($conn, $remove_hh);
        $del_sql = "DELETE FROM activity_participants WHERE household_no = '$safe_hh' AND activity_id = '$activity_id'";
        if (mysqli_query($conn, $del_sql)) {
            $action_desc = mysqli_real_escape_string($conn, "Removed household #$safe_hh from activity #$activity_id");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
            $success = 'Household removed successfully.';
            if ($is_4ps_act_edit) {
                $count_query = mysqli_query($conn, "SELECT COUNT(DISTINCT household_no) AS cnt FROM activity_participants WHERE activity_id = '$activity_id'");
            } else {
                $count_query = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM activity_participants WHERE activity_id = '$activity_id'");
            }
            $beneficiaries_count = $count_query ? (int)mysqli_fetch_assoc($count_query)['cnt'] : 0;
        } else {
            $errors[] = 'Failed to remove household.';
        }
    } elseif ($participant_id > 0) {
        $del_sql = "DELETE FROM activity_participants WHERE id = '$participant_id' AND activity_id = '$activity_id'";
        if (mysqli_query($conn, $del_sql)) {
            $action_desc = mysqli_real_escape_string($conn, "Removed participant #$participant_id from activity #$activity_id");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
            $success = 'Participant removed successfully.';
            $beneficiaries_count--;
        } else {
            $errors[] = 'Failed to remove participant.';
        }
    }
}

if ($is_4ps_act_edit) {
    $participants_query = mysqli_query(
        $conn,
        "SELECT ap.household_no, min(ap.status) as status, min(ap.id) as participant_id,
                GROUP_CONCAT(CONCAT(r.last_name, ', ', r.first_name) SEPARATOR '; ') as members
         FROM activity_participants ap
         JOIN residents r ON ap.resident_id = r.id
         WHERE ap.activity_id = '$activity_id'
         GROUP BY ap.household_no
         ORDER BY ap.household_no ASC"
    );
} else {
    $participants_query = mysqli_query(
        $conn,
        "SELECT ap.id as participant_id, ap.status, r.id AS resident_id, r.last_name, r.first_name, r.middle_name, r.household_no
         FROM activity_participants ap
         JOIN residents r ON ap.resident_id = r.id
         WHERE ap.activity_id = '$activity_id'
         ORDER BY r.last_name ASC, r.first_name ASC"
    );
}

$non_participants = mysqli_query($conn, "SELECT r.id, r.last_name, r.first_name, r.middle_name, r.household_no, r.is_4ps, r.photo_path FROM residents r LEFT JOIN activity_participants ap ON r.id = ap.resident_id AND ap.activity_id = '$activity_id' WHERE COALESCE(r.is_archived, 0) = 0 AND ap.resident_id IS NULL ORDER BY r.last_name ASC, r.first_name ASC");
$non_participant_hh_query = mysqli_query($conn, "SELECT r.household_no, GROUP_CONCAT(CONCAT(r.last_name, ', ', r.first_name) SEPARATOR '; ') as members FROM residents r LEFT JOIN activity_participants ap ON r.id = ap.resident_id AND ap.activity_id = '$activity_id' WHERE COALESCE(r.is_archived, 0) = 0 AND ap.resident_id IS NULL GROUP BY r.household_no ORDER BY r.household_no ASC");

$page_toasts = [];
if ($success !== '') {
    $page_toasts[] = app_toast_from_message($success, 'success', 'Success');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-navy: #1e293b; --accent-blue: #2563eb; --logo-orange: #ff9800; --text-gray: #64748b; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

        /* SIDEBAR CONSISTENCY */
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
        .sidebar.collapsed .sidebar-header { justify-content: center; padding: 25px 0; }
        .sidebar.collapsed .toggle-icon { margin-left: 0; color: white; font-size: 32px; }

        .nav-menu { padding: 10px 12px; flex-grow: 1; }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; color: #cbd5e1; text-decoration: none; border-radius: 12px; margin-bottom: 4px; font-weight: 600;  }

        .nav-item.active { background: var(--accent-blue); color: white; }
        .nav-item i { font-size: 15px; min-width: 28px; text-align: center; }
        .nav-text { margin-left: 10px; }
        .sidebar.collapsed .nav-text { display: none; }
        .sidebar.collapsed .nav-item { justify-content: center; padding: 18px 0; }

        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .top-header { background: #ffffff; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .content-body { padding: 16px 20px 20px; }
        .panel { background: white; border: 1px solid #e5e7eb; padding: 18px; border-radius: 20px; border: 1px solid #e2e8f0; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-weight: 600; color: #1e293b; font-size: 14px; }
        input[type="text"], input[type="date"], textarea { padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 14px; }
        textarea { resize: vertical; min-height: 80px; }
        .suggestion-wrap { position: relative; }
        .suggestion-list { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; z-index: 20;  }
        .suggestion-list[hidden] { display: none; }
        .suggestion-option { width: 100%; padding: 11px 12px; border: 0; background: white; text-align: left; font-family: inherit; font-size: 14px; cursor: pointer; color: #1e293b; }
        .suggestion-option:hover, .suggestion-option:focus { background: #eff6ff; outline: none; }

        .btn-row { display: flex; gap: 12px; margin-top: 20px; }
        .btn-save { background: var(--accent-blue); color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .btn-secondary { background: #f1f5f9; color: #1e293b; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }

        .beneficiary-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .beneficiary-table th { text-align: left; padding: 12px; border-bottom: 2px solid #e5e7eb; color: var(--text-gray); font-size: 11px; text-transform: uppercase; }
        .beneficiary-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .btn-view-resident { background: none; border: 1px solid #e2e8f0; color: var(--accent-blue); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.15s; }
        .btn-view-resident:hover { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
        .view-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .view-modal-overlay.show { display: flex; }
        .view-modal { background: white; border-radius: 16px; width: 420px; max-width: 92%; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden; }
        .view-modal-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .view-modal-header h3 { margin: 0; font-size: 16px; color: #1e293b; }
        .view-modal-close { background: #f1f5f9; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; }
        .view-modal-close:hover { background: #e2e8f0; color: #1e293b; }
        .view-modal-body { padding: 24px; }
        .view-photo { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; display: block; margin: 0 auto 16px; background: #f1f5f9; }
        .view-name { text-align: center; font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .view-hh { text-align: center; font-size: 13px; color: var(--text-gray); margin-bottom: 20px; }
        .view-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .view-info-item { display: flex; flex-direction: column; gap: 2px; }
        .view-info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
        .view-info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
        .status-badge { background: #e8f5e9; color: #2e7d32; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; border: none; }
        .status-pending { background: #fee2e2; color: #991b1b; }
        .status-received { background: #dcfce7; color: #166534; }

        .error-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }

        .user-profile-container { position: relative; }
        .user-pill { display: flex; align-items: center; background: #f8fafc; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; }
        .avatar { background: var(--accent-blue); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logout-dropdown { position: absolute; top: 110%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; width: 220px;  display: none; z-index: 100; overflow: visible; }
        .logout-dropdown.show { display: block; }
        .dropdown-header { padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb; color: #64748b; font-size: 14px; }
        .dropdown-header b { display: block; color: #1e293b; margin-top: 4px; font-size: 16px; }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px; color: #ef4444; text-decoration: none; font-weight: 600; font-size: 16px; }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .responsive-table {
            width: 100%;
            border-collapse: collapse;
        }

        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
            .beneficiary-tools { flex-direction: column; align-items: stretch; gap: 12px; }
            .search-input { width: 100% !important; box-sizing: border-box; }
        }

        @media (max-width: 768px) {
            body { overflow: auto !important; height: auto !important; }
            .main-container { min-height: 100vh; overflow: visible; }
            .top-header { padding: 16px !important; flex-direction: column; gap: 14px; align-items: flex-start; }
            .user-profile-container { width: 100%; }
            .user-pill { width: 100%; box-sizing: border-box; }
            .content-body { padding: 16px !important; }
            .panel { padding: 16px; border-radius: 16px; }
            .btn-row { flex-direction: column; }
            .btn-save, .btn-secondary { width: 100%; justify-content: center; box-sizing: border-box; text-align: center; }

            .table-responsive { overflow: visible; }
            .responsive-table,
            .responsive-table thead,
            .responsive-table tbody,
            .responsive-table tr,
            .responsive-table td,
            .responsive-table th {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .responsive-table thead { display: none; }
            .responsive-table tr {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 10px 12px;
                margin-bottom: 12px;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            }
            .responsive-table td {
                border: none;
                padding: 8px 0;
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: flex-start;
            }
            .responsive-table td::before {
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                flex: 0 0 40%;
                max-width: 40%;
            }
            .responsive-table td > * {
                text-align: right;
            }
            form[method="POST"] button, form[method="POST"] input[type="submit"] { margin: 0; }
            /* Fix header dropdown on mobile so it won't be clipped by parent containers */
            .logout-dropdown {
                position: fixed;
                left: 16px;
                right: 16px;
                top: 72px;
                width: auto;
                max-width: calc(100% - 32px);
                border-radius: 12px;
                z-index: 20000;
                overflow: visible;
            }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Edit Activity</h2>
            <p style="margin:0; color: var(--text-gray);">Update activity details</p>
        </div>

        <div class="user-profile-container">
            <div class="user-pill" onclick="toggleLogout()">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div style="line-height: 1.2; margin-left: 15px; margin-right: 15px;">
                    <div style="font-weight: 600; font-size: 15px;"><?php echo htmlspecialchars($user_role); ?></div>
                    <div style="color:#64748b; font-size: 13px;">Authorized Personnel</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #94a3b8;"></i>
            </div>

            <div class="logout-dropdown" id="logoutDropdown">
                <div class="dropdown-header">Signed in as<br><b><?php echo htmlspecialchars($user_role); ?></b></div>
                <a href="logout.php" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Activity Title</label>
                        <div class="suggestion-wrap">
                            <input type="text" id="activityTitle" name="activity_title" value="<?php echo htmlspecialchars($activity_title); ?>" autocomplete="off" required>
                            <div id="activityTitleSuggestions" class="suggestion-list" hidden></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Activity</label>
                        <input type="date" name="activity_date" value="<?php echo htmlspecialchars($activity_date); ?>" readonly disabled>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label>Description <span style="font-weight: 400; color: #94a3b8; font-size: 13px;">(Optional)</span></label>
                    <textarea name="activity_description" placeholder="Add a brief description of the activity..."><?php echo htmlspecialchars($activity_description); ?></textarea>
                </div>

                <div style="margin-top: 16px; color: var(--text-gray);">
                    Beneficiaries: <b><?php echo $beneficiaries_count; ?></b>
                </div>

                <div style="margin-top: 10px;" class="table-responsive">
                    <?php if ($participants_query && mysqli_num_rows($participants_query) > 0): ?>
                        <table class="beneficiary-table responsive-table">
                            <?php if ($is_4ps_act_edit): ?>
                                <thead>
                                    <tr>
                                        <th>Household No.</th>
                                        <th>Household Members</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($participants_query)): ?>
                                        <tr>
                                            <td data-label="Household No.">
                                                <strong>Household #<?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td data-label="Household Members"><small style="color: #64748b;"><?php echo htmlspecialchars($row['members']); ?></small></td>
                                            <td data-label="Status">
                                                <?php if ($row['status'] === 'Received'): ?>
                                                    <span class="status-badge status-received">RECEIVED</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            <?php else: ?>
                                <thead>
                                    <tr>
                                        <th>Resident</th>
                                        <th>Household No.</th>
                                        <th>Status</th>
                                        <th>View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($participants_query)): ?>
                                        <tr>
                                            <td data-label="Resident">
                                                <strong>
                                                    <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ($row['middle_name'] ? ' ' . $row['middle_name'] : '')); ?>
                                                </strong>
                                            </td>
                                            <td data-label="Household No."><?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></td>
                                            <td data-label="Status">
                                                <?php if ($row['status'] === 'Received'): ?>
                                                    <span class="status-badge status-received">RECEIVED</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="View">
                                                <button type="button" class="btn-view-resident" onclick="viewResident(<?php echo (int)$row['resident_id']; ?>)" title="View resident info">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <div style="color: var(--text-gray); margin-top: 10px;">No beneficiaries assigned.</div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
                    <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #1e293b;">Add New Beneficiaries</h3>
                    <div class="beneficiary-tools" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <input type="text" id="beneficiarySearch" class="search-input" placeholder="Search resident name or household no..." style="padding:10px 12px; border:1px solid #e2e8f0; border-radius:16px; font-size:14px; width:320px;">
                        <label class="select-all" style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                            <input type="checkbox" id="selectAllBeneficiaries"> Select all
                        </label>
                    </div>

                    <div id="residentTableContainer" class="table-responsive">
                        <table id="beneficiaryTable" class="beneficiary-table responsive-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Resident</th>
                                    <th>Household No.</th>
                                    <th style="width: 50px;">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($non_participants && mysqli_num_rows($non_participants) > 0): ?>
                                    <?php while($row_np = mysqli_fetch_assoc($non_participants)): ?>
                                        <?php
                                            $full_name = $row_np['last_name'] . ', ' . $row_np['first_name'] . ($row_np['middle_name'] ? ' ' . $row_np['middle_name'] : '');
                                        ?>
                                        <tr class="data-row" data-is-4ps="<?php echo (int)$row_np['is_4ps']; ?>">
                                            <td data-label="Include">
                                                <input type="checkbox" class="beneficiary-check" name="new_beneficiaries[]" value="<?php echo (int)$row_np['id']; ?>">
                                            </td>
                                            <td data-label="Resident"><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                                            <td data-label="Household No."><?php echo htmlspecialchars($row_np['household_no'] ?? 'N/A'); ?></td>
                                            <td data-label="View">
                                                <button type="button" class="btn-view-resident" onclick="viewResident(<?php echo (int)$row_np['id']; ?>)" title="View resident info">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding: 30px; color: var(--text-gray);">All active residents are already assigned to this activity.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="householdTableContainer" style="display: none;" class="table-responsive">
                        <table id="householdBeneficiaryTable" class="beneficiary-table responsive-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Household No.</th>
                                    <th>Household Members</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($non_participant_hh_query && mysqli_num_rows($non_participant_hh_query) > 0): ?>
                                    <?php while($row_hh = mysqli_fetch_assoc($non_participant_hh_query)): ?>
                                        <tr class="data-row">
                                            <td data-label="Include">
                                                <input type="checkbox" class="hh-beneficiary-check" name="new_household_beneficiaries[]" value="<?php echo htmlspecialchars($row_hh['household_no']); ?>">
                                            </td>
                                            <td data-label="Household No."><strong>Household #<?php echo htmlspecialchars($row_hh['household_no']); ?></strong></td>
                                            <td data-label="Household Members"><small style="color: #64748b;"><?php echo htmlspecialchars($row_hh['members']); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align:center; padding: 30px; color: var(--text-gray);">All active households are already assigned to this activity.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <a href="activities.php" class="btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Activities
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Resident Modal -->
<div class="view-modal-overlay" id="viewResidentModal">
    <div class="view-modal">
        <div class="view-modal-header">
            <h3><i class="fa-solid fa-user" style="margin-right: 8px; color: var(--accent-blue);"></i>Resident Information</h3>
            <button class="view-modal-close" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="view-modal-body" id="viewModalBody">
            <div style="text-align:center; color: var(--text-gray); padding: 20px;">Loading...</div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setupActivityTitleSuggestions();
        setupBeneficiarySearch();
        setupSelectAll();
    });

    function toggleLogout() {
        document.getElementById('logoutDropdown').classList.toggle('show');
    }

    window.onclick = function(event) {
        if (!event.target.closest('.user-profile-container')) {
            const dropdown = document.getElementById('logoutDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }

    function setupActivityTitleSuggestions() {
        const input = document.getElementById('activityTitle');
        const list = document.getElementById('activityTitleSuggestions');
        if (!input || !list) return;

        const suggestions = [
            'Livelihood Assistance',
            'Educational Assistance',
            'Cash Assistance For Emergencies',
            'Scholarship Program',
            'Rice Or Food Distribution',
            'TUPAD (Emergency Employment Program)',
            '4Ps Beneficiary'
        ];

        const showSuggestions = () => {
            const term = input.value.trim().toLowerCase();
            const matches = suggestions.filter((item) => item.toLowerCase().includes(term));

            list.innerHTML = '';
            matches.forEach((item) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'suggestion-option';
                option.textContent = item;
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = item;
                    list.hidden = true;
                    if (window.filterBeneficiaryRows) window.filterBeneficiaryRows();
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

    function setupBeneficiarySearch() {
        const searchInput = document.getElementById('beneficiarySearch');
        const titleInput = document.getElementById('activityTitle');
        const resContainer = document.getElementById('residentTableContainer');
        const hhContainer = document.getElementById('householdTableContainer');
        if (!searchInput) return;

        const resRows = Array.from(document.querySelectorAll('#beneficiaryTable tbody tr.data-row'));
        const hhRows = Array.from(document.querySelectorAll('#householdBeneficiaryTable tbody tr.data-row'));

        window.filterBeneficiaryRows = () => {
            const term = searchInput.value.trim().toLowerCase();
            const is4PsAct = titleInput && titleInput.value.trim().toLowerCase() === '4ps beneficiary';

            if (is4PsAct) {
                if (resContainer) resContainer.style.display = 'none';
                if (hhContainer) hhContainer.style.display = '';
                document.querySelectorAll('.beneficiary-check').forEach(box => box.checked = false);

                hhRows.forEach((row) => {
                    const hay = row.textContent.toLowerCase();
                    row.style.display = hay.includes(term) ? '' : 'none';
                });
            } else {
                if (resContainer) resContainer.style.display = '';
                if (hhContainer) hhContainer.style.display = 'none';

                document.querySelectorAll('.hh-beneficiary-check').forEach(box => box.checked = false);

                resRows.forEach((row) => {
                    const hay = row.textContent.toLowerCase();
                    row.style.display = hay.includes(term) ? '' : 'none';
                });
            }
        };

        searchInput.addEventListener('input', window.filterBeneficiaryRows);
        if (titleInput) {
            titleInput.addEventListener('input', window.filterBeneficiaryRows);
            titleInput.addEventListener('change', window.filterBeneficiaryRows);
        }
        window.filterBeneficiaryRows();
    }

    function setupSelectAll() {
        const selectAll = document.getElementById('selectAllBeneficiaries');
        if (!selectAll) return;

        selectAll.addEventListener('change', () => {
            const is4PsAct = document.getElementById('activityTitle').value.trim().toLowerCase() === '4ps beneficiary';
            if (is4PsAct) {
                document.querySelectorAll('.hh-beneficiary-check').forEach((box) => {
                    if (box.closest('tr').style.display !== 'none') {
                        box.checked = selectAll.checked;
                    }
                });
            } else {
                document.querySelectorAll('.beneficiary-check').forEach((box) => {
                    if (box.closest('tr').style.display !== 'none') {
                        box.checked = selectAll.checked;
                    }
                });
            }
        });
    }
    function viewResident(residentId) {
        const modal = document.getElementById('viewResidentModal');
        const body = document.getElementById('viewModalBody');
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        body.innerHTML = '<div style="text-align:center; color: #64748b; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

        fetch('edit_activity.php?ajax_view_resident=1&resident_id=' + residentId)
            .then(res => res.json())
            .then(data => {
                if (data.error) { body.innerHTML = '<div style="text-align:center; color:#ef4444; padding:20px;">' + data.error + '</div>'; return; }
                const fullName = data.last_name + ', ' + data.first_name + (data.middle_name ? ' ' + data.middle_name : '');
                const suffix = data.suffix_name ? data.suffix_name : '—';
                const photoSrc = data.photo_path ? data.photo_path : '';
                const photoHtml = photoSrc ? '<img src="' + photoSrc + '" class="view-photo" alt="Photo">' : '<div class="view-photo" style="display:flex;align-items:center;justify-content:center;font-size:32px;color:#94a3b8;"><i class="fa-solid fa-user"></i></div>';
                
                let classifications = [];
                if (data.is_voter == 1) classifications.push('Registered Voter');
                if (data.is_pwd == 1) classifications.push('PWD');
                if (data.is_solo == 1) classifications.push('Solo Parent');
                if (data.is_senior == 1) classifications.push('Senior Citizen');
                if (data.is_minor == 1) classifications.push('Minor');
                if (data.is_4ps == 1) classifications.push('4Ps');
                const classStr = classifications.length > 0 ? classifications.join(', ') : 'None';

                body.innerHTML = `
                    ${photoHtml}
                    <div class="view-name">${fullName}</div>
                    <div class="view-hh"><i class="fa-solid fa-house" style="margin-right:4px;"></i> Household No. ${data.household_no || 'N/A'}</div>
                    <div class="view-info-grid">
                        <div class="view-info-item"><span class="view-info-label">Last Name</span><span class="view-info-value">${data.last_name || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">First Name</span><span class="view-info-value">${data.first_name || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Middle Name</span><span class="view-info-value">${data.middle_name || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Suffix Name</span><span class="view-info-value">${suffix}</span></div>
                        
                        <div class="view-info-item"><span class="view-info-label">Gender</span><span class="view-info-value">${data.gender || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Date of Birth</span><span class="view-info-value">${data.dob || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Age</span><span class="view-info-value">${data.age || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Relationship to Head</span><span class="view-info-value">${data.relationship || '—'}</span></div>
                        
                        <div class="view-info-item"><span class="view-info-label">Civil Status</span><span class="view-info-value">${data.civil_status || '—'}</span></div>
                        <div class="view-info-item"><span class="view-info-label">Employment Status</span><span class="view-info-value">${data.employment_status || '—'}</span></div>
                        <div class="view-info-item" style="grid-column: span 2;"><span class="view-info-label">Educational Attainment</span><span class="view-info-value">${data.education || '—'}</span></div>
                        
                        <div class="view-info-item" style="grid-column: span 2;"><span class="view-info-label">Special Classifications</span><span class="view-info-value">${classStr}</span></div>
                    </div>
                `;
            })
            .catch(() => { body.innerHTML = '<div style="text-align:center; color:#ef4444; padding:20px;">Error loading resident data.</div>'; });
    }

    function closeViewModal() {
        document.getElementById('viewResidentModal').classList.remove('show');
        document.body.classList.remove('modal-open');
    }

</script>
</body>
</html>

















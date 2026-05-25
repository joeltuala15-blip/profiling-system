<?php 
include('db.php');
include_once('toast_helpers.php');
session_start();

$allowed_roles = ['Captain', 'Barangay Captain', 'Admin', 'Secretary'];
if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$page_toasts = [];

if (isset($_POST['ajax_mark_received'])) {
    $activity_id = (int)$_POST['activity_id'];
    $resident_id = (int)($_POST['resident_id'] ?? 0);
    $household_no = $_POST['household_no'] ?? '';

    if ($activity_id > 0) {
        if ($household_no !== '') {
            $safe_hh = mysqli_real_escape_string($conn, $household_no);
            mysqli_query($conn, "UPDATE activity_participants SET status = 'Received', received_at = NOW() WHERE activity_id = '$activity_id' AND household_no = '$safe_hh'");
            $action_desc = mysqli_real_escape_string($conn, "Marked household #$safe_hh as received for activity #$activity_id");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
        } elseif ($resident_id > 0) {
            $check = mysqli_query($conn, "SELECT id, status FROM activity_participants WHERE activity_id = '$activity_id' AND resident_id = '$resident_id' LIMIT 1");
            $row = $check ? mysqli_fetch_assoc($check) : null;

            if ($row) {
                if ($row['status'] !== 'Received') {
                    $participant_id = (int)$row['id'];
                    mysqli_query($conn, "UPDATE activity_participants SET status = 'Received', received_at = NOW() WHERE id = '$participant_id'");
                }
            } else {
                $hh_res = mysqli_query($conn, "SELECT household_no FROM residents WHERE id = '$resident_id' LIMIT 1");
                $hh_row = $hh_res ? mysqli_fetch_assoc($hh_res) : null;
                $household_no_res = $hh_row['household_no'] ?? null;

                if ($household_no_res === null || $household_no_res === '') {
                    $insert_sql = "INSERT INTO activity_participants (activity_id, resident_id, status) VALUES ('$activity_id', '$resident_id', 'Received')";
                } else {
                    $safe_hh_res = mysqli_real_escape_string($conn, $household_no_res);
                    $insert_sql = "INSERT INTO activity_participants (activity_id, resident_id, household_no, status) VALUES ('$activity_id', '$resident_id', '$safe_hh_res', 'Received')";
                }
                mysqli_query($conn, $insert_sql);
            }

            $action_desc = mysqli_real_escape_string($conn, "Marked resident #$resident_id as received for activity #$activity_id");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
        }
    }
    echo json_encode(['success' => true]);
    exit();
}


if (isset($_POST['ajax_archive_activity'])) {
    $activity_id = (int)$_POST['activity_id'];
    if ($activity_id > 0) {
        mysqli_query($conn, "UPDATE activities SET is_archived = 1, archived_at = NOW() WHERE id = '$activity_id'");
        
        $action_desc = mysqli_real_escape_string($conn, "Archived activity #$activity_id");
        mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
    }
    echo json_encode(['success' => true]);
    exit();
}


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
                'suffix_name' => $res['suffix_name'] ?? '',
                'gender' => $res['gender'] ?? '',
                'dob' => $res['dob'] ?? '',
                'age' => $res['age'] ?? '',
                'civil_status' => $res['civil_status'] ?? '',
                'relationship' => $res['relationship'] ?? '',
                'employment_status' => $res['employment_status'] ?? '',
                'education' => $res['education'] ?? '',
                'household_no' => $res['household_no'] ?? 'N/A',
                'status' => $res['status'] ?? 'Active',
                'is_voter' => $res['is_voter'] ?? 0,
                'is_pwd' => $res['is_pwd'] ?? 0,
                'is_solo' => $res['is_solo'] ?? 0,
                'is_senior' => $res['is_senior'] ?? 0,
                'is_minor' => $res['is_minor'] ?? 0,
                'is_4ps' => $res['is_4ps'] ?? 0
            ]);
        } else {
            echo json_encode(['error' => 'Resident not found']);
        }
    } else {
        echo json_encode(['error' => 'Invalid ID']);
    }
    exit();
}

if (isset($_GET['ajax_fetch_participants'])) {
    $activity_id = (int)$_GET['activity_id'];
    $act_query = mysqli_query($conn, "SELECT activity_name FROM activities WHERE id = '$activity_id' LIMIT 1");
    $act_title = $act_query ? mysqli_fetch_assoc($act_query)['activity_name'] : '';
    $is_4ps_act = (strtolower(trim($act_title)) === '4ps beneficiary');

    if ($is_4ps_act) {
        $sql = "SELECT ap.household_no, min(ap.status) as status, min(ap.id) as participant_id,
                       GROUP_CONCAT(CONCAT(r.last_name, ', ', r.first_name) SEPARATOR '; ') as members
                FROM activity_participants ap
                JOIN residents r ON ap.resident_id = r.id
                WHERE ap.activity_id = '$activity_id'
                GROUP BY ap.household_no
                ORDER BY ap.household_no ASC";
        $participants = mysqli_query($conn, $sql);
        $data = [];
        while($row = mysqli_fetch_assoc($participants)) {
            $data[] = [
                'is_hh_view' => true,
                'household_no' => $row['household_no'],
                'members' => $row['members'],
                'status' => $row['status'],
                'participant_id' => $row['participant_id'],
                'activity_id' => $activity_id
            ];
        }
        echo json_encode($data);
        exit();
    } else {
        $sql = "SELECT ap.id AS participant_id, ap.status,
                   r.id AS resident_id, r.last_name, r.first_name, r.middle_name,
                   r.household_no, r.purok
            FROM activity_participants ap
            JOIN residents r ON ap.resident_id = r.id
            WHERE ap.activity_id = '$activity_id'
            ORDER BY r.last_name ASC, r.first_name ASC";
        $participants = mysqli_query($conn, $sql);
        $data = [];
        while($row = mysqli_fetch_assoc($participants)) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
}


$query = "SELECT a.*, 
          (SELECT COUNT(*) FROM activity_participants ap WHERE ap.activity_id = a.id) as beneficiary_count 
          FROM activities a WHERE COALESCE(a.is_archived, 0) = 0 ORDER BY a.id DESC";
$result = mysqli_query($conn, $query);

$user_role = $_SESSION['role'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities - Profiling System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-navy: #1e293b; 
            --accent-blue: #2563eb; 
            --accent-green: #10b981;
            --logo-orange: #ff9800; 
            --text-gray: #64748b; 
        }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }
        .sidebar { width: 280px; background: var(--sidebar-navy); color: white; display: flex; flex-direction: column; position: relative; flex-shrink: 0; transition: width 0.3s ease; overflow: hidden; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 15px 15px; display: flex; align-items: center; height: 70px; }
        .brand-group { display: flex; align-items: center;  }
        .brand-logo-container { border: 3px solid var(--logo-orange); border-radius: 14px; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .brand-logo-container i { color: var(--logo-orange); font-size: 30px; }
        .brand-text { margin-left: 15px; white-space: nowrap; }
        .brand-text b { display: block; font-size: 20px; color: white; }
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
        .user-profile-container { position: relative; }
        .user-pill { display: flex; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; }
        .avatar { background: var(--accent-blue); color: white; width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .logout-dropdown { position: absolute; top: 110%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 8px; width: 200px; display: none; z-index: 100; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        .logout-dropdown.show { display: block; }
        .dropdown-header { padding: 12px; text-align: center; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 13px; }
        .dropdown-header b { display: block; color: #1e293b; margin-top: 2px; font-size: 14px; }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; color: #ef4444; text-decoration: none; font-weight: 600; font-size: 14px; }
        .logout-btn:hover { background: #fef2f2; }
        .panel { background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; color: var(--text-gray); font-size: 12px; letter-spacing: 0.05em; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: var(--text-gray); display: inline-flex; align-items: center; gap: 5px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .btn-add { background: var(--accent-blue); color: white; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
        .action-link { text-decoration: none; font-weight: 600; font-size: 13px; margin-right: 15px; display: inline-flex; align-items: center; gap: 4px; }
        .link-blue { color: var(--accent-blue); }
        .link-green { color: var(--accent-green); }
        .link-red { color: #ef4444; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 12px; width: 600px; max-width: 90%; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h3 { margin: 0; color: #1e293b; font-size: 18px; font-weight: 700; }
        .modal-close { background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; }
        .modal-close:hover { background: #e2e8f0; color: #1e293b; }
        .modal-body { padding: 24px; overflow-y: auto; flex-grow: 1; }
        .modal-confirm { width: 450px; }
        .confirm-icon { width: 56px; height: 56px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px; }
        .confirm-title { text-align: center; font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .confirm-text { text-align: center; color: #64748b; line-height: 1.5; margin-bottom: 24px; font-size: 14px; }
        .confirm-actions { display: flex; gap: 12px; justify-content: center; }
        .btn-cancel { padding: 10px 18px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
        .btn-delete-confirm { padding: 10px 18px; border-radius: 8px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer; }
        .btn-delete-confirm:hover { background: #dc2626; }
        .modal-table { width: 100%; border-collapse: collapse; }
        .modal-table th { text-align: left; padding: 10px; border-bottom: 2px solid #e2e8f0; color: var(--text-gray); font-size: 12px; }
        .modal-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .status-badge { background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .pending { background: #fee2e2; color: #991b1b; }
        .btn-mark { background: var(--accent-blue); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; }
        .btn-mark:hover { background: var(--accent-blue-hover); }
        .modal-empty { text-align: center; color: var(--text-gray); padding: 16px; font-size: 14px; }
        
        table th:nth-child(4), table td:nth-child(4) { text-align: center; }

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

        @media (max-width: 768px) {
            .main-container { min-width: 0; }
            .top-header { align-items: flex-start; flex-direction: column; gap: 16px; padding: 15px 20px; }
            .panel { padding: 14px; border-radius: 16px; overflow-x: auto; }
            
            table { min-width: 600px; }
            
            .modal-content { width: 95%; max-height: 90vh; }
            .modal-confirm { width: 95%; }
            .btn-add { display: flex; width: 100%; justify-content: center; margin-top: 10px; box-sizing: border-box; }
            
            .content-body .panel > div:first-child { flex-direction: column; align-items: stretch; gap: 10px; }
            .action-link { margin-right: 5px; margin-bottom: 5px; display: inline-flex; }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0; color: #1e293b;">Barangay Activities</h2>
            <p style="margin:0; color: var(--text-gray);">Manage programs and track assistance distribution</p>
        </div>

        <div class="user-profile-container">
            <div class="user-pill" onclick="toggleLogout()">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div style="line-height: 1.2; margin-left: 15px; margin-right: 15px;">
                    <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($user_role); ?></div>
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
            <div style="display:flex; justify-content: space-between; align-items:center;">
                <h3 style="margin:0; color: #1e293b;">Activity Monitoring</h3>
                <a href="add_activity.php" class="btn-add"><i class="fa-solid fa-plus"></i> New Activity</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Activity Details</th>
                        <th>Date</th>
                        <th>Beneficiaries</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <strong style="display:block; color: #1e293b;"><?php echo htmlspecialchars($row['activity_name'] ?? $row['title'] ?? 'N/A'); ?></strong>
                                <small style="color: var(--text-gray);"><?php echo htmlspecialchars($row['description'] ?? 'No description provided'); ?></small>
                            </td>
                            
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fa-regular fa-calendar" style="color: var(--accent-blue);"></i>
                                    <?php 
                                        $date_val = $row['activity_date'] ?? $row['date'] ?? null;
                                        echo ($date_val) ? date('M d, Y', strtotime($date_val)) : 'TBA';
                                    ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo ($row['beneficiary_count'] > 0) ? 'badge-success' : ''; ?>" style="cursor: pointer;" onclick="openActivityModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['activity_name'] ?? $row['title'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars(addslashes($row['description'] ?? '')); ?>', '<?php $d = $row['activity_date'] ?? $row['date'] ?? null; echo $d ? date('M d, Y', strtotime($d)) : ''; ?>')">
                                    <i class="fa-solid fa-people-group"></i> <?php echo $row['beneficiary_count']; ?> Given
                                </span>
                            </td>
                            
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 6px;">
                                    <a href="#" class="action-link link-blue" onclick="event.stopPropagation(); openActivityModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['activity_name'] ?? $row['title'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars(addslashes($row['description'] ?? '')); ?>', '<?php $d = $row['activity_date'] ?? $row['date'] ?? null; echo $d ? date('M d, Y', strtotime($d)) : ''; ?>');">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                    <a href="edit_activity.php?id=<?php echo $row['id']; ?>" class="action-link link-blue" onclick="event.stopPropagation();">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>
                                    <a href="#" class="action-link link-red" onclick="event.stopPropagation(); openArchiveModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['activity_name'] ?? 'this activity')); ?>');">
                                        <i class="fa-solid fa-check"></i> Mark Done
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 50px; color: var(--text-gray);">No activities found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="activityModal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Activity Beneficiaries</h3>
                <div style="color: #1e293b; font-weight: 600; margin-top: 4px; font-size: 14px;" id="modalActivityTitle">Loading...</div>
                <div style="color: var(--text-gray); font-size: 13px; margin-top: 2px;" id="modalActivityDesc"></div>
                <div style="color: var(--text-gray); font-size: 12px; margin-top: 4px;" id="modalActivityDate"></div>
            </div>
            <button class="modal-close" onclick="closeActivityModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="modal-empty">Loading participants...</div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="archiveModal">
    <div class="modal-content modal-confirm">
        <div class="modal-body">
            <div class="confirm-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="confirm-title">Mark Activity as Done?</div>
            <div class="confirm-text">
                Are you sure you want to mark <strong id="archiveActivityName" style="color: #1e293b;">this activity</strong> as done?<br>
                <span style="font-size: 13px; margin-top: 8px; display: block; color: #64748b;">This will move it to Archived Activities.</span>
            </div>
            <div class="confirm-actions">
                <button class="btn-cancel" onclick="closeArchiveModal()">Cancel</button>
                <button class="btn-delete-confirm" id="confirmArchiveBtn">Yes, Mark Done</button>
            </div>
        </div>
    </div>
</div>

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
    let currentActivityId = 0;
    let activityIdToArchive = 0;

    function openActivityModal(id, title, desc, date) {
        currentActivityId = id;
        const modal = document.getElementById('activityModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        document.getElementById('modalActivityTitle').textContent = 'Tracking for: ' + title;
        
        const descElem = document.getElementById('modalActivityDesc');
        if (descElem) {
            descElem.textContent = desc ? desc : 'No description available';
        }
        
        const dateElem = document.getElementById('modalActivityDate');
        if (dateElem) {
            dateElem.innerHTML = date ? '<i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> ' + date : '';
        }

        fetchParticipants(id);
    }

    function closeActivityModal() {
        const modal = document.getElementById('activityModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            currentActivityId = 0;
            location.reload();
        }, 200);
    }

    function openArchiveModal(id, name) {
        activityIdToArchive = id;
        document.getElementById('archiveActivityName').textContent = '"' + name + '"';
        const modal = document.getElementById('archiveModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        
        document.getElementById('confirmArchiveBtn').onclick = function() {
            confirmArchiveActivity(id);
        };
    }

    function closeArchiveModal() {
        const modal = document.getElementById('archiveModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            activityIdToArchive = 0;
        }, 200);
    }

    function fetchParticipants(id) {
        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = '<div class="modal-empty">Loading participants...</div>';

        fetch('activities.php?ajax_fetch_participants=1&activity_id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    modalBody.innerHTML = '<div class="modal-empty">No beneficiaries found for this activity.</div>';
                    return;
                }

                if (data[0] && data[0].is_hh_view) {
                    let html = '<table class="modal-table"><thead><tr><th>Household No.</th><th>Household Members</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                    data.forEach(p => {
                        const statusClass = p.status === 'Received' ? 'status-badge' : 'status-badge pending';
                        const statusText = p.status === 'Received' ? 'RECEIVED' : 'PENDING';
                        
                        html += `<tr>
                            <td><strong>Household #${p.household_no || 'N/A'}</strong></td>
                            <td><small style="color: #64748b;">${p.members}</small></td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td>`;
                        
                        if (p.status !== 'Received') {
                            html += `<button class="btn-mark" onclick="markReceivedHH(${p.activity_id}, '${p.household_no}')">Mark Received</button>`;
                        } else {
                            html += `<span style="color: #166534; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-check"></i> Done</span>`;
                        }

                        html += `</td></tr>`;
                    });
                    html += '</tbody></table>';
                    modalBody.innerHTML = html;
                } else {
                    let html = '<table class="modal-table"><thead><tr><th>Resident</th><th>Household No.</th><th>Status</th><th style="width: 50px;">View</th><th>Action</th></tr></thead><tbody>';
                    data.forEach(p => {
                        const fullName = `${p.last_name}, ${p.first_name} ${p.middle_name || ''}`.trim();
                        const statusClass = p.status === 'Received' ? 'status-badge' : 'status-badge pending';
                        const statusText = p.status === 'Received' ? 'RECEIVED' : 'PENDING';
                        
                        html += `<tr>
                            <td><strong>${fullName}</strong></td>
                            <td>${p.household_no || 'N/A'}</td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-view-resident" onclick="viewResident(${p.resident_id})" title="View resident info">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                            <td>`;
                        
                        if (p.status !== 'Received') {
                            html += `<button class="btn-mark" onclick="markReceived(${p.resident_id})">Mark Received</button>`;
                        } else {
                            html += `<span style="color: #166534; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-check"></i> Done</span>`;
                        }

                        html += `</td></tr>`;
                    });
                    html += '</tbody></table>';
                    modalBody.innerHTML = html;
                }
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="modal-empty">Error loading participants.</div>';
                console.error(err);
            });
    }

    function markReceived(residentId) {
        if(!currentActivityId) return;

        const formData = new FormData();
        formData.append('ajax_mark_received', '1');
        formData.append('activity_id', currentActivityId);
        formData.append('resident_id', residentId);

        fetch('activities.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                fetchParticipants(currentActivityId);
            }
        })
        .catch(err => console.error(err));
    }

    function markReceivedHH(activityId, householdNo) {
        const formData = new FormData();
        formData.append('ajax_mark_received', '1');
        formData.append('activity_id', activityId);
        formData.append('household_no', householdNo);

        fetch('activities.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                fetchParticipants(activityId);
            }
        })
        .catch(err => console.error(err));
    }
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
    function confirmArchiveActivity(activityId) {
        const btn = document.getElementById('confirmArchiveBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Marking...';

        const formData = new FormData();
        formData.append('ajax_archive_activity', '1');
        formData.append('activity_id', activityId);

        fetch('activities.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAppToast('Error archiving activity.', 'error', 'Action Needed');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            showAppToast('Error archiving activity.', 'error', 'Action Needed');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function viewResident(residentId) {
        const modal = document.getElementById('viewResidentModal');
        const body = document.getElementById('viewModalBody');
        modal.classList.add('show');
        body.innerHTML = '<div style="text-align:center; color: #64748b; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

        fetch('activities.php?ajax_view_resident=1&resident_id=' + residentId)
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
    }
</script>
</body>











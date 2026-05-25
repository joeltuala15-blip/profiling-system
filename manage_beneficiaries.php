<?php
include('db.php');
session_start();

$allowed_roles = ['Captain', 'Barangay Captain', 'Admin', 'Secretary'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: login.php");
    exit();
}

$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($activity_id <= 0) {
    header("Location: activities.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'User';

if (isset($_POST['mark_received'])) {
    $resident_id = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0;
    $household_no = $_POST['household_no'] ?? '';

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

    header("Location: manage_beneficiaries.php?id=$activity_id");
    exit();
}

$act_query = mysqli_query($conn, "SELECT activity_name, activity_date FROM activities WHERE id = '$activity_id' LIMIT 1");
$act_data = $act_query ? mysqli_fetch_assoc($act_query) : null;
if (!$act_data) {
    header("Location: activities.php");
    exit();
}

$is_4ps_act = (strtolower(trim($act_data['activity_name'])) === '4ps beneficiary');

if ($is_4ps_act) {
    $sql = "SELECT ap.household_no, min(ap.status) as status, min(ap.received_at) as received_at,
                   GROUP_CONCAT(CONCAT(r.last_name, ', ', r.first_name) SEPARATOR '; ') as members
            FROM activity_participants ap
            JOIN residents r ON ap.resident_id = r.id
            WHERE ap.activity_id = '$activity_id'
            GROUP BY ap.household_no
            ORDER BY ap.household_no ASC";
    $participants = mysqli_query($conn, $sql);
} else {
    $sql = "SELECT ap.id AS participant_id, ap.status, ap.received_at,
                   r.id AS resident_id, r.last_name, r.first_name, r.middle_name,
                   r.household_no, r.purok
            FROM activity_participants ap
            JOIN residents r ON ap.resident_id = r.id
            WHERE ap.activity_id = '$activity_id'
            ORDER BY r.last_name ASC, r.first_name ASC";
    $participants = mysqli_query($conn, $sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Beneficiaries</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-navy: #1e293b; --accent-blue: #2563eb; --logo-orange: #ff9800; --text-gray: #64748b; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

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

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #e5e7eb; color: var(--text-gray); font-size: 11px; text- }
        td { padding: 15px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }

        .status-badge { background: #e8f5e9; color: #2e7d32; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; border: none; }
        .received { background: #dcfce7; color: #166534; }
        .pending { background: #fee2e2; color: #991b1b; }

        .btn-mark { background: var(--accent-blue); color: white; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 800; cursor: pointer; }
        .btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }

        .user-profile-container { position: relative; }
        .user-pill { display: flex; align-items: center; background: #f8fafc; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; }
        .avatar { background: var(--accent-blue); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logout-dropdown { position: absolute; top: 110%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; width: 220px;  display: none; z-index: 100; overflow: hidden; }
        .logout-dropdown.show { display: block; }
        .dropdown-header { padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb; color: #64748b; font-size: 14px; }
        .dropdown-header b { display: block; color: #1e293b; margin-top: 4px; font-size: 16px; }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px; color: #ef4444; text-decoration: none; font-weight: 600; font-size: 16px; }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Activity Beneficiaries</h2>
            <p style="margin:0; color: var(--text-gray);">Tracking for: <b><?php echo htmlspecialchars($act_data['activity_name']); ?></b></p>
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
            <table>
                <?php if ($is_4ps_act): ?>
                    <thead>
                        <tr>
                            <th>Household No.</th>
                            <th>Household Members</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($participants && mysqli_num_rows($participants) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($participants)): ?>
                            <tr>
                                <td><b>Household #<?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></b></td>
                                <td><small style="color: #64748b;"><?php echo htmlspecialchars($row['members']); ?></small></td>
                                <td>
                                    <?php if($row['status'] === 'Received'): ?>
                                        <span class="status-badge received">RECEIVED</span>
                                    <?php else: ?>
                                        <span class="status-badge pending">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] !== 'Received'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="household_no" value="<?php echo htmlspecialchars($row['household_no']); ?>">
                                            <button type="submit" name="mark_received" class="btn-mark">Mark Received</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-mark btn-disabled" disabled>Saved</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 50px; color: var(--text-gray);">No beneficiaries assigned to this activity.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php else: ?>
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Household No.</th>
                            <th>Purok</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($participants && mysqli_num_rows($participants) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($participants)): ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($row['last_name'] . ", " . $row['first_name'] . ($row['middle_name'] ? " " . $row['middle_name'] : "")); ?></b></td>
                                <td><?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['purok'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if($row['status'] === 'Received'): ?>
                                        <span class="status-badge received">RECEIVED</span>
                                    <?php else: ?>
                                        <span class="status-badge pending">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] !== 'Received'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="resident_id" value="<?php echo (int)$row['resident_id']; ?>">
                                            <button type="submit" name="mark_received" class="btn-mark">Mark Received</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-mark btn-disabled" disabled>Saved</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 50px; color: var(--text-gray);">No beneficiaries assigned to this activity.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>


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
</script>
</body>
</html>

















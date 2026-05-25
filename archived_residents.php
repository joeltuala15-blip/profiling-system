<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

$allowed_roles = ['Barangay Captain', 'Captain', 'Secretary'];
if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: login.php");
    exit();
}

$page_toasts = [];
if (!empty($_SESSION['toast_error'])) {
    $page_toasts[] = app_toast_from_message($_SESSION['toast_error']);
    unset($_SESSION['toast_error']);
}

$proof_col_exists = false;
$proof_col_check = mysqli_query($conn, "SHOW COLUMNS FROM residents LIKE 'deceased_proof_path'");
if ($proof_col_check && mysqli_num_rows($proof_col_check) > 0) {
    $proof_col_exists = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $restore_id = (int)$_POST['restore_id'];

    $resident_query = mysqli_query($conn, "SELECT id, status, archive_reason FROM residents WHERE id = '$restore_id' AND COALESCE(is_archived, 0) = 1 LIMIT 1");
    $resident = $resident_query ? mysqli_fetch_assoc($resident_query) : null;

    if ($resident) {
        $status = strtolower(trim((string)($resident['status'] ?? '')));
        $archive_reason = strtolower(trim((string)($resident['archive_reason'] ?? '')));

        if ($status === 'deceased' || $archive_reason === 'deceased') {
            $_SESSION['toast_error'] = 'Residents archived as Deceased cannot be restored.';
            header("Location: archived_residents.php");
            exit();
        }

        $update = "UPDATE residents SET is_archived = 0, archive_reason = NULL, status = 'Active' WHERE id = '$restore_id'";
        if (mysqli_query($conn, $update)) {
            $action_desc = mysqli_real_escape_string($conn, "Restored resident #$restore_id from archive");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
        }
    }

    header("Location: archived_residents.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_proof_id']) && $proof_col_exists) {
    $remove_id = (int)$_POST['remove_proof_id'];
    $update = "UPDATE residents SET deceased_proof_path = NULL WHERE id = '$remove_id'";
    if (mysqli_query($conn, $update)) {
        $action_desc = mysqli_real_escape_string($conn, "Removed deceased proof for resident #$remove_id");
        mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");
    }
    header("Location: archived_residents.php");
    exit();
}

$where = ["COALESCE(is_archived, 0) = 1"];
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where[] = "(first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR middle_name LIKE '%$search%' OR household_no LIKE '%$search%')";
}

$where_sql = implode(" AND ", $where);
$query = "SELECT * FROM residents WHERE $where_sql ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Residents - Profiling System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-navy: #1e293b;
            --accent-blue: #2563eb;
            --logo-orange: #ff9800;
            --text-gray: #64748b;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; box-sizing: border-box; width: 100%; }
        .top-header { background: #ffffff; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }

        .user-profile-container { position: relative; }
        .user-pill { display: flex; align-items: center; background: #f8fafc; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; }
        .avatar { background: var(--accent-blue); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logout-dropdown { position: absolute; top: 110%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; width: 220px; display: none; z-index: 100; overflow: hidden; }
        .logout-dropdown.show { display: block; }
        .dropdown-header { padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb; color: #64748b; font-size: 14px; }
        .dropdown-header b { display: block; color: #1e293b; margin-top: 4px; font-size: 16px; }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px; color: #ef4444; text-decoration: none; font-weight: 600; font-size: 16px; }

        .content-body { padding: 16px 20px 20px; }
        .panel { background: white; padding: 18px; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; }
        .controls { display: flex; justify-content: space-between; margin-bottom: 25px; align-items: center; }
        .search-input { padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 15px; outline: none; width: 350px; }
        .search-input:focus { border-color: var(--accent-blue); }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 18px 15px; border-bottom: 2px solid #e5e7eb; font-size: 12px; color: var(--text-gray); letter-spacing: 0.5px; text-transform: uppercase; }
        td { padding: 18px 15px; border-bottom: 1px solid #e5e7eb; font-size: 15px; color: #334155; }

        .reason-badge { padding: 6px 14px; border-radius: 20px; background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 600; }

        .btn-restore { background: var(--accent-blue); color: white; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .restore-locked { background: #e2e8f0; color: #64748b; border: none; padding: 10px 14px; border-radius: 12px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .proof-link { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border-radius: 10px; background: #eff6ff; color: #1d4ed8; font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .proof-empty { color: #94a3b8; font-size: 13px; font-style: italic; white-space: nowrap; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-gray); }
        .empty-state i { font-size: 48px; margin-bottom: 4px; color: #cbd5e1; display: block; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 24px; width: 450px; max-width: 90%; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.95); transition: transform 0.2s ease-out; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-body { padding: 32px; text-align: center; }
        
        .confirm-icon { width: 64px; height: 64px; background: #dcfce7; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 20px; }
        .confirm-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
        .confirm-text { color: #64748b; line-height: 1.6; margin-bottom: 30px; font-size: 15px; }
        .confirm-actions { display: flex; gap: 12px; justify-content: center; }
        .btn-cancel { padding: 12px 24px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-cancel:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
        .btn-confirm-restore { padding: 12px 24px; border-radius: 12px; border: none; background: var(--accent-blue); color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(130, 78, 57, 0.2); }
        .btn-confirm-restore:hover { background: var(--accent-blue-hover); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(130, 78, 57, 0.3); }

        @media (max-width: 768px) {
            .main-container { min-width: 0; }
            .top-header { align-items: flex-start; }
            .panel { padding: 14px; border-radius: 16px; overflow-x: visible; }
            .controls { display: block; margin-bottom: 16px; }
            .controls form { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }

            .archive-table,
            .archive-table tbody,
            .archive-table tr,
            .archive-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            .archive-table thead { display: none; }
            .archive-table tbody { display: flex; flex-direction: column; gap: 12px; }
            .archive-table tr.data-row {
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                background: #ffffff;
                overflow: hidden;
            }
            .archive-table td {
                display: grid;
                grid-template-columns: minmax(110px, 38%) 1fr;
                gap: 12px;
                align-items: center;
                padding: 12px 14px;
                border-bottom: 1px solid #f1f5f9;
                font-size: 14px;
                overflow-wrap: anywhere;
            }
            .archive-table tr.data-row td:last-child { border-bottom: none; }
            .archive-table td::before {
                content: attr(data-label);
                color: #64748b;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
            }
            .archive-table tr.empty-row { display: block; }
            .archive-table tr.empty-row td {
                display: block;
                padding: 0;
                border: 0;
            }
            .archive-table tr.empty-row td::before { display: none; }
            .btn-restore,
            .restore-locked,
            .proof-link {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
            .modal-content { width: calc(100vw - 32px); border-radius: 18px; }
            .modal-body { padding: 24px 18px; }
            .confirm-actions { flex-direction: column; }
            .confirm-actions button { width: 100%; }
        }

        @media (max-width: 480px) {
            .archive-table td {
                grid-template-columns: 1fr;
                gap: 6px;
                align-items: start;
            }
            .empty-state { padding: 36px 14px; }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Archived Residents</h2>
            <p style="margin:0; color: var(--text-gray);">View and restore archived resident records</p>
        </div>

        <div class="user-profile-container">
            <div class="user-pill" onclick="toggleLogout()">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div style="line-height: 1.2; margin-left: 15px; margin-right: 15px;">
                    <div style="font-weight: 600; font-size: 15px;">Secretary</div>
                    <div style="color:#64748b; font-size: 13px;">Barangay Secretary</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #94a3b8;"></i>
            </div>

            <div class="logout-dropdown" id="logoutDropdown">
                <div class="dropdown-header">Signed in as<br><b>Secretary</b></div>
                <a href="logout.php" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <div class="controls">
                <form method="GET" style="display:flex; gap:12px; align-items: center;">
                    <input type="text" id="archiveSearch" name="search" class="search-input" placeholder="Search archived resident name or household no..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </form>
            </div>

            <table id="archiveTable" class="archive-table">
                <thead>
                    <tr>
                        <th>Resident Name</th>
                        <th>Household No.</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <?php if ($proof_col_exists): ?>
                            <th>Deceased Proof</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $has_rows = $result && mysqli_num_rows($result) > 0; ?>
                    <?php if($has_rows): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                            $row_status = strtolower(trim((string)($row['status'] ?? '')));
                            $row_archive_reason = strtolower(trim((string)($row['archive_reason'] ?? '')));
                            $is_deceased_archive = $row_status === 'deceased' || $row_archive_reason === 'deceased';
                        ?>
                        <tr class="data-row">
                            <td data-label="Resident Name"><strong><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ($row['middle_name'] ? ' ' . $row['middle_name'] : '')); ?></strong></td>
                            <td data-label="Household No."><?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></td>
                            <td data-label="Age"><?php echo htmlspecialchars($row['age'] ?? 'N/A'); ?></td>
                            <td data-label="Gender"><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></td>
                            <td data-label="Status"><span class="reason-badge"><?php echo htmlspecialchars($row['status'] ?? 'N/A'); ?></span></td>
                             <td data-label="Reason"><?php echo htmlspecialchars($row['archive_reason'] ?? 'N/A'); ?></td>
                             <?php if ($proof_col_exists): ?>
                                 <td data-label="Deceased Proof">
                                     <?php
                                         $proof_path = trim((string)($row['deceased_proof_path'] ?? ''));
                                         $has_deceased_proof = $proof_path !== '' && (($row['status'] ?? '') === 'Deceased' || ($row['archive_reason'] ?? '') === 'Deceased');
                                     ?>
                                     <?php if ($has_deceased_proof): ?>
                                         <div style="display: inline-flex; align-items: center; gap: 6px;">
                                             <a class="proof-link" href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener noreferrer">
                                                 <i class="fa-solid fa-file-lines"></i> View Proof
                                             </a>
                                             <button type="button" class="proof-x-btn" onclick="openRemoveProofModal(<?php echo (int)$row['id']; ?>)" title="Dismiss proof" style="background: #fee2e2; border: none; color: #ef4444; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; transition: all 0.15s;">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                         </div>
                                     <?php else: ?>
                                         <span class="proof-empty">No file</span>
                                     <?php endif; ?>
                                 </td>
                             <?php endif; ?>
                             <td data-label="Actions">
                                 <?php if ($_SESSION['role'] === 'Secretary'): ?>
                                     <?php if ($is_deceased_archive): ?>
                                         <span class="restore-locked" title="Deceased resident records cannot be restored.">
                                             <i class="fa-solid fa-lock"></i> Cannot Restore
                                         </span>
                                     <?php else: ?>
                                         <button type="button" class="btn-restore" onclick="openRestoreModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['last_name'] . ', ' . $row['first_name'])); ?>')">
                                             <i class="fa-solid fa-rotate-left"></i> Restore
                                         </button>
                                     <?php endif; ?>
                                 <?php else: ?>
                                     <span style="color: #94a3b8; font-size: 13px; font-style: italic;"><i class="fa-solid fa-lock"></i> View Only</span>
                                 <?php endif; ?>
                             </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="archiveNoResults" class="empty-row" style="<?php echo $has_rows ? 'display: none;' : ''; ?>">
                        <td colspan="<?php echo $proof_col_exists ? 8 : 7; ?>">
                            <div class="empty-state">
                                <i class="fa-solid fa-box-archive"></i>
                                No archived residents found.
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal-overlay" id="restoreModal">
    <div class="modal-content">
        <div class="modal-body">
            <div class="confirm-icon">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="confirm-title">Restore Resident?</div>
            <div class="confirm-text">
                Are you sure you want to restore <strong id="restoreResidentName" style="color: #1e293b;">this resident</strong>?<br>
                This will move them back to the active household list.
            </div>
            <form method="POST" id="restoreForm">
                <input type="hidden" name="restore_id" id="restoreIdInput">
                <div class="confirm-actions">
                    <button type="button" class="btn-cancel" onclick="closeRestoreModal()">Cancel</button>
                    <button type="submit" class="btn-confirm-restore">Yes, Restore Resident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="removeProofModal">
    <div class="modal-content">
        <div class="modal-body">
            <div class="confirm-icon" style="background: #fee2e2; color: #ef4444;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="confirm-title">Remove Deceased Proof?</div>
            <div class="confirm-text">
                Are you sure you want to remove this proof document?<br>
                This action cannot be undone.
            </div>
            <form method="POST" id="removeProofForm">
                <input type="hidden" name="remove_proof_id" id="removeProofIdInput">
                <div class="confirm-actions">
                    <button type="button" class="btn-cancel" onclick="closeRemoveProofModal()">Cancel</button>
                    <button type="submit" class="btn-confirm-restore" style="background: #ef4444; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);">Yes, Remove Proof</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setupArchiveSearch();
    });

    function toggleLogout() {
        document.getElementById('logoutDropdown').classList.toggle('show');
    }

    window.onclick = function(e) {
        if (!e.target.closest('.user-profile-container')) {
            const dropdown = document.getElementById('logoutDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }

    function openRestoreModal(id, name) {
        document.getElementById('restoreIdInput').value = id;
        document.getElementById('restoreResidentName').textContent = name;
        const modal = document.getElementById('restoreModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function closeRestoreModal() {
        const modal = document.getElementById('restoreModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    window.onclick = function(event) {
        const modal = document.getElementById('restoreModal');
        const proofModal = document.getElementById('removeProofModal');
        if (event.target == modal) {
            closeRestoreModal();
        } else if (event.target == proofModal) {
            closeRemoveProofModal();
        }
    }

    function openRemoveProofModal(id) {
        document.getElementById('removeProofIdInput').value = id;
        const modal = document.getElementById('removeProofModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function closeRemoveProofModal() {
        const modal = document.getElementById('removeProofModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    function setupArchiveSearch() {
        const searchInput = document.getElementById('archiveSearch');
        if (!searchInput) return;

        const rows = Array.from(document.querySelectorAll('#archiveTable tbody tr.data-row'));
        const emptyRow = document.getElementById('archiveNoResults');

        const filterRows = () => {
            const term = searchInput.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach((row) => {
                const hay = row.textContent.toLowerCase();
                const match = hay.includes(term);
                row.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });

            if (emptyRow) {
                emptyRow.style.display = visible === 0 ? '' : 'none';
            }
        };

        searchInput.addEventListener('input', filterRows);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        filterRows();
    }
</script>
</body>
</html>

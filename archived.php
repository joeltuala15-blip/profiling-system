<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$page_toasts = [];
if (!empty($_SESSION['toast_error'])) {
    $page_toasts[] = app_toast_from_message($_SESSION['toast_error']);
    unset($_SESSION['toast_error']);
}

$household_no = $_GET['household_no'] ?? '';
$member_id = $_GET['id'] ?? '';
$safe_hh_no = mysqli_real_escape_string($conn, $household_no);

$proof_col_exists = false;
$proof_col_check = mysqli_query($conn, "SHOW COLUMNS FROM residents LIKE 'deceased_proof_path'");
if ($proof_col_check && mysqli_num_rows($proof_col_check) > 0) {
    $proof_col_exists = true;
}

$query_h = "SELECT * FROM households WHERE household_no = '$safe_hh_no'";
$result_h = mysqli_query($conn, $query_h);
$household = mysqli_fetch_assoc($result_h);

$query_m = "SELECT * FROM residents WHERE household_no = '$safe_hh_no' AND COALESCE(is_archived, 0) = 0 ORDER BY id ASC";
$result_m = mysqli_query($conn, $query_m);

$res_query = mysqli_query($conn, "SELECT first_name, last_name FROM residents WHERE id = '$member_id'");
$res_data = mysqli_fetch_assoc($res_query);
$full_name = ($res_data) ? $res_data['first_name'] . ' ' . $res_data['last_name'] : "Resident";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_archive'])) {
    $m_id = mysqli_real_escape_string($conn, $_POST['id']);
    $h_no = mysqli_real_escape_string($conn, $_POST['household_no']);
    $reason = mysqli_real_escape_string($conn, trim($_POST['archive_reason'] ?? 'Archived'));
    $proof_update_sql = '';
    $errors = [];

    if ($reason === '') {
        $reason = 'Archived';
    }

    if ($reason === 'Deceased') {
        if (!$proof_col_exists) {
            $errors[] = 'Database is missing the deceased proof column.';
        }

        $existing_proof = '';
        if ($proof_col_exists) {
            $proof_res = mysqli_query($conn, "SELECT deceased_proof_path FROM residents WHERE id = '$m_id' LIMIT 1");
            if ($proof_res) {
                $proof_row = mysqli_fetch_assoc($proof_res);
                $existing_proof = $proof_row['deceased_proof_path'] ?? '';
            }
        }

        if (isset($_FILES['deceased_proof']) && $_FILES['deceased_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['deceased_proof']['error'] === UPLOAD_ERR_OK) {
                $proof_dir = 'uploads/deceased_proofs';
                if (!is_dir($proof_dir)) {
                    mkdir($proof_dir, 0777, true);
                }

                $ext = pathinfo($_FILES['deceased_proof']['name'], PATHINFO_EXTENSION);
                $proof_filename = 'deceased_' . (int)$m_id . '_' . time();
                if ($ext !== '') {
                    $proof_filename .= '.' . $ext;
                }
                $proof_path = $proof_dir . '/' . $proof_filename;

                if (move_uploaded_file($_FILES['deceased_proof']['tmp_name'], $proof_path)) {
                    $safe_proof_path = mysqli_real_escape_string($conn, $proof_path);
                    $proof_update_sql = ", deceased_proof_path = '$safe_proof_path'";
                } else {
                    $errors[] = 'Failed to upload deceased proof file.';
                }
            } else {
                $errors[] = 'Failed to upload deceased proof file.';
            }
        } elseif ($existing_proof === '') {
            $errors[] = 'Proof file is required when reason is Deceased.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['toast_error'] = implode("\n", $errors);
        header("Location: archived.php?household_no=" . rawurlencode($h_no) . "&id=" . rawurlencode($m_id));
        exit();
    }

    $update = "UPDATE residents SET is_archived = 1, archive_reason = '$reason', status = '$reason' $proof_update_sql WHERE id = '$m_id'";
    if (mysqli_query($conn, $update)) {
        header("Location: household_members.php?household_no=" . rawurlencode($h_no) . "&success=resident_archived");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archive Resident | Profiling System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --sidebar-navy: #1e293b; --accent-blue: #2563eb; --logo-orange: #ff9800;
            --text-gray: #64748b; --border-color: #e2e8f0; --main-bg: #f1f5f9;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: var(--main-bg); overflow: hidden; }
        .sidebar { width: 300px; background: var(--sidebar-navy); color: white; display: flex; flex-direction: column; filter: blur(2px); }
        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; filter: blur(2px); }
        .content-body { padding: 30px; opacity: 0.6; }
        .hh-card, .panel { background: white; border: 1px solid #e5e7eb; padding: 18px; border-radius: 20px; border: 1px solid var(--border-color); margin-bottom: 25px; }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(30, 41, 59, 0.7); 
            display: flex; justify-content: center; align-items: center; z-index: 9999;
        }

        .archive-card {
            background: white; width: 500px; border-radius: 16px;
            
            overflow: hidden; 
        }

        @keyframes slideUp { from {  opacity: 0; } to {  opacity: 1; } }

        .card-header {
            padding: 20px 25px; border-bottom: 1px solid #e5e7eb;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b; }
        .close-x { color: #64748b; text-decoration: none; font-size: 1.5rem; }

        .card-body { padding: 25px; }
        .instruction { color: #64748b; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.5; }
        
        label { display: block; font-weight: 700; margin-bottom: 4px; font-size: 0.9rem; color: #1e293b; }
        select {
            width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 16px;
            background: #f8fafc; font-size: 1rem; margin-bottom: 20px;
        }

        .notice-box {
            background: #fffbeb; border-radius: 16px; padding: 15px;
            display: flex; gap: 12px; border: 1px solid #fef3c7;
        }
        .notice-box i { color: #d97706; margin-top: 3px; }
        .notice-text h4 { margin: 0 0 4px 0; color: #92400e; font-size: 0.9rem; }
        .notice-text p { margin: 0; color: #b45309; font-size: 0.85rem; line-height: 1.4; }

        .card-footer {
            padding: 15px 25px; background: #f8fafc;
            display: flex; justify-content: flex-end; gap: 12px;
        }

        .btn { padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
        .btn-cancel { background: white; border: 1px solid #e2e8f0; color: #1e293b; }
        .btn-confirm { background: #f97316; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px; background: #f8fafc; font-size: 11px; color: #64748b; }
        td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
    </style>
</head>
<body>

<?php render_app_toasts($page_toasts); ?>

<div class="sidebar">
    <div style="padding: 25px;"><i class="fa-solid fa-house" style="color:var(--logo-orange); font-size: 30px;"></i></div>
</div>

<div class="main-container">
    <div class="content-body">
        <div class="hh-card">
            <h3>Household #<?php echo htmlspecialchars($safe_hh_no); ?></h3>
            <p>Purok: <?php echo htmlspecialchars($household['purok'] ?? ''); ?></p>
        </div>
        <div class="panel">
            <table>
                <thead><tr><th>Name</th><th>Relation</th><th>Sex</th></tr></thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result_m)) { 
                        echo "<tr><td>{$row['last_name']}, {$row['first_name']}</td><td>{$row['relationship']}</td><td>{$row['gender']}</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay">
    <div class="archive-card">
        <div class="card-header">
            <h2>Archive Resident</h2>
            <a href="household_members.php?household_no=<?php echo $safe_hh_no; ?>" class="close-x">&times;</a>
        </div>
        
        <form action="archived.php" method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <p class="instruction">
                    Archiving <b><?php echo htmlspecialchars($full_name); ?></b> will update their status and hide this record from active household lists.
                </p>

                <label>Reason for Archiving *</label>
                <select name="archive_reason" id="archiveReason" required>
                    <option value="" disabled selected>Select a reason</option>
                    <option value="Transferred to Another Location">Transferred to Another Location</option>
                    <option value="Deceased">Deceased</option>
                    <option value="Duplicate Record">Duplicate Record</option>
                    <option value="Error data entry">Error data entry</option>
                    <option value="Other">Other</option>
                </select>

                <label>Deceased Proof (Death Certificate or Valid ID, required if Deceased)</label>
                <input type="file" name="deceased_proof" id="archiveDeceasedProof" style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:16px; background:#f8fafc; font-size:1rem; margin-bottom:20px;">

                <div class="notice-box">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div class="notice-text">
                        <h4>Archive Notice</h4>
                        <p>This resident will be hidden from the active household list. All data remains in the database.</p>
                    </div>
                </div>

                <input type="hidden" name="id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="household_no" value="<?php echo $safe_hh_no; ?>">
            </div>

            <div class="card-footer">
                <a href="household_members.php?household_no=<?php echo $safe_hh_no; ?>" class="btn btn-cancel">Cancel</a>
                <button type="submit" name="confirm_archive" class="btn btn-confirm">Archive Resident</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const reasonSelect = document.getElementById('archiveReason');
        const proofInput = document.getElementById('archiveDeceasedProof');
        if (!reasonSelect || !proofInput) return;

        const updateProofRequirement = () => {
            const isDeceased = reasonSelect.value === 'Deceased';
            proofInput.required = isDeceased;
        };

        reasonSelect.addEventListener('change', updateProofRequirement);
        updateProofRequirement();
    })();
</script>

</body>
</html>

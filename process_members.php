<?php
session_start();

$connection_file = __DIR__ . '/db.php';

if (!file_exists($connection_file)) {
    die("ERROR: The file 'db.php' was not found. Please check your folder again.");
}

include($connection_file);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['members'])) {

    if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }

    $household_no = $_POST['household_no'] ?? '';
    $safe_hh_no = mysqli_real_escape_string($conn, $household_no);
    $members = $_POST['members'];

    foreach ($members as $i => $m) {
        $current_photo_path = '';

        if (!empty($m['photo'])) {
            $data = $m['photo'];
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = base64_decode(substr($data, strpos($data, ',') + 1));
                $filename = "res_" . time() . "_" . $i . ".jpg";
                $filepath = "uploads/" . $filename;
                if (file_put_contents($filepath, $data)) { 
                    $current_photo_path = $filepath; 
                }
            }
        }

        $is_voter  = isset($m['voter']) ? 1 : 0;
        $is_4ps    = isset($m['four_ps']) ? 1 : 0;
        $is_pwd    = isset($m['pwd']) ? 1 : 0;
        $is_solo   = isset($m['solo_parent']) ? 1 : 0;
        $age       = (int)($m['age'] ?? 0);
        $is_senior = ($age >= 60) ? 1 : 0;
        $is_minor  = ($age < 18) ? 1 : 0;

        $status = !empty($m['status']) ? $m['status'] : 'Active';
        
        $is_archived = 0;
        $archive_reason = '';
        $proof_path = '';

        if ($status === 'Deceased') {
            $is_archived = 1;
            $archive_reason = 'Deceased';
            if (isset($_FILES['members']['name'][$i]['deceased_proof']) && $_FILES['members']['error'][$i]['deceased_proof'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['members']['name'][$i]['deceased_proof'], PATHINFO_EXTENSION);
                $proof_filename = "proof_" . time() . "_" . $i . "." . $ext;
                $proof_filepath = "uploads/" . $proof_filename;
                if (move_uploaded_file($_FILES['members']['tmp_name'][$i]['deceased_proof'], $proof_filepath)) {
                    $proof_path = $proof_filepath;
                }
            }
        } elseif ($status === 'Transferred') {
            $is_archived = 1;
            $archive_reason = 'Transferred to Another Location';
        }

        $sql = "INSERT INTO residents (
                    last_name, first_name, middle_name, suffix_name, dob, gender, 
                    relationship, employment_status, civil_status, 
                    education, age, photo_path, household_no, status,
                    is_voter, is_4ps, is_pwd, is_solo, is_senior, is_minor,
                    is_archived, archive_reason, deceased_proof_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssssssssisssiiiiiisss", 
                $m['last_name'], $m['first_name'], $m['middle_name'], $m['suffix_name'], $m['dob'], $m['gender'],
                $m['relationship'], $m['employment'], $m['civil_status'], $m['education'], 
                $age, $current_photo_path, $safe_hh_no, $status,
                $is_voter, $is_4ps, $is_pwd, $is_solo, $is_senior, $is_minor,
                $is_archived, $archive_reason, $proof_path
            );
            $stmt->execute();
            $stmt->close();
        }
    }
    $redirect_url = 'household_members.php?household_no=' . rawurlencode($household_no) . '&success=residents_added';
    header("Location: " . $redirect_url);
    exit();
}
?>















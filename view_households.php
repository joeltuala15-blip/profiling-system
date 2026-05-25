<?php
include('db.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$household_no = $_GET['household_no'] ?? '';

if (empty($household_no)) {
    header("Location: residents.php");
    exit();
}

$query_h = "SELECT * FROM households WHERE household_no = '" . mysqli_real_escape_string($conn, $household_no) . "'";
$result_h = mysqli_query($conn, $query_h);
$household = mysqli_fetch_assoc($result_h);

if (!$household) {
    echo "Household record not found.";
    exit();
}

$query_m = "SELECT * FROM residents WHERE household_no = '" . mysqli_real_escape_string($conn, $household_no) . "' AND COALESCE(is_archived, 0) = 0";
$result_m = mysqli_query($conn, $query_m);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Household Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 20px; }
        .panel { background: white; border: 1px solid #e5e7eb; padding: 18px; border-radius: 20px; border: 1px solid #e2e8f0; max-width: 1000px; margin: auto; }
        .info-box { background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #e2e8f0; padding: 12px; text-align: left; font-size: 14px; color: #475569; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .status-active { background: #22c55e; color: white; padding: 4px 10px; border-radius: 16px; font-size: 12px; }
        .btn-back { background: #64748b; color: white; padding: 10px 20px; border-radius: 16px; text-decoration: none; display: inline-block; margin-top: 20px; }
        body.dark-mode { background: #0f172a; color: white; }
        body.dark-mode .panel { background: #1e293b; border-color: #334155; }
        body.dark-mode .info-box { background: #0f172a; border-color: #334155; }
        body.dark-mode th { background: #0f172a; border-bottom-color: #334155; color: #94a3b8; }
        body.dark-mode td { border-bottom-color: #334155; color: #e2e8f0; }
        body.dark-mode .btn-back { background: #334155; color: white; }
        body.dark-mode h2, body.dark-mode h3 { color: white !important; }
        body.dark-mode p { color: #94a3b8 !important; }
    </style>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>

<div class="panel">
    <h2>Household Details</h2>
    <p style="color: #64748b;">View and manage household member information</p>

    <div class="info-box">
        <div>
            <strong>Household No:</strong> <?php echo htmlspecialchars($household['household_no']); ?><br>
            <strong>Address:</strong> <?php echo htmlspecialchars($household['address'] ?? 'N/A'); ?>
        </div>
        <div>
            <strong>Purok:</strong> <?php echo htmlspecialchars($household['purok'] ?? 'N/A'); ?>
        </div>
    </div>

    <h3>HOUSEHOLD MEMBERS (<?php echo mysqli_num_rows($result_m); ?>):</h3>
    <table>
        <thead>
            <tr><th>Name</th><th>Age</th><th>Sex</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result_m) > 0): ?>
                <?php while($m = mysqli_fetch_assoc($result_m)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['age'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($m['gender'] ?? 'N/A'); ?></td>
                    <td><span class="status-active"><?php echo htmlspecialchars($m['status'] ?? 'Active'); ?></span></td>
                    <td><i class="fa-solid fa-pen-to-square"></i> <i class="fa-solid fa-box-archive"></i></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No members found for this household.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="residents.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Records</a>
</div>

</body>
</html>















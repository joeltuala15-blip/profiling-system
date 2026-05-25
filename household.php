<?php 
include('db.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}


$query = "SELECT h.*, 
          (SELECT COUNT(*) FROM residents r WHERE r.household_no = h.household_no AND COALESCE(r.is_archived, 0) = 0) as member_count,
          (SELECT CONCAT(first_name, ' ', last_name) FROM residents r WHERE r.household_no = h.household_no AND r.relationship = 'Head' AND COALESCE(r.is_archived, 0) = 0 LIMIT 1) as head_name
          FROM households h
          ORDER BY h.id DESC"; 

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Household Profiling | Secretary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #1e293b; --accent-blue: #2563eb; --text-gray: #94a3b8; --bg-light: #f1f5f9; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: var(--bg-light); }
        .sidebar { width: 260px; background: var(--sidebar-bg); color: white; position: relative; flex-shrink: 0; transition: width 0.3s ease; overflow: hidden; }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; color: var(--text-gray); text-decoration: none; border-radius: 12px; margin: 5px 10px; font-size: 14px; }
        .nav-item.active { background: rgba(37, 99, 235, 0.2); color: #3b82f6; }
        .main-container { flex: 1; padding: 30px; overflow-y: auto; }
        .records-card { background: white; border: 1px solid #e5e7eb; border-radius: 20px; padding: 25px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #e5e7eb; }
        td { padding: 20px 15px; border-bottom: 1px solid #e5e7eb; }
        .status-badge { background: #e8f5e9; color: #2e7d32; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; border: none; }
        .add-btn { background: var(--accent-blue); color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <div class="records-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Household Records</h3>
            <a href="add_household.php" class="add-btn">+ Add Household</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Household No.</th>
                    <th>Address</th>
                    <th>Purok</th>
                    <th>Head of the Household</th>
                    <th>Members</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                                <td><strong>".$row['household_no']."</strong></td>
                                <td>".$row['address']."</td>
                                <td>".$row['purok']."</td>
                                <td>".htmlspecialchars($row['head_name'] ?? 'Not Assigned')."</td>
                                <td>".$row['member_count']."</td>
                                <td><a href='view_members.php?household_no=".$row['household_no']."'><i class='fa-regular fa-eye'></i></a></td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center;'>No records found in database.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>















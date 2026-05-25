<?php
include('db.php');
include_once('user_archive_helpers.php');
session_start();

$allowed_roles = ['Barangay Captain', 'Former Captain', 'Secretary'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: login.php");
    exit();
}

$archive_columns_ready = ensure_user_archive_columns($conn);

$email_col_exists = user_column_exists($conn, 'email');
$term_start_col_exists = user_column_exists($conn, 'term_start');
$term_end_col_exists = user_column_exists($conn, 'term_end');

$select_fields = "id, first_name, middle_name, last_name, username, role, archived_at";
if ($email_col_exists) {
    $select_fields .= ", email";
}
if ($term_start_col_exists) {
    $select_fields .= ", term_start";
}
if ($term_end_col_exists) {
    $select_fields .= ", term_end";
}

$archived_query = null;
if ($archive_columns_ready) {
    $archive_order_date = $term_end_col_exists ? "COALESCE(archived_at, term_end)" : "archived_at";
    $archived_query = mysqli_query(
        $conn,
        "SELECT $select_fields
         FROM users
         WHERE COALESCE(is_archived, 0) = 1
            OR role IN ('Former Captain', 'Former Secretary')
         ORDER BY $archive_order_date DESC, id DESC"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --accent-blue: #2563eb; --text-gray: #64748b; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }
        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .top-header { background: #ffffff; padding: 20px 40px; border-bottom: 1px solid #e2e8f0; }
        .content-body { padding: 16px 20px 20px; }
        .panel { background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 15px; border-bottom: 2px solid #e5e7eb; color: var(--text-gray); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 15px; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #334155; }
        .role-badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .role-captain { background: #fef3c7; color: #92400e; }
        .role-secretary { background: #dbeafe; color: #1e40af; }
        .role-former { background: #e2e8f0; color: #475569; }
        .role-former-secretary { background: #ede9fe; color: #5b21b6; }
        .empty-state { text-align: center; padding: 40px; color: var(--text-gray); }
        .error-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; }

        @media (max-width: 768px) {
            .main-container { min-width: 0; }
            .top-header { align-items: flex-start; }
            .panel { padding: 14px; border-radius: 16px; overflow-x: visible; }

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
            .role-badge { width: 100%; justify-content: center; box-sizing: border-box; }
        }

        @media (max-width: 480px) {
            .archive-table td {
                grid-template-columns: 1fr;
                gap: 6px;
                align-items: start;
            }
            .empty-state { padding: 32px 14px; }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin:0;">Archived Users</h2>
            <p style="margin:0; color: var(--text-gray);">Archived system accounts with former-role labels</p>
        </div>
    </header>

    <div class="content-body">
        <?php if (!$archive_columns_ready): ?>
            <div class="error-box">Database is missing archive columns for users.</div>
        <?php endif; ?>

        <div class="panel">
            <table class="archive-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <?php if ($email_col_exists): ?>
                            <th>Email</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <?php if ($term_start_col_exists || $term_end_col_exists): ?>
                            <th>Term Date</th>
                        <?php endif; ?>
                        <th>Archived On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($archived_query && mysqli_num_rows($archived_query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($archived_query)): ?>
                            <?php
                                $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                $full_name = $full_name !== '' ? $full_name : ($row['username'] ?? 'Archived User');
                                $status_label = archived_user_role_label($row['role'] ?? '');
                                $badge_class = user_role_badge_class($status_label);
                                $term_label = '---';

                                if ($term_start_col_exists || $term_end_col_exists) {
                                    $term_start = $row['term_start'] ?? '';
                                    $term_end = $row['term_end'] ?? '';
                                    if (!empty($term_start) && !empty($term_end)) {
                                        $term_label = date('M Y', strtotime($term_start)) . ' - ' . date('M Y', strtotime($term_end));
                                    } elseif (!empty($term_start)) {
                                        $term_label = date('M Y', strtotime($term_start)) . ' - Present';
                                    } elseif (!empty($term_end)) {
                                        $term_label = 'Ended ' . date('M Y', strtotime($term_end));
                                    }
                                }
                            ?>
                            <tr class="data-row">
                                <td data-label="Full Name"><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                                <td data-label="Username"><?php echo htmlspecialchars($row['username']); ?></td>
                                <?php if ($email_col_exists): ?>
                                    <td data-label="Email"><?php echo htmlspecialchars($row['email'] ?? '---'); ?></td>
                                <?php endif; ?>
                                <td data-label="Status">
                                    <span class="role-badge <?php echo $badge_class; ?>">
                                        <i class="fa-solid fa-user-clock"></i>
                                        <?php echo htmlspecialchars($status_label); ?>
                                    </span>
                                </td>
                                <?php if ($term_start_col_exists || $term_end_col_exists): ?>
                                    <td data-label="Term Date"><?php echo htmlspecialchars($term_label); ?></td>
                                <?php endif; ?>
                                <td data-label="Archived On"><?php echo !empty($row['archived_at']) ? date('M d, Y', strtotime($row['archived_at'])) : '---'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="<?php echo 4 + ($email_col_exists ? 1 : 0) + (($term_start_col_exists || $term_end_col_exists) ? 1 : 0); ?>">
                                <div class="empty-state">No archived users found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>

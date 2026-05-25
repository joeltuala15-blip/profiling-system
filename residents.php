<?php 
include('db.php');
include_once('toast_helpers.php');
session_start();

// Strict Role Access
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$display_name = trim($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'));
$display_role = trim($_SESSION['role'] ?? 'Secretary');
$page_toasts = [];
$error_toast = app_toast_from_error_code($_GET['error'] ?? '');
if ($error_toast) {
    $page_toasts[] = $error_toast;
}

$where = ["1=1"];
$show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] == '1';
$selected_purok = $_GET['purok'] ?? 'All';
$purok_options = [];

$purok_options_query = mysqli_query(
    $conn,
    "SELECT DISTINCT COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') AS purok_name
     FROM households
     ORDER BY purok_name"
);

if ($purok_options_query) {
    while ($purok_row = mysqli_fetch_assoc($purok_options_query)) {
        $purok_options[] = $purok_row['purok_name'];
    }
}

if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where[] = "(
        households.household_no LIKE '%$search%' OR 
        households.address LIKE '%$search%' OR 
        households.purok LIKE '%$search%' OR
        EXISTS (
            SELECT 1 FROM residents 
            WHERE residents.household_no = households.household_no 
            AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR middle_name LIKE '%$search%' OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE '%$search%')
        )
    )";
}

if (!empty($selected_purok) && $selected_purok !== 'All') {
    $escaped_purok = mysqli_real_escape_string($conn, $selected_purok);
    if ($escaped_purok === 'Unspecified') {
        $where[] = "COALESCE(NULLIF(TRIM(households.purok), ''), 'Unspecified') = 'Unspecified'";
    } else {
        $where[] = "COALESCE(NULLIF(TRIM(households.purok), ''), 'Unspecified') = '$escaped_purok'";
    }
}

if ($show_archived) {
    $where[] = "COALESCE(residents.is_archived, 0) = 1";
} else {
    $where[] = "COALESCE(residents.is_archived, 0) = 0";
}

$where_sql = implode(" AND ", $where);

$query = "SELECT households.*, 
          COUNT(DISTINCT residents.id) as total_members,
          (SELECT CONCAT(last_name, ', ', first_name, ', ', middle_name) 
           FROM residents 
           WHERE residents.household_no = households.household_no 
           AND relationship = 'head' 
           AND COALESCE(is_archived, 0) = 0
           LIMIT 1) as head_of_family
          FROM households 
          LEFT JOIN residents ON households.household_no = residents.household_no
          WHERE $where_sql 
          GROUP BY households.id 
          ORDER BY households.id DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household List - Profiling System</title>
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

        .panel { background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        .controls { display: flex; justify-content: space-between; margin-bottom: 25px; align-items: center; }
        .search-input, .category-select { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
        .search-input:focus { border-color: var(--accent-blue); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); letter-spacing: 0.5px; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        th:nth-child(6), td:nth-child(6) { text-align: center; }

        .status-pill { padding: 4px 10px; border-radius: 6px; background: #e8f5e9; color: #2e7d32; font-size: 12px; font-weight: 600; }
        .status-archived { background: #fee2e2; color: #991b1b; }
        
        .btn-add { background: var(--accent-blue); color: white; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }

        .action-link { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; text-decoration: none; color: var(--accent-blue); background: #eff6ff; margin-right: 6px; }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .responsive-table {
            width: 100%;
            border-collapse: collapse;
        }

        @media (max-width: 1024px) {
            .controls { flex-direction: column; align-items: stretch; gap: 16px; }
            .controls form { width: 100%; display: flex !important; flex-direction: column; gap: 12px; }
            .controls .search-input,
            .controls .category-select,
            .controls .btn-add { width: 100% !important; box-sizing: border-box; }
        }

        @media (max-width: 768px) {
            .panel { padding: 16px; border-radius: 16px; }
            .content-body { padding: 12px 16px 24px !important; }
            .top-header {
                padding: 16px !important;
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }
            .table-responsive { overflow: visible; }
            .responsive-table,
            .responsive-table thead,
            .responsive-table tbody,
            .responsive-table tr,
            .responsive-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .responsive-table thead { display: none; }
            .responsive-table tr.data-row {
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
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Welcome, <?php echo htmlspecialchars($display_name); ?></h2>
            <p style="margin:0; color: var(--text-gray);"><?php echo htmlspecialchars($display_role); ?></p>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <div class="controls">
                <form method="GET" style="display:flex; gap:12px; align-items: center;">
                    <input type="text" id="householdSearch" name="search" class="search-input" placeholder="Search household no, address, or head..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    
                    <select name="purok" class="category-select" onchange="this.form.submit()">
                        <option value="All" <?php echo $selected_purok === 'All' ? 'selected' : ''; ?>>All Purok</option>
                        <?php foreach ($purok_options as $purok_option): ?>
                            <option value="<?php echo htmlspecialchars($purok_option); ?>" <?php echo $selected_purok === $purok_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($purok_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <a href="add_household.php" class="btn-add"><i class="fa-solid fa-plus"></i> Add Household</a>
            </div>

            <div class="table-responsive">
            <table id="householdTable" class="responsive-table">
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
                    <?php $has_rows = $result && mysqli_num_rows($result) > 0; ?>
                    <?php if($has_rows): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="data-row">
                            <td data-label="Household No."><strong><?php echo htmlspecialchars($row['household_no']); ?></strong></td>
                            <td data-label="Address"><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                            <td data-label="Purok"><?php echo htmlspecialchars($row['purok'] ?? 'N/A'); ?></td>
                            <td data-label="Head of the Household"><?php echo htmlspecialchars($row['head_of_family'] ?? 'No Head Assigned'); ?></td>
                            <td data-label="Members"><i class="fa-solid fa-user-group" style="color: var(--text-gray); margin-right: 5px;"></i> <?php echo htmlspecialchars($row['total_members']); ?></td>
                            <td data-label="Actions">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="household_members.php?household_no=<?php echo urlencode($row['household_no']); ?>" class="action-link" style="margin: 0;" title="View Members">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="edit_household.php?household_no=<?php echo urlencode($row['household_no']); ?>" class="action-link" style="margin: 0;" title="Edit Household">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="householdNoResults" style="<?php echo $has_rows ? 'display: none;' : ''; ?>">
                        <td colspan="6" style="text-align:center; padding: 40px; color: var(--text-gray);">No households found matching your criteria.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setupHouseholdSearch();
    });

    function setupHouseholdSearch() {
        const searchInput = document.getElementById('householdSearch');
        if (!searchInput) return;

        const rows = Array.from(document.querySelectorAll('#householdTable tbody tr.data-row'));
        const emptyRow = document.getElementById('householdNoResults');

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














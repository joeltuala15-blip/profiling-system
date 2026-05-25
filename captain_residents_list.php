<?php
include('db.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Barangay Captain') {
    header("Location: login.php");
    exit();
}

$where = ["COALESCE(r.is_archived, 0) = 0"];
$category_options = [
    'All' => 'All Categories',
    'Senior Citizen' => 'Senior Citizen',
    'Minor' => 'Minor',
    'Voters' => 'Voters',
    'Solo Parent' => 'Solo Parent',
    '4ps' => '4ps',
    'PWD' => 'PWD',
];
$selected_category = $_GET['category'] ?? 'All';
if (!array_key_exists($selected_category, $category_options)) {
    $selected_category = 'All';
}
$selected_category_label = $category_options[$selected_category];

if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where[] = "(r.first_name LIKE '%$search%' OR r.last_name LIKE '%$search%' OR r.middle_name LIKE '%$search%' OR CONCAT(r.first_name, ' ', r.middle_name, ' ', r.last_name) LIKE '%$search%' OR r.household_no LIKE '%$search%')";
}

if ($selected_category !== 'All') {
    $cat = mysqli_real_escape_string($conn, $selected_category);
    if ($cat == 'Senior Citizen') $where[] = "r.is_senior = 1";
    elseif ($cat == 'Minor')      $where[] = "r.is_minor = 1";
    elseif ($cat == 'Voters')     $where[] = "r.is_voter = 1";
    elseif ($cat == 'Solo Parent')$where[] = "r.is_solo = 1";
    elseif ($cat == '4ps')        $where[] = "r.is_4ps = 1";
    elseif ($cat == 'PWD')        $where[] = "r.is_pwd = 1";
}

$where_sql = implode(" AND ", $where);

$query = "SELECT r.*, h.purok
          FROM residents r
          LEFT JOIN households h ON r.household_no = h.household_no
          WHERE $where_sql
          ORDER BY r.id DESC";

$result = mysqli_query($conn, $query);

$act_query = mysqli_query($conn, "SELECT ap.resident_id, a.activity_name FROM activity_participants ap JOIN activities a ON ap.activity_id = a.id");
$resident_activities = [];
if ($act_query) {
    while ($row_act = mysqli_fetch_assoc($act_query)) {
        $resident_activities[$row_act['resident_id']][] = $row_act['activity_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents List - Profiling System</title>
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

        .panel { background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); overflow-x: auto; width: 100%; box-sizing: border-box; }
        .controls { display: flex; justify-content: space-between; margin-bottom: 25px; align-items: center; gap: 16px; flex-wrap: wrap; }
        .filter-form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-input, .category-select { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; max-width: 100%; box-sizing: border-box; }
        .search-input:focus { border-color: var(--accent-blue); }

        .category-dropdown { position: relative; width: 220px; max-width: 100%; }
        .category-trigger {
            width: 100%;
            min-height: 40px;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-sizing: border-box;
        }
        .category-trigger:focus { border-color: var(--accent-blue); outline: none; }
        .category-trigger i { color: #64748b; font-size: 12px; transition: transform 0.18s ease; }
        .category-dropdown.open .category-trigger i { transform: rotate(180deg); }
        .category-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 20;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.16);
            overflow: hidden;
        }
        .category-dropdown.open .category-menu { display: block; }
        .category-option {
            width: 100%;
            min-height: 38px;
            padding: 9px 14px;
            border: none;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }
        .category-option:hover,
        .category-option.active {
            background: var(--accent-blue);
            color: #ffffff;
        }

        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); letter-spacing: 0.5px; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            padding: 4px 10px;
            border-radius: 6px;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
            box-sizing: border-box;
        }
        .status-archived { background: #fee2e2; color: #991b1b; }

        .action-link { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; text-decoration: none; color: var(--accent-blue); background: #eff6ff; }

        .activity-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .responsive-table {
            width: 100%;
            border-collapse: collapse;
        }
        @media (min-width: 769px) {
            .responsive-table th:nth-child(12),
            .responsive-table td:nth-child(12) {
                width: 88px;
                min-width: 88px;
            }
            .responsive-table th:nth-child(13),
            .responsive-table td:nth-child(13) {
                width: 76px;
                min-width: 76px;
                text-align: center;
            }
        }
        .cell-value { min-width: 0; color: #334155; overflow-wrap: anywhere; }
        td[data-label="Status"] .cell-value,
        td[data-label="Actions"] .cell-value {
            white-space: nowrap;
            overflow-wrap: normal;
        }
        .cell-value-wrap { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
        th:nth-child(11), td:nth-child(11) { text-align: center; }

        @media (max-width: 1024px) {
            .controls { flex-direction: column; align-items: stretch; }
            .filter-form { flex-direction: column; align-items: stretch; width: 100%; }
            .search-input, .category-select, .category-dropdown, .category-trigger { width: 100% !important; box-sizing: border-box; }
            .category-menu {
                position: static;
                margin-top: 8px;
                box-shadow: none;
            }
        }

        @media (max-width: 768px) {
            html,
            body {
                max-width: 100%;
                overflow-x: hidden !important;
            }
            .main-container {
                max-width: 100vw;
                min-width: 0;
                overflow-x: hidden !important;
            }
            .panel {
                padding: 16px;
                border-radius: 16px;
                max-width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }
            .content-body {
                padding: 12px 16px 24px !important;
                max-width: 100%;
                overflow-x: hidden;
            }
            .header {
                padding: 16px !important;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .table-responsive {
                max-width: 100%;
                overflow-x: hidden;
            }
            .responsive-table {
                min-width: 0 !important;
                max-width: 100%;
                table-layout: fixed;
            }
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
                display: grid;
                grid-template-columns: minmax(120px, 40%) 1fr;
                gap: 16px;
                align-items: center;
            }
            .responsive-table td::before {
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .responsive-table .cell-value {
                text-align: right;
                justify-self: end;
            }
            .activity-badge { white-space: normal; margin-bottom: 4px; }
        }

        @media (max-width: 480px) {
            .responsive-table td {
                grid-template-columns: 1fr;
                gap: 5px;
                align-items: start;
            }
            .responsive-table .cell-value {
                justify-self: start;
                text-align: left;
            }
            .cell-value-wrap {
                justify-content: flex-start;
            }
        }

        /* Modal Dark Mode Overrides */
        body.dark-mode #residentDetailModal .modal-container {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        body.dark-mode #residentDetailModal [style*="border-bottom:1px solid #e2e8f0"] {
            border-bottom-color: #334155 !important;
        }
        body.dark-mode #residentDetailModal h2 { color: #f8fafc !important; }
        body.dark-mode #residentDetailModal div[id^="modalRes"] { color: #f8fafc !important; }
        body.dark-mode #residentDetailModal label { color: #94a3b8 !important; }
        body.dark-mode #residentDetailModal p { color: #94a3b8 !important; }
        body.dark-mode #residentDetailModal button:hover { color: #f8fafc !important; }

    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="header">
        <div>
            <h2 style="margin:0;">Residents List</h2>
            <p style="color:#64748b; margin:0; font-size:14px;">View resident records</p>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <div class="controls">
                <form method="GET" class="filter-form">
                    <input type="text" id="residentSearch" name="search" class="search-input" placeholder="Search resident name or household no..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">

                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>" data-category-input>
                    <div class="category-dropdown" data-category-dropdown>
                        <button type="button" class="category-trigger" data-category-trigger aria-expanded="false">
                            <span><?php echo htmlspecialchars($selected_category_label); ?></span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="category-menu" data-category-menu>
                            <?php foreach ($category_options as $value => $label): ?>
                                <button type="button" class="category-option <?php echo $selected_category === $value ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($value); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
            <table id="residentsTable" class="responsive-table">
                <thead>
                    <tr>
                        <th>Resident Name</th>
                        <th>Household No.</th>
                        <th>Purok</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Relationship</th>
                        <th>Civil Status</th>
                        <th>Employment Status</th>
                        <th>Educational Attainment</th>
                        <th>Classifications</th>
                        <th>Activities</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $has_rows = $result && mysqli_num_rows($result) > 0; ?>
                    <?php if($has_rows): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <?php 
                            $activities = $resident_activities[$row['id']] ?? []; 
                            $row['included_activities'] = $activities;
                        ?>
                        <tr class="data-row">
                            <td data-label="Resident Name"><span class="cell-value"><strong><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ($row['middle_name'] ? ' ' . $row['middle_name'] : '')); ?></strong></span></td>
                            <td data-label="Household No."><span class="cell-value"><?php echo htmlspecialchars($row['household_no'] ?? 'N/A'); ?></span></td>
                            <td data-label="Purok"><span class="cell-value"><?php echo htmlspecialchars($row['purok'] ?? 'N/A'); ?></span></td>
                            <td data-label="Age"><span class="cell-value"><?php echo htmlspecialchars($row['age'] ?? 'N/A'); ?></span></td>
                            <td data-label="Gender"><span class="cell-value"><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></span></td>
                            <td data-label="Relationship"><span class="cell-value"><?php echo htmlspecialchars($row['relationship'] ?? 'N/A'); ?></span></td>
                            <td data-label="Civil Status"><span class="cell-value"><?php echo htmlspecialchars($row['civil_status'] ?? 'N/A'); ?></span></td>
                            <td data-label="Employment Status"><span class="cell-value"><?php echo htmlspecialchars($row['employment_status'] ?? 'N/A'); ?></span></td>
                            <td data-label="Educational Attainment"><span class="cell-value"><?php echo htmlspecialchars($row['education'] ?? 'N/A'); ?></span></td>
                            <td data-label="Classifications"><span class="cell-value">
                                <?php
                                    $classifications = [];
                                    if(isset($row['is_voter']) && $row['is_voter']) $classifications[] = 'Registered Voter';
                                    if(isset($row['is_senior']) && $row['is_senior']) $classifications[] = 'Senior Citizen';
                                    if(isset($row['is_pwd']) && $row['is_pwd']) $classifications[] = 'PWD';
                                    if(isset($row['is_solo']) && $row['is_solo']) $classifications[] = 'Solo Parent';
                                    if(isset($row['is_minor']) && $row['is_minor']) $classifications[] = 'Minor';
                                    if(isset($row['is_4ps']) && $row['is_4ps']) $classifications[] = '4Ps';
                                    echo !empty($classifications) ? htmlspecialchars(implode(', ', $classifications)) : '<span style="color: var(--text-gray); font-size: 13px;">None</span>';
                                ?>
                            </span></td>
                            <td data-label="Activities">
                                <div class="cell-value cell-value-wrap">
                                <?php if (!empty($activities)): ?>
                                    <?php foreach ($activities as $act): ?>
                                        <span class="activity-badge"><?php echo htmlspecialchars($act); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-gray); font-size: 13px;">None</span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="cell-value">
                                <span class="status-pill <?php echo ($row['status'] ?? 'Active') !== 'Active' ? 'status-archived' : ''; ?>">
                                    <?php echo htmlspecialchars($row['status'] ?? 'Active'); ?>
                                </span>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <span class="cell-value cell-value-wrap">
                                    <span style="color: #94a3b8; font-size: 13px;">None</span>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="residentNoResults" style="<?php echo $has_rows ? 'display: none;' : ''; ?>">
                        <td colspan="13" style="text-align:center; padding: 40px; color: var(--text-gray);">No residents found matching your criteria.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
    // CONSISTENT SIDEBAR LOGIC
    document.addEventListener("DOMContentLoaded", function() {
        setupResidentSearch();
        setupCategoryDropdowns();
    });

    // CONSISTENT LOGOUT DROPDOWN
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

    function setupResidentSearch() {
        const searchInput = document.getElementById('residentSearch');
        if (!searchInput) return;

        const rows = Array.from(document.querySelectorAll('#residentsTable tbody tr.data-row'));
        const emptyRow = document.getElementById('residentNoResults');

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

    function setupCategoryDropdowns() {
        const dropdowns = Array.from(document.querySelectorAll('[data-category-dropdown]'));

        const closeDropdown = (dropdown) => {
            dropdown.classList.remove('open');
            const trigger = dropdown.querySelector('[data-category-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        };

        dropdowns.forEach((dropdown) => {
            const trigger = dropdown.querySelector('[data-category-trigger]');
            const input = dropdown.closest('form')?.querySelector('[data-category-input]');
            if (!trigger || !input) return;

            trigger.addEventListener('click', () => {
                const shouldOpen = !dropdown.classList.contains('open');
                dropdowns.forEach(closeDropdown);
                dropdown.classList.toggle('open', shouldOpen);
                trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            });

            dropdown.querySelectorAll('[data-value]').forEach((option) => {
                option.addEventListener('click', () => {
                    input.value = option.dataset.value || 'All';
                    option.closest('form').submit();
                });
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-category-dropdown]')) return;
            dropdowns.forEach(closeDropdown);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                dropdowns.forEach(closeDropdown);
            }
        });
    }

    function openResidentModal(data) {
        document.getElementById('modalResPhoto').src = data.photo_path || 'uploads/default.png';
        document.getElementById('modalResName').innerText = `${data.last_name}, ${data.first_name} ${data.middle_name ? data.middle_name : ''}`;
        document.getElementById('modalResHH').innerText = `Household No: #${data.household_no || 'N/A'}`;
        
        document.getElementById('modalResLastName').innerText = data.last_name || '—';
        document.getElementById('modalResFirstName').innerText = data.first_name || '—';
        document.getElementById('modalResMiddleName').innerText = data.middle_name || '—';
        document.getElementById('modalResSuffix').innerText = data.suffix_name || '—';
        
        document.getElementById('modalResGender').innerText = data.gender || '—';
        document.getElementById('modalResDob').innerText = data.dob || '—';
        document.getElementById('modalResAge').innerText = data.age || '—';
        document.getElementById('modalResRel').innerText = data.relationship || '—';
        
        document.getElementById('modalResCivil').innerText = data.civil_status || '—';
        document.getElementById('modalResEmp').innerText = data.employment_status || '—';
        document.getElementById('modalResEdu').innerText = data.education || '—';
        
        let badges = [];
        if(parseInt(data.is_voter)) badges.push('Registered Voter');
        if(parseInt(data.is_senior)) badges.push('Senior Citizen');
        if(parseInt(data.is_minor)) badges.push('Minor');
        if(parseInt(data.is_pwd)) badges.push('PWD');
        if(parseInt(data.is_solo)) badges.push('Solo Parent');
        if(parseInt(data.is_4ps)) badges.push('4Ps');

        document.getElementById('modalResClassifications').innerText = badges.length ? badges.join(', ') : 'None';

        let actBadges = (data.included_activities || []).map(act => `<span class="activity-badge" style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600;">${act}</span>`);
        document.getElementById('modalResActivities').innerHTML = actBadges.length ? actBadges.join(' ') : '<span style="color:#64748b; font-size:14px;">None</span>';

        document.getElementById('residentDetailModal').style.display = 'flex';
    }
    function closeResidentModal() {
        document.getElementById('residentDetailModal').style.display = 'none';
    }
</script>

<div id="residentDetailModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div class="panel modal-container" style="background:white; width:650px; max-width:90%; border-radius:24px; padding:32px; max-height:85vh; overflow-y:auto; position:relative;">
        <button onclick="closeResidentModal()" style="position:absolute; top:24px; right:24px; background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;"><i class="fa-solid fa-xmark"></i></button>
        <div style="display:flex; align-items:center; gap:20px; margin-bottom:24px; border-bottom:1px solid #e2e8f0; padding-bottom:20px;">
            <img id="modalResPhoto" src="uploads/default.png" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-blue);">
            <div>
                <h2 id="modalResName" style="margin:0 0 4px 0; font-size:22px; color:inherit;"></h2>
                <p id="modalResHH" style="margin:0; color:#64748b; font-size:14px;"></p>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Last Name</label><div id="modalResLastName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">First Name</label><div id="modalResFirstName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Middle Name</label><div id="modalResMiddleName" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Suffix Name</label><div id="modalResSuffix" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Gender</label><div id="modalResGender" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Date of Birth</label><div id="modalResDob" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Age</label><div id="modalResAge" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Relationship to Head</label><div id="modalResRel" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Civil Status</label><div id="modalResCivil" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Employment Status</label><div id="modalResEmp" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            <div style="grid-column: span 2;"><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Educational Attainment</label><div id="modalResEdu" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
            
            <div style="grid-column: span 2;"><label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Special Classifications</label><div id="modalResClassifications" style="font-weight:500; font-size:14px; color:#1e293b;"></div></div>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; margin-bottom:8px; display:block;">Included Activities</label>
            <div id="modalResActivities" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
        </div>
    </div>
</div>

</body>
</html>

















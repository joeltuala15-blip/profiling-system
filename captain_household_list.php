<?php 
include('db.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Barangay Captain') {
    header("Location: login.php");
    exit();
}

$query = "SELECT h.household_no, h.address, h.purok,
          (SELECT CONCAT(first_name, ' ', last_name) FROM residents r2 WHERE r2.household_no = h.household_no AND r2.relationship = 'Head' AND COALESCE(r2.is_archived, 0) = 0 LIMIT 1) as head_name,
          COUNT(r1.id) as member_count
          FROM households h
          LEFT JOIN residents r1 ON r1.household_no = h.household_no AND COALESCE(r1.is_archived, 0) = 0
          GROUP BY h.household_no, h.address, h.purok
          ORDER BY h.id DESC";
$result = mysqli_query($conn, $query);

$all_members_query = mysqli_query($conn, "SELECT id, household_no, first_name, last_name, relationship, age, gender FROM residents WHERE COALESCE(is_archived, 0) = 0 ORDER BY relationship = 'Head' DESC, id ASC");
$hh_members_map = [];
if ($all_members_query) {
    while ($mem = mysqli_fetch_assoc($all_members_query)) {
        $hh_no = $mem['household_no'];
        if (!isset($hh_members_map[$hh_no])) {
            $hh_members_map[$hh_no] = [];
        }
        $hh_members_map[$hh_no][] = $mem;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Records | Captain</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --accent-blue: #2563eb; --light-bg: #f1f5f9; }
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; background: var(--light-bg); height: 100vh; overflow: hidden; }
        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; box-sizing: border-box; width: 100%; }

        .content-card { background: white; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow-x: auto; width: 100%; box-sizing: border-box; }
        .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
        .filter-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-bar { background: #f1f5f9; border: 1px solid transparent; padding: 10px 16px; border-radius: 8px; width: 300px; max-width: 100%; box-sizing: border-box; font-size: 14px; outline: none; }
        .search-bar:focus { border-color: var(--accent-blue); background: white; }
        .category-select { padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155; cursor: pointer; max-width: 100%; box-sizing: border-box; }
        
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); letter-spacing: 0.5px; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        th:nth-child(6), td:nth-child(6) { text-align: center; }
        
        .status-active { background: #22c55e; color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
        .view-btn { color: #64748b; cursor: pointer; font-size: 16px; }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .responsive-table {
            width: 100%;
            border-collapse: collapse;
        }

        @media (max-width: 1024px) {
            .table-controls { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; align-items: stretch; width: 100%; }
            .search-bar, .category-select { width: 100% !important; }
        }

        @media (max-width: 768px) {
            .content-card { padding: 16px; border-radius: 16px; }
            .content-body { padding: 12px 16px 24px !important; }
            .header {
                padding: 16px !important;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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

<div class="main-container">
    <header class="header">
        <div>
            <h2 style="margin:0;">Household List</h2>
            <p style="color:#64748b; margin:0; font-size:14px;">View and manage household records</p>
        </div>
    </header>

    <div class="content-body">
        <div class="content-card">
            <div class="table-controls">
                <h3>Household Records</h3>
                <div class="filter-group">
                    <input type="text" id="householdSearch" class="search-bar" placeholder="Search household no, address, purok, or head...">
                    <select id="householdPurokFilter" class="category-select">
                        <option value="All">All Puroks</option>
                        <?php 
                        $purok_query = mysqli_query($conn, "SELECT DISTINCT COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') AS purok_name FROM households ORDER BY purok_name");
                        if ($purok_query) {
                            while ($p_row = mysqli_fetch_assoc($purok_query)) {
                                echo '<option value="' . htmlspecialchars($p_row['purok_name']) . '">' . htmlspecialchars($p_row['purok_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
            <table id="householdsTable" class="responsive-table">
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
                    <?php if ($has_rows): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="data-row">
                            <td data-label="Household No."><strong><?php echo htmlspecialchars($row['household_no']); ?></strong></td>
                            <td data-label="Address"><?php echo htmlspecialchars($row['address']); ?></td>
                            <td data-label="Purok"><?php echo htmlspecialchars($row['purok']); ?></td>
                            <td data-label="Head of the Household"><?php echo htmlspecialchars($row['head_name'] ?? 'N/A'); ?></td>
                            <td data-label="Members"><?php echo htmlspecialchars($row['member_count']); ?></td>
                            <td data-label="Actions">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="#" class="view-btn" title="View Household Members" onclick='event.stopPropagation(); openHouseholdModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>, <?php echo htmlspecialchars(json_encode($hh_members_map[$row['household_no']] ?? []), ENT_QUOTES, "UTF-8"); ?>)' style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; text-decoration:none; color:var(--accent-blue); background:#eff6ff;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="householdNoResults" style="<?php echo $has_rows ? 'display: none;' : ''; ?>">
                        <td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">No households found matching your criteria.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById('householdSearch');
        const purokFilter = document.getElementById('householdPurokFilter');
        if (!searchInput || !purokFilter) return;

        const rows = Array.from(document.querySelectorAll('#householdsTable tbody tr.data-row'));
        const emptyRow = document.getElementById('householdNoResults');

        const filterRows = () => {
            const term = searchInput.value.trim().toLowerCase();
            const selectedPurok = purokFilter.value.trim();
            let visible = 0;

            rows.forEach((row) => {
                const hay = row.textContent.toLowerCase();
                const matchSearch = hay.includes(term);
                
                const purokCell = row.cells[2].textContent.trim();
                const matchPurok = selectedPurok === 'All' || purokCell === selectedPurok || (selectedPurok === 'Unspecified' && purokCell === '');

                if (matchSearch && matchPurok) {
                    row.style.display = '';
                    visible += 1;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyRow) {
                emptyRow.style.display = visible === 0 ? '' : 'none';
            }
        };

        searchInput.addEventListener('input', filterRows);
        purokFilter.addEventListener('change', filterRows);
    });

    function openHouseholdModal(hh, members) {
        document.getElementById('modalHHNo').innerText = hh.household_no;
        document.getElementById('modalHHAddress').innerText = hh.address;
        document.getElementById('modalHHPurok').innerText = hh.purok;

        let rows = members.map(m => `
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:12px; font-weight:600; font-size:14px;">${m.first_name} ${m.last_name}</td>
                <td style="padding:12px; font-size:14px;">
                    <span class="status-pill" style="background:${m.relationship === 'Head' ? '#ffedd5' : '#f1f5f9'}; color:${m.relationship === 'Head' ? '#ea580c' : '#475569'}; padding:4px 10px; border-radius:12px; font-weight:600; font-size:12px;">${m.relationship}</span>
                </td>
                <td style="padding:12px; font-size:14px;">${m.age}</td>
                <td style="padding:12px; font-size:14px;">${m.gender}</td>
                <td style="padding:12px; font-size:14px; text-align:center;">
                    <button type="button" onclick="viewResident(${m.id})" title="View personal details" style="background:none; border:1px solid #e2e8f0; color:var(--accent-blue); width:32px; height:32px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:0.15s;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>
        `);

        document.getElementById('modalHHMembersList').innerHTML = rows.length ? rows.join('') : '<tr><td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No members registered yet.</td></tr>';
        document.getElementById('householdDetailModal').style.display = 'flex';
    }
    function closeHouseholdModal() {
        document.getElementById('householdDetailModal').style.display = 'none';
    }
</script>

<div id="householdDetailModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div class="panel modal-container" style="background:white; width:700px; max-width:90%; border-radius:24px; padding:32px; max-height:85vh; overflow-y:auto; position:relative;">
        <button onclick="closeHouseholdModal()" style="position:absolute; top:24px; right:24px; background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;"><i class="fa-solid fa-xmark"></i></button>
        <div style="margin-bottom:24px; border-bottom:1px solid #e2e8f0; padding-bottom:20px;">
            <h2 style="margin:0 0 4px 0; font-size:22px; color:inherit;">Household #<span id="modalHHNo"></span></h2>
            <p style="margin:0; color:#64748b; font-size:14px;"><i class="fa-solid fa-location-dot" style="color:var(--accent-blue); margin-right:6px;"></i> <span id="modalHHAddress"></span> (Purok <span id="modalHHPurok"></span>)</p>
        </div>
        <h3 style="font-size:16px; margin:0 0 16px 0; color:inherit;">Household Members</h3>
        <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
            <thead>
                <tr class="modal-header">
                    <th style="padding:12px; text-align:left; font-size:13px; color:#64748b; border:none;">Name</th>
                    <th style="padding:12px; text-align:left; font-size:13px; color:#64748b; border:none;">Relationship</th>
                    <th style="padding:12px; text-align:left; font-size:13px; color:#64748b; border:none;">Age</th>
                    <th style="padding:12px; text-align:left; font-size:13px; color:#64748b; border:none;">Gender</th>
                    <th style="padding:12px; text-align:center; font-size:13px; color:#64748b; border:none; width:60px;">Action</th>
                </tr>
            </thead>
            <tbody id="modalHHMembersList"></tbody>
        </table>
    </div>
</div>

<div class="view-modal-overlay" id="viewResidentModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); z-index:4000; align-items:center; justify-content:center;">
    <div class="view-modal" style="background:white; border-radius:16px; width:420px; max-width:92%; box-shadow:0 20px 40px rgba(0,0,0,0.15); overflow:hidden;">
        <div class="view-modal-header" style="padding:18px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <h3 style="margin:0; font-size:16px; color:#1e293b;"><i class="fa-solid fa-user" style="margin-right:8px; color:var(--accent-blue);"></i>Resident Information</h3>
            <button class="view-modal-close" onclick="closeViewModal()" style="background:#f1f5f9; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; color:#64748b; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="view-modal-body" id="viewModalBody" style="padding:24px;">
            <div style="text-align:center; color:var(--text-gray); padding:20px;">Loading...</div>
        </div>
    </div>
</div>

<style>
    .view-photo { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; display: block; margin: 0 auto 16px; background: #f1f5f9; }
    .view-name { text-align: center; font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
    .view-hh { text-align: center; font-size: 13px; color: #64748b; margin-bottom: 20px; }
    .view-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .view-info-item { display: flex; flex-direction: column; gap: 2px; }
    .view-info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
    .view-info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
</style>

<script>
    function viewResident(residentId) {
        const modal = document.getElementById('viewResidentModal');
        const body = document.getElementById('viewModalBody');
        modal.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center; color:#64748b; padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

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
        document.getElementById('viewResidentModal').style.display = 'none';
    }
</script>

</body>
</html>















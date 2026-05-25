<?php 
include('db.php');
session_start();

$allowed_roles = ['Captain', 'Barangay Captain', 'Admin', 'Secretary'];
if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$display_name = trim($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'));
$display_role = trim($_SESSION['role'] ?? 'Barangay Captain');

$query = "SELECT a.*, 
          (SELECT COUNT(*) FROM activity_participants ap WHERE ap.activity_id = a.id) as beneficiary_count 
          FROM activities a WHERE COALESCE(a.is_archived, 0) = 0 ORDER BY a.id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Dates - Barangay Captain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --sidebar-navy: #1e293b; 
            --accent-blue: #2563eb; 
            --accent-green: #10b981;
            --logo-orange: #ff9800;
            --text-gray: #64748b;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; height: 100vh; background: #f1f5f9; overflow: hidden; }

        .main-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; box-sizing: border-box; width: 100%; }

        .panel { background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); letter-spacing: 0.5px; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }

        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        
        .action-link { text-decoration: none; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--accent-blue); background: #eff6ff; padding: 6px 12px; border-radius: 8px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-container { background: white; width: 100%; max-width: 750px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; max-height: 85vh; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #ffffff; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 700; }
        .modal-close { background: #f1f5f9; border: none; font-size: 16px; cursor: pointer; color: #64748b; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { background: #e2e8f0; color: #1e293b; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-empty { text-align: center; padding: 40px; color: var(--text-gray); font-size: 15px; }
        
        .modal-table { width: 100%; border-collapse: collapse; }
        .modal-table th { padding: 10px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #e8f5e9; color: #2e7d32; display: inline-block; }
        .status-badge.pending { background: #fee2e2; color: #991b1b; }
        
        table th:nth-child(4), table td:nth-child(4) { text-align: center; }

        .btn-view-resident { background: none; border: 1px solid #e2e8f0; color: var(--accent-blue); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.15s; }
        .btn-view-resident:hover { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
        .view-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .view-modal-overlay.show { display: flex; }
        .view-modal { background: white; border-radius: 16px; width: 420px; max-width: 92%; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden; }
        .view-modal-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .view-modal-header h3 { margin: 0; font-size: 16px; color: #1e293b; }
        .view-modal-close { background: #f1f5f9; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; }
        .view-modal-close:hover { background: #e2e8f0; color: #1e293b; }
        .view-modal-body { padding: 24px; }
        .view-photo { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; display: block; margin: 0 auto 16px; background: #f1f5f9; }
        .view-name { text-align: center; font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .view-hh { text-align: center; font-size: 13px; color: var(--text-gray); margin-bottom: 20px; }
        .view-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .view-info-item { display: flex; flex-direction: column; gap: 2px; }
        .view-info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
        .view-info-value { font-size: 14px; color: #1e293b; font-weight: 500; }

        @media (max-width: 768px) {
            .main-container { min-width: 0; }
            .top-header { align-items: flex-start; flex-direction: column; gap: 16px; padding: 15px 20px; }
            .panel { padding: 14px; border-radius: 16px; overflow-x: auto; }
            
            table { min-width: 600px; }
            
            .modal-container { width: 95%; max-height: 90vh; }
            .action-link { margin-right: 5px; margin-bottom: 5px; display: inline-flex; }
        }
    </style>
</head>
<body>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Activity Monitoring</h2>
            <p style="margin:0; color: var(--text-gray);">View activities and their participating beneficiaries</p>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <table>
                <thead>
                    <tr>
                        <th>Activity Details</th>
                        <th>Date</th>
                        <th>Beneficiaries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <strong style="display:block; color: #1e293b; font-size: 16px;"><?php echo htmlspecialchars($row['activity_name'] ?? $row['title'] ?? 'N/A'); ?></strong>
                                <small style="color: var(--text-gray); font-size: 13px;"><?php echo htmlspecialchars($row['description'] ?? 'No description provided'); ?></small>
                            </td>
                            
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:6px;">
                                    <i class="fa-regular fa-calendar" style="color: var(--accent-blue);"></i>
                                    <?php 
                                        $date_val = $row['activity_date'] ?? $row['date'] ?? null;
                                        echo ($date_val) ? date('M d, Y', strtotime($date_val)) : 'TBA';
                                    ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo ($row['beneficiary_count'] > 0) ? 'badge-success' : ''; ?>">
                                    <i class="fa-solid fa-people-group"></i> <?php echo $row['beneficiary_count']; ?> Given
                                </span>
                            </td>
                            
                            <td>
                                <div style="display: flex; justify-content: center;">
                                    <a href="#" class="action-link" onclick="event.stopPropagation(); openActivityModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['activity_name'] ?? $row['title'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars(addslashes($row['description'] ?? '')); ?>', '<?php $d = $row['activity_date'] ?? $row['date'] ?? null; echo $d ? date('M d, Y', strtotime($d)) : ''; ?>');">
                                        <i class="fa-solid fa-eye"></i> View Members
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 50px; color: var(--text-gray);">No activities found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="activityModal">
    <div class="modal-container">
        <div class="modal-header">
            <div>
                <h3>Activity Beneficiaries</h3>
                <div style="color: #1e293b; font-weight: 600; margin-top: 4px; font-size: 14px;" id="modalActivityTitle">Loading...</div>
                <div style="color: var(--text-gray); font-size: 13px; margin-top: 2px;" id="modalActivityDesc"></div>
                <div style="color: var(--text-gray); font-size: 12px; margin-top: 4px;" id="modalActivityDate"></div>
            </div>
            <button class="modal-close" onclick="closeActivityModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="modal-empty">Loading participants...</div>
        </div>
    </div>
</div>

<div class="view-modal-overlay" id="viewResidentModal">
    <div class="view-modal">
        <div class="view-modal-header">
            <h3><i class="fa-solid fa-user" style="margin-right: 8px; color: var(--accent-blue);"></i>Resident Information</h3>
            <button class="view-modal-close" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="view-modal-body" id="viewModalBody">
            <div style="text-align:center; color: var(--text-gray); padding: 20px;">Loading...</div>
        </div>
    </div>
</div>

<script>
    function openActivityModal(id, title, desc, date) {
        document.getElementById('activityModal').style.display = 'flex';
        document.getElementById('modalActivityTitle').textContent = 'Tracking for: ' + title;
        
        const descElem = document.getElementById('modalActivityDesc');
        if (descElem) {
            descElem.textContent = desc ? desc : 'No description available';
        }
        
        const dateElem = document.getElementById('modalActivityDate');
        if (dateElem) {
            dateElem.innerHTML = date ? '<i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> ' + date : '';
        }

        fetchParticipants(id);
    }

    function closeActivityModal() {
        document.getElementById('activityModal').style.display = 'none';
    }

    function fetchParticipants(id) {
        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = '<div class="modal-empty">Loading participants...</div>';

        fetch('activities.php?ajax_fetch_participants=1&activity_id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    modalBody.innerHTML = '<div class="modal-empty">No beneficiaries found for this activity.</div>';
                    return;
                }

                if (data[0] && data[0].is_hh_view) {
                    let html = '<table class="modal-table"><thead><tr><th>Household No.</th><th>Household Members</th><th>Status</th></tr></thead><tbody>';
                    data.forEach(p => {
                        const statusClass = p.status === 'Received' ? 'status-badge' : 'status-badge pending';
                        const statusText = p.status === 'Received' ? 'RECEIVED' : 'PENDING';
                        
                        html += `<tr>
                            <td><strong>Household #${p.household_no || 'N/A'}</strong></td>
                            <td><small style="color: #64748b;">${p.members}</small></td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    modalBody.innerHTML = html;
                } else {
                    let html = '<table class="modal-table"><thead><tr><th>Resident</th><th>Household No.</th><th>Status</th><th style="width: 50px;">View</th></tr></thead><tbody>';
                    data.forEach(p => {
                        const fullName = `${p.last_name}, ${p.first_name} ${p.middle_name || ''}`.trim();
                        const statusClass = p.status === 'Received' ? 'status-badge' : 'status-badge pending';
                        const statusText = p.status === 'Received' ? 'RECEIVED' : 'PENDING';
                        
                        html += `<tr>
                            <td><strong>${fullName}</strong></td>
                            <td>${p.household_no || 'N/A'}</td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-view-resident" onclick="viewResident(${p.resident_id})" title="View resident info">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    modalBody.innerHTML = html;
                }
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="modal-empty">Error loading participants.</div>';
                console.error(err);
            });
    }

    function viewResident(residentId) {
        const modal = document.getElementById('viewResidentModal');
        const body = document.getElementById('viewModalBody');
        modal.classList.add('show');
        body.innerHTML = '<div style="text-align:center; color: #64748b; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

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
        document.getElementById('viewResidentModal').classList.remove('show');
    }

</script>
</body>
</html>

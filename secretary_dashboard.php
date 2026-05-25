<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$display_name = trim($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'));
$display_role = trim($_SESSION['role'] ?? 'Secretary');
$page_toasts = [];
$success_toast = app_toast_from_success_code($_GET['success'] ?? '');
if ($success_toast) {
    $page_toasts[] = $success_toast;
}

$filter = $_GET['category'] ?? 'All';
$household_purok_filter = $_GET['household_purok'] ?? 'All';
$where_clause = "COALESCE(is_archived, 0) = 0";

if ($filter !== 'All' && !empty($filter)) {
    $categories = explode(',', $filter);
    $cat_clauses = [];
    foreach ($categories as $cat) {
        $cat = trim($cat);
        $escaped_cat = mysqli_real_escape_string($conn, $cat);
        if ($escaped_cat == 'Senior Citizen') $cat_clauses[] = "age >= 60";
        elseif ($escaped_cat == 'Minor')      $cat_clauses[] = "age <= 17";
        elseif ($escaped_cat == 'Voters')     $cat_clauses[] = "is_voter = 1";
        elseif ($escaped_cat == 'Solo Parent')$cat_clauses[] = "is_solo = 1";
        elseif ($escaped_cat == '4ps')         $cat_clauses[] = "is_4ps = 1";
        elseif ($escaped_cat == 'PWD')         $cat_clauses[] = "is_pwd = 1";
    }
    if (!empty($cat_clauses)) {
        $where_clause .= " AND (" . implode(" OR ", $cat_clauses) . ")";
    }
}


$total_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM residents WHERE $where_clause"))['count'] ?? 0;
$total_house = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM households"))['count'] ?? 0;
$child_c  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM residents WHERE age <= 12 AND $where_clause"))['count'] ?? 0;
$teen_c   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM residents WHERE age BETWEEN 13 AND 19 AND $where_clause"))['count'] ?? 0;
$adult_c  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM residents WHERE age BETWEEN 20 AND 59 AND $where_clause"))['count'] ?? 0;
$senior_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM residents WHERE age >= 60 AND $where_clause"))['count'] ?? 0;

$purok_labels = [];
$purok_counts = [];
$purok_options = [];
$purok_filter_sql = '';

if ($household_purok_filter !== 'All' && !empty($household_purok_filter)) {
    $puroks = explode(',', $household_purok_filter);
    $purok_clauses = [];
    foreach ($puroks as $purok_val) {
        $purok_val = trim($purok_val);
        $escaped_purok = mysqli_real_escape_string($conn, $purok_val);
        if ($escaped_purok === 'Unspecified') {
            $purok_clauses[] = "COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') = 'Unspecified'";
        } else {
            $purok_clauses[] = "COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') = '$escaped_purok'";
        }
    }
    if (!empty($purok_clauses)) {
        $purok_filter_sql = "WHERE " . implode(" OR ", $purok_clauses);
    }
}

$purok_options_query = mysqli_query(
    $conn,
    "SELECT DISTINCT COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') AS purok_name
     FROM households
     ORDER BY purok_name"
);

if ($purok_options_query) {
    while ($option_row = mysqli_fetch_assoc($purok_options_query)) {
        $purok_options[] = $option_row['purok_name'];
    }
}

$purok_query = mysqli_query(
    $conn,
    "SELECT COALESCE(NULLIF(TRIM(purok), ''), 'Unspecified') AS purok_name, COUNT(*) AS total_households
     FROM households
     $purok_filter_sql
     GROUP BY purok_name
     ORDER BY purok_name"
);

if ($purok_query) {
    while ($row = mysqli_fetch_assoc($purok_query)) {
        $purok_labels[] = $row['purok_name'];
        $purok_counts[] = (int) $row['total_households'];
    }
}

$population_labels = ['Children (0-12)', 'Teenagers (13-19)', 'Adults (20-59)', 'Seniors (60+)'];
$population_counts = [(int) $child_c, (int) $teen_c, (int) $adult_c, (int) $senior_c];
$activities_query = mysqli_query($conn, "SELECT action, created_at FROM logs ORDER BY created_at DESC LIMIT 8");

function is_filter_active($val, $filter_str) {
    if ($filter_str === 'All' || empty($filter_str)) {
        return $val === 'All';
    }
    $parts = explode(',', $filter_str);
    return in_array($val, $parts, true);
}

if (isset($_GET['ajax_fetch_stats'])) {
    echo json_encode([
        'total_res' => (int)$total_res,
        'total_house' => (int)$total_house,
        'population_counts' => $population_counts,
        'purok_labels' => $purok_labels,
        'purok_counts' => $purok_counts,
        'filter_text' => $filter,
        'purok_filter_text' => $household_purok_filter
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #f1f5f9;
            --accent-color: #2563eb;
            --hero-bg: #1e293b;
        }
        
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; background: var(--primary-bg); height: 100vh; overflow: hidden; }

        .main-container { flex: 1; overflow: hidden; display: flex; flex-direction: column; box-sizing: border-box; width: 100%; position: relative; height: 100vh; }

        .top-header {
            background: var(--hero-bg);
            padding: 40px 40px 110px 40px; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            color: white;
            position: relative;
            flex-shrink: 0;
        }

        .top-header h2 { margin: 0 0 6px 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; color: #ffffff; }
        .top-header p { margin: 0; color: #94a3b8; font-size: 15px; }
        
        .content-body { 
            padding: 0 40px 40px 40px; 
            margin-top: -65px;
            z-index: 10; 
            position: relative; 
            max-width: 1400px; 
            width: 100%; 
            box-sizing: border-box; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden;
            min-height: 0;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(280px, 0.75fr);
            gap: 24px;
            margin-bottom: 0;
            align-items: stretch;
            flex: 1;
            min-height: 0;
        }
        
        .grid-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
            height: 100%;
            min-height: 0;
            min-width: 0;
        }
        
        .stat-card { 
            background: var(--card-bg); 
            padding: 24px 32px; 
            border-radius: 24px; 
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
            position: relative; 
            border: 1px solid rgba(255, 255, 255, 0.8);
            flex-shrink: 0;
        }
        .stat-card p { font-size: 15px; font-weight: 600; color: var(--text-muted); margin: 0; }
        .stat-card h2 { font-size: 36px; font-weight: 700; margin: 8px 0 0 0; color: var(--text-main); letter-spacing: -1px; }
        
        .card-icon {
            position: absolute;
            top: 24px;
            right: 32px;
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-color);
            font-size: 22px !important;
        }
        
        .panel { 
            background: var(--card-bg); 
            padding: 32px; 
            border-radius: 24px; 
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); 
            display: flex; 
            flex-direction: column; 
            border: 1px solid var(--border-color);
            flex: 1;
            min-height: 0;
            overflow: hidden;
            min-width: 0;
        }
        .panel h3 { font-size: 18px; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main); }
        .panel > p { color: var(--text-muted); font-size: 14px; margin: 0 0 24px 0; flex-shrink: 0; }
 
        .population-panel-header { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-bottom: 24px; }
        .population-filter-form { margin: 0; }
 
        .chart-wrap {
            flex: 1;
            min-height: 0;
            position: relative;
            margin-top: 10px;
        }
 
        .empty-chart-text {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
            text-align: center;
            padding-top: 80px;
        }
 
        .activity-list {
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            padding-right: 8px;
            padding-left: 10px;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
 
        .activity-list::-webkit-scrollbar {
            width: 6px;
        }
        .activity-list::-webkit-scrollbar-track {
            background: transparent;
        }
        .activity-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .activity-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        body.dark-mode .activity-list::-webkit-scrollbar-thumb {
            background: #475569;
        }
        body.dark-mode .activity-list::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .activity-item { 
            border-left: 2px solid #e2e8f0; 
            padding-left: 16px; 
            padding-bottom: 4px; 
            padding-top: 4px;
            position: relative; 
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: auto;
        }
        .activity-item:last-child { border-left-color: transparent; padding-bottom: 4px; }
        
        .activity-item::before { 
            content: ""; 
            position: absolute; 
            left: -6px; 
            top: 9px; 
            width: 10px; 
            height: 10px; 
            background: var(--accent-color); 
            border-radius: 50%; 
            border: 2px solid var(--card-bg); /* Cutout effect */
        }
        
        .activity-title { font-weight: 600; color: var(--text-main); font-size: 15px; margin-bottom: 4px; line-height: 1.35; overflow-wrap: anywhere; word-break: break-word; }
        .activity-time { color: var(--text-muted); font-size: 13px; font-weight: 500; }

        .filter-select { 
            padding: 10px 16px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            font-family: inherit; 
            font-size: 13px;
            font-weight: 600; 
            color: var(--text-main); 
            outline: none; 
            background: var(--primary-bg);
            cursor: pointer;
        }
        .filter-select:focus { border-color: var(--accent-color); }

        /* Premium Custom Multi-Select Checkbox Dropdown Widget */
        .custom-multiselect {
            position: relative;
            width: 200px;
            font-family: 'Inter', sans-serif;
            user-select: none;
        }

        .multiselect-trigger {
            background: var(--card-bg);
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: border-color 0.2s ease;
            gap: 8px;
        }

        body.dark-mode .multiselect-trigger {
            border-color: #334155;
        }

        .multiselect-trigger:hover {
            border-color: #824E39 !important;
        }

        .multiselect-value {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        .multiselect-trigger i {
            font-size: 10px;
            color: var(--text-muted);
            transition: transform 0.2s ease;
        }

        .custom-multiselect.active .multiselect-trigger i {
            transform: rotate(180deg);
        }

        .multiselect-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 200px;
            background: var(--card-bg);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 6px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
            display: none;
            z-index: 1000;
            max-height: 240px;
            overflow-y: auto;
        }

        body.dark-mode .multiselect-dropdown {
            border-color: #334155;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
        }

        .custom-multiselect.active .multiselect-dropdown {
            display: block;
        }

        .multiselect-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .multiselect-option:hover {
            background: #f1f5f9;
        }

        body.dark-mode .multiselect-option:hover {
            background: rgba(148, 163, 184, 0.1);
        }

        .multiselect-option input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 15px;
            height: 15px;
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            outline: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--card-bg);
            margin: 0;
            flex-shrink: 0;
        }

        body.dark-mode .multiselect-option input[type="checkbox"] {
            border-color: #475569;
        }

        .multiselect-option input[type="checkbox"]:checked {
            background-color: #824E39 !important;
            border-color: #824E39 !important;
        }

        .multiselect-option input[type="checkbox"]:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 9px;
            color: #ffffff;
            display: block;
        }

        @media (max-width: 1360px) {
            body { height: auto !important; overflow: auto !important; }
            .main-container { height: auto !important; overflow: visible !important; }
            .content-body { height: auto !important; overflow: visible !important; display: block !important; }
            .dashboard-grid { grid-template-columns: 1fr 1fr; height: auto !important; }
            .grid-column { height: auto !important; overflow: visible !important; }
            .panel { height: auto !important; overflow: visible !important; }
            .grid-column:last-child { grid-column: 1 / -1; }
            .chart-wrap { height: 380px !important; }
            .activity-list { height: auto !important; overflow: visible !important; }
            .activity-item { flex: none !important; padding-bottom: 14px !important; padding-top: 0 !important; }
            .activity-item::before { top: 3px !important; }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .grid-column:last-child { grid-column: auto; }
            .top-header { padding: 30px 20px 90px 20px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; }
            .content-body { padding: 0 20px 20px 20px; }
        }
    </style>
</head>
<body>

<?php render_app_toasts($page_toasts); ?>

<?php include_once('left_navbar.php'); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2 style="margin:0;">Welcome, <?php echo htmlspecialchars($display_name); ?></h2>
            <p style="margin:0; color:#64748b; font-size: 15px;"><?php echo htmlspecialchars($display_role); ?></p>
        </div>
    </header>

    <div class="content-body">
        <div class="dashboard-grid">
            <!-- Column 1: Residents -->
            <div class="grid-column">
                <div class="stat-card">
                    <p>Total Residents (<b id="categoryFilterLabel"><?php echo htmlspecialchars($filter); ?></b>)</p>
                    <h2 class="counter" data-target="<?php echo $total_res; ?>">0</h2>
                    <i class="fa-solid fa-users card-icon" style="color: #cbd5e1;"></i>
                </div>
                <div class="panel" style="display: flex; flex-direction: column; flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; gap: 10px;">
                        <div>
                            <h3 style="margin:0 0 4px 0; font-size: 16px;">Residents by Age Group</h3>
                            <p style="color:#64748b; font-size: 13px; margin: 0;">Distribution across categories</p>
                        </div>
                        <div class="custom-multiselect" id="categoryMultiselect">
                            <div class="multiselect-trigger" onclick="toggleMultiselect('categoryMultiselect')">
                                <span class="multiselect-value">All Residents</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="multiselect-dropdown" id="categoryDropdown">
                                <label class="multiselect-option">
                                    <input type="checkbox" value="All" <?php if(is_filter_active('All', $filter)) echo 'checked'; ?> onchange="handleSelectAll('category')"> All Residents
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="Senior Citizen" <?php if(is_filter_active('Senior Citizen', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> Seniors Only
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="Minor" <?php if(is_filter_active('Minor', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> Minors Only
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="PWD" <?php if(is_filter_active('PWD', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> PWD Only
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="Voters" <?php if(is_filter_active('Voters', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> Voters Only
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="Solo Parent" <?php if(is_filter_active('Solo Parent', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> Solo Parents Only
                                </label>
                                <label class="multiselect-option">
                                    <input type="checkbox" value="4ps" <?php if(is_filter_active('4ps', $filter)) echo 'checked'; ?> onchange="handleOptionChange('category')"> 4PS Only
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="chart-wrap" style="flex-grow: 1;">
                        <canvas id="populationPieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid-column">
                <div class="stat-card">
                    <p>Total Households</p>
                    <h2 class="counter" data-target="<?php echo $total_house; ?>">0</h2>
                    <i class="fa-solid fa-house card-icon" style="color: #cbd5e1;"></i>
                </div>
                <div class="panel" style="display: flex; flex-direction: column; flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; gap: 10px;">
                        <div>
                            <h3 style="margin:0 0 4px 0; font-size: 16px;">Households per District</h3>
                            <p style="color:#64748b; font-size: 13px; margin: 0;">Distribution across districts</p>
                        </div>
                        <div class="custom-multiselect" id="districtMultiselect">
                            <div class="multiselect-trigger" onclick="toggleMultiselect('districtMultiselect')">
                                <span class="multiselect-value">All Districts</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="multiselect-dropdown" id="purokDropdown">
                                <label class="multiselect-option">
                                    <input type="checkbox" value="All" <?php if(is_filter_active('All', $household_purok_filter)) echo 'checked'; ?> onchange="handleSelectAll('purok')"> All Purok
                                </label>
                                <?php foreach ($purok_options as $purok_option): ?>
                                    <label class="multiselect-option">
                                        <input type="checkbox" value="<?php echo htmlspecialchars($purok_option); ?>" <?php if(is_filter_active($purok_option, $household_purok_filter)) echo 'checked'; ?> onchange="handleOptionChange('purok')"> <?php echo htmlspecialchars($purok_option); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($purok_labels)): ?>
                        <div class="chart-wrap" style="flex-grow: 1;">
                            <canvas id="purokPieChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p class="empty-chart-text" style="padding-top: 50px;">No household purok data available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Column 3: Recent Activities -->
            <div class="grid-column">
                <div class="panel" style="padding: 24px;">
                    <h3>Recent Activities</h3>
                    <p style="color:#64748b; font-size: 13px; margin-bottom: 16px;">Latest system updates</p>
                    <div class="activity-list">
                        <?php if($activities_query && mysqli_num_rows($activities_query) > 0): ?>
                            <?php while($log = mysqli_fetch_assoc($activities_query)): ?>
                                <div class="activity-item">
                                    <div class="activity-title"><?php echo htmlspecialchars($log['action']); ?></div>
                                    <div class="activity-time"><?php echo date('h:i A', strtotime($log['created_at'])); ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color:#64748b;">No recent activities found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const counters = document.querySelectorAll('.counter');
        const duration = 1500; // ms
        const frameDuration = 1000 / 60; // 60fps
        const totalFrames = Math.round(duration / frameDuration);
        
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
            let frame = 0;
            const easeOutQuad = t => t * (2 - t);
            
            const counterInterval = setInterval(() => {
                frame++;
                const progress = easeOutQuad(frame / totalFrames);
                const currentCount = Math.round(target * progress);
                
                counter.innerText = currentCount.toLocaleString();
                
                if (frame >= totalFrames) {
                    clearInterval(counterInterval);
                    counter.innerText = target.toLocaleString();
                }
            }, frameDuration);
        });
    });

    let populationChart, purokChart;
    const populationLabels = <?php echo json_encode($population_labels); ?>;
    const initialPopulationCounts = <?php echo json_encode($population_counts); ?>;
    const initialPurokLabels = <?php echo json_encode($purok_labels); ?>;
    const initialPurokCounts = <?php echo json_encode($purok_counts); ?>;

    const popColors = ['#4f46e5', '#8b5cf6', '#10b981', '#f97316'];
    const popColorMap = {};
    populationLabels.forEach((l, i) => popColorMap[l] = popColors[i]);

    const purokColors = ['#0ea5e9', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#ef4444', '#64748b', '#3b82f6', '#10b981'];
    const purokColorMap = {};
    initialPurokLabels.forEach((l, i) => purokColorMap[l] = purokColors[i % purokColors.length]);

    const chartTooltip = {
        callbacks: {
            label: function(context) {
                const value = context.parsed || 0;
                return context.label + ': ' + value;
            }
        }
    };

    function getActiveData(labels, counts, colorMap, defaultColor = '#94a3b8') {
        let resLabels = [], resCounts = [], resColors = [];
        for (let i = 0; i < counts.length; i++) {
            if (counts[i] > 0) {
                resLabels.push(labels[i]);
                resCounts.push(counts[i]);
                resColors.push(colorMap[labels[i]] || defaultColor);
            }
        }
        if (resCounts.length === 0) {
            resLabels = ['No Data'];
            resCounts = [1];
            resColors = ['#e2e8f0'];
        }
        return { labels: resLabels, counts: resCounts, colors: resColors };
    }

    function initCharts() {
        const populationChartCanvas = document.getElementById('populationPieChart');
        if (populationChartCanvas) {
            const popData = getActiveData(populationLabels, initialPopulationCounts, popColorMap);
            populationChart = new Chart(populationChartCanvas, {
                type: 'pie',
                data: {
                    labels: popData.labels,
                    datasets: [{
                        data: popData.counts,
                        backgroundColor: popData.colors,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    animation: { duration: 1200, easing: 'easeOutQuart' },
                    plugins: { legend: { position: 'bottom' }, tooltip: chartTooltip }
                }
            });
        }

        const purokChartCanvas = document.getElementById('purokPieChart');
        if (purokChartCanvas && initialPurokCounts.length > 0) {
            const purData = getActiveData(initialPurokLabels, initialPurokCounts, purokColorMap, '#0ea5e9');
            purokChart = new Chart(purokChartCanvas, {
                type: 'pie',
                data: {
                    labels: purData.labels,
                    datasets: [{
                        data: purData.counts,
                        backgroundColor: purData.colors,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    animation: { duration: 1200, easing: 'easeOutQuart' },
                    plugins: { legend: { position: 'bottom' }, tooltip: chartTooltip }
                }
            });
        }
    }

    function animateCounter(counter, target) {
        const duration = 1000;
        const frameDuration = 1000 / 60;
        const totalFrames = Math.round(duration / frameDuration);
        let frame = 0;
        const start = parseInt(counter.innerText.replace(/,/g, ''), 10) || 0;
        
        const easeOutQuad = t => t * (2 - t);
        
        const counterInterval = setInterval(() => {
            frame++;
            const progress = easeOutQuad(frame / totalFrames);
            const currentCount = Math.round(start + (target - start) * progress);
            
            counter.innerText = currentCount.toLocaleString();
            
            if (frame >= totalFrames) {
                clearInterval(counterInterval);
                counter.innerText = target.toLocaleString();
            }
        }, frameDuration);
    }

    function toggleMultiselect(id) {
        const el = document.getElementById(id);
        const isActive = el.classList.contains('active');
        document.querySelectorAll('.custom-multiselect').forEach(m => m.classList.remove('active'));
        if (!isActive) {
            el.classList.add('active');
        }
    }

    function handleSelectAll(type) {
        const dropdown = document.getElementById(type === 'category' ? 'categoryDropdown' : 'purokDropdown');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:not([value="All"])');
        const allCheckbox = dropdown.querySelector('input[value="All"]');
        
        if (allCheckbox.checked) {
            checkboxes.forEach(cb => cb.checked = false);
        } else {
            allCheckbox.checked = true;
        }
        updateTriggerText(type);
        updateDashboardStats();
    }

    function handleOptionChange(type) {
        const dropdown = document.getElementById(type === 'category' ? 'categoryDropdown' : 'purokDropdown');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:not([value="All"])');
        const allCheckbox = dropdown.querySelector('input[value="All"]');
        
        let anyChecked = false;
        checkboxes.forEach(cb => {
            if (cb.checked) anyChecked = true;
        });
        
        if (anyChecked) {
            allCheckbox.checked = false;
        } else {
            allCheckbox.checked = true;
        }
        updateTriggerText(type);
        updateDashboardStats();
    }

    function getSelectedOptions(type) {
        const dropdown = document.getElementById(type === 'category' ? 'categoryDropdown' : 'purokDropdown');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:not([value="All"])');
        const allCheckbox = dropdown.querySelector('input[value="All"]');
        
        if (allCheckbox && allCheckbox.checked) {
            return 'All';
        }
        
        const selected = [];
        checkboxes.forEach(cb => {
            if (cb.checked) {
                selected.push(cb.value);
            }
        });
        
        return selected.length > 0 ? selected.join(',') : 'All';
    }

    function updateTriggerText(type) {
        const multiselect = document.getElementById(type === 'category' ? 'categoryMultiselect' : 'purokMultiselect');
        if (!multiselect) return;
        const valueSpan = multiselect.querySelector('.multiselect-value');
        const dropdown = document.getElementById(type === 'category' ? 'categoryDropdown' : 'purokDropdown');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:not([value="All"])');
        const allCheckbox = dropdown.querySelector('input[value="All"]');
        
        if (allCheckbox && allCheckbox.checked) {
            valueSpan.textContent = type === 'category' ? 'All Residents' : 'All Purok';
            return;
        }
        
        const selected = [];
        checkboxes.forEach(cb => {
            if (cb.checked) {
                selected.push(cb.value);
            }
        });
        
        if (selected.length === 0) {
            valueSpan.textContent = type === 'category' ? 'All Residents' : 'All Purok';
        } else {
            valueSpan.textContent = selected.join(', ');
        }
    }

    window.addEventListener('click', (e) => {
        if (!e.target.closest('.custom-multiselect')) {
            document.querySelectorAll('.custom-multiselect').forEach(m => m.classList.remove('active'));
        }
    });

    function updateDashboardStats() {
        const category = getSelectedOptions('category');
        const purok = getSelectedOptions('purok');
        
        fetch(`secretary_dashboard.php?ajax_fetch_stats=1&category=${category}&household_purok=${purok}`)
            .then(res => res.json())
            .then(data => {
                const resCounter = document.querySelector('.counter[data-target="<?php echo $total_res; ?>"]');
                const houseCounter = document.querySelector('.counter[data-target="<?php echo $total_house; ?>"]');
                
                if (resCounter) animateCounter(resCounter, data.total_res);
                if (houseCounter) animateCounter(houseCounter, data.total_house);

                const filterLabel = document.getElementById('categoryFilterLabel');
                if (filterLabel) {
                    filterLabel.innerText = data.filter_text;
                }

                if (populationChart) {
                    const newPopData = getActiveData(populationLabels, data.population_counts, popColorMap);
                    populationChart.data.labels = newPopData.labels;
                    populationChart.data.datasets[0].data = newPopData.counts;
                    populationChart.data.datasets[0].backgroundColor = newPopData.colors;
                    populationChart.update();
                }

                if (purokChart) {
                    if (data.purok_counts.length > 0) {
                        const newPurData = getActiveData(data.purok_labels, data.purok_counts, purokColorMap, '#0ea5e9');
                        purokChart.data.labels = newPurData.labels;
                        purokChart.data.datasets[0].data = newPurData.counts;
                        purokChart.data.datasets[0].backgroundColor = newPurData.colors;
                        purokChart.update();

                        const wrap = document.getElementById('purokPieChart').parentElement;
                        if (wrap.querySelector('.empty-chart-text')) {
                            wrap.querySelector('.empty-chart-text').remove();
                            document.getElementById('purokPieChart').style.display = 'block';
                        }
                    }
                }
            })
            .catch(err => console.error('Error updating dashboard:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {

        updateTriggerText('category');
        updateTriggerText('purok');

        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
            animateCounter(counter, target);
        });
        
        initCharts();

        const dashboardGrid = document.querySelector('.dashboard-grid');
        if (dashboardGrid && window.ResizeObserver) {
            const resizeCharts = () => {
                if (populationChart) populationChart.resize();
                if (purokChart) purokChart.resize();
            };
            const observer = new ResizeObserver(() => {
                window.requestAnimationFrame(resizeCharts);
            });
            observer.observe(dashboardGrid);
        }
    });
</script>

</body>
</html>









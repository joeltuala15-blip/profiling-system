<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $_SESSION['role'] ?? '';

$secretary_nav = [
    [
        'href' => 'secretary_dashboard.php',
        'icon' => 'fa-table-cells-large',
        'label' => 'Overview',
        'pages' => ['secretary_dashboard.php'],
    ],
    [
        'href' => 'residents_list.php',
        'icon' => 'fa-users',
        'label' => 'Residents List',
        'pages' => ['residents_list.php', 'edit_member.php'],
    ],
    [
        'href' => 'residents.php',
        'icon' => 'fa-house-chimney',
        'label' => 'Household List',
        'pages' => ['residents.php', 'household_members.php', 'edit_household.php', 'add_household.php', 'add_members.php', 'view_households.php', 'save_household.php', 'save_household_session.php'],
    ],
    [
        'href' => 'activities.php',
        'icon' => 'fa-clipboard-list',
        'label' => 'Activity Lists',
        'pages' => ['activities.php', 'manage_beneficiaries.php', 'add_activity.php', 'edit_activity.php'],
    ],
];

$captain_nav = [
    [
        'href' => 'captain_dashboard.php',
        'icon' => 'fa-table-columns',
        'label' => 'Overview',
        'pages' => ['captain_dashboard.php'],
    ],
    [
        'href' => 'captain_household_list.php',
        'icon' => 'fa-house-chimney',
        'label' => 'Household List',
        'pages' => ['captain_household_list.php'],
    ],
    [
        'href' => 'captain_residents_list.php',
        'icon' => 'fa-users',
        'label' => 'Residents List',
        'pages' => ['captain_residents_list.php'],
    ],
    [
        'href' => 'captain_activity_list.php',
        'icon' => 'fa-clipboard-list',
        'label' => 'Activity Lists',
        'pages' => ['captain_activity_list.php'],
    ],
    [
        'href' => 'captain_reports.php',
        'icon' => 'fa-file-lines',
        'label' => 'Reports',
        'pages' => ['captain_reports.php'],
    ],
    [
        'href' => 'manage_users.php',
        'icon' => 'fa-user-gear',
        'label' => 'User Lists',
        'pages' => ['manage_users.php'],
    ],
];

$former_captain_nav = [
    [
        'href' => 'captain_dashboard.php',
        'icon' => 'fa-table-columns',
        'label' => 'Overview',
        'pages' => ['captain_dashboard.php'],
    ]
];

$nav_items = ($current_role === 'Barangay Captain') ? $captain_nav : (($current_role === 'Former Captain') ? $former_captain_nav : $secretary_nav);
$show_archived_link = ($current_role !== 'Former Captain');
$role_title = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$role_subtitle = ($current_role === 'Barangay Captain') ? 'Barangay Captain' : (($current_role === 'Former Captain') ? 'Former Captain' : 'Barangay Secretary');

function left_nav_active($current, $pages) {
    return in_array($current, $pages, true) ? ' active' : '';
}
?>

<style>
    :root {
        --accent-blue: #824E39 !important;
        --accent-blue-hover: #693C2A !important;
        --accent-color: #824E39 !important;
        --accent-color-hover: #693C2A !important;
        --primary: #824E39 !important;
        --primary-hover: #693C2A !important;
    }

    body.preload-transitions * {
        transition: none !important;
    }
    header.top-header, header.header, .top-header, .header {
        background: transparent !important;
        padding: 20px 40px 10px 40px !important;
        margin: 0 !important;
        border-radius: 0 !important;
        border: none !important;
        border-bottom: none !important;
        min-height: 70px !important;
        box-sizing: border-box !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        box-shadow: none !important;
        width: 100% !important;
        float: none !important;
        position: relative !important;
        flex-shrink: 0 !important;
    }
    header.top-header h2, header.header h2, .top-header h2, .header h2 {
        font-size: 32px !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        margin: 0 !important;
        letter-spacing: -1px !important;
        line-height: 1.2 !important;
        font-family: 'Inter', sans-serif !important;
    }
    header.top-header p, header.header p, .top-header p, .header p {
        font-size: 16px !important;
        color: #64748b !important;
        margin: 6px 0 0 0 !important;
        font-family: 'Inter', sans-serif !important;
    }
    .content-body {
        padding: 10px 40px 40px 40px !important;
        margin: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        margin-top: 0 !important;
        position: relative !important;
        z-index: 1 !important;
    }
    body.dark-mode header.top-header, 
    body.dark-mode header.header, 
    body.dark-mode .top-header, 
    body.dark-mode .header {
        background: transparent !important;
        border-bottom: none !important;
    }
    body.dark-mode header.top-header h2, 
    body.dark-mode header.header h2, 
    body.dark-mode .top-header h2, 
    body.dark-mode .header h2 {
        color: #ffffff !important;
    }
    body.dark-mode header.top-header p, 
    body.dark-mode header.header p, 
    body.dark-mode .top-header p, 
    body.dark-mode .header p {
        color: #94a3b8 !important;
    }

    .sidebar {
        width: 260px !important;
        min-width: 260px !important;
        height: calc(100vh - 32px) !important;
        background: #ffffff !important;
        color: #1e293b !important;
        display: flex !important;
        flex-direction: column !important;
        position: sticky !important;
        top: 16px !important;
        flex-shrink: 0 !important;
        margin: 12px 2px 12px 12px !important;
        border-radius: 20px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05) !important;
        z-index: 9999 !important;
        transition: width 0.42s cubic-bezier(0.22, 1, 0.36, 1), min-width 0.42s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.3s ease !important;
        overflow: visible !important;
        will-change: width, min-width !important;
    }

    body.modal-open .sidebar {
        z-index: 1 !important;
        pointer-events: none !important;
    }

    .modal-overlay,
    .preview-modal,
    .details-modal,
    .lightbox-modal,
    .end-term-modal,
    #selectHouseholdModal,
    #memberDetailsModal,
    #residentDetailModal,
    #householdDetailModal,
    #activityModal,
    #archiveModal,
    #restoreModal,
    #successModal,
    #deleteModal,
    #viewResidentModal {
        z-index: 99999 !important;
    }

    .sidebar.collapsed {
        width: 74px !important;
        min-width: 74px !important;
    }

    .sidebar-header {
        height: 90px !important;
        padding: 12px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        position: relative !important;
        box-sizing: border-box !important;
        outline: none !important;
        margin-bottom: 8px !important;
    }

    .brand-group {
        display: flex !important;
        align-items: center !important;
        min-width: 0 !important;
        flex: 1 !important;
        overflow: hidden !important;
        opacity: 1 !important;
        transition: opacity 0.2s ease !important;
    }

    .brand-logo-container {
        border: 3px solid var(--accent-blue) !important;
        border-radius: 14px !important;
        width: 55px !important;
        height: 55px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        box-sizing: border-box !important;
    }

    .brand-logo-container i {
        color: var(--accent-blue) !important;
        font-size: 30px !important;
    }

    img.logo {
        width: 50px !important;
        height: 50px !important;
        border-radius: 10px !important;
        object-fit: cover !important;
        flex-shrink: 0 !important;
    }

    .brand-text {
        margin-left: 12px !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
        width: 150px !important;
        opacity: 1 !important;
        overflow: hidden !important;
        transition: width 0.35s ease, opacity 0.3s ease, margin 0.35s ease !important;
    }

    .brand-text b {
        display: block !important;
        font-size: 14px !important;
        color: #1e293b !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .brand-text span {
        display: block !important;
        color: #94a3b8 !important;
        font-size: 11px !important;
        margin-top: 2px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .toggle-icon {
        cursor: pointer !important;
        z-index: 10000 !important;
        line-height: 1 !important;
        transition: right 0.42s cubic-bezier(0.22, 1, 0.36, 1), color 0.2s ease, background-color 0.2s ease !important;
    }

    .toggle-icon:hover {
        cursor: pointer !important;
    }

    @media (min-width: 769px) {
        .toggle-icon {
            color: var(--accent-blue) !important;
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 50% !important;
            width: 24px !important;
            height: 24px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: absolute !important;
            right: -12px !important;
            top: 33px !important;
            font-size: 11px !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08) !important;
        }
        body.dark-mode .toggle-icon {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #d29d8a !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
        }
    }

    @media (max-width: 768px) {
        .toggle-icon {
            color: #64748b !important;
            display: block !important;
            position: absolute !important;
            right: 20px !important;
            top: 24px !important;
            font-size: 24px !important;
        }
        body.dark-mode .toggle-icon {
            color: #cbd5e1 !important;
        }
    }

    .nav-menu {
        padding: 10px 10px 8px !important;
        flex-grow: 1 !important;
        box-sizing: border-box !important;
    }

    .nav-item {
        display: flex !important;
        align-items: center !important;
        min-height: 38px !important;
        padding: 7px 10px !important;
        color: #64748b !important;
        text-decoration: none !important;
        border-radius: 12px !important;
        margin-bottom: 4px !important;
        font-weight: 500 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease !important;
        outline: none !important;
    }

    .nav-item:hover {
        background: #f1f5f9 !important;
        color: #1e293b !important;
    }

    .nav-item.active {
        background: var(--accent-blue) !important;
        color: white !important;
    }

    .nav-item i {
        font-size: 15px !important;
        width: 24px !important;
        min-width: 24px !important;
        text-align: center !important;
        color: inherit !important;
        transition: color 0.2s ease !important;
    }

    .nav-text {
        display: inline-block !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        margin-left: 8px !important;
        white-space: nowrap !important;
        opacity: 1 !important;
        transition: opacity 0.16s ease !important;
    }

    .nav-item.has-dropdown {
        position: relative !important;
        cursor: pointer !important;
    }

    .dropdown-arrow {
        margin-left: auto !important;
        font-size: 11px !important;
        transition: transform 0.2s ease !important;
    }

    .sidebar.collapsed .dropdown-arrow {
        display: none !important;
    }

    .nav-dropdown-menu {
        position: absolute !important;
        left: calc(100% + 8px) !important;
        top: 0 !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 6px !important;
        width: max-content !important;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateX(-10px) !important;
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        z-index: 100 !important;
    }

    .nav-item.has-dropdown:hover .nav-dropdown-menu {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateX(0) !important;
    }

    .nav-dropdown-item {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 10px 14px !important;
        color: #334155 !important;
        text-decoration: none !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        border-radius: 8px !important;
        transition: background 0.2s ease !important;
    }

    .nav-dropdown-item:hover {
        background: #f1f5f9 !important;
        color: #1e293b !important;
    }
    
    .nav-dropdown-item i {
        width: 16px !important;
        min-width: 16px !important;
        font-size: 14px !important;
        text-align: center !important;
    }

    .sidebar-footer {
        margin-top: auto !important;
        padding: 10px 10px 12px !important;
        border-top: 1px solid rgba(148, 163, 184, 0.15) !important;
        position: relative !important;
        overflow: visible !important;
    }

    .sidebar-profile-container {
        position: relative !important;
        overflow: visible !important;
    }

    .sidebar-account {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        padding: 8px 10px !important;
        cursor: pointer !important;
        color: #1e293b !important;
        transition: background 0.2s ease, border-color 0.2s ease !important;
    }

    .sidebar-account:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
    }

    .sidebar-avatar {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        background: var(--accent-blue) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        color: white !important;
        font-size: 14px !important;
    }

    .sidebar-account-meta {
        min-width: 0 !important;
        line-height: 1.15 !important;
    }

    .sidebar-account-name {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        white-space: nowrap !important;
    }

    .sidebar-account-role {
        font-size: 11px !important;
        color: #94a3b8 !important;
        margin-top: 1px !important;
        white-space: nowrap !important;
    }

    .sidebar-account-caret {
        margin-left: auto !important;
        color: #94a3b8 !important;
        font-size: 11px !important;
    }

    .sidebar-logout-dropdown {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        bottom: calc(100% + 8px) !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        display: none !important;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18) !important;
        z-index: 10000 !important;
        overflow: visible !important;
    }

    .sidebar-logout-dropdown.show {
        display: block !important;
    }

    .sidebar-dropdown-header {
        padding: 12px !important;
        text-align: center !important;
        border-bottom: 1px solid #e5e7eb !important;
        color: #64748b !important;
        font-size: 13px !important;
    }

    .sidebar-dropdown-header b {
        display: block !important;
        color: #1e293b !important;
        margin-top: 4px !important;
        font-size: 15px !important;
    }

    .sidebar-logout-btn {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        padding: 14px !important;
        color: #ef4444 !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-size: 14px !important;
    }

    .sidebar-dropdown-link.has-submenu {
        position: relative !important;
        cursor: pointer !important;
    }

    .submenu-arrow {
        transition: transform 0.2s ease !important;
        font-size: 11px !important;
    }

    .sidebar-dropdown-link.has-submenu:hover .submenu-arrow {
        transform: rotate(90deg) !important;
    }

    .sidebar-submenu {
        position: absolute !important;
        left: calc(100% + 4px) !important;
        top: 0 !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 6px !important;
        width: max-content !important;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateX(-10px) !important;
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        z-index: 140 !important;
    }

    .sidebar-dropdown-link.has-submenu:hover .sidebar-submenu {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateX(0) !important;
    }

    .submenu-item {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 10px 14px !important;
        color: #334155 !important;
        text-decoration: none !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        border-radius: 8px !important;
        white-space: nowrap !important;
        transition: background 0.2s ease !important;
    }

    .submenu-item:hover {
        background: #f1f5f9 !important;
        color: #1e293b !important;
    }

    .header {
        background: transparent !important;
        border: none !important;
        border-radius: 18px !important;
        margin: 12px 12px 0 !important;
        padding: 12px 18px !important;
        box-sizing: border-box !important;
    }

    .header, .report-panel, .settings-card, .records-card, .content-card {
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05) !important;
        border: 1px solid #e2e8f0 !important;
    }

    .sidebar-dropdown-link {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 12px 14px !important;
        color: #334155 !important;
        text-decoration: none !important;
        border-bottom: 1px solid #e5e7eb !important;
        font-size: 13px !important;
        font-weight: 600 !important;
    }

    .sidebar-dropdown-link:hover {
        background: #f8fafc !important;
    }

    .top-header .user-profile-container,
    .header .user-profile-container {
        display: none !important;
    }

    .sidebar.collapsed .brand-group {
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    .sidebar.collapsed .sidebar-header {
        /* Let it inherit the same 12px padding as expanded state so the logo doesn't jump vertically or horizontally */
    }

    .sidebar.collapsed .toggle-icon {
        /* Keep visible in collapsed state */
    }

    .sidebar.collapsed .brand-text {
        opacity: 0 !important;
        width: 0 !important;
        margin-left: 0 !important;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0 !important;
        width: 0 !important;
        margin-left: 0 !important;
        overflow: hidden !important;
    }

    .sidebar.collapsed .nav-item {
        justify-content: center !important;
        padding: 0 !important;
        width: 40px !important;
        height: 40px !important;
        margin: 0 auto 6px auto !important;
        border-radius: 12px !important;
    }

    .sidebar.collapsed .nav-item i {
        margin: 0 !important;
        width: auto !important;
        min-width: unset !important;
    }

    .sidebar.collapsed .sidebar-account {
        justify-content: center !important;
        padding: 8px !important;
        border-radius: 12px !important;
    }

    .sidebar.collapsed .sidebar-account-meta,
    .sidebar.collapsed .sidebar-account-caret {
        display: none !important;
    }

    .sidebar.collapsed .sidebar-logout-dropdown {
        left: 100% !important;
        right: auto !important;
        bottom: 0 !important;
        width: 210px !important;
        margin-left: 8px !important;
    }

    body.dark-mode {
        background: #0f172a !important;
        color: #f8fafc !important;
    }
    body.dark-mode .sidebar {
        background: #1e293b !important;
        color: white !important;
        border: 1px solid #334155 !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
    }
    body.dark-mode .brand-text b { color: white !important; }
    body.dark-mode .brand-text span { color: #94a3b8 !important; }
    body.dark-mode .toggle-icon { color: #cbd5e1 !important; }
    body.dark-mode .sidebar.collapsed .toggle-icon { color: white !important; }
    body.dark-mode .nav-item { color: #cbd5e1 !important; }
    body.dark-mode .nav-item:hover { background: rgba(148, 163, 184, 0.14) !important; color: white !important; }
    body.dark-mode .nav-item.active { background: var(--accent-blue) !important; color: white !important; }
    body.dark-mode .sidebar-submenu { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3) !important; }
    body.dark-mode .submenu-item { color: #cbd5e1 !important; }
    body.dark-mode .submenu-item:hover { background: rgba(148, 163, 184, 0.14) !important; color: white !important; }
    body.dark-mode .sidebar-dropdown-link { color: #cbd5e1 !important; border-bottom-color: #334155 !important; }
    body.dark-mode .sidebar-dropdown-link:hover { background: rgba(248, 250, 252, 0.05) !important; color: white !important; }
    body.dark-mode .sidebar-account { background: rgba(248, 250, 252, 0.09) !important; border-color: rgba(148, 163, 184, 0.24) !important; color: #e2e8f0 !important; }
    body.dark-mode .sidebar-account:hover { background: rgba(248, 250, 252, 0.14) !important; border-color: rgba(148, 163, 184, 0.36) !important; }
    body.dark-mode .sidebar-account-name { color: #f8fafc !important; }
    body.dark-mode .sidebar-account-role { color: #94a3b8 !important; }
    body.dark-mode .sidebar-footer { border-color: rgba(148, 163, 184, 0.15) !important; }

    body.dark-mode .sidebar-logout-dropdown { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4) !important; }
    body.dark-mode .sidebar-dropdown-header { border-bottom-color: #334155 !important; color: #94a3b8 !important; }
    body.dark-mode .sidebar-dropdown-header b { color: #f8fafc !important; }
    body.dark-mode .sidebar-logout-btn:hover { background: rgba(248, 113, 113, 0.1) !important; }

    body.dark-mode .header {
        background: transparent !important;
        border-color: transparent !important;
    }
    body.dark-mode .header h2 { color: white !important; }
    body.dark-mode .header p { color: #94a3b8 !important; }
    
    body.dark-mode .user-pill {
        background: rgba(248, 250, 252, 0.09) !important;
        border-color: rgba(148, 163, 184, 0.24) !important;
        color: white !important;
    }
    body.dark-mode .user-pill div { color: #f8fafc !important; }
    
    body.dark-mode .logout-dropdown { background: #1e293b !important; border-color: #334155 !important; }
    body.dark-mode .dropdown-header { border-color: #334155 !important; color: #94a3b8 !important; }
    body.dark-mode .dropdown-header b { color: white !important; }
    body.dark-mode .logout-btn { color: #ef4444 !important; }
    body.dark-mode .logout-btn:hover { background: rgba(248, 250, 252, 0.05) !important; }
    
    body.dark-mode .content-body .panel,
    body.dark-mode .panel,
    body.dark-mode .report-panel,
    body.dark-mode .settings-card,
    body.dark-mode .records-card,
    body.dark-mode .content-card { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important; }
    body.dark-mode .panel h3, body.dark-mode .panel h2,
    body.dark-mode .report-panel h3, body.dark-mode .report-panel h2,
    body.dark-mode .settings-card h3, body.dark-mode .records-card h3,
    body.dark-mode .content-card h3 { color: white !important; }
    
    body.dark-mode .stat-card { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important; }
    body.dark-mode .stat-card h2 { color: white !important; }
    body.dark-mode .stat-card p { color: #94a3b8 !important; }
    
    body.dark-mode .chart-tile { background: #0f172a !important; border-color: #334155 !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important; }
    body.dark-mode .chart-tile h4 { color: white !important; }
    
    body.dark-mode .form-group label { color: #cbd5e1 !important; }
    
    body.dark-mode td strong { color: white !important; }
    
    body.dark-mode .action-link { background: rgba(130, 78, 57, 0.2) !important; color: #d29d8a !important; }
    body.dark-mode .action-link:hover { background: rgba(130, 78, 57, 0.4) !important; color: white !important; }
    
    body.dark-mode table th { border-bottom-color: #334155 !important; color: #94a3b8 !important; }
    body.dark-mode table td { border-bottom-color: #334155 !important; color: #e2e8f0 !important; }
    
    body.dark-mode .badge { background: rgba(248, 250, 252, 0.09) !important; color: #e2e8f0 !important; }
    body.dark-mode .badge-success { background: rgba(22, 101, 52, 0.3) !important; color: #4ade80 !important; }
    
    body.dark-mode .report-tabs { background: #0f172a !important; border-color: #334155 !important; }
    body.dark-mode .report-tab { color: #94a3b8 !important; }
    body.dark-mode .report-tab:hover { color: white !important; background: rgba(248, 250, 252, 0.05) !important; }
    body.dark-mode .report-tab.active { background: var(--accent-blue) !important; color: white !important; }
    body.dark-mode .hh-group-header { background: #0f172a !important; border-left-color: var(--accent-blue) !important; }
    body.dark-mode .hh-group-header h4 { color: white !important; }
    
    body.dark-mode .modal-content, body.dark-mode .modal-container, body.dark-mode .edit-card { background: #1e293b !important; border-color: #334155 !important; }
    body.dark-mode .modal-container h2, body.dark-mode .edit-card .header h2 { color: white !important; }
    body.dark-mode .modal-container label, body.dark-mode .edit-card label { color: #cbd5e1 !important; }
    
    body.dark-mode .checkbox-container { background: #0f172a !important; border-color: #334155 !important; }
    body.dark-mode .checkbox-container label { color: #e2e8f0 !important; }
    body.dark-mode .benefits-title { color: white !important; }

    body.dark-mode .suggestion-list { background: #1e293b !important; border-color: #334155 !important; }
    body.dark-mode .suggestion-option { background: #1e293b !important; color: white !important; }
    body.dark-mode .suggestion-option:hover, body.dark-mode .suggestion-option:focus { background: #334155 !important; }

    body.dark-mode input:-webkit-autofill,
    body.dark-mode input:-webkit-autofill:hover, 
    body.dark-mode input:-webkit-autofill:focus, 
    body.dark-mode input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 30px #0f172a inset !important;
        -webkit-text-fill-color: white !important;
    }
    body.dark-mode .modal-header, body.dark-mode tr.modal-header { background: rgba(248, 250, 252, 0.05) !important; border-color: #334155 !important; }
    body.dark-mode .modal-header h3 { color: white !important; }
    body.dark-mode .modal-close { color: #94a3b8 !important; }
    body.dark-mode .modal-table th { border-bottom-color: #334155 !important; color: #94a3b8 !important; }
    body.dark-mode .modal-table td { border-bottom-color: #334155 !important; color: #e2e8f0 !important; }
    body.dark-mode .status-badge { background: rgba(22, 101, 52, 0.3) !important; color: #4ade80 !important; }
    body.dark-mode .status-badge.pending { background: rgba(153, 27, 27, 0.3) !important; color: #f87171 !important; }
    
    body.dark-mode input, body.dark-mode select, body.dark-mode textarea {
        background: #0f172a !important; border-color: #334155 !important; color: white !important;
    }
    body.dark-mode input:disabled, body.dark-mode select:disabled {
        background: #1e293b !important; color: #94a3b8 !important; border-color: #334155 !important;
    }

    @media (max-width: 768px) {
        body {
            flex-direction: column !important;
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh !important;
        }
        .sidebar {
            width: 100% !important;
            min-width: 100% !important;
            height: auto !important;
            max-height: 100vh !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            margin: 0 !important;
            border-radius: 0 !important;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12) !important;
            border-bottom: 1px solid #e2e8f0 !important;
            overflow-y: auto !important;
            z-index: 9999 !important;
        }
        body.dark-mode .sidebar {
            border-bottom: 1px solid #334155 !important;
        }
        .sidebar-header {
            height: 70px !important;
            margin-bottom: 0 !important;
            padding: 12px 20px !important;
        }
        .sidebar.collapsed {
            width: 100% !important;
            min-width: 100% !important;
        }
        .sidebar.collapsed .brand-group {
            opacity: 1 !important;
            pointer-events: auto !important;
            display: flex !important;
        }
        .sidebar.collapsed .brand-text {
            display: block !important;
            opacity: 1 !important;
            width: auto !important;
            max-width: calc(100vw - 140px) !important;
            margin-left: 12px !important;
        }
        .sidebar.collapsed .sidebar-header {
            justify-content: flex-start !important;
            padding: 12px 20px !important;
        }
        .toggle-icon {
            display: block !important;
            position: absolute !important;
            right: 20px !important;
            top: 24px !important;
            transform: none !important;
            font-size: 26px !important;
        }
        .sidebar-logout-dropdown {
            position: static !important;
            left: auto !important;
            right: auto !important;
            bottom: auto !important;
            width: 100% !important;
            margin: 8px 0 0 !important;
            border-radius: 14px !important;
            overflow: hidden !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12) !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-header {
            padding: 14px 12px !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            width: 100% !important;
            min-height: 44px !important;
            padding: 12px 14px !important;
            box-sizing: border-box !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link.has-submenu {
            flex-wrap: wrap !important;
            position: relative !important;
            white-space: normal !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link.has-submenu > i:first-child {
            margin: 0 !important;
            width: 16px !important;
            min-width: 16px !important;
            text-align: center !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link.has-submenu .submenu-arrow {
            display: inline-flex !important;
            margin-left: auto !important;
            transition: transform 0.18s ease !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link.has-submenu.open .submenu-arrow {
            transform: rotate(90deg) !important;
        }
        #sidebarLogoutDropdown .sidebar-submenu {
            position: static !important;
            flex-basis: calc(100% + 28px) !important;
            width: calc(100% + 28px) !important;
            margin: 12px -14px -12px !important;
            padding: 0 !important;
            border: 0 !important;
            border-top: 1px solid #e5e7eb !important;
            border-radius: 0 !important;
            background: #f8fafc !important;
            box-shadow: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transform: none !important;
            display: none !important;
        }
        #sidebarLogoutDropdown .sidebar-dropdown-link.has-submenu.open .sidebar-submenu {
            display: flex !important;
            flex-direction: column !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        #sidebarLogoutDropdown .submenu-item {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            width: 100% !important;
            padding: 12px 14px 12px 42px !important;
            border-top: 1px solid #eef2f6 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
        }
        #sidebarLogoutDropdown .submenu-item:first-child {
            border-top: 0 !important;
        }
        #sidebarLogoutDropdown .sidebar-logout-btn {
            min-height: 44px !important;
        }
        body.dark-mode #sidebarLogoutDropdown.sidebar-logout-dropdown {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        body.dark-mode #sidebarLogoutDropdown .sidebar-dropdown-header {
            border-bottom-color: #334155 !important;
            color: #94a3b8 !important;
        }
        body.dark-mode #sidebarLogoutDropdown .sidebar-dropdown-header b {
            color: #f8fafc !important;
        }
        body.dark-mode #sidebarLogoutDropdown .sidebar-dropdown-link {
            color: #cbd5e1 !important;
            border-bottom-color: #334155 !important;
        }
        body.dark-mode #sidebarLogoutDropdown .sidebar-submenu {
            background: #0f172a !important;
            border-top-color: #334155 !important;
        }
        body.dark-mode #sidebarLogoutDropdown .submenu-item {
            color: #cbd5e1 !important;
            border-top-color: #334155 !important;
        }
        .sidebar.collapsed .toggle-icon {
            right: 20px !important;
            transform: none !important;
        }
        .sidebar.collapsed .nav-menu,
        .sidebar.collapsed .sidebar-footer {
            display: none !important;
        }
        .nav-menu {
            display: flex !important;
            flex-direction: column !important;
            padding: 10px 20px !important;
        }
        .sidebar-footer {
            padding: 10px 20px !important;
        }
        .main-container {
            width: 100% !important;
            overflow-y: visible !important;
            margin-top: 70px !important;
            padding-bottom: 40px !important;
        }
        header.top-header, header.header, .top-header, .header {
            padding: 16px 20px 8px 20px !important;
            min-height: 60px !important;
        }
        header.top-header h2, header.header h2, .top-header h2, .header h2 {
            font-size: 24px !important;
        }
        .content-body {
            padding: 8px 20px 30px 20px !important;
        }
    }

    @media print {
        .sidebar { display: none !important; }
        body { background: white !important; }
    }
</style>
<script>
    document.body.classList.add('preload-transitions');
</script>
<script>
(function(){
    function initLogoutSubmenuToggles(){
        const mq = window.matchMedia('(max-width: 768px)');
        const setup = function(){
            const container = document.getElementById('sidebarLogoutDropdown');
            if (!container) return;
            const links = container.querySelectorAll('.sidebar-dropdown-link.has-submenu');
            links.forEach(link => {
                const newLink = link.cloneNode(true);
                link.parentNode.replaceChild(newLink, link);
            });
            const freshLinks = container.querySelectorAll('.sidebar-dropdown-link.has-submenu');
            freshLinks.forEach(link => {
                link.classList.remove('open');
                link.addEventListener('click', function(e){
                    if (e.target.closest('.sidebar-submenu')) return;
                    this.classList.toggle('open');
                });
            });
        };
        if (mq.matches) setup();
        mq.addEventListener && mq.addEventListener('change', (ev)=>{ if (ev.matches) setup(); });
    }
    document.addEventListener('DOMContentLoaded', initLogoutSubmenuToggles);
})();
</script>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-group" id="brandGroup">
            <img src="logo/logopulantubig.png" class="logo" alt="Barangay Logo" style="cursor: pointer;" onclick="if(document.getElementById('sidebar').classList.contains('collapsed')) toggleSidebar()"> 
            <div class="brand-text" style="cursor: pointer;" onclick="if(document.getElementById('sidebar').classList.contains('collapsed')) toggleSidebar()"><b>Barangay Pulantubig</b><span>Residents Profiling System</span></div>
        </div>
        <i class="fa-solid fa-chevron-left toggle-icon" id="toggleBtn" onclick="toggleSidebar()"></i>
    </div>

    <nav class="nav-menu">
        <?php foreach ($nav_items as $item): ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="nav-item<?php echo left_nav_active($current_page, $item['pages']); ?>">
                <i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i>
                <span class="nav-text"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-profile-container" id="sidebarProfileContainer">
            <div class="sidebar-account" id="sidebarAccountBtn" onclick="toggleSidebarAccountDropdown()">
                <div class="sidebar-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="sidebar-account-meta">
                    <div class="sidebar-account-name"><?php echo htmlspecialchars($role_title); ?></div>
                    <div class="sidebar-account-role"><?php echo htmlspecialchars($role_subtitle); ?></div>
                </div>
                <i class="fa-solid fa-chevron-down sidebar-account-caret"></i>
            </div>

            <div class="sidebar-logout-dropdown" id="sidebarLogoutDropdown">
                <div class="sidebar-dropdown-header">Signed in as<br><b><?php echo htmlspecialchars($role_subtitle); ?></b></div>
                <?php if ($show_archived_link): ?>
                    <div class="sidebar-dropdown-link has-submenu">
                        <i class="fa-solid fa-box-archive"></i> Archived
                        <i class="fa-solid fa-caret-right submenu-arrow" style="margin-left: auto;"></i>
                        
                        <div class="sidebar-submenu">
                            <a href="archived_residents.php" class="submenu-item">
                                <i class="fa-solid fa-users-slash"></i> Residents
                            </a>
                            <a href="archived_activities.php" class="submenu-item">
                                <i class="fa-solid fa-clipboard-check"></i> Activities
                            </a>
                            <a href="archived_users.php" class="submenu-item">
                                <i class="fa-solid fa-user-clock"></i> Users
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="sidebar-dropdown-link has-submenu">
                    <i class="fa-solid fa-palette"></i> Theme
                    <i class="fa-solid fa-caret-right submenu-arrow" style="margin-left: auto;"></i>
                    
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item theme-switch" data-theme="light">
                            <i class="fa-solid fa-sun" style="color: #64748b;"></i> Light Mode
                        </a>
                        <a href="#" class="submenu-item theme-switch" data-theme="dark">
                            <i class="fa-solid fa-moon" style="color: #64748b;"></i> Dark Mode
                        </a>
                    </div>
                </div>

                <a href="settings.php" class="sidebar-dropdown-link">
                    <i class="fa-solid fa-gear"></i> Settings
                </a>
                <a href="logout.php" class="sidebar-logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    const mobileToggleQuery = window.matchMedia('(max-width: 768px)');

    function syncToggleIcon(icon, isCollapsed) {
        if (!icon) return;

        icon.classList.remove('fa-chevron-left', 'fa-chevron-right', 'fa-bars', 'fa-times');

        if (mobileToggleQuery.matches) {
            icon.classList.add(isCollapsed ? 'fa-bars' : 'fa-times');
            return;
        }

        icon.classList.add(isCollapsed ? 'fa-chevron-right' : 'fa-chevron-left');
    }

    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
        document.body.classList.add('sidebar-is-collapsed');
        const toggleBtn = document.getElementById('toggleBtn');
        if (toggleBtn) {
            syncToggleIcon(toggleBtn, true);
        }
    }

    function closeSidebarAccountDropdown() {
        const dropdown = document.getElementById('sidebarLogoutDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }

    function toggleSidebarAccountDropdown() {
        const dropdown = document.getElementById('sidebarLogoutDropdown');
        if (!dropdown) return;
        dropdown.classList.toggle('show');
    }

    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.getElementById('toggleBtn');
        if (!sidebar || !icon) return;

        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-is-collapsed');
        closeSidebarAccountDropdown();

        syncToggleIcon(icon, sidebar.classList.contains('collapsed'));

        if (sidebar.classList.contains('collapsed')) {
            localStorage.setItem('sidebar-collapsed', 'true');
        } else {
            localStorage.setItem('sidebar-collapsed', 'false');
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.getElementById('toggleBtn');
        if (!sidebar || !icon) return;

        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.body.classList.add('sidebar-is-collapsed');
            sidebar.classList.add('collapsed');
        }

        syncToggleIcon(icon, sidebar.classList.contains('collapsed'));

        if (mobileToggleQuery.addEventListener) {
            mobileToggleQuery.addEventListener('change', () => {
                syncToggleIcon(icon, sidebar.classList.contains('collapsed'));
            });
        } else if (mobileToggleQuery.addListener) {
            mobileToggleQuery.addListener(() => {
                syncToggleIcon(icon, sidebar.classList.contains('collapsed'));
            });
        }

        setTimeout(() => {
            document.body.classList.remove('preload-transitions');
        }, 100);
    });

    window.addEventListener('click', function(e) {
        if (!e.target.closest('#sidebarProfileContainer')) {
            closeSidebarAccountDropdown();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        const themeSwitches = document.querySelectorAll('.theme-switch');
        
        themeSwitches.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const theme = btn.getAttribute('data-theme');
                if (theme === 'dark') {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
            });
        });

        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    });
</script>

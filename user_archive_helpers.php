<?php
if (!function_exists('user_column_exists')) {
    function user_column_exists($conn, $column) {
        $safe_column = mysqli_real_escape_string($conn, $column);
        $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$safe_column'");
        return $check && mysqli_num_rows($check) > 0;
    }
}

if (!function_exists('ensure_user_archive_columns')) {
    function ensure_user_archive_columns($conn) {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        if (!user_column_exists($conn, 'is_archived')) {
            mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!user_column_exists($conn, 'archived_at')) {
            mysqli_query($conn, "ALTER TABLE users ADD COLUMN archived_at DATETIME NULL AFTER is_archived");
        }

        $ready = user_column_exists($conn, 'is_archived') && user_column_exists($conn, 'archived_at');
        return $ready;
    }
}

if (!function_exists('archived_user_role_label')) {
    function archived_user_role_label($role) {
        if ($role === 'Barangay Captain' || $role === 'Captain' || $role === 'Former Captain') {
            return 'Former Captain';
        }

        if ($role === 'Secretary' || $role === 'Former Secretary') {
            return 'Former Secretary';
        }

        return 'Former User';
    }
}

if (!function_exists('active_captain_exists')) {
    function active_captain_exists($conn, $exclude_id = 0) {
        $exclude_id = (int)$exclude_id;
        $archive_filter = user_column_exists($conn, 'is_archived') ? " AND COALESCE(is_archived, 0) = 0" : "";
        $exclude_filter = $exclude_id > 0 ? " AND id != '$exclude_id'" : "";
        $query = mysqli_query(
            $conn,
            "SELECT id FROM users WHERE role IN ('Barangay Captain', 'Captain')$archive_filter$exclude_filter LIMIT 1"
        );

        return $query && mysqli_num_rows($query) > 0;
    }
}

if (!function_exists('user_role_badge_class')) {
    function user_role_badge_class($role) {
        if ($role === 'Barangay Captain' || $role === 'Captain') {
            return 'role-captain';
        }

        if ($role === 'Secretary') {
            return 'role-secretary';
        }

        if ($role === 'Former Secretary') {
            return 'role-former-secretary';
        }

        return 'role-former';
    }
}

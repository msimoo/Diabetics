<?php
/**
 * Lookup Helper Functions — For accessing settings-driven dropdowns
 * Include this file to get formatted options from lookup tables
 */

/**
 * Get cities as HTML options for a select dropdown
 */
function get_cities_options($mysqli, $selected = null) {
    $result = $mysqli->query("SELECT city_id, city_name_ar FROM cities WHERE is_active = 1 ORDER BY city_name_ar ASC");
    $html = '<option value="">-- اختر المدينة --</option>';
    while ($row = $result->fetch_assoc()) {
        $sel = $row['city_id'] == $selected ? 'selected' : '';
        $html .= "<option value=\"" . escape_output($row['city_name_ar']) . "\" data-id=\"{$row['city_id']}\" $sel>" . escape_output($row['city_name_ar']) . "</option>";
    }
    return $html;
}

/**
 * Get diabetes types as HTML options
 */
function get_diabetes_types_options($mysqli, $selected = null) {
    $result = $mysqli->query("SELECT type_name_ar FROM diabetes_types WHERE is_active = 1 ORDER BY sort_order ASC, type_name_ar ASC");
    $html = '<option value="">-- اختر نوع السكري --</option>';
    while ($row = $result->fetch_assoc()) {
        $sel = $row['type_name_ar'] === $selected ? 'selected' : '';
        $html .= "<option value=\"" . escape_output($row['type_name_ar']) . "\" $sel>" . escape_output($row['type_name_ar']) . "</option>";
    }
    return $html;
}

/**
 * Get lookup options by category as HTML options
 */
function get_lookup_options($mysqli, $category, $selected = null, $placeholder = '-- اختر --') {
    $result = $mysqli->query("SELECT option_value_ar FROM lookup_options WHERE category = '$category' AND is_active = 1 ORDER BY sort_order ASC, option_value_ar ASC");
    $html = "<option value=\"\">$placeholder</option>";
    while ($row = $result->fetch_assoc()) {
        $sel = $row['option_value_ar'] === $selected ? 'selected' : '';
        $html .= "<option value=\"" . escape_output($row['option_value_ar']) . "\" $sel>" . escape_output($row['option_value_ar']) . "</option>";
    }
    return $html;
}

/**
 * Get medications as HTML options
 */
function get_medications_options($mysqli, $category_id = null, $selected = null) {
    $where = $category_id ? "WHERE m.category_id = $category_id AND m.is_active = 1" : "WHERE m.is_active = 1";
    $result = $mysqli->query("SELECT m.medication_id, m.name_ar, m.strength, c.name_ar as cat_name FROM medications m LEFT JOIN medication_categories c ON m.category_id = c.category_id $where ORDER BY m.name_ar ASC");
    $html = '<option value="">-- اختر الدواء --</option>';
    while ($row = $result->fetch_assoc()) {
        $sel = $row['medication_id'] == $selected ? 'selected' : '';
        $label = $row['name_ar'] . ($row['strength'] ? ' (' . $row['strength'] . ')' : '');
        $html .= "<option value=\"{$row['medication_id']}\" $sel>" . escape_output($label) . "</option>";
    }
    return $html;
}

/**
 * Ensure lookup tables exist and have default data
 */
function ensure_lookup_tables_exist($mysqli) {
    // Create tables if they don't exist
    $mysqli->query("CREATE TABLE IF NOT EXISTS cities (
        city_id INT AUTO_INCREMENT PRIMARY KEY, city_name_ar VARCHAR(100) NOT NULL,
        city_name_en VARCHAR(100), is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $mysqli->query("CREATE TABLE IF NOT EXISTS lookup_options (
        option_id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(50) NOT NULL,
        option_value_ar VARCHAR(200) NOT NULL, option_value_en VARCHAR(200),
        sort_order INT DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $mysqli->query("CREATE TABLE IF NOT EXISTS auto_instructions (
        instruction_id INT AUTO_INCREMENT PRIMARY KEY, title_ar VARCHAR(200) NOT NULL,
        content_ar TEXT NOT NULL, category ENUM('عام','تغذية','عناية قدم','أدوية','طوارئ','تمارين') NOT NULL DEFAULT 'عام',
        diabetes_type VARCHAR(100), min_hba1c DECIMAL(4,1), max_hba1c DECIMAL(4,1),
        min_wagner INT, max_wagner INT, has_wound TINYINT(1), smoking_status VARCHAR(20),
        is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $mysqli->query("CREATE TABLE IF NOT EXISTS role_permissions (
        permission_id INT AUTO_INCREMENT PRIMARY KEY, role VARCHAR(50) NOT NULL,
        page_module VARCHAR(100) NOT NULL, can_view TINYINT(1) NOT NULL DEFAULT 1,
        can_create TINYINT(1) NOT NULL DEFAULT 0, can_edit TINYINT(1) NOT NULL DEFAULT 0,
        can_delete TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_role_page (role, page_module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Check users table has the right role values
    $columns = [];
    $col_result = $mysqli->query("SHOW COLUMNS FROM users");
    while ($c = $col_result->fetch_assoc()) { $columns[] = $c['Field']; }
    
    if (in_array('role', $columns)) {
        // Map old roles before altering the column to avoid enum constraint failures
        $mysqli->query("UPDATE users SET role = 'nurse' WHERE role NOT IN ('super_admin','admin','doctor','nurse')");
        $mysqli->query("UPDATE users SET role = 'doctor' WHERE role = 'user'");
        $result = $mysqli->query("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','doctor','medical_assistant','nurse') NOT NULL DEFAULT 'nurse'");
        if (!$result) {
            // Silently ignore if ALTER fails — the database_upgrade script handles this
        }
    }

    // Insert default data if empty
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM cities")->fetch_assoc()['cnt'];
    if ($count === 0) {
        $defaults = [
            ['الرياض', 'Riyadh'], ['جدة', 'Jeddah'], ['مكة المكرمة', 'Makkah'],
            ['المدينة المنورة', 'Madinah'], ['الدمام', 'Dammam'], ['الخبر', 'Khobar'],
            ['أبها', 'Abha'], ['تبوك', 'Tabuk'], ['بريدة', 'Buraydah'], ['حائل', 'Hail'],
        ];
        $stmt = $mysqli->prepare("INSERT INTO cities (city_name_ar, city_name_en) VALUES (?, ?)");
        foreach ($defaults as $c) { $stmt->bind_param('ss', $c[0], $c[1]); $stmt->execute(); }
    }
}
?>

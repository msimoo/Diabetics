<?php
/**
 * AJAX Patient Search API
 * Returns JSON array of matching patients
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = '%' . $mysqli->real_escape_string($q) . '%';

$stmt = $mysqli->prepare("SELECT patient_id, file_number, full_name, phone_primary,
                          DATE_FORMAT(date_of_birth, '%Y-%m-%d') as dob,
                          TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age,
                          gender
                          FROM patients
                          WHERE is_active = 1
                          AND (full_name LIKE ? OR file_number LIKE ? OR phone_primary LIKE ?)
                          LIMIT 10");
$stmt->bind_param('sss', $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients, JSON_UNESCAPED_UNICODE);
?>

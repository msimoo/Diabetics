<?php
/**
 * Patient API
 * Returns JSON data for a single patient
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'get':
        if (!$id) { echo json_encode(['error' => 'No patient ID']); exit; }
        $stmt = $mysqli->prepare("SELECT p.*, 
                                  (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.patient_id) as visit_count,
                                  (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
                                  FROM patients p WHERE p.patient_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        echo json_encode($patient ?: ['error' => 'Patient not found'], JSON_UNESCAPED_UNICODE);
        break;

    case 'list':
        $stmt = $mysqli->query("SELECT patient_id, file_number, full_name, phone_primary FROM patients WHERE is_active = 1 ORDER BY full_name LIMIT 50");
        $patients = [];
        while ($row = $stmt->fetch_assoc()) {
            $patients[] = $row;
        }
        echo json_encode($patients, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>

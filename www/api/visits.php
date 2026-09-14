<?php
/**
 * Visits API
 * Returns JSON data for visits
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$action = $_GET['action'] ?? '';
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

switch ($action) {
    case 'get':
        if (!$visit_id) { echo json_encode(['error' => 'No visit ID']); exit; }
        $stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number, u.full_name as doctor_name
                                  FROM visits v 
                                  JOIN patients p ON v.patient_id = p.patient_id
                                  LEFT JOIN users u ON v.created_by = u.user_id
                                  WHERE v.visit_id = ?");
        $stmt->bind_param('i', $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();
        echo json_encode($visit ?: ['error' => 'Visit not found'], JSON_UNESCAPED_UNICODE);
        break;

    case 'list_by_patient':
        if (!$patient_id) { echo json_encode(['error' => 'No patient ID']); exit; }
        $stmt = $mysqli->prepare("SELECT v.*, u.full_name as doctor_name
                                  FROM visits v 
                                  LEFT JOIN users u ON v.created_by = u.user_id
                                  WHERE v.patient_id = ? 
                                  ORDER BY v.visit_date DESC LIMIT 20");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        echo json_encode($visits, JSON_UNESCAPED_UNICODE);
        break;

    case 'recent':
        $stmt = $mysqli->query("SELECT v.*, p.full_name, p.file_number 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.patient_id 
                                ORDER BY v.created_at DESC LIMIT 10");
        $visits = [];
        while ($row = $stmt->fetch_assoc()) {
            $visits[] = $row;
        }
        echo json_encode($visits, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>

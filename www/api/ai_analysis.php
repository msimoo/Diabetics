<?php
/**
 * ai_analysis.php — AI Analysis API Endpoint
 * Called via AJAX from modules/ai/analyze.php for re-analysis
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_login();

require_once __DIR__ . '/../includes/ai_client.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $patient_id = isset($input['patient_id']) ? (int)$input['patient_id'] : 0;
    
    if (!$patient_id) {
        echo json_encode(['success' => false, 'error' => '❌ Patient ID is required']);
        exit;
    }
    
    $result = analyzePatient($patient_id);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => '❌ POST method required']);
}

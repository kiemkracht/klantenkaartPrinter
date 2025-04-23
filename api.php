<?php

header('Content-Type: application/json');

// Configuration
$allowedKeys = ['abc123']; // voeg hier de api keys toe voor elke winkel
$dbConfig = [
    'host' => 'ID304765_authkiemkracht.db.webhosting.be',
    'port' => '3306',
    'user' => 'ID304765_authkiemkracht',
    'pass' => 'S545n79S2o4nWD6KJg5g',
    'name' => 'ID304765_authkiemkracht'
];


// Valideren van de API Key 
$key = $_GET['key'] ?? '';
if (!in_array($key, $allowedKeys)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$action = $_GET['action'] ?? '';
$mysqli = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name'], $dbConfig['port']);

if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($action === 'next') {
    $queue = $_GET['queue'] ?? '';
    $limit = intval($_GET['limit'] ?? 1);
    if (!$queue) {
        echo json_encode([]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT p.id, p.barcode, u.voornaam FROM printqueues p JOIN users u ON p.user_id = u.id WHERE p.printed_at IS NULL AND p.queue = ? ORDER BY p.id ASC LIMIT ?");
    $stmt->bind_param('si', $queue, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }

    echo json_encode($tickets);
    exit;

} elseif ($action === 'mark') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE printqueues SET printed_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        echo json_encode(['status' => $success ? 'ok' : 'error']);
        exit;
    }
}

// als er geen valid data is:
echo json_encode(['error' => 'Invalid request']);
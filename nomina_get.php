<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

// disciplinaId normalizado: futbol / basket / voleibol
$disciplinaId = trim($_GET['disciplinaId'] ?? '');

if (!$disciplinaId) {
    // Sin filtro → devuelve todos
    $stmt = $pdo->query('SELECT * FROM estudiantes ORDER BY nombre ASC');
} else {
    $stmt = $pdo->prepare('SELECT * FROM estudiantes WHERE disciplinaId = ? ORDER BY nombre ASC');
    $stmt->execute([$disciplinaId]);
}

$estudiantes = $stmt->fetchAll();

echo json_encode(['ok' => true, 'data' => $estudiantes]);

<?php
// ── recuperar.php ─────────────────────────────────────────────────────────
// POST { email } → { cedula, password, nombre } | { error }
// ─────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { echo json_encode(['error' => 'Método no permitido']); exit; }

require_once __DIR__ . '/db.php';

$body = json_decode(file_get_contents('php://input'), true);
$email = isset($body['email']) ? strtolower(trim($body['email'])) : '';

if (empty($email)) {
    echo json_encode(['error' => 'El correo es requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT cedula, password, nombre FROM usuarios WHERE emailRecuperacion = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['error' => 'Correo no registrado']);
        exit;
    }

    echo json_encode([
        'cedula'   => $user['cedula'],
        'password' => $user['password'],
        'nombre'   => $user['nombre'],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos']);
}

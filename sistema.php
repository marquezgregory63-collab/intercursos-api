<?php
// ── sistema.php ───────────────────────────────────────────────────────────
// DELETE {confirmar:true} → borra partidos + posiciones + estudiantes
//                           NO borra usuarios
// ─────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE')  { echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }

require_once __DIR__ . '/db.php';

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$confirmar = $body['confirmar'] ?? false;

if (!$confirmar) {
    echo json_encode(['ok' => false, 'msg' => 'Confirmación requerida.']);
    exit;
}

try {
    // historial_puntos y stats_partidos se borran en CASCADE al borrar partidos
    $pdo->exec('DELETE FROM posiciones');
    $pdo->exec('DELETE FROM partidos');
    $pdo->exec('DELETE FROM estudiantes');

    echo json_encode(['ok' => true, 'msg' => 'Sistema vaciado. Partidos, posiciones y nómina eliminados.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al vaciar el sistema.']);
}

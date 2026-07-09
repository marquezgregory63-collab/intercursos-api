<?php
// ── posiciones_get.php ────────────────────────────────────────────────────
// GET ?disciplinaId=futbol
// Devuelve { ok:true, data:[...posiciones ordenadas por puntos] }
// ─────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$disciplinaId = isset($_GET['disciplinaId']) ? trim($_GET['disciplinaId']) : '';

if (empty($disciplinaId)) {
    echo json_encode(['ok' => false, 'error' => 'disciplinaId requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT idEquipo, disciplinaId, pj, pg, pe, pp, gf, gc, puntos
        FROM posiciones
        WHERE disciplinaId = ?
        ORDER BY puntos DESC, (gf - gc) DESC, gf DESC
    ');
    $stmt->execute([$disciplinaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Castear a int para que el front no reciba strings
    $data = array_map(function($r) {
        return [
            'idEquipo'    => $r['idEquipo'],
            'disciplinaId'=> $r['disciplinaId'],
            'pj'   => (int)$r['pj'],
            'pg'   => (int)$r['pg'],
            'pe'   => (int)$r['pe'],
            'pp'   => (int)$r['pp'],
            'gf'   => (int)$r['gf'],
            'gc'   => (int)$r['gc'],
            'puntos'=> (int)$r['puntos'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'data' => $data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en la base de datos']);
}

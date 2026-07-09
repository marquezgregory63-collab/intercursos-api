<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Estudiantes por idEquipo + disciplinaId (para modal anotador) ────────
if ($method === 'GET') {
    $idEquipo     = trim($_GET['idEquipo']     ?? '');
    $disciplinaId = trim($_GET['disciplinaId'] ?? '');

    if (!$idEquipo || !$disciplinaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'idEquipo y disciplinaId son requeridos.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT id, nombre, apellido, cedula, puntos_totales,
               goles_totales, tl_totales, dobles_totales, triples_totales,
               kills_totales, aces_totales, bloqueos_totales
        FROM estudiantes
        WHERE idEquipo = ? AND disciplinaId = ?
        ORDER BY nombre ASC
    ');
    $stmt->execute([$idEquipo, $disciplinaId]);
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// ── PUT: Guardar estadísticas del partido ─────────────────────────────────────
if ($method === 'PUT') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $partidoId = intval($body['partidoId'] ?? 0);
    $stats     = $body['stats'] ?? [];

    if (!$partidoId || empty($stats)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'partidoId y stats son requeridos.']);
        exit;
    }

    // Todos los campos posibles de stats_partidos
    $campos = [
        'pos1','pos2','rem1','rem2','arc1','arc2','cor1','cor2',
        'fal1','fal2','ama1','ama2','par1','par2','roj1','roj2',
        'fgm1','fgm2','fga1','fga2','tp1','tp2','tpa1','tpa2',
        'ftm1','ftm2','fta1','fta2','ast1','ast2','oreb1','oreb2',
        'dreb1','dreb2','stl1','stl2','blk1','blk2',
        'ace1','ace2','kill1','kill2','blkp1','blkp2','dig1','dig2',
    ];

    // Construir SET dinámico solo con los campos recibidos
    $sets   = [];
    $values = [];
    foreach ($campos as $campo) {
        if (isset($stats[$campo])) {
            $sets[]   = "$campo = ?";
            $values[] = intval($stats[$campo]);
        }
    }

    // Ajuste especial: pos2 = 100 - pos1 automático
    if (isset($stats['pos1']) && !isset($stats['pos2'])) {
        $sets[]   = "pos2 = ?";
        $values[] = 100 - intval($stats['pos1']);
    }

    if (empty($sets)) {
        echo json_encode(['ok' => false, 'msg' => 'No hay estadísticas válidas para guardar.']);
        exit;
    }

    $values[] = $partidoId;
    $pdo->prepare("UPDATE stats_partidos SET " . implode(', ', $sets) . " WHERE partido_id = ?")
        ->execute($values);

    echo json_encode(['ok' => true, 'msg' => 'Estadísticas actualizadas correctamente.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);

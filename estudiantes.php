<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

// ── GET: leer nómina ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $disciplinaId = trim($_GET['disciplinaId'] ?? '');
    $idEquipo     = trim($_GET['idEquipo']     ?? '');

    if (!$disciplinaId) {
        echo json_encode(['ok' => false, 'msg' => 'disciplinaId requerido']); exit;
    }
    try {
        if ($idEquipo) {
            // Para selector de anotadores: solo el equipo específico
            $stmt = $pdo->prepare('
                SELECT id, nombre, apellido, cedula, idEquipo,
                       puntos_totales, goles_totales, tl_totales,
                       dobles_totales, triples_totales,
                       kills_totales, aces_totales, bloqueos_totales
                FROM estudiantes WHERE disciplinaId = ? AND idEquipo = ?
                ORDER BY nombre ASC
            ');
            $stmt->execute([$disciplinaId, $idEquipo]);
        } else {
            // Nómina completa de la disciplina
            $stmt = $pdo->prepare('
                SELECT id, nombre, apellido, cedula, ano, mencion, seccion,
                       disciplina, disciplinaId, idEquipo,
                       puntos_totales, goles_totales, tl_totales,
                       dobles_totales, triples_totales,
                       kills_totales, aces_totales, bloqueos_totales
                FROM estudiantes WHERE disciplinaId = ?
                ORDER BY ano ASC, mencion ASC, seccion ASC, nombre ASC
            ');
            $stmt->execute([$disciplinaId]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            foreach (['puntos_totales','goles_totales','tl_totales','dobles_totales',
                      'triples_totales','kills_totales','aces_totales','bloqueos_totales'] as $col) {
                if (isset($r[$col])) $r[$col] = (int)$r[$col];
            }
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Error en la base de datos']);
    }
    exit;
}

// ── POST: inscribir estudiante ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

$body         = json_decode(file_get_contents('php://input'), true) ?? [];
$nombre       = trim($body['nombre']       ?? '');
$apellido     = trim($body['apellido']     ?? '');
$cedula       = trim($body['cedula']       ?? '');   // con prefijo V/E
$tipoDoc      = trim($body['tipoDocumento']?? 'V');
$ano          = trim($body['ano']          ?? '');
$mencion      = trim($body['mencion']      ?? '');
$seccion      = trim($body['seccion']      ?? '');
$disciplina   = trim($body['disciplina']   ?? '');   // display: Fútbol
$disciplinaId = trim($body['disciplinaId'] ?? '');   // normalizado: futbol
$idEquipo     = trim($body['idEquipo']     ?? '');   // FK: 1-TEL-A

if (!$nombre || !$apellido || !$cedula || !$ano || !$mencion || !$seccion || !$disciplina || !$disciplinaId || !$idEquipo) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Todos los campos son requeridos.']);
    exit;
}

// Verificar cédula duplicada en estudiantes
$stmt = $pdo->prepare('SELECT id FROM estudiantes WHERE cedula = ? LIMIT 1');
$stmt->execute([$cedula]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'Esta cédula ya está registrada en la nómina.']);
    exit;
}

$pdo->prepare('
    INSERT INTO estudiantes
        (cedula, tipoDocumento, nombre, apellido, ano, mencion, seccion,
         disciplina, disciplinaId, idEquipo,
         puntos_totales, goles_totales, tl_totales, dobles_totales,
         triples_totales, kills_totales, aces_totales, bloqueos_totales)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0)
')->execute([
    $cedula, $tipoDoc, $nombre, $apellido, $ano, $mencion, $seccion,
    $disciplina, $disciplinaId, $idEquipo
]);

echo json_encode(['ok' => true, 'msg' => 'Estudiante inscrito en nómina correctamente.', 'id' => $pdo->lastInsertId()]);

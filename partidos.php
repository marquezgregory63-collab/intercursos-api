<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST: Crear partido ───────────────────────────────────────────────────────
if ($method === 'POST') {
    $equipoLocal  = trim($body['equipoLocal']  ?? '');
    $equipoVisita = trim($body['equipoVisita'] ?? '');
    $idLocal      = trim($body['idLocal']      ?? '');
    $idVisita     = trim($body['idVisita']     ?? '');
    $disciplinaId = trim($body['disciplinaId'] ?? '');
    $ubicacion    = trim($body['ubicacion']    ?? 'Cancha 1');
    $programacion = trim($body['programacion'] ?? '');

    if (!$equipoLocal || !$equipoVisita || !$idLocal || !$idVisita || !$disciplinaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios.']);
        exit;
    }

    // Insertar partido
    $stmt = $pdo->prepare('
        INSERT INTO partidos
            (disciplinaId, equipoLocal, equipoVisita, idLocal, idVisita,
             goles1, goles2, estado_tiempo, inicio_real_ms, programacion, ubicacion)
        VALUES (?, ?, ?, ?, ?, 0, 0, "Programado", NULL, ?, ?)
    ');
    $stmt->execute([$disciplinaId, $equipoLocal, $equipoVisita, $idLocal, $idVisita, $programacion, $ubicacion]);
    $partidoId = $pdo->lastInsertId();

    // Insertar fila vacía en stats_partidos
    $pdo->prepare('INSERT INTO stats_partidos (partido_id) VALUES (?)')->execute([$partidoId]);

    echo json_encode(['ok' => true, 'id' => $partidoId, 'msg' => 'Partido creado correctamente.']);
    exit;
}

// ── PUT: Iniciar o Finalizar partido ─────────────────────────────────────────
if ($method === 'PUT') {
    $accion    = trim($body['accion']    ?? '');  // 'iniciar' | 'finalizar'
    $partidoId = intval($body['partidoId'] ?? 0);

    if (!$partidoId || !$accion) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'partidoId y accion son requeridos.']);
        exit;
    }

    // Leer el partido actual
    $stmt = $pdo->prepare('SELECT * FROM partidos WHERE id = ? LIMIT 1');
    $stmt->execute([$partidoId]);
    $partido = $stmt->fetch();

    if (!$partido) {
        echo json_encode(['ok' => false, 'msg' => 'Partido no encontrado.']);
        exit;
    }

    if ($accion === 'iniciar') {
        $pdo->prepare('UPDATE partidos SET estado_tiempo = "En Vivo", inicio_real_ms = ? WHERE id = ?')
            ->execute([round(microtime(true) * 1000), $partidoId]);
        echo json_encode(['ok' => true, 'msg' => 'Partido iniciado.']);
        exit;
    }

    if ($accion === 'finalizar') {
        if ($partido['estado_tiempo'] === 'Finalizado') {
            echo json_encode(['ok' => false, 'msg' => 'El partido ya fue finalizado.']);
            exit;
        }

        $pdo->prepare('UPDATE partidos SET estado_tiempo = "Finalizado" WHERE id = ?')->execute([$partidoId]);

        $goles1       = (int)$partido['goles1'];
        $goles2       = (int)$partido['goles2'];
        $disciplinaId = $partido['disciplinaId'];
        $idLocal      = $partido['idLocal'];
        $idVisita     = $partido['idVisita'];

        if (!$idLocal || !$idVisita || !$disciplinaId) {
            echo json_encode(['ok' => true, 'msg' => 'Partido finalizado (sin actualizar posiciones — datos incompletos).']);
            exit;
        }

        // Calcular puntos de tabla
        $pts1=$pts2=$pg1=$pg2=$pe1=$pe2=$pp1=$pp2 = 0;

        if ($goles1 > $goles2) {
            $pg1=1; $pp2=1;
            $pts1 = ($disciplinaId === 'basket') ? 2 : (($disciplinaId === 'voleibol') ? 1 : 3);
        } elseif ($goles2 > $goles1) {
            $pg2=1; $pp1=1;
            $pts2 = ($disciplinaId === 'basket') ? 2 : (($disciplinaId === 'voleibol') ? 1 : 3);
        } elseif ($disciplinaId === 'futbol') {
            // Solo fútbol tiene empates
            $pe1=1; $pe2=1; $pts1=1; $pts2=1;
        }

        // Función para actualizar posiciones con upsert
        $upsert = function($idEq, $pts, $gf, $gc, $win, $draw, $loss) use ($pdo, $disciplinaId) {
            $stmt = $pdo->prepare('SELECT id FROM posiciones WHERE idEquipo = ? AND disciplinaId = ? LIMIT 1');
            $stmt->execute([$idEq, $disciplinaId]);
            $existe = $stmt->fetch();

            if ($existe) {
                $pdo->prepare('
                    UPDATE posiciones
                    SET pj=pj+1, pg=pg+?, pe=pe+?, pp=pp+?, gf=gf+?, gc=gc+?, puntos=puntos+?
                    WHERE idEquipo=? AND disciplinaId=?
                ')->execute([$win, $draw, $loss, $gf, $gc, $pts, $idEq, $disciplinaId]);
            } else {
                $pdo->prepare('
                    INSERT INTO posiciones (idEquipo, disciplinaId, pj, pg, pe, pp, gf, gc, puntos)
                    VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)
                ')->execute([$idEq, $disciplinaId, $win, $draw, $loss, $gf, $gc, $pts]);
            }
        };

        $upsert($idLocal,  $pts1, $goles1, $goles2, $pg1, $pe1, $pp1);
        $upsert($idVisita, $pts2, $goles2, $goles1, $pg2, $pe2, $pp2);

        echo json_encode(['ok' => true, 'msg' => 'Partido finalizado y posiciones actualizadas.']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Accion no reconocida.']);
    exit;
}

// ── DELETE: Eliminar partido (+ posiciones si era Finalizado) ─────────────────
if ($method === 'DELETE') {
    $partidoId = intval($body['partidoId'] ?? 0);

    if (!$partidoId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'partidoId es requerido.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM partidos WHERE id = ? LIMIT 1');
    $stmt->execute([$partidoId]);
    $partido = $stmt->fetch();

    if (!$partido) {
        echo json_encode(['ok' => false, 'msg' => 'Partido no encontrado.']);
        exit;
    }

    // Eliminar partido (CASCADE borra historial_puntos y stats_partidos)
    $pdo->prepare('DELETE FROM partidos WHERE id = ?')->execute([$partidoId]);

    // Si era Finalizado → borrar posiciones asociadas
    if ($partido['estado_tiempo'] === 'Finalizado' && $partido['idLocal'] && $partido['idVisita'] && $partido['disciplinaId']) {
        $pdo->prepare('DELETE FROM posiciones WHERE idEquipo = ? AND disciplinaId = ?')
            ->execute([$partido['idLocal'],  $partido['disciplinaId']]);
        $pdo->prepare('DELETE FROM posiciones WHERE idEquipo = ? AND disciplinaId = ?')
            ->execute([$partido['idVisita'], $partido['disciplinaId']]);
        echo json_encode(['ok' => true, 'msg' => 'Partido y posiciones eliminados correctamente.']);
    } else {
        echo json_encode(['ok' => true, 'msg' => 'Partido eliminado correctamente.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);

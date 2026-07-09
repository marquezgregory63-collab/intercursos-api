<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$CAMPO_DESGLOSE = [
    'Gol'       => 'goles_totales',
    'Tiro Libre'=> 'tl_totales',
    'Doble'     => 'dobles_totales',
    'Triple'    => 'triples_totales',
    'Kill'      => 'kills_totales',
    'Ace'       => 'aces_totales',
    'Bloqueo'   => 'bloqueos_totales',
];

// ── POST: Anotar punto ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $partidoId    = intval($body['partidoId']    ?? 0);
    $estudianteId = intval($body['estudianteId'] ?? 0);
    $numEquipo    = intval($body['numEquipo']    ?? 1);  // 1=Local, 2=Visita
    $puntos       = intval($body['puntos']       ?? 1);
    $tipoEvento   = trim($body['tipoEvento']     ?? 'Punto');
    $anotador     = trim($body['anotador']       ?? '');
    $minuto       = intval($body['minuto']       ?? 0);

    if (!$partidoId || !$estudianteId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'partidoId y estudianteId son requeridos.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Actualizar marcador del partido
        $campoGoles = $numEquipo === 1 ? 'goles1' : 'goles2';
        $pdo->prepare("UPDATE partidos SET $campoGoles = $campoGoles + ? WHERE id = ?")
            ->execute([$puntos, $partidoId]);

        // 2. Insertar en historial_puntos
        $pdo->prepare('
            INSERT INTO historial_puntos
                (partido_id, anotador, equipo, estudiante_id, minuto, puntos, tipoEvento)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([$partidoId, $anotador, $numEquipo, $estudianteId, $minuto, $puntos, $tipoEvento]);

        // 3. Actualizar stats del estudiante
        $campoDesglose = $CAMPO_DESGLOSE[$tipoEvento] ?? null;
        if ($campoDesglose) {
            $pdo->prepare("UPDATE estudiantes SET puntos_totales = puntos_totales + ?, $campoDesglose = $campoDesglose + 1 WHERE id = ?")
                ->execute([$puntos, $estudianteId]);
        } else {
            $pdo->prepare("UPDATE estudiantes SET puntos_totales = puntos_totales + ? WHERE id = ?")
                ->execute([$puntos, $estudianteId]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => "$tipoEvento anotado correctamente."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Error al anotar punto: ' . $e->getMessage()]);
    }
    exit;
}

// ── DELETE: Deshacer último punto ─────────────────────────────────────────────
if ($method === 'DELETE') {
    $partidoId = intval($body['partidoId'] ?? 0);

    if (!$partidoId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'partidoId es requerido.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Obtener el último punto del historial
        $stmt = $pdo->prepare('
            SELECT * FROM historial_puntos
            WHERE partido_id = ?
            ORDER BY id DESC LIMIT 1
        ');
        $stmt->execute([$partidoId]);
        $ultimo = $stmt->fetch();

        if (!$ultimo) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => 'No hay puntos registrados para deshacer.']);
            exit;
        }

        // 1. Borrar el último registro del historial
        $pdo->prepare('DELETE FROM historial_puntos WHERE id = ?')->execute([$ultimo['id']]);

        // 2. Restar al marcador
        $campoGoles = $ultimo['equipo'] == 1 ? 'goles1' : 'goles2';
        $pdo->prepare("UPDATE partidos SET $campoGoles = GREATEST(0, $campoGoles - ?) WHERE id = ?")
            ->execute([$ultimo['puntos'], $partidoId]);

        // 3. Restar stats del estudiante
        if ($ultimo['estudiante_id']) {
            $campoDesglose = $CAMPO_DESGLOSE[$ultimo['tipoEvento']] ?? null;
            if ($campoDesglose) {
                $pdo->prepare("UPDATE estudiantes SET puntos_totales = GREATEST(0, puntos_totales - ?), $campoDesglose = GREATEST(0, $campoDesglose - 1) WHERE id = ?")
                    ->execute([$ultimo['puntos'], $ultimo['estudiante_id']]);
            } else {
                $pdo->prepare("UPDATE estudiantes SET puntos_totales = GREATEST(0, puntos_totales - ?) WHERE id = ?")
                    ->execute([$ultimo['puntos'], $ultimo['estudiante_id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'Último punto eliminado correctamente.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Error al deshacer punto: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);

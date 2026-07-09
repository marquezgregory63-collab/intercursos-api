<?php
// ── partidos_get.php ──────────────────────────────────────────────────────
// GET ?disciplinaId=futbol
// Devuelve { ok:true, data:[...partidos con stats e historial] }
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
    // Traer partidos
    $stmt = $pdo->prepare('
        SELECT p.*,
               s.pos1, s.pos2, s.rem1, s.rem2, s.arc1, s.arc2,
               s.cor1, s.cor2, s.fal1, s.fal2, s.ama1, s.ama2,
               s.par1, s.par2, s.roj1, s.roj2,
               s.fgm1, s.fgm2, s.fga1, s.fga2,
               s.tp1,  s.tp2,  s.tpa1, s.tpa2,
               s.ftm1, s.ftm2, s.fta1, s.fta2,
               s.ast1, s.ast2, s.oreb1,s.oreb2,
               s.dreb1,s.dreb2,s.stl1, s.stl2,
               s.blk1, s.blk2,
               s.ace1, s.ace2, s.kill1,s.kill2,
               s.blkp1,s.blkp2,s.dig1, s.dig2
        FROM partidos p
        LEFT JOIN stats_partidos s ON s.partido_id = p.id
        WHERE p.disciplinaId = ?
        ORDER BY p.fecha_creacion DESC
    ');
    $stmt->execute([$disciplinaId]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada partido traer historial de puntos
    $stmtH = $pdo->prepare('
        SELECT h.*, e.nombre, e.apellido
        FROM historial_puntos h
        LEFT JOIN estudiantes e ON e.id = h.estudiante_id
        WHERE h.partido_id = ?
        ORDER BY h.created_at ASC
    ');

    $resultado = [];
    foreach ($partidos as $p) {
        // Construir objeto stats igual al formato que usa el front
        $stats = [
            'pos1' => $p['pos1'] ?? 50, 'pos2' => $p['pos2'] ?? 50,
            'rem1' => $p['rem1'] ?? 0,  'rem2' => $p['rem2'] ?? 0,
            'arc1' => $p['arc1'] ?? 0,  'arc2' => $p['arc2'] ?? 0,
            'cor1' => $p['cor1'] ?? 0,  'cor2' => $p['cor2'] ?? 0,
            'fal1' => $p['fal1'] ?? 0,  'fal2' => $p['fal2'] ?? 0,
            'ama1' => $p['ama1'] ?? 0,  'ama2' => $p['ama2'] ?? 0,
            'par1' => $p['par1'] ?? 0,  'par2' => $p['par2'] ?? 0,
            'roj1' => $p['roj1'] ?? 0,  'roj2' => $p['roj2'] ?? 0,
            // basket
            'fgm1' => $p['fgm1'] ?? 0,  'fgm2' => $p['fgm2'] ?? 0,
            'fga1' => $p['fga1'] ?? 0,  'fga2' => $p['fga2'] ?? 0,
            'tp1'  => $p['tp1']  ?? 0,  'tp2'  => $p['tp2']  ?? 0,
            'tpa1' => $p['tpa1'] ?? 0,  'tpa2' => $p['tpa2'] ?? 0,
            'ftm1' => $p['ftm1'] ?? 0,  'ftm2' => $p['ftm2'] ?? 0,
            'fta1' => $p['fta1'] ?? 0,  'fta2' => $p['fta2'] ?? 0,
            'ast1' => $p['ast1'] ?? 0,  'ast2' => $p['ast2'] ?? 0,
            'oreb1'=> $p['oreb1']?? 0,  'oreb2'=> $p['oreb2']?? 0,
            'dreb1'=> $p['dreb1']?? 0,  'dreb2'=> $p['dreb2']?? 0,
            'stl1' => $p['stl1'] ?? 0,  'stl2' => $p['stl2'] ?? 0,
            'blk1' => $p['blk1'] ?? 0,  'blk2' => $p['blk2'] ?? 0,
            // voleibol
            'ace1' => $p['ace1'] ?? 0,  'ace2' => $p['ace2'] ?? 0,
            'kill1'=> $p['kill1']?? 0,  'kill2'=> $p['kill2']?? 0,
            'blkp1'=> $p['blkp1']?? 0,  'blkp2'=> $p['blkp2']?? 0,
            'dig1' => $p['dig1'] ?? 0,  'dig2' => $p['dig2'] ?? 0,
        ];

        // Historial
        $stmtH->execute([$p['id']]);
        $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        $resultado[] = [
            'id'            => $p['id'],
            'disciplinaId'  => $p['disciplinaId'],
            'equipoLocal'   => $p['equipoLocal'],
            'equipoVisita'  => $p['equipoVisita'],
            'idLocal'       => $p['idLocal'],
            'idVisita'      => $p['idVisita'],
            'goles1'        => (int)($p['goles1'] ?? 0),
            'goles2'        => (int)($p['goles2'] ?? 0),
            'estado_tiempo' => $p['estado_tiempo'],
            'inicio_real_ms'=> $p['inicio_real_ms'],
            'programacion'  => $p['programacion'],
            'ubicacion'     => $p['ubicacion'],
            'fecha_creacion'=> $p['fecha_creacion'],
            'stats'         => $stats,
            'historialPuntos'=> $historial,
        ];
    }

    echo json_encode(['ok' => true, 'data' => $resultado]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en la base de datos']);
}

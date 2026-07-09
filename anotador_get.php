<?php
// ── anotador_get.php ──────────────────────────────────────────────────────
// GET ?disciplina=Fútbol  (nombre con tilde, como viene del front)
// Devuelve { ok:true, data:{nombre,apellido,idEquipo,puntos_totales,...} }
// ─────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$disciplina = isset($_GET['disciplina']) ? trim($_GET['disciplina']) : '';

if (empty($disciplina)) {
    echo json_encode(['ok' => false, 'error' => 'disciplina requerida']);
    exit;
}

try {
    // Normalizar sin la clase Normalizer (no requiere extensión intl)
    $disc = mb_strtolower($disciplina);
    $disc = str_replace(
        ['á','é','í','ó','ú','à','è','ì','ò','ù','ä','ë','ï','ö','ü','ñ'],
        ['a','e','i','o','u','a','e','i','o','u','a','e','i','o','u','n'],
        $disc
    );

    $orderCol = 'puntos_totales'; // default fútbol
    $extraCols = 'goles_totales, 0 as tl_totales, 0 as dobles_totales,
                  0 as triples_totales, 0 as kills_totales,
                  0 as aces_totales, 0 as bloqueos_totales';

    if ($disc === 'basket' || $disc === 'baloncesto') {
        $orderCol  = 'puntos_totales';
        $extraCols = 'goles_totales, tl_totales, dobles_totales,
                      triples_totales, 0 as kills_totales,
                      0 as aces_totales, 0 as bloqueos_totales';
    } elseif ($disc === 'voleibol') {
        $orderCol  = 'puntos_totales';
        $extraCols = 'goles_totales, 0 as tl_totales, 0 as dobles_totales,
                      0 as triples_totales, kills_totales,
                      aces_totales, bloqueos_totales';
    }

    $stmt = $pdo->prepare("
        SELECT nombre, apellido, idEquipo, puntos_totales, {$extraCols}
        FROM estudiantes
        WHERE disciplina = ?
          AND puntos_totales > 0
        ORDER BY {$orderCol} DESC
        LIMIT 1
    ");
    $stmt->execute([$disciplina]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => true, 'data' => null]);
        exit;
    }

    // Castear numéricos
    foreach (['puntos_totales','goles_totales','tl_totales','dobles_totales',
              'triples_totales','kills_totales','aces_totales','bloqueos_totales'] as $col) {
        $row[$col] = (int)($row[$col] ?? 0);
    }

    echo json_encode(['ok' => true, 'data' => $row]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en la base de datos']);
}

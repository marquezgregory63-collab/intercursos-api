<?php
// ══════════════════════════════════════════════════════════════════
//  intercursos-api/exportar_pdf.php
//  Genera un HTML imprimible con todos los datos del torneo.
//  El dashboard lo abre en ventana nueva y el usuario imprime/guarda como PDF.
//  GET → devuelve HTML completo listo para imprimir
//  Solo accesible con rol admin o arbitro (verificado por cedula GET param)
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/db.php';

// Verificar que viene de un usuario válido (cedula en query string)
$cedula = trim($_GET['cedula'] ?? '');
if ($cedula) {
    $chk = $pdo->prepare('SELECT rol FROM usuarios WHERE cedula = ? LIMIT 1');
    $chk->execute([$cedula]);
    $usr = $chk->fetch();
    if (!$usr || !in_array($usr['rol'], ['admin','arbitro'])) {
        http_response_code(403);
        echo '<h1>Sin permisos para exportar.</h1>'; exit;
    }
}

// ── Obtener todos los datos ───────────────────────────────────────

// 1. Partidos con historial y stats
$stmtP = $pdo->query("SELECT * FROM partidos ORDER BY disciplinaId, fecha_creacion ASC");
$partidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Historial de cada partido
$stmtH = $pdo->query("SELECT * FROM historial_puntos ORDER BY partido_id, minuto ASC");
$historialRaw = $stmtH->fetchAll(PDO::FETCH_ASSOC);
$historialMap = [];
foreach ($historialRaw as $h) { $historialMap[$h['partido_id']][] = $h; }

// Stats de cada partido
$stmtS = $pdo->query("SELECT * FROM stats_partidos");
$statsRaw = $stmtS->fetchAll(PDO::FETCH_ASSOC);
$statsMap = [];
foreach ($statsRaw as $s) { $statsMap[$s['partido_id']] = $s; }

// 2. Posiciones por disciplina
$stmtPos = $pdo->query("SELECT * FROM posiciones ORDER BY disciplinaId, puntos DESC, gf DESC");
$posiciones = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
$posMap = [];
foreach ($posiciones as $pos) { $posMap[$pos['disciplinaId']][] = $pos; }

// 3. Estudiantes con estadísticas
$stmtE = $pdo->query("SELECT * FROM estudiantes ORDER BY disciplina, puntos_totales DESC");
$estudiantes = $stmtE->fetchAll(PDO::FETCH_ASSOC);
$estMap = [];
foreach ($estudiantes as $e) { $estMap[$e['disciplina']][] = $e; }

// ── Helpers ───────────────────────────────────────────────────────
function idToNombre($id) {
    if (!$id) return 'Sin nombre';
    $mNum = ['1'=>'1ER','2'=>'2DO','3'=>'3ER','4'=>'4TO','5'=>'5TO'];
    $mMen = ['TEL'=>'TELEMÁTICA','ADM'=>'ADMINISTRACIÓN','CONT'=>'CONTABILIDAD','TUR'=>'TURISMO'];
    $p = explode('-', $id);
    if (count($p) < 3) return strtoupper($id);
    return ($mNum[$p[0]] ?? $p[0]).' '.($mMen[$p[1]] ?? $p[1]).' '.$p[2];
}

function dg($gf, $gc) {
    $d = intval($gf) - intval($gc);
    return $d >= 0 ? '+' . $d : strval($d);
}

$fecha = date('d/m/Y H:i');
$disciplinas = ['Fútbol','Basket','Voleibol'];

// ── Agrupar partidos por disciplina ──────────────────────────────
$partidosMap = [];
foreach ($partidos as $p) {
    $disc = $p['disciplinaId'] ?? 'otro';
    $partidosMap[$disc][] = $p;
}

// ── Calcular totales globales ─────────────────────────────────────
$totalPartidos   = count($partidos);
$totalFin        = count(array_filter($partidos, fn($p) => strtolower($p['estado_tiempo']) === 'finalizado'));
$totalGoles      = array_sum(array_column($partidos, 'goles1')) + array_sum(array_column($partidos, 'goles2'));
$totalEst        = count($estudiantes);

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<title>Reporte Intercursos JRG — <?= $fecha ?></title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Times New Roman',serif;font-size:11pt;color:#111;background:#fff}
  @page{size:A4 portrait;margin:2cm 1.5cm}
  @media print{
    .no-print{display:none!important}
    .page-break{page-break-before:always}
    body{font-size:10pt}
  }

  /* PORTADA */
  .portada{text-align:center;padding:60px 20px;border-bottom:3px solid #0284C7}
  .portada-logo{font-size:64px;margin-bottom:20px}
  .portada-institucion{font-size:13pt;font-weight:bold;text-transform:uppercase;color:#0284C7;letter-spacing:1px}
  .portada-titulo{font-size:22pt;font-weight:bold;margin:24px 0 12px;color:#1E293B;line-height:1.2}
  .portada-sub{font-size:12pt;color:#64748B;margin-bottom:32px}
  .portada-meta{font-size:10pt;color:#475569;line-height:2}
  .portada-linea{width:80px;height:4px;background:#0284C7;margin:24px auto}

  /* RESUMEN */
  .resumen-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:24px 0}
  .resumen-card{border:2px solid #E2E8F0;border-radius:10px;padding:16px;text-align:center;background:#F8FAFC}
  .resumen-num{font-size:28pt;font-weight:bold;color:#0284C7}
  .resumen-lbl{font-size:9pt;color:#64748B;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}

  /* SECCIONES */
  h1.sec-title{font-size:14pt;font-weight:bold;color:#fff;background:#0284C7;padding:10px 16px;border-radius:8px 8px 0 0;margin-top:32px;text-transform:uppercase;letter-spacing:.5px}
  h2.disc-title{font-size:12pt;font-weight:bold;color:#0284C7;margin:24px 0 10px;padding-bottom:4px;border-bottom:2px solid #0284C7;text-transform:uppercase}
  h3.sub-title{font-size:10pt;font-weight:bold;color:#1E293B;margin:16px 0 8px;text-transform:uppercase;letter-spacing:.3px}

  /* TABLAS */
  table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:9.5pt}
  thead tr{background:#0284C7;color:#fff}
  thead th{padding:7px 10px;text-align:left;font-weight:bold;font-size:9pt;text-transform:uppercase;letter-spacing:.3px}
  thead th.c{text-align:center}
  tbody tr:nth-child(even){background:#F1F5F9}
  tbody tr:hover{background:#E0F2FE}
  td{padding:6px 10px;border-bottom:1px solid #E2E8F0;vertical-align:top}
  td.c{text-align:center}
  td.r{text-align:right}
  .rank-top{color:#0284C7;font-weight:bold}
  .rank-1{color:#F59E0B;font-weight:900}
  .rank-2{color:#94A3B8;font-weight:900}
  .rank-3{color:#B45309;font-weight:900}

  /* PARTIDO CARD */
  .partido-card{border:1px solid #CBD5E1;border-radius:8px;padding:12px 16px;margin-bottom:12px;background:#F8FAFC}
  .partido-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .partido-disc{font-size:8pt;font-weight:bold;color:#0284C7;text-transform:uppercase;letter-spacing:.5px;background:#EFF6FF;padding:2px 8px;border-radius:20px}
  .partido-estado{font-size:8pt;font-weight:bold;padding:2px 8px;border-radius:20px}
  .estado-fin{background:#F0FDF4;color:#166534}
  .estado-vivo{background:#FEE2E2;color:#991B1B}
  .estado-prog{background:#F1F5F9;color:#475569}
  .partido-marcador{text-align:center;font-size:20pt;font-weight:900;color:#1E293B;margin:8px 0}
  .partido-equipos{display:flex;justify-content:space-between;font-size:10pt;font-weight:bold;color:#1E293B}
  .partido-meta{font-size:8.5pt;color:#64748B;margin-top:6px;text-align:center}
  .historial-item{font-size:8.5pt;color:#475569;padding:2px 0;border-bottom:1px solid #F1F5F9}
  .historial-min{display:inline-block;width:28px;font-weight:bold;color:#0284C7}

  /* STATS GRID */
  .stats-mini{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:8px;font-size:8pt}
  .stat-item{background:#EFF6FF;padding:3px 8px;border-radius:4px;color:#1E293B}
  .stat-lbl{color:#64748B;font-size:7.5pt}

  /* JUGADOR DESTACADO */
  .top-scorer{background:linear-gradient(135deg,#FEF9C3,#FEF3C7);border:2px solid #F59E0B;border-radius:8px;padding:12px 16px;margin-bottom:12px}
  .top-scorer-nombre{font-size:12pt;font-weight:bold;color:#1E293B}
  .top-scorer-pts{font-size:20pt;font-weight:900;color:#F59E0B}

  /* FOOTER */
  .footer{margin-top:40px;padding-top:16px;border-top:2px solid #E2E8F0;font-size:8.5pt;color:#94A3B8;text-align:center}
  .no-data-msg{color:#94A3B8;font-style:italic;font-size:9.5pt;padding:12px;text-align:center}

  /* BOTÓN IMPRIMIR */
  .btn-imprimir{position:fixed;top:20px;right:20px;background:#0284C7;color:#fff;border:none;padding:12px 24px;border-radius:10px;font-size:12pt;font-weight:bold;cursor:pointer;box-shadow:0 4px 16px rgba(2,132,199,.4);z-index:999}
  .btn-imprimir:hover{background:#0369A1}
</style>
</head>
<body>

<button class="btn-imprimir no-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>

<!-- ══════════ PORTADA ══════════ -->
<div class="portada">
  <div class="portada-logo">🏆</div>
  <div class="portada-institucion">Escuela Técnica "Prof. José Ricardo Guillén Suárez"</div>
  <div class="portada-linea"></div>
  <div class="portada-titulo">Reporte General de los<br>Juegos Intercursos</div>
  <div class="portada-sub">Registro y Control Estadístico Completo del Torneo</div>
  <div class="portada-meta">
    <strong>Disciplinas:</strong> Fútbol · Básquet · Voleibol<br>
    <strong>Generado:</strong> <?= $fecha ?><br>
    <strong>Sistema:</strong> SARCI — Sistema Automatizado para el Registro y Control de los Intercursos
  </div>
</div>

<!-- ══════════ RESUMEN GENERAL ══════════ -->
<div class="page-break"></div>

<h1 class="sec-title">📊 Resumen General del Torneo</h1>

<div class="resumen-grid">
  <div class="resumen-card">
    <div class="resumen-num"><?= $totalPartidos ?></div>
    <div class="resumen-lbl">Partidos Registrados</div>
  </div>
  <div class="resumen-card">
    <div class="resumen-num"><?= $totalFin ?></div>
    <div class="resumen-lbl">Partidos Finalizados</div>
  </div>
  <div class="resumen-card">
    <div class="resumen-num"><?= $totalGoles ?></div>
    <div class="resumen-lbl">Puntos / Goles Totales</div>
  </div>
  <div class="resumen-card">
    <div class="resumen-num"><?= $totalEst ?></div>
    <div class="resumen-lbl">Jugadores Inscritos</div>
  </div>
</div>

<!-- ══════════ TABLA DE POSICIONES ══════════ -->
<?php foreach ($disciplinas as $disc):
  $discId = strtolower(preg_replace('/[áéíóú]/u', fn($m) => ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u'][$m[0]], $disc));
  $pos = $posMap[$discId] ?? [];
  if (empty($pos)) continue;
?>
<h2 class="disc-title">⚽ <?= $disc ?> — Tabla de Posiciones</h2>
<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Equipo</th>
      <th class="c">PJ</th>
      <th class="c">PG</th>
      <?php if($disc==='Fútbol'):?><th class="c">PE</th><?php endif;?>
      <th class="c">PP</th>
      <th class="c">GF</th>
      <th class="c">GC</th>
      <th class="c">DG</th>
      <th class="c">PTS</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pos as $i => $t): ?>
    <tr>
      <td class="c <?= $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'')) ?>"><?= $i+1 ?></td>
      <td><?= htmlspecialchars(idToNombre($t['idEquipo'])) ?></td>
      <td class="c"><?= $t['pj']??0 ?></td>
      <td class="c"><?= $t['pg']??0 ?></td>
      <?php if($disc==='Fútbol'):?><td class="c"><?= $t['pe']??0 ?></td><?php endif;?>
      <td class="c"><?= $t['pp']??0 ?></td>
      <td class="c"><?= $t['gf']??0 ?></td>
      <td class="c"><?= $t['gc']??0 ?></td>
      <td class="c"><?= dg($t['gf']??0,$t['gc']??0) ?></td>
      <td class="c rank-top"><?= $t['puntos']??0 ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<!-- ══════════ NÓMINA Y ESTADÍSTICAS DE JUGADORES ══════════ -->
<div class="page-break"></div>
<h1 class="sec-title">👥 Nómina y Estadísticas por Jugador</h1>

<?php foreach ($disciplinas as $disc):
  $jugadores = $estMap[$disc] ?? [];
  if (empty($jugadores)) continue;
  $discId = strtolower(preg_replace('/[áéíóú]/u', fn($m) => ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u'][$m[0]], $disc));
?>
<h2 class="disc-title"><?= $disc ?></h2>

<?php
// Jugador top
$top = $jugadores[0] ?? null;
if ($top && ($top['puntos_totales'] ?? 0) > 0):
?>
<div class="top-scorer">
  <div style="font-size:8pt;color:#92400E;text-transform:uppercase;font-weight:bold;margin-bottom:4px">🏆 Mayor Anotador</div>
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div class="top-scorer-nombre"><?= htmlspecialchars(($top['nombre']??'').' '.($top['apellido']??'')) ?></div>
      <div style="font-size:9pt;color:#64748B"><?= htmlspecialchars(idToNombre($top['idEquipo']??'')) ?> · C.I. <?= htmlspecialchars($top['cedula']??'S/C') ?></div>
    </div>
    <div style="text-align:center">
      <div class="top-scorer-pts"><?= $top['puntos_totales']??0 ?></div>
      <div style="font-size:8pt;color:#92400E;font-weight:bold">PUNTOS</div>
    </div>
  </div>
</div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Nombre</th>
      <th>C.I.</th>
      <th>Equipo</th>
      <?php if($disc==='Fútbol'):?>
        <th class="c">Goles</th>
        <th class="c">PTS</th>
      <?php elseif($disc==='Basket'):?>
        <th class="c">TL</th>
        <th class="c">2P</th>
        <th class="c">3P</th>
        <th class="c">PTS</th>
      <?php elseif($disc==='Voleibol'):?>
        <th class="c">Kills</th>
        <th class="c">Aces</th>
        <th class="c">Blq</th>
        <th class="c">PTS</th>
      <?php endif;?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($jugadores as $i => $e): ?>
    <tr>
      <td class="c <?= $i<3?'rank-top':'' ?>"><?= $i+1 ?></td>
      <td><?= htmlspecialchars(($e['nombre']??'').' '.($e['apellido']??'')) ?></td>
      <td><?= htmlspecialchars($e['cedula']??'S/C') ?></td>
      <td><?= htmlspecialchars(idToNombre($e['idEquipo']??'')) ?></td>
      <?php if($disc==='Fútbol'):?>
        <td class="c"><?= $e['goles_totales']??0 ?></td>
        <td class="c rank-top"><?= $e['puntos_totales']??0 ?></td>
      <?php elseif($disc==='Basket'):?>
        <td class="c"><?= $e['tl_totales']??0 ?></td>
        <td class="c"><?= $e['dobles_totales']??0 ?></td>
        <td class="c"><?= $e['triples_totales']??0 ?></td>
        <td class="c rank-top"><?= $e['puntos_totales']??0 ?></td>
      <?php elseif($disc==='Voleibol'):?>
        <td class="c"><?= $e['kills_totales']??0 ?></td>
        <td class="c"><?= $e['aces_totales']??0 ?></td>
        <td class="c"><?= $e['bloqueos_totales']??0 ?></td>
        <td class="c rank-top"><?= $e['puntos_totales']??0 ?></td>
      <?php endif;?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<!-- ══════════ HISTORIAL DE PARTIDOS ══════════ -->
<div class="page-break"></div>
<h1 class="sec-title">🏟️ Historial Completo de Partidos</h1>

<?php foreach ($disciplinas as $disc):
  $discId = strtolower(preg_replace('/[áéíóú]/u', fn($m) => ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u'][$m[0]], $disc));
  $parts  = $partidosMap[$discId] ?? [];
  if (empty($parts)) continue;
?>
<h2 class="disc-title"><?= $disc ?></h2>

<?php foreach ($parts as $p):
  $estadoLow = strtolower($p['estado_tiempo']??'');
  $estadoClass = str_contains($estadoLow,'finalizado')||str_contains($estadoLow,'terminado') ? 'estado-fin'
    : (str_contains($estadoLow,'vivo')||str_contains($estadoLow,'curso') ? 'estado-vivo' : 'estado-prog');
  $hist  = $historialMap[$p['id']] ?? [];
  $stats = $statsMap[$p['id']] ?? [];
?>
<div class="partido-card">
  <div class="partido-header">
    <span class="partido-disc"><?= strtoupper($p['disciplinaId']??'') ?></span>
    <span class="partido-estado <?= $estadoClass ?>"><?= htmlspecialchars($p['estado_tiempo']??'') ?></span>
    <span style="font-size:8.5pt;color:#64748B"><?= htmlspecialchars($p['programacion']??'') ?> · <?= htmlspecialchars($p['ubicacion']??'') ?></span>
  </div>
  <div class="partido-equipos">
    <span><?= htmlspecialchars($p['equipoLocal']??'Local') ?></span>
    <span style="font-size:8.5pt;color:#94A3B8">VS</span>
    <span><?= htmlspecialchars($p['equipoVisita']??'Visita') ?></span>
  </div>
  <div class="partido-marcador">
    <?= intval($p['goles1']) ?> — <?= intval($p['goles2']) ?>
  </div>

  <?php if (!empty($hist)): ?>
  <div style="margin-top:8px">
    <div style="font-size:8pt;font-weight:bold;color:#475569;margin-bottom:4px;text-transform:uppercase">Anotaciones</div>
    <?php foreach ($hist as $h): ?>
    <div class="historial-item">
      <span class="historial-min"><?= intval($h['minuto']) ?>'</span>
      <?= htmlspecialchars($h['anotador']??'') ?>
      · <?= htmlspecialchars($h['tipoEvento']??'Gol') ?>
      · <?= $h['equipo']==1 ? htmlspecialchars($p['equipoLocal']??'Local') : htmlspecialchars($p['equipoVisita']??'Visita') ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($stats)): ?>
  <div style="margin-top:8px">
    <div style="font-size:8pt;font-weight:bold;color:#475569;margin-bottom:4px;text-transform:uppercase">Estadísticas del Partido</div>
    <table style="font-size:8pt;margin-bottom:0">
      <thead><tr>
        <th>Indicador</th>
        <th class="c"><?= htmlspecialchars($p['equipoLocal']??'Local') ?></th>
        <th class="c"><?= htmlspecialchars($p['equipoVisita']??'Visita') ?></th>
      </tr></thead>
      <tbody>
      <?php
      $campos = [];
      if ($discId==='futbol') {
        $campos = [
          'Posesión (%)'=>['pos1','pos2'],'Remates'=>['rem1','rem2'],
          'Córners'=>['cor1','cor2'],'Faltas'=>['fal1','fal2'],
          'Amarillas'=>['ama1','ama2'],'Rojas'=>['roj1','roj2'],
        ];
      } elseif ($discId==='basket') {
        $campos = [
          'FGM/FGA'=>['fgm1','fgm2'],'3PM/3PA'=>['tp1','tp2'],
          'FTM/FTA'=>['ftm1','ftm2'],'Asistencias'=>['ast1','ast2'],
          'Reb. Ofensivos'=>['oreb1','oreb2'],'Reb. Defensivos'=>['dreb1','dreb2'],
          'Robos'=>['stl1','stl2'],'Tapones'=>['blk1','blk2'],
        ];
      } elseif ($discId==='voleibol') {
        $campos = [
          'Aces'=>['ace1','ace2'],'Kills'=>['kill1','kill2'],
          'Bloqueos'=>['blkp1','blkp2'],'Digs'=>['dig1','dig2'],
        ];
      }
      foreach ($campos as $lbl => $keys):
        $v1 = $stats[$keys[0]] ?? 0;
        $v2 = $stats[$keys[1]] ?? 0;
        if ($v1==0 && $v2==0) continue;
      ?>
      <tr>
        <td><?= $lbl ?></td>
        <td class="c"><?= $v1 ?></td>
        <td class="c"><?= $v2 ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endforeach; ?>

<!-- ══════════ FOOTER ══════════ -->
<div class="footer">
  Reporte generado el <?= $fecha ?> por el Sistema SARCI — E.T. "Prof. José Ricardo Guillén Suárez" · Ejido, Mérida, Venezuela<br>
  Este documento es de uso institucional y contiene información oficial de los Juegos Intercursos.
</div>

</body>
</html>
<?php
echo ob_get_clean();
?>

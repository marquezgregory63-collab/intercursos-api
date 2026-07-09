<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $cedula = trim($_GET['cedula'] ?? '');
    if (!$cedula) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Cédula requerida.']); exit; }
    $stmt = $pdo->prepare('SELECT uid, cedula, nombre, apellido, rol FROM usuarios WHERE cedula = ? LIMIT 1');
    $stmt->execute([$cedula]);
    $user = $stmt->fetch();
    if (!$user) { echo json_encode(['ok'=>false,'data'=>[],'msg'=>'Usuario no encontrado.']); exit; }
    echo json_encode(['ok'=>true,'data'=>[$user]]);
    exit;
}

if ($method === 'PUT') {
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $cedulaAdmin = trim($body['cedulaAdmin'] ?? '');
    $targetUid   = trim($body['uid']         ?? '');
    $nuevoRol    = trim($body['rol']         ?? '');
    if (!$cedulaAdmin||!$targetUid||!$nuevoRol) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Faltan campos.']); exit; }
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE cedula = ? LIMIT 1');
    $stmt->execute([$cedulaAdmin]);
    $sol = $stmt->fetch();
    if (!$sol||$sol['rol']!=='admin') { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permisos.']); exit; }
    if (!in_array($nuevoRol,['arbitro','usuario'])) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Rol inválido.']); exit; }
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE uid = ? LIMIT 1');
    $stmt->execute([$targetUid]);
    $target = $stmt->fetch();
    if (!$target) { echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado.']); exit; }
    if ($target['rol']==='admin') { echo json_encode(['ok'=>false,'msg'=>'No puedes modificar al admin global.']); exit; }
    $pdo->prepare('UPDATE usuarios SET rol = ? WHERE uid = ?')->execute([$nuevoRol,$targetUid]);
    $label = $nuevoRol==='arbitro'?'Árbitro':'Usuario';
    echo json_encode(['ok'=>true,'msg'=>"Rol actualizado a {$label} correctamente."]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']);

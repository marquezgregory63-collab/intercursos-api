<?php
// ══════════════════════════════════════════════════════════════════
//  intercursos-api/login.php
//  Ruta: C:\xampp\htdocs\intercursos-api\login.php
//  Método: POST
//  Body JSON: { "cedula": "V12345678", "password": "mipass123" }
//  Responde: { uid, cedula, nombre, apellido, rol } o { error: "..." }
//  Roles válidos: usuario | arbitro | admin
// ══════════════════════════════════════════════════════════════════

// ── Cabeceras completas para permitir conexión desde la App ───────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── Leer body JSON ────────────────────────────────────────────────
$body     = json_decode(file_get_contents('php://input'), true);
$cedula   = trim($body['cedula']   ?? '');
$password = trim($body['password'] ?? '');

if (!$cedula || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Cédula y contraseña son obligatorias.']);
    exit;
}

// ── Buscar usuario ────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT uid, cedula, nombre, apellido, rol, password
     FROM usuarios WHERE cedula = ? LIMIT 1'
);
$stmt->execute([$cedula]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no encontrado en el sistema.']);
    exit;
}

// ── Verificar contraseña ──────────────────────────────────────────
if ($user['password'] !== $password) {
    http_response_code(401);
    echo json_encode(['error' => 'Contraseña incorrecta.']);
    exit;
}

// ── Normalizar rol ────────────────────────────────────────────────
// Si quedó algún usuario con rol='estudiante' (rol viejo de Firebase)
// lo migramos automáticamente a 'usuario' en la BD y devolvemos 'usuario'
$rolesValidos = ['usuario', 'arbitro', 'admin'];
$rol = in_array($user['rol'], $rolesValidos) ? $user['rol'] : 'usuario';

if (!in_array($user['rol'], $rolesValidos)) {
    $fix = $pdo->prepare('UPDATE usuarios SET rol = "usuario" WHERE cedula = ?');
    $fix->execute([$cedula]);
}

// ── Login exitoso — devolver datos completos ──────────────────────
http_response_code(200);
echo json_encode([
    'uid'      => $user['uid'],
    'cedula'   => $user['cedula'],
    'nombre'   => $user['nombre'],
    'apellido' => $user['apellido'],
    'rol'      => $rol,
]);
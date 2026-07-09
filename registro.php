<?php
// ══════════════════════════════════════════════════════════════════
//  intercursos-api/registro.php
//  Ruta: C:\xampp\htdocs\intercursos-api\registro.php
//  Método: POST
//  Body JSON: { tipoDoc, cedula, nombre, apellido,
//               emailRecuperacion, password }
//  Responde: { uid, cedula, nombre } o { error: "..." }
// ══════════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Método no permitido']); exit; }

require_once __DIR__ . '/db.php';

// ── Leer y sanear entrada ─────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true);
$tipoDoc = strtoupper(trim($body['tipoDoc']           ?? ''));
$cedula  = trim($body['cedula']                        ?? '');
$nombre  = strtoupper(trim($body['nombre']             ?? ''));
$apellido= strtoupper(trim($body['apellido']           ?? ''));
$email   = strtolower(trim($body['emailRecuperacion']  ?? ''));
$password= trim($body['password']                      ?? '');

// ── Validaciones server-side ──────────────────────────────────────
if (!$tipoDoc || !$cedula || !$nombre || !$apellido || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son obligatorios.']); exit;
}
if (!in_array($tipoDoc, ['V', 'E'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de documento inválido (V o E).']); exit;
}

// Cédula: extraer solo números para validar la longitud
$cedNums = preg_replace('/[^0-9]/', '', $cedula);
$longMin = $tipoDoc === 'V' ? 7 : 6;
$longMax = $tipoDoc === 'V' ? 8 : 9;
if (strlen($cedNums) < $longMin || strlen($cedNums) > $longMax) {
    http_response_code(400);
    echo json_encode(['error' => $tipoDoc === 'V'
        ? 'La cédula venezolana debe tener 7 u 8 dígitos.'
        : 'La cédula extranjera debe tener entre 6 y 9 dígitos.']); exit;
}

// Formar cédula completa con su prefijo (ej: V12345678)
$cedulaFull = $tipoDoc . $cedNums;

// Nombre y apellido: mínimo 2 letras reales sin contar espacios vacíos
if (mb_strlen(preg_replace('/\s/', '', $nombre)) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'El nombre es demasiado corto.']); exit;
}
if (mb_strlen(preg_replace('/\s/', '', $apellido)) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'El apellido es demasiado corto.']); exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo electrónico no es válido.']); exit;
}

// Validaciones de Contraseña
if (strlen($password) < 8 || strlen($password) > 16) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe tener entre 8 y 16 caracteres.']); exit;
}
if (!preg_match('/^[a-zA-Z0-9.]+$/', $password)) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña solo puede contener letras, números y puntos.']); exit;
}
if (ctype_digit($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña no puede ser únicamente números.']); exit;
}

try {
    // ── Verificar cédula duplicada ────────────────────────────────────
    $check = $pdo->prepare('SELECT uid FROM usuarios WHERE cedula = ? LIMIT 1');
    $check->execute([$cedulaFull]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Esta cédula ya está registrada en el sistema.']); exit;
    }

    // ── Verificar correo duplicado ────────────────────────────────────
    $chkMail = $pdo->prepare('SELECT uid FROM usuarios WHERE emailRecuperacion = ? LIMIT 1');
    $chkMail->execute([$email]);
    if ($chkMail->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Este correo ya está registrado.']); exit;
    }

    // ── Generar UUID v4 ───────────────────────────────────────────────
    $uid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $emailAuth = $cedNums . '@estudiante.com';

    // ── Insertar usuario con rol "usuario" ────────────────────────────
    $stmt = $pdo->prepare('
        INSERT INTO usuarios
            (uid, cedula, tipoDocumento, nombre, apellido,
             emailRecuperacion, emailAuth, password, rol,
             fechaRegistro, id_ano, seccion, disciplina, intercursos_inscrito)
        VALUES 
            (?, ?, ?, ?, ?, 
             ?, ?, ?, "usuario", 
             NOW(), "1", "A", "Ninguna", 0)
    ');
    
    $stmt->execute([
        $uid, $cedulaFull, $tipoDoc, $nombre, $apellido, 
        $email, $emailAuth, $password
    ]);

    http_response_code(201);
    echo json_encode([
        'uid'    => $uid,
        'cedula' => $cedulaFull,
        'nombre' => $nombre,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos. Intenta más tarde.']);
}
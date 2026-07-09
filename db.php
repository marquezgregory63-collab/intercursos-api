<?php
// ══════════════════════════════════════════════════════════════════
//  intercursos-api/db.php
//  Conexión interna para entorno de producción en Railway
//  Ruta: C:\xampp\htdocs\intercursos-api\db.php
// ══════════════════════════════════════════════════════════════════

// Datos de conexión INTERNA de Railway (Vuelan más rápido)
define('DB_HOST', 'mysql.railway.internal');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'XCSwBSmeEbNGAuKiYnIEsFCWIcBrMNOC'); 
define('DB_PORT', '3306'); // El puerto interno estándar de MySQL

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
    exit;
}
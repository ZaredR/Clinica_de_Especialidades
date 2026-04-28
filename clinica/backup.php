<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Log.php';
require_once __DIR__ . '/middlewares/Auth.php';

Auth::verificar();
Auth::requiereRol(['administrador']);

$db_host = 'localhost';
$db_name = 'clinica_especialidades';
$db_user = 'root';
$db_pass = '';

// Buscar mysqldump en rutas comunes de XAMPP
$candidatos = [
    'mysqldump',
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    '/Applications/XAMPP/xamppfiles/bin/mysqldump',
    '/usr/bin/mysqldump',
];
$mysqldump = 'mysqldump';
foreach ($candidatos as $c) {
    if (@is_executable($c) || $c === 'mysqldump') { $mysqldump = $c; break; }
}

$passFlag = $db_pass !== '' ? " -p\"{$db_pass}\"" : '';
$cmd      = "\"{$mysqldump}\" -h{$db_host} -u{$db_user}{$passFlag} {$db_name}";
$output   = [];
$exit     = 0;

exec($cmd . ' 2>&1', $output, $exit);

if ($exit !== 0 || empty($output)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'No se pudo generar el respaldo. Verifique que mysqldump esté accesible en el PATH del sistema.',
    ]);
    exit;
}

Log::registrar('BACKUP', 'database', null, 'Respaldo de base de datos generado por administrador');

$filename = 'backup_clinica_' . date('Y-m-d_H-i-s') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo implode("\n", $output);
exit;

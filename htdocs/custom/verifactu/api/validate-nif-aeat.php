<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * API REST para validación de NIF/CIF contra AEAT
 * 
 * URL de ejemplo: /custom/verifactu/api/validate-nif-aeat.php
 */

// Cargar el entorno de Dolibarr
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuaeatvalidator.class.php';

// Configurar headers para API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo se permite POST.']);
    exit;
}

// Verificar permisos del módulo
if (!isModEnabled('verifactu')) {
    http_response_code(403);
    echo json_encode(['error' => 'Módulo Verifactu no habilitado.']);
    exit;
}

// Verificar permisos de usuario (opcional - ajustar según necesidades)
if (empty($user) || !$user->hasRight("verifactu", "myobject", "read")) {
    http_response_code(401);
    echo json_encode(['error' => 'No tiene permisos para acceder a esta API.']);
    exit;
}

try {
    // Leer datos JSON del body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validar datos de entrada
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido en el cuerpo de la petición.']);
        exit;
    }

    // Validar campos requeridos
    if (empty($data['nif']) || empty($data['nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Los campos "nif" y "nombre" son obligatorios.']);
        exit;
    }

    $nif = trim($data['nif']);
    $nombre = trim($data['nombre']);

    // Validar longitudes
    if (strlen($nif) > 15 || strlen($nombre) > 300) {
        http_response_code(400);
        echo json_encode(['error' => 'NIF máximo 15 caracteres, nombre máximo 300 caracteres.']);
        exit;
    }

    // Inicializar validador
    $validator = new VerifactuAEATValidator($db);

    // Realizar validación local primero
    $formatoValido = $validator->validarCIFNIFNIEDNI($nif);
    
    if (!$formatoValido) {
        http_response_code(200);
        echo json_encode([
            'valido' => false,
            'error' => 'Formato de NIF/CIF/NIE inválido.',
            'validacion_local' => false,
            'validacion_aeat' => null
        ]);
        exit;
    }

    // Si el formato es válido, validar contra AEAT
    $aeatValido = $validator->validateNIFAEAT($nif, $nombre);

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'valido' => $aeatValido,
        'validacion_local' => $formatoValido,
        'validacion_aeat' => $aeatValido,
        'nif' => $nif,
        'nombre' => $nombre
    ]);

} catch (Exception $e) {
    // Log del error
    dol_syslog("Verifactu API validate-nif-aeat error: " . $e->getMessage(), LOG_ERR);
    
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor.',
        'mensaje' => 'Error al procesar la validación.'
    ]);
}
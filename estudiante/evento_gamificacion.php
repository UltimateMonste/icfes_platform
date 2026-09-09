<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion_eventos.php';

exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$tipo = trim((string)($_POST['tipo'] ?? ''));
$idReferencia = (int)($_POST['id_referencia'] ?? 0);
$idTema = (int)($_POST['id_tema'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

$resultado = registrarEventoGamificacion(
    $conexion,
    $idUsuario,
    $tipo,
    $idReferencia,
    ['id_tema'=>$idTema]
);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';

exigirEstudiante();

$idUsuario=(int)($_SESSION['id_usuario']??0);
$gam=obtenerGamificacionUsuario($conexion,$idUsuario);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'=>true,
    'puntos'=>(int)$gam['puntos'],
    'nivel'=>(int)$gam['nivel']['id_nivel'],
    'nombre_nivel'=>$gam['nivel']['nombre'],
    'progreso_nivel'=>(float)$gam['progreso_nivel'],
    'puntos_siguiente_nivel'=>$gam['puntos_siguiente_nivel']
], JSON_UNESCAPED_UNICODE);

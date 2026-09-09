<?php
declare(strict_types=1);
/*
 * Endpoint interno para registrar acciones de gamificación.
 * No sustituye las páginas existentes: se usará desde ellas cuando
 * conectemos cada interacción, evitando duplicar puntos.
 */
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';

exigirEstudiante();

$idUsuario=(int)($_SESSION['id_usuario']??0);
$accion=(string)($_POST['accion']??'');
$idReferencia=(int)($_POST['id_referencia']??0);

$map=[
 'tema'=>['clave'=>'entrar_tema','prefijo'=>'tema'],
 'recurso'=>['clave'=>'ver_recurso','prefijo'=>'recurso'],
 'completar_tema'=>['clave'=>'completar_tema','prefijo'=>'completar_tema'],
 'evaluacion'=>['clave'=>'evaluacion','prefijo'=>'evaluacion'],
 'evaluacion_perfecta'=>['clave'=>'evaluacion_perfecta','prefijo'=>'evaluacion_perfecta'],
];

header('Content-Type: application/json; charset=utf-8');

if(!isset($map[$accion]) || $idReferencia<1){
    http_response_code(400);
    echo json_encode(['ok'=>false,'mensaje'=>'Acción inválida.']);
    exit;
}

$cfg=$map[$accion]['clave'];
$puntos=obtenerConfiguracionGamificacion($conexion,$cfg,0);
$motivo=$map[$accion]['prefijo'].':'.$idReferencia;

$otorgado=otorgarPuntos($conexion,$idUsuario,$puntos,$motivo,true);
$gam=obtenerGamificacionUsuario($conexion,$idUsuario);

echo json_encode([
 'ok'=>true,
 'otorgado'=>$otorgado,
 'puntos_otorgados'=>$otorgado?$puntos:0,
 'puntos_totales'=>(int)$gam['puntos'],
 'nivel'=>(int)$gam['nivel']['id_nivel'],
 'nombre_nivel'=>$gam['nivel']['nombre']
],JSON_UNESCAPED_UNICODE);

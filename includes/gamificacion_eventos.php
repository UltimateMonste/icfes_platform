<?php
declare(strict_types=1);

/**
 * Eventos de gamificación de Studia360.
 * Archivo nuevo: no reemplaza módulos terminados.
 *
 * Uso:
 * require_once __DIR__ . '/../includes/gamificacion_eventos.php';
 * registrarEventoGamificacion($conexion, $idUsuario, 'tema', $idTema);
 */

require_once __DIR__ . '/gamificacion.php';

$archivoInsignias = __DIR__ . '/insignias_automaticas.php';
if (is_file($archivoInsignias)) {
    require_once $archivoInsignias;
}

if (!function_exists('registrarEventoGamificacion')) {
    function registrarEventoGamificacion(
        PDO $conexion,
        int $idUsuario,
        string $tipo,
        int $idReferencia,
        array $opciones = []
    ): array {
        $tiposPermitidos = [
            'tema' => 'entrar_tema',
            'recurso' => 'ver_recurso',
            'completar_tema' => 'completar_tema',
            'evaluacion' => 'evaluacion',
            'evaluacion_perfecta' => 'evaluacion_perfecta'
        ];

        if ($idUsuario < 1 || $idReferencia < 1 || !isset($tiposPermitidos[$tipo])) {
            return ['ok'=>false,'otorgado'=>false,'puntos'=>0,'mensaje'=>'Evento no válido.'];
        }

        $clave = $tiposPermitidos[$tipo];
        $puntos = obtenerConfiguracionGamificacion(
            $conexion,
            $clave,
            (int)($opciones['puntos_defecto'] ?? 0)
        );

        $motivo = $tipo . ':' . $idReferencia;
        $otorgado = otorgarPuntos($conexion, $idUsuario, $puntos, $motivo, true);

        // Si es un tema, actualizamos su progreso sin tocar el contenido.
        if ($tipo === 'tema') {
            actualizarProgresoTema($conexion, $idUsuario, $idReferencia);
        }

        if ($tipo === 'recurso') {
            $tema = (int)($opciones['id_tema'] ?? 0);
            if ($tema > 0) {
                $progreso = obtenerProgresoTema($conexion, $idUsuario, $tema);
                actualizarProgresoTema(
                    $conexion,
                    $idUsuario,
                    $tema,
                    (int)$progreso['recursos_vistos'] + 1,
                    (int)$progreso['evaluaciones_realizadas']
                );
            }
        }

        if ($tipo === 'evaluacion' || $tipo === 'evaluacion_perfecta') {
            $tema = (int)($opciones['id_tema'] ?? 0);
            if ($tema > 0) {
                $progreso = obtenerProgresoTema($conexion, $idUsuario, $tema);
                actualizarProgresoTema(
                    $conexion,
                    $idUsuario,
                    $tema,
                    (int)$progreso['recursos_vistos'],
                    (int)$progreso['evaluaciones_realizadas'] + 1
                );
            }
        }

        // Las insignias se revisan después del evento.
        $nuevasInsignias = [];
        if (function_exists('revisarYOtorgarInsignias')) {
            $nuevasInsignias = revisarYOtorgarInsignias($conexion, $idUsuario);
        }

        $gam = obtenerGamificacionUsuario($conexion, $idUsuario);

        return [
            'ok'=>true,
            'otorgado'=>$otorgado,
            'puntos'=>$otorgado ? $puntos : 0,
            'puntos_totales'=>(int)$gam['puntos'],
            'nivel'=>(int)$gam['nivel']['id_nivel'],
            'nombre_nivel'=>(string)$gam['nivel']['nombre'],
            'progreso_nivel'=>(float)$gam['progreso_nivel'],
            'insignias'=>$nuevasInsignias
        ];
    }
}

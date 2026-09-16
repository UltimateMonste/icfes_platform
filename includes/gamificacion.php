<?php
declare(strict_types=1);

/*
 * Gamificación central de Studia360.
 * Lee los valores configurables desde configuracion_gamificacion.
 * No usa columnas que no existen en la BD actual.
 */

function obtenerConfiguracionGamificacion(PDO $conexion, string $clave, int $porDefecto = 0): int {
    try {
        $st = $conexion->prepare("SELECT puntos FROM configuracion_gamificacion WHERE clave=? AND estado='Activo' LIMIT 1");
        $st->execute([$clave]);
        $v = $st->fetchColumn();
        return $v === false ? $porDefecto : max(0, (int)$v);
    } catch (Throwable $e) {
        return $porDefecto;
    }
}

function obtenerNivelPorPuntos(PDO $conexion, int $puntos): array {
    $st = $conexion->prepare("
        SELECT id_nivel,nombre,descripcion,puntos_minimos,puntos_maximos,imagen
        FROM niveles
        WHERE puntos_minimos <= ?
        ORDER BY puntos_minimos DESC
        LIMIT 1
    ");
    $st->execute([$puntos]);
    $nivel = $st->fetch(PDO::FETCH_ASSOC);

    if (!$nivel) {
        $st = $conexion->query("SELECT id_nivel,nombre,descripcion,puntos_minimos,puntos_maximos,imagen FROM niveles ORDER BY puntos_minimos ASC LIMIT 1");
        $nivel = $st->fetch(PDO::FETCH_ASSOC) ?: [
            'id_nivel'=>1,'nombre'=>'Inicial','descripcion'=>'Comienza tu recorrido.','puntos_minimos'=>0,'puntos_maximos'=>100,'imagen'=>null
        ];
    }
    return $nivel;
}

function sincronizarNivel(PDO $conexion, int $idUsuario): array {
    $st = $conexion->prepare("SELECT puntos FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->execute([$idUsuario]);
    $puntos=(int)$st->fetchColumn();
    $nivel=obtenerNivelPorPuntos($conexion,$puntos);

    $up=$conexion->prepare("UPDATE usuarios SET nivel=? WHERE id_usuario=?");
    $up->execute([(int)$nivel['id_nivel'],$idUsuario]);

    // Cada nivel tiene un avatar de perfil asociado. El avatar se desbloquea
    // al alcanzar el nivel y queda disponible para seleccionarlo desde el perfil.
    // No reemplazamos una foto personalizada del estudiante automáticamente.
    return $nivel;
}

function obtenerGamificacionUsuario(PDO $conexion, int $idUsuario): array {
    $st=$conexion->prepare("SELECT puntos,nivel FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->execute([$idUsuario]);
    $u=$st->fetch(PDO::FETCH_ASSOC) ?: ['puntos'=>0,'nivel'=>1];
    $puntos=(int)$u['puntos'];
    $nivel=sincronizarNivel($conexion,$idUsuario);

    $st=$conexion->prepare("SELECT id_nivel,nombre,puntos_minimos,puntos_maximos FROM niveles WHERE puntos_minimos>? ORDER BY puntos_minimos ASC LIMIT 1");
    $st->execute([$puntos]);
    $sig=$st->fetch(PDO::FETCH_ASSOC);

    $min=(int)$nivel['puntos_minimos']; $max=(int)$nivel['puntos_maximos'];
    $avance=$max>$min ? (($puntos-$min)/($max-$min))*100 : 100;
    $avance=max(0,min(100,$avance));

    return [
        'puntos'=>$puntos,'nivel'=>$nivel,'progreso_nivel'=>$avance,
        'puntos_siguiente_nivel'=>$sig ? (int)$sig['puntos_minimos'] : null,
        'siguiente_nivel'=>$sig
    ];
}

function yaRecibioPuntosPorReferencia(PDO $conexion,int $idUsuario,string $referencia): bool {
    $st=$conexion->prepare("SELECT 1 FROM historial_puntos WHERE id_usuario=? AND motivo=? LIMIT 1");
    $st->execute([$idUsuario,$referencia]);
    return (bool)$st->fetchColumn();
}

function otorgarPuntos(PDO $conexion,int $idUsuario,int $puntos,string $motivo,bool $unico=true): bool {
    $puntos=max(0,$puntos);
    if($puntos<=0)return false;
    if($unico && yaRecibioPuntosPorReferencia($conexion,$idUsuario,$motivo))return false;

    $conexion->beginTransaction();
    try{
        $st=$conexion->prepare("UPDATE usuarios SET puntos=puntos+? WHERE id_usuario=?");
        $st->execute([$puntos,$idUsuario]);
        $st=$conexion->prepare("INSERT INTO historial_puntos(id_usuario,motivo,puntos) VALUES(?,?,?)");
        $st->execute([$idUsuario,$motivo,$puntos]);
        $conexion->commit();
        sincronizarNivel($conexion,$idUsuario);
        return true;
    }catch(Throwable $e){
        if($conexion->inTransaction())$conexion->rollBack();
        return false;
    }
}

function obtenerProgresoTema(PDO $conexion,int $idUsuario,int $idTema): array {
    $st=$conexion->prepare("SELECT * FROM progreso WHERE id_usuario=? AND id_tema=? LIMIT 1");
    $st->execute([$idUsuario,$idTema]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [
        'id_progreso'=>null,'id_usuario'=>$idUsuario,'id_tema'=>$idTema,
        'recursos_vistos'=>0,'evaluaciones_realizadas'=>0,'porcentaje_avance'=>0,'ultima_actividad'=>null
    ];
}

function actualizarProgresoTema(PDO $conexion,int $idUsuario,int $idTema,?int $recursosVistos=null,?int $evaluacionesRealizadas=null): array {
    $actual=obtenerProgresoTema($conexion,$idUsuario,$idTema);
    $rv=$recursosVistos===null?(int)$actual['recursos_vistos']:max(0,$recursosVistos);
    $ev=$evaluacionesRealizadas===null?(int)$actual['evaluaciones_realizadas']:max(0,$evaluacionesRealizadas);

    // Entrada al tema aporta avance; recursos/evaluaciones lo incrementan.
    $avance=(int)$actual['porcentaje_avance'];
    if($avance<10)$avance=10;
    if($rv>0)$avance=max($avance,min(75,10+$rv*10));
    if($ev>0)$avance=max($avance,90);
    if($avance>=100)$avance=100;

    $st=$conexion->prepare("
      INSERT INTO progreso(id_usuario,id_tema,recursos_vistos,evaluaciones_realizadas,porcentaje_avance,ultima_actividad)
      VALUES(?,?,?,?,?,NOW())
      ON DUPLICATE KEY UPDATE recursos_vistos=VALUES(recursos_vistos),
      evaluaciones_realizadas=VALUES(evaluaciones_realizadas),
      porcentaje_avance=VALUES(porcentaje_avance),
      completado=IF(VALUES(porcentaje_avance)>=100,1,completado),
      fecha_completado=IF(VALUES(porcentaje_avance)>=100,COALESCE(fecha_completado,NOW()),fecha_completado),
      ultima_actividad=NOW()
    ");
    $st->execute([$idUsuario,$idTema,$rv,$ev,$avance]);

    if($avance>=100){
        $p=obtenerConfiguracionGamificacion($conexion,'completar_tema',25);
        otorgarPuntos($conexion,$idUsuario,$p,'completar_tema:'.$idTema,true);
    }
    return obtenerProgresoTema($conexion,$idUsuario,$idTema);
}

function revisarYOtorgarRecompensaUnidad(PDO $conexion, int $idUsuario, int $idTema): int {
    /*
     * Una unidad temática se considera completada cuando TODOS sus temas
     * tienen un progreso de 100% para el estudiante.
     *
     * La unidad predeterminada creada por la migración es solo de transición
     * y no genera esta recompensa.
     */
    try {
        $st = $conexion->prepare(
            "SELECT u.id_unidad, u.es_predeterminada, u.estado
             FROM temas t
             INNER JOIN unidades_tematicas u ON u.id_unidad=t.id_unidad
             WHERE t.id_tema=?
             LIMIT 1"
        );
        $st->execute([$idTema]);
        $unidad = $st->fetch(PDO::FETCH_ASSOC);

        if (!$unidad || (int)$unidad['es_predeterminada'] === 1 || $unidad['estado'] !== 'Activa') {
            return 0;
        }

        $idUnidad = (int)$unidad['id_unidad'];

        $st = $conexion->prepare(
            "SELECT
                COUNT(t.id_tema) AS total_temas,
                SUM(CASE WHEN COALESCE(p.porcentaje_avance,0) >= 100 THEN 1 ELSE 0 END) AS temas_completados
             FROM temas t
             INNER JOIN usuarios usr ON usr.id_usuario=?
             LEFT JOIN progreso p
               ON p.id_tema=t.id_tema
              AND p.id_usuario=?
             WHERE t.id_unidad=?
               AND t.grado=usr.grado"
        );
        $st->execute([$idUsuario, $idUsuario, $idUnidad]);
        $estado = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($estado['total_temas'] ?? 0);
        $completados = (int)($estado['temas_completados'] ?? 0);

        if ($total <= 0 || $completados < $total) {
            return 0;
        }

        $puntos = obtenerConfiguracionGamificacion(
            $conexion,
            'completar_unidad',
            50
        );

        if ($puntos <= 0) {
            return 0;
        }

        $motivo = 'completar_unidad:' . $idUnidad;
        return otorgarPuntos(
            $conexion,
            $idUsuario,
            $puntos,
            $motivo,
            true
        ) ? $puntos : 0;
    } catch (Throwable $e) {
        /* La recompensa de unidad nunca debe impedir completar un tema. */
        return 0;
    }
}

function completarTema(PDO $conexion,int $idUsuario,int $idTema): array {
    $actual=obtenerProgresoTema($conexion,$idUsuario,$idTema);

    $st=$conexion->prepare("
        INSERT INTO progreso(id_usuario,id_tema,recursos_vistos,evaluaciones_realizadas,porcentaje_avance,ultima_actividad)
        VALUES(?,?,?,?,100,NOW())
        ON DUPLICATE KEY UPDATE
          porcentaje_avance=100,
          completado=1,
          fecha_completado=COALESCE(fecha_completado,NOW()),
          ultima_actividad=NOW()
    ");
    $st->execute([
        $idUsuario,
        $idTema,
        (int)$actual['recursos_vistos'],
        (int)$actual['evaluaciones_realizadas']
    ]);

    $p=obtenerConfiguracionGamificacion($conexion,'completar_tema',25);
    otorgarPuntos($conexion,$idUsuario,$p,'completar_tema:'.$idTema,true);

    // Después de completar el tema, comprobamos si con este avance
    // también se terminó la unidad temática completa.
    revisarYOtorgarRecompensaUnidad($conexion,$idUsuario,$idTema);

    if(function_exists('revisarYOtorgarInsignias')){
        revisarYOtorgarInsignias($conexion,$idUsuario);
    }

    return obtenerProgresoTema($conexion,$idUsuario,$idTema);
}

function obtenerProgresoMateria(PDO $conexion,int $idUsuario,int $idMateria): float {
    $st=$conexion->prepare("SELECT COALESCE(AVG(p.porcentaje_avance),0) FROM progreso p INNER JOIN temas t ON t.id_tema=p.id_tema WHERE p.id_usuario=? AND t.id_materia=?");
    $st->execute([$idUsuario,$idMateria]);
    return (float)$st->fetchColumn();
}

function obtenerProgresoGeneral(PDO $conexion,int $idUsuario): array {
    $st=$conexion->prepare("SELECT COUNT(*) total_temas,COALESCE(AVG(porcentaje_avance),0) porcentaje FROM progreso WHERE id_usuario=?");
    $st->execute([$idUsuario]);
    $r=$st->fetch(PDO::FETCH_ASSOC) ?: ['total_temas'=>0,'porcentaje'=>0];
    return ['total_temas'=>(int)$r['total_temas'],'porcentaje'=>(float)$r['porcentaje']];
}


/**
 * Revisa insignias después de una recompensa.
 * Se mantiene aquí como puente para no obligar a modificar
 * tema.php, evaluacion.php u otros módulos ya terminados.
 */
function procesarRecompensasGamificacion(PDO $conexion, int $idUsuario): array {
    $insignias = [];
    $archivo = __DIR__ . '/insignias_automaticas.php';

    if (is_file($archivo)) {
        require_once $archivo;
        if (function_exists('revisarYOtorgarInsignias')) {
            $insignias = revisarYOtorgarInsignias($conexion, $idUsuario);
        }
    }

    $nivel = sincronizarNivel($conexion, $idUsuario);
    $gam = obtenerGamificacionUsuario($conexion, $idUsuario);

    return [
        'insignias' => $insignias,
        'nivel' => $nivel,
        'gamificacion' => $gam
    ];
}

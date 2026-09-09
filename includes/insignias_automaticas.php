<?php
declare(strict_types=1);

/*
 * Revisión automática de insignias.
 * Formatos recomendados para el campo insignias.criterio:
 * puntos:100 | temas:5 | evaluaciones:3 | recursos:10 | nivel:2
 * También acepta combinaciones separadas por coma.
 *
 * No crea nuevas tablas ni modifica las existentes.
 */

if (!function_exists('evaluarCriterioInsignia')) {
function evaluarCriterioInsignia(PDO $conexion, int $idUsuario, string $criterio): bool {
    $criterio = strtolower(trim($criterio));
    if ($criterio === '') return false;

    $st = $conexion->prepare("SELECT puntos,nivel FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return false;

    $valores = [
        'puntos' => (int)$u['puntos'],
        'nivel' => (int)$u['nivel'],
        'temas' => 0,
        'evaluaciones' => 0,
        'recursos' => 0,
    ];

    $st = $conexion->prepare("
        SELECT
          COALESCE(COUNT(CASE WHEN porcentaje_avance > 0 THEN 1 END),0) temas,
          COALESCE(SUM(evaluaciones_realizadas),0) evaluaciones,
          COALESCE(SUM(recursos_vistos),0) recursos
        FROM progreso
        WHERE id_usuario=?
    ");
    $st->execute([$idUsuario]);
    $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $valores['temas'] = (int)($p['temas'] ?? 0);
    $valores['evaluaciones'] = (int)($p['evaluaciones'] ?? 0);
    $valores['recursos'] = (int)($p['recursos'] ?? 0);

    foreach (preg_split('/[,;]+/', $criterio) as $regla) {
        $regla = trim($regla);
        if ($regla === '') continue;

        if (!preg_match('/^(puntos|nivel|temas|evaluaciones|recursos)\s*[:=]\s*(\d+)$/', $regla, $m)) {
            return false;
        }

        if ($valores[$m[1]] < (int)$m[2]) return false;
    }

    return true;
}

}

if (!function_exists('revisarYOtorgarInsignias')) {
function revisarYOtorgarInsignias(PDO $conexion, int $idUsuario): array {
    $otorgadas = [];

    $st = $conexion->query("
        SELECT id_insignia,nombre,descripcion,criterio,puntos_otorgados
        FROM insignias
        WHERE estado='Activa'
        ORDER BY id_insignia
    ");
    $insignias = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($insignias as $i) {
        $check = $conexion->prepare("SELECT 1 FROM usuarios_insignias WHERE id_usuario=? AND id_insignia=? LIMIT 1");
        $check->execute([$idUsuario,(int)$i['id_insignia']]);
        if ($check->fetchColumn()) continue;

        if (!evaluarCriterioInsignia($conexion,$idUsuario,(string)$i['criterio'])) continue;

        try {
            $conexion->beginTransaction();

            $insert = $conexion->prepare("
                INSERT INTO usuarios_insignias(id_usuario,id_insignia)
                VALUES(?,?)
            ");
            $insert->execute([$idUsuario,(int)$i['id_insignia']]);

            $puntos = max(0,(int)$i['puntos_otorgados']);
            if ($puntos > 0) {
                $up = $conexion->prepare("UPDATE usuarios SET puntos=puntos+? WHERE id_usuario=?");
                $up->execute([$puntos,$idUsuario]);

                $hist = $conexion->prepare("
                    INSERT INTO historial_puntos(id_usuario,motivo,puntos)
                    VALUES(?,?,?)
                ");
                $hist->execute([
                    $idUsuario,
                    'insignia:'.(int)$i['id_insignia'],
                    $puntos
                ]);
            }

            $conexion->commit();
            sincronizarNivel($conexion,$idUsuario);

            $otorgadas[] = [
                'id_insignia'=>(int)$i['id_insignia'],
                'nombre'=>$i['nombre'],
                'puntos'=>$puntos
            ];
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
        }
    }

    return $otorgadas;
}

/*
 * Llamada sencilla después de cualquier acción que pueda desbloquear
 * una insignia:
 *
 * revisarYOtorgarInsignias($conexion, $idUsuario);
 */

}

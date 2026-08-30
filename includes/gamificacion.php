<?php
declare(strict_types=1);

/**
 * Studia360 - Núcleo de progreso y gamificación.
 *
 * IMPORTANTE:
 * Este archivo está adaptado a la estructura actual de la BD
 * icfes_platform.sql.
 *
 * No utiliza columnas que actualmente NO existen:
 * - historial_puntos.referencia
 * - progreso.completado
 * - progreso.fecha_completado
 */

function obtenerNivelPorPuntos(PDO $conexion, int $puntos): array
{
    $puntos = max(0, $puntos);

    $stmt = $conexion->prepare("
        SELECT
            id_nivel,
            nombre,
            descripcion,
            puntos_minimos,
            puntos_maximos,
            imagen
        FROM niveles
        WHERE puntos_minimos <= ?
          AND puntos_maximos >= ?
        ORDER BY puntos_minimos DESC
        LIMIT 1
    ");
    $stmt->execute([$puntos, $puntos]);

    $nivel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nivel) {
        $stmt = $conexion->query("
            SELECT
                id_nivel,
                nombre,
                descripcion,
                puntos_minimos,
                puntos_maximos,
                imagen
            FROM niveles
            ORDER BY puntos_minimos ASC
            LIMIT 1
        ");

        $nivel = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$nivel) {
        throw new RuntimeException(
            "No existen niveles configurados en Studia360."
        );
    }

    return $nivel;
}

function obtenerGamificacionUsuario(PDO $conexion, int $idUsuario): array
{
    if ($idUsuario <= 0) {
        throw new InvalidArgumentException("El usuario no es válido.");
    }

    $stmt = $conexion->prepare("
        SELECT
            id_usuario,
            nombres,
            apellidos,
            puntos,
            nivel,
            avatar,
            id_avatar
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ");

    $stmt->execute([$idUsuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException("No se encontró el usuario.");
    }

    $puntos = (int)($usuario["puntos"] ?? 0);
    $nivel = obtenerNivelPorPuntos($conexion, $puntos);

    $minimo = (int)$nivel["puntos_minimos"];
    $maximo = (int)$nivel["puntos_maximos"];

    if ($maximo >= 999999) {
        $progresoNivel = 100;
        $puntosSiguienteNivel = null;
    } else {
        $rango = max(1, $maximo - $minimo + 1);

        $progresoNivel =
            (($puntos - $minimo) / $rango) * 100;

        $progresoNivel =
            max(0, min(100, $progresoNivel));

        $puntosSiguienteNivel = $maximo + 1;
    }

    return [
        "usuario" => $usuario,
        "nivel" => $nivel,
        "puntos" => $puntos,
        "progreso_nivel" => round($progresoNivel, 2),
        "puntos_siguiente_nivel" => $puntosSiguienteNivel
    ];
}

function sincronizarNivel(PDO $conexion, int $idUsuario): array
{
    $stmt = $conexion->prepare("
        SELECT puntos, nivel
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ");

    $stmt->execute([$idUsuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException("No se encontró el usuario.");
    }

    $nivelAnterior = (int)($usuario["nivel"] ?? 1);
    $puntos = (int)($usuario["puntos"] ?? 0);

    $nivelNuevo = obtenerNivelPorPuntos(
        $conexion,
        $puntos
    );

    $idNivelNuevo = (int)$nivelNuevo["id_nivel"];

    if ($nivelAnterior !== $idNivelNuevo) {
        $stmt = $conexion->prepare("
            UPDATE usuarios
            SET nivel = ?
            WHERE id_usuario = ?
        ");

        $stmt->execute([
            $idNivelNuevo,
            $idUsuario
        ]);
    }

    return [
        "subio_nivel" =>
            $idNivelNuevo > $nivelAnterior,

        "bajo_nivel" =>
            $idNivelNuevo < $nivelAnterior,

        "nivel_anterior" =>
            $nivelAnterior,

        "nivel_nuevo" =>
            $idNivelNuevo,

        "datos_nivel" =>
            $nivelNuevo,

        "puntos" =>
            $puntos
    ];
}

function yaRecibioPuntosPorReferencia(
    PDO $conexion,
    int $idUsuario,
    string $referencia
): bool {
    /*
     * La BD actual no tiene una columna "referencia".
     *
     * Para recompensas de temas usamos el motivo:
     * "Tema completado: ID".
     */
    $stmt = $conexion->prepare("
        SELECT id_historial
        FROM historial_puntos
        WHERE id_usuario = ?
          AND motivo = ?
        LIMIT 1
    ");

    $stmt->execute([
        $idUsuario,
        $referencia
    ]);

    return (bool)$stmt->fetchColumn();
}

function otorgarPuntos(
    PDO $conexion,
    int $idUsuario,
    int $puntos,
    string $motivo,
    ?string $referencia = null
): array {
    if ($idUsuario <= 0) {
        throw new InvalidArgumentException(
            "El usuario no es válido."
        );
    }

    if ($puntos <= 0) {
        throw new InvalidArgumentException(
            "La cantidad de puntos debe ser mayor que cero."
        );
    }

    $motivo = trim($motivo)
        ?: "Recompensa de Studia360";

    /*
     * Si se envía referencia, la usamos para impedir
     * duplicados sin necesitar una columna nueva.
     */
    $motivoControl = $referencia !== null &&
                     trim($referencia) !== ""
        ? trim($referencia)
        : $motivo;

    if (
        $referencia !== null &&
        trim($referencia) !== "" &&
        yaRecibioPuntosPorReferencia(
            $conexion,
            $idUsuario,
            $motivoControl
        )
    ) {
        $datos = obtenerGamificacionUsuario(
            $conexion,
            $idUsuario
        );

        return [
            "otorgados" => false,
            "duplicado" => true,
            "puntos_otorgados" => 0,
            "puntos_totales" => $datos["puntos"],
            "nivel" => $datos["nivel"],
            "subio_nivel" => false
        ];
    }

    $ownTransaction =
        !$conexion->inTransaction();

    if ($ownTransaction) {
        $conexion->beginTransaction();
    }

    try {

        $stmt = $conexion->prepare("
            UPDATE usuarios
            SET puntos = COALESCE(puntos, 0) + ?
            WHERE id_usuario = ?
        ");

        $stmt->execute([
            $puntos,
            $idUsuario
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                "No fue posible actualizar los puntos."
            );
        }

        /*
         * La tabla actual solo tiene:
         * id_usuario, motivo, puntos y fecha.
         */
        $motivoGuardar =
            $referencia !== null &&
            trim($referencia) !== ""
                ? $motivoControl
                : $motivo;

        $stmt = $conexion->prepare("
            INSERT INTO historial_puntos
                (id_usuario, motivo, puntos)
            VALUES
                (?, ?, ?)
        ");

        $stmt->execute([
            $idUsuario,
            $motivoGuardar,
            $puntos
        ]);

        $nivelResultado =
            sincronizarNivel(
                $conexion,
                $idUsuario
            );

        if ($ownTransaction) {
            $conexion->commit();
        }

        return [
            "otorgados" => true,
            "duplicado" => false,
            "puntos_otorgados" => $puntos,
            "puntos_totales" =>
                $nivelResultado["puntos"],
            "nivel" =>
                $nivelResultado["datos_nivel"],
            "subio_nivel" =>
                $nivelResultado["subio_nivel"],
            "nivel_anterior" =>
                $nivelResultado["nivel_anterior"],
            "nivel_nuevo" =>
                $nivelResultado["nivel_nuevo"]
        ];

    } catch (Throwable $e) {

        if (
            $ownTransaction &&
            $conexion->inTransaction()
        ) {
            $conexion->rollBack();
        }

        throw $e;
    }
}

function obtenerProgresoTema(
    PDO $conexion,
    int $idUsuario,
    int $idTema
): array {
    if ($idUsuario <= 0 || $idTema <= 0) {
        throw new InvalidArgumentException(
            "Usuario o tema no válido."
        );
    }

    $stmt = $conexion->prepare("
        SELECT
            id_progreso,
            id_usuario,
            id_tema,
            recursos_vistos,
            evaluaciones_realizadas,
            porcentaje_avance,
            ultima_actividad
        FROM progreso
        WHERE id_usuario = ?
          AND id_tema = ?
        LIMIT 1
    ");

    $stmt->execute([
        $idUsuario,
        $idTema
    ]);

    $progreso =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$progreso) {

        $stmt = $conexion->prepare("
            INSERT INTO progreso
                (
                    id_usuario,
                    id_tema,
                    recursos_vistos,
                    evaluaciones_realizadas,
                    porcentaje_avance,
                    ultima_actividad
                )
            VALUES
                (?, ?, 0, 0, 0.00, NOW())
        ");

        $stmt->execute([
            $idUsuario,
            $idTema
        ]);

        $stmt = $conexion->prepare("
            SELECT
                id_progreso,
                id_usuario,
                id_tema,
                recursos_vistos,
                evaluaciones_realizadas,
                porcentaje_avance,
                ultima_actividad
            FROM progreso
            WHERE id_usuario = ?
              AND id_tema = ?
            LIMIT 1
        ");

        $stmt->execute([
            $idUsuario,
            $idTema
        ]);

        $progreso =
            $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$progreso) {
        throw new RuntimeException(
            "No fue posible crear el progreso del tema."
        );
    }

    /*
     * Compatibilidad para que el resto del proyecto
     * pueda consultar "completado" sin que exista
     * físicamente esa columna.
     */
    $progreso["completado"] =
        (float)$progreso["porcentaje_avance"] >= 100
            ? 1
            : 0;

    $progreso["fecha_completado"] =
        $progreso["completado"]
            ? $progreso["ultima_actividad"]
            : null;

    return $progreso;
}

function actualizarProgresoTema(
    PDO $conexion,
    int $idUsuario,
    int $idTema,
    array $datos
): array {

    $actual =
        obtenerProgresoTema(
            $conexion,
            $idUsuario,
            $idTema
        );

    $recursos =
        array_key_exists(
            "recursos_vistos",
            $datos
        )
            ? max(
                0,
                (int)$datos["recursos_vistos"]
            )
            : (int)$actual["recursos_vistos"];

    $evaluaciones =
        array_key_exists(
            "evaluaciones_realizadas",
            $datos
        )
            ? max(
                0,
                (int)$datos["evaluaciones_realizadas"]
            )
            : (int)$actual["evaluaciones_realizadas"];

    $porcentaje =
        array_key_exists(
            "porcentaje_avance",
            $datos
        )
            ? (float)$datos["porcentaje_avance"]
            : (float)$actual["porcentaje_avance"];

    $porcentaje =
        max(0, min(100, $porcentaje));

    $completadoSolicitado =
        !empty($datos["completado"]) ||
        $porcentaje >= 100;

    /*
     * Si se marca completado, almacenamos 100%.
     * No necesitamos una columna "completado".
     */
    if ($completadoSolicitado) {
        $porcentaje = 100;
    }

    $stmt = $conexion->prepare("
        UPDATE progreso
        SET
            recursos_vistos = ?,
            evaluaciones_realizadas = ?,
            porcentaje_avance = ?,
            ultima_actividad = NOW()
        WHERE id_progreso = ?
    ");

    $stmt->execute([
        $recursos,
        $evaluaciones,
        $porcentaje,
        (int)$actual["id_progreso"]
    ]);

    $recompensa = null;

    /*
     * Solo premiamos la primera transición a 100%.
     */
    if (
        $porcentaje >= 100 &&
        (float)$actual["porcentaje_avance"] < 100
    ) {
        $recompensa = otorgarPuntos(
            $conexion,
            $idUsuario,
            10,
            "Tema completado",
            "Tema completado: " . $idTema
        );
    }

    return [
        "progreso" =>
            obtenerProgresoTema(
                $conexion,
                $idUsuario,
                $idTema
            ),
        "recompensa" =>
            $recompensa
    ];
}

function completarTema(
    PDO $conexion,
    int $idUsuario,
    int $idTema
): array {
    return actualizarProgresoTema(
        $conexion,
        $idUsuario,
        $idTema,
        [
            "porcentaje_avance" => 100,
            "completado" => 1
        ]
    );
}

function obtenerProgresoMateria(
    PDO $conexion,
    int $idUsuario,
    int $idMateria,
    ?string $grado = null
): array {

    $sql = "
        SELECT
            COUNT(t.id_tema) AS total_temas,

            COALESCE(
                SUM(
                    CASE
                        WHEN COALESCE(
                            p.porcentaje_avance,
                            0
                        ) >= 100
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS temas_completados,

            COALESCE(
                AVG(
                    COALESCE(
                        p.porcentaje_avance,
                        0
                    )
                ),
                0
            ) AS porcentaje

        FROM temas t

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = ?

        WHERE t.id_materia = ?
    ";

    $params = [
        $idUsuario,
        $idMateria
    ];

    if ($grado !== null && $grado !== "") {
        $sql .= " AND t.grado = ?";
        $params[] = $grado;
    }

    $stmt =
        $conexion->prepare($sql);

    $stmt->execute($params);

    $r =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    return [
        "total_temas" =>
            (int)($r["total_temas"] ?? 0),

        "temas_completados" =>
            (int)($r["temas_completados"] ?? 0),

        "porcentaje" =>
            round(
                max(
                    0,
                    min(
                        100,
                        (float)(
                            $r["porcentaje"] ?? 0
                        )
                    )
                ),
                2
            )
    ];
}

function obtenerProgresoGeneral(
    PDO $conexion,
    int $idUsuario,
    ?string $grado = null
): array {

    $sql = "
        SELECT
            COUNT(t.id_tema) AS total_temas,

            COALESCE(
                SUM(
                    CASE
                        WHEN COALESCE(
                            p.porcentaje_avance,
                            0
                        ) >= 100
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS temas_completados,

            COALESCE(
                AVG(
                    COALESCE(
                        p.porcentaje_avance,
                        0
                    )
                ),
                0
            ) AS porcentaje

        FROM temas t

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = ?
    ";

    $params = [$idUsuario];

    if ($grado !== null && $grado !== "") {
        $sql .= " WHERE t.grado = ?";
        $params[] = $grado;
    }

    $stmt =
        $conexion->prepare($sql);

    $stmt->execute($params);

    $r =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    return [
        "total_temas" =>
            (int)($r["total_temas"] ?? 0),

        "temas_completados" =>
            (int)($r["temas_completados"] ?? 0),

        "porcentaje" =>
            round(
                max(
                    0,
                    min(
                        100,
                        (float)(
                            $r["porcentaje"] ?? 0
                        )
                    )
                ),
                2
            )
    ];
}

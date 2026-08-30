<?php
/**
 * Studia360
 * Evaluación para estudiantes
 * Fase 2 - Evaluaciones e intentos
 *
 * Archivo:
 * estudiante/evaluacion.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../includes/seguridad.php";
require_once __DIR__ . "/../includes/gamificacion.php";

exigirEstudiante();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$idUsuario = (int)($_SESSION["id_usuario"] ?? 0);
$idEvaluacion = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($idUsuario <= 0 || !$idEvaluacion) {
    redireccionarDashboardUsuario();
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_evaluacion"])) {
    $_SESSION["csrf_evaluacion"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_evaluacion"];

$errores = [];
$evaluacion = null;
$preguntas = [];
$intento = null;
$bloqueada = false;
$mensajeBloqueo = "";

try {

    /*
     * Evaluación activa.
     * El acceso es transversal: el estudiante puede realizar
     * evaluaciones de otros grados si están publicadas.
     */
    $stmt = $conexion->prepare("
        SELECT
            e.id_evaluacion,
            e.id_materia,
            e.id_tema,
            e.titulo,
            e.descripcion,
            e.instrucciones,
            e.tipo,
            e.grado,
            e.tiempo_minutos,
            e.intentos_permitidos,
            e.puntaje_maximo,
            e.estado,
            m.nombre AS materia,
            t.nombre AS tema
        FROM evaluaciones e
        INNER JOIN materias m
            ON m.id_materia = e.id_materia
        LEFT JOIN temas t
            ON t.id_tema = e.id_tema
        WHERE e.id_evaluacion = ?
          AND e.estado = 'Activo'
        LIMIT 1
    ");
    $stmt->execute([$idEvaluacion]);
    $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluacion) {
        redireccionarDashboardUsuario();
    }

    /*
     * Preguntas activas de la evaluación.
     */
    $stmtPreguntas = $conexion->prepare("
        SELECT
            id_pregunta,
            enunciado,
            imagen,
            nivel,
            explicacion
        FROM preguntas
        WHERE id_evaluacion = ?
          AND estado = 'Activo'
        ORDER BY id_pregunta ASC
    ");
    $stmtPreguntas->execute([$idEvaluacion]);
    $preguntas = $stmtPreguntas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($preguntas)) {
        $bloqueada = true;
        $mensajeBloqueo = "Esta evaluación todavía no tiene preguntas disponibles.";
    }

    /*
     * Intentos finalizados del estudiante.
     */
    $stmtIntentos = $conexion->prepare("
        SELECT COUNT(*)
        FROM intentos
        WHERE id_usuario = ?
          AND id_evaluacion = ?
          AND estado = 'Finalizado'
    ");
    $stmtIntentos->execute([$idUsuario, $idEvaluacion]);
    $intentosRealizados = (int)$stmtIntentos->fetchColumn();

    $limiteIntentos = max(1, (int)$evaluacion["intentos_permitidos"]);

    /*
     * Si ya existe un intento en proceso, lo retomamos.
     * Así refrescar la página no crea intentos duplicados.
     */
    $stmtEnProceso = $conexion->prepare("
        SELECT
            id_intento,
            fecha_inicio,
            fecha_fin,
            estado
        FROM intentos
        WHERE id_usuario = ?
          AND id_evaluacion = ?
          AND estado = 'En proceso'
        ORDER BY id_intento DESC
        LIMIT 1
    ");
    $stmtEnProceso->execute([$idUsuario, $idEvaluacion]);
    $intento = $stmtEnProceso->fetch(PDO::FETCH_ASSOC);

    if (!$intento && $intentosRealizados >= $limiteIntentos) {
        $bloqueada = true;
        $mensajeBloqueo =
            "Has utilizado todos los intentos permitidos para esta evaluación.";
    }

    /*
     * Crear intento al entrar.
     */
    if (!$bloqueada && !$intento) {
        $ahora = date("Y-m-d H:i:s");

        $stmtCrear = $conexion->prepare("
            INSERT INTO intentos
            (
                id_usuario,
                id_evaluacion,
                fecha_inicio,
                estado,
                ip,
                navegador
            )
            VALUES (?, ?, ?, 'En proceso', ?, ?)
        ");

        $stmtCrear->execute([
            $idUsuario,
            $idEvaluacion,
            $ahora,
            $_SERVER["REMOTE_ADDR"] ?? null,
            substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 150)
        ]);

        $idIntento = (int)$conexion->lastInsertId();

        $intento = [
            "id_intento" => $idIntento,
            "fecha_inicio" => $ahora,
            "fecha_fin" => null,
            "estado" => "En proceso"
        ];
    }

    /*
     * Opciones de cada pregunta.
     */
    if (!$bloqueada) {
        $idsPreguntas = array_map(
            static fn($p) => (int)$p["id_pregunta"],
            $preguntas
        );

        if (!empty($idsPreguntas)) {
            $placeholders = implode(",", array_fill(0, count($idsPreguntas), "?"));

            $stmtOpciones = $conexion->prepare("
                SELECT
                    id_opcion,
                    id_pregunta,
                    opcion,
                    descripcion
                FROM opciones
                WHERE id_pregunta IN ($placeholders)
                ORDER BY id_pregunta ASC, opcion ASC
            ");

            $stmtOpciones->execute($idsPreguntas);
            $filasOpciones = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);

            $opcionesPorPregunta = [];

            foreach ($filasOpciones as $opcion) {
                $opcionesPorPregunta[(int)$opcion["id_pregunta"]][] = $opcion;
            }

            foreach ($preguntas as &$pregunta) {
                $pregunta["opciones"] =
                    $opcionesPorPregunta[(int)$pregunta["id_pregunta"]] ?? [];
            }
            unset($pregunta);
        }
    }

    /*
     * Procesar envío.
     */
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !$bloqueada) {

        $token = (string)($_POST["csrf"] ?? "");

        if (!hash_equals($csrf, $token)) {
            $errores[] =
                "La sesión de seguridad expiró. Recarga la página e inténtalo nuevamente.";
        } else {

            $accion = trim((string)($_POST["accion"] ?? ""));

            if ($accion !== "finalizar") {
                $errores[] = "La acción solicitada no es válida.";
            } else {

                $idIntento = (int)($intento["id_intento"] ?? 0);

                if ($idIntento <= 0) {
                    $errores[] = "No fue posible identificar tu intento.";
                } else {

                    try {

                        /*
                         * Bloqueo para evitar dos envíos simultáneos.
                         */
                        $stmtBloqueo = $conexion->prepare("
                            SELECT
                                id_intento,
                                fecha_inicio,
                                estado
                            FROM intentos
                            WHERE id_intento = ?
                              AND id_usuario = ?
                              AND id_evaluacion = ?
                            LIMIT 1
                            FOR UPDATE
                        ");

                        $conexion->beginTransaction();

                        $stmtBloqueo->execute([
                            $idIntento,
                            $idUsuario,
                            $idEvaluacion
                        ]);

                        $intentoActual = $stmtBloqueo->fetch(PDO::FETCH_ASSOC);

                        if (!$intentoActual) {
                            throw new RuntimeException(
                                "El intento no está disponible."
                            );
                        }

                        if ($intentoActual["estado"] === "Finalizado") {
                            $conexion->rollBack();

                            header(
                                "Location: " .
                                urlAplicacion(
                                    "/estudiante/resultado_evaluacion.php?id=" .
                                    $idIntento
                                )
                            );
                            exit;
                        }

                        $inicioTimestamp = strtotime(
                            (string)$intentoActual["fecha_inicio"]
                        );

                        $ahoraTimestamp = time();

                        if ($inicioTimestamp === false) {
                            $inicioTimestamp = $ahoraTimestamp;
                        }

                        $tiempoEmpleado = max(
                            0,
                            $ahoraTimestamp - $inicioTimestamp
                        );

                        $tiempoLimite = max(
                            0,
                            (int)$evaluacion["tiempo_minutos"]
                        ) * 60;

                        /*
                         * El servidor también controla el tiempo.
                         * Si se acabó, se procesa igualmente el envío.
                         */
                        $respuestas = $_POST["respuesta"] ?? [];

                        if (!is_array($respuestas)) {
                            $respuestas = [];
                        }

                        $correctas = 0;
                        $incorrectas = 0;
                        $guardadas = 0;

                        /*
                         * Insertamos únicamente opciones que pertenecen
                         * a la pregunta correspondiente.
                         */
                        $stmtValidarOpcion = $conexion->prepare("
                            SELECT
                                id_opcion,
                                es_correcta
                            FROM opciones
                            WHERE id_opcion = ?
                              AND id_pregunta = ?
                            LIMIT 1
                        ");

                        $stmtRespuesta = $conexion->prepare("
                            INSERT INTO respuestas_usuario
                            (
                                id_intento,
                                id_pregunta,
                                id_opcion,
                                es_correcta
                            )
                            VALUES (?, ?, ?, ?)
                        ");

                        foreach ($preguntas as $pregunta) {

                            $idPregunta =
                                (int)$pregunta["id_pregunta"];

                            $idOpcion = filter_var(
                                $respuestas[$idPregunta] ?? null,
                                FILTER_VALIDATE_INT
                            );

                            if (!$idOpcion) {
                                $incorrectas++;
                                continue;
                            }

                            $stmtValidarOpcion->execute([
                                $idOpcion,
                                $idPregunta
                            ]);

                            $opcion = $stmtValidarOpcion->fetch(PDO::FETCH_ASSOC);

                            if (!$opcion) {
                                $incorrectas++;
                                continue;
                            }

                            $esCorrecta =
                                (int)$opcion["es_correcta"] === 1 ? 1 : 0;

                            if ($esCorrecta) {
                                $correctas++;
                            } else {
                                $incorrectas++;
                            }

                            $stmtRespuesta->execute([
                                $idIntento,
                                $idPregunta,
                                $idOpcion,
                                $esCorrecta
                            ]);

                            $guardadas++;
                        }

                        $totalPreguntas = count($preguntas);

                        $porcentaje = $totalPreguntas > 0
                            ? round(
                                ($correctas / $totalPreguntas) * 100,
                                2
                            )
                            : 0;

                        $puntajeMaximo = max(
                            1,
                            (int)$evaluacion["puntaje_maximo"]
                        );

                        $puntaje = round(
                            ($porcentaje / 100) * $puntajeMaximo,
                            2
                        );

                        $fechaFin = date(
                            "Y-m-d H:i:s",
                            $ahoraTimestamp
                        );

                        $stmtFinalizar = $conexion->prepare("
                            UPDATE intentos
                            SET
                                fecha_fin = ?,
                                respuestas_correctas = ?,
                                respuestas_incorrectas = ?,
                                puntaje = ?,
                                tiempo_empleado = ?,
                                estado = 'Finalizado'
                            WHERE id_intento = ?
                              AND id_usuario = ?
                              AND estado = 'En proceso'
                        ");

                        $stmtFinalizar->execute([
                            $fechaFin,
                            $correctas,
                            $incorrectas,
                            $puntaje,
                            $tiempoEmpleado,
                            $idIntento,
                            $idUsuario
                        ]);

                        /*
                         * Actualizar progreso del tema.
                         * Se mantiene la lógica de 10% inicial +
                         * recursos + evaluaciones.
                         */
                        if (!empty($evaluacion["id_tema"])) {

                            $idTema = (int)$evaluacion["id_tema"];

                            $stmtProgreso = $conexion->prepare("
                                SELECT
                                    recursos_vistos,
                                    evaluaciones_realizadas,
                                    porcentaje_avance
                                FROM progreso
                                WHERE id_usuario = ?
                                  AND id_tema = ?
                                LIMIT 1
                                FOR UPDATE
                            ");

                            $stmtProgreso->execute([
                                $idUsuario,
                                $idTema
                            ]);

                            $progreso = $stmtProgreso->fetch(PDO::FETCH_ASSOC);

                            $recursosVistos = (int)(
                                $progreso["recursos_vistos"] ?? 0
                            );

                            $evaluacionesRealizadas = (int)(
                                $progreso["evaluaciones_realizadas"] ?? 0
                            );

                            $evaluacionesRealizadas++;

                            $stmtTotales = $conexion->prepare("
                                SELECT
                                    (
                                        SELECT COUNT(*)
                                        FROM recursos
                                        WHERE id_tema = ?
                                          AND estado = 'Activo'
                                    ) AS total_recursos,
                                    (
                                        SELECT COUNT(*)
                                        FROM evaluaciones
                                        WHERE id_tema = ?
                                          AND estado = 'Activo'
                                    ) AS total_evaluaciones
                            ");

                            $stmtTotales->execute([
                                $idTema,
                                $idTema
                            ]);

                            $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC);

                            $totalRecursos = (int)(
                                $totales["total_recursos"] ?? 0
                            );

                            $totalEvaluaciones = max(
                                1,
                                (int)(
                                    $totales["total_evaluaciones"] ?? 1
                                )
                            );

                            $avance = 10;

                            if ($totalRecursos > 0) {
                                $avance += min(
                                    60,
                                    ($recursosVistos / $totalRecursos) * 60
                                );
                            } else {
                                $avance += 60;
                            }

                            $avance += min(
                                30,
                                ($evaluacionesRealizadas / $totalEvaluaciones) * 30
                            );

                            $avance = min(
                                99,
                                round($avance, 2)
                            );

                            if ($progreso) {

                                $stmtActualizarProgreso = $conexion->prepare("
                                    UPDATE progreso
                                    SET
                                        evaluaciones_realizadas = ?,
                                        porcentaje_avance = ?,
                                        ultima_actividad = NOW()
                                    WHERE id_usuario = ?
                                      AND id_tema = ?
                                ");

                                $stmtActualizarProgreso->execute([
                                    $evaluacionesRealizadas,
                                    $avance,
                                    $idUsuario,
                                    $idTema
                                ]);

                            } else {

                                $stmtInsertarProgreso = $conexion->prepare("
                                    INSERT INTO progreso
                                    (
                                        id_usuario,
                                        id_tema,
                                        recursos_vistos,
                                        evaluaciones_realizadas,
                                        porcentaje_avance,
                                        ultima_actividad
                                    )
                                    VALUES (?, ?, ?, ?, ?, NOW())
                                ");

                                $stmtInsertarProgreso->execute([
                                    $idUsuario,
                                    $idTema,
                                    $recursosVistos,
                                    1,
                                    $avance
                                ]);
                            }
                        }

                        /*
                         * XP por completar una evaluación.
                         * Se registra en historial y se sincroniza el nivel.
                         */
                        $xpGanada = max(
                            5,
                            (int)round($puntaje / 20)
                        );

                        $stmtXP = $conexion->prepare("
                            UPDATE usuarios
                            SET puntos = puntos + ?
                            WHERE id_usuario = ?
                        ");
                        $stmtXP->execute([
                            $xpGanada,
                            $idUsuario
                        ]);

                        $stmtHistorial = $conexion->prepare("
                            INSERT INTO historial_puntos
                            (
                                id_usuario,
                                motivo,
                                puntos
                            )
                            VALUES (?, ?, ?)
                        ");

                        $stmtHistorial->execute([
                            $idUsuario,
                            "Evaluación: " . $evaluacion["titulo"],
                            $xpGanada
                        ]);

                        $conexion->commit();

                        /*
                         * Recalcular nivel si la función existe.
                         */
                        if (function_exists("sincronizarNivel")) {
                            sincronizarNivel(
                                $conexion,
                                $idUsuario
                            );
                        }

                        header(
                            "Location: " .
                            urlAplicacion(
                                "/estudiante/resultado_evaluacion.php?id=" .
                                $idIntento
                            )
                        );
                        exit;

                    } catch (Throwable $e) {

                        if ($conexion->inTransaction()) {
                            $conexion->rollBack();
                        }

                        $errores[] =
                            "No fue posible finalizar la evaluación. Revisa tus respuestas e inténtalo nuevamente.";
                    }
                }
            }
        }
    }

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $errores[] =
        "No fue posible cargar la evaluación. Inténtalo nuevamente.";
}

$nombreEvaluacion = (string)($evaluacion["titulo"] ?? "Evaluación");
$tiempoMinutos = max(1, (int)($evaluacion["tiempo_minutos"] ?? 30));
$fechaInicio = (string)($intento["fecha_inicio"] ?? date("Y-m-d H:i:s"));

$urlRegreso = !empty($evaluacion["id_tema"])
    ? urlAplicacion(
        "/estudiante/tema.php?id=" .
        (int)$evaluacion["id_tema"]
    )
    : urlAplicacion("/estudiante/dashboard.php");

$urlDashboard = urlAplicacion("/estudiante/dashboard.php");
$urlLogout = urlAplicacion("/cerrar_sesion.php");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($nombreEvaluacion) ?> | Studia360</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>
:root{
    --primary:#0d6efd;
    --dark:#0b1f3a;
    --bg:#f4f7fb;
    --text:#172033;
    --muted:#667085;
    --border:#e5eaf1;
}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.navbar-studia{
    background:linear-gradient(110deg,var(--dark),var(--primary));
    box-shadow:0 6px 24px rgba(13,110,253,.18);
}

.page{
    width:min(1050px,calc(100% - 28px));
    margin:auto;
    padding:28px 0 70px;
}

.hero{
    position:relative;
    overflow:hidden;
    border-radius:24px;
    padding:28px;
    color:#fff;
    background:linear-gradient(135deg,#0d6efd,#084298);
    box-shadow:0 18px 45px rgba(13,110,253,.20);
}

.hero:after{
    content:"";
    position:absolute;
    width:280px;
    height:280px;
    border-radius:50%;
    right:-120px;
    top:-150px;
    background:rgba(255,255,255,.10);
}

.hero-content{
    position:relative;
    z-index:1;
}

.question-card{
    border:1px solid var(--border);
    border-radius:20px;
    background:#fff;
    padding:24px;
    box-shadow:0 10px 28px rgba(17,24,39,.06);
}

.option{
    display:flex;
    gap:14px;
    align-items:flex-start;
    padding:15px;
    border:1px solid #dfe5ed;
    border-radius:14px;
    cursor:pointer;
    transition:.18s ease;
    background:#fff;
}

.option:hover{
    border-color:#86b7fe;
    background:#f7fbff;
}

.option input{
    margin-top:4px;
}

.option-letter{
    min-width:32px;
    height:32px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:#eaf2ff;
    color:#0d6efd;
    font-weight:800;
}

.sticky-bar{
    position:sticky;
    bottom:16px;
    z-index:10;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(12px);
    border:1px solid var(--border);
    border-radius:18px;
    padding:12px 16px;
    box-shadow:0 14px 35px rgba(17,24,39,.12);
}

.timer{
    font-variant-numeric:tabular-nums;
    font-weight:800;
}

.timer.warning{
    color:#dc3545;
}

.empty-state{
    border-radius:20px;
    border:1px dashed #cfd7e3;
    background:#fff;
    padding:45px 25px;
    text-align:center;
}
</style>
</head>

<body>

<nav class="navbar navbar-dark navbar-studia">
<div class="container-fluid px-3 px-md-4">
    <a
        class="navbar-brand fw-bold"
        href="<?= e($urlDashboard) ?>"
    >
        <i class="bi bi-mortarboard-fill me-2"></i>
        Studia360
    </a>

    <div class="d-flex align-items-center gap-2">
        <a
            href="<?= e($urlDashboard) ?>"
            class="btn btn-sm btn-outline-light"
        >
            <i class="bi bi-house me-1"></i>
            Inicio
        </a>

        <a
            href="<?= e($urlLogout) ?>"
            class="btn btn-sm btn-light"
        >
            Salir
        </a>
    </div>
</div>
</nav>

<main class="page">

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= e($errores[0]) ?>
    </div>
<?php endif; ?>

<section class="hero mb-4">
    <div class="hero-content">

        <div class="small text-uppercase fw-bold opacity-75">
            <?= e($evaluacion["tipo"] ?? "Evaluación") ?>
            · <?= e($evaluacion["grado"] ?? "") ?>°
        </div>

        <h1 class="h2 fw-bold mt-2 mb-2">
            <?= e($nombreEvaluacion) ?>
        </h1>

        <p class="mb-3 opacity-75">
            <?= e(
                $evaluacion["descripcion"]
                ?? "Pon a prueba lo que has aprendido."
            ) ?>
        </p>

        <div class="d-flex flex-wrap gap-2">

            <span class="badge rounded-pill text-bg-light">
                <i class="bi bi-clock me-1"></i>
                <?= $tiempoMinutos ?> min
            </span>

            <span class="badge rounded-pill text-bg-light">
                <i class="bi bi-list-check me-1"></i>
                <?= count($preguntas) ?> preguntas
            </span>

            <span class="badge rounded-pill text-bg-light">
                <i class="bi bi-arrow-repeat me-1"></i>
                <?= max(1, (int)($evaluacion["intentos_permitidos"] ?? 1)) ?> intento(s)
            </span>

        </div>
    </div>
</section>

<?php if ($bloqueada): ?>

<div class="empty-state">

    <div class="display-5 text-primary mb-3">
        <i class="bi bi-clipboard-x"></i>
    </div>

    <h2 class="h4 fw-bold">
        Evaluación no disponible
    </h2>

    <p class="text-secondary mb-4">
        <?= e($mensajeBloqueo) ?>
    </p>

    <a
        href="<?= e($urlRegreso) ?>"
        class="btn btn-primary"
    >
        <i class="bi bi-arrow-left me-1"></i>
        Volver
    </a>

</div>

<?php else: ?>

<?php if (!empty($evaluacion["instrucciones"])): ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">

        <h2 class="h6 fw-bold mb-2">
            <i class="bi bi-info-circle-fill text-primary me-2"></i>
            Instrucciones
        </h2>

        <div class="text-secondary">
            <?= nl2br(e($evaluacion["instrucciones"])) ?>
        </div>

    </div>
</div>

<?php endif; ?>

<form method="POST" id="evaluacionForm">

<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="accion" value="finalizar">

<div class="d-grid gap-4">

<?php foreach ($preguntas as $indice => $pregunta): ?>

<div class="question-card">

    <div class="d-flex justify-content-between gap-3 mb-3">

        <div>
            <div class="small text-uppercase text-primary fw-bold">
                Pregunta <?= $indice + 1 ?>
            </div>

            <h2 class="h5 fw-bold mt-1 mb-0">
                <?= nl2br(e($pregunta["enunciado"])) ?>
            </h2>
        </div>

        <span class="badge rounded-pill text-bg-light border align-self-start">
            <?= e($pregunta["nivel"] ?? "Medio") ?>
        </span>

    </div>

    <?php if (!empty($pregunta["imagen"])): ?>
        <div class="mb-4 text-center">
            <img
                src="<?= e($pregunta["imagen"]) ?>"
                alt="Imagen de la pregunta"
                class="img-fluid rounded-4 border"
                style="max-height:420px;object-fit:contain;"
            >
        </div>
    <?php endif; ?>

    <div class="d-grid gap-2">

    <?php foreach (($pregunta["opciones"] ?? []) as $opcion): ?>

        <label class="option">

            <input
                type="radio"
                class="form-check-input"
                name="respuesta[<?= (int)$pregunta["id_pregunta"] ?>]"
                value="<?= (int)$opcion["id_opcion"] ?>"
            >

            <span class="option-letter">
                <?= e($opcion["opcion"]) ?>
            </span>

            <span class="flex-grow-1">
                <?= nl2br(e($opcion["descripcion"])) ?>
            </span>

        </label>

    <?php endforeach; ?>

    </div>

    <?php if (empty($pregunta["opciones"])): ?>
        <div class="alert alert-warning mt-3 mb-0">
            Esta pregunta no tiene opciones configuradas.
        </div>
    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

<div class="sticky-bar mt-4">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

        <div>
            <div class="small text-secondary">
                Tiempo restante
            </div>

            <div
                id="timer"
                class="timer fs-5"
                data-start="<?= e($fechaInicio) ?>"
                data-minutes="<?= $tiempoMinutos ?>"
            >
                --:--
            </div>
        </div>

        <div class="d-flex gap-2">

            <a
                href="<?= e($urlRegreso) ?>"
                class="btn btn-outline-secondary"
            >
                Salir
            </a>

            <button
                type="submit"
                class="btn btn-primary px-4"
                id="submitButton"
            >
                <i class="bi bi-check2-circle me-1"></i>
                Finalizar evaluación
            </button>

        </div>

    </div>

</div>

</form>

<?php endif; ?>

</main>

<script>
(function(){

    const timer = document.getElementById("timer");
    const form = document.getElementById("evaluacionForm");
    const submitButton = document.getElementById("submitButton");

    if (!timer || !form) {
        return;
    }

    const startText = timer.dataset.start;
    const minutes = parseInt(timer.dataset.minutes || "30", 10);

    /*
     * PHP entrega "YYYY-MM-DD HH:MM:SS".
     * Convertimos a formato local del navegador.
     */
    const start = new Date(
        startText.replace(" ", "T")
    ).getTime();

    const deadline = start + (minutes * 60 * 1000);

    let submitted = false;

    function pintarTiempo(){

        const restante = Math.max(
            0,
            deadline - Date.now()
        );

        const totalSegundos =
            Math.floor(restante / 1000);

        const minutosRestantes =
            Math.floor(totalSegundos / 60);

        const segundos =
            totalSegundos % 60;

        timer.textContent =
            String(minutosRestantes).padStart(2, "0") +
            ":" +
            String(segundos).padStart(2, "0");

        if (totalSegundos <= 60) {
            timer.classList.add("warning");
        }

        if (restante <= 0 && !submitted) {

            submitted = true;

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<i class="bi bi-hourglass-split me-1"></i> Procesando...';
            }

            form.submit();
        }
    }

    form.addEventListener("submit", function(){

        if (submitted) {
            return;
        }

        submitted = true;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<i class="bi bi-hourglass-split me-1"></i> Guardando...';
        }
    });

    pintarTiempo();
    setInterval(pintarTiempo, 1000);

})();
</script>

</body>
</html>

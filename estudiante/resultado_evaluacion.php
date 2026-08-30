<?php
/**
 * Studia360
 * Resultado de evaluación para estudiantes
 * Fase 2 - Evaluaciones e intentos
 *
 * Archivo:
 * estudiante/resultado_evaluacion.php
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
$idIntento = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($idUsuario <= 0 || !$idIntento) {
    redireccionarDashboardUsuario();
}

$errores = [];
$intento = null;
$respuestas = [];
$gamificacion = null;

try {

    /*
     * El resultado solo pertenece al estudiante que realizó el intento.
     */
    $stmt = $conexion->prepare("
        SELECT
            i.id_intento,
            i.id_evaluacion,
            i.fecha_inicio,
            i.fecha_fin,
            i.respuestas_correctas,
            i.respuestas_incorrectas,
            i.puntaje,
            i.tiempo_empleado,
            i.estado,

            e.titulo,
            e.descripcion,
            e.tipo,
            e.grado,
            e.puntaje_maximo,
            e.id_tema,

            m.nombre AS materia,
            t.nombre AS tema

        FROM intentos i

        INNER JOIN evaluaciones e
            ON e.id_evaluacion = i.id_evaluacion

        INNER JOIN materias m
            ON m.id_materia = e.id_materia

        LEFT JOIN temas t
            ON t.id_tema = e.id_tema

        WHERE i.id_intento = ?
          AND i.id_usuario = ?
          AND i.estado = 'Finalizado'

        LIMIT 1
    ");

    $stmt->execute([
        $idIntento,
        $idUsuario
    ]);

    $intento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$intento) {
        redireccionarDashboardUsuario();
    }

    /*
     * Respuestas con la opción seleccionada y la correcta.
     */
    $stmtRespuestas = $conexion->prepare("
        SELECT
            ru.id_respuesta,
            ru.id_pregunta,
            ru.id_opcion,
            ru.es_correcta,

            p.enunciado,
            p.imagen,
            p.explicacion,
            p.nivel,

            op.opcion AS letra_seleccionada,
            op.descripcion AS respuesta_seleccionada

        FROM respuestas_usuario ru

        INNER JOIN preguntas p
            ON p.id_pregunta = ru.id_pregunta

        INNER JOIN opciones op
            ON op.id_opcion = ru.id_opcion

        WHERE ru.id_intento = ?

        ORDER BY ru.id_respuesta ASC
    ");

    $stmtRespuestas->execute([$idIntento]);
    $respuestas = $stmtRespuestas->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Gamificación actual.
     */
    if (function_exists("sincronizarNivel")) {
        sincronizarNivel(
            $conexion,
            $idUsuario
        );
    }

    if (function_exists("obtenerGamificacionUsuario")) {
        $gamificacion = obtenerGamificacionUsuario(
            $conexion,
            $idUsuario
        );
    }

} catch (Throwable $e) {

    $errores[] =
        "No fue posible cargar el resultado. Inténtalo nuevamente.";
}

$correctas = (int)($intento["respuestas_correctas"] ?? 0);
$incorrectas = (int)($intento["respuestas_incorrectas"] ?? 0);
$totalRespondidas = $correctas + $incorrectas;

$puntajeMaximo = max(
    1,
    (int)($intento["puntaje_maximo"] ?? 100)
);

$puntaje = (float)($intento["puntaje"] ?? 0);

$porcentaje = $puntajeMaximo > 0
    ? round(($puntaje / $puntajeMaximo) * 100, 0)
    : 0;

$tiempoEmpleado = max(
    0,
    (int)($intento["tiempo_empleado"] ?? 0)
);

$minutosEmpleado = intdiv(
    $tiempoEmpleado,
    60
);

$segundosEmpleado = $tiempoEmpleado % 60;

if ($porcentaje >= 90) {
    $mensajeResultado = "¡Excelente trabajo!";
    $iconoResultado = "bi-trophy-fill";
} elseif ($porcentaje >= 70) {
    $mensajeResultado = "¡Muy bien! Sigue así.";
    $iconoResultado = "bi-stars";
} elseif ($porcentaje >= 50) {
    $mensajeResultado = "Buen intento. Puedes mejorar.";
    $iconoResultado = "bi-graph-up-arrow";
} else {
    $mensajeResultado = "No te preocupes. Repasa el tema y vuelve a intentarlo.";
    $iconoResultado = "bi-arrow-repeat";
}

$urlDashboard = urlAplicacion("/estudiante/dashboard.php");

$urlTema = !empty($intento["id_tema"])
    ? urlAplicacion(
        "/estudiante/tema.php?id=" .
        (int)$intento["id_tema"]
    )
    : $urlDashboard;

$urlLogout = urlAplicacion("/cerrar_sesion.php");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Resultado | Studia360</title>

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
    width:min(1100px,calc(100% - 28px));
    margin:auto;
    padding:28px 0 70px;
}

.result-hero{
    position:relative;
    overflow:hidden;
    border-radius:26px;
    padding:32px;
    color:#fff;
    background:linear-gradient(135deg,#0d6efd,#084298);
    box-shadow:0 18px 45px rgba(13,110,253,.20);
}

.result-hero:after{
    content:"";
    position:absolute;
    width:320px;
    height:320px;
    border-radius:50%;
    right:-130px;
    top:-180px;
    background:rgba(255,255,255,.10);
}

.result-content{
    position:relative;
    z-index:1;
}

.score-ring{
    width:150px;
    height:150px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    background:rgba(255,255,255,.13);
    border:7px solid rgba(255,255,255,.45);
    margin:auto;
}

.score-number{
    font-size:2.7rem;
    font-weight:900;
    line-height:1;
}

.stat-card{
    height:100%;
    border:1px solid var(--border);
    background:#fff;
    border-radius:18px;
    padding:20px;
    box-shadow:0 8px 25px rgba(17,24,39,.05);
}

.answer-card{
    border:1px solid var(--border);
    border-radius:18px;
    background:#fff;
    padding:22px;
}

.answer-correct{
    border-left:5px solid #198754;
}

.answer-wrong{
    border-left:5px solid #dc3545;
}

.explanation{
    border-radius:14px;
    background:#f6f8fb;
    padding:14px;
}

.sticky-actions{
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

    <div class="d-flex gap-2">

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

<div class="alert alert-danger border-0 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= e($errores[0]) ?>
</div>

<?php else: ?>

<section class="result-hero mb-4">

<div class="result-content">

<div class="row align-items-center g-4">

<div class="col-lg-8">

<div class="small text-uppercase fw-bold opacity-75">
    <?= e($intento["tipo"]) ?>
    · <?= e($intento["grado"]) ?>°
</div>

<h1 class="display-6 fw-bold mt-2 mb-2">
    <?= e($intento["titulo"]) ?>
</h1>

<p class="mb-2 opacity-75">
    <?= e($intento["materia"]) ?>
    <?php if (!empty($intento["tema"])): ?>
        · <?= e($intento["tema"]) ?>
    <?php endif; ?>
</p>

<div class="d-flex align-items-center gap-2 mt-4">
    <i class="bi <?= e($iconoResultado) ?> fs-3"></i>
    <strong>
        <?= e($mensajeResultado) ?>
    </strong>
</div>

</div>

<div class="col-lg-4 text-center">

<div class="score-ring">

<div class="score-number">
    <?= e($porcentaje) ?>%
</div>

<div class="small opacity-75">
    <?= number_format($puntaje, 0) ?>
    /
    <?= number_format($puntajeMaximo, 0) ?>
</div>

</div>

</div>

</div>

</div>

</section>

<div class="row g-3 mb-4">

<div class="col-6 col-lg-3">
<div class="stat-card text-center">
    <div class="fs-3 fw-bold text-success">
        <?= $correctas ?>
    </div>
    <div class="small text-secondary">
        Correctas
    </div>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="stat-card text-center">
    <div class="fs-3 fw-bold text-danger">
        <?= $incorrectas ?>
    </div>
    <div class="small text-secondary">
        Incorrectas / sin responder
    </div>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="stat-card text-center">
    <div class="fs-3 fw-bold text-primary">
        <?= $totalRespondidas ?>
    </div>
    <div class="small text-secondary">
        Preguntas procesadas
    </div>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="stat-card text-center">
    <div class="fs-3 fw-bold">
        <?= $minutosEmpleado ?>:<?= str_pad((string)$segundosEmpleado, 2, "0", STR_PAD_LEFT) ?>
    </div>
    <div class="small text-secondary">
        Tiempo empleado
    </div>
</div>
</div>

</div>

<?php if ($gamificacion): ?>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="small text-uppercase text-primary fw-bold">
    Recompensa
</div>

<h2 class="h5 fw-bold mt-1 mb-1">
    Tu progreso en Studia360
</h2>

<p class="text-secondary mb-0">
    <?= number_format((int)$gamificacion["puntos"]) ?> XP acumulados
    ·
    <?= e($gamificacion["nivel"]["nombre"]) ?>
</p>

</div>

<i class="bi bi-trophy-fill fs-2 text-primary"></i>

</div>

<?php if (
    isset($gamificacion["puntos_siguiente_nivel"]) &&
    $gamificacion["puntos_siguiente_nivel"] !== null
): ?>

<div class="progress mt-3" style="height:10px;">
    <div
        class="progress-bar"
        style="width:<?= e($gamificacion["progreso_nivel"]) ?>%"
    ></div>
</div>

<?php endif; ?>

</div>
</div>

<?php endif; ?>

<section class="mb-4">

<div class="mb-3">

<div class="small text-uppercase text-primary fw-bold">
    Revisión
</div>

<h2 class="h4 fw-bold mb-1">
    Revisa tus respuestas
</h2>

<p class="text-secondary mb-0">
    Aquí puedes identificar qué dominaste y qué conviene repasar.
</p>

</div>

<div class="d-grid gap-3">

<?php if (empty($respuestas)): ?>

<div class="alert alert-warning">
    No hay respuestas almacenadas para este intento.
</div>

<?php else: ?>

<?php foreach ($respuestas as $indice => $respuesta): ?>

<div class="answer-card <?= (int)$respuesta["es_correcta"] === 1 ? "answer-correct" : "answer-wrong" ?>">

<div class="d-flex justify-content-between gap-3 mb-3">

<div class="fw-bold">
    Pregunta <?= $indice + 1 ?>
</div>

<?php if ((int)$respuesta["es_correcta"] === 1): ?>

<span class="badge text-bg-success">
    <i class="bi bi-check-circle me-1"></i>
    Correcta
</span>

<?php else: ?>

<span class="badge text-bg-danger">
    <i class="bi bi-x-circle me-1"></i>
    Incorrecta
</span>

<?php endif; ?>

</div>

<div class="fw-semibold mb-3">
    <?= nl2br(e($respuesta["enunciado"])) ?>
</div>

<?php if (!empty($respuesta["imagen"])): ?>

<div class="mb-3 text-center">
    <img
        src="<?= e($respuesta["imagen"]) ?>"
        alt="Imagen de la pregunta"
        class="img-fluid rounded-4 border"
        style="max-height:360px;object-fit:contain;"
    >
</div>

<?php endif; ?>

<div class="p-3 rounded-3 border">

<div class="small text-secondary mb-1">
    Tu respuesta
</div>

<strong>
    <?= e($respuesta["letra_seleccionada"]) ?>.
    <?= e($respuesta["respuesta_seleccionada"]) ?>
</strong>

</div>

<?php if (!empty($respuesta["explicacion"])): ?>

<div class="explanation mt-3">

<div class="small fw-bold text-primary mb-1">
    <i class="bi bi-lightbulb-fill me-1"></i>
    Retroalimentación
</div>

<div class="small text-secondary">
    <?= nl2br(e($respuesta["explicacion"])) ?>
</div>

</div>

<?php endif; ?>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</section>

<div class="sticky-actions">

<div class="d-flex flex-wrap justify-content-between gap-2">

<a
    href="<?= e($urlTema) ?>"
    class="btn btn-outline-primary"
>
    <i class="bi bi-arrow-left me-1"></i>
    Volver al tema
</a>

<a
    href="<?= e($urlDashboard) ?>"
    class="btn btn-primary"
>
    <i class="bi bi-house me-1"></i>
    Ir al inicio
</a>

</div>

</div>

<?php endif; ?>

</main>

</body>
</html>

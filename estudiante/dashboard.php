<?php
/**
 * Studia360
 * Dashboard del estudiante
 * Fase 2 - Progreso y gamificación
 *
 * Archivo:
 * estudiante/dashboard.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../includes/seguridad.php";
require_once __DIR__ . "/../includes/gamificacion.php";

exigirEstudiante();

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$idUsuario = (int)($_SESSION["id_usuario"] ?? 0);

if ($idUsuario <= 0) {
    redireccionarDashboardUsuario();
}

/*
 * Grado del estudiante.
 * Las materias del dashboard se calculan únicamente con los temas
 * correspondientes a este grado.
 */
$gradoSesion = trim((string)($_SESSION["grado"] ?? ""));

if (!in_array($gradoSesion, ["9", "10", "11"], true)) {
    $gradoSesion = "9";
}

$nombres = trim((string)($_SESSION["nombres"] ?? "Estudiante"));
$apellidos = trim((string)($_SESSION["apellidos"] ?? ""));

$primerNombre = trim(explode(" ", $nombres)[0] ?? "Estudiante");

$errores = [];
$materias = [];
$actividadReciente = [];

try {

    /*
     * Sincronizamos el nivel por si el usuario obtuvo puntos
     * desde otra sección del sistema.
     */
    sincronizarNivel(
        $conexion,
        $idUsuario
    );

    $gamificacion = obtenerGamificacionUsuario(
        $conexion,
        $idUsuario
    );

    /*
     * Progreso general.
     */
    $progresoGeneral = obtenerProgresoGeneral(
        $conexion,
        $idUsuario
    );

    /*
     * Materias con progreso calculado directamente sobre sus temas.
     */
    $stmtMaterias = $conexion->prepare("
        SELECT
            m.id_materia,
            m.nombre,
            m.descripcion,

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

        FROM materias m

        LEFT JOIN temas t
            ON t.id_materia = m.id_materia
            AND t.grado = ?

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = ?

        GROUP BY
            m.id_materia,
            m.nombre,
            m.descripcion

        HAVING COUNT(t.id_tema) > 0

        ORDER BY m.nombre ASC
    ");

    $stmtMaterias->execute([
        $gradoSesion,
        $idUsuario
    ]);

    $materias = $stmtMaterias->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Últimos temas trabajados.
     */
    $stmtActividad = $conexion->prepare("
        SELECT
            p.id_tema,
            p.porcentaje_avance,
            p.completado,
            p.ultima_actividad,
            t.nombre AS tema,
            t.grado,
            m.nombre AS materia

        FROM progreso p

        INNER JOIN temas t
            ON t.id_tema = p.id_tema

        INNER JOIN materias m
            ON m.id_materia = t.id_materia

        WHERE p.id_usuario = ?

        ORDER BY
            p.ultima_actividad DESC,
            p.id_progreso DESC

        LIMIT 6
    ");

    $stmtActividad->execute([
        $idUsuario
    ]);

    $actividadReciente =
        $stmtActividad->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $errores[] =
        "No fue posible cargar todo el progreso. Algunas estadísticas pueden no estar disponibles.";

    $gamificacion = [
        "puntos" => 0,
        "nivel" => [
            "nombre" => "Iniciado",
            "puntos_minimos" => 0,
            "puntos_maximos" => 100
        ],
        "progreso_nivel" => 0,
        "puntos_siguiente_nivel" => 101
    ];

    $progresoGeneral = [
        "total_temas" => 0,
        "temas_completados" => 0,
        "porcentaje" => 0
    ];
}

$urlGrado = function(string $grado, ?int $idMateria = null): string {
    $url = "/estudiante/grado.php?grado=" . urlencode($grado);

    if ($idMateria !== null && $idMateria > 0) {
        $url .= "&id_materia=" . $idMateria;
    }

    return urlAplicacion($url);
};

$urlLogout = urlAplicacion("/cerrar_sesion.php");

$avatar = trim((string)($_SESSION["avatar"] ?? ""));
?>
<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<meta
    name="theme-color"
    content="#0d6efd"
>

<title>Inicio | Studia360</title>

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
    --primary-dark:#084298;
    --bg:#f4f7fb;
    --text:#172033;
    --muted:#667085;
    --border:#e4e9f1;
}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.navbar-studia{
    background:linear-gradient(110deg,#0b1f3a,#0d6efd);
    box-shadow:0 5px 22px rgba(13,110,253,.20);
}

.brand{
    font-weight:850;
    letter-spacing:-.02em;
}

.page{
    width:min(1250px,calc(100% - 32px));
    margin:auto;
    padding:30px 0 70px;
}

.welcome{
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,#0d6efd,#084298);
    color:#fff;
    border-radius:26px;
    padding:30px;
    box-shadow:0 18px 45px rgba(13,110,253,.20);
}

.welcome:after{
    content:"";
    position:absolute;
    width:270px;
    height:270px;
    border-radius:50%;
    right:-90px;
    top:-130px;
    background:rgba(255,255,255,.09);
}

.welcome-content{
    position:relative;
    z-index:1;
}

.welcome h1{
    font-weight:850;
    letter-spacing:-.04em;
    margin-bottom:7px;
}

.welcome p{
    opacity:.78;
    margin-bottom:0;
}

.avatar{
    width:70px;
    height:70px;
    border-radius:20px;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.25);
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    font-size:2rem;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.stat-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
    margin-top:18px;
}

.stat-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:19px;
    padding:19px;
    box-shadow:0 9px 25px rgba(20,35,60,.06);
}

.stat-icon{
    width:42px;
    height:42px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eaf2ff;
    color:var(--primary);
    font-size:1.2rem;
}

.stat-number{
    font-size:1.65rem;
    font-weight:850;
    margin-top:12px;
}

.stat-label{
    color:var(--muted);
    font-size:.84rem;
}

.section{
    margin-top:30px;
}

.section-heading{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:15px;
    margin-bottom:14px;
}

.section-heading h2{
    font-size:1.25rem;
    font-weight:850;
    margin:0;
}

.section-heading p{
    color:var(--muted);
    margin:3px 0 0;
    font-size:.9rem;
}

.progress-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:19px;
    padding:18px;
    box-shadow:0 9px 25px rgba(20,35,60,.06);
    height:100%;
}

.subject-icon{
    width:48px;
    height:48px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eaf2ff;
    color:var(--primary);
    font-size:1.35rem;
}

.subject-title{
    font-weight:800;
}

.subject-description{
    color:var(--muted);
    font-size:.84rem;
    min-height:38px;
}

.progress{
    height:9px;
    background:#edf1f7;
}

.progress-bar{
    background:var(--primary);
}

.activity-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:19px;
    overflow:hidden;
    box-shadow:0 9px 25px rgba(20,35,60,.06);
}

.activity-item{
    padding:16px 18px;
    border-bottom:1px solid var(--border);
}

.activity-item:last-child{
    border-bottom:0;
}

.activity-icon{
    width:40px;
    height:40px;
    border-radius:12px;
    background:#eef5ff;
    color:var(--primary);
    display:flex;
    align-items:center;
    justify-content:center;
    flex:none;
}

.xp-panel{
    background:linear-gradient(135deg,#172033,#263b5c);
    color:#fff;
    border-radius:21px;
    padding:21px;
    box-shadow:0 10px 28px rgba(20,35,60,.12);
}

.xp-number{
    font-size:2rem;
    font-weight:850;
}

.xp-panel .progress{
    background:rgba(255,255,255,.15);
}

.xp-panel .progress-bar{
    background:#fff;
}

.grade-buttons{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.grade-button{
    flex:1;
    min-width:120px;
    border-radius:14px;
    padding:13px;
    text-decoration:none;
    background:#fff;
    border:1px solid var(--border);
    color:var(--text);
    font-weight:750;
    transition:.2s;
}

.grade-button:hover{
    transform:translateY(-2px);
    border-color:#b9d2ff;
    color:var(--primary);
    box-shadow:0 8px 22px rgba(20,35,60,.07);
}

@media(max-width:850px){
    .stat-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:575px){
    .page{
        width:min(100% - 20px,1250px);
        padding-top:20px;
    }

    .welcome{
        padding:23px;
        border-radius:20px;
    }
}

</style>

</head>

<body>

<nav class="navbar navbar-dark navbar-studia">

<div class="container-fluid px-3 px-lg-4">

<a
    href="<?= e(urlAplicacion("/estudiante/dashboard.php")) ?>"
    class="navbar-brand brand"
>
    <i class="bi bi-mortarboard-fill me-2"></i>
    Studia360
</a>

<div class="d-flex align-items-center gap-2">

<span class="text-white small d-none d-md-inline">
    <i class="bi bi-person-circle me-1"></i>
    <?= e(trim($nombres . " " . $apellidos)) ?>
</span>

<a
    href="<?= e($urlLogout) ?>"
    class="btn btn-light btn-sm"
>
    Cerrar sesión
</a>

</div>

</div>

</nav>


<main class="page">

<?php if (!empty($errores)): ?>

    <?php foreach ($errores as $error): ?>

        <div class="alert alert-warning border-0 shadow-sm rounded-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= e($error) ?>
        </div>

    <?php endforeach; ?>

<?php endif; ?>


<section class="welcome">

<div class="welcome-content">

<div class="d-flex justify-content-between align-items-center gap-4 flex-wrap">

<div>

<div class="small text-uppercase fw-bold opacity-75">
    Tu espacio de aprendizaje
</div>

<h1>
    Hola, <?= e($primerNombre) ?> 👋
</h1>

<p>
    Sigue avanzando a tu ritmo y convierte cada tema en un nuevo logro.
</p>

</div>


<div class="avatar">

<?php if ($avatar !== ""): ?>

<img
    src="<?= e($avatar) ?>"
    alt="Avatar"
>

<?php else: ?>

<i class="bi bi-person-fill"></i>

<?php endif; ?>

</div>

</div>

</div>

</section>


<div class="stat-grid">

<div class="stat-card">

<div class="stat-icon">
    <i class="bi bi-stars"></i>
</div>

<div class="stat-number">
    <?= number_format((int)$gamificacion["puntos"]) ?>
    <span class="fs-6 text-secondary">XP</span>
</div>

<div class="stat-label">
    Experiencia acumulada
</div>

</div>


<div class="stat-card">

<div class="stat-icon">
    <i class="bi bi-trophy-fill"></i>
</div>

<div class="stat-number">
    <?= e($gamificacion["nivel"]["nombre"]) ?>
</div>

<div class="stat-label">
    Nivel actual
</div>

</div>


<div class="stat-card">

<div class="stat-icon">
    <i class="bi bi-check2-circle"></i>
</div>

<div class="stat-number">
    <?= (int)$progresoGeneral["temas_completados"] ?>
    <span class="fs-6 text-secondary">
        / <?= (int)$progresoGeneral["total_temas"] ?>
    </span>
</div>

<div class="stat-label">
    Temas completados
</div>

</div>

</div>


<section class="section">

<div class="section-heading">

<div>

<h2>
    Tu progreso general
</h2>

<p>
    Avance de todos los temas disponibles.
</p>

</div>

<strong class="text-primary">
    <?= number_format((float)$progresoGeneral["porcentaje"], 0) ?>%
</strong>

</div>


<div class="card border-0 bg-transparent">

<div class="progress" style="height:12px;">
    <div
        class="progress-bar"
        style="width:<?= e($progresoGeneral["porcentaje"]) ?>%"
    ></div>
</div>

</div>

</section>


<section class="section">

<div class="section-heading">

<div>

<h2>
    Mis materias
</h2>

<p>
    Consulta tu avance y continúa donde lo dejaste.
</p>

</div>

</div>


<div class="row g-3">

<?php if (empty($materias)): ?>

<div class="col-12">

<div class="progress-card text-center py-5">

<i class="bi bi-journal-x fs-1 text-secondary"></i>

<h3 class="h6 fw-bold mt-3">
    Todavía no hay materias disponibles
</h3>

<p class="text-secondary small mb-0">
    Cuando existan temas publicados aparecerán aquí.
</p>

</div>

</div>

<?php else: ?>

<?php foreach ($materias as $materia): ?>

<?php
    $porcentajeMateria = max(
        0,
        min(
            100,
            (float)$materia["porcentaje"]
        )
    );
?>

<div class="col-12 col-md-6">

<div class="progress-card">

<div class="d-flex gap-3 align-items-start">

<div class="subject-icon">
    <i class="bi bi-book-fill"></i>
</div>

<div class="flex-grow-1">

<div class="subject-title">
    <?= e($materia["nombre"]) ?>
</div>

<div class="subject-description mt-1">
    <?= e($materia["descripcion"] ?? "Continúa fortaleciendo tus conocimientos.") ?>
</div>

</div>

</div>


<div class="d-flex justify-content-between small mt-3 mb-2">

<span class="text-secondary">
    <?= (int)$materia["temas_completados"] ?>
    /
    <?= (int)$materia["total_temas"] ?>
    temas
</span>

<strong>
    <?= number_format($porcentajeMateria, 0) ?>%
</strong>

</div>


<div class="progress mb-3">

<div
    class="progress-bar"
    style="width:<?= e($porcentajeMateria) ?>%"
></div>

</div>


<a
    href="<?= e($urlGrado($gradoSesion, (int)$materia["id_materia"])) ?>"
    class="btn btn-outline-primary btn-sm"
>
    Ver contenidos
    <i class="bi bi-arrow-right ms-1"></i>
</a>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</section>


<section class="section">

<div class="row g-4">

<div class="col-lg-8">

<div class="section-heading">

<div>

<h2>
    Continúa aprendiendo
</h2>

<p>
    Tus temas trabajados recientemente.
</p>

</div>

</div>


<div class="activity-card">

<?php if (empty($actividadReciente)): ?>

<div class="text-center py-5 px-3">

<i class="bi bi-clock-history fs-1 text-secondary"></i>

<h3 class="h6 fw-bold mt-3">
    Aún no has comenzado un tema
</h3>

<p class="text-secondary small mb-0">
    Elige un grado y empieza a construir tu progreso.
</p>

</div>

<?php else: ?>

<?php foreach ($actividadReciente as $actividad): ?>

<div class="activity-item">

<div class="d-flex gap-3 align-items-center">

<div class="activity-icon">
    <i class="bi bi-journal-text"></i>
</div>

<div class="flex-grow-1">

<div class="fw-bold">
    <?= e($actividad["tema"]) ?>
</div>

<div class="small text-secondary">

<?= e($actividad["materia"]) ?>
·
<?= e($actividad["grado"]) ?>°

</div>

</div>

<div class="text-end">

<div class="fw-bold text-primary">
    <?= number_format((float)$actividad["porcentaje_avance"], 0) ?>%
</div>

<a
    href="<?= e(
        urlAplicacion(
            "/estudiante/tema.php?id=" .
            (int)$actividad["id_tema"]
        )
    ) ?>"
    class="btn btn-sm btn-outline-primary mt-1"
>
    Continuar
</a>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>


<div class="col-lg-4">

<div class="section-heading">

<div>

<h2>
    Tu nivel
</h2>

</div>

</div>


<div class="xp-panel">

<div class="small text-uppercase opacity-75 fw-bold">
    Nivel actual
</div>

<div class="fs-4 fw-bold mt-1">
    <?= e($gamificacion["nivel"]["nombre"]) ?>
</div>

<div class="xp-number mt-3">
    <?= number_format((int)$gamificacion["puntos"]) ?>
    <span class="fs-6 fw-normal opacity-75">
        XP
    </span>
</div>

<div class="progress mt-3">

<div
    class="progress-bar"
    style="width:<?= e($gamificacion["progreso_nivel"]) ?>%"
></div>

</div>

<div class="small opacity-75 mt-2">

<?php if ($gamificacion["puntos_siguiente_nivel"] !== null): ?>

Te faltan aproximadamente
<strong>
<?= max(
    0,
    (int)$gamificacion["puntos_siguiente_nivel"] -
    (int)$gamificacion["puntos"]
) ?>
</strong>
XP para el siguiente nivel.

<?php else: ?>

Has alcanzado el nivel máximo configurado.

<?php endif; ?>

</div>

</div>

</div>

</div>

</section>


<section class="section">

<div class="section-heading">

<div>

<h2>
    Explora por grado
</h2>

<p>
    Accede directamente a los contenidos de tu curso.
</p>

</div>

</div>


<div class="grade-buttons">

<a
    href="<?= e($urlGrado("9")) ?>"
    class="grade-button"
>
    <i class="bi bi-1-circle-fill text-primary me-2"></i>
    Noveno
    <i class="bi bi-arrow-right float-end"></i>
</a>


<a
    href="<?= e($urlGrado("10")) ?>"
    class="grade-button"
>
    <i class="bi bi-2-circle-fill text-success me-2"></i>
    Décimo
    <i class="bi bi-arrow-right float-end"></i>
</a>


<a
    href="<?= e($urlGrado("11")) ?>"
    class="grade-button"
>
    <i class="bi bi-3-circle-fill text-warning me-2"></i>
    Undécimo
    <i class="bi bi-arrow-right float-end"></i>
</a>

</div>

</section>

</main>

</body>
</html>

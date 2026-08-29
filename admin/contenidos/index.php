<?php
/**
 * Studia360
 * Centro de administración de contenidos
 *
 * Archivo:
 * admin/contenidos/index.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$nombreAdmin = trim($_SESSION["nombres"] ?? "");
if ($nombreAdmin === "") {
    $nombreAdmin = "Administrador";
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$urlDashboard = urlAplicacion("/admin/dashboard.php");
$urlMaterias = urlAplicacion("/admin/contenidos/materias.php");
$urlTemas = urlAplicacion("/admin/contenidos/temas.php");
$urlNuevoTema = urlAplicacion("/admin/contenidos/nuevo_tema.php");
$urlCerrarSesion = urlAplicacion("/cerrar_sesion.php");

$estadisticas = [
    "materias" => 0,
    "temas" => 0,
    "contenido" => 0,
    "recursos" => 0
];

$ultimosTemas = [];

try {
    $estadisticas["materias"] = (int)$conexion->query(
        "SELECT COUNT(*) FROM materias"
    )->fetchColumn();

    $estadisticas["temas"] = (int)$conexion->query(
        "SELECT COUNT(*) FROM temas"
    )->fetchColumn();

    $estadisticas["contenido"] = (int)$conexion->query(
        "SELECT COUNT(*)
         FROM temas
         WHERE contenido IS NOT NULL
         AND TRIM(contenido) <> ''"
    )->fetchColumn();

    $estadisticas["recursos"] = (int)$conexion->query(
        "SELECT COUNT(*) FROM recursos"
    )->fetchColumn();

    $stmt = $conexion->query(
        "SELECT
            t.id_tema,
            t.nombre AS tema,
            t.grado,
            m.nombre AS materia,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM contenido_temas ct
                    WHERE ct.id_tema = t.id_tema
                    AND ct.estado = 'Publicado'
                ) THEN 'Publicado'
                WHEN EXISTS (
                    SELECT 1
                    FROM contenido_temas ct
                    WHERE ct.id_tema = t.id_tema
                    AND ct.estado = 'Borrador'
                ) THEN 'Borrador'
                WHEN t.contenido IS NOT NULL
                     AND TRIM(t.contenido) <> ''
                    THEN 'Con contenido'
                ELSE 'Sin contenido'
            END AS estado_contenido
         FROM temas t
         INNER JOIN materias m ON m.id_materia = t.id_materia
         ORDER BY t.id_tema DESC
         LIMIT 6"
    );

    $ultimosTemas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errores[] = "No fue posible cargar las estadísticas del módulo.";
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Contenidos | Studia360</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>
        :root {
            --blue: #0d6efd;
            --blue-dark: #084298;
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #667085;
            --border: #e4e9f1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family:
                Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", sans-serif;
        }

        .navbar {
            box-shadow: 0 4px 18px rgba(20, 35, 60, .12);
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.15);
            margin-right: .55rem;
        }

        .page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }

        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 2rem;
            color: white;
            background:
                radial-gradient(circle at 85% 15%, rgba(255,255,255,.16), transparent 28%),
                linear-gradient(135deg, var(--blue), var(--blue-dark));
            box-shadow: 0 18px 40px rgba(13,110,253,.18);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            right: -65px;
            bottom: -95px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
        }

        .hero-kicker {
            text-transform: uppercase;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .08em;
            opacity: .82;
        }

        .hero h1 {
            font-weight: 800;
            letter-spacing: -.03em;
        }

        .hero p {
            max-width: 720px;
            margin-bottom: 0;
            opacity: .9;
        }

        .hero-actions {
            position: relative;
            z-index: 2;
        }

        .stat-card,
        .module-card,
        .recent-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(20,35,60,.06);
        }

        .stat-card {
            height: 100%;
            padding: 1.1rem;
            transition: .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(20,35,60,.09);
        }

        .stat-icon,
        .module-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon {
            background: #eaf2ff;
            color: var(--blue);
        }

        .stat-number {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            color: var(--muted);
            font-size: .85rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
        }

        .module-card {
            height: 100%;
            padding: 1.4rem;
            transition: .2s ease;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(20,35,60,.1);
        }

        .module-icon.blue { background: #eaf2ff; color: #0d6efd; }
        .module-icon.green { background: #eaf8ef; color: #198754; }
        .module-icon.orange { background: #fff5df; color: #d98b00; }
        .module-icon.purple { background: #f2edff; color: #6f42c1; }

        .module-card h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin: 1rem 0 .45rem;
        }

        .module-card p {
            color: var(--muted);
            font-size: .9rem;
            min-height: 48px;
        }

        .recent-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 0;
            border-bottom: 1px solid #edf0f5;
        }

        .recent-row:last-child { border-bottom: 0; }

        .recent-title {
            font-weight: 700;
            font-size: .92rem;
        }

        .recent-meta {
            color: var(--muted);
            font-size: .8rem;
        }

        .badge-status {
            font-size: .72rem;
            padding: .42rem .6rem;
            border-radius: 999px;
        }

        .empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--muted);
        }

        @media (max-width: 767px) {
            .page { padding-top: 1rem; }
            .hero { padding: 1.4rem; border-radius: 18px; }
            .hero-actions .btn { width: 100%; }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand fw-bold" href="<?= e($urlDashboard) ?>">
            <span class="brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </span>
            Studia360
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="text-white small d-none d-md-inline">
                <i class="bi bi-shield-check me-1"></i>
                <?= e($nombreAdmin) ?>
            </span>

            <a
                href="<?= e($urlCerrarSesion) ?>"
                class="btn btn-outline-light btn-sm"
            >
                <i class="bi bi-box-arrow-right me-1"></i>
                Cerrar sesión
            </a>
        </div>
    </div>
</nav>

<main class="page">

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-4">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <section class="hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="hero-kicker mb-2">Administración académica</div>
                <h1 class="display-6 mb-2">Centro de contenidos</h1>
                <p>
                    Administra materias, temas y materiales de Studia360
                    desde un único lugar, sin mezclar la gestión académica
                    con la edición de cada lección.
                </p>
            </div>

            <div class="col-lg-4 hero-actions text-lg-end">
                <a
                    href="<?= e($urlNuevoTema) ?>"
                    class="btn btn-light btn-lg fw-semibold"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Nuevo tema
                </a>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?= $estadisticas["materias"] ?>
                        </div>
                        <div class="stat-label">Materias</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?= $estadisticas["temas"] ?>
                        </div>
                        <div class="stat-label">Temas</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-richtext-fill"></i>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?= $estadisticas["contenido"] ?>
                        </div>
                        <div class="stat-label">Con contenido</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon">
                        <i class="bi bi-collection-play-fill"></i>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?= $estadisticas["recursos"] ?>
                        </div>
                        <div class="stat-label">Recursos</div>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <section class="mb-4">
        <div class="mb-3">
            <div class="section-title">¿Qué quieres administrar?</div>
            <div class="text-muted small">
                Accede directamente al módulo que necesitas.
            </div>
        </div>

        <div class="row g-3">

            <div class="col-md-6 col-xl-3">
                <article class="module-card">
                    <div class="module-icon blue">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <h3>Materias</h3>
                    <p>
                        Crea, edita y organiza las materias que forman
                        la estructura académica.
                    </p>
                    <a href="<?= e($urlMaterias) ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-arrow-right me-1"></i>
                        Gestionar materias
                    </a>
                </article>
            </div>

            <div class="col-md-6 col-xl-3">
                <article class="module-card">
                    <div class="module-icon green">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <h3>Temas</h3>
                    <p>
                        Filtra por grado y administra únicamente
                        los temas que necesites modificar.
                    </p>
                    <a href="<?= e($urlTemas) ?>" class="btn btn-outline-success w-100">
                        <i class="bi bi-arrow-right me-1"></i>
                        Gestionar temas
                    </a>
                </article>
            </div>

            <div class="col-md-6 col-xl-3">
                <article class="module-card">
                    <div class="module-icon orange">
                        <i class="bi bi-plus-square"></i>
                    </div>
                    <h3>Crear contenido</h3>
                    <p>
                        Crea un tema nuevo indicando su materia,
                        grado, nombre y descripción.
                    </p>
                    <a href="<?= e($urlNuevoTema) ?>" class="btn btn-outline-warning w-100">
                        <i class="bi bi-plus-lg me-1"></i>
                        Crear tema
                    </a>
                </article>
            </div>

            <div class="col-md-6 col-xl-3">
                <article class="module-card">
                    <div class="module-icon purple">
                        <i class="bi bi-collection-play-fill"></i>
                    </div>
                    <h3>Recursos</h3>
                    <p>
                        Los recursos se administran desde cada tema:
                        videos, PDF, enlaces y demás materiales.
                    </p>
                    <a href="<?= e($urlTemas) ?>" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-right me-1"></i>
                        Ir a temas
                    </a>
                </article>
            </div>

        </div>
    </section>

    <section class="recent-card p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
            <div>
                <div class="section-title">Temas recientes</div>
                <div class="text-muted small">
                    Acceso rápido a los últimos temas registrados.
                </div>
            </div>

            <a href="<?= e($urlTemas) ?>" class="btn btn-sm btn-outline-primary">
                Ver todos los temas
            </a>
        </div>

        <?php if (empty($ultimosTemas)): ?>

            <div class="empty">
                <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                Todavía no hay temas registrados.
            </div>

        <?php else: ?>

            <?php foreach ($ultimosTemas as $tema): ?>
                <?php
                    $estado = (string)$tema["estado_contenido"];
                    $claseEstado = match ($estado) {
                        "Publicado" => "text-bg-success",
                        "Borrador" => "text-bg-warning",
                        "Con contenido" => "text-bg-info",
                        default => "text-bg-secondary"
                    };
                ?>

                <div class="recent-row">
                    <div>
                        <div class="recent-title">
                            <?= e($tema["tema"]) ?>
                        </div>
                        <div class="recent-meta">
                            <?= e($tema["materia"]) ?>
                            ·
                            <?= e($tema["grado"]) ?>°
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?= e($claseEstado) ?> badge-status">
                            <?= e($estado) ?>
                        </span>

                        <a
                            href="<?= e(urlAplicacion("/admin/contenidos/editar_tema.php?id=" . (int)$tema["id_tema"])) ?>"
                            class="btn btn-sm btn-outline-primary"
                            title="Editar tema"
                        >
                            <i class="bi bi-pencil"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </section>

</main>

</body>
</html>

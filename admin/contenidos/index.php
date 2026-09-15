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
<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">

<style id="studia360-contenidos-polished">
:root{
  --c-accent:#2563eb;
  --c-accent2:#4f46e5;
  --c-soft:#eff6ff;
  --c-bg:#f6f8fc;
  --c-card:#fff;
  --c-text:#1f2937;
  --c-muted:#748196;
  --c-line:#e5eaf1;
  --c-input:#fff;
  --c-track:#e9eef5;
}
body.s360-content-theme{
  --c-accent:#2563eb;--c-accent2:#4f46e5;--c-soft:#eff6ff;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 7%,transparent),transparent 25rem),var(--c-bg)!important;
  color:var(--c-text)!important;
  transition:background .25s ease,color .25s ease;
}
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}

body.s360-content-theme .adm-theme-toggle{
  position:fixed!important;right:20px!important;bottom:20px!important;z-index:2000!important;
  width:50px!important;height:50px!important;padding:0!important;margin:0!important;
  display:grid!important;place-items:center!important;
  border:0!important;border-radius:16px!important;
  color:#fff!important;background:linear-gradient(145deg,var(--c-accent),var(--c-accent2))!important;
  box-shadow:0 12px 30px color-mix(in srgb,var(--c-accent) 28%,transparent)!important;
  cursor:pointer!important;outline:none!important;transition:transform .2s ease,box-shadow .2s ease!important;
}
body.s360-content-theme .adm-theme-toggle:hover{transform:translateY(-3px)!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.15rem!important;line-height:1!important}

body.s360-content-theme .adm-theme-panel{
  position:fixed!important;right:20px!important;bottom:82px!important;z-index:1999!important;
  width:290px!important;max-width:calc(100vw - 28px)!important;
  padding:16px!important;margin:0!important;
  display:block!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;border-radius:18px!important;
  box-shadow:0 20px 55px rgba(20,35,60,.16)!important;
  opacity:0!important;visibility:hidden!important;pointer-events:none!important;
  transform:translateY(8px) scale(.98)!important;
  transition:opacity .18s ease,transform .18s ease,visibility .18s ease!important;
}
body.s360-content-theme .adm-theme-panel.open{
  opacity:1!important;visibility:visible!important;pointer-events:auto!important;
  transform:none!important;
}
body.s360-content-theme .adm-theme-title{
  margin:0 0 4px!important;font-size:.84rem!important;line-height:1.25!important;
  font-weight:850!important;color:var(--c-text)!important;
}
body.s360-content-theme .adm-theme-sub{
  margin:0 0 13px!important;font-size:.66rem!important;line-height:1.45!important;
  color:var(--c-muted)!important;
}
body.s360-content-theme .adm-theme-grid{
  display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;
}
body.s360-content-theme .adm-theme-option{
  appearance:none!important;width:100%!important;min-height:74px!important;
  padding:9px!important;margin:0!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;box-shadow:none!important;
  font:inherit!important;transition:.18s ease!important;
}
body.s360-content-theme .adm-theme-option:hover,
body.s360-content-theme .adm-theme-option.active{
  border-color:color-mix(in srgb,var(--c-accent) 42%,var(--c-line))!important;
  background:var(--c-soft)!important;transform:translateY(-1px)!important;
}
body.s360-content-theme .adm-swatch{
  display:block!important;width:100%!important;height:23px!important;margin:0 0 7px!important;
  border-radius:8px!important;
}
body.s360-content-theme .adm-theme-option strong{
  display:block!important;font-size:.67rem!important;line-height:1.15!important;
}
body.s360-content-theme .adm-theme-option small{
  display:block!important;margin-top:3px!important;font-size:.57rem!important;
  line-height:1.15!important;color:var(--c-muted)!important;
}
body.s360-content-theme .adm-mode-btn{
  appearance:none!important;width:100%!important;min-height:38px!important;
  margin:9px 0 0!important;padding:8px 10px!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;font:700 .68rem/1.2 system-ui,sans-serif!important;
}
body.s360-content-theme .adm-mode-btn:hover{
  color:var(--c-accent)!important;background:var(--c-soft)!important;
}

/* Capa visual de las pantallas existentes; no cambia su estructura ni sus datos. */
body.s360-content-theme .card,
body.s360-content-theme .card-soft,
body.s360-content-theme .editor-card,
body.s360-content-theme .info-card,
body.s360-content-theme .content-card,
body.s360-content-theme .preview-header,
body.s360-content-theme .resource,
body.s360-content-theme .resource-card,
body.s360-content-theme .recurso-card,
body.s360-content-theme .sidebar-card,
body.s360-content-theme .panel,
body.s360-content-theme .stat,
body.s360-content-theme .theme-info,
body.s360-content-theme .delete-card{
  background:var(--c-card)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,
body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{
  color:var(--c-text);
}
body.s360-content-theme .text-muted,body.s360-content-theme .muted,
body.s360-content-theme .url-help{color:var(--c-muted)!important}
body.s360-content-theme .text-primary{color:var(--c-accent)!important}
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .btn-primary{
  background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .btn-outline-primary{
  color:var(--c-accent)!important;border-color:color-mix(in srgb,var(--c-accent) 30%,var(--c-line))!important;
  background:var(--c-card)!important;
}
body.s360-content-theme .btn-outline-primary:hover{
  color:#fff!important;background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .badge-type,
body.s360-content-theme .icon,
body.s360-content-theme .resource-icon,
body.s360-content-theme .icono-recurso{
  background:var(--c-soft)!important;color:var(--c-accent)!important;
}
body.s360-content-theme .hero,
body.s360-content-theme .preview-hero{
  background:linear-gradient(125deg,var(--c-accent),var(--c-accent2))!important;
}
body.s360-content-theme .table{
  --bs-table-bg:var(--c-card);--bs-table-color:var(--c-text);--bs-table-border-color:var(--c-line);
}
body.s360-content-theme .table thead th{background:var(--c-soft)!important;color:var(--c-text)!important}
body.s360-content-theme .dropdown-menu{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme .dropdown-item{color:var(--c-text)!important}
body.s360-content-theme .dropdown-item:hover{background:var(--c-soft)!important;color:var(--c-accent)!important}

/* Acciones compactas: solo icono, sin deformar los botones. */
body.s360-content-theme .topic-actions .btn,
body.s360-content-theme .recent-row .btn,
body.s360-content-theme .resource .btn.btn-sm,
body.s360-content-theme .resource-card .btn.btn-sm,
body.s360-content-theme .recurso-card .btn.btn-sm{
  width:36px!important;height:36px!important;min-width:36px!important;min-height:36px!important;
  padding:0!important;display:inline-grid!important;place-items:center!important;
  border-radius:10px!important;font-size:0!important;line-height:1!important;
}
body.s360-content-theme .topic-actions .btn i,
body.s360-content-theme .recent-row .btn i,
body.s360-content-theme .resource .btn.btn-sm i,
body.s360-content-theme .resource-card .btn.btn-sm i,
body.s360-content-theme .recurso-card .btn.btn-sm i{
  margin:0!important;font-size:.9rem!important;line-height:1!important;
}

/* Oscuro */
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;--c-card:#172236;--c-text:#edf2f8;--c-muted:#9ba8ba;
  --c-line:#2b374b;--c-input:#111827;--c-track:#263247;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 13%,transparent),transparent 25rem),var(--c-bg)!important;
}
body.s360-content-theme.s360-content-dark .navbar{
  background:rgba(13,20,36,.94)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .navbar-brand,
body.s360-content-theme.s360-content-dark .text-dark,
body.s360-content-theme.s360-content-dark h1,
body.s360-content-theme.s360-content-dark h2,
body.s360-content-theme.s360-content-dark h3,
body.s360-content-theme.s360-content-dark h4,
body.s360-content-theme.s360-content-dark h5,
body.s360-content-theme.s360-content-dark h6{color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .modal-content{
  background:var(--c-card)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .note-toolbar{background:#202c41!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-editable{background:var(--c-input)!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .note-statusbar{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-btn{
  background:#27344a!important;color:#dce4ee!important;border-color:#354158!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-panel{box-shadow:0 22px 60px rgba(0,0,0,.42)!important}

@media(max-width:767px){
  body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}
  body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}
}
</style>

</head>

<body class="s360-admin">

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

<!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button></div>

<script id="studia360-contenidos-theme-controller">
(function(){
  'use strict';
  var body=document.body;
  var key='studia360_theme';
  var toggle=document.getElementById('admThemeToggle');
  var panel=document.getElementById('admThemePanel');
  var modeBtn=document.getElementById('admModeBtn');
  var options=document.querySelectorAll('.adm-theme-option');

  var themes={
    blue:{cls:'',label:'Azul'},
    purple:{cls:'s360-accent-purple',label:'Violeta'},
    orange:{cls:'s360-accent-orange',label:'Naranja'},
    green:{cls:'s360-accent-green',label:'Verde'}
  };

  var saved={theme:'blue',mode:'light'};
  try{
    var raw=localStorage.getItem(key);
    if(raw){
      var parsed=JSON.parse(raw);
      if(parsed && typeof parsed==='object') saved=Object.assign(saved,parsed);
    }
  }catch(e){}
  if(!themes[saved.theme]) saved.theme='blue';
  if(saved.mode!=='dark' && saved.mode!=='light') saved.mode='light';

  function persist(){
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
  }

  function apply(){
    body.classList.add('s360-content-theme');
    Object.keys(themes).forEach(function(name){
      if(themes[name].cls) body.classList.remove(themes[name].cls);
    });
    if(themes[saved.theme].cls) body.classList.add(themes[saved.theme].cls);
    body.classList.toggle('s360-content-dark',saved.mode==='dark');

    options.forEach(function(option){
      option.classList.toggle('active',option.getAttribute('data-theme')===saved.theme);
    });

    if(modeBtn){
      modeBtn.innerHTML=saved.mode==='dark'
        ? '<i class="bi bi-moon-stars me-2"></i>Modo oscuro'
        : '<i class="bi bi-sun me-2"></i>Modo claro';
    }
  }

  window.admSetTheme=function(theme){
    if(!themes[theme]) return;
    saved.theme=theme;
    persist();
    apply();
  };

  window.admToggleMode=function(){
    saved.mode=saved.mode==='dark'?'light':'dark';
    persist();
    apply();
  };

  if(toggle && panel){
    toggle.addEventListener('click',function(event){
      event.preventDefault();
      event.stopPropagation();
      panel.classList.toggle('open');
    });
    panel.addEventListener('click',function(event){
      event.stopPropagation();
    });
    document.addEventListener('click',function(){
      panel.classList.remove('open');
    });
  }

  apply();
})();
</script>

</body>
</html>

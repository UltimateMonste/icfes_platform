<?php
/**
 * Studia360
 * Creación de temas
 *
 * Archivo:
 * admin/contenidos/nuevo_tema.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$nombre = "";
$descripcion = "";
$grado = "";
$idMateria = "";

$nombreAdmin = trim($_SESSION["nombres"] ?? "");
if ($nombreAdmin === "") {
    $nombreAdmin = "Administrador";
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$urlDashboard = urlAplicacion("/admin/dashboard.php");
$urlIndex = urlAplicacion("/admin/contenidos/index.php");
$urlTemas = urlAplicacion("/admin/contenidos/temas.php");
$urlCerrarSesion = urlAplicacion("/cerrar_sesion.php");

$materias = [];

try {
    $stmt = $conexion->query(
        "SELECT id_materia, nombre, descripcion
         FROM materias
         ORDER BY nombre ASC"
    );

    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errores[] = "No fue posible cargar las materias.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $grado = trim($_POST["grado"] ?? "");
    $idMateria = trim($_POST["id_materia"] ?? "");

    if ($nombre === "") {
        $errores[] = "El nombre del tema es obligatorio.";
    } elseif (mb_strlen($nombre) > 150) {
        $errores[] = "El nombre del tema no puede superar los 150 caracteres.";
    }

    if (mb_strlen($descripcion) > 5000) {
        $errores[] = "La descripción es demasiado larga.";
    }

    if (!in_array($grado, ["9", "10", "11"], true)) {
        $errores[] = "Debes seleccionar un grado válido.";
    }

    $idMateriaInt = filter_var(
        $idMateria,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if (!$idMateriaInt) {
        $errores[] = "Debes seleccionar una materia.";
    }

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare(
                "SELECT id_materia
                 FROM materias
                 WHERE id_materia = ?
                 LIMIT 1"
            );
            $stmt->execute([$idMateriaInt]);

            if (!$stmt->fetchColumn()) {
                $errores[] = "La materia seleccionada no existe.";
            }

            if (empty($errores)) {

                $stmt = $conexion->prepare(
                    "SELECT COUNT(*)
                     FROM temas
                     WHERE id_materia = ?
                     AND grado = ?
                     AND LOWER(nombre) = LOWER(?)"
                );

                $stmt->execute([
                    $idMateriaInt,
                    $grado,
                    $nombre
                ]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $errores[] =
                        "Ya existe un tema con ese nombre en esa materia y grado.";
                }
            }

            if (empty($errores)) {

                $stmt = $conexion->prepare(
                    "INSERT INTO temas
                        (id_materia, nombre, descripcion, grado)
                     VALUES
                        (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $idMateriaInt,
                    $nombre,
                    $descripcion !== "" ? $descripcion : null,
                    $grado
                ]);

                $idNuevoTema = (int)$conexion->lastInsertId();

                header(
                    "Location: " .
                    urlAplicacion(
                        "/admin/contenidos/editar_tema.php?id=" .
                        $idNuevoTema .
                        "&creado=1"
                    )
                );
                exit;
            }

        } catch (PDOException $e) {
            $errores[] =
                "No fue posible crear el tema. Verifica la conexión y la estructura de la tabla temas.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Nuevo tema | Studia360</title>

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
            --border: #e3e8f0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family:
                Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", sans-serif;
        }

        .navbar {
            box-shadow: 0 4px 18px rgba(20,35,60,.12);
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
            max-width: 1100px;
            margin: auto;
            padding: 2rem 1rem 4rem;
        }

        .hero {
            border-radius: 22px;
            padding: 1.8rem 2rem;
            color: #fff;
            background:
                linear-gradient(135deg, var(--blue), var(--blue-dark));
            box-shadow: 0 16px 35px rgba(13,110,253,.16);
        }

        .hero-kicker {
            text-transform: uppercase;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            opacity: .82;
        }

        .form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(20,35,60,.07);
        }

        .form-control,
        .form-select {
            border-color: #d7dee9;
            border-radius: 12px;
            padding: .75rem .9rem;
            color: #172033 !important;
            background-color: #fff !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 .2rem rgba(13,110,253,.12);
        }

        /* Evita el select negro que aparecía en algunas configuraciones del navegador. */
        .form-select option {
            color: #172033 !important;
            background: #fff !important;
        }

        .form-label {
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .help {
            color: var(--muted);
            font-size: .82rem;
        }

        .grade-option {
            cursor: pointer;
        }

        .grade-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .grade-box {
            border: 2px solid #e1e6ef;
            border-radius: 16px;
            padding: 1rem;
            height: 100%;
            transition: .18s ease;
            background: #fff;
        }

        .grade-box:hover {
            border-color: #9bbcff;
            transform: translateY(-2px);
        }

        .grade-option input:checked + .grade-box {
            border-color: var(--blue);
            background: #eef5ff;
            box-shadow: 0 8px 20px rgba(13,110,253,.10);
        }

        .grade-number {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: var(--blue);
            font-weight: 800;
        }

        @media (max-width: 767px) {
            .page { padding-top: 1rem; }
            .hero { padding: 1.4rem; }
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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="<?= e($urlIndex) ?>" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>
                Centro de contenidos
            </a>
        </div>

        <a href="<?= e($urlTemas) ?>" class="btn btn-outline-primary">
            <i class="bi bi-journal-text me-1"></i>
            Ver temas
        </a>
    </div>

    <section class="hero mb-4">
        <div class="hero-kicker mb-2">Administración académica</div>
        <h1 class="fw-bold mb-2">Crear nuevo tema</h1>
        <p class="mb-0 opacity-75">
            Primero define la ubicación académica del tema.
            Después podrás construir su contenido, agregar recursos
            y publicarlo desde el editor.
        </p>
    </section>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($materias)): ?>

        <div class="alert alert-warning rounded-4 shadow-sm">
            <div class="d-flex gap-3 align-items-start">
                <i class="bi bi-info-circle-fill fs-4"></i>
                <div>
                    <strong>No hay materias disponibles.</strong>
                    <div class="small mt-1">
                        Debes crear al menos una materia antes de crear un tema.
                    </div>
                    <a
                        href="<?= e(urlAplicacion("/admin/contenidos/materias.php")) ?>"
                        class="btn btn-sm btn-warning mt-3"
                    >
                        <i class="bi bi-book me-1"></i>
                        Gestionar materias
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>

        <form method="POST" class="form-card p-4 p-lg-5">

            <div class="mb-4">
                <h2 class="h5 fw-bold mb-1">Información del tema</h2>
                <p class="help mb-0">
                    Estos datos aparecerán en los listados y en la vista del estudiante.
                </p>
            </div>

            <div class="row g-4">

                <div class="col-12">
                    <label for="nombre" class="form-label">
                        Nombre del tema
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        class="form-control form-control-lg"
                        maxlength="150"
                        value="<?= e($nombre) ?>"
                        placeholder="Ej. Biología Básica"
                        required
                    >

                    <div class="help mt-2">
                        Máximo 150 caracteres.
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="id_materia" class="form-label">
                        Materia
                    </label>

                    <select
                        id="id_materia"
                        name="id_materia"
                        class="form-select form-select-lg"
                        required
                    >
                        <option value="" disabled <?= $idMateria === "" ? "selected" : "" ?>>
                            Selecciona una materia
                        </option>

                        <?php foreach ($materias as $materia): ?>
                            <option
                                value="<?= (int)$materia["id_materia"] ?>"
                                <?= (string)$idMateria === (string)$materia["id_materia"] ? "selected" : "" ?>
                            >
                                <?= e($materia["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="help mt-2">
                        El tema quedará asociado a esta materia.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label d-block">
                        Grado
                    </label>

                    <div class="row g-3">

                        <?php
                        $grados = [
                            "9" => [
                                "nombre" => "Noveno",
                                "descripcion" => "Fundamentos y bases.",
                                "icono" => "bi-1-circle-fill"
                            ],
                            "10" => [
                                "nombre" => "Décimo",
                                "descripcion" => "Profundización.",
                                "icono" => "bi-2-circle-fill"
                            ],
                            "11" => [
                                "nombre" => "Undécimo",
                                "descripcion" => "Preparación avanzada.",
                                "icono" => "bi-3-circle-fill"
                            ]
                        ];
                        ?>

                        <?php foreach ($grados as $valor => $datos): ?>
                            <div class="col-md-4">
                                <label class="grade-option d-block position-relative">
                                    <input
                                        type="radio"
                                        name="grado"
                                        value="<?= e($valor) ?>"
                                        <?= $grado === $valor ? "checked" : "" ?>
                                        required
                                    >

                                    <div class="grade-box">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="grade-number">
                                                <i class="bi <?= e($datos["icono"]) ?>"></i>
                                            </div>

                                            <div>
                                                <div class="fw-bold">
                                                    <?= e($datos["nombre"]) ?>
                                                    <span class="text-muted">
                                                        (<?= e($valor) ?>°)
                                                    </span>
                                                </div>

                                                <div class="help">
                                                    <?= e($datos["descripcion"]) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="col-12">
                    <label for="descripcion" class="form-label">
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        class="form-control"
                        rows="5"
                        maxlength="5000"
                        placeholder="Describe brevemente qué aprenderá o trabajará el estudiante en este tema..."
                    ><?= e($descripcion) ?></textarea>

                    <div class="help mt-2">
                        Esta descripción es independiente del contenido completo que posteriormente se editará con Summernote.
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">

                <a
                    href="<?= e($urlTemas) ?>"
                    class="btn btn-outline-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="btn btn-primary px-4"
                >
                    <i class="bi bi-check-lg me-1"></i>
                    Crear tema
                </button>

            </div>

        </form>

    <?php endif; ?>

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

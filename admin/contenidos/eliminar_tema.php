<?php
/**
 * Studia360
 * Eliminación de temas
 *
 * Archivo:
 * admin/contenidos/eliminar_tema.php
 *
 * IMPORTANTE:
 * La eliminación se realiza mediante POST para evitar eliminaciones
 * accidentales mediante enlaces GET.
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$urlDashboard = urlAplicacion("/admin/dashboard.php");
$urlTemas = urlAplicacion("/admin/contenidos/temas.php");
$urlMaterias = urlAplicacion("/admin/contenidos/materias.php");

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$idTema) {
    header("Location: " . $urlTemas);
    exit;
}

$tema = null;

try {
    $stmt = $conexion->prepare(
        "SELECT
            t.id_tema,
            t.id_materia,
            t.nombre,
            t.descripcion,
            t.grado,
            m.nombre AS materia
         FROM temas t
         INNER JOIN materias m
            ON m.id_materia = t.id_materia
         WHERE t.id_tema = ?
         LIMIT 1"
    );

    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    header("Location: " . $urlTemas);
    exit;
}

if (!$tema) {
    header("Location: " . $urlTemas);
    exit;
}

/*
 * Eliminación.
 *
 * Las relaciones de contenido, recursos y progreso que tengan
 * FOREIGN KEY con ON DELETE CASCADE serán eliminadas por MySQL.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $confirmacion = $_POST["confirmar"] ?? "";

    if ($confirmacion !== "ELIMINAR") {
        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/eliminar_tema.php?id=" .
                $idTema .
                "&error=confirmacion"
            )
        );
        exit;
    }

    try {

        $conexion->beginTransaction();

        /*
         * Primero eliminamos explícitamente las relaciones que pueden
         * no tener ON DELETE CASCADE en instalaciones anteriores.
         *
         * Si alguna tabla no existe en una instalación antigua, la
         * eliminación principal se intenta igualmente.
         */

        $tablasRelacionadas = [
            "contenido_temas",
            "recursos",
            "progreso"
        ];

        foreach ($tablasRelacionadas as $tabla) {

            try {
                $stmt = $conexion->prepare(
                    "DELETE FROM `$tabla` WHERE id_tema = ?"
                );

                $stmt->execute([$idTema]);

            } catch (PDOException $e) {
                /*
                 * Si la tabla no existe o la instalación utiliza
                 * únicamente ON DELETE CASCADE, continuamos.
                 */
            }
        }

        $stmt = $conexion->prepare(
            "DELETE FROM temas
             WHERE id_tema = ?
             LIMIT 1"
        );

        $stmt->execute([$idTema]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                "No se encontró el tema para eliminar."
            );
        }

        $conexion->commit();

        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/temas.php?eliminado=1"
            )
        );
        exit;

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/eliminar_tema.php?id=" .
                $idTema .
                "&error=eliminacion"
            )
        );
        exit;
    }
}

$error = $_GET["error"] ?? "";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Eliminar tema | Studia360</title>

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
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #667085;
            --border: #e4e9f1;
            --danger: #dc3545;
            --danger-dark: #a71d2a;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
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
            min-height: calc(100vh - 62px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem 4rem;
        }

        .delete-card {
            width: 100%;
            max-width: 680px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(20,35,60,.10);
            overflow: hidden;
        }

        .delete-header {
            padding: 2rem;
            text-align: center;
            background:
                linear-gradient(
                    135deg,
                    #fff4f4,
                    #fff
                );
            border-bottom: 1px solid #f0d8db;
        }

        .danger-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1rem;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fde8ea;
            color: var(--danger);
            font-size: 2rem;
        }

        .delete-header h1 {
            font-weight: 800;
            letter-spacing: -.025em;
            margin-bottom: .45rem;
        }

        .delete-header p {
            color: var(--muted);
            margin-bottom: 0;
        }

        .delete-body {
            padding: 2rem;
        }

        .theme-info {
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 1rem 1.1rem;
            background: #fafbfd;
            margin-bottom: 1.25rem;
        }

        .theme-name {
            font-size: 1.15rem;
            font-weight: 800;
        }

        .theme-meta {
            color: var(--muted);
            font-size: .86rem;
            margin-top: .25rem;
        }

        .warning {
            border: 1px solid #f2c7cc;
            background: #fff5f6;
            border-radius: 15px;
            padding: 1rem;
            color: #7a1f29;
        }

        .warning ul {
            margin-bottom: 0;
            padding-left: 1.15rem;
        }

        .confirm-label {
            font-weight: 700;
        }

        .form-control {
            border-radius: 12px;
            padding: .75rem .9rem;
        }

        .form-control:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 .2rem rgba(220,53,69,.12);
        }

        .btn {
            border-radius: 11px;
        }

        @media (max-width: 575px) {
            .delete-header,
            .delete-body {
                padding: 1.4rem;
            }

            .delete-card {
                border-radius: 18px;
            }
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

        <a
            class="navbar-brand fw-bold"
            href="<?= e($urlDashboard) ?>"
        >
            <span class="brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </span>

            Studia360
        </a>

        <a
            href="<?= e($urlTemas) ?>"
            class="btn btn-outline-light btn-sm"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Volver a temas
        </a>

    </div>
</nav>

<main class="page">

    <section class="delete-card">

        <header class="delete-header">

            <div class="danger-icon">
                <i class="bi bi-trash3-fill"></i>
            </div>

            <h1>Eliminar tema</h1>

            <p>
                Esta acción es permanente y no debe realizarse
                si todavía necesitas conservar este contenido.
            </p>

        </header>

        <div class="delete-body">

            <?php if ($error === "confirmacion"): ?>
                <div class="alert alert-warning rounded-4 border-0">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                    Debes escribir exactamente
                    <strong>ELIMINAR</strong>
                    para confirmar.
                </div>
            <?php elseif ($error === "eliminacion"): ?>
                <div class="alert alert-danger rounded-4 border-0">
                    <i class="bi bi-x-circle-fill me-1"></i>
                    No fue posible eliminar el tema.
                    Verifica las relaciones de la base de datos e inténtalo nuevamente.
                </div>
            <?php endif; ?>

            <div class="theme-info">

                <div class="theme-name">
                    <?= e($tema["nombre"]) ?>
                </div>

                <div class="theme-meta">
                    <i class="bi bi-book me-1"></i>
                    <?= e($tema["materia"]) ?>

                    <span class="mx-1">·</span>

                    <i class="bi bi-mortarboard me-1"></i>
                    <?= e($tema["grado"]) ?>°
                </div>

                <?php if (!empty($tema["descripcion"])): ?>
                    <div class="small text-muted mt-2">
                        <?= e($tema["descripcion"]) ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="warning mb-4">

                <div class="fw-bold mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Antes de continuar
                </div>

                <ul>
                    <li>
                        El tema dejará de estar disponible para los estudiantes.
                    </li>

                    <li>
                        Su contenido y los recursos asociados pueden eliminarse.
                    </li>

                    <li>
                        Las relaciones asociadas al tema también pueden verse afectadas.
                    </li>

                    <li>
                        <strong>Esta operación no tiene botón de deshacer.</strong>
                    </li>
                </ul>

            </div>

            <form method="POST">

                <div class="mb-3">

                    <label
                        for="confirmar"
                        class="form-label confirm-label"
                    >
                        Escribe <strong>ELIMINAR</strong> para confirmar
                    </label>

                    <input
                        type="text"
                        id="confirmar"
                        name="confirmar"
                        class="form-control"
                        autocomplete="off"
                        placeholder="ELIMINAR"
                        required
                    >

                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">

                    <a
                        href="<?= e($urlTemas) ?>"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-x-lg me-1"></i>
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="btn btn-danger px-4"
                    >
                        <i class="bi bi-trash3-fill me-1"></i>
                        Eliminar definitivamente
                    </button>

                </div>

            </form>

        </div>

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

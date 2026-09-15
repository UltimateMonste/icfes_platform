<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$mensajes = [];

$idMateria = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idMateria || $idMateria <= 0) {

    header("Location: materias.php");
    exit;
}

function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| OBTENER MATERIA
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conexion->prepare("
        SELECT
            id_materia,
            nombre,
            descripcion
        FROM materias
        WHERE id_materia = ?
        LIMIT 1
    ");

    $stmt->execute([
        $idMateria
    ]);

    $materia =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$materia) {

        header("Location: materias.php");
        exit;
    }

} catch (PDOException $e) {

    die(
        "No fue posible cargar la materia."
    );
}


/*
|--------------------------------------------------------------------------
| GUARDAR CAMBIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre =
        trim($_POST["nombre"] ?? "");

    $descripcion =
        trim($_POST["descripcion"] ?? "");


    if ($nombre === "") {

        $errores[] =
            "El nombre es obligatorio.";
    }

    if (mb_strlen($nombre) > 100) {

        $errores[] =
            "El nombre no puede superar los 100 caracteres.";
    }


    /*
     * Comprobar duplicados.
     */

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM materias
                WHERE LOWER(nombre) = LOWER(?)
                AND id_materia <> ?
            ");

            $stmt->execute([
                $nombre,
                $idMateria
            ]);

            if ((int)$stmt->fetchColumn() > 0) {

                $errores[] =
                    "Ya existe otra materia con ese nombre.";
            }

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible validar la materia.";
        }
    }


    /*
     * Actualizar.
     */

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare("
                UPDATE materias
                SET
                    nombre = ?,
                    descripcion = ?
                WHERE id_materia = ?
            ");

            $stmt->execute([
                $nombre,
                $descripcion !== ""
                    ? $descripcion
                    : null,
                $idMateria
            ]);

            $mensajes[] =
                "La materia se actualizó correctamente.";

            $materia["nombre"] =
                $nombre;

            $materia["descripcion"] =
                $descripcion;

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible actualizar la materia.";
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Editar materia | Studia360
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

body {
    background: #f4f7fb;
}

.navbar {
    background: #20252b;
}

.page {
    max-width: 900px;
    margin: auto;
    padding: 35px 18px;
}

.card-studia {
    border: 0;
    border-radius: 20px;
    box-shadow:
        0 10px 30px rgba(20,35,60,.08);
}

</style>

<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">


<style id="studia360-theme">
:root{--s360-accent:#2563eb;--s360-accent-2:#4f46e5;--s360-soft:#eff6ff;--s360-bg:#f6f8fc;--s360-card:#fff;--s360-text:#1f2937;--s360-muted:#748196;--s360-line:#e5eaf1;--s360-input:#fff}
body.s360-content-theme{--s360-accent:#2563eb;--s360-accent-2:#4f46e5;--s360-soft:#eff6ff;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360-accent) 8%,transparent),transparent 25rem),var(--s360-bg)!important;color:var(--s360-text)!important;min-height:100vh;transition:background .25s ease,color .25s ease}
body.s360-content-theme.s360-accent-orange{--s360-accent:#f97316;--s360-accent-2:#ea580c;--s360-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--s360-accent:#8b5cf6;--s360-accent-2:#7c3aed;--s360-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--s360-accent:#10b981;--s360-accent-2:#059669;--s360-soft:#ecfdf5}
body.s360-content-theme .navbar{background:rgba(255,255,255,.94)!important;border-bottom:1px solid var(--s360-line)!important;box-shadow:0 5px 22px rgba(20,35,60,.06)!important}
body.s360-content-theme .navbar-brand{color:var(--s360-text)!important}
body.s360-content-theme .navbar-brand i{color:var(--s360-accent)!important}
body.s360-content-theme .page,body.s360-content-theme .page-wrap{position:relative}
body.s360-content-theme .card-studia,body.s360-content-theme .editor-card,body.s360-content-theme .info-card{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .card-studia h1,body.s360-content-theme .editor-card h1,body.s360-content-theme .editor-card h2,body.s360-content-theme .editor-card h3,body.s360-content-theme .info-card h1,body.s360-content-theme .info-card h2{color:var(--s360-text)!important}
body.s360-content-theme .text-secondary,body.s360-content-theme .text-muted,body.s360-content-theme .url-help{color:var(--s360-muted)!important}
body.s360-content-theme .text-primary{color:var(--s360-accent)!important}
body.s360-content-theme .form-control,body.s360-content-theme .form-select,body.s360-content-theme textarea{background:var(--s360-input)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .form-control:focus,body.s360-content-theme .form-select:focus,body.s360-content-theme textarea:focus{border-color:color-mix(in srgb,var(--s360-accent) 45%,var(--s360-line))!important;box-shadow:0 0 0 4px color-mix(in srgb,var(--s360-accent) 10%,transparent)!important}
body.s360-content-theme .btn-primary{background:var(--s360-accent)!important;border-color:var(--s360-accent)!important;color:#fff!important}
body.s360-content-theme .btn-outline-primary{color:var(--s360-accent)!important;border-color:color-mix(in srgb,var(--s360-accent) 32%,var(--s360-line))!important}
body.s360-content-theme .btn-outline-primary:hover{background:var(--s360-accent)!important;border-color:var(--s360-accent)!important;color:#fff!important}
body.s360-content-theme .actions-bar{background:rgba(255,255,255,.94)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .hero{background:linear-gradient(135deg,var(--s360-accent),var(--s360-accent-2))!important}
body.s360-content-theme .info-box{background:var(--s360-soft)!important;border-left-color:var(--s360-accent)!important;color:var(--s360-text)!important}
body.s360-content-theme .note-editor{border-color:var(--s360-line)!important}
body.s360-content-theme .note-toolbar{background:var(--s360-soft)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .note-btn{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .note-editable{background:var(--s360-input)!important;color:var(--s360-text)!important}
body.s360-content-theme .note-statusbar{background:var(--s360-card)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .adm-theme-toggle{position:fixed!important;right:20px!important;bottom:20px!important;z-index:3000!important;width:50px!important;height:50px!important;padding:0!important;border:0!important;border-radius:16px!important;display:grid!important;place-items:center!important;background:linear-gradient(145deg,var(--s360-accent),var(--s360-accent-2))!important;color:#fff!important;box-shadow:0 12px 30px color-mix(in srgb,var(--s360-accent) 28%,transparent)!important;cursor:pointer!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.1rem!important}
body.s360-content-theme .adm-theme-panel{position:fixed!important;right:20px!important;bottom:82px!important;width:290px!important;max-width:calc(100vw - 28px)!important;padding:16px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;border:1px solid var(--s360-line)!important;border-radius:18px!important;box-shadow:0 20px 55px rgba(20,35,60,.18)!important;z-index:2999!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important;transform:translateY(8px)!important;transition:.18s ease!important}
body.s360-content-theme .adm-theme-panel.open{opacity:1!important;visibility:visible!important;pointer-events:auto!important;transform:none!important}
body.s360-content-theme .adm-theme-title{font-weight:850!important;font-size:.84rem!important;margin:0 0 4px!important;color:var(--s360-text)!important}
body.s360-content-theme .adm-theme-sub{font-size:.66rem!important;color:var(--s360-muted)!important;margin:0 0 13px!important}
body.s360-content-theme .adm-theme-grid{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important}
body.s360-content-theme .adm-theme-option{appearance:none!important;width:100%!important;min-height:74px!important;padding:9px!important;border:1px solid var(--s360-line)!important;border-radius:12px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;text-align:left!important;cursor:pointer!important;font:inherit!important}
body.s360-content-theme .adm-theme-option:hover,body.s360-content-theme .adm-theme-option.active{background:var(--s360-soft)!important;border-color:color-mix(in srgb,var(--s360-accent) 42%,var(--s360-line))!important}
body.s360-content-theme .adm-swatch{display:block!important;height:23px!important;border-radius:8px!important;margin-bottom:7px!important}
body.s360-content-theme .adm-theme-option strong{display:block!important;font-size:.67rem!important}
body.s360-content-theme .adm-theme-option small{display:block!important;font-size:.57rem!important;color:var(--s360-muted)!important;margin-top:3px!important}
body.s360-content-theme .adm-mode-btn{appearance:none!important;width:100%!important;min-height:38px!important;margin-top:9px!important;padding:8px 10px!important;border:1px solid var(--s360-line)!important;border-radius:12px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;cursor:pointer!important;text-align:left!important;font:700 .68rem/1.2 system-ui,sans-serif!important}
body.s360-content-theme.s360-content-dark{--s360-bg:#0d1424;--s360-card:#172236;--s360-text:#edf2f8;--s360-muted:#9ba8ba;--s360-line:#2b374b;--s360-input:#111827;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360-accent) 13%,transparent),transparent 25rem),var(--s360-bg)!important}
body.s360-content-theme.s360-content-dark .navbar{background:rgba(13,20,36,.95)!important}
body.s360-content-theme.s360-content-dark .btn-secondary,body.s360-content-theme.s360-content-dark .btn-outline-secondary{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme.s360-content-dark .bg-white{background:var(--s360-card)!important;color:var(--s360-text)!important}
body.s360-content-theme.s360-content-dark .actions-bar{background:rgba(23,34,54,.95)!important}
body.s360-content-theme.s360-content-dark .modal-content{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme.s360-content-dark .btn-close{filter:invert(1) grayscale(1) brightness(2)}
@media(max-width:767px){body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}}
</style>

</head>

<body class="s360-admin s360-content-theme">

<nav class="navbar navbar-dark">

<div class="container">

<a
    href="../dashboard.php"
    class="navbar-brand fw-bold"
>
    <i class="bi bi-mortarboard-fill me-2"></i>
    Studia360
</a>

<a
    href="materias.php"
    class="btn btn-outline-light btn-sm"
>
    <i class="bi bi-arrow-left me-1"></i>
    Materias
</a>

</div>

</nav>


<main class="page">

<div class="card-studia bg-white p-4 p-md-5">

<div class="mb-4">

<h1 class="h3 fw-bold">

<i class="bi bi-pencil-square text-primary me-2"></i>

Editar materia

</h1>

<p class="text-secondary mb-0">

Modifica la información básica de esta materia.

</p>

</div>


<?php foreach ($mensajes as $mensaje): ?>

<div class="alert alert-success">

<i class="bi bi-check-circle-fill me-2"></i>

<?= e($mensaje) ?>

</div>

<?php endforeach; ?>


<?php foreach ($errores as $error): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= e($error) ?>

</div>

<?php endforeach; ?>


<form method="POST">

<div class="mb-4">

<label class="form-label fw-semibold">

Nombre de la materia

</label>

<input
    type="text"
    name="nombre"
    class="form-control form-control-lg"
    maxlength="100"
    value="<?= e($materia["nombre"]) ?>"
    required
>

</div>


<div class="mb-4">

<label class="form-label fw-semibold">

Descripción

</label>

<textarea
    name="descripcion"
    class="form-control"
    rows="6"
    placeholder="Describe la materia..."
><?= e($materia["descripcion"]) ?></textarea>

</div>


<div class="d-flex justify-content-between flex-wrap gap-2">

<a
    href="materias.php"
    class="btn btn-secondary"
>
    Cancelar
</a>

<button
    type="submit"
    class="btn btn-primary px-4"
>
    <i class="bi bi-save me-1"></i>
    Guardar cambios
</button>

</div>

</form>

</div>

</main>


<!-- Studia360: personalizador de apariencia -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza Studia360</div>
<div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" type="button" data-theme="blue"><span class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></span><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" type="button" data-theme="orange"><span class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></span><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" type="button" data-theme="purple"><span class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></span><strong>Violeta</strong><small>Creativo</small></button>
<button class="adm-theme-option" type="button" data-theme="green"><span class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></span><strong>Verde</strong><small>Calma</small></button>
</div>
<button id="admModeBtn" class="adm-mode-btn" type="button"></button>
</div>
<script>
(function(){
'use strict';
var body=document.body,key='studia360_theme',toggle=document.getElementById('admThemeToggle'),panel=document.getElementById('admThemePanel'),mode=document.getElementById('admModeBtn'),opts=document.querySelectorAll('.adm-theme-option');
var state={theme:'blue',mode:'light'};
try{var raw=localStorage.getItem(key);if(raw){var p=JSON.parse(raw);if(p&&typeof p==='object'){if(typeof p.theme==='string')state.theme=p.theme;if(p.mode==='dark'||p.mode==='light')state.mode=p.mode;}}}catch(e){}
var cls={blue:'',orange:'s360-accent-orange',purple:'s360-accent-purple',green:'s360-accent-green'};
if(!cls[state.theme])state.theme='blue';
function save(){try{localStorage.setItem(key,JSON.stringify(state));}catch(e){}}
function apply(){
Object.keys(cls).forEach(function(k){if(cls[k])body.classList.remove(cls[k]);});
body.classList.add('s360-content-theme');
if(cls[state.theme])body.classList.add(cls[state.theme]);
body.classList.toggle('s360-content-dark',state.mode==='dark');
opts.forEach(function(o){o.classList.toggle('active',o.dataset.theme===state.theme);});
mode.innerHTML=state.mode==='dark'?'<i class="bi bi-moon-stars me-2"></i>Modo oscuro':'<i class="bi bi-sun me-2"></i>Modo claro';
save();
}
opts.forEach(function(o){o.addEventListener('click',function(){state.theme=o.dataset.theme;apply();});});
mode.addEventListener('click',function(){state.mode=state.mode==='dark'?'light':'dark';apply();});
toggle.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();panel.classList.toggle('open');});
panel.addEventListener('click',function(e){e.stopPropagation();});
document.addEventListener('click',function(){panel.classList.remove('open');});
apply();
})();
</script>

</body>

</html>
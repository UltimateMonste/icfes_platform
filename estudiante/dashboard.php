<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';

exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    redireccionarLogin('Tu sesión no es válida.');
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fotoPerfil(?string $foto): string {
    $foto = trim((string)$foto);
    if ($foto === '') return '';
    if (preg_match('#^https?://#i', $foto)) return $foto;

    $foto = str_replace('\\', '/', ltrim($foto, '/'));

    if (str_starts_with($foto, 'assets/')) {
        return urlAplicacion('/' . $foto);
    }
    if (str_starts_with($foto, 'uploads/')) {
        return urlAplicacion('/assets/' . $foto);
    }

    return urlAplicacion('/assets/uploads/avatares/' . basename($foto));
}

try {
    $st = $conexion->prepare("
        SELECT nombres, apellidos, grado, avatar
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ");
    $st->execute([$idUsuario]);
    $usuario = $st->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        redireccionarLogin('Usuario no encontrado.');
    }

    sincronizarNivel($conexion, $idUsuario);
    $gam = obtenerGamificacionUsuario($conexion, $idUsuario);
    $progresoGeneral = obtenerProgresoGeneral($conexion, $idUsuario);

    $insigniasObtenidas = [];
    try {
        $stIns = $conexion->prepare("
            SELECT i.nombre
            FROM usuarios_insignias ui
            INNER JOIN insignias i ON i.id_insignia = ui.id_insignia
            WHERE ui.id_usuario = ? AND i.estado = 'Activa'
            ORDER BY ui.fecha DESC, i.nombre ASC
        ");
        $stIns->execute([$idUsuario]);
        $insigniasObtenidas = $stIns->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $insigniasObtenidas = [];
    }

    // No se filtra por el grado del estudiante:
    // cualquier estudiante puede explorar 9°, 10° y 11°.
    $st = $conexion->query("
        SELECT
            m.id_materia,
            m.nombre,
            m.descripcion,
            COUNT(t.id_tema) AS total_temas
        FROM materias m
        LEFT JOIN temas t ON t.id_materia = m.id_materia
        GROUP BY m.id_materia, m.nombre, m.descripcion
        ORDER BY m.nombre
    ");
    $materias = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die('No fue posible cargar el dashboard.');
}

$foto = fotoPerfil($usuario['avatar'] ?? '');
$nombre = trim((string)$usuario['nombres']);
$nivel = $gam['nivel'];
$puntos = (int)$gam['puntos'];
$progresoNivel = (float)$gam['progreso_nivel'];
$siguienteNivel = $gam['puntos_siguiente_nivel'];

$iniciales = '';
foreach (preg_split('/\s+/', trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''))) as $parte) {
    if ($parte !== '') {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($iniciales) >= 2) break;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inicio | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
    --sd-accent:#2563eb;
    --sd-accent-2:#4f46e5;
    --sd-accent-soft:#eff6ff;
    --sd-bg:#f6f8fc;
    --sd-card:#ffffff;
    --sd-text:#1f2937;
    --sd-muted:#748196;
    --sd-line:#e5eaf1;
    --sd-track:#e9eef5;
    --sd-shadow:0 10px 28px rgba(31,45,70,.055);
    --sd-shadow-hover:0 16px 36px rgba(31,45,70,.09);
    --sd-radius:20px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    background:
        radial-gradient(circle at 92% 0%, color-mix(in srgb,var(--sd-accent) 7%,transparent), transparent 25rem),
        var(--sd-bg);
    color:var(--sd-text);
    font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
    transition:background .25s ease,color .25s ease;
}

/* Navegación */
.navbar.sd-navbar{
    position:sticky;
    top:0;
    z-index:1030;
    min-height:68px;
    background:rgba(255,255,255,.88)!important;
    border-bottom:1px solid var(--sd-line)!important;
    box-shadow:0 5px 24px rgba(20,35,60,.035)!important;
    backdrop-filter:blur(16px);
}
.sd-brand{
    display:flex;
    align-items:center;
    gap:9px;
    color:var(--sd-text)!important;
    text-decoration:none;
    font-weight:850!important;
    letter-spacing:-.035em;
}
.sd-logo{
    width:38px;
    height:38px;
    border-radius:12px;
    display:grid;
    place-items:center;
    flex:none;
    color:#fff;
    background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
    box-shadow:0 8px 18px color-mix(in srgb,var(--sd-accent) 20%,transparent);
}
.sd-nav-actions{
    display:flex;
    align-items:center;
    gap:7px;
}
.sd-nav-btn{
    height:38px;
    border:1px solid var(--sd-line)!important;
    background:var(--sd-card)!important;
    color:#637086!important;
    border-radius:11px!important;
    font-size:.73rem!important;
    font-weight:750!important;
    transition:.18s ease;
}
.sd-nav-btn:hover{
    color:var(--sd-accent)!important;
    background:var(--sd-accent-soft)!important;
    transform:translateY(-1px);
}
.sd-avatar-mini{
    width:35px;
    height:35px;
    border-radius:50%;
    overflow:hidden;
    display:grid;
    place-items:center;
    flex:none;
    background:var(--sd-accent-soft);
    color:var(--sd-accent);
    font-weight:850;
    border:2px solid var(--sd-card);
    box-shadow:0 2px 9px rgba(20,40,70,.08);
}
.sd-avatar-mini img{width:100%;height:100%;object-fit:cover}

/* Contenedor */
main.container{
    max-width:1160px;
}
main.container{
    padding-top:30px!important;
    padding-bottom:55px!important;
}

/* Bienvenida */
.welcome{
    position:relative;
    overflow:hidden;
    isolation:isolate;
    min-height:168px;
    background:
        radial-gradient(circle at 91% 10%,rgba(255,255,255,.14),transparent 12rem),
        linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2));
    border-radius:25px;
    padding:28px 30px;
    color:#fff;
    box-shadow:0 18px 42px color-mix(in srgb,var(--sd-accent) 16%,transparent);
}
.welcome:before{
    content:"";
    position:absolute;
    width:250px;
    height:250px;
    right:-92px;
    bottom:-170px;
    border-radius:50%;
    border:30px solid rgba(255,255,255,.065);
    z-index:-1;
}
.welcome:after{
    content:"";
    position:absolute;
    width:150px;
    height:150px;
    right:90px;
    top:-105px;
    border-radius:50%;
    background:rgba(255,255,255,.045);
    z-index:-1;
}
.welcome-avatar{
    width:76px;
    height:76px;
    border-radius:22px;
    overflow:hidden;
    flex:none;
    background:rgba(255,255,255,.15);
    color:#fff;
    border:3px solid rgba(255,255,255,.8);
    display:grid;
    place-items:center;
    font-size:1.55rem;
    font-weight:900;
    box-shadow:0 7px 20px rgba(0,0,0,.08);
}
.welcome-avatar img{width:100%;height:100%;object-fit:cover}
.welcome .h2{
    font-size:1.9rem;
    line-height:1.05;
    letter-spacing:-.045em;
}
.welcome .small.text-uppercase{letter-spacing:.05em}
.xp-pill{
    display:inline-flex;
    align-items:center;
    gap:7px;
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.2);
    padding:7px 12px;
    border-radius:999px;
    font-size:.73rem;
    font-weight:800;
    backdrop-filter:blur(4px);
}

/* Títulos / insignias */
.sd-title-row{
    display:flex;
    flex-wrap:wrap;
    gap:5px;
    margin-top:7px;
}
.sd-title-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:5px 8px;
    border-radius:999px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    font-size:.61rem;
    font-weight:800;
}
.sd-title-badge i{font-size:.63rem}

/* Tarjetas de resumen */
.quick{
    background:var(--sd-card);
    border:1px solid var(--sd-line);
    border-radius:17px;
    padding:15px 17px;
    min-height:76px;
    box-shadow:var(--sd-shadow);
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .25s;
}
.quick:hover{
    transform:translateY(-2px);
    box-shadow:var(--sd-shadow-hover);
    border-color:color-mix(in srgb,var(--sd-accent) 17%,var(--sd-line));
}
.quick-icon{
    width:40px;
    height:40px;
    border-radius:12px;
    display:grid;
    place-items:center;
    flex:none;
    background:var(--sd-accent-soft);
    color:var(--sd-accent);
    font-size:1rem;
}
.quick .small{font-size:.68rem!important}
.quick strong{font-size:.83rem}

/* Progreso */
.level-card{
    background:var(--sd-card);
    border:1px solid var(--sd-line);
    border-radius:19px;
    padding:19px 21px;
    box-shadow:var(--sd-shadow);
}
.level-card .text-primary{
    color:var(--sd-accent)!important;
}
.progress{
    height:8px;
    background:var(--sd-track);
    border-radius:99px;
    overflow:hidden;
}
.progress-bar{
    background:linear-gradient(90deg,var(--sd-accent),var(--sd-accent-2));
    border-radius:99px;
    transition:width .5s ease;
}

/* Exploración */
.explore{
    background:var(--sd-card);
    border:1px solid var(--sd-line);
    border-radius:19px;
    padding:18px 20px;
    box-shadow:var(--sd-shadow);
}
.explore .h4{
    font-size:1.12rem;
    letter-spacing:-.025em;
}
.grade-filter{
    display:flex;
    gap:4px;
    flex-wrap:wrap;
    background:var(--sd-accent-soft);
    padding:4px;
    border-radius:13px;
}
.grade-btn{
    border:0;
    background:transparent;
    color:var(--sd-muted);
    padding:8px 15px;
    border-radius:10px;
    font-size:.75rem;
    font-weight:850;
    transition:.18s ease;
}
.grade-btn:hover{color:var(--sd-accent)}
.grade-btn.active{
    background:var(--sd-card);
    color:var(--sd-accent);
    box-shadow:0 3px 10px rgba(30,50,80,.08);
}

/* Materias */
.subject{
    position:relative;
    height:100%;
    background:var(--sd-card);
    border:1px solid var(--sd-line);
    border-radius:19px;
    padding:17px;
    box-shadow:var(--sd-shadow);
    transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease,background .25s;
}
.subject:hover{
    transform:translateY(-4px);
    box-shadow:var(--sd-shadow-hover);
    border-color:color-mix(in srgb,var(--sd-accent) 22%,var(--sd-line));
}
.subject-icon{
    width:48px;
    height:48px;
    border-radius:14px;
    display:grid;
    place-items:center;
    color:var(--sd-accent);
    background:var(--sd-accent-soft);
    font-size:1.15rem;
    margin-bottom:14px;
}
.subject h3{
    font-size:.98rem;
    letter-spacing:-.015em;
}
.subject p{
    min-height:39px;
    font-size:.72rem!important;
    line-height:1.55;
}
.subject .muted{color:var(--sd-muted)!important}
.btn-study{
    display:block;
    width:100%;
    border:1px solid color-mix(in srgb,var(--sd-accent) 15%,var(--sd-line));
    border-radius:11px;
    padding:8px 12px;
    font-size:.7rem;
    font-weight:850;
    background:var(--sd-accent-soft);
    color:var(--sd-accent);
    transition:.18s ease;
}
.btn-study:hover{
    background:var(--sd-accent);
    border-color:var(--sd-accent);
    color:#fff;
    transform:translateY(-1px);
}

/* Apariencia */
.sd-theme-toggle{
    position:fixed;
    right:20px;
    bottom:20px;
    z-index:1045;
    width:48px;
    height:48px;
    border:0;
    border-radius:15px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
    box-shadow:0 12px 28px color-mix(in srgb,var(--sd-accent) 24%,transparent);
    cursor:pointer;
    transition:transform .2s ease;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(3deg)}
.sd-theme-panel{
    position:fixed;
    right:20px;
    bottom:78px;
    z-index:1046;
    width:285px;
    padding:15px;
    background:var(--sd-card);
    color:var(--sd-text);
    border:1px solid var(--sd-line);
    border-radius:18px;
    box-shadow:0 20px 50px rgba(20,35,60,.15);
    transform:translateY(8px) scale(.98);
    opacity:0;
    pointer-events:none;
    transition:.2s ease;
}
.sd-theme-panel.open{
    transform:none;
    opacity:1;
    pointer-events:auto;
}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--sd-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
    border:1px solid var(--sd-line);
    background:var(--sd-card);
    border-radius:12px;
    padding:9px;
    text-align:left;
    cursor:pointer;
    color:var(--sd-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
    border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff);
    background:var(--sd-accent-soft);
}
.sd-theme-swatch{height:22px;border-radius:8px;margin-bottom:6px}
.sd-theme-option strong{display:block;font-size:.65rem}
.sd-theme-option small{font-size:.56rem;color:var(--sd-muted)}
.sd-theme-mode{
    width:100%;
    margin-top:8px;
    border:1px solid var(--sd-line);
    background:var(--sd-card);
    border-radius:12px;
    padding:8px;
    color:var(--sd-text);
    font-size:.68rem;
    font-weight:750;
    cursor:pointer;
    text-align:left;
}

/* Modo oscuro: aquí se sobreescriben también los estilos antiguos del dashboard */
body.sd-dark{
    --sd-bg:#0d1424;
    --sd-card:#172236;
    --sd-text:#edf2f8;
    --sd-muted:#9ba8ba;
    --sd-line:#2b374b;
    --sd-track:#263247;
    background:
        radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--sd-accent) 13%,transparent),transparent 25rem),
        var(--sd-bg)!important;
}
body.sd-dark .navbar.sd-navbar{
    background:rgba(13,20,36,.9)!important;
    border-color:var(--sd-line)!important;
}
body.sd-dark .sd-nav-btn{
    background:var(--sd-card)!important;
    color:#aeb9ca!important;
}
body.sd-dark .welcome{
    box-shadow:0 18px 42px rgba(0,0,0,.2);
}
body.sd-dark .quick,
body.sd-dark .level-card,
body.sd-dark .explore,
body.sd-dark .subject{
    background:var(--sd-card)!important;
    border-color:var(--sd-line)!important;
    color:var(--sd-text)!important;
}
body.sd-dark .subject:hover,
body.sd-dark .quick:hover{
    border-color:color-mix(in srgb,var(--sd-accent) 30%,var(--sd-line))!important;
}
body.sd-dark .grade-filter{
    background:#202c41!important;
}
body.sd-dark .grade-btn.active{
    background:#27344a!important;
}
body.sd-dark .btn-study{
    background:color-mix(in srgb,var(--sd-accent) 13%,var(--sd-card));
    border-color:color-mix(in srgb,var(--sd-accent) 24%,var(--sd-line));
}
body.sd-dark .dropdown-menu{
    background:#172236!important;
    border-color:var(--sd-line)!important;
}
body.sd-dark .dropdown-item{color:#dce4ee!important}
body.sd-dark .dropdown-item:hover{background:#202c41!important}
body.sd-dark .sd-theme-panel{box-shadow:0 20px 55px rgba(0,0,0,.35)}
body.sd-dark .sd-theme-option,
body.sd-dark .sd-theme-mode{
    background:var(--sd-card);
    color:var(--sd-text);
}
body.sd-dark .muted{color:var(--sd-muted)!important}

body.sd-accent-orange{
    --sd-accent:#f97316;
    --sd-accent-2:#ea580c;
    --sd-accent-soft:#fff7ed;
}
body.sd-accent-purple{
    --sd-accent:#8b5cf6;
    --sd-accent-2:#7c3aed;
    --sd-accent-soft:#f5f3ff;
}
body.sd-accent-green{
    --sd-accent:#10b981;
    --sd-accent-2:#059669;
    --sd-accent-soft:#ecfdf5;
}

@media(max-width:767px){
    main.container{padding-top:20px!important}
    .welcome{padding:22px;border-radius:21px}
    .welcome-avatar{width:64px;height:64px;border-radius:19px}
    .welcome .h2{font-size:1.55rem}
    .quick{min-height:70px}
    .subject{padding:16px}
    .sd-theme-toggle{right:14px;bottom:14px}
    .sd-theme-panel{
        right:12px;
        bottom:70px;
        width:min(285px,calc(100vw - 24px));
    }
}
@media(prefers-reduced-motion:reduce){
    *,*:before,*:after{
        animation:none!important;
        transition:none!important;
    }
}
</style>
<style id="studia360-common-style">
:root{
  --sd-accent:#2563eb;
  --sd-accent-2:#4f46e5;
  --sd-accent-soft:#eff6ff;
  --sd-bg:#f7f9fc;
  --sd-card:#ffffff;
  --sd-text:#1f2a3d;
  --sd-muted:#7b8798;
  --sd-line:#e6ebf2;
  --sd-success:#10b981;
  --sd-warning:#f59e0b;
  --sd-danger:#ef4444;
  --sd-shadow:0 12px 34px rgba(31,55,86,.055);
  --sd-shadow-hover:0 18px 42px rgba(31,55,86,.09);
  --sd-radius:20px;
}
html{scroll-behavior:smooth}
body.sd-page{
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 8%,transparent),transparent 28rem),
    var(--sd-bg)!important;
  color:var(--sd-text);
  transition:background .25s,color .25s;
}
.sd-navbar{
  position:sticky;top:0;z-index:1030;
  min-height:68px;
  background:rgba(255,255,255,.9)!important;
  border-bottom:1px solid var(--sd-line)!important;
  box-shadow:0 5px 24px rgba(31,55,86,.035);
  backdrop-filter:blur(15px);
}
.sd-brand{
  display:flex;align-items:center;gap:9px;
  color:var(--sd-text)!important;font-weight:850!important;
  letter-spacing:-.035em;text-decoration:none;
}
.sd-logo{
  width:38px;height:38px;border-radius:12px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 8px 18px color-mix(in srgb,var(--sd-accent) 22%,transparent);
}
.sd-nav-actions{display:flex;align-items:center;gap:7px}
.sd-nav-btn{
  height:38px;border:1px solid var(--sd-line);background:#fff!important;
  color:#637086!important;border-radius:11px!important;
  font-size:.73rem!important;font-weight:750!important;
}
.sd-nav-btn:hover{background:var(--sd-accent-soft)!important;color:var(--sd-accent)!important}
.sd-avatar-mini{
  width:34px;height:34px;border-radius:50%;overflow:hidden;
  display:grid;place-items:center;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-weight:850;border:2px solid #fff;
  box-shadow:0 2px 9px rgba(20,40,70,.08);
}
.sd-avatar-mini img{width:100%;height:100%;object-fit:cover}
.sd-shell{width:min(1240px,calc(100% - 30px));margin:auto;padding:27px 0 55px}
.sd-page-title{font-size:1.65rem;font-weight:850;letter-spacing:-.04em;margin:0}
.sd-page-sub{font-size:.78rem;color:var(--sd-muted);margin:.25rem 0 0}
.sd-card{
  background:var(--sd-card)!important;
  border:1px solid var(--sd-line)!important;
  border-radius:var(--sd-radius)!important;
  box-shadow:var(--sd-shadow)!important;
}
.sd-card:hover{box-shadow:var(--sd-shadow-hover)!important}
.sd-btn{
  border-radius:10px!important;font-size:.74rem!important;
  font-weight:750!important;padding:.52rem .76rem!important;
}
.sd-btn-primary{
  color:#fff!important;background:var(--sd-accent)!important;border-color:var(--sd-accent)!important;
  box-shadow:0 7px 16px color-mix(in srgb,var(--sd-accent) 14%,transparent);
}
.sd-btn-outline{
  color:var(--sd-accent)!important;background:var(--sd-card)!important;border:1px solid color-mix(in srgb,var(--sd-accent) 22%,var(--sd-line))!important;
}
.sd-btn-outline:hover{background:var(--sd-accent-soft)!important}
.sd-form .form-control,.sd-form .form-select{
  border:1px solid var(--sd-line)!important;border-radius:11px!important;
  min-height:40px;box-shadow:none!important;
}
.sd-form .form-control:focus,.sd-form .form-select:focus{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff)!important;
  box-shadow:0 0 0 4px color-mix(in srgb,var(--sd-accent) 9%,transparent)!important;
}
.sd-muted{color:var(--sd-muted)!important}
.sd-eyebrow{
  display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;
  color:var(--sd-accent);background:var(--sd-accent-soft);
  border:1px solid color-mix(in srgb,var(--sd-accent) 12%,#fff);
  font-size:.61rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em;
}
.sd-hero{
  position:relative;overflow:hidden;color:#fff;
  border-radius:25px;padding:27px 29px;
  background:
    radial-gradient(circle at 90% 12%,rgba(255,255,255,.15),transparent 17rem),
    linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 18px 44px color-mix(in srgb,var(--sd-accent) 15%,transparent);
}
.sd-hero:after{
  content:"";position:absolute;width:200px;height:200px;border-radius:50%;
  right:-75px;bottom:-110px;border:27px solid rgba(255,255,255,.055);
}
.sd-hero>*{position:relative;z-index:1}
.sd-fade{animation:sdFade .38s ease both}
@keyframes sdFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.sd-theme-toggle{
  position:fixed;right:20px;bottom:20px;z-index:1045;
  width:48px;height:48px;border:0;border-radius:16px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 12px 28px color-mix(in srgb,var(--sd-accent) 25%,transparent);
  cursor:pointer;transition:transform .2s,box-shadow .2s;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(4deg)}
.sd-theme-panel{
  position:fixed;right:20px;bottom:78px;z-index:1046;width:285px;
  padding:15px;background:var(--sd-card);border:1px solid var(--sd-line);
  border-radius:18px;box-shadow:0 20px 50px rgba(20,35,60,.15);
  transform:translateY(8px) scale(.98);opacity:0;pointer-events:none;
  transition:.2s;
}
.sd-theme-panel.open{transform:none;opacity:1;pointer-events:auto}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--sd-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
  border:1px solid var(--sd-line);background:var(--sd-card);border-radius:12px;
  padding:9px;text-align:left;cursor:pointer;color:var(--sd-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff);
  background:var(--sd-accent-soft);
}
.sd-theme-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.sd-theme-option strong{display:block;font-size:.66rem}
.sd-theme-option small{font-size:.57rem;color:var(--sd-muted)}
.sd-theme-mode{
  width:100%;margin-top:8px;border:1px solid var(--sd-line);background:var(--sd-card);
  border-radius:12px;padding:8px;color:var(--sd-text);font-size:.68rem;font-weight:750;
  cursor:pointer;text-align:left;
}

/* Student badges/titles */
.sd-title-row{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
.sd-title-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 8px;border-radius:999px;
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.18);
  color:#fff;font-size:.62rem;font-weight:800;
}
.sd-title-badge.dark{
  color:#795f13;background:#fff8df;border-color:#efdfaa;
}
.sd-title-badge i{font-size:.65rem}

/* Dark mode */
body.sd-dark{
  --sd-bg:#111827;--sd-card:#182235;--sd-text:#edf2f7;--sd-muted:#9aa7ba;--sd-line:#2a3549;
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 13%,transparent),transparent 28rem),
    var(--sd-bg)!important;
}
body.sd-dark .sd-navbar{background:rgba(17,24,39,.88)!important}
body.sd-dark .sd-nav-btn{background:#182235!important;color:#aab5c5!important}
body.sd-dark .sd-card,
body.sd-dark .card,
body.sd-dark .cardx,
body.sd-dark .panel,
body.sd-dark .subject,
body.sd-dark .quick,
body.sd-dark .level-card,
body.sd-dark .explore,
body.sd-dark .resource-card,
body.sd-dark .evaluation-card,
body.sd-dark .stat,
body.sd-dark .avatar-option,
body.sd-dark .message-card{background:var(--sd-card)!important;color:var(--sd-text)!important}
body.sd-dark .form-control,body.sd-dark .form-select{
  background:#111827!important;color:var(--sd-text)!important;border-color:var(--sd-line)!important;
}
body.sd-dark .form-control::placeholder{color:#718096}
body.sd-dark .text-dark,body.sd-dark h1,body.sd-dark h2,body.sd-dark h3,body.sd-dark h4,body.sd-dark h5,
body.sd-dark .fw-bold,body.sd-dark .fw-semibold{color:var(--sd-text)!important}
body.sd-dark .text-muted,body.sd-dark .muted{color:var(--sd-muted)!important}
body.sd-dark .sd-theme-option,body.sd-dark .sd-theme-mode{background:var(--sd-card);color:var(--sd-text)}
body.sd-dark .table{--bs-table-bg:var(--sd-card);--bs-table-color:var(--sd-text)}
body.sd-dark .dropdown-menu{background:#182235;border-color:var(--sd-line)}
body.sd-dark .dropdown-item{color:#dce4ee}
body.sd-dark .dropdown-item:hover{background:#202d42}
body.sd-dark .bg-light{background:#202d42!important}
body.sd-dark .border{border-color:var(--sd-line)!important}

/* Accent themes */
body.sd-accent-orange{--sd-accent:#f97316;--sd-accent-2:#ea580c;--sd-accent-soft:#fff7ed}
body.sd-accent-purple{--sd-accent:#8b5cf6;--sd-accent-2:#7c3aed;--sd-accent-soft:#f5f3ff}
body.sd-accent-green{--sd-accent:#10b981;--sd-accent-2:#059669;--sd-accent-soft:#ecfdf5}

@media(max-width:767px){
  .sd-shell{width:min(100% - 20px,1240px);padding-top:19px}
  .sd-page-title{font-size:1.4rem}
  .sd-theme-toggle{right:14px;bottom:14px}
  .sd-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation:none!important;transition:none!important}
}
</style>
<link rel="stylesheet" href="studia360-estudiante.css">
</head>

<body class="sd-page">


<nav class="navbar sd-navbar">
  <div class="container-fluid px-3 px-lg-4">
    <a href="dashboard.php" class="sd-brand">
      <span class="sd-logo"><i class="bi bi-stars"></i></span>
      <span>Studia360</span>
    </a>
    <div class="sd-nav-actions">
      <div class="dropdown">
        <button class="btn sd-nav-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Explorar</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm p-2" style="border-radius:14px">
          <li><a class="dropdown-item rounded-2 small" href="dashboard.php"><i class="bi bi-house me-2"></i>Inicio</a></li>
          <li><a class="dropdown-item rounded-2 small" href="grado.php?grado=9"><i class="bi bi-book me-2"></i>Materias y temas</a></li>
          <li><a class="dropdown-item rounded-2 small" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
          <li><a class="dropdown-item rounded-2 small" href="sugerencias.php"><i class="bi bi-chat-left-heart me-2"></i>Quejas y recomendaciones</a></li>
          <li><a class="dropdown-item rounded-2 small" href="recuperacion.php"><i class="bi bi-key me-2"></i>Notificaciones</a></li>
        </ul>
      </div>
      <a href="perfil.php" class="sd-avatar-mini d-none d-sm-grid" title="Mi perfil">
        <?php if (!empty($foto)): ?><img src="<?=h($foto)?>" alt="Mi perfil"><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
      </a>
      <a href="perfil.php" class="btn sd-nav-btn d-none d-md-inline-flex"><i class="bi bi-person me-1"></i>Perfil</a>
      <a href="../cerrar_sesion.php" class="btn sd-nav-btn" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Salir</span></a>
    </div>
  </div>
</nav>


<main class="container py-4 py-lg-5">

<section class="welcome mb-4">
    <div class="d-flex align-items-center justify-content-between gap-4 position-relative" style="z-index:1">
        <div class="d-flex align-items-center gap-3">
            <div class="welcome-avatar">
                <?php if ($foto): ?>
                    <img src="<?=h($foto)?>" alt="Foto de <?=h($nombre)?>">
                <?php else: ?>
                    <?=h($iniciales)?>
                <?php endif; ?>
            </div>

            <div>
                <div class="small text-uppercase opacity-75 fw-semibold">Qué bueno verte</div>
                <h1 class="h2 fw-bold mb-1">¡Hola, <?=h($nombre)?>! 👋</h1>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="xp-pill"><i class="bi bi-star-fill"></i><?=number_format($puntos)?> XP</span>
                    <span class="xp-pill"><i class="bi bi-trophy-fill"></i><?=h($nivel['nombre'])?></span>
                </div>
                <?php if (!empty($insigniasObtenidas)): ?>
                <div class="sd-title-row">
                    <?php foreach (array_slice($insigniasObtenidas, 0, 4) as $titulo): ?>
                        <span class="sd-title-badge"><i class="bi bi-award-fill"></i><?=h($titulo)?></span>
                    <?php endforeach; ?>
                    <?php if (count($insigniasObtenidas) > 4): ?><span class="sd-title-badge">+<?=count($insigniasObtenidas)-4?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-none d-md-block text-end">
            <div class="small opacity-75">Tu progreso</div>
            <div class="display-6 fw-bold"><?=number_format((float)($progresoGeneral['porcentaje'] ?? 0),0)?>%</div>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <div><div class="small muted">Experiencia</div><strong><?=number_format($puntos)?> XP</strong></div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-trophy-fill"></i></div>
            <div><div class="small muted">Nivel</div><strong><?=h($nivel['nombre'])?></strong></div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div><div class="small muted">Progreso</div><strong><?=number_format((float)($progresoGeneral['porcentaje'] ?? 0),0)?>% completado</strong></div>
        </div>
    </div>
</div>

<section class="level-card mb-4">
    <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
            <div class="small text-primary fw-bold">SIGUE AVANZANDO</div>
            <h2 class="h5 fw-bold mb-0"><?=h($nivel['nombre'])?></h2>
        </div>
        <strong><?=number_format($progresoNivel,0)?>%</strong>
    </div>
    <div class="progress">
        <div class="progress-bar" style="width:<?=h((string)$progresoNivel)?>%"></div>
    </div>
    <div class="small muted mt-2">
        <?php if ($siguienteNivel !== null): ?>
            Te faltan <?=number_format(max(0,(int)$siguienteNivel-$puntos))?> XP para subir de nivel.
        <?php else: ?>
            ¡Llegaste al nivel máximo! 🏆
        <?php endif; ?>
    </div>
</section>

<section class="explore mb-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h2 class="h4 fw-bold mb-1">¿Qué quieres aprender hoy?</h2>
            <div class="small muted">Elige un grado y descubre sus contenidos.</div>
        </div>

        <div class="grade-filter" id="selectorGrado">
            <button type="button" class="grade-btn" data-grado="9">9°</button>
            <button type="button" class="grade-btn" data-grado="10">10°</button>
            <button type="button" class="grade-btn" data-grado="11">11°</button>
        </div>
    </div>
</section>

<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h2 class="h4 fw-bold mb-1">Tus materias</h2>
        <div class="small muted" id="textoGrado">Contenidos de grado <?=h($usuario['grado'])?>°</div>
    </div>
</div>

<div class="row g-4">
<?php foreach ($materias as $m): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <article class="subject">
            <div class="subject-icon"><i class="bi bi-book-half"></i></div>
            <h3 class="h5 fw-bold mb-2"><?=h($m['nombre'])?></h3>
            <p class="small muted mb-3">
                <?=h($m['descripcion'] ?: 'Explora, aprende y avanza a tu ritmo.')?>
            </p>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small muted">
                    <i class="bi bi-journal-text me-1"></i>
                    <?=number_format((int)$m['total_temas'])?> temas
                </span>
            </div>

            <a class="btn-study w-100 d-block text-center text-decoration-none"
               href="#"
               data-materia="<?=h((string)$m['id_materia'])?>">
                Explorar <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </article>
    </div>
<?php endforeach; ?>

<?php if (!$materias): ?>
    <div class="col-12">
        <div class="sd-card p-4 text-center"><div class="quick-icon mx-auto mb-2"><i class="bi bi-book"></i></div><div class="fw-bold">Aún no hay materias disponibles</div><div class="small muted mt-1">Cuando el administrador publique materias, aparecerán aquí.</div></div>
    </div>
<?php endif; ?>
</div>

</main>

<script>
(function(){
    const base = <?=json_encode(urlAplicacion('/estudiante/grado.php?grado='))?>;
    const botones = document.querySelectorAll('#selectorGrado .grade-btn');
    const enlaces = document.querySelectorAll('[data-materia]');
    const texto = document.getElementById('textoGrado');

    let gradoActual = <?=json_encode((string)$usuario['grado'])?>;

    function actualizar(){
        enlaces.forEach(function(enlace){
            enlace.href = base + gradoActual + '&id_materia=' + enlace.dataset.materia;
        });
        texto.textContent = 'Contenidos de grado ' + gradoActual + '°';
    }

    botones.forEach(function(btn){
        btn.addEventListener('click', function(){
            gradoActual = this.dataset.grado;
            botones.forEach(function(b){ b.classList.toggle('active', b === btn); });
            actualizar();
        });
    });

    const inicial = document.querySelector('#selectorGrado [data-grado="' + gradoActual + '"]');
    if(inicial) inicial.classList.add('active');

    actualizar();
})();
</script>


<button id="sdThemeToggle" class="sd-theme-toggle" type="button" aria-label="Cambiar apariencia" title="Personalizar apariencia">
  <i class="bi bi-palette2"></i>
</button>
<div id="sdThemePanel" class="sd-theme-panel" aria-label="Personalizar apariencia">
  <div class="sd-theme-title">Personaliza Studia360</div>
  <div class="sd-theme-sub">Elige el color que te guste y decide si prefieres fondo claro u oscuro.</div>
  <div class="sd-theme-row">
    <button class="sd-theme-option" data-theme="blue" onclick="studiaTheme('blue')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
    <button class="sd-theme-option" data-theme="orange" onclick="studiaTheme('orange')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
    <button class="sd-theme-option" data-theme="purple" onclick="studiaTheme('purple')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Creativo</small></button>
    <button class="sd-theme-option" data-theme="green" onclick="studiaTheme('green')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
  </div>
  <button id="sdThemeMode" class="sd-theme-mode" type="button" onclick="studiaThemeMode()"></button>
</div>

<script>
(function(){
  const body=document.body;
  const key="studia360_theme";
  const themes={
    blue:{label:"Azul",className:""},
    orange:{label:"Naranja",className:"sd-accent-orange"},
    purple:{label:"Violeta",className:"sd-accent-purple"},
    green:{label:"Verde",className:"sd-accent-green"}
  };
  function applyTheme(theme,mode){
    Object.values(themes).forEach(t=>{if(t.className) body.classList.remove(t.className)});
    if(themes[theme]?.className) body.classList.add(themes[theme].className);
    body.classList.toggle("sd-dark",mode==="dark");
    document.querySelectorAll(".sd-theme-option").forEach(el=>el.classList.toggle("active",el.dataset.theme===theme));
    const modeBtn=document.getElementById("sdThemeMode");
    if(modeBtn) modeBtn.innerHTML=(mode==="dark"?"<i class='bi bi-moon-stars me-2'></i>Modo oscuro":"<i class='bi bi-sun me-2'></i>Modo claro");
  }
  let saved={theme:"blue",mode:"light"};
  try{saved=Object.assign(saved,JSON.parse(localStorage.getItem(key)||"{}"));}catch(e){}
  applyTheme(saved.theme,saved.mode);
  window.studiaTheme=function(theme){
    saved.theme=theme;
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  window.studiaThemeMode=function(){
    saved.mode=saved.mode==="dark"?"light":"dark";
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  const toggle=document.getElementById("sdThemeToggle");
  const panel=document.getElementById("sdThemePanel");
  toggle?.addEventListener("click",()=>panel?.classList.toggle("open"));
  document.addEventListener("click",e=>{
    if(panel && panel.classList.contains("open") && !panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove("open");
  });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

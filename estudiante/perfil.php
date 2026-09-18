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

function recursoImagen(?string $imagen, string $carpeta = 'avatares'): string {
    $imagen = trim((string)$imagen);
    if ($imagen === '') return '';
    if (preg_match('#^https?://#i', $imagen)) return $imagen;

    $imagen = str_replace('\\', '/', $imagen);
    $imagen = ltrim($imagen, '/');

    if (str_starts_with($imagen, 'assets/')) {
        return urlAplicacion('/' . $imagen);
    }
    if (str_starts_with($imagen, 'uploads/')) {
        return urlAplicacion('/assets/' . $imagen);
    }

    return urlAplicacion('/assets/uploads/' . $carpeta . '/' . rawurlencode(basename($imagen)));
}

if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_perfil'];
if (empty($_SESSION['csrf_perfil_accion'])) { $_SESSION['csrf_perfil_accion'] = bin2hex(random_bytes(32)); }
$mensaje = (string)($_SESSION['mensaje_perfil'] ?? '');
$tipoMensaje = (string)($_SESSION['tipo_mensaje_perfil'] ?? 'success');
unset($_SESSION['mensaje_perfil'], $_SESSION['tipo_mensaje_perfil']);
$tipoMensaje = 'success';

try {
    sincronizarNivel($conexion, $idUsuario);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_avatar') {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('La solicitud de seguridad expiró. Recarga la página.');
        }

        $idAvatar = filter_input(INPUT_POST, 'id_avatar', FILTER_VALIDATE_INT);
        if (!$idAvatar || $idAvatar < 1) {
            throw new RuntimeException('Selecciona un avatar válido.');
        }

        $st = $conexion->prepare("
            SELECT id_avatar, nombre, imagen, puntos_requeridos
            FROM avatares
            WHERE id_avatar = ? AND estado = 'Activo'
            LIMIT 1
        ");
        $st->execute([$idAvatar]);
        $avatar = $st->fetch(PDO::FETCH_ASSOC);

        if (!$avatar) throw new RuntimeException('El avatar seleccionado no está disponible.');

        $st = $conexion->prepare("SELECT puntos FROM usuarios WHERE id_usuario = ?");
        $st->execute([$idUsuario]);
        $puntosUsuario = (int)$st->fetchColumn();

        if ($puntosUsuario < (int)$avatar['puntos_requeridos']) {
            throw new RuntimeException('Aún no tienes suficientes puntos para desbloquear este avatar.');
        }

        $st = $conexion->prepare("
            UPDATE usuarios
            SET id_avatar = ?, avatar = ?
            WHERE id_usuario = ?
        ");
        $st->execute([$idAvatar, $avatar['imagen'], $idUsuario]);

        $mensaje = 'Tu avatar se actualizó correctamente.';
    }
} catch (Throwable $e) {
    if ($e instanceof RuntimeException) {
        $mensaje = $e->getMessage();
        $tipoMensaje = 'danger';
    } else {
        $mensaje = 'No fue posible actualizar el perfil.';
        $tipoMensaje = 'danger';
    }
}

try {
    $st = $conexion->prepare("
        SELECT
            u.id_usuario, u.nombres, u.apellidos, u.correo, u.grado,
            u.avatar, u.id_avatar, u.puntos, u.nivel, u.fecha_registro,
            a.nombre AS avatar_nombre, a.imagen AS avatar_imagen,
            a.puntos_requeridos AS avatar_puntos
        FROM usuarios u
        LEFT JOIN avatares a ON a.id_avatar = u.id_avatar
        WHERE u.id_usuario = ?
        LIMIT 1
    ");
    $st->execute([$idUsuario]);
    $usuario = $st->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) redireccionarDashboardUsuario();

    $gam = obtenerGamificacionUsuario($conexion, $idUsuario);
    $puntos = (int)$gam['puntos'];
    $nivel = $gam['nivel'];
    $avanceNivel = (float)$gam['progreso_nivel'];
    $siguientePuntos = $gam['puntos_siguiente_nivel'];
} catch (Throwable $e) {
    die('No fue posible cargar el perfil.');
}

$avatares = [];
try {
    $st = $conexion->query("
        SELECT id_avatar, nombre, imagen, puntos_requeridos
        FROM avatares
        WHERE estado = 'Activo'
        ORDER BY puntos_requeridos ASC, id_avatar ASC
    ");
    $avatares = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$insignias = [];
try {
    $st = $conexion->prepare("
        SELECT
            i.id_insignia, i.nombre, i.descripcion, i.imagen,
            i.criterio, i.puntos_otorgados,
            CASE WHEN ui.id_usuario IS NULL THEN 0 ELSE 1 END AS obtenida,
            ui.fecha AS fecha_obtenida
        FROM insignias i
        LEFT JOIN usuarios_insignias ui
          ON ui.id_insignia = i.id_insignia
         AND ui.id_usuario = ?
        WHERE i.estado = 'Activa'
        ORDER BY obtenida DESC, i.id_insignia ASC
    ");
    $st->execute([$idUsuario]);
    $insignias = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$estadisticas = [
    'temas_iniciados' => 0,
    'temas_completados' => 0,
    'progreso_promedio' => 0,
    'recursos_vistos' => 0,
];
try {
    $st = $conexion->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN porcentaje_avance > 0 THEN id_tema END) temas_iniciados,
            COUNT(DISTINCT CASE WHEN porcentaje_avance >= 100 THEN id_tema END) temas_completados,
            COALESCE(ROUND(AVG(porcentaje_avance),0),0) progreso_promedio,
            COALESCE(SUM(recursos_vistos),0) recursos_vistos
        FROM progreso
        WHERE id_usuario = ?
    ");
    $st->execute([$idUsuario]);
    $estadisticas = array_merge($estadisticas, $st->fetch(PDO::FETCH_ASSOC) ?: []);
} catch (Throwable $e) {}

$notificacionesNoLeidas = 0;
try {
    $stN = $conexion->prepare("SELECT COUNT(*) FROM solicitudes_recuperacion WHERE id_usuario=? AND estado='Gestionada' AND mensaje_admin IS NOT NULL AND mensaje_admin<>''");
    $stN->execute([$idUsuario]);
    $notificacionesNoLeidas = (int)$stN->fetchColumn();
} catch (Throwable $e) {}

$notificacionesSugerencias = 0;
try {
    $stN = $conexion->prepare("SELECT COUNT(*) FROM sugerencias WHERE id_usuario=? AND respuesta IS NOT NULL AND respuesta<>'' AND estado IN ('Respondida','Cerrada')");
    $stN->execute([$idUsuario]);
    $notificacionesSugerencias = (int)$stN->fetchColumn();
} catch (Throwable $e) {}
$notificacionesTotal = $notificacionesNoLeidas + $notificacionesSugerencias;

$nombreCompleto = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
$iniciales = '';
foreach (preg_split('/\s+/', $nombreCompleto) as $p) {
    if ($p !== '') $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
    if (mb_strlen($iniciales) >= 2) break;
}
$avatarPrincipal = recursoImagen($usuario['avatar_imagen'] ?: $usuario['avatar']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi perfil | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#2467c5;--dark:#173f80;--soft:#eef5ff;--border:#dce5f0;--text:#26364a}
body{background:radial-gradient(circle at top right,#eaf3ff,transparent 28%),#f4f7fb;color:var(--text)}
.navbar{background:linear-gradient(100deg,var(--dark),var(--blue));box-shadow:0 6px 24px #173f8025}
.hero{border-radius:26px;padding:2rem;color:#fff;background:linear-gradient(120deg,#2467c5,#173f80);box-shadow:0 18px 45px #173f8020}
.avatar{width:125px;height:125px;border-radius:50%;overflow:hidden;background:#e8f1ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:2.4rem;font-weight:800;border:5px solid #ffffffd9}
.avatar img{width:100%;height:100%;object-fit:cover}
.cardx{background:#fff;border:1px solid var(--border);border-radius:21px;box-shadow:0 10px 30px #1f395c10}
.stat{border:1px solid var(--border);border-radius:17px;padding:1rem;background:#fbfdff;height:100%}
.stat i{width:42px;height:42px;border-radius:13px;background:var(--soft);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.progress{height:12px;border-radius:99px}.progress-bar{background:linear-gradient(90deg,#2467c5,#6aa2f3)}
.avatar-option{border:2px solid var(--border);border-radius:18px;padding:1rem;background:#fff;height:100%;transition:.2s}
.avatar-option:hover{transform:translateY(-2px);box-shadow:0 10px 24px #2467c51a}.avatar-option.selected{border-color:var(--blue)}
.avatar-img{width:72px;height:72px;border-radius:50%;overflow:hidden;background:#eef5ff;margin:auto;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:1.7rem;font-weight:800}
.avatar-img img{width:100%;height:100%;object-fit:cover}
.badge-card{border:1px solid var(--border);border-radius:17px;padding:1rem;height:100%}.badge-card.locked{opacity:.5;filter:grayscale(1)}
.badge-img{width:54px;height:54px;border-radius:15px;background:#fff5d9;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b78100;font-size:1.5rem}
.badge-img img{width:100%;height:100%;object-fit:cover}
</style>
<style>
.sd-title-emblem{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:14px;background:#f7f8fa;border:1px solid #e6ebf2;color:#697689}
.sd-title-emblem.earned{background:#fff8df;border-color:#efdfaa;color:#73590f}
.sd-emblem-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:#fff;color:#9aa5b4;border:1px solid #e5e9ef}
.sd-title-emblem.earned .sd-emblem-icon{color:#b78310;background:#fffdf4}
.sd-title-emblem small{font-size:.62rem;color:#8a94a3}
body.sd-dark .sd-title-emblem{background:#202d42;border-color:#2e3a4f;color:#aeb9c8}
body.sd-dark .sd-title-emblem.earned{background:#3b3320;border-color:#65552c;color:#eadb9a}
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
<style>
.sd-notification-btn{position:relative;width:38px;height:38px;border-radius:11px;border:1px solid var(--sd-line);background:var(--sd-card);color:var(--sd-muted);display:grid;place-items:center;text-decoration:none;transition:.18s}
.sd-notification-btn:hover{color:var(--sd-accent);background:var(--sd-accent-soft)}
.sd-notification-btn span{position:absolute;right:-3px;top:-4px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#ef4444;color:#fff;font-size:9px;display:grid;place-items:center;border:2px solid var(--sd-card)}
.sd-password-card{position:relative;overflow:hidden}
.sd-password-card:after{content:"";position:absolute;width:160px;height:160px;right:-80px;bottom:-100px;border:24px solid color-mix(in srgb,var(--sd-accent) 6%,transparent);border-radius:50%;pointer-events:none}
</style>
<link rel="stylesheet" href="studia360-estudiante.css">
<style id="profile-final-overrides">
body.sd-page .navbar{background:var(--sd-card)!important;box-shadow:0 5px 24px rgba(31,55,86,.035)!important;border-bottom:1px solid var(--sd-line)!important}
body.sd-page .hero{background:linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2))!important;box-shadow:0 18px 44px color-mix(in srgb,var(--sd-accent) 15%,transparent)!important}
body.sd-page .badge-card.locked{opacity:1!important;filter:none!important;background:var(--sd-card)!important}
body.sd-page .badge-card.locked .sd-title-emblem{opacity:.68;filter:grayscale(.7)}
body.sd-page .badge-card.locked .sd-title-emblem *{color:inherit!important}
body.sd-page .sd-password-card .form-label{color:var(--sd-muted)!important}
</style>

<style id="studia360-perfil-email-final">
body.sd-page .cardx{background:var(--sd-card)!important;color:var(--sd-text)!important;border-color:var(--sd-line)!important}
body.sd-page .cardx .form-control{background:var(--sd-card)!important;color:var(--sd-text)!important;border-color:var(--sd-line)!important}
body.sd-dark .cardx .form-control{background:#111827!important;color:#edf2f7!important;border-color:#2a3549!important}
body.sd-dark .cardx .form-control::placeholder{color:#718096!important}
@media(max-width:575px){
  #correo + .btn, .sd-password-card .btn{width:100%}
}
</style>

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
      <a href="recuperacion.php" class="sd-notification-btn" title="Notificaciones">
        <i class="bi bi-bell"></i>
        <?php if($notificacionesTotal>0): ?><span><?=min(99,$notificacionesTotal)?></span><?php endif; ?>
      </a>
      <a href="perfil.php" class="sd-avatar-mini d-none d-sm-grid" title="Mi perfil">
        <?php if (!empty($avatarPrincipal)): ?><img src="<?=h($avatarPrincipal)?>" alt="Mi perfil"><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
      </a>
      <a href="perfil.php" class="btn sd-nav-btn d-none d-md-inline-flex"><i class="bi bi-person me-1"></i>Perfil</a>
      <a href="../cerrar_sesion.php" class="btn sd-nav-btn" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Salir</span></a>
    </div>
  </div>
</nav>

<main class="container py-4 py-lg-5">
<section class="hero mb-4">
<div class="row align-items-center g-4">
<div class="col-auto"><div class="avatar"><?php if($avatarPrincipal):?><img src="<?=h($avatarPrincipal)?>" alt="Avatar"><?php else:?><?=h($iniciales)?><?php endif;?></div>
<form class="mt-2 text-center" method="post" action="<?=h(urlAplicacion('/estudiante/perfil_accion.php'))?>" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf_perfil_accion'] ?? '')?>">
<input type="hidden" name="accion" value="foto">
<label class="btn btn-light btn-sm" style="cursor:pointer"><i class="bi bi-camera me-1"></i>Cambiar foto
<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()">
</label>
</form></div>
<div class="col"><div class="text-uppercase small opacity-75">Mi perfil</div><h1 class="display-6 fw-bold mb-1"><?=h($nombreCompleto)?></h1>
<p class="mb-2 text-white-50">Estudiante · Grado <?=h($usuario['grado'])?></p>
<div class="d-flex flex-wrap gap-2"><span class="badge text-bg-light"><?=number_format($puntos)?> puntos</span><span class="badge bg-white text-primary">Nivel <?=h($nivel['id_nivel'])?> · <?=h($nivel['nombre'])?></span></div></div>
</div></section>

<?php if($mensaje):?><div class="alert alert-<?=$tipoMensaje?>"><?=h($mensaje)?></div><?php endif;?>

<div class="row g-4 mb-4">
<div class="col-lg-7"><section class="cardx p-4 h-100">
<h2 class="h5 fw-bold"><i class="bi bi-stars me-2 text-primary"></i>Tu progreso de nivel</h2>
<div class="d-flex justify-content-between align-items-end mt-3"><div><div class="h3 fw-bold mb-0"><?=h($nivel['nombre'])?></div><small class="text-muted"><?=number_format($puntos)?> puntos acumulados</small></div><strong><?=number_format($avanceNivel,0)?>%</strong></div>
<div class="progress mt-3"><div class="progress-bar" style="width:<?=h((string)$avanceNivel)?>%"></div></div>
<div class="small text-muted mt-2"><?php if($siguientePuntos!==null):?>Te faltan <strong><?=number_format(max(0,(int)$siguientePuntos-$puntos))?></strong> puntos para el siguiente nivel.<?php else:?>¡Has alcanzado el nivel máximo configurado!<?php endif;?></div>
<p class="text-muted mt-3 mb-0"><?=h($nivel['descripcion'] ?? 'Sigue aprendiendo y acumulando puntos.')?></p>
</section></div>
<div class="col-lg-5"><section class="cardx p-4 h-100"><h2 class="h5 fw-bold mb-3"><i class="bi bi-person-vcard me-2 text-primary"></i>Datos de cuenta</h2>
<div class="mb-3">
  <label for="correo" class="form-label small text-muted mb-1">Correo electrónico</label>
  <form method="post" action="<?=h(urlAplicacion('/estudiante/perfil_accion.php'))?>" class="d-flex gap-2 flex-wrap">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf_perfil_accion'] ?? '')?>">
    <input type="hidden" name="accion" value="actualizar_correo">
    <input type="email" id="correo" name="correo" class="form-control flex-grow-1" maxlength="120" autocomplete="email" value="<?=h($usuario['correo'])?>" required>
    <button type="submit" class="btn sd-btn sd-btn-primary"><i class="bi bi-envelope-check me-1"></i>Actualizar</button>
  </form>
</div>
<div class="mb-3"><small class="text-muted">Grado</small><div class="fw-semibold"><?=h($usuario['grado'])?></div></div>
<div><small class="text-muted">Puntos</small><div class="fw-semibold"><?=number_format($puntos)?></div></div>
</section></div>
</div>

<section class="cardx p-4 mb-4"><h2 class="h5 fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Resumen de aprendizaje</h2>
<div class="row g-3">
<?php
$stats=[
['bi-book','Temas iniciados',$estadisticas['temas_iniciados']],
['bi-check2-circle','Temas completados',$estadisticas['temas_completados']],
['bi-bar-chart','Progreso promedio',$estadisticas['progreso_promedio'].'%'],
['bi-collection-play','Recursos vistos',$estadisticas['recursos_vistos']],
];
foreach($stats as $s):?>
<div class="col-6 col-lg"><div class="stat"><i class="bi <?=$s[0]?> mb-2"></i><div class="small text-muted"><?=$s[1]?></div><div class="h4 fw-bold mb-0"><?=h((string)$s[2])?></div></div></div>
<?php endforeach;?>
</div></section>

<section class="cardx p-4 mb-4 sd-password-card">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <h2 class="h5 fw-bold mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Seguridad de la cuenta</h2>
      <p class="text-muted mb-0 small">Puedes cambiar tu contraseña directamente desde tu perfil.</p>
    </div>
    <a href="recuperacion.php" class="btn sd-btn sd-btn-outline"><i class="bi bi-bell me-1"></i>Ver notificaciones</a>
  </div>
  <form method="post" action="<?=h(urlAplicacion('/estudiante/perfil_accion.php'))?>" class="row g-3 mt-1">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <input type="hidden" name="accion" value="cambiar_password">
    <div class="col-12 col-md-4"><label class="form-label">Contraseña actual</label><input type="password" name="password_actual" class="form-control" autocomplete="current-password" required></div>
    <div class="col-12 col-md-4"><label class="form-label">Nueva contraseña</label><input type="password" name="password_nueva" class="form-control" minlength="6" autocomplete="new-password" required></div>
    <div class="col-12 col-md-4"><label class="form-label">Confirmar contraseña</label><input type="password" name="password_confirmar" class="form-control" minlength="6" autocomplete="new-password" required></div>
    <div class="col-12 d-flex justify-content-end"><button class="btn sd-btn sd-btn-primary"><i class="bi bi-check2 me-1"></i>Actualizar contraseña</button></div>
  </form>
</section>

<section class="cardx p-4 mb-4"><h2 class="h5 fw-bold mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Avatares</h2><p class="text-muted">Desbloquea avatares con tus puntos y selecciona el que represente tu progreso.</p>
<div class="row g-3">
<?php foreach($avatares as $a):
$desbloqueado=$puntos >= (int)$a['puntos_requeridos'];
$seleccionado=(int)$usuario['id_avatar']===(int)$a['id_avatar'];
$img=recursoImagen($a['imagen']);
?>
<div class="col-6 col-md-4 col-lg-3"><div class="avatar-option <?=$seleccionado?'selected':''?>">
<div class="avatar-img"><?php if($img):?><img src="<?=h($img)?>" alt="<?=h($a['nombre'])?>"><?php else:?><i class="bi bi-person"></i><?php endif;?></div>
<div class="text-center mt-2 fw-bold"><?=h($a['nombre'])?></div>
<div class="text-center small text-muted mb-3"><?=number_format((int)$a['puntos_requeridos'])?> puntos</div>
<?php if($desbloqueado):?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="cambiar_avatar"><input type="hidden" name="id_avatar" value="<?=$a['id_avatar']?>">
<button class="btn btn-sm <?=$seleccionado?'btn-primary':'btn-outline-primary'?> w-100"><?=$seleccionado?'Avatar actual':'Seleccionar'?></button></form>
<?php else:?><button class="btn btn-sm btn-outline-secondary w-100" disabled><i class="bi bi-lock me-1"></i>Bloqueado</button><?php endif;?>
</div></div>
<?php endforeach;?>
</div>
</section>

<section class="cardx p-4"><h2 class="h5 fw-bold mb-3"><i class="bi bi-award me-2 text-primary"></i>Insignias y coleccionables</h2>
<div class="row g-3">
<?php if(!$insignias):?><div class="text-muted">Aún no hay insignias configuradas.</div><?php endif;?>
<?php foreach($insignias as $i):$img=recursoImagen($i['imagen'],'insignias');$ob=(int)$i['obtenida']===1;?>
<div class="col-12 col-md-6 col-lg-4"><div class="badge-card <?=$ob?'':'locked'?>">
<div class="sd-title-emblem <?=$ob?'earned':''?>">
  <span class="sd-emblem-icon"><i class="bi bi-award-fill"></i></span>
  <div><div class="fw-bold"><?=h($i['nombre'])?></div><small><?=$ob?'Conseguida':'Aún no conseguida'?></small></div>
</div>
<p class="small text-muted mt-3 mb-1"><?=h($i['descripcion'])?></p>
<?php if(!empty($i['criterio'])):?><div class="small"><strong>Criterio:</strong> <?=h($i['criterio'])?></div><?php endif;?>
</div></div>
<?php endforeach;?>
</div></section>
</main>

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
</body></html>

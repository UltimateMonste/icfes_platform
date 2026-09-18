<?php

require_once __DIR__ . "/../includes/seguridad.php";

exigirAdmin();


// =====================================================
// VARIABLES PRINCIPALES
// =====================================================

$total_estudiantes = 0;
$total_materias = 0;
$total_temas = 0;
$total_temas_contenido = 0;
$total_temas_sin_contenido = 0;
$total_recursos = 0;
$total_sugerencias = 0;
$total_recuperaciones_pendientes = 0;
$total_unidades = 0;
$total_cursos = 0;
$total_estudiantes_activos = 0;
$temas_sin_unidad = 0;
$progreso_promedio = 0;
$ultimas_actividades = [];
$hay_unidades = false;
$unidades_dashboard = [];

$temas_por_grado = [
    "9" => 0,
    "10" => 0,
    "11" => 0
];

$contenido_por_grado = [
    "9" => 0,
    "10" => 0,
    "11" => 0
];

$error_estadisticas = false;


// =====================================================
// DATOS DEL ADMINISTRADOR
// =====================================================

$nombresAdmin =
    trim($_SESSION["nombres"] ?? "");

$apellidosAdmin =
    trim($_SESSION["apellidos"] ?? "");

$nombreCompleto =
    trim(
        $nombresAdmin . " " . $apellidosAdmin
    );

if ($nombreCompleto === "") {

    $nombreCompleto = "Administrador";

}

$primerNombre =
    explode(
        " ",
        $nombresAdmin
    )[0] ?? "Administrador";

if ($primerNombre === "") {

    $primerNombre = "Administrador";

}

// Foto de perfil del administrador
$avatarAdmin = "";
try {
    $stAvatarAdmin = $conexion->prepare("SELECT avatar FROM usuarios WHERE id_usuario = ? AND id_rol = 1 LIMIT 1");
    $stAvatarAdmin->execute([(int)($_SESSION["id_usuario"] ?? 0)]);
    $avatarAdmin = (string)($stAvatarAdmin->fetchColumn() ?: "");
} catch (Throwable $e) {
    $avatarAdmin = "";
}

function fotoAdminDashboard(?string $foto): string
{
    $foto = trim((string)$foto);
    if ($foto === "") return "";
    if (preg_match("#^https?://#i", $foto)) return $foto;
    $foto = str_replace("\\", "/", ltrim($foto, "/"));
    if (str_starts_with($foto, "assets/")) return urlAplicacion("/" . $foto);
    if (str_starts_with($foto, "uploads/")) return urlAplicacion("/assets/" . $foto);
    return urlAplicacion("/assets/uploads/perfiles/" . basename($foto));
}
$avatarAdminUrl = fotoAdminDashboard($avatarAdmin);


// =====================================================
// ESTADÍSTICAS
// =====================================================

try {


    // -------------------------------------------------
    // ESTUDIANTES
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM usuarios
            WHERE id_rol = 2
            "
        );

    $total_estudiantes =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // MATERIAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM materias
            "
        );

    $total_materias =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM temas
            "
        );

    $total_temas =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS CON CONTENIDO
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM temas
            WHERE contenido IS NOT NULL
            AND TRIM(contenido) <> ''
            "
        );

    $total_temas_contenido =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS SIN CONTENIDO
    // -------------------------------------------------

    $total_temas_sin_contenido =
        max(
            0,
            $total_temas -
            $total_temas_contenido
        );


    // -------------------------------------------------
    // RECURSOS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM recursos
            "
        );

    $total_recursos =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // SUGERENCIAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM sugerencias
            "
        );

    $total_sugerencias =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // RECUPERACIONES DE CONTRASEÑA PENDIENTES
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM solicitudes_recuperacion
            WHERE estado = 'Pendiente'
            "
        );

    $total_recuperaciones_pendientes =
        (int)$consulta->fetchColumn();


    // =================================================
    // TEMAS POR GRADO
    // =================================================

    $consulta =
        $conexion->query(
            "
            SELECT
                grado,
                COUNT(*) AS cantidad
            FROM temas
            WHERE grado IN ('9', '10', '11')
            GROUP BY grado
            "
        );


    while (
        $fila =
        $consulta->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        $grado =
            (string)$fila["grado"];


        if (
            isset(
                $temas_por_grado[$grado]
            )
        ) {

            $temas_por_grado[$grado] =
                (int)$fila["cantidad"];

        }

    }


    // =================================================
    // CONTENIDO POR GRADO
    // =================================================

    $consulta =
        $conexion->query(
            "
            SELECT
                grado,
                COUNT(*) AS cantidad
            FROM temas
            WHERE grado IN ('9', '10', '11')
            AND contenido IS NOT NULL
            AND TRIM(contenido) <> ''
            GROUP BY grado
            "
        );


    while (
        $fila =
        $consulta->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        $grado =
            (string)$fila["grado"];


        if (
            isset(
                $contenido_por_grado[$grado]
            )
        ) {

            $contenido_por_grado[$grado] =
                (int)$fila["cantidad"];

        }

    }


} catch (PDOException $e) {

    $error_estadisticas = true;

}


// =====================================================
// ESTRUCTURA ACADÉMICA Y ACTIVIDAD RECIENTE
// =====================================================

try {
    $st = $conexion->query("SHOW TABLES LIKE 'unidades_tematicas'");
    $hay_unidades = (bool)$st->fetchColumn();

    if ($hay_unidades) {
        $total_unidades = (int)$conexion->query("SELECT COUNT(*) FROM unidades_tematicas")->fetchColumn();

        // Unidades reales para conectar el panel con la nueva jerarquía académica.
        $stUnidades = $conexion->query("
            SELECT
                ut.id_unidad,
                ut.id_materia,
                ut.nombre,
                COUNT(t.id_tema) AS total_temas
            FROM unidades_tematicas ut
            LEFT JOIN temas t ON t.id_unidad = ut.id_unidad
            GROUP BY ut.id_unidad, ut.id_materia, ut.nombre
            ORDER BY ut.id_materia ASC, ut.nombre ASC
            LIMIT 8
        ");
        $unidades_dashboard = $stUnidades->fetchAll(PDO::FETCH_ASSOC);
    }

    $total_cursos = (int)$conexion->query("SELECT COUNT(*) FROM cursos WHERE estado='Activo'")->fetchColumn();
    $total_estudiantes_activos = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE id_rol=2 AND estado='Activo'")->fetchColumn();

    $stCol = $conexion->query("SHOW COLUMNS FROM temas LIKE 'id_unidad'");
    $tiene_id_unidad = (bool)$stCol->fetch(PDO::FETCH_ASSOC);

    if ($tiene_id_unidad) {
        $temas_sin_unidad = (int)$conexion->query("SELECT COUNT(*) FROM temas WHERE id_unidad IS NULL")->fetchColumn();
    }

    $stProm = $conexion->query("SELECT COALESCE(AVG(porcentaje_avance),0) FROM progreso WHERE porcentaje_avance IS NOT NULL");
    $progreso_promedio = round((float)$stProm->fetchColumn(), 1);

    $stAct = $conexion->query("SELECT p.ultima_actividad,p.porcentaje_avance,u.nombres,u.apellidos,t.nombre AS tema,m.nombre AS materia FROM progreso p INNER JOIN usuarios u ON u.id_usuario=p.id_usuario INNER JOIN temas t ON t.id_tema=p.id_tema INNER JOIN materias m ON m.id_materia=t.id_materia WHERE p.ultima_actividad IS NOT NULL ORDER BY p.ultima_actividad DESC LIMIT 6");
    $ultimas_actividades = $stAct->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // El dashboard continúa funcionando aunque una instalación todavía no tenga alguna estructura opcional.
}


// =====================================================
// PORCENTAJE DE CONTENIDO
// =====================================================

$porcentaje_contenido = 0;

if ($total_temas > 0) {

    $porcentaje_contenido =
        round(
            (
                $total_temas_contenido /
                $total_temas
            ) * 100
        );

}


// =====================================================
// FUNCIÓN DE ESCAPE
// =====================================================

function e($valor): string
{

    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );

}

function fechaActividadDashboard(?string $fecha): string
{
    if (!$fecha) return "Sin actividad";
    $ts = strtotime($fecha);
    return $ts ? date("d/m/Y · H:i", $ts) : "Sin actividad";
}

function inicialesDashboard(string $nombres, string $apellidos): string
{
    $a = trim($nombres);
    $b = trim($apellidos);
    return strtoupper((substr($a, 0, 1) ?: "") . (substr($b, 0, 1) ?: ""));
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Panel de administración de Studia360">
<title>Dashboard | Studia360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">

<style>
:root{
    --blue:#2563eb;
    --blue-2:#3b82f6;
    --blue-soft:#eff6ff;
    --green:#10b981;
    --green-soft:#ecfdf5;
    --purple:#8b5cf6;
    --purple-soft:#f5f3ff;
    --orange:#f59e0b;
    --orange-soft:#fffbeb;
    --cyan:#06b6d4;
    --cyan-soft:#ecfeff;
    --red:#ef4444;
    --ink:#172033;
    --muted:#748094;
    --line:#e8edf4;
    --surface:#fff;
    --canvas:#f7f9fc;
    --nav:#fff;
    --shadow:0 8px 30px rgba(30,55,90,.055);
    --shadow-hover:0 14px 34px rgba(30,55,90,.09);
    --radius:18px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    background:
        radial-gradient(circle at 85% 0%,rgba(219,234,254,.65),transparent 28rem),
        var(--canvas);
    color:var(--ink);
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    font-size:14px;
}
a{text-decoration:none}
button,input{font:inherit}

/* ---------- SIDEBAR ---------- */
.sidebar{
    position:fixed; inset:0 auto 0 0; width:264px;
    background:rgba(255,255,255,.96);
    border-right:1px solid var(--line);
    z-index:1050;
    display:flex; flex-direction:column;
    transition:width .25s ease,transform .25s ease;
    backdrop-filter:blur(14px);
}
.brand{
    height:78px; padding:0 20px;
    display:flex; align-items:center;
    border-bottom:1px solid var(--line);
}
.brand-link{display:flex;align-items:center;gap:11px;color:var(--ink);min-width:0}
.brand-mark{
    width:42px;height:42px;border-radius:13px;
    display:grid;place-items:center;color:#fff;
    background:linear-gradient(145deg,#3b82f6,#1d4ed8);
    box-shadow:0 8px 20px rgba(37,99,235,.2);
    flex:none;
}
.brand-name{font-weight:800;font-size:1.08rem;letter-spacing:-.03em}
.brand-sub{font-size:.68rem;color:#96a0b0;margin-top:1px}
.sidebar-tools{padding:13px 12px 7px}
.nav-search{
    height:40px;border:1px solid var(--line);border-radius:12px;
    background:#f8fafc;color:var(--ink);width:100%;padding:0 12px 0 38px;
    outline:none;transition:.2s;
}
.nav-search:focus{border-color:#bfdbfe;background:#fff;box-shadow:0 0 0 4px rgba(37,99,235,.07)}
.search-wrap{position:relative}
.search-wrap i{position:absolute;left:13px;top:11px;color:#9aa4b2}
.sidebar-nav{padding:8px 12px 16px;overflow:auto;flex:1}
.nav-section{margin-top:12px}
.nav-section:first-child{margin-top:2px}
.nav-section-title{
    padding:7px 10px;color:#a0a9b7;font-size:.65rem;
    font-weight:800;text-transform:uppercase;letter-spacing:.1em;
}
.side-link{
    display:flex;align-items:center;gap:10px;
    min-height:40px;padding:8px 10px;margin:2px 0;
    border-radius:11px;color:#5f6b7c;font-size:.82rem;font-weight:600;
    transition:background .18s,color .18s,transform .18s;
}
.side-link i{width:20px;text-align:center;font-size:1rem;color:#8b97a8}
.side-link:hover{background:#f5f8fc;color:var(--ink);transform:translateX(2px)}
.side-link:hover i{color:var(--blue)}
.side-link.active{background:var(--blue-soft);color:var(--blue)}
.side-link.active i{color:var(--blue)}
.side-badge{margin-left:auto;font-size:.62rem}
.side-divider{height:1px;background:var(--line);margin:10px 10px}
.sidebar-footer{padding:12px;border-top:1px solid var(--line)}
.profile-mini{
    display:flex;align-items:center;gap:10px;padding:9px;
    border-radius:13px;color:var(--ink);
}
.profile-mini:hover{background:#f7f9fc}
.avatar{
    width:38px;height:38px;border-radius:50%;overflow:hidden;flex:none;
    background:#eef2f7;color:#64748b;display:grid;place-items:center;
}
.avatar img{width:100%;height:100%;object-fit:cover}
.profile-mini-name{font-size:.78rem;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.profile-mini-role{font-size:.67rem;color:#929cac}
.collapse-btn{
    width:30px;height:30px;border:0;border-radius:9px;background:#f4f7fb;color:#738096;
    display:grid;place-items:center;flex:none;
}
.collapse-btn:hover{background:var(--blue-soft);color:var(--blue)}

/* collapsed desktop sidebar */
body.sidebar-collapsed .sidebar{width:82px}
body.sidebar-collapsed .main{margin-left:82px}
body.sidebar-collapsed .brand{padding:0 20px}
body.sidebar-collapsed .brand-copy,
body.sidebar-collapsed .nav-section-title,
body.sidebar-collapsed .side-link span,
body.sidebar-collapsed .profile-copy,
body.sidebar-collapsed .search-wrap input{display:none}
body.sidebar-collapsed .sidebar-tools{padding:13px 12px 7px}
body.sidebar-collapsed .search-wrap{height:40px}
body.sidebar-collapsed .search-wrap i{left:12px}
body.sidebar-collapsed .side-link{justify-content:center;padding:8px}
body.sidebar-collapsed .side-link i{width:auto;font-size:1.08rem}
body.sidebar-collapsed .profile-mini{justify-content:center}
body.sidebar-collapsed .collapse-btn{transform:rotate(180deg)}
body.sidebar-collapsed .sidebar-footer{padding:12px 8px}

/* ---------- MAIN / TOPBAR ---------- */
.main{margin-left:264px;min-height:100vh;transition:margin-left .25s ease}
.topbar{
    height:72px;position:sticky;top:0;z-index:900;
    display:flex;align-items:center;justify-content:space-between;gap:18px;
    padding:0 30px;background:rgba(255,255,255,.88);
    border-bottom:1px solid rgba(232,237,244,.9);backdrop-filter:blur(14px);
}
.page-context{display:flex;align-items:center;gap:12px;min-width:0}
.mobile-menu{display:none}
.context-dot{width:8px;height:8px;border-radius:50%;background:#34d399;box-shadow:0 0 0 5px #ecfdf5}
.context-title{font-size:.84rem;font-weight:750}
.context-sub{font-size:.68rem;color:#9aa3b2}
.top-actions{display:flex;align-items:center;gap:9px}
.top-action{
    height:38px;border:1px solid var(--line);background:#fff;border-radius:11px;
    color:#647084;display:inline-flex;align-items:center;gap:7px;padding:0 11px;
}
.top-action:hover{background:#f8fafc;color:var(--blue)}
.top-profile{display:flex;align-items:center;gap:9px;padding-left:4px}
.top-profile .avatar{width:34px;height:34px}
.top-profile-name{font-size:.76rem;font-weight:700}
.top-profile-role{font-size:.64rem;color:#98a1af}

/* ---------- CONTENT ---------- */
.content{max-width:1480px;margin:auto;padding:28px 30px 40px}
.hero{
    position:relative;overflow:hidden;border-radius:24px;
    padding:28px 30px;color:#fff;
    background:
        radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 18rem),
        linear-gradient(125deg,#2563eb,#3b82f6);
    box-shadow:0 16px 38px rgba(37,99,235,.16);
}
.hero:after{
    content:"";position:absolute;width:170px;height:170px;border-radius:50%;
    right:-45px;bottom:-75px;border:25px solid rgba(255,255,255,.07);
}
.hero-kicker{font-size:.66rem;text-transform:uppercase;letter-spacing:.12em;font-weight:800;opacity:.78}
.hero h1{font-size:clamp(1.55rem,2.4vw,2rem);font-weight:800;letter-spacing:-.035em;margin:6px 0 6px}
.hero p{max-width:670px;margin:0;color:rgba(255,255,255,.82);line-height:1.6;font-size:.83rem}
.hero-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.btn-soft-white{
    border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#fff;
    border-radius:10px;padding:8px 12px;font-size:.76rem;font-weight:700;
}
.btn-soft-white:hover{background:#fff;color:var(--blue)}
.btn-white{background:#fff;color:var(--blue);border:0;border-radius:10px;padding:8px 13px;font-size:.76rem;font-weight:750}
.btn-white:hover{background:#f8fafc;color:#1d4ed8}

/* ---------- SECTION HEAD ---------- */
.section{margin-top:25px}
.section-head{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-bottom:12px}
.section-title{font-size:.98rem;font-weight:800;letter-spacing:-.02em;margin:0}
.section-sub{font-size:.72rem;color:var(--muted);margin:3px 0 0}
.section-link{font-size:.72rem;color:var(--blue);font-weight:700}
.section-link:hover{text-decoration:underline}

/* ---------- STATS ---------- */
.stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:15px}
.stat-col{min-width:0}
.stat-unit .stat-label{line-height:1.25}
@media (max-width:1199px){.stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:767px){.stat-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}}
@media (max-width:480px){.stat-grid{grid-template-columns:1fr}}
.stat{
    background:var(--surface);border:1px solid var(--line);border-radius:17px;
    box-shadow:var(--shadow);height:100%;overflow:hidden;transition:.2s;
}
.stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover)}
.stat-body{padding:17px 17px 15px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.stat-label{font-size:.7rem;color:#8993a2;font-weight:700;margin-bottom:7px}
.stat-number{font-size:1.7rem;line-height:1;font-weight:800;letter-spacing:-.04em}
.stat-note{font-size:.66rem;color:#9aa3b0;margin-top:7px}
.stat-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1.05rem}
.stat-foot{border-top:1px solid #f0f2f6;padding:8px 17px;font-size:.67rem}
.stat-foot a{color:#657186;font-weight:700}
.stat-foot a:hover{color:var(--blue)}
.blue{background:var(--blue-soft);color:var(--blue)}
.green{background:var(--green-soft);color:#059669}
.orange{background:var(--orange-soft);color:#d97706}
.cyan{background:var(--cyan-soft);color:#0891b2}
.purple{background:var(--purple-soft);color:#7c3aed}
.red{background:#fef2f2;color:#dc2626}

/* ---------- PANELS ---------- */
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);height:100%}
.panel-body{padding:19px}
.progress-number{font-size:1.9rem;font-weight:800;letter-spacing:-.04em}
.progress-caption{font-size:.66rem;color:#9aa3b0}
.progress-track{height:8px;background:#edf1f6;border-radius:20px;overflow:hidden}
.progress-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#2563eb,#60a5fa)}
.mini-box{border:1px solid var(--line);background:#fbfcfe;border-radius:13px;padding:12px}
.mini-value{font-size:1.1rem;font-weight:800}
.mini-label{font-size:.65rem;color:#8d97a5;margin-top:2px}
.grade{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    border:1px solid var(--line);border-radius:13px;padding:11px 13px;
}
.grade:hover{background:#fafcff;border-color:#dbe7fa}
.grade-num{font-size:1.05rem;font-weight:800}
.grade-info{font-size:.64rem;color:#8993a1;margin-top:2px}
.grade-link{font-size:.68rem;font-weight:750;color:var(--blue)}

/* ---------- QUICK LINKS ---------- */
.quick{
    display:flex;align-items:center;gap:11px;height:100%;padding:12px;
    border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);
    transition:.2s;
}
.quick:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:#d7e4f7;color:var(--ink)}
.quick-icon{width:39px;height:39px;border-radius:11px;display:grid;place-items:center;flex:none}
.quick-title{font-size:.76rem;font-weight:800}
.quick-text{font-size:.63rem;color:#9099a7;margin-top:2px;line-height:1.4}

/* ---------- MODULES ---------- */
.module{
    display:block;height:100%;padding:15px;border:1px solid var(--line);border-radius:15px;
    background:#fff;color:var(--ink);transition:.2s;
}
.module:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:#d9e5f6;color:var(--ink)}
.module-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;margin-bottom:12px}
.module-title{font-size:.77rem;font-weight:800}
.module-description{font-size:.65rem;color:#8a94a3;line-height:1.5;margin:5px 0 12px;min-height:30px}
.status{font-size:.6rem!important;padding:5px 8px!important}

/* ---------- FINAL ---------- */
.final-card{
    border-radius:20px;padding:21px 22px;color:#fff;
    background:linear-gradient(125deg,#173f80,#2563eb);
    box-shadow:0 14px 34px rgba(23,63,128,.14);
}
.final-icon{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.13);display:grid;place-items:center;font-size:1.1rem}
.final-title{font-weight:800;font-size:.9rem}
.final-text{font-size:.7rem;color:rgba(255,255,255,.72);line-height:1.55;margin:4px 0 0}

/* ---------- EMPTY / ALERT ---------- */
.dashboard-alert{border:0;border-radius:13px;font-size:.75rem}

/* ---------- DASHBOARD PRO ---------- */
.control-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:12px;align-items:start}
.insight{height:auto;padding:18px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}
.insight-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.insight-title{font-size:.84rem;font-weight:800;margin:0}.insight-sub{font-size:.66rem;color:var(--muted);margin:3px 0 0}
.route{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;align-items:center}
.route-step{position:relative;text-align:center;padding:12px 7px;border:1px solid var(--line);border-radius:14px;background:var(--surface)}
.route-step:not(:last-child):after{content:"→";position:absolute;right:-10px;top:50%;transform:translateY(-50%);z-index:2;width:18px;height:18px;border-radius:50%;display:grid;place-items:center;background:var(--surface);color:var(--theme);font-size:.75rem;font-weight:900}
.route-icon{width:34px;height:34px;margin:0 auto 7px;border-radius:10px;display:grid;place-items:center;background:var(--theme-soft);color:var(--theme)}
.route-name{font-size:.66rem;font-weight:800}.route-note{font-size:.57rem;color:var(--muted);margin-top:2px}
.health-list{display:flex;flex-direction:column;gap:8px}.health-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 11px;border:1px solid var(--line);border-radius:12px;background:var(--surface)}
.health-left{display:flex;align-items:center;gap:9px;min-width:0}.health-icon{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;background:var(--theme-soft);color:var(--theme);flex:none}
.health-label{font-size:.67rem;font-weight:750}.health-note{font-size:.58rem;color:var(--muted);margin-top:2px}
.activity-list{display:flex;flex-direction:column}.activity-row{display:flex;align-items:center;gap:11px;padding:11px 0;border-bottom:1px solid var(--line)}.activity-row:last-child{border-bottom:0;padding-bottom:0}
.activity-avatar{width:35px;height:35px;border-radius:11px;display:grid;place-items:center;flex:none;background:var(--theme-soft);color:var(--theme);font-size:.62rem;font-weight:850}
.activity-main{min-width:0;flex:1}.activity-name{font-size:.68rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.activity-topic{font-size:.61rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.activity-time{font-size:.57rem;color:var(--muted);text-align:right;white-space:nowrap}.activity-progress{width:65px;height:5px;border-radius:99px;background:#edf1f6;overflow:hidden;flex:none}.activity-progress span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--theme-2),var(--theme))}
.readiness{display:flex;align-items:center;gap:14px}.readiness-ring{width:78px;height:78px;border-radius:50%;display:grid;place-items:center;flex:none;background:conic-gradient(var(--theme) calc(var(--value)*1%),#edf1f6 0);position:relative}.readiness-ring:after{content:"";position:absolute;inset:7px;border-radius:50%;background:var(--surface)}.readiness-ring strong{position:relative;z-index:1;font-size:1rem}.readiness-copy{min-width:0}.readiness-copy strong{font-size:.78rem}.readiness-copy p{font-size:.63rem;color:var(--muted);line-height:1.5;margin:4px 0 0}
@media(max-width:991px){.control-grid{grid-template-columns:1fr}.route{grid-template-columns:repeat(2,1fr)}.route-step:nth-child(2):after{display:none}}
@media(max-width:575px){.route{grid-template-columns:1fr 1fr}.route-step:nth-child(2):after,.route-step:nth-child(4):after{display:none}.activity-progress{width:45px}.activity-time{display:none}}

/* ---------- UNIDADES TEMÁTICAS ---------- */
.unit-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}
.unit-card{
    display:flex;
    align-items:center;
    gap:11px;
    min-width:0;
    padding:12px;
    border:1px solid var(--line);
    border-radius:15px;
    background:var(--surface);
    color:var(--ink);
    transition:transform .2s,box-shadow .2s,border-color .2s,background .2s;
}
.unit-card:hover{
    transform:translateY(-2px);
    box-shadow:var(--shadow);
    border-color:rgba(var(--theme-rgb),.28);
    color:var(--ink);
}
.unit-icon{
    width:38px;
    height:38px;
    border-radius:11px;
    flex:none;
    display:grid;
    place-items:center;
    background:var(--theme-soft);
    color:var(--theme);
}
.unit-copy{min-width:0;flex:1}
.unit-name{
    font-size:.72rem;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.unit-meta{
    font-size:.61rem;
    color:var(--muted);
    margin-top:3px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.unit-arrow{
    color:#9aa4b2;
    font-size:.75rem;
    transition:transform .2s,color .2s;
}
.unit-card:hover .unit-arrow{
    color:var(--theme);
    transform:translateX(2px);
}
.unit-empty{
    padding:18px;
    border:1px dashed var(--line);
    border-radius:15px;
    text-align:center;
    color:var(--muted);
    font-size:.7rem;
}
@media(max-width:1199px){
    .unit-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:767px){
    .unit-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:480px){
    .unit-grid{grid-template-columns:1fr}
}

/* ---------- SCROLLBAR DEL PANEL LATERAL ---------- */
.sidebar-nav{
    scrollbar-width:thin;
    scrollbar-color:var(--theme) transparent;
    scrollbar-gutter:stable;
}
.sidebar-nav::-webkit-scrollbar{
    width:7px;
}
.sidebar-nav::-webkit-scrollbar-track{
    background:transparent;
}
.sidebar-nav::-webkit-scrollbar-thumb{
    background:linear-gradient(180deg,var(--theme),var(--theme-2));
    border-radius:999px;
    border:1px solid transparent;
    background-clip:padding-box;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover{
    background:linear-gradient(180deg,var(--theme-2),var(--theme));
}
.s360-admin.adm-dark .sidebar-nav{
    scrollbar-color:var(--theme) #0d1626;
}
.s360-admin.adm-dark .sidebar-nav::-webkit-scrollbar-track{
    background:#0d1626;
}
.s360-admin.adm-dark .sidebar-nav::-webkit-scrollbar-thumb{
    background:linear-gradient(180deg,var(--theme),var(--theme-2));
}

/* ---------- RESPONSIVE ---------- */
@media(max-width:1100px){
    .sidebar{width:82px}
    .main{margin-left:82px}
    .brand{padding:0 20px}
    .brand-copy,.nav-section-title,.side-link span,.profile-copy,.search-wrap input{display:none}
    .side-link{justify-content:center;padding:8px}
    .side-link i{width:auto}
    .profile-mini{justify-content:center}
    .collapse-btn{transform:rotate(180deg)}
}
@media(max-width:991.98px){
    .sidebar{transform:translateX(-100%);width:264px}
    .sidebar.open{transform:translateX(0)}
    .sidebar .brand-copy,.sidebar .nav-section-title,.sidebar .side-link span,.sidebar .profile-copy,.sidebar .search-wrap input{display:block}
    .sidebar .side-link{justify-content:flex-start;padding:8px 10px}
    .sidebar .side-link i{width:20px}
    .sidebar .profile-mini{justify-content:flex-start}
    .sidebar .collapse-btn{transform:none}
    .main{margin-left:0}
    .mobile-menu{display:grid;width:38px;height:38px;border:1px solid var(--line);background:#fff;border-radius:11px;place-items:center;color:#68758a}
    .topbar{padding:0 17px}
    .top-profile-copy{display:none}
    .content{padding:20px 17px 32px}
    .sidebar-overlay{position:fixed;inset:0;background:rgba(15,23,42,.32);z-index:1040;display:none}
    .sidebar-overlay.show{display:block}
}
@media(max-width:575.98px){
    .topbar{height:64px}
    .context-sub{display:none}
    .hero{padding:22px 20px;border-radius:20px}
    .hero h1{font-size:1.45rem}
    .hero-actions .btn-white,.hero-actions .btn-soft-white{width:100%;text-align:center}
    .section{margin-top:20px}
    .panel-body{padding:15px}
    .final-card{padding:18px}
}


/* ---------- CLEAN POLISH / MICRO-INTERACTIONS ---------- */
body{
    letter-spacing:-.005em;
}
.sidebar{
    box-shadow:8px 0 30px rgba(30,55,90,.025);
}
.brand{
    position:relative;
}
.brand:after{
    content:"";
    position:absolute;
    left:20px; right:20px; bottom:-1px;
    height:1px;
    background:linear-gradient(90deg,transparent,#dbeafe,transparent);
}
.brand-mark{
    position:relative;
    overflow:hidden;
    background:linear-gradient(145deg,#2563eb 0%,#4f46e5 100%);
}
.brand-mark:before{
    content:"";
    position:absolute;
    width:70px;height:70px;border-radius:50%;
    background:rgba(255,255,255,.10);
    top:-43px;right:-28px;
}
.brand-mark i{
    position:relative;
    z-index:2;
    animation:brandFloat 3.2s ease-in-out infinite;
}
.brand-name span{color:#2563eb}
.brand-orbit{
    position:absolute;
    border:1px solid rgba(255,255,255,.28);
    border-radius:50%;
    pointer-events:none;
}
.orbit-one{width:38px;height:18px;transform:rotate(35deg)}
.orbit-two{width:31px;height:15px;transform:rotate(-35deg)}
@keyframes brandFloat{
    0%,100%{transform:translateY(0) rotate(0deg)}
    50%{transform:translateY(-2px) rotate(6deg)}
}
.profile-row{
    display:flex;
    align-items:center;
    gap:5px;
}
.profile-row .profile-mini{
    min-width:0;
    flex:1;
}
.collapse-btn{
    transition:background .2s,color .2s,transform .25s;
}
body.sidebar-collapsed .profile-row{
    justify-content:center;
}
body.sidebar-collapsed .profile-row .collapse-btn{
    position:absolute;
    bottom:8px;
    right:8px;
    width:24px;height:24px;
    opacity:0;
}
body.sidebar-collapsed .sidebar-footer:hover .collapse-btn{
    opacity:1;
}
.context-dot{
    position:relative;
    display:block;
}
.context-dot > span{
    position:absolute;
    inset:-4px;
    border:1px solid rgba(52,211,153,.35);
    border-radius:50%;
    animation:statusPulse 2s ease-out infinite;
}
@keyframes statusPulse{
    0%{transform:scale(.65);opacity:.9}
    80%,100%{transform:scale(1.55);opacity:0}
}
.hero{
    isolation:isolate;
}
.hero:before{
    content:"";
    position:absolute;
    width:310px;height:310px;
    border-radius:50%;
    right:-110px;top:-170px;
    border:1px solid rgba(255,255,255,.12);
    box-shadow:
        0 0 0 28px rgba(255,255,255,.025),
        0 0 0 56px rgba(255,255,255,.018);
    animation:heroOrbit 12s linear infinite;
    pointer-events:none;
}
@keyframes heroOrbit{
    to{transform:rotate(360deg)}
}
.hero-sparkles{
    position:absolute;
    right:80px;
    top:28px;
    display:flex;
    align-items:center;
    gap:12px;
    color:rgba(255,255,255,.7);
    font-size:13px;
    z-index:0;
    pointer-events:none;
}
.hero-sparkles span:nth-child(1){animation:twinkle 2.4s ease-in-out infinite}
.hero-sparkles span:nth-child(2){animation:twinkle 2.4s .4s ease-in-out infinite}
.hero-sparkles span:nth-child(3){animation:twinkle 2.4s .8s ease-in-out infinite}
.hero-sparkles span:nth-child(4){animation:twinkle 2.4s 1.1s ease-in-out infinite}
.hero-sparkles span:nth-child(5){animation:twinkle 2.4s 1.5s ease-in-out infinite}
@keyframes twinkle{
    0%,100%{opacity:.25;transform:scale(.8)}
    50%{opacity:1;transform:scale(1.35)}
}
.hero > *:not(.hero-sparkles){position:relative;z-index:1}
.quick,.module,.stat,.panel{
    will-change:transform;
}
.quick-icon,.module-icon,.stat-icon{
    position:relative;
    overflow:hidden;
}
.quick-icon:after,.module-icon:after,.stat-icon:after{
    content:"";
    position:absolute;
    width:26px;height:26px;border-radius:50%;
    border:1px solid currentColor;
    opacity:.08;
    transform:translate(10px,10px);
}
.quick:hover .quick-icon i,
.module:hover .module-icon i{
    transform:translateY(-1px) scale(1.08);
}
.quick-icon i,.module-icon i,.stat-icon i{
    transition:transform .2s ease;
}
.section-title i{
    font-size:.86rem;
}
.top-action{
    transition:background .18s,color .18s,border-color .18s,transform .18s;
}
.top-action:hover{
    transform:translateY(-1px);
}
.nav-search::placeholder{color:#a4adba}
.side-link{
    position:relative;
}
.side-link.active:before{
    content:"";
    position:absolute;
    left:-12px;
    top:8px;bottom:8px;
    width:3px;
    border-radius:0 4px 4px 0;
    background:#2563eb;
}
body.sidebar-collapsed .side-link.active:before{
    left:0;
}
.status{
    font-weight:700;
}
.final-card{
    position:relative;
    overflow:hidden;
}
.final-card:after{
    content:"";
    position:absolute;
    width:180px;height:180px;
    right:-75px;top:-85px;
    border:22px solid rgba(255,255,255,.045);
    border-radius:50%;
}
@media(max-width:991.98px){
    .hero-sparkles{right:28px}
    body.sidebar-collapsed .sidebar{width:264px}
}

/* ---------- MOTION ---------- */
.reveal{animation:rise .42s ease both}
.reveal:nth-child(2){animation-delay:.03s}.reveal:nth-child(3){animation-delay:.06s}.reveal:nth-child(4){animation-delay:.09s}
@keyframes rise{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;animation:none!important;transition:none!important}}

/* =====================================================
   STUDIA360 — CAPA DE TEMA REAL
   El color seleccionado debe dominar TODO el dashboard.
   ===================================================== */
body.s360-admin{
    --theme:#8b5cf6;
    --theme-2:#7c3aed;
    --theme-soft:#f5f3ff;
    --theme-rgb:139,92,246;
    --theme-contrast:#fff;
}
body.s360-admin.adm-accent-blue{
    --theme:#2563eb;
    --theme-2:#4f46e5;
    --theme-soft:#eff6ff;
    --theme-rgb:37,99,235;
}
body.s360-admin.adm-accent-orange{
    --theme:#f97316;
    --theme-2:#ea580c;
    --theme-soft:#fff7ed;
    --theme-rgb:249,115,22;
}
body.s360-admin.adm-accent-green{
    --theme:#10b981;
    --theme-2:#059669;
    --theme-soft:#ecfdf5;
    --theme-rgb:16,185,129;
}

/* Elementos que antes estaban clavados a azul */
.s360-admin .brand-mark{
    background:linear-gradient(145deg,var(--theme),var(--theme-2)) !important;
    box-shadow:0 8px 20px rgba(var(--theme-rgb),.22) !important;
}
.s360-admin .brand-name span{color:var(--theme) !important}
.s360-admin .side-link:hover i,
.s360-admin .side-link.active,
.s360-admin .side-link.active i,
.s360-admin .section-link,
.s360-admin .grade-link,
.s360-admin .stat-foot a:hover,
.s360-admin .top-action:hover,
.s360-admin .collapse-btn:hover{
    color:var(--theme) !important;
}
.s360-admin .side-link.active{
    background:var(--theme-soft) !important;
}
.s360-admin .side-link.active:before{
    background:var(--theme) !important;
}
.s360-admin .nav-search:focus{
    border-color:rgba(var(--theme-rgb),.35) !important;
    box-shadow:0 0 0 4px rgba(var(--theme-rgb),.08) !important;
}
.s360-admin .hero{
    background:
        radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 18rem),
        linear-gradient(125deg,var(--theme-2),var(--theme)) !important;
    box-shadow:0 16px 38px rgba(var(--theme-rgb),.18) !important;
}
.s360-admin .progress-fill{
    background:linear-gradient(90deg,var(--theme-2),var(--theme)) !important;
}
.s360-admin .final-card{
    background:linear-gradient(125deg,var(--theme-2),var(--theme)) !important;
}
.s360-admin .btn-white{
    color:var(--theme) !important;
}
.s360-admin .btn-white:hover{
    color:var(--theme-2) !important;
}
.s360-admin .context-dot{
    background:#34d399 !important;
}
.s360-admin .context-dot > span{
    border-color:rgba(52,211,153,.35) !important;
}

/* El color de iconos puede seguir siendo variado, pero los elementos
   principales usan el color elegido. */
.s360-admin .stat-icon.purple{background:rgba(139,92,246,.14);color:#a78bfa}
.stat-icon.blue,
.s360-admin .quick-icon.blue,
.s360-admin .module-icon.blue{
    background:var(--theme-soft) !important;
    color:var(--theme) !important;
}

/* ===================== MODO OSCURO ===================== */
body.s360-admin.adm-dark{
    --ink:#eef2ff;
    --muted:#aab4c8;
    --line:#28344a;
    --surface:#172238;
    --canvas:#0b1220;
    color:#eef2ff !important;
    background:
        radial-gradient(circle at 85% 0%,rgba(var(--theme-rgb),.12),transparent 28rem),
        #0b1220 !important;
}
.s360-admin.adm-dark .sidebar{
    background:rgba(13,22,38,.97) !important;
    border-color:#28344a !important;
    box-shadow:8px 0 30px rgba(0,0,0,.18) !important;
}
.s360-admin.adm-dark .topbar{
    background:rgba(13,22,38,.92) !important;
    border-color:#28344a !important;
}
.s360-admin.adm-dark .brand-link,
.s360-admin.adm-dark .side-link,
.s360-admin.adm-dark .profile-mini,
.s360-admin.adm-dark .top-action,
.s360-admin.adm-dark .quick,
.s360-admin.adm-dark .module,
.s360-admin.adm-dark .stat,
.s360-admin.adm-dark .panel,
.s360-admin.adm-dark .grade{
    color:#eef2ff !important;
}
.s360-admin.adm-dark .side-link{color:#aeb9ca !important}
.s360-admin.adm-dark .side-link:hover{
    background:rgba(255,255,255,.055) !important;
    color:#fff !important;
}
.s360-admin.adm-dark .side-link.active{
    background:rgba(var(--theme-rgb),.16) !important;
    color:#fff !important;
}
.s360-admin.adm-dark .side-link.active i{color:var(--theme) !important}
.s360-admin.adm-dark .sidebar,
.s360-admin.adm-dark .sidebar-tools,
.s360-admin.adm-dark .sidebar-footer{
    border-color:#28344a !important;
}
.s360-admin.adm-dark .nav-search{
    background:#111b2d !important;
    border-color:#2b3850 !important;
    color:#eef2ff !important;
}
.s360-admin.adm-dark .nav-search:focus{
    background:#142039 !important;
}
.s360-admin.adm-dark .nav-search::placeholder{
    color:#77849a !important;
}
.s360-admin.adm-dark .top-action{
    background:#172238 !important;
    border-color:#2b3850 !important;
    color:#aeb9ca !important;
}
.s360-admin.adm-dark .top-action:hover{
    background:#1d2a42 !important;
    color:var(--theme) !important;
}
.s360-admin.adm-dark .collapse-btn,
.s360-admin.adm-dark .mobile-menu{
    background:#172238 !important;
    color:#aeb9ca !important;
}
.s360-admin.adm-dark .profile-mini:hover,
.s360-admin.adm-dark .quick:hover,
.s360-admin.adm-dark .module:hover{
    background:#1b2941 !important;
}
.s360-admin.adm-dark .unit-card{
    background:#172238 !important;
    border-color:#28344a !important;
    color:#eef2ff !important;
    box-shadow:0 8px 30px rgba(0,0,0,.12) !important;
}
.s360-admin.adm-dark .unit-card:hover{
    background:#1b2941 !important;
    border-color:rgba(var(--theme-rgb),.3) !important;
}
.s360-admin.adm-dark .unit-meta{
    color:#8997ad !important;
}
.s360-admin.adm-dark .unit-arrow{
    color:#718098 !important;
}

.s360-admin.adm-dark .stat,
.s360-admin.adm-dark .panel,
.s360-admin.adm-dark .quick,
.s360-admin.adm-dark .module{
    background:#172238 !important;
    border-color:#28344a !important;
    box-shadow:0 8px 30px rgba(0,0,0,.15) !important;
}
.s360-admin.adm-dark .stat-foot{
    border-color:#28344a !important;
}
.s360-admin.adm-dark .stat-foot a,
.s360-admin.adm-dark .stat-label,
.s360-admin.adm-dark .stat-note,
.s360-admin.adm-dark .section-sub,
.s360-admin.adm-dark .progress-caption,
.s360-admin.adm-dark .mini-label,
.s360-admin.adm-dark .grade-info,
.s360-admin.adm-dark .quick-text,
.s360-admin.adm-dark .module-description{
    color:#8997ad !important;
}
.s360-admin.adm-dark .mini-box{
    background:#111b2d !important;
    border-color:#28344a !important;
}
.s360-admin.adm-dark .grade{
    background:#111b2d !important;
    border-color:#28344a !important;
}
.s360-admin.adm-dark .grade:hover{
    background:#1b2941 !important;
    border-color:rgba(var(--theme-rgb),.3) !important;
}
.s360-admin.adm-dark .progress-track{
    background:#263249 !important;
}
.s360-admin.adm-dark .blue,
.s360-admin.adm-dark .stat-icon.blue,
.s360-admin.adm-dark .quick-icon.blue,
.s360-admin.adm-dark .module-icon.blue{
    background:rgba(var(--theme-rgb),.15) !important;
    color:#b9c8ff !important;
}
.s360-admin.adm-dark .green{
    background:rgba(16,185,129,.14) !important;
}
.s360-admin.adm-dark .orange{
    background:rgba(249,115,22,.14) !important;
}
.s360-admin.adm-dark .cyan{
    background:rgba(6,182,212,.14) !important;
}
.s360-admin.adm-dark .purple{
    background:rgba(139,92,246,.14) !important;
}
.s360-admin.adm-dark .red{
    background:rgba(239,68,68,.14) !important;
}
.s360-admin.adm-dark .avatar{
    background:#263249 !important;
    color:#aeb9ca !important;
}
.s360-admin.adm-dark .section-title,
.s360-admin.adm-dark .context-title,
.s360-admin.adm-dark .module-title,
.s360-admin.adm-dark .quick-title,
.s360-admin.adm-dark .grade-num,
.s360-admin.adm-dark .mini-value{
    color:#eef2ff !important;
}
.s360-admin.adm-dark .text-primary{
    color:var(--theme) !important;
}

/* ===================== MODO CLARO ===================== */
body.s360-admin:not(.adm-dark){
    color:#172033;
}
.s360-admin:not(.adm-dark) .sidebar{
    background:rgba(255,255,255,.96);
}
.s360-admin:not(.adm-dark) .topbar{
    background:rgba(255,255,255,.88);
}

</style>
</head>

<body class="s360-admin">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">
    <div class="brand">
        <a class="brand-link" href="dashboard.php">
            <div class="brand-mark">
                <span class="brand-orbit orbit-one"></span>
                <span class="brand-orbit orbit-two"></span>
                <i class="bi bi-stars"></i>
            </div>
            <div class="brand-copy">
                <div class="brand-name">Studia<span>360</span></div>
                <div class="brand-sub">Panel de administración</div>
            </div>
        </a>
    </div>

    <div class="sidebar-tools">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input id="menuSearch" class="nav-search" type="search" placeholder="Buscar en el menú…" autocomplete="off">
        </div>
    </div>

    <nav id="sidebarNav" class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Principal</div>
            <a href="dashboard.php" class="side-link active" data-label="dashboard inicio panel principal">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Académico</div>
            <a href="contenidos/materias.php" class="side-link" data-label="materias temas asignaturas">
                <i class="bi bi-book-half"></i><span>Materias y temas</span>
            </a>
            <a href="contenidos/index.php" class="side-link" data-label="contenidos lecciones">
                <i class="bi bi-journal-richtext"></i><span>Contenidos</span>
            </a>
            <a href="contenidos/index.php" class="side-link" data-label="recursos pdf videos enlaces">
                <i class="bi bi-collection-play"></i><span>Recursos</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Estudiantes</div>
            <a href="estudiantes/index.php" class="side-link" data-label="estudiantes usuarios perfiles">
                <i class="bi bi-people-fill"></i><span>Estudiantes</span>
            </a>
            <a href="progreso/index.php" class="side-link" data-label="progreso avance seguimiento">
                <i class="bi bi-graph-up-arrow"></i><span>Progreso estudiantil</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Comunidad</div>
            <a href="sugerencias/index.php" class="side-link" data-label="quejas reclamos recomendaciones sugerencias">
                <i class="bi bi-chat-left-text"></i><span>Quejas y recomendaciones</span>
                <?php if ($total_sugerencias > 0): ?><span class="badge rounded-pill text-bg-primary side-badge"><?= $total_sugerencias ?></span><?php endif; ?>
            </a>
            <a href="recuperacion/index.php" class="side-link" data-label="recuperación contraseña password">
                <i class="bi bi-key"></i><span>Recuperación</span>
                <?php if ($total_recuperaciones_pendientes > 0): ?><span class="badge rounded-pill text-bg-danger side-badge"><?= $total_recuperaciones_pendientes ?></span><?php endif; ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Personalización</div>
            <a href="gamificacion/index.php" class="side-link" data-label="gamificación puntos niveles avatares insignias">
                <i class="bi bi-stars"></i><span>Gamificación</span>
            </a>
            <a href="coleccionables/index.php" class="side-link" data-label="coleccionables insignias avatares">
                <i class="bi bi-award"></i><span>Coleccionables</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="profile-row">
            <a href="perfil.php" class="profile-mini">
                <div class="avatar">
                    <?php if ($avatarAdminUrl): ?>
                        <img src="<?= e($avatarAdminUrl) ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-copy flex-grow-1 overflow-hidden">
                    <div class="profile-mini-name"><?= e($nombreCompleto) ?></div>
                    <div class="profile-mini-role">Administrador</div>
                </div>
            </a>
            <button id="collapseSidebar" type="button" class="collapse-btn" title="Contraer menú" aria-label="Contraer menú">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="page-context">
            <button id="mobileMenu" class="mobile-menu" type="button" aria-label="Abrir menú">
                <i class="bi bi-list"></i>
            </button>
            <span class="context-dot"><span></span></span>
            <div>
                <div class="context-title">Panel de administración</div>
                <div class="context-sub">Gestiona Studia360 de forma sencilla</div>
            </div>
        </div>

        <div class="top-actions">
            <a href="perfil.php" class="top-action d-none d-sm-inline-flex">
                <i class="bi bi-person"></i> Mi perfil
            </a>
            <div class="top-profile">
                <div class="top-profile-copy text-end">
                    <div class="top-profile-name"><?= e($nombreCompleto) ?></div>
                    <div class="top-profile-role">Administrador</div>
                </div>
                <a href="perfil.php" class="avatar" title="Abrir mi perfil">
                    <?php if ($avatarAdminUrl): ?>
                        <img src="<?= e($avatarAdminUrl) ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </a>
            </div>
            <a href="../cerrar_sesion.php" class="top-action" title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i><span class="d-none d-md-inline">Salir</span>
            </a>
        </div>
    </header>

    <main class="content">

        <section class="hero reveal">
            <div class="hero-sparkles" aria-hidden="true">
                <span>✦</span><span>·</span><span>✧</span><span>·</span><span>✦</span>
            </div>
            <div class="hero-kicker">Centro de control</div>
            <h1>Hola, <?= e($primerNombre) ?> 👋</h1>
            <p>Todo lo importante de Studia360, organizado en un solo lugar. Administra contenidos, estudiantes, progreso y la experiencia de aprendizaje.</p>
            <div class="hero-actions">
                <a href="contenidos/materias.php" class="btn-white"><i class="bi bi-book-half me-1"></i> Administrar materias</a>
                <a href="estudiantes/index.php" class="btn-soft-white"><i class="bi bi-people me-1"></i> Ver estudiantes</a>
            </div>
        </section>

        <?php if ($error_estadisticas): ?>
            <div class="alert alert-danger dashboard-alert mt-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                No fue posible cargar una o varias estadísticas del sistema.
            </div>
        <?php endif; ?>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Vista general</h2>
                    <p class="section-sub">Una mirada rápida al estado actual de tu plataforma.</p>
                </div>
            </div>
            <div class="stat-grid">
                <div class="stat-col">
                    <div class="stat">
                        <div class="stat-body">
                            <div><div class="stat-label">Estudiantes</div><div class="stat-number"><?= $total_estudiantes ?></div><div class="stat-note">Usuarios registrados</div></div>
                            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                        </div>
                        <div class="stat-foot"><a href="estudiantes/index.php">Gestionar estudiantes <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat">
                        <div class="stat-body">
                            <div><div class="stat-label">Materias</div><div class="stat-number"><?= $total_materias ?></div><div class="stat-note">Estructura académica</div></div>
                            <div class="stat-icon green"><i class="bi bi-book-fill"></i></div>
                        </div>
                        <div class="stat-foot"><a href="contenidos/materias.php">Gestionar materias <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat stat-unit">
                        <div class="stat-body">
                            <div><div class="stat-label">Unidades temáticas</div><div class="stat-number"><?= $total_unidades ?></div><div class="stat-note">Organización por grado</div></div>
                            <div class="stat-icon purple"><i class="bi bi-diagram-3-fill"></i></div>
                        </div>
                        <div class="stat-foot"><a href="contenidos/unidades.php">Gestionar unidades <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat">
                        <div class="stat-body">
                            <div><div class="stat-label">Temas</div><div class="stat-number"><?= $total_temas ?></div><div class="stat-note"><?= $total_temas_contenido ?> con contenido</div></div>
                            <div class="stat-icon orange"><i class="bi bi-journal-text"></i></div>
                        </div>
                        <div class="stat-foot"><a href="contenidos/temas.php">Gestionar temas <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat">
                        <div class="stat-body">
                            <div><div class="stat-label">Recursos</div><div class="stat-number"><?= $total_recursos ?></div><div class="stat-note">Materiales asociados</div></div>
                            <div class="stat-icon cyan"><i class="bi bi-collection-play-fill"></i></div>
                        </div>
                        <div class="stat-foot"><a href="contenidos/index.php">Administrar recursos <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section reveal">
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="panel">
                        <div class="panel-body">
                            <div class="section-head">
                                <div>
                                    <h2 class="section-title"><i class="bi bi-pie-chart-fill text-primary me-1"></i> Estado del contenido</h2>
                                    <p class="section-sub">Qué parte de los temas ya tiene material preparado.</p>
                                </div>
                                <div class="text-end">
                                    <div class="progress-number"><?= $porcentaje_contenido ?>%</div>
                                    <div class="progress-caption">completado</div>
                                </div>
                            </div>
                            <div class="progress-track mb-3"><div class="progress-fill" style="width:<?= $porcentaje_contenido ?>%"></div></div>
                            <div class="row g-2">
                                <div class="col-6"><div class="mini-box"><div class="mini-value"><?= $total_temas_contenido ?></div><div class="mini-label">Temas con contenido</div></div></div>
                                <div class="col-6"><div class="mini-box"><div class="mini-value"><?= $total_temas_sin_contenido ?></div><div class="mini-label">Pendientes</div></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="panel">
                        <div class="panel-body">
                            <div class="section-head">
                                <div>
                                    <h2 class="section-title"><i class="bi bi-mortarboard-fill text-primary me-1"></i> Distribución académica</h2>
                                    <p class="section-sub">Contenido organizado por grado.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <div class="grade"><div><div class="grade-num">9°</div><div class="grade-info"><?= $temas_por_grado["9"] ?> temas · <?= $contenido_por_grado["9"] ?> con contenido</div></div><a class="grade-link" href="contenidos/temas.php?grado=9">Ver <i class="bi bi-arrow-right"></i></a></div>
                                <div class="grade"><div><div class="grade-num">10°</div><div class="grade-info"><?= $temas_por_grado["10"] ?> temas · <?= $contenido_por_grado["10"] ?> con contenido</div></div><a class="grade-link" href="contenidos/temas.php?grado=10">Ver <i class="bi bi-arrow-right"></i></a></div>
                                <div class="grade"><div><div class="grade-num">11°</div><div class="grade-info"><?= $temas_por_grado["11"] ?> temas · <?= $contenido_por_grado["11"] ?> con contenido</div></div><a class="grade-link" href="contenidos/temas.php?grado=11">Ver <i class="bi bi-arrow-right"></i></a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section reveal">
            <div class="control-grid">
                <div class="insight">
                    <div class="insight-head"><div><h2 class="insight-title"><i class="bi bi-diagram-3-fill text-primary me-1"></i> Arquitectura de aprendizaje</h2><p class="insight-sub">La ruta académica que organiza el contenido de Studia360.</p></div><span class="badge rounded-pill text-bg-light border"><?= $total_unidades ?> unidades</span></div>
                    <div class="route">
                        <div class="route-step"><div class="route-icon"><i class="bi bi-book-fill"></i></div><div class="route-name">Materia</div><div class="route-note"><?= $total_materias ?> registradas</div></div>
                        <div class="route-step"><div class="route-icon"><i class="bi bi-diagram-3"></i></div><div class="route-name">Unidad temática</div><div class="route-note"><?= $hay_unidades ? $total_unidades . ' creadas' : 'Migración pendiente' ?></div></div>
                        <div class="route-step"><div class="route-icon"><i class="bi bi-journal-text"></i></div><div class="route-name">Tema</div><div class="route-note"><?= $total_temas ?> disponibles</div></div>
                        <div class="route-step"><div class="route-icon"><i class="bi bi-file-earmark-richtext"></i></div><div class="route-name">Contenido</div><div class="route-note"><?= $total_temas_contenido ?> preparados</div></div>
                    </div>
                    <div class="d-flex justify-content-end mt-3"><a class="section-link" href="contenidos/materias.php">Gestionar estructura <i class="bi bi-arrow-right ms-1"></i></a></div>
                </div>
                <div class="insight">
                    <div class="insight-head"><div><h2 class="insight-title"><i class="bi bi-heart-pulse-fill text-primary me-1"></i> Estado del sistema</h2><p class="insight-sub">Indicadores rápidos para saber qué requiere atención.</p></div></div>
                    <div class="health-list">
                        <div class="health-row"><div class="health-left"><div class="health-icon"><i class="bi bi-people-fill"></i></div><div><div class="health-label">Estudiantes activos</div><div class="health-note"><?= $total_cursos ?> cursos activos</div></div></div><strong><?= $total_estudiantes_activos ?></strong></div>
                        <div class="health-row"><div class="health-left"><div class="health-icon"><i class="bi bi-bar-chart-fill"></i></div><div><div class="health-label">Avance registrado</div><div class="health-note">Promedio de progreso</div></div></div><strong><?= number_format($progreso_promedio,1) ?>%</strong></div>
                        <div class="health-row"><div class="health-left"><div class="health-icon"><i class="bi bi-chat-left-text-fill"></i></div><div><div class="health-label">Mensajes recibidos</div><div class="health-note">Quejas y recomendaciones</div></div></div><strong><?= $total_sugerencias ?></strong></div>
                        <div class="health-row"><div class="health-left"><div class="health-icon"><i class="bi bi-key-fill"></i></div><div><div class="health-label">Recuperaciones</div><div class="health-note">Solicitudes pendientes</div></div></div><strong class="<?= $total_recuperaciones_pendientes ? 'text-danger' : 'text-success' ?>"><?= $total_recuperaciones_pendientes ?></strong></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section reveal">
            <div class="insight">
                <div class="insight-head">
                    <div>
                        <h2 class="insight-title"><i class="bi bi-layers-fill text-primary me-1"></i> Unidades temáticas</h2>
                        <p class="insight-sub">La nueva capa intermedia entre cada materia y sus temas.</p>
                    </div>
                    <a class="section-link" href="contenidos/materias.php">Gestionar <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <?php if ($unidades_dashboard): ?>
                    <div class="unit-grid">
                        <?php foreach ($unidades_dashboard as $unidad): ?>
                            <a class="unit-card" href="contenidos/materias.php#unidad-<?= (int)$unidad['id_unidad'] ?>" title="Abrir <?= e($unidad['nombre']) ?>">
                                <div class="unit-icon"><i class="bi bi-diagram-3-fill"></i></div>
                                <div class="unit-copy">
                                    <div class="unit-name"><?= e($unidad['nombre']) ?></div>
                                    <div class="unit-meta"><?= (int)$unidad['total_temas'] ?> <?= (int)$unidad['total_temas'] === 1 ? 'tema' : 'temas' ?></div>
                                </div>
                                <i class="bi bi-chevron-right unit-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_unidades > count($unidades_dashboard)): ?>
                        <div class="d-flex justify-content-end mt-3">
                            <a class="section-link" href="contenidos/materias.php">Ver las <?= $total_unidades ?> unidades <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="unit-empty">
                        <i class="bi bi-diagram-3 d-block fs-5 mb-2"></i>
                        Todavía no hay unidades temáticas disponibles.
                        <div class="mt-2"><a class="section-link" href="contenidos/materias.php">Abrir estructura académica <i class="bi bi-arrow-right ms-1"></i></a></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="row g-3">
                <div class="col-12 col-lg-7"><div class="insight"><div class="insight-head"><div><h2 class="insight-title"><i class="bi bi-lightning-charge-fill text-primary me-1"></i> Actividad reciente</h2><p class="insight-sub">Últimos avances registrados por los estudiantes.</p></div><a class="section-link" href="progreso/index.php">Ver progreso <i class="bi bi-arrow-right ms-1"></i></a></div><div class="activity-list">
                    <?php if ($ultimas_actividades): foreach ($ultimas_actividades as $actividad): ?>
                    <div class="activity-row"><div class="activity-avatar"><?= e(inicialesDashboard($actividad['nombres'], $actividad['apellidos'])) ?></div><div class="activity-main"><div class="activity-name"><?= e(trim($actividad['nombres'].' '.$actividad['apellidos'])) ?></div><div class="activity-topic"><?= e($actividad['materia']) ?> · <?= e($actividad['tema']) ?></div></div><div class="activity-progress"><span style="width:<?= min(100,max(0,(float)$actividad['porcentaje_avance'])) ?>%"></span></div><div class="activity-time"><?= e(fechaActividadDashboard($actividad['ultima_actividad'])) ?></div></div>
                    <?php endforeach; else: ?><div class="text-center py-4 text-muted small"><i class="bi bi-activity d-block fs-4 mb-2"></i>Aún no hay actividad registrada.</div><?php endif; ?>
                </div></div></div>
                <div class="col-12 col-lg-5"><div class="insight"><div class="insight-head"><div><h2 class="insight-title"><i class="bi bi-check2-circle text-primary me-1"></i> Preparación de contenidos</h2><p class="insight-sub">Una vista rápida de lo que está listo para estudiar.</p></div></div><div class="readiness"><div class="readiness-ring" style="--value:<?= $porcentaje_contenido ?>"><strong><?= $porcentaje_contenido ?>%</strong></div><div class="readiness-copy"><strong><?= $total_temas_contenido ?> de <?= $total_temas ?> temas preparados</strong><p><?= $total_temas_sin_contenido ?> temas todavía necesitan contenido. <?= $temas_sin_unidad > 0 ? $temas_sin_unidad.' temas no tienen unidad temática asignada.' : 'La estructura temática está conectada.' ?></p><a class="section-link" href="contenidos/temas.php">Revisar contenidos <i class="bi bi-arrow-right ms-1"></i></a></div></div></div></div>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Acceso rápido</h2>
                    <p class="section-sub">Las herramientas que más vas a utilizar.</p>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12 col-md-6 col-xl-3"><a href="contenidos/materias.php" class="quick"><div class="quick-icon green"><i class="bi bi-book-fill"></i></div><div><div class="quick-title">Materias</div><div class="quick-text">Organiza materias y temas.</div></div></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="contenidos/temas.php" class="quick"><div class="quick-icon orange"><i class="bi bi-journal-text"></i></div><div><div class="quick-title">Temas</div><div class="quick-text">Busca, edita y revisa temas.</div></div></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="estudiantes/index.php" class="quick"><div class="quick-icon blue"><i class="bi bi-people-fill"></i></div><div><div class="quick-title">Estudiantes</div><div class="quick-text">Consulta perfiles y cuentas.</div></div></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="gamificacion/index.php" class="quick"><div class="quick-icon purple"><i class="bi bi-stars"></i></div><div><div class="quick-title">Gamificación</div><div class="quick-text">Niveles, avatares e insignias.</div></div></a></div>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Módulos de Studia360</h2>
                    <p class="section-sub">Entra directamente al área que necesitas administrar.</p>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12 col-md-6 col-xl-3"><a href="contenidos/index.php" class="module"><div class="module-icon blue"><i class="bi bi-journal-richtext"></i></div><div class="module-title">Contenidos</div><div class="module-description">Editor y gestión de las lecciones.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="contenidos/materias.php" class="module"><div class="module-icon green"><i class="bi bi-book-half"></i></div><div class="module-title">Materias y temas</div><div class="module-description">Estructura académica de la plataforma.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="contenidos/index.php" class="module"><div class="module-icon cyan"><i class="bi bi-collection-play"></i></div><div class="module-title">Recursos</div><div class="module-description">Videos, PDFs, enlaces y actividades.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="gamificacion/index.php" class="module"><div class="module-icon purple"><i class="bi bi-stars"></i></div><div class="module-title">Gamificación</div><div class="module-description">Puntos, niveles, avatares e insignias.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>

                <div class="col-12 col-md-6 col-xl-3"><a href="sugerencias/index.php" class="module"><div class="module-icon purple"><i class="bi bi-chat-left-text"></i></div><div class="module-title">Quejas y recomendaciones</div><div class="module-description">Mensajes enviados por estudiantes.</div><span class="badge rounded-pill text-bg-success status"><?= $total_sugerencias ?> mensajes</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="recuperacion/index.php" class="module"><div class="module-icon orange"><i class="bi bi-key"></i></div><div class="module-title">Recuperación</div><div class="module-description">Solicitudes pendientes de estudiantes.</div><span class="badge rounded-pill <?= $total_recuperaciones_pendientes > 0 ? 'text-bg-danger' : 'text-bg-success' ?> status"><?= $total_recuperaciones_pendientes ?> pendientes</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="progreso/index.php" class="module"><div class="module-icon green"><i class="bi bi-graph-up-arrow"></i></div><div class="module-title">Progreso estudiantil</div><div class="module-description">Seguimiento individual y por materia.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>
                <div class="col-12 col-md-6 col-xl-3"><a href="coleccionables/index.php" class="module"><div class="module-icon purple"><i class="bi bi-award"></i></div><div class="module-title">Coleccionables</div><div class="module-description">Avatares e insignias del sistema.</div><span class="badge rounded-pill text-bg-success status">Disponible</span></a></div>
            </div>
        </section>

        <section class="section reveal">
            <div class="final-card">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-lg-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="final-icon"><i class="bi bi-stars"></i></div>
                            <div>
                                <div class="final-title">Haz que cada avance cuente</div>
                                <p class="final-text">Configura la experiencia de aprendizaje para que los estudiantes puedan avanzar, ganar XP y conseguir sus emblemas.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4"><a href="gamificacion/index.php" class="btn btn-light w-100 fw-semibold btn-sm py-2"><i class="bi bi-arrow-right me-1"></i> Configurar gamificación</a></div>
                </div>
            </div>
        </section>

    </main>
</div>

<script>
(function(){
    const body=document.body;
    const sidebar=document.getElementById('sidebar');
    const overlay=document.getElementById('sidebarOverlay');
    const mobile=document.getElementById('mobileMenu');
    const collapse=document.getElementById('collapseSidebar');
    const search=document.getElementById('menuSearch');
    const links=[...document.querySelectorAll('.side-link')];

    // Remember desktop menu preference.
    try{
        if(localStorage.getItem('studia_sidebar_collapsed')==='1' && window.innerWidth>991){
            body.classList.add('sidebar-collapsed');
        }
    }catch(e){}

    collapse?.addEventListener('click',function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        if(window.innerWidth<=991){
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            return;
        }
        body.classList.toggle('sidebar-collapsed');
        try{localStorage.setItem('studia_sidebar_collapsed',body.classList.contains('sidebar-collapsed')?'1':'0')}catch(e){}
    });

    mobile?.addEventListener('click',function(){
        sidebar.classList.add('open');
        overlay.classList.add('show');
    });

    overlay?.addEventListener('click',function(){
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });

    window.addEventListener('resize',function(){
        if(window.innerWidth>991){
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
    });

    document.addEventListener('keydown',function(ev){
        if(ev.key==='Escape'){
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
        if((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase()==='k'){
            ev.preventDefault();
            search?.focus();
        }
    });

    // Quick menu search.
    search?.addEventListener('input',function(){
        const q=this.value.trim().toLowerCase();
        document.querySelectorAll('.nav-section').forEach(section=>{
            let visible=0;
            section.querySelectorAll('.side-link').forEach(link=>{
                const hay=(link.dataset.label||link.textContent).toLowerCase();
                const show=!q || hay.includes(q);
                link.style.display=show?'flex':'none';
                if(show) visible++;
            });
            section.style.display=visible?'block':'none';
        });
    });

    // Close mobile menu after navigation.
    links.forEach(link=>link.addEventListener('click',function(){
        if(window.innerWidth<=991){
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
    }));
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
<script>
(function(){
'use strict';

const body=document.body;
const sharedKey='studia360_theme';
const legacyKey='studia360_admin_theme';

const validThemes=['purple','blue','orange','green'];
let state={theme:'purple',mode:'dark'};

function readState(){
    try{
        let raw=localStorage.getItem(sharedKey);
        if(!raw) raw=localStorage.getItem(legacyKey);
        if(raw){
            const parsed=JSON.parse(raw);
            if(parsed && typeof parsed==='object') state=Object.assign(state,parsed);
        }
    }catch(e){}
    if(!validThemes.includes(state.theme)) state.theme='purple';
    state.mode=state.mode==='light'?'light':'dark';
}

function writeState(){
    try{
        const value=JSON.stringify(state);
        localStorage.setItem(sharedKey,value);
        localStorage.setItem(legacyKey,value);
    }catch(e){}
}

function applyTheme(){
    validThemes.forEach(theme=>{
        body.classList.remove('adm-accent-'+theme);
    });

    body.classList.add('s360-admin','adm-accent-'+state.theme);
    body.classList.toggle('adm-dark',state.mode==='dark');

    document.querySelectorAll('.adm-theme-option').forEach(btn=>{
        btn.classList.toggle('active',btn.dataset.theme===state.theme);
        btn.setAttribute('aria-pressed',btn.dataset.theme===state.theme?'true':'false');
    });

    const modeBtn=document.getElementById('admModeBtn');
    if(modeBtn){
        modeBtn.innerHTML=state.mode==='dark'
            ? "<i class='bi bi-moon-stars me-2'></i>Modo oscuro"
            : "<i class='bi bi-sun me-2'></i>Modo claro";
    }
}

window.admSetTheme=function(theme){
    if(!validThemes.includes(theme)) return;
    state.theme=theme;
    writeState();
    applyTheme();
};

window.admToggleMode=function(){
    state.mode=state.mode==='dark'?'light':'dark';
    writeState();
    applyTheme();
};

readState();
applyTheme();

const toggle=document.getElementById('admThemeToggle');
const panel=document.getElementById('admThemePanel');

toggle?.addEventListener('click',function(event){
    event.preventDefault();
    event.stopPropagation();
    panel?.classList.toggle('open');
});

panel?.addEventListener('click',function(event){
    event.stopPropagation();
});

document.addEventListener('click',function(event){
    if(panel?.classList.contains('open') &&
       !panel.contains(event.target) &&
       !toggle?.contains(event.target)){
        panel.classList.remove('open');
    }
});
})();
</script>
</body>
</html>

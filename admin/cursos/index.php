<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }

$errores = [];
$mensajes = [];
if (empty($_SESSION['csrf_cursos'])) $_SESSION['csrf_cursos'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_cursos'];

$grados = ['9','10','11'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $errores[] = 'La sesión del formulario expiró. Recarga la página.';
    } else {
        $accion = (string)($_POST['accion'] ?? '');
        try {
            if ($accion === 'guardar') {
                $id = (int)($_POST['id_curso'] ?? 0);
                $grado = trim((string)($_POST['grado'] ?? ''));
                $grupo = strtoupper(trim((string)($_POST['grupo'] ?? '')));
                $director = trim((string)($_POST['director'] ?? ''));
                $estado = (string)($_POST['estado'] ?? 'Activo');

                if (!in_array($grado, $grados, true)) $errores[] = 'Selecciona un grado válido.';
                if ($grupo === '') $errores[] = 'El código del curso es obligatorio.';
                elseif (!preg_match('/^' . preg_quote($grado, '/') . '\\d{2}$/', $grupo)) $errores[] = "Para grado {$grado}°, el curso debe tener formato {$grado}01, {$grado}02, etc.";
                if (mb_strlen($director) > 150) $errores[] = 'El nombre del director no puede superar 150 caracteres.';
                if (!in_array($estado, ['Activo','Inactivo'], true)) $estado = 'Activo';

                if (!$errores) {
                    $st = $conexion->prepare('SELECT id_curso FROM cursos WHERE grado = ? AND grupo = ? AND id_curso <> ? LIMIT 1');
                    $st->execute([$grado, $grupo, $id]);
                    if ($st->fetchColumn()) {
                        $errores[] = "El curso {$grupo} ya existe para {$grado}°.";
                    } elseif ($id > 0) {
                        $st = $conexion->prepare('UPDATE cursos SET grado = ?, grupo = ?, director = ?, estado = ? WHERE id_curso = ?');
                        $st->execute([$grado, $grupo, $director !== '' ? $director : null, $estado, $id]);
                        $mensajes[] = 'Curso actualizado correctamente.';
                    } else {
                        $st = $conexion->prepare('INSERT INTO cursos (grado, grupo, director, estado) VALUES (?, ?, ?, ?)');
                        $st->execute([$grado, $grupo, $director !== '' ? $director : null, $estado]);
                        $mensajes[] = 'Curso creado correctamente.';
                    }
                }
            } elseif ($accion === 'estado') {
                $id = (int)($_POST['id_curso'] ?? 0);
                if ($id <= 0) $errores[] = 'Curso no válido.';
                else {
                    $st = $conexion->prepare("UPDATE cursos SET estado = CASE WHEN estado = 'Activo' THEN 'Inactivo' ELSE 'Activo' END WHERE id_curso = ?");
                    $st->execute([$id]);
                    $mensajes[] = 'Estado del curso actualizado.';
                }
            } elseif ($accion === 'eliminar') {
                $id = (int)($_POST['id_curso'] ?? 0);
                if ($id <= 0) $errores[] = 'Curso no válido.';
                else {
                    $st = $conexion->prepare('SELECT COUNT(*) FROM usuarios WHERE id_curso = ?');
                    $st->execute([$id]);
                    $estudiantes = (int)$st->fetchColumn();
                    if ($estudiantes > 0) {
                        $errores[] = "No puedes eliminar este curso porque tiene {$estudiantes} estudiante(s) asociado(s). Puedes inactivarlo.";
                    } else {
                        $st = $conexion->prepare('DELETE FROM cursos WHERE id_curso = ?');
                        $st->execute([$id]);
                        $mensajes[] = 'Curso eliminado correctamente.';
                    }
                }
            }
        } catch (PDOException $ex) {
            $errores[] = 'No fue posible completar la operación.';
        }
    }
}

$gradoFiltro = trim((string)($_GET['grado'] ?? ''));
$estadoFiltro = trim((string)($_GET['estado'] ?? ''));
$busqueda = trim((string)($_GET['buscar'] ?? ''));
if (!in_array($gradoFiltro, $grados, true)) $gradoFiltro = '';
if (!in_array($estadoFiltro, ['Activo','Inactivo'], true)) $estadoFiltro = '';

try {
    $sql = "SELECT c.id_curso, c.grado, c.grupo, c.director, c.estado,
                   COUNT(u.id_usuario) AS cantidad_estudiantes
            FROM cursos c
            LEFT JOIN usuarios u ON u.id_curso = c.id_curso AND u.id_rol = 2";
    $where = [];
    $params = [];
    if ($gradoFiltro !== '') { $where[] = 'c.grado = ?'; $params[] = $gradoFiltro; }
    if ($estadoFiltro !== '') { $where[] = 'c.estado = ?'; $params[] = $estadoFiltro; }
    if ($busqueda !== '') { $where[] = '(c.grupo LIKE ? OR c.director LIKE ?)'; $params[] = "%{$busqueda}%"; $params[] = "%{$busqueda}%"; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY c.id_curso, c.grado, c.grupo, c.director, c.estado ORDER BY c.grado, c.grupo';
    $st = $conexion->prepare($sql);
    $st->execute($params);
    $cursos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $cursos = [];
    $errores[] = 'No fue posible cargar los cursos.';
}

try {
    $resumen = $conexion->query("SELECT grado, COUNT(*) cantidad, SUM(estado='Activo') activos FROM cursos GROUP BY grado ORDER BY grado")->fetchAll(PDO::FETCH_ASSOC);
    $totalCursos = (int)$conexion->query('SELECT COUNT(*) FROM cursos')->fetchColumn();
    $totalActivos = (int)$conexion->query("SELECT COUNT(*) FROM cursos WHERE estado='Activo'")->fetchColumn();
    $totalEstudiantesConCurso = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE id_rol=2 AND id_curso IS NOT NULL")->fetchColumn();
} catch (PDOException $ex) {
    $resumen = [];
    $totalCursos = $totalActivos = $totalEstudiantesConCurso = 0;
}

$urlDashboard = urlAplicacion('/admin/dashboard.php');
$urlSalir = urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cursos | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(urlAplicacion('/admin/assets/studia-admin.css')) ?>">
<style>
body.cursos-page{background:#0d1424;color:#edf2f8;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
.cursos-shell{width:min(1180px,calc(100% - 32px));margin:0 auto;padding:34px 0 70px}
.cursos-hero{position:relative;overflow:hidden;border-radius:25px;padding:30px;background:linear-gradient(125deg,#2563eb,#7c3aed);box-shadow:0 22px 55px rgba(37,99,235,.18);color:#fff}
.cursos-hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;right:-80px;top:-120px;border:55px solid rgba(255,255,255,.08);box-shadow:0 0 0 35px rgba(255,255,255,.035)}
.cursos-kicker{font-size:.68rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;opacity:.78}.cursos-hero h1{font-size:clamp(1.8rem,4vw,2.5rem);font-weight:900;letter-spacing:-.04em;margin:.25rem 0 .35rem}.cursos-hero p{max-width:650px;margin:0;opacity:.84}
.cursos-hero .btn{position:relative;z-index:2;border:0;border-radius:12px;font-weight:800;padding:.7rem 1rem;background:#fff;color:#172033}
.cursos-panel,.curso-card,.filter-panel{background:#172236;border:1px solid #2b374b;border-radius:21px;color:#edf2f8;box-shadow:0 12px 34px rgba(0,0,0,.12)}
.filter-panel{padding:20px}.form-label{font-size:.75rem;font-weight:800;color:#c7d2e1}.form-control,.form-select{background:#111827!important;color:#edf2f8!important;border:1px solid #334158!important;border-radius:11px!important}.form-control::placeholder{color:#77859b!important}.form-control:focus,.form-select:focus{border-color:#8b5cf6!important;box-shadow:0 0 0 3px rgba(139,92,246,.12)!important}
.btn-theme{background:#8b5cf6;border-color:#8b5cf6;color:#fff;font-weight:800;border-radius:11px}.btn-theme:hover{background:#7c3aed;border-color:#7c3aed;color:#fff}.btn-ghost{background:#1e2b41;border:1px solid #35445c;color:#dce5f2;border-radius:11px;font-weight:750}.btn-ghost:hover{background:#263650;color:#fff}
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0}.mini-stat{padding:18px;border-radius:18px;background:#151f31;border:1px solid #2b374b}.mini-stat-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:#211d45;color:#a78bfa;margin-bottom:12px}.mini-stat strong{font-size:1.55rem;display:block}.mini-stat span{color:#91a0b5;font-size:.78rem}
.grade-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:16px}.grade-box{padding:12px 14px;border:1px solid #2d3a50;border-radius:14px;background:#141e30}.grade-box strong{font-size:.92rem}.grade-box small{display:block;color:#8f9db0;margin-top:3px}
.curso-card{padding:19px;height:100%;transition:.2s}.curso-card:hover{transform:translateY(-2px);border-color:#465778}.curso-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.curso-icon{width:46px;height:46px;border-radius:14px;background:#202a55;color:#a78bfa;display:grid;place-items:center;font-size:1.2rem}.curso-code{font-size:1.35rem;font-weight:900;letter-spacing:-.02em}.grado-pill,.estado-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:.67rem;font-weight:850}.grado-pill{background:#1d3153;color:#93c5fd}.estado-pill.activo{background:#0d3b2f;color:#6ee7b7}.estado-pill.inactivo{background:#39291a;color:#fdba74}.curso-meta{margin-top:16px;padding-top:14px;border-top:1px solid #2b374b;color:#9eacbf;font-size:.79rem}.curso-meta strong{color:#edf2f8}.curso-actions{display:flex;gap:8px;margin-top:16px}.curso-actions .btn{font-size:.74rem;font-weight:800;border-radius:9px}.empty{padding:55px 20px;text-align:center;color:#91a0b5}
.modal-content{background:#172236;color:#edf2f8;border:1px solid #334158;border-radius:20px}.modal-header,.modal-footer{border-color:#2b374b}.btn-close{filter:invert(1) grayscale(1) brightness(2)}
.alert{border-radius:13px;border:1px solid #334158}.alert-success{background:#12372d;color:#a7f3d0}.alert-danger{background:#3a2026;color:#fecdd3}
@media(max-width:767px){.cursos-shell{width:min(100% - 20px,1180px);padding-top:20px}.stat-grid,.grade-summary{grid-template-columns:1fr}.cursos-hero{padding:23px}.cursos-hero .btn{margin-top:18px}.curso-actions{flex-wrap:wrap}}
</style>

<style>
/* =========================================================
   CURSOS — INTEGRACIÓN CON EL TEMA GLOBAL DE STUDIA360
   ========================================================= */
body.s360-admin.cursos-page{
    --page-bg:var(--adm-bg,#eef2f7);
    --page-surface:var(--adm-surface,#e9eef7);
    --page-surface-2:var(--adm-surface-2,#e1e8f2);
    --page-text:var(--adm-text,#172033);
    --page-muted:var(--adm-muted,#748094);
    --page-line:var(--adm-line,#e8edf4);
    background:
        radial-gradient(circle at 88% 0%,color-mix(in srgb,var(--adm-accent) 10%,transparent),transparent 28rem),
        var(--page-bg)!important;
    color:var(--page-text)!important;
}

/* Barra superior */
.s360-admin .cursos-topbar{
    background:color-mix(in srgb,var(--adm-bg) 94%,transparent)!important;
    border-color:var(--adm-line)!important;
    color:var(--page-text)!important;
    backdrop-filter:blur(14px);
}
.s360-admin .cursos-brand{color:var(--page-text)!important}
.s360-admin .cursos-brand-mark{
    background:linear-gradient(145deg,var(--adm-accent),var(--adm-accent-2))!important;
    box-shadow:0 8px 20px color-mix(in srgb,var(--adm-accent) 22%,transparent)!important;
}
.s360-admin .cursos-topbar .btn-ghost{
    background:var(--page-surface)!important;
    color:var(--page-text)!important;
    border-color:var(--page-line)!important;
}
.s360-admin .cursos-topbar .btn-ghost:hover{
    background:var(--adm-accent-soft)!important;
    color:var(--adm-accent)!important;
}

/* Hero */
.s360-admin .cursos-hero{
    background:
        radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 18rem),
        linear-gradient(125deg,var(--adm-accent-2),var(--adm-accent))!important;
    box-shadow:0 22px 55px color-mix(in srgb,var(--adm-accent) 18%,transparent)!important;
}
.s360-admin .cursos-hero .btn{
    background:var(--page-surface)!important;
    color:var(--page-text)!important;
}

/* Tarjetas / paneles */
.s360-admin .cursos-panel,
.s360-admin .curso-card,
.s360-admin .filter-panel,
.s360-admin .mini-stat{
    background:var(--page-surface)!important;
    color:var(--page-text)!important;
    border-color:var(--page-line)!important;
    box-shadow:var(--adm-shadow)!important;
}
.s360-admin .curso-card:hover{
    border-color:color-mix(in srgb,var(--adm-accent) 32%,var(--page-line))!important;
    box-shadow:var(--adm-shadow-hover)!important;
}
.s360-admin .mini-stat-icon,
.s360-admin .curso-icon{
    background:color-mix(in srgb,var(--adm-accent) 15%,var(--page-surface))!important;
    color:var(--adm-accent)!important;
    border:1px solid color-mix(in srgb,var(--adm-accent) 28%,var(--page-line))!important;
}

/* Texto */
.s360-admin .cursos-page h2,
.s360-admin .cursos-page h3,
.s360-admin .cursos-page .curso-code,
.s360-admin .cursos-page .mini-stat strong,
.s360-admin .cursos-page .grade-box strong,
.s360-admin .cursos-page .form-label{
    color:var(--page-text)!important;
}
.s360-admin .cursos-page .mini-stat span,
.s360-admin .cursos-page .grade-box small,
.s360-admin .cursos-page .curso-meta{
    color:var(--page-muted)!important;
}
.s360-admin .cursos-page [style*="#91a0b5"],
.s360-admin .cursos-page [style*="#8f9db0"],
.s360-admin .cursos-page [style*="#9eacbf"]{
    color:var(--page-muted)!important;
}
.s360-admin .cursos-page [style*="#edf2f8"]{
    color:var(--page-text)!important;
}

/* Resumen */
.s360-admin .grade-box{
    background:var(--page-surface-2)!important;
    border-color:var(--page-line)!important;
    color:var(--page-text)!important;
}

/* Formularios */
.s360-admin .form-control,
.s360-admin .form-select{
    background:var(--page-surface)!important;
    color:var(--page-text)!important;
    border-color:var(--page-line)!important;
}
.s360-admin .form-control::placeholder{color:var(--page-muted)!important}
.s360-admin .form-control:focus,
.s360-admin .form-select:focus{
    border-color:color-mix(in srgb,var(--adm-accent) 48%,var(--page-line))!important;
    box-shadow:0 0 0 4px color-mix(in srgb,var(--adm-accent) 10%,transparent)!important;
}
.s360-admin .btn-theme{
    background:var(--adm-accent)!important;
    border-color:var(--adm-accent)!important;
    color:#fff!important;
}
.s360-admin .btn-theme:hover{
    background:var(--adm-accent-2)!important;
    border-color:var(--adm-accent-2)!important;
}
.s360-admin .btn-ghost{
    background:var(--page-surface-2)!important;
    color:var(--page-text)!important;
    border-color:var(--page-line)!important;
}
.s360-admin .btn-ghost:hover{
    background:var(--adm-accent-soft)!important;
    color:var(--adm-accent)!important;
}

/* Chips */
.s360-admin .grado-pill{
    background:color-mix(in srgb,var(--adm-accent) 13%,var(--page-surface))!important;
    color:var(--adm-accent)!important;
}
.s360-admin .curso-meta{border-color:var(--page-line)!important}
.s360-admin .estado-pill.activo{background:rgba(16,185,129,.14)!important;color:#059669!important}
.s360-admin .estado-pill.inactivo{background:rgba(245,158,11,.14)!important;color:#d97706!important}

/* Vacío, alertas y modal */
.s360-admin .cursos-panel.empty{
    background:var(--page-surface)!important;
    color:var(--page-muted)!important;
    border-color:var(--page-line)!important;
}
.s360-admin .cursos-panel.empty h3{color:var(--page-text)!important}
.s360-admin .modal-content{
    background:var(--page-surface)!important;
    color:var(--page-text)!important;
    border-color:var(--page-line)!important;
}
.s360-admin .modal-header,
.s360-admin .modal-footer{border-color:var(--page-line)!important}
.s360-admin .modal-title{color:var(--page-text)!important}
.s360-admin .btn-close{filter:none!important}
.s360-admin.adm-dark .btn-close{filter:invert(1) grayscale(1) brightness(2)!important}

.s360-admin:not(.adm-dark) .alert-success{background:#ecfdf5!important;color:#047857!important;border-color:#d1fae5!important}
.s360-admin:not(.adm-dark) .alert-danger{background:#fef2f2!important;color:#b91c1c!important;border-color:#fecaca!important}
.s360-admin.adm-dark .alert-success{background:#12372d!important;color:#a7f3d0!important}
.s360-admin.adm-dark .alert-danger{background:#3a2026!important;color:#fecdd3!important}

/* ===================== MODO CLARO — SUPERFICIES SIN BLANCO PURO ===================== */
.s360-admin:not(.adm-dark).cursos-page{
    --page-bg:#eef2f7;
    --page-surface:#e8edf5;
    --page-surface-2:#dde5ef;
    --page-text:#172033;
    --page-muted:#3f5068;
    --page-line:#c1ccda;
}
.s360-admin:not(.adm-dark) .cursos-topbar{
    background:rgba(232,237,245,.94)!important;
    border-color:var(--page-line)!important;
}
.s360-admin:not(.adm-dark) .cursos-panel,
.s360-admin:not(.adm-dark) .curso-card,
.s360-admin:not(.adm-dark) .filter-panel,
.s360-admin:not(.adm-dark) .mini-stat,
.s360-admin:not(.adm-dark) .modal-content{
    background:var(--page-surface)!important;
    border-color:var(--page-line)!important;
}
.s360-admin:not(.adm-dark) .grade-box{
    background:var(--page-surface-2)!important;
    border-color:var(--page-line)!important;
}
.s360-admin:not(.adm-dark) .form-control,
.s360-admin:not(.adm-dark) .form-select{
    background:#e1e7f0!important;
    border-color:#c6d0df!important;
}
.s360-admin:not(.adm-dark) .btn-ghost{
    background:var(--page-surface-2)!important;
    border-color:var(--page-line)!important;
}
.s360-admin:not(.adm-dark) .curso-meta{
    border-color:var(--page-line)!important;
    color:#4f5f76!important;
}
.s360-admin:not(.adm-dark) .curso-meta strong{
    color:#172033!important;
}
.s360-admin:not(.adm-dark) .curso-card [style*="#91a0b5"],
.s360-admin:not(.adm-dark) .curso-card [style*="#8f9db0"],
.s360-admin:not(.adm-dark) .curso-card [style*="#9eacbf"]{
    color:#4f5f76!important;
}

/* ================= CONTRASTE EXTRA — MODO CLARO ================= */
.s360-admin:not(.adm-dark).cursos-page .form-label{
    color:#26364d!important;
}
.s360-admin:not(.adm-dark).cursos-page .form-control,
.s360-admin:not(.adm-dark).cursos-page .form-select{
    color:#172033!important;
}
.s360-admin:not(.adm-dark).cursos-page .form-control::placeholder{
    color:#56677e!important;
    opacity:1!important;
}
.s360-admin:not(.adm-dark).cursos-page .grade-box strong,
.s360-admin:not(.adm-dark).cursos-page .grade-box small{
    color:#26364d!important;
}
.s360-admin:not(.adm-dark).cursos-page .grade-box small{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .mini-stat span{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .curso-card [style*="#91a0b5"],
.s360-admin:not(.adm-dark).cursos-page .curso-card [style*="#8f9db0"],
.s360-admin:not(.adm-dark).cursos-page .curso-card [style*="#9eacbf"]{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .curso-meta{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .curso-meta strong{
    color:#26364d!important;
}
.s360-admin:not(.adm-dark).cursos-page h2{
    color:#172033!important;
}
.s360-admin:not(.adm-dark).cursos-page section > .d-flex small{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .btn-ghost{
    color:#26364d!important;
}
.s360-admin:not(.adm-dark).cursos-page .btn-ghost:hover{
    color:var(--adm-accent)!important;
}
.s360-admin:not(.adm-dark).cursos-page .empty{
    color:#52647b!important;
}
.s360-admin:not(.adm-dark).cursos-page .empty h3{
    color:#172033!important;
}
.s360-admin:not(.adm-dark).cursos-page .modal-content,
.s360-admin:not(.adm-dark).cursos-page .modal-content .modal-title{
    color:#172033!important;
}

/* El valor por defecto también se ve con buen contraste. */
.s360-admin:not(.adm-dark).cursos-page .curso-meta .bi + strong{
    color:#26364d!important;
}

/* ================= "SIN ASIGNAR" — MÁS LEGIBLE ================= */
.s360-admin:not(.adm-dark).cursos-page .curso-meta strong{
    color:#26364d!important;
}
.s360-admin.adm-dark.cursos-page .curso-meta strong{
    color:#edf2f8!important;
}

/* Dark mode: ninguna superficie puede quedarse con el fondo claro */
.s360-admin.adm-dark{
    --page-bg:#0f1727;
    --page-surface:#182235;
    --page-surface-2:#111a2b;
    --page-text:#edf2f7;
    --page-muted:#9aa7ba;
    --page-line:#2a3549;
}
.s360-admin.adm-dark .cursos-hero .btn{
    background:#182235!important;
    color:#edf2f7!important;
}
.s360-admin.adm-dark .grado-pill{
    background:color-mix(in srgb,var(--adm-accent) 18%,#182235)!important;
}
.s360-admin.adm-dark .estado-pill.activo{background:#12372d!important;color:#6ee7b7!important}
.s360-admin.adm-dark .estado-pill.inactivo{background:#39291a!important;color:#fdba74!important}

/* Iconos de tarjetas: nunca blanco puro; siguen el acento seleccionado */
.s360-admin.cursos-page .mini-stat-icon,
.s360-admin.cursos-page .curso-icon{
    background:color-mix(in srgb,var(--adm-accent) 15%,var(--page-surface))!important;
    color:var(--adm-accent)!important;
    border:1px solid color-mix(in srgb,var(--adm-accent) 28%,var(--page-line))!important;
}
.s360-admin.adm-dark.cursos-page .mini-stat-icon,
.s360-admin.adm-dark.cursos-page .curso-icon{
    background:color-mix(in srgb,var(--adm-accent) 17%,#182235)!important;
    color:var(--adm-accent)!important;
    border-color:color-mix(in srgb,var(--adm-accent) 30%,#2a3549)!important;
}
</style>
</head>
<body class="s360-admin cursos-page">
<header class="cursos-topbar" style="height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:1000">
    <a href="<?= e($urlDashboard) ?>" class="cursos-brand" style="text-decoration:none;font-weight:900;display:flex;align-items:center;gap:10px">
        <span class="cursos-brand-mark" style="width:38px;height:38px;border-radius:12px;display:grid;place-items:center;color:#fff"><i class="bi bi-mortarboard-fill"></i></span>
        Studia360 <span style="font-size:.65rem;background:var(--adm-accent);padding:4px 7px;border-radius:6px;color:#fff">Admin</span>
    </a>
    <div class="d-flex gap-2"><a class="btn btn-ghost btn-sm" href="<?= e($urlDashboard) ?>"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a><a class="btn btn-ghost btn-sm" href="<?= e($urlSalir) ?>"><i class="bi bi-box-arrow-right me-1"></i>Salir</a></div>
</header>
<main class="cursos-shell">
<section class="cursos-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
 <div><div class="cursos-kicker">Estructura académica</div><h1>Gestión de cursos</h1><p>Crea y organiza cursos como 901, 902, 1001 o 1101. Cada curso queda vinculado a su grado y puede tener un director de grupo.</p></div>
 <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#cursoModal" onclick="nuevoCurso()"><i class="bi bi-plus-lg me-1"></i>Nuevo curso</button>
</section>

<div class="stat-grid">
 <div class="mini-stat"><div class="mini-stat-icon"><i class="bi bi-diagram-3"></i></div><strong><?= $totalCursos ?></strong><span>Cursos registrados</span></div>
 <div class="mini-stat"><div class="mini-stat-icon"><i class="bi bi-check-circle"></i></div><strong><?= $totalActivos ?></strong><span>Cursos activos</span></div>
 <div class="mini-stat"><div class="mini-stat-icon"><i class="bi bi-people"></i></div><strong><?= $totalEstudiantesConCurso ?></strong><span>Estudiantes con curso</span></div>
</div>

<?php foreach ($errores as $error): ?><div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div><?php endforeach; ?>
<?php foreach ($mensajes as $mensaje): ?><div class="alert alert-success mb-3"><i class="bi bi-check-circle me-2"></i><?= e($mensaje) ?></div><?php endforeach; ?>

<section class="filter-panel mb-4">
<form method="get" class="row g-3 align-items-end">
 <div class="col-12 col-md-4"><label class="form-label">Buscar</label><input class="form-control" name="buscar" value="<?= e($busqueda) ?>" placeholder="901, 1001 o director..."></div>
 <div class="col-6 col-md-3"><label class="form-label">Grado</label><select class="form-select" name="grado"><option value="">Todos</option><?php foreach($grados as $g): ?><option value="<?= $g ?>" <?= $gradoFiltro===$g?'selected':'' ?>><?= $g ?>°</option><?php endforeach; ?></select></div>
 <div class="col-6 col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><option value="Activo" <?= $estadoFiltro==='Activo'?'selected':'' ?>>Activos</option><option value="Inactivo" <?= $estadoFiltro==='Inactivo'?'selected':'' ?>>Inactivos</option></select></div>
 <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-theme flex-grow-1" type="submit"><i class="bi bi-search"></i></button><a class="btn btn-ghost" href="<?= e(urlAplicacion('/admin/cursos/index.php')) ?>" title="Limpiar"><i class="bi bi-arrow-clockwise"></i></a></div>
</form>
<div class="grade-summary"><?php foreach($grados as $g): $row=null; foreach($resumen as $r){if((string)$r['grado']===$g){$row=$r;break;}} ?><div class="grade-box"><strong><?= $g ?>°</strong><small><?= (int)($row['cantidad']??0) ?> cursos · <?= (int)($row['activos']??0) ?> activos</small></div><?php endforeach; ?></div>
</section>

<section>
 <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 style="font-size:1.05rem;font-weight:900;margin:0">Cursos registrados</h2><small style="color:var(--page-muted)"><?= count($cursos) ?> resultado(s)</small></div></div>
 <div class="row g-3">
 <?php if (!$cursos): ?><div class="col-12"><div class="cursos-panel empty"><i class="bi bi-diagram-3" style="font-size:2rem"></i><h3 class="mt-3" style="font-size:1rem;color:#edf2f8">No hay cursos para mostrar</h3><p class="mb-0">Prueba otros filtros o crea el primer curso.</p></div></div><?php endif; ?>
 <?php foreach($cursos as $curso): ?>
 <div class="col-12 col-md-6 col-xl-4"><article class="curso-card">
   <div class="curso-head"><div class="curso-icon"><i class="bi bi-people-fill"></i></div><div class="text-end"><span class="grado-pill"><?= e($curso['grado']) ?>°</span><span class="estado-pill <?= $curso['estado']==='Activo'?'activo':'inactivo' ?> ms-1"><?= e($curso['estado']) ?></span></div></div>
   <div class="mt-3"><div class="curso-code"><?= e($curso['grupo']) ?></div><div style="color:#91a0b5;font-size:.78rem">Curso de <?= e($curso['grado']) ?>°</div></div>
   <div class="curso-meta"><div><i class="bi bi-person-badge me-1"></i><strong><?= e($curso['director'] ?: 'Sin asignar') ?></strong></div><div class="mt-2"><i class="bi bi-people me-1"></i> Estudiantes: <strong><?= (int)$curso['cantidad_estudiantes'] ?></strong></div></div>
   <div class="curso-actions"><button class="btn btn-theme flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#cursoModal" onclick='editarCurso(<?= json_encode($curso, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'><i class="bi bi-pencil me-1"></i>Editar</button><form method="post" class="m-0"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_curso" value="<?= (int)$curso['id_curso'] ?>"><button class="btn btn-ghost" type="submit" title="Cambiar estado"><i class="bi bi-power"></i></button></form><?php if((int)$curso['cantidad_estudiantes']===0): ?><form method="post" class="m-0" onsubmit="return confirm('¿Eliminar el curso <?= e($curso['grupo']) ?>? Esta acción no se puede deshacer.');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_curso" value="<?= (int)$curso['id_curso'] ?>"><button class="btn btn-ghost" style="color:#fda4af" type="submit" title="Eliminar"><i class="bi bi-trash3"></i></button></form><?php endif; ?></div>
 </article></div>
 <?php endforeach; ?>
 </div>
</section>
</main>

<div class="modal fade" id="cursoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><div><h5 class="modal-title" id="modalTitulo" style="font-weight:900">Nuevo curso</h5><small style="color:var(--page-muted)">Define el grado y el código del curso.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id_curso" id="id_curso" value="0"><div class="row g-3"><div class="col-5"><label class="form-label">Grado *</label><select class="form-select" name="grado" id="modal_grado" required><option value="9">9°</option><option value="10">10°</option><option value="11">11°</option></select></div><div class="col-7"><label class="form-label">Curso *</label><input class="form-control" name="grupo" id="modal_grupo" maxlength="5" placeholder="901" required><div style="font-size:.67rem;color:#91a0b5;margin-top:5px">Ej.: 901, 902, 1001, 1101</div></div><div class="col-12"><label class="form-label">Director de grupo</label><input class="form-control" name="director" id="modal_director" maxlength="150" placeholder="Nombre del director (opcional)"></div><div class="col-12"><label class="form-label">Estado</label><select class="form-select" name="estado" id="modal_estado"><option>Activo</option><option>Inactivo</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-theme" type="submit"><i class="bi bi-check-lg me-1"></i>Guardar curso</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function nuevoCurso(){document.getElementById('modalTitulo').textContent='Nuevo curso';document.getElementById('id_curso').value='0';document.getElementById('modal_grado').value='9';document.getElementById('modal_grupo').value='';document.getElementById('modal_director').value='';document.getElementById('modal_estado').value='Activo';setTimeout(()=>document.getElementById('modal_grupo').focus(),150)}
function editarCurso(c){document.getElementById('modalTitulo').textContent='Editar curso '+c.grupo;document.getElementById('id_curso').value=c.id_curso;document.getElementById('modal_grado').value=c.grado;document.getElementById('modal_grupo').value=c.grupo;document.getElementById('modal_director').value=c.director||'';document.getElementById('modal_estado').value=c.estado;}
document.getElementById('modal_grado').addEventListener('change',function(){const g=this.value, campo=document.getElementById('modal_grupo');if(!document.getElementById('id_curso').value||document.getElementById('id_curso').value==='0')campo.value=g+'01';});
</script>

<!-- Studia360 Admin: personalizador global — misma interfaz del dashboard -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia">
    <i class="bi bi-palette2"></i>
</button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
    <div class="adm-theme-title">Personaliza el panel</div>
    <div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
    <div class="adm-theme-grid">
        <button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')">
            <div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div>
            <strong>Violeta</strong><small>Studia360</small>
        </button>
        <button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')">
            <div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div>
            <strong>Azul</strong><small>Clásico</small>
        </button>
        <button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')">
            <div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div>
            <strong>Naranja</strong><small>Enérgico</small>
        </button>
        <button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')">
            <div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div>
            <strong>Verde</strong><small>Calma</small>
        </button>
    </div>
    <button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button>
</div>
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
    validThemes.forEach(theme=>body.classList.remove('adm-accent-'+theme));
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
panel?.addEventListener('click',function(event){ event.stopPropagation(); });
document.addEventListener('click',function(event){
    if(panel?.classList.contains('open') &&
       !panel.contains(event.target) &&
       !toggle?.contains(event.target)){
        panel.classList.remove('open');
    }
});
})();
</script>
</body></html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/seguridad.php';
require_once __DIR__ . '/../../includes/gamificacion.php';
exigirAdmin();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$grado = trim((string)($_GET['grado'] ?? ''));
$curso = (int)($_GET['curso'] ?? 0);
$materia = (int)($_GET['materia'] ?? 0);
$buscar = trim((string)($_GET['q'] ?? ''));
$estudiante = (int)($_GET['estudiante'] ?? 0);

$grados = ['9','10','11'];

try {
    $cursos = $conexion->query("SELECT id_curso, grado, grupo FROM cursos WHERE estado='Activo' ORDER BY grado, grupo")->fetchAll(PDO::FETCH_ASSOC);
    $materias = $conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $cursos = [];
    $materias = [];
}

$where = ["u.id_rol=(SELECT id_rol FROM roles WHERE nombre='Estudiante' LIMIT 1)", "u.estado='Activo'"];
$params = [];

if (in_array($grado, $grados, true)) { $where[] = 'u.grado=:grado'; $params[':grado']=$grado; }
if ($curso > 0) { $where[] = 'u.id_curso=:curso'; $params[':curso']=$curso; }
if ($buscar !== '') {
    $where[] = '(u.nombres LIKE :q OR u.apellidos LIKE :q OR u.numero_documento LIKE :q)';
    $params[':q']='%'.$buscar.'%';
}

$baseWhere = implode(' AND ', $where);

$sql = "
SELECT
    u.id_usuario,u.nombres,u.apellidos,u.numero_documento,u.grado,u.id_curso,u.puntos,u.nivel,
    c.grupo,
    n.nombre AS nombre_nivel,
    COALESCE(pg.porcentaje,0) AS progreso,
    COALESCE(pg.temas_iniciados,0) AS temas_iniciados,
    COALESCE(pg.temas_completados,0) AS temas_completados,
    pg.ultima_actividad
FROM usuarios u
LEFT JOIN cursos c ON c.id_curso=u.id_curso
LEFT JOIN niveles n ON n.id_nivel=u.nivel
LEFT JOIN (
    SELECT
        u2.id_usuario,
        CASE WHEN COUNT(t.id_tema)=0 THEN 0
             ELSE COALESCE(SUM(COALESCE(p.porcentaje_avance,0))/COUNT(t.id_tema),0) END AS porcentaje,
        COALESCE(SUM(CASE WHEN COALESCE(p.porcentaje_avance,0)>0 THEN 1 ELSE 0 END),0) AS temas_iniciados,
        COALESCE(SUM(CASE WHEN COALESCE(p.porcentaje_avance,0)>=100 THEN 1 ELSE 0 END),0) AS temas_completados,
        MAX(p.ultima_actividad) AS ultima_actividad
    FROM usuarios u2
    LEFT JOIN temas t ON t.grado=u2.grado
    LEFT JOIN progreso p ON p.id_usuario=u2.id_usuario AND p.id_tema=t.id_tema
    GROUP BY u2.id_usuario
) pg ON pg.id_usuario=u.id_usuario
WHERE $baseWhere
";

if ($materia > 0) {
    // Recalcular el promedio por materia para el filtro solicitado.
    $sql = "
    SELECT
        u.id_usuario,u.nombres,u.apellidos,u.numero_documento,u.grado,u.id_curso,u.puntos,u.nivel,
        c.grupo,n.nombre AS nombre_nivel,
        COALESCE(AVG(COALESCE(p.porcentaje_avance,0)),0) AS progreso,
        COALESCE(SUM(CASE WHEN COALESCE(p.porcentaje_avance,0)>0 THEN 1 ELSE 0 END),0) AS temas_iniciados,
        COALESCE(SUM(CASE WHEN COALESCE(p.porcentaje_avance,0)>=100 THEN 1 ELSE 0 END),0) AS temas_completados,
        MAX(p.ultima_actividad) AS ultima_actividad
    FROM usuarios u
    LEFT JOIN cursos c ON c.id_curso=u.id_curso
    LEFT JOIN niveles n ON n.id_nivel=u.nivel
    INNER JOIN temas t ON t.grado=u.grado AND t.id_materia=:materia
    LEFT JOIN progreso p ON p.id_usuario=u.id_usuario AND p.id_tema=t.id_tema
    WHERE $baseWhere
    GROUP BY u.id_usuario,u.nombres,u.apellidos,u.numero_documento,u.grado,u.id_curso,u.puntos,u.nivel,c.grupo,n.nombre
    ";
    $params[':materia']=$materia;
}

$sql .= ' ORDER BY progreso DESC,u.apellidos,u.nombres LIMIT 200';

try {
    $st=$conexion->prepare($sql); $st->execute($params); $estudiantes=$st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $estudiantes=[]; }

$total=count($estudiantes);
$promedio=$total ? array_sum(array_map(fn($x)=>(float)$x['progreso'],$estudiantes))/$total : 0;
$completos=array_sum(array_map(fn($x)=>(int)$x['temas_completados'],$estudiantes));

$detalle=null; $detalleMaterias=[];
if ($estudiante>0) {
    try {
        $st=$conexion->prepare("SELECT u.id_usuario,u.nombres,u.apellidos,u.grado,u.puntos,u.nivel,n.nombre AS nombre_nivel FROM usuarios u LEFT JOIN niveles n ON n.id_nivel=u.nivel WHERE u.id_usuario=? AND u.id_rol=(SELECT id_rol FROM roles WHERE nombre='Estudiante' LIMIT 1)");
        $st->execute([$estudiante]); $detalle=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($detalle) {
            $st=$conexion->prepare("SELECT m.id_materia,m.nombre,COUNT(t.id_tema) total_temas,COALESCE(AVG(COALESCE(p.porcentaje_avance,0)),0) progreso,COALESCE(SUM(CASE WHEN COALESCE(p.porcentaje_avance,0)>=100 THEN 1 ELSE 0 END),0) completados FROM materias m INNER JOIN temas t ON t.id_materia=m.id_materia AND t.grado=? LEFT JOIN progreso p ON p.id_usuario=? AND p.id_tema=t.id_tema GROUP BY m.id_materia,m.nombre ORDER BY m.nombre");
            $st->execute([$detalle['grado'],$estudiante]); $detalleMaterias=$st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Throwable $e) { $detalle=null; $detalleMaterias=[]; }
}

function progresoClass(float $p): string {
    if ($p >= 80) return 'bg-success';
    if ($p >= 50) return 'bg-primary';
    if ($p > 0) return 'bg-warning';
    return 'bg-secondary';
}
function fechaCorta($fecha): string {
    if (!$fecha) return 'Sin actividad registrada';
    $ts=strtotime((string)$fecha); return $ts ? date('d/m/Y H:i',$ts) : 'Sin actividad registrada';
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Progreso estudiantil | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#2467c5;--dark:#173f80;--border:#dce5f0;--muted:#718096;--bg:#f4f7fb}
body{background:radial-gradient(circle at top right,rgba(36,103,197,.10),transparent 28%),var(--bg);color:#26364a}
.top{background:linear-gradient(105deg,var(--dark),var(--primary));box-shadow:0 5px 20px rgba(22,58,117,.18)}
.hero{border-radius:25px;padding:2rem;color:#fff;background:radial-gradient(circle at 88% 10%,rgba(255,255,255,.15),transparent 24%),linear-gradient(120deg,#1e5eb8,#173f80);box-shadow:0 18px 42px rgba(25,64,124,.14)}
.cardx{background:#fff;border:1px solid var(--border);border-radius:22px;box-shadow:0 10px 28px rgba(31,57,92,.06)}
.stat{padding:1.15rem;border:1px solid var(--border);border-radius:18px;background:linear-gradient(180deg,#fff,#f8fbff);height:100%}.stat i{font-size:1.35rem}.stat-num{font-size:1.65rem;font-weight:850}.muted{color:var(--muted)}
.form-control,.form-select{border-color:var(--border);border-radius:13px;min-height:44px}.table>:not(caption)>*>*{padding:.9rem}.student-link{text-decoration:none;color:inherit}.student-link:hover{color:var(--primary)}
.progress{height:9px;background:#e9eef5;border-radius:999px}.detail{border:1px solid var(--border);border-radius:18px;padding:1rem}.subject-progress{min-width:150px}
</style><link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head><body>
<nav class="navbar navbar-dark top"><div class="container py-2"><a class="navbar-brand fw-bold" href="<?=e(urlAplicacion('/admin/dashboard.php'))?>"><i class="bi bi-mortarboard-fill me-2"></i>Studia360 <span class="badge bg-light text-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="<?=e(urlAplicacion('/admin/gamificacion/index.php'))?>">Gamificación</a><a class="btn btn-light btn-sm" href="<?=e(urlAplicacion('/admin/dashboard.php'))?>">Dashboard</a></div></div></nav>
<main class="container py-4 py-lg-5">
<section class="hero mb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-4"><div><div class="small text-uppercase fw-bold opacity-75 mb-2">Seguimiento académico</div><h1 class="h2 fw-bold mb-2">Progreso estudiantil</h1><p class="mb-0 text-white-50">Consulta el avance real de los estudiantes a partir de los temas disponibles para su grado.</p></div><i class="bi bi-graph-up-arrow display-4"></i></div></section>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="stat"><div class="d-flex justify-content-between"><span class="muted">Estudiantes visibles</span><i class="bi bi-people-fill text-primary"></i></div><div class="stat-num mt-2"><?=$total?></div></div></div><div class="col-md-4"><div class="stat"><div class="d-flex justify-content-between"><span class="muted">Promedio de avance</span><i class="bi bi-speedometer2 text-primary"></i></div><div class="stat-num mt-2"><?=number_format($promedio,1)?>%</div></div></div><div class="col-md-4"><div class="stat"><div class="d-flex justify-content-between"><span class="muted">Temas completados</span><i class="bi bi-check2-circle text-success"></i></div><div class="stat-num mt-2"><?=$completos?></div></div></div></div>
<section class="cardx p-3 p-md-4 mb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Filtros de seguimiento</h2><p class="muted mb-0 small">Filtra por grado, curso, materia o estudiante.</p></div><a class="btn btn-sm btn-outline-secondary" href="<?=e(urlAplicacion('/admin/progreso/index.php'))?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a></div><form class="row g-2"><div class="col-md-3"><input class="form-control" name="q" value="<?=e($buscar)?>" placeholder="Nombre o documento"></div><div class="col-md-2"><select class="form-select" name="grado"><option value="">Todos los grados</option><?php foreach($grados as $g):?><option value="<?=$g?>" <?=$grado===$g?'selected':''?>><?=$g?>°</option><?php endforeach;?></select></div><div class="col-md-3"><select class="form-select" name="curso"><option value="0">Todos los cursos</option><?php foreach($cursos as $c):?><option value="<?=$c['id_curso']?>" <?=$curso===(int)$c['id_curso']?'selected':''?>><?=$c['grupo']?> (<?=$c['grado']?>°)</option><?php endforeach;?></select></div><div class="col-md-3"><select class="form-select" name="materia"><option value="0">Todas las materias</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=$materia===(int)$m['id_materia']?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div><div class="col-md-1"><button class="btn btn-primary w-100" title="Filtrar"><i class="bi bi-search"></i></button></div></form></section>
<?php if($detalle):?><section class="cardx p-4 mb-4"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div><div class="small text-uppercase fw-bold text-primary">Detalle del estudiante</div><h2 class="h4 fw-bold mb-1"><?=e($detalle['nombres'].' '.$detalle['apellidos'])?></h2><div class="muted">Grado <?=$detalle['grado']?> · <?=number_format((int)$detalle['puntos'])?> XP · <?=e($detalle['nombre_nivel']??'Sin nivel')?></div></div><a class="btn btn-sm btn-outline-secondary" href="<?=e(urlAplicacion('/admin/progreso/index.php'))?>">Cerrar detalle</a></div><div class="row g-3"><?php foreach($detalleMaterias as $dm):?><div class="col-md-6 col-xl-4"><div class="detail"><div class="fw-bold mb-1"><?=e($dm['nombre'])?></div><div class="small muted mb-2"><?=$dm['completados']?> de <?=$dm['total_temas']?> temas completados</div><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar <?=progresoClass((float)$dm['progreso'])?>" style="width:<?=min(100,max(0,(float)$dm['progreso']))?>%"></div></div><strong><?=number_format((float)$dm['progreso'],0)?>%</strong></div></div></div><?php endforeach;?></div></section><?php endif;?>
<section class="cardx overflow-hidden"><div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Estudiantes</h2><p class="muted mb-0 small">Selecciona un estudiante para ver el avance por materia.</p></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Estudiante</th><th>Curso</th><th>Avance</th><th>Temas</th><th>Última actividad</th><th></th></tr></thead><tbody><?php foreach($estudiantes as $s):?><tr><td><a class="student-link" href="?<?=e(http_build_query(array_merge($_GET,['estudiante'=>(int)$s['id_usuario']])))?>"><strong><?=e($s['nombres'].' '.$s['apellidos'])?></strong></a><div class="small muted"><?=e($s['numero_documento'])?></div></td><td><?=$s['grado']?>° · <?=e($s['grupo']??'Sin curso')?></td><td class="subject-progress"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar <?=progresoClass((float)$s['progreso'])?>" style="width:<?=min(100,max(0,(float)$s['progreso']))?>%"></div></div><strong><?=number_format((float)$s['progreso'],0)?>%</strong></div></td><td><span class="badge text-bg-light border"><?=$s['temas_completados']?> completados</span><div class="small muted mt-1"><?=$s['temas_iniciados']?> iniciados</div></td><td class="small muted"><?=e(fechaCorta($s['ultima_actividad']))?></td><td><a class="btn btn-sm btn-outline-primary" href="?<?=e(http_build_query(array_merge($_GET,['estudiante'=>(int)$s['id_usuario']])))?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach;?><?php if(!$estudiantes):?><tr><td colspan="6" class="text-center py-5 muted"><i class="bi bi-inbox display-6 d-block mb-2"></i>No hay estudiantes que coincidan con los filtros.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>

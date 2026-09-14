<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$errores=[];$nombreAdmin=trim((string)($_SESSION['nombres']??'')) ?: 'Administrador';
$idMateria=filter_var($_GET['id_materia']??null,FILTER_VALIDATE_INT); if(!$idMateria||$idMateria<=0)$idMateria=null;
$gradoFiltro=(string)($_GET['grado']??''); if(!in_array($gradoFiltro,['9','10','11'],true))$gradoFiltro='';
$busqueda=trim((string)($_GET['q']??''));
$materias=[];$materiaSeleccionada=null;$temas=[];
try{$materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
if($idMateria){$st=$conexion->prepare("SELECT id_materia,nombre,descripcion FROM materias WHERE id_materia=?");$st->execute([$idMateria]);$materiaSeleccionada=$st->fetch(PDO::FETCH_ASSOC);if(!$materiaSeleccionada){$idMateria=null;$errores[]='La materia seleccionada no existe.';}}
$sql="SELECT t.id_tema,t.id_materia,t.nombre AS tema,t.descripcion,t.grado,COALESCE(t.contenido,'') AS contenido,m.nombre AS materia,
(SELECT COUNT(*) FROM recursos r WHERE r.id_tema=t.id_tema AND r.estado='Activo') total_recursos,
(SELECT ct.estado FROM contenido_temas ct WHERE ct.id_tema=t.id_tema ORDER BY ct.fecha_actualizacion DESC,ct.id_contenido DESC LIMIT 1) estado_contenido
FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE 1=1";$params=[];
if($idMateria){$sql.=" AND t.id_materia=?";$params[]=$idMateria;} if($gradoFiltro){$sql.=" AND t.grado=?";$params[]=$gradoFiltro;} if($busqueda!==''){$sql.=" AND (t.nombre LIKE ? OR t.descripcion LIKE ? OR m.nombre LIKE ?)";$q="%$busqueda%";array_push($params,$q,$q,$q);}
$sql.=" ORDER BY CAST(t.grado AS UNSIGNED),m.nombre,t.nombre";$st=$conexion->prepare($sql);$st->execute($params);$temas=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $ex){$errores[]='No fue posible cargar la información académica.';}
$total=count($temas);$porGrado=['9'=>0,'10'=>0,'11'=>0];foreach($temas as $t)$porGrado[(string)$t['grado']]++;
$urlDashboard=urlAplicacion('/admin/dashboard.php');$urlMaterias=urlAplicacion('/admin/contenidos/materias.php');$urlNuevo=urlAplicacion('/admin/contenidos/nuevo_tema.php');$urlBase=urlAplicacion('/admin/contenidos/temas.php');$urlSalir=urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temas | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.topic-card{background:#fff;border:1px solid #e4eaf2;border-radius:19px;padding:18px;box-shadow:0 8px 24px rgba(23,32,51,.045);height:100%;display:flex;flex-direction:column;transition:.18s}.topic-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(23,32,51,.08)}
.topic-icon{width:43px;height:43px;border-radius:13px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center}.grade-pill{font-size:.7rem;font-weight:800;color:#2563eb;background:#eff6ff;border-radius:999px;padding:.3rem .55rem}.topic-desc{color:#64748b;font-size:.87rem;line-height:1.55}.topic-actions{margin-top:auto;display:flex;gap:7px;flex-wrap:wrap}.topic-actions .btn{border-radius:10px;font-size:.78rem;font-weight:700}.filterbar{background:#fff;border:1px solid #e4eaf2;border-radius:20px;padding:16px;box-shadow:0 8px 24px rgba(23,32,51,.04)}.filter-chip{border:1px solid #dbe3ee;background:#fff;color:#526176;border-radius:11px;padding:.55rem .8rem;font-weight:750;font-size:.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}.filter-chip:hover{border-color:#9bb8e7}.filter-chip.active{background:#2563eb;color:#fff;border-color:#2563eb}.subject-select{max-width:330px}
</style><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">
</head><body>
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlMaterias)?>">Materias</a><a class="btn btn-outline-secondary s360-btn" href="<?=e($urlSalir)?>">Salir</a></div></div></nav>
<main class="s360-shell">
<section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Administración académica</div><h1 class="h2 mb-2"><?= $materiaSeleccionada?'Temas de '.e($materiaSeleccionada['nombre']):'Todos los temas'?></h1><p class="mb-0 opacity-75">Encuentra, edita y previsualiza cualquier tema desde un solo lugar.</p></div><div class="col-auto"><a class="btn btn-light s360-btn" href="<?=e($urlNuevo)?>"><i class="bi bi-plus-lg me-1"></i>Nuevo tema</a></div></div></section>
<?php foreach($errores as $er):?><div class="alert alert-danger border-0"><?=e($er)?></div><?php endforeach;?>
<section class="filterbar mb-4">
<form method="get" class="row g-3 align-items-end">
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Materia</label><select class="form-select subject-select" name="id_materia" onchange="this.form.submit()"><option value="">Todas las materias</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=(int)$idMateria===(int)$m['id_materia']?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div>
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Buscar tema</label><input class="form-control" name="q" value="<?=e($busqueda)?>" placeholder="Nombre, descripción o materia"></div>
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Grado</label><div class="d-flex flex-wrap gap-2"><?php foreach([''=>'Todos','9'=>'9°','10'=>'10°','11'=>'11°'] as $g=>$label):?><a class="filter-chip <?=$gradoFiltro===$g?'active':''?>" href="<?=e($urlBase.'?'.http_build_query(array_filter(['id_materia'=>$idMateria,'grado'=>$g,'q'=>$busqueda],fn($v)=>$v!==null&&$v!=='')))?>"><?=$label?> <span class="badge rounded-pill <?=$gradoFiltro===$g?'text-bg-light text-primary':'text-bg-secondary'?>"><?=$g===''?$total:$porGrado[$g]?></span></a><?php endforeach;?></div></div>
</form>
<div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top"><span class="small text-secondary"><strong><?=$total?></strong> tema(s) encontrado(s)</span><?php if($idMateria||$gradoFiltro||$busqueda):?><a class="small text-decoration-none fw-bold" href="<?=e($urlBase)?>">Limpiar filtros</a><?php endif;?></div>
</section>
<div class="row g-3">
<?php if(!$temas):?><div class="col-12"><div class="s360-empty"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-journal-x"></i></div><h2 class="h5 fw-bold">No encontramos temas</h2><p class="s360-muted mb-0">Prueba con otros filtros o crea un nuevo tema.</p></div></div><?php endif;?>
<?php foreach($temas as $t):$estado=(string)($t['estado_contenido']??'');if($estado==='')$estado=trim((string)($t['contenido']??''))!==''?'Publicado':'';?>
<div class="col-12 col-md-6 col-xl-4"><article class="topic-card">
<div class="d-flex gap-3 align-items-start mb-3"><div class="topic-icon"><i class="bi bi-journal-text"></i></div><div class="min-w-0"><div class="grade-pill d-inline-flex"><?=$t['grado']?>°</div><h2 class="h6 fw-bold mt-2 mb-1"><?=e($t['tema'])?></h2><?php if(!$materiaSeleccionada):?><div class="small text-secondary"><?=e($t['materia'])?></div><?php endif;?></div></div>
<p class="topic-desc mb-3"><?=e($t['descripcion']?:'Sin descripción.')?></p><div class="d-flex flex-wrap gap-2 mb-3"><span class="s360-chip"><i class="bi bi-paperclip"></i><?=$t['total_recursos']?> recurso(s)</span><?php if($estado==='Publicado'):?><span class="s360-chip success"><i class="bi bi-check-circle"></i>Publicado</span><?php elseif($estado==='Borrador'):?><span class="s360-chip warning"><i class="bi bi-pencil"></i>Borrador</span><?php else:?><span class="s360-chip"><i class="bi bi-file-earmark"></i>Sin contenido</span><?php endif;?></div>
<div class="topic-actions"><a class="btn btn-primary" href="informacion_tema.php?id=<?=$t['id_tema']?>"><i class="bi bi-pencil me-1"></i>Editar tema</a><a class="btn btn-outline-primary" href="editar_tema.php?id=<?=$t['id_tema']?>"><i class="bi bi-file-earmark-text me-1"></i>Contenido</a><a class="btn btn-outline-secondary" href="vista_previa_tema.php?id=<?=$t['id_tema']?>" target="_blank"><i class="bi bi-eye me-1"></i>Vista previa</a><a class="btn btn-outline-danger ms-auto" href="eliminar_tema.php?id=<?=$t['id_tema']?>" onclick="return confirm('¿Eliminar este tema?');" title="Eliminar"><i class="bi bi-trash3"></i></a></div>
</article></div>
<?php endforeach;?></div></main></body></html>

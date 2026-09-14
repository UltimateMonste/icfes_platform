<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$idTema=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$idTema||$idTema<=0){header('Location: temas.php');exit;}
if(empty($_SESSION['csrf_informacion_tema']))$_SESSION['csrf_informacion_tema']=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf_informacion_tema'];
$errores=[];$mensajes=[];$tema=null;$materias=[];
try{
 $st=$conexion->prepare("SELECT t.id_tema,t.id_materia,t.nombre,t.descripcion,t.grado,m.nombre materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=? LIMIT 1");$st->execute([$idTema]);$tema=$st->fetch(PDO::FETCH_ASSOC);
 if(!$tema){header('Location: temas.php');exit;}
 $materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $e){$errores[]='No fue posible cargar la información del tema.';}
if($_SERVER['REQUEST_METHOD']==='POST'&&$tema){
 if(!hash_equals($csrf,(string)($_POST['csrf']??'')))$errores[]='La sesión del formulario expiró. Recarga la página.';
 $nombre=trim((string)($_POST['nombre']??''));$descripcion=trim((string)($_POST['descripcion']??''));$grado=(string)($_POST['grado']??'');$idMateria=(int)($_POST['id_materia']??0);
 if($nombre==='')$errores[]='El nombre del tema es obligatorio.';elseif(mb_strlen($nombre)>150)$errores[]='El nombre no puede superar 150 caracteres.';
 if(mb_strlen($descripcion)>5000)$errores[]='La descripción es demasiado larga.';
 if(!in_array($grado,['9','10','11'],true))$errores[]='El grado seleccionado no es válido.';
 if($idMateria<=0)$errores[]='Selecciona una materia.';
 if(!$errores)try{
  $st=$conexion->prepare("SELECT COUNT(*) FROM materias WHERE id_materia=?");$st->execute([$idMateria]);if(!(int)$st->fetchColumn())$errores[]='La materia seleccionada no existe.';
  $st=$conexion->prepare("SELECT COUNT(*) FROM temas WHERE id_materia=? AND grado=? AND LOWER(nombre)=LOWER(?) AND id_tema<>?");$st->execute([$idMateria,$grado,$nombre,$idTema]);if((int)$st->fetchColumn())$errores[]='Ya existe otro tema con ese nombre en la misma materia y grado.';
  if(!$errores){$st=$conexion->prepare("UPDATE temas SET id_materia=?,nombre=?,descripcion=?,grado=? WHERE id_tema=?");$st->execute([$idMateria,$nombre,$descripcion!==''?$descripcion:null,$grado,$idTema]);$st=$conexion->prepare("SELECT t.id_tema,t.id_materia,t.nombre,t.descripcion,t.grado,m.nombre materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=?");$st->execute([$idTema]);$tema=$st->fetch(PDO::FETCH_ASSOC);$mensajes[]='Los datos del tema se actualizaron correctamente.';}
 }catch(PDOException $e){$errores[]='No fue posible actualizar el tema.';}
}
try{
 $st=$conexion->prepare("SELECT COUNT(*) FROM recursos WHERE id_tema=? AND estado='Activo'");$st->execute([$idTema]);$recursos=(int)$st->fetchColumn();
 $st=$conexion->prepare("SELECT COUNT(*) FROM progreso WHERE id_tema=?");$st->execute([$idTema]);$seguimiento=(int)$st->fetchColumn();
 $st=$conexion->prepare("SELECT estado,fecha_actualizacion FROM contenido_temas WHERE id_tema=? ORDER BY fecha_actualizacion DESC,id_contenido DESC LIMIT 1");$st->execute([$idTema]);$contenido=$st->fetch(PDO::FETCH_ASSOC)?:null;
}catch(PDOException $e){$recursos=0;$seguimiento=0;$contenido=null;}
$urlTemas=urlAplicacion('/admin/contenidos/temas.php');$urlEditor=urlAplicacion('/admin/contenidos/editar_tema.php?id='.$idTema);$urlPreview=urlAplicacion('/admin/contenidos/vista_previa_tema.php?id='.$idTema);$urlDashboard=urlAplicacion('/admin/dashboard.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Editar tema | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>.info-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:18px}.field-label{font-size:.78rem;font-weight:800;color:#526176;margin-bottom:6px}.stat-mini{padding:14px;border:1px solid #e4eaf2;border-radius:16px;background:#f8fafc}.stat-mini strong{font-size:1.3rem}@media(max-width:900px){.info-layout{grid-template-columns:1fr}}</style><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">
</head><body>
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlTemas)?>"><i class="bi bi-arrow-left me-1"></i>Temas</a></div></div></nav>
<main class="s360-shell"><section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Editar estructura</div><h1 class="h2 mb-2"><?=e($tema['nombre'])?></h1><p class="mb-0 opacity-75"><?=e($tema['materia'])?> · <?=e($tema['grado'])?>°</p></div><div class="col-auto d-flex gap-2 flex-wrap"><a class="btn btn-light s360-btn" href="<?=e($urlPreview)?>" target="_blank"><i class="bi bi-eye me-1"></i>Vista previa</a><a class="btn btn-outline-light s360-btn" href="<?=e($urlEditor)?>"><i class="bi bi-file-earmark-text me-1"></i>Editar contenido</a></div></div></section>
<?php foreach($mensajes as $m):?><div class="alert alert-success border-0"><?=e($m)?></div><?php endforeach;?><?php foreach($errores as $m):?><div class="alert alert-danger border-0"><?=e($m)?></div><?php endforeach;?>
<div class="info-layout"><section class="s360-card p-4 p-lg-5"><div class="s360-section-title mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Información del tema</div><p class="s360-muted small mb-4">Aquí se modifica lo que identifica al tema: materia, nombre, grado y descripción.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>">
<div class="mb-4"><label class="field-label">Materia</label><select class="form-select" name="id_materia" required><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=$tema['id_materia']==$m['id_materia']?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div>
<div class="mb-4"><label class="field-label">Nombre del tema</label><input class="form-control" name="nombre" maxlength="150" value="<?=e($tema['nombre'])?>" required></div>
<div class="mb-4"><label class="field-label">Grado</label><select class="form-select" name="grado" required><option value="9" <?=$tema['grado']==='9'?'selected':''?>>9° · Noveno</option><option value="10" <?=$tema['grado']==='10'?'selected':''?>>10° · Décimo</option><option value="11" <?=$tema['grado']==='11'?'selected':''?>>11° · Undécimo</option></select></div>
<div class="mb-4"><label class="field-label">Descripción</label><textarea class="form-control" name="descripcion" rows="6" maxlength="5000" placeholder="Describe qué aprenderá el estudiante en este tema."><?=e($tema['descripcion'])?></textarea></div>
<div class="d-flex justify-content-between gap-2 flex-wrap"><a class="btn btn-light border s360-btn" href="<?=e($urlTemas)?>">Cancelar</a><button class="btn btn-primary s360-btn" type="submit"><i class="bi bi-check2 me-1"></i>Guardar cambios</button></div>
</form></section>
<aside class="s360-card p-4"><div class="s360-section-title mb-3">Resumen</div><div class="stat-mini mb-2"><small class="s360-muted">Materia</small><div class="fw-bold"><?=e($tema['materia'])?></div></div><div class="stat-mini mb-2"><small class="s360-muted">Recursos activos</small><div><strong><?=$recursos?></strong></div></div><div class="stat-mini mb-2"><small class="s360-muted">Estudiantes con registro de progreso</small><div><strong><?=$seguimiento?></strong></div></div><div class="stat-mini"><small class="s360-muted">Contenido</small><div class="fw-bold"><?=e($contenido['estado']??'Sin contenido')?></div><?php if(!empty($contenido['fecha_actualizacion'])):?><small class="s360-muted"><?=e(date('d/m/Y H:i',strtotime($contenido['fecha_actualizacion'])))?></small><?php endif;?></div></aside></div></main></body></html>

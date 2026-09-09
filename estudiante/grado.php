<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';
exigirEstudiante();

$idUsuario=(int)($_SESSION['id_usuario']??0);
$grado=(string)($_GET['grado']??'');
$idMateria=(int)($_GET['id_materia']??0);
if(!in_array($grado,['9','10','11'],true)){$grado='11';}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
try{
 $sql="SELECT t.id_tema,t.nombre,t.descripcion,t.id_materia,m.nombre materia,
 COALESCE(p.porcentaje_avance,0) porcentaje_avance,
 COALESCE(p.recursos_vistos,0) recursos_vistos,COALESCE(p.evaluaciones_realizadas,0) evaluaciones_realizadas
 FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia
 LEFT JOIN progreso p ON p.id_tema=t.id_tema AND p.id_usuario=?
 WHERE t.grado=?"; $params=[$idUsuario,$grado];
 if($idMateria>0){$sql.=" AND t.id_materia=?";$params[]=$idMateria;}
 $sql.=" ORDER BY m.nombre,t.id_tema";
 $st=$conexion->prepare($sql);$st->execute($params);$temas=$st->fetchAll(PDO::FETCH_ASSOC);
 $tituloMateria='';
 if($idMateria>0){$st=$conexion->prepare("SELECT nombre FROM materias WHERE id_materia=?");$st->execute([$idMateria]);$tituloMateria=(string)$st->fetchColumn();}
}catch(Throwable $e){die('No fue posible cargar los contenidos.');}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contenidos | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#f4f7fb;color:#26364a}.navbar{background:linear-gradient(100deg,#173f80,#2467c5)}.cardx{background:#fff;border:1px solid #dce5f0;border-radius:19px;box-shadow:0 8px 25px #1f395c0c}.topic{transition:.2s}.topic:hover{transform:translateY(-3px)}.progress{height:9px}.progress-bar{background:linear-gradient(90deg,#2467c5,#65a1f0)}</style></head><body>
<nav class="navbar navbar-dark"><div class="container py-2"><a class="navbar-brand fw-bold" href="<?=h(urlAplicacion('/estudiante/dashboard.php'))?>">Studia360</a><a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/estudiante/dashboard.php'))?>"><i class="bi bi-arrow-left me-1"></i>Materias</a></div></nav>
<main class="container py-4"><div class="mb-4"><div class="text-primary fw-semibold">Grado <?=h($grado)?>°</div><h1 class="h2 fw-bold mb-1"><?=h($tituloMateria?:'Contenidos de aprendizaje')?></h1><p class="text-muted mb-0"><?=count($temas)?> tema(s) disponibles</p></div>
<div class="row g-3"><?php foreach($temas as $t):?><div class="col-12 col-md-6 col-lg-4"><div class="cardx topic p-4 h-100">
<span class="badge text-bg-light mb-3"><?=h($t['materia'])?></span><h2 class="h5 fw-bold"><?=h($t['nombre'])?></h2><p class="small text-muted"><?=h($t['descripcion']??'')?></p>
<div class="d-flex justify-content-between small mb-1"><span>Progreso</span><strong><?=number_format((float)$t['porcentaje_avance'],0)?>%</strong></div><div class="progress mb-3"><div class="progress-bar" style="width:<?=h((string)$t['porcentaje_avance'])?>%"></div></div>
<a class="btn btn-primary w-100" href="<?=h(urlAplicacion('/estudiante/tema.php?id='.(int)$t['id_tema']))?>">Estudiar tema <i class="bi bi-arrow-right ms-1"></i></a>
</div></div><?php endforeach;?>
<?php if(!$temas):?><div class="col-12"><div class="alert alert-light border">No hay temas disponibles para esta selección.</div></div><?php endif;?></div></main></body></html>

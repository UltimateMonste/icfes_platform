<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/seguridad.php';
require_once __DIR__.'/../../includes/gamificacion.php';
exigirAdmin();
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(empty($_SESSION['csrf_gam']))$_SESSION['csrf_gam']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_gam'];$msg='';$tipo='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Solicitud inválida.');
  $accion=$_POST['accion']??'';
  if($accion==='config'){
   $st=$conexion->prepare("UPDATE configuracion_gamificacion SET puntos=?,estado=? WHERE clave=?");
   $st->execute([max(0,(int)$_POST['puntos']),($_POST['estado']??'')==='Inactivo'?'Inactivo':'Activo',(string)$_POST['clave']]);
   $msg='Recompensa actualizada correctamente.';
  }elseif($accion==='nivel'){
   $st=$conexion->prepare("UPDATE niveles SET nombre=?,descripcion=?,puntos_minimos=?,puntos_maximos=?,imagen=? WHERE id_nivel=?");
   $min=max(0,(int)$_POST['min']);$max=max($min,(int)$_POST['max']);
   $st->execute([trim($_POST['nombre']),trim($_POST['descripcion']),$min,$max,trim($_POST['imagen'])?:null,(int)$_POST['id_nivel']]);
   $msg='Nivel actualizado correctamente.';
  }elseif($accion==='avatar'){
   $st=$conexion->prepare("UPDATE avatares SET nombre=?,imagen=?,puntos_requeridos=?,estado=? WHERE id_avatar=?");
   $st->execute([trim($_POST['nombre']),trim($_POST['imagen'])?:null,max(0,(int)$_POST['puntos_requeridos']),($_POST['estado']??'')==='Inactivo'?'Inactivo':'Activo',(int)$_POST['id_avatar']]);
   $msg='Avatar actualizado correctamente.';
  }elseif($accion==='insignia'){
   $st=$conexion->prepare("UPDATE insignias SET nombre=?,descripcion=?,imagen=?,criterio=?,puntos_otorgados=?,estado=? WHERE id_insignia=?");
   $st->execute([trim($_POST['nombre']),trim($_POST['descripcion']),trim($_POST['imagen'])?:null,trim($_POST['criterio']),max(0,(int)$_POST['puntos_otorgados']),($_POST['estado']??'')==='Inactiva'?'Inactiva':'Activa',(int)$_POST['id_insignia']]);
   $msg='Insignia actualizada correctamente.';
  }
 }catch(Throwable $e){$msg=$e instanceof RuntimeException?$e->getMessage():'No fue posible guardar.';$tipo='danger';}
}
$configs=$conexion->query("SELECT * FROM configuracion_gamificacion ORDER BY id_configuracion")->fetchAll(PDO::FETCH_ASSOC);
$niveles=$conexion->query("SELECT * FROM niveles ORDER BY puntos_minimos,id_nivel")->fetchAll(PDO::FETCH_ASSOC);
$avatares=$conexion->query("SELECT * FROM avatares ORDER BY puntos_requeridos,id_avatar")->fetchAll(PDO::FETCH_ASSOC);
$insignias=$conexion->query("SELECT * FROM insignias ORDER BY id_insignia")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gamificación | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#f4f7fb;color:#26364a}.navbar{background:linear-gradient(100deg,#173f80,#2467c5)}.cardx{background:#fff;border:1px solid #dce5f0;border-radius:20px;box-shadow:0 8px 25px #1f395c0c}.hero{background:linear-gradient(120deg,#2467c5,#173f80);color:#fff;border-radius:24px;padding:2rem}.item{border:1px solid #e2e8f0;border-radius:16px;padding:1rem;height:100%}.form-control,.form-select{border-radius:11px}</style></head>
<body><nav class="navbar navbar-dark"><div class="container py-2"><a class="navbar-brand fw-bold" href="<?=h(urlAplicacion('/admin/dashboard.php'))?>">Studia360</a><a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/admin/dashboard.php'))?>">Dashboard</a></div></nav>
<main class="container py-4"><section class="hero mb-4"><h1 class="h2 fw-bold">Gamificación</h1><p class="mb-0 text-white-50">Configura puntos, niveles, avatares e insignias sin modificar el resto del sistema.</p></section>
<?php if($msg):?><div class="alert alert-<?=$tipo?>"><?=h($msg)?></div><?php endif;?>
<section class="cardx p-4 mb-4"><h2 class="h4 fw-bold"><i class="bi bi-controller me-2 text-primary"></i>Recompensas por interacción</h2><p class="text-muted">Estos valores son los que utilizará el sistema al interactuar con los contenidos.</p><div class="row g-3">
<?php foreach($configs as $c):?><div class="col-12 col-lg-6"><div class="item"><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="config"><input type="hidden" name="clave" value="<?=h($c['clave'])?>">
<div class="fw-bold"><?=h($c['nombre'])?></div><div class="small text-muted mb-3"><?=h($c['descripcion'])?></div><div class="input-group"><input class="form-control" type="number" min="0" name="puntos" value="<?=$c['puntos']?>"><span class="input-group-text">puntos</span></div><select class="form-select mt-2" name="estado"><option value="Activo" <?=$c['estado']==='Activo'?'selected':''?>>Activo</option><option value="Inactivo" <?=$c['estado']==='Inactivo'?'selected':''?>>Inactivo</option></select><button class="btn btn-primary btn-sm mt-3"><i class="bi bi-save me-1"></i>Guardar</button></form></div></div><?php endforeach;?>
</div></section>
<section class="cardx p-4 mb-4"><h2 class="h4 fw-bold">Niveles</h2><div class="row g-3"><?php foreach($niveles as $n):?><div class="col-12 col-lg-6"><div class="item"><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="nivel"><input type="hidden" name="id_nivel" value="<?=$n['id_nivel']?>">
<input class="form-control mb-2" name="nombre" value="<?=h($n['nombre'])?>" required><textarea class="form-control mb-2" name="descripcion" rows="2"><?=h($n['descripcion'])?></textarea><div class="row g-2"><div class="col-6"><input class="form-control" type="number" min="0" name="min" value="<?=$n['puntos_minimos']?>"></div><div class="col-6"><input class="form-control" type="number" min="0" name="max" value="<?=$n['puntos_maximos']?>"></div></div><input class="form-control mt-2" name="imagen" value="<?=h($n['imagen'])?>" placeholder="Imagen"><button class="btn btn-outline-primary btn-sm mt-3">Guardar nivel</button></form></div></div><?php endforeach;?></div></section>
<section class="cardx p-4 mb-4"><h2 class="h4 fw-bold">Avatares</h2><div class="row g-3"><?php foreach($avatares as $a):?><div class="col-12 col-md-6 col-xl-4"><div class="item"><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="avatar"><input type="hidden" name="id_avatar" value="<?=$a['id_avatar']?>"><input class="form-control mb-2" name="nombre" value="<?=h($a['nombre'])?>" required><input class="form-control mb-2" name="imagen" value="<?=h($a['imagen'])?>"><label class="small text-muted">Puntos para desbloquear</label><input class="form-control mb-2" type="number" min="0" name="puntos_requeridos" value="<?=$a['puntos_requeridos']?>"><select class="form-select" name="estado"><option value="Activo" <?=$a['estado']==='Activo'?'selected':''?>>Activo</option><option value="Inactivo" <?=$a['estado']==='Inactivo'?'selected':''?>>Inactivo</option></select><button class="btn btn-outline-primary btn-sm mt-3">Guardar avatar</button></form></div></div><?php endforeach;?></div></section>
<section class="cardx p-4"><h2 class="h4 fw-bold">Insignias</h2><div class="row g-3"><?php foreach($insignias as $i):?><div class="col-12 col-md-6"><div class="item"><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="insignia"><input type="hidden" name="id_insignia" value="<?=$i['id_insignia']?>"><input class="form-control mb-2" name="nombre" value="<?=h($i['nombre'])?>" required><textarea class="form-control mb-2" name="descripcion" rows="2"><?=h($i['descripcion'])?></textarea><input class="form-control mb-2" name="imagen" value="<?=h($i['imagen'])?>"><input class="form-control mb-2" name="criterio" value="<?=h($i['criterio'])?>" placeholder="Criterio"><label class="small text-muted">Puntos de la insignia</label><input class="form-control mb-2" type="number" min="0" name="puntos_otorgados" value="<?=$i['puntos_otorgados']?>"><select class="form-select" name="estado"><option value="Activa" <?=$i['estado']==='Activa'?'selected':''?>>Activa</option><option value="Inactiva" <?=$i['estado']==='Inactiva'?'selected':''?>>Inactiva</option></select><button class="btn btn-outline-primary btn-sm mt-3">Guardar insignia</button></form></div></div><?php endforeach;?></div></section>
</main></body></html>

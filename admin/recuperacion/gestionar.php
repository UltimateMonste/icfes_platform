<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(empty($_SESSION['csrf_recuperacion']))$_SESSION['csrf_recuperacion']=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf_recuperacion'];
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id){header('Location:index.php');exit;}
function cargar(PDO $db,int $id):?array{$st=$db->prepare("SELECT sr.*,u.id_usuario,u.nombres,u.apellidos,u.correo,u.grado,u.numero_documento FROM solicitudes_recuperacion sr INNER JOIN usuarios u ON u.id_usuario=sr.id_usuario WHERE sr.id_solicitud=? LIMIT 1");$st->execute([$id]);$r=$st->fetch(PDO::FETCH_ASSOC);return $r?:null;}
$sol=cargar($conexion,$id);if(!$sol){header('Location:index.php');exit;}
$alerta=null;$tipo='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$alerta='La sesión de seguridad expiró. Recarga la página.';$tipo='danger';}
 else try{
  $accion=(string)($_POST['accion']??'');
  if($accion==='restablecer'){
   $hash=password_hash((string)$sol['numero_documento'],PASSWORD_DEFAULT);
   $conexion->beginTransaction();
   $st=$conexion->prepare("UPDATE usuarios SET password=?,primer_ingreso=1,fecha_cambio_password=NULL WHERE id_usuario=? AND id_rol=2");$st->execute([$hash,$sol['id_usuario']]);
   $mensaje=trim((string)($_POST['mensaje_admin']??''));if($mensaje==='')$mensaje='Tu contraseña fue restablecida a la contraseña predeterminada (número de documento). Al ingresar podrás cambiarla.';
   $st=$conexion->prepare("UPDATE solicitudes_recuperacion SET estado='Gestionada',mensaje_admin=?,fecha_gestion=NOW() WHERE id_solicitud=?");$st->execute([$mensaje,$id]);
   $conexion->commit();$sol=cargar($conexion,$id)?:$sol;$alerta='La contraseña fue restablecida a la contraseña predeterminada del estudiante.';$tipo='success';
  }elseif($accion==='cancelar'){
   $st=$conexion->prepare("UPDATE solicitudes_recuperacion SET estado='Cancelada',fecha_gestion=NOW() WHERE id_solicitud=?");$st->execute([$id]);$sol=cargar($conexion,$id)?:$sol;$alerta='La solicitud fue cancelada.';
  }
 }catch(Throwable $ex){if($conexion->inTransaction())$conexion->rollBack();$alerta='No fue posible completar la operación.';$tipo='danger';}
}
$urlIndex=urlAplicacion('/admin/recuperacion/index.php');$urlDash=urlAplicacion('/admin/dashboard.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restablecimiento | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">
<style>.security-note{background:#eff6ff;border:1px solid #d7e7ff;border-radius:17px;padding:15px}.student-card{background:#fff;border:1px solid #e4eaf2;border-radius:22px;box-shadow:0 10px 28px rgba(23,32,51,.05)}.info-line{padding:12px 0;border-bottom:1px solid #edf1f6}.info-line:last-child{border-bottom:0}</style></head><body>
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDash)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360</a><a class="btn btn-light border s360-btn" href="<?=e($urlIndex)?>"><i class="bi bi-arrow-left me-1"></i>Solicitudes</a></div></nav>
<main class="s360-shell"><section class="s360-hero mb-4"><div class="s360-kicker">Seguridad de cuentas</div><h1 class="h2 fw-bold mt-2 mb-2">Restablecer contraseña</h1><p class="mb-0 opacity-75">La administración no conoce ni define la contraseña personal del estudiante.</p></section>
<?php if($alerta):?><div class="alert alert-<?=$tipo?> border-0"><?=e($alerta)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-7"><section class="student-card p-4 p-lg-5"><div class="s360-section-title mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Restablecimiento seguro</div><p class="s360-muted small mb-4">Al confirmar, el sistema genera un hash de la contraseña predeterminada: <strong>número de documento</strong>. El administrador nunca ve la contraseña almacenada.</p>
<div class="security-note mb-4"><div class="fw-bold"><i class="bi bi-key-fill me-2"></i>¿Qué ocurrirá?</div><ul class="small mb-0 mt-2"><li>La contraseña se restablecerá al número de documento del estudiante.</li><li>Se marcará el próximo ingreso para que pueda cambiarla.</li><li>La contraseña se almacena únicamente como hash.</li></ul></div>
<?php if($sol['estado']==='Pendiente'):?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="restablecer"><label class="form-label fw-semibold">Mensaje para el estudiante <span class="text-secondary fw-normal">(opcional)</span></label><textarea class="form-control mb-3" name="mensaje_admin" rows="4" placeholder="Tu contraseña fue restablecida a tu número de documento."></textarea><button class="btn btn-primary s360-btn" type="submit" onclick="return confirm('¿Restablecer la contraseña al número de documento del estudiante?');"><i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer al número de documento</button></form><form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="cancelar"><button class="btn btn-light border s360-btn" type="submit">Cancelar solicitud</button></form><?php else:?><div class="alert alert-secondary mb-0">Esta solicitud ya fue <?=e(mb_strtolower($sol['estado']))?>.</div><?php endif;?></section></div>
<div class="col-lg-5"><aside class="student-card p-4"><div class="s360-section-title mb-3">Estudiante</div><div class="info-line"><small class="s360-muted d-block">Nombre</small><strong><?=e(trim($sol['nombres'].' '.$sol['apellidos']))?></strong></div><div class="info-line"><small class="s360-muted d-block">Correo</small><strong class="text-break"><?=e($sol['correo'])?></strong></div><div class="info-line"><small class="s360-muted d-block">Grado</small><strong><?=e($sol['grado'])?>°</strong></div><div class="info-line"><small class="s360-muted d-block">Solicitud</small><strong>#<?=$id?></strong></div><div class="info-line"><small class="s360-muted d-block">Estado</small><span class="s360-chip <?=$sol['estado']==='Pendiente'?'warning':($sol['estado']==='Gestionada'?'success':'')?>"><?=e($sol['estado'])?></span></div></aside></div></div></main></body></html>

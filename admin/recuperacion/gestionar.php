<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

function e(?string $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_recuperacion'])) {
    $_SESSION['csrf_recuperacion'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_recuperacion'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

function cargar(PDO $conexion, int $id): ?array {
    $stmt=$conexion->prepare("SELECT sr.*,u.id_usuario,u.nombres,u.apellidos,u.correo,u.grado
        FROM solicitudes_recuperacion sr
        INNER JOIN usuarios u ON u.id_usuario=sr.id_usuario
        WHERE sr.id_solicitud=:id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $x=$stmt->fetch(PDO::FETCH_ASSOC);
    return $x?:null;
}

$solicitud=cargar($conexion,$id);
if(!$solicitud){header('Location:index.php');exit;}

$alerta=null;$tipo='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){
        $alerta='La sesión de seguridad expiró.';$tipo='danger';
    }else{
        $accion=$_POST['accion']??'';
        try{
            if($accion==='guardar'){
                $nueva=(string)($_POST['nueva_password']??'');
                $confirmar=(string)($_POST['confirmar_password']??'');
                $mensaje=trim((string)($_POST['mensaje_admin']??''));

                if(strlen($nueva)<6){
                    throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
                }
                if($nueva!==$confirmar){
                    throw new RuntimeException('Las contraseñas no coinciden.');
                }

                $conexion->beginTransaction();

                $hash=password_hash($nueva,PASSWORD_DEFAULT);

                $stmt=$conexion->prepare("UPDATE usuarios SET password=:password WHERE id_usuario=:usuario");
                $stmt->execute([':password'=>$hash,':usuario'=>(int)$solicitud['id_usuario']]);

                $stmt=$conexion->prepare("UPDATE solicitudes_recuperacion
                    SET estado='Gestionada', mensaje_admin=:mensaje, fecha_gestion=NOW()
                    WHERE id_solicitud=:id");
                $stmt->execute([':mensaje'=>$mensaje!==''?$mensaje:null,':id'=>$id]);

                $conexion->commit();

                $alerta='La contraseña fue actualizada y la solicitud quedó gestionada.';
                $tipo='success';
                $solicitud=cargar($conexion,$id)??$solicitud;
            }

            if($accion==='cancelar'){
                $stmt=$conexion->prepare("UPDATE solicitudes_recuperacion SET estado='Cancelada' WHERE id_solicitud=:id");
                $stmt->execute([':id'=>$id]);
                $alerta='La solicitud fue cancelada.';$tipo='success';
                $solicitud=cargar($conexion,$id)??$solicitud;
            }
        }catch(RuntimeException $e){
            if($conexion->inTransaction())$conexion->rollBack();
            $alerta=$e->getMessage();$tipo='danger';
        }catch(PDOException $e){
            if($conexion->inTransaction())$conexion->rollBack();
            $alerta='No fue posible completar la operación.';$tipo='danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestionar recuperación | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#26364a}.top{background:linear-gradient(110deg,#173f80,#2467c5)}
.cardx{background:#fff;border:1px solid #dce5f0;border-radius:22px;box-shadow:0 10px 28px rgba(31,57,92,.06)}
.info{padding:.8rem 0;border-bottom:1px solid #edf1f5}.form-control{border-radius:13px;padding:.75rem}.form-control:focus{box-shadow:0 0 0 .25rem rgba(36,103,197,.1)}
</style>
</head>
<body>
<nav class="navbar navbar-dark top"><div class="container">
<a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i>Studia360</a>
<a class="btn btn-light btn-sm" href="index.php"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div></nav>

<main class="container py-5">
<div class="row g-4">
<div class="col-lg-8">

<div class="cardx p-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<div><div class="small text-uppercase text-muted">Solicitud #<?= (int)$id ?></div><h1 class="h3 fw-bold mb-0">Cambiar contraseña</h1></div>
<span class="badge text-bg-<?= $solicitud['estado']==='Pendiente'?'warning':($solicitud['estado']==='Gestionada'?'success':'secondary') ?>"><?= e($solicitud['estado']) ?></span>
</div>

<?php if($alerta): ?><div class="alert alert-<?= e($tipo) ?>"><?= e($alerta) ?></div><?php endif; ?>

<?php if($solicitud['estado']==='Pendiente'): ?>
<form method="POST">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="accion" value="guardar">

<div class="mb-3">
<label class="form-label fw-semibold">Nueva contraseña</label>
<input type="password" class="form-control" name="nueva_password" minlength="6" required autocomplete="new-password">
</div>

<div class="mb-3">
<label class="form-label fw-semibold">Confirmar contraseña</label>
<input type="password" class="form-control" name="confirmar_password" minlength="6" required autocomplete="new-password">
</div>

<div class="mb-4">
<label class="form-label fw-semibold">Mensaje para el estudiante <span class="text-muted fw-normal">(opcional)</span></label>
<textarea class="form-control" name="mensaje_admin" rows="5" placeholder="Ejemplo: Tu contraseña fue actualizada. Recuerda guardarla en un lugar seguro."></textarea>
</div>

<button class="btn btn-primary px-4" type="submit"><i class="bi bi-key-fill me-1"></i>Actualizar contraseña</button>
</form>

<form method="POST" class="mt-3" onsubmit="return confirm('¿Cancelar esta solicitud?');">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="accion" value="cancelar">
<button class="btn btn-outline-secondary" type="submit">Cancelar solicitud</button>
</form>

<?php else: ?>
<div class="alert alert-info mb-0">
Esta solicitud ya fue <?= e(mb_strtolower($solicitud['estado'])) ?>.
<?php if(!empty($solicitud['mensaje_admin'])): ?><hr><strong>Mensaje enviado:</strong><br><?= nl2br(e($solicitud['mensaje_admin'])) ?><?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<div class="col-lg-4">
<div class="cardx p-4">
<h2 class="h5 fw-bold mb-4"><i class="bi bi-person-circle text-primary me-2"></i>Estudiante</h2>
<div class="info"><small class="text-muted d-block">Nombre</small><strong><?= e(trim($solicitud['nombres'].' '.$solicitud['apellidos'])) ?></strong></div>
<div class="info"><small class="text-muted d-block">Correo</small><strong class="text-break"><?= e($solicitud['correo']) ?></strong></div>
<div class="info"><small class="text-muted d-block">Grado</small><strong><?= e((string)$solicitud['grado']) ?>°</strong></div>
<div class="info"><small class="text-muted d-block">Solicitada</small><strong><?= e(date('d/m/Y H:i',strtotime($solicitud['fecha_solicitud']))) ?></strong></div>
</div>
</div>
</div>
</main>
</body>
</html>

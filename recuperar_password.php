<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/conexion.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$mensaje = '';
$tipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim((string)($_POST['numero_documento'] ?? ''));

    if ($documento === '') {
        $mensaje = 'Ingresa tu número de documento.';
        $tipo = 'danger';
    } else {
        try {
            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombres, apellidos, numero_documento
                 FROM usuarios
                 WHERE numero_documento = :documento
                 AND id_rol = 2
                 LIMIT 1"
            );
            $stmt->execute([':documento' => $documento]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $mensaje = 'No encontramos un estudiante registrado con ese número de documento.';
                $tipo = 'danger';
            } else {
                $pendiente = $conexion->prepare(
                    "SELECT id_solicitud FROM solicitudes_recuperacion
                     WHERE id_usuario = :id_usuario AND estado = 'Pendiente'
                     LIMIT 1"
                );
                $pendiente->execute([':id_usuario' => (int)$usuario['id_usuario']]);

                if ($pendiente->fetch()) {
                    $mensaje = 'Ya existe una solicitud pendiente para tu cuenta. El administrador la revisará.';
                    $tipo = 'warning';
                } else {
                    $insertar = $conexion->prepare(
                        "INSERT INTO solicitudes_recuperacion
                         (id_usuario, estado, fecha_solicitud)
                         VALUES (:id_usuario, 'Pendiente', NOW())"
                    );
                    $insertar->execute([':id_usuario' => (int)$usuario['id_usuario']]);

                    $mensaje = 'Solicitud enviada correctamente. El administrador revisará tu cuenta y gestionará el cambio.';
                    $tipo = 'success';
                }
            }
        } catch (PDOException $ex) {
            $mensaje = 'No fue posible enviar la solicitud. Verifica que la tabla de recuperación exista.';
            $tipo = 'danger';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar contraseña | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--p:#2467c5;--d:#173f80}
body{min-height:100vh;background:radial-gradient(circle at 10% 10%,rgba(70,135,230,.18),transparent 30%),radial-gradient(circle at 90% 90%,rgba(23,63,128,.13),transparent 30%),#f4f7fb}
.cardx{border:0;border-radius:28px;overflow:hidden;box-shadow:0 25px 70px rgba(23,63,128,.15)}
.side{padding:3rem;color:#fff;background:linear-gradient(135deg,var(--d),var(--p))}
.icon{width:76px;height:76px;border-radius:24px;background:rgba(255,255,255,.13);display:flex;align-items:center;justify-content:center;font-size:2rem}
.main{padding:3rem}.form-control{min-height:54px;border-radius:14px;border-color:#dbe4ef}.input-group-text{border-radius:14px 0 0 14px;background:#fff;border-color:#dbe4ef;color:var(--p)}.input-group .form-control{border-left:0}.btnx{min-height:54px;border:0;border-radius:14px;font-weight:700;background:linear-gradient(120deg,var(--p),#3d7fd8)}
.step{display:flex;gap:.7rem;align-items:center;margin-top:1rem;color:rgba(255,255,255,.82)}.step i{width:35px;height:35px;border-radius:11px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center}
</style>
</head>
<body>
<div class="container py-4 py-lg-5"><div class="row min-vh-100 align-items-center justify-content-center"><div class="col-12 col-lg-9">
<div class="card cardx"><div class="row g-0">
<div class="col-lg-5"><div class="side h-100 d-flex flex-column justify-content-center">
<div class="icon mb-4"><i class="bi bi-shield-lock-fill"></i></div>
<div class="small text-uppercase fw-semibold opacity-75">Recuperación de cuenta</div>
<h1 class="h2 fw-bold mt-2">¿Problemas para entrar?</h1>
<p class="text-white-50">Solicita al administrador el cambio de contraseña de forma segura.</p>
<div class="step"><i class="bi bi-1-circle-fill"></i><span>Identifica tu cuenta.</span></div>
<div class="step"><i class="bi bi-2-circle-fill"></i><span>El administrador revisa la solicitud.</span></div>
<div class="step"><i class="bi bi-3-circle-fill"></i><span>Recibes el nuevo acceso.</span></div>
</div></div>
<div class="col-lg-7"><div class="main">
<a href="login.php" class="btn btn-sm btn-outline-secondary mb-4"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
<h2 class="h3 fw-bold mb-2">Recuperar contraseña</h2>
<p class="text-muted mb-4">Escribe el <strong>número de documento</strong> con el que ingresas a Studia360.</p>
<?php if($mensaje): ?><div class="alert alert-<?=e($tipo)?>"><i class="bi bi-info-circle-fill me-2"></i><?=e($mensaje)?></div><?php endif;?>
<form method="POST">
<label for="numero_documento" class="form-label fw-semibold">Número de documento</label>
<div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-person-vcard-fill"></i></span><input id="numero_documento" name="numero_documento" class="form-control" required inputmode="numeric" autocomplete="username" placeholder="Ingresa tu documento" value="<?=e($_POST['numero_documento']??'')?>"></div>
<div class="form-text mb-4">No necesitas ingresar tu correo electrónico.</div>
<button class="btn btn-primary btnx w-100" type="submit"><i class="bi bi-send-fill me-2"></i>Enviar solicitud al administrador</button>
</form>
<div class="text-center mt-4"><small class="text-muted"><i class="bi bi-shield-check me-1"></i>El administrador es quien autoriza el cambio de contraseña.</small></div>
</div></div>
</div></div></div></div></div>
</body></html>
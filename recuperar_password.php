<?php
declare(strict_types=1);

require_once __DIR__ . '/config/conexion.php';

function e(?string $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function urlBase(): string {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($script === '/' ? '' : $script, '/');
}

$mensaje = null;
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim((string)($_POST['identificador'] ?? ''));

    if ($identificador === '') {
        $mensaje = 'Ingresa tu correo electrónico o usuario.';
        $tipoMensaje = 'danger';
    } else {
        try {
            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombres, apellidos, correo
                 FROM usuarios
                 WHERE correo = :identificador
                 LIMIT 1"
            );
            $stmt->execute([':identificador' => $identificador]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $mensaje = 'No encontramos una cuenta registrada con los datos proporcionados.';
                $tipoMensaje = 'danger';
            } else {
                $verificar = $conexion->prepare(
                    "SELECT id_solicitud
                     FROM solicitudes_recuperacion
                     WHERE id_usuario = :id_usuario
                     AND estado = 'Pendiente'
                     LIMIT 1"
                );
                $verificar->execute([':id_usuario' => (int)$usuario['id_usuario']]);

                if ($verificar->fetch()) {
                    $mensaje = 'Ya tienes una solicitud de recuperación pendiente. El administrador la revisará próximamente.';
                    $tipoMensaje = 'warning';
                } else {
                    $insertar = $conexion->prepare(
                        "INSERT INTO solicitudes_recuperacion
                         (id_usuario, estado, fecha_solicitud)
                         VALUES (:id_usuario, 'Pendiente', NOW())"
                    );
                    $insertar->execute([':id_usuario' => (int)$usuario['id_usuario']]);

                    $mensaje = 'Tu solicitud fue enviada correctamente. El administrador revisará tu cuenta y gestionará el cambio de contraseña.';
                    $tipoMensaje = 'success';
                }
            }
        } catch (PDOException $e) {
            $mensaje = 'Ocurrió un problema al procesar la solicitud. Verifica que la tabla de recuperación esté creada.';
            $tipoMensaje = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña | Studia360</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root{
            --primary:#2467c5;
            --dark:#173f80;
        }

        body{
            min-height:100vh;
            display:flex;
            align-items:center;
            background:
                radial-gradient(circle at 15% 20%, rgba(70,135,230,.18), transparent 30%),
                radial-gradient(circle at 85% 80%, rgba(31,95,180,.14), transparent 30%),
                #f4f7fb;
        }

        .recovery-card{
            border:0;
            border-radius:28px;
            overflow:hidden;
            box-shadow:0 25px 70px rgba(23,63,128,.15);
        }

        .brand-side{
            min-height:100%;
            padding:3rem;
            color:white;
            background:
                radial-gradient(circle at 80% 20%,rgba(255,255,255,.14),transparent 25%),
                linear-gradient(135deg,var(--dark),var(--primary));
        }

        .brand-icon{
            width:76px;height:76px;
            display:flex;align-items:center;justify-content:center;
            border-radius:24px;
            background:rgba(255,255,255,.14);
            font-size:2rem;
        }

        .form-side{padding:3rem;}

        .form-control{
            min-height:50px;
            border-radius:14px;
            border-color:#dce5f0;
        }

        .form-control:focus{
            border-color:#7aa8e6;
            box-shadow:0 0 0 .25rem rgba(36,103,197,.10);
        }

        @media(max-width:991px){
            .brand-side{padding:2rem;}
            .form-side{padding:2rem;}
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="card recovery-card mx-auto" style="max-width:980px;">
        <div class="row g-0">

            <div class="col-lg-5">
                <div class="brand-side h-100 d-flex flex-column justify-content-center">
                    <div class="brand-icon mb-4">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>

                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">
                        Recuperación de cuenta
                    </div>

                    <h1 class="fw-bold mb-3">Recupera el acceso a Studia360</h1>

                    <p class="text-white-50 mb-0">
                        Envía una solicitud y el administrador gestionará el cambio de tu contraseña.
                    </p>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="form-side">
                    <a href="login.php" class="btn btn-sm btn-outline-secondary mb-4">
                        <i class="bi bi-arrow-left me-1"></i>
                        Volver al inicio de sesión
                    </a>

                    <h2 class="h3 fw-bold mb-2">¿Olvidaste tu contraseña?</h2>

                    <p class="text-muted mb-4">
                        Ingresa el correo asociado a tu cuenta para enviar una solicitud al administrador.
                    </p>

                    <?php if ($mensaje !== null): ?>
                        <div class="alert alert-<?= e($tipoMensaje) ?> shadow-sm">
                            <?= e($mensaje) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label for="identificador" class="form-label fw-semibold">
                                Correo electrónico
                            </label>

                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-envelope text-primary"></i>
                                </span>

                                <input
                                    type="email"
                                    class="form-control border-start-0"
                                    id="identificador"
                                    name="identificador"
                                    required
                                    placeholder="tu@correo.com"
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold">
                            <i class="bi bi-send me-2"></i>
                            Enviar solicitud
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Por seguridad, la contraseña solo puede ser modificada por el administrador.
                        </small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>

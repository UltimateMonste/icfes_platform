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
/* ===== Personalización Studia360 ===== */
:root{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff;--s360-bg:#f6f7fb;--s360-card:#fff;--s360-text:#182235;--s360-muted:#68758a;--s360-line:#dfe5ef}
body.s360-blue{--s360-primary:#2563eb;--s360-primary-2:#4f46e5;--s360-soft:#eff6ff}
body.s360-orange{--s360-primary:#f97316;--s360-primary-2:#ea580c;--s360-soft:#fff3e8}
body.s360-purple{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff}
body.s360-green{--s360-primary:#10b981;--s360-primary-2:#059669;--s360-soft:#ecfdf5}
body.s360-dark{--s360-bg:#0b1220;--s360-card:#172236;--s360-text:#eef2f7;--s360-muted:#9aa8bb;--s360-line:#2b3850}

.s360-login-bg{position:fixed;inset:0;z-index:-10;overflow:hidden;background:radial-gradient(circle at 10% 15%,color-mix(in srgb,var(--s360-primary) 17%,transparent),transparent 30rem),radial-gradient(circle at 90% 80%,color-mix(in srgb,var(--s360-primary-2) 15%,transparent),transparent 32rem),var(--s360-bg);transition:background .35s}
.s360-login-bg:before,.s360-login-bg:after{content:"";position:absolute;width:430px;height:430px;border-radius:50%;border:1px solid color-mix(in srgb,var(--s360-primary) 20%,transparent);animation:s360Float 14s ease-in-out infinite}
.s360-login-bg:before{left:-150px;top:-170px;box-shadow:0 0 0 42px color-mix(in srgb,var(--s360-primary) 4%,transparent),0 0 0 88px color-mix(in srgb,var(--s360-primary) 2%,transparent)}
.s360-login-bg:after{right:-170px;bottom:-190px;animation-delay:-7s;box-shadow:0 0 0 48px color-mix(in srgb,var(--s360-primary-2) 4%,transparent),0 0 0 96px color-mix(in srgb,var(--s360-primary-2) 2%,transparent)}
.s360-particle{position:absolute;width:7px;height:7px;border-radius:50%;background:var(--s360-primary);opacity:.35;box-shadow:0 0 20px color-mix(in srgb,var(--s360-primary) 60%,transparent);animation:s360Drift 11s ease-in-out infinite}
.s360-particle:nth-child(1){left:17%;top:25%;animation-duration:12s}.s360-particle:nth-child(2){left:73%;top:19%;animation-duration:15s;animation-delay:-4s}.s360-particle:nth-child(3){left:31%;top:78%;animation-duration:14s;animation-delay:-8s}.s360-particle:nth-child(4){left:83%;top:63%;animation-duration:10s;animation-delay:-2s}.s360-particle:nth-child(5){left:54%;top:10%;animation-duration:16s;animation-delay:-10s}
@keyframes s360Float{0%,100%{transform:translate(0,0) rotate(0)}50%{transform:translate(20px,18px) rotate(7deg)}}
@keyframes s360Drift{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(18px,-24px) scale(1.35)}}

.s360-theme-button{position:fixed;right:22px;bottom:22px;z-index:100;width:50px;height:50px;border:0;border-radius:16px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--s360-primary),var(--s360-primary-2));box-shadow:0 14px 30px color-mix(in srgb,var(--s360-primary) 28%,transparent);cursor:pointer;transition:.2s}
.s360-theme-button:hover{transform:translateY(-3px) rotate(4deg)}
.s360-theme-panel{position:fixed;right:22px;bottom:82px;z-index:101;width:292px;padding:16px;border:1px solid var(--s360-line);border-radius:20px;background:var(--s360-card);color:var(--s360-text);box-shadow:0 24px 60px rgba(0,0,0,.18);opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px) scale(.98);transition:.2s}
.s360-theme-panel.open{opacity:1;visibility:visible;pointer-events:auto;transform:none}
.s360-theme-title{font-size:.88rem;font-weight:800}.s360-theme-sub{font-size:.68rem;color:var(--s360-muted);margin:3px 0 12px}
.s360-theme-colors{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.s360-theme-option{border:1px solid var(--s360-line);border-radius:13px;padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left}
.s360-theme-option:hover,.s360-theme-option.active{border-color:var(--s360-primary);background:var(--s360-soft)}
.s360-swatch{height:24px;border-radius:8px;margin-bottom:6px}.s360-theme-option strong{display:block;font-size:.68rem}.s360-theme-option small{font-size:.58rem;color:var(--s360-muted)}
.s360-mode-button{width:100%;margin-top:8px;border:1px solid var(--s360-line);border-radius:13px;padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left;font-size:.7rem;font-weight:700}
.s360-mode-button:hover{background:var(--s360-soft);color:var(--s360-primary)}

body.s360-dark .cardx,body.s360-dark .password-card{background:var(--s360-card)!important;color:var(--s360-text)}
body.s360-dark .form-panel,body.s360-dark .main{background:var(--s360-card)!important;color:var(--s360-text)}
body.s360-dark .form-control,body.s360-dark .input-group-text,body.s360-dark .toggle,body.s360-dark .password-toggle{background:#111a2a!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-dark .form-control::placeholder{color:#748299}body.s360-dark .text-muted{color:var(--s360-muted)!important}
body.s360-dark .form-label,body.s360-dark h1,body.s360-dark h2,body.s360-dark h3{color:var(--s360-text)}
body.s360-dark .security-note{background:#111a2a!important;color:var(--s360-muted)!important;border-color:var(--s360-line)!important}
body.s360-dark .alert-danger{background:#3a202a!important;color:#ffd9df!important;border-color:#6b3545!important}
body.s360-dark .alert-warning{background:#3a301b!important;color:#ffe7a3!important;border-color:#69551f!important}
body.s360-dark .alert-success{background:#14372d!important;color:#b9f3dc!important;border-color:#23604d!important}
body.s360-dark .eyebrow{background:color-mix(in srgb,var(--s360-primary) 14%,#111a2a)!important;color:#c9b8ff!important}
@media(max-width:575px){.s360-theme-button{right:14px;bottom:14px}.s360-theme-panel{right:12px;bottom:72px;width:min(292px,calc(100vw - 24px))}}

:root{--p:#2467c5;--d:#173f80}
body{min-height:100vh;background:radial-gradient(circle at 10% 10%,rgba(70,135,230,.18),transparent 30%),radial-gradient(circle at 90% 90%,rgba(23,63,128,.13),transparent 30%),#f4f7fb}
.cardx{border:0;border-radius:28px;overflow:hidden;box-shadow:0 25px 70px rgba(23,63,128,.15)}
.side{padding:3rem;color:#fff;background:linear-gradient(135deg,var(--d),var(--p))}
.icon{width:76px;height:76px;border-radius:24px;background:rgba(255,255,255,.13);display:flex;align-items:center;justify-content:center;font-size:2rem}
.main{padding:3rem}.form-control{min-height:54px;border-radius:14px;border-color:#dbe4ef}.input-group-text{border-radius:14px 0 0 14px;background:#fff;border-color:#dbe4ef;color:var(--p)}.input-group .form-control{border-left:0}.btnx{min-height:54px;border:0;border-radius:14px;font-weight:700;background:linear-gradient(120deg,var(--p),#3d7fd8)}
.step{display:flex;gap:.7rem;align-items:center;margin-top:1rem;color:rgba(255,255,255,.82)}.step i{width:35px;height:35px;border-radius:11px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center}

/* ===== Studia360 · tema aplicado a toda la interfaz ===== */
:root{
    --s360-primary:#8b5cf6;
    --s360-primary-2:#7c3aed;
    --s360-soft:#f3efff;
    --s360-bg:#f6f7fb;
    --s360-card:#ffffff;
    --s360-input:#ffffff;
    --s360-text:#182235;
    --s360-muted:#68758a;
    --s360-line:#dfe5ef;
    --s360-panel-tint:#faf9ff;
    --s360-shadow:rgba(43,35,80,.15);
}
body.s360-blue{--s360-primary:#2563eb;--s360-primary-2:#4f46e5;--s360-soft:#eff6ff;--s360-panel-tint:#f8faff}
body.s360-orange{--s360-primary:#f97316;--s360-primary-2:#ea580c;--s360-soft:#fff3e8;--s360-panel-tint:#fffaf6}
body.s360-purple{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff;--s360-panel-tint:#faf9ff}
body.s360-green{--s360-primary:#10b981;--s360-primary-2:#059669;--s360-soft:#ecfdf5;--s360-panel-tint:#f7fdfa}

body.s360-dark{
    --s360-bg:#0b1220;
    --s360-card:#172236;
    --s360-input:#111a2a;
    --s360-text:#eef2f7;
    --s360-muted:#9aa8bb;
    --s360-line:#2b3850;
    --s360-panel-tint:#1b2940;
    --s360-shadow:rgba(0,0,0,.36);
}

/* Fondo animado */
.s360-login-bg{
    position:fixed;inset:0;z-index:-10;overflow:hidden;
    background:
      radial-gradient(circle at 10% 15%,color-mix(in srgb,var(--s360-primary) 17%,transparent),transparent 30rem),
      radial-gradient(circle at 90% 80%,color-mix(in srgb,var(--s360-primary-2) 15%,transparent),transparent 32rem),
      var(--s360-bg);
    transition:background .35s ease;
}
.s360-login-bg:before,.s360-login-bg:after{
    content:"";position:absolute;width:430px;height:430px;border-radius:50%;
    border:1px solid color-mix(in srgb,var(--s360-primary) 20%,transparent);
    animation:s360Float 14s ease-in-out infinite;
}
.s360-login-bg:before{left:-150px;top:-170px;box-shadow:0 0 0 42px color-mix(in srgb,var(--s360-primary) 4%,transparent),0 0 0 88px color-mix(in srgb,var(--s360-primary) 2%,transparent)}
.s360-login-bg:after{right:-170px;bottom:-190px;animation-delay:-7s;box-shadow:0 0 0 48px color-mix(in srgb,var(--s360-primary-2) 4%,transparent),0 0 0 96px color-mix(in srgb,var(--s360-primary-2) 2%,transparent)}
.s360-particle{position:absolute;width:7px;height:7px;border-radius:50%;background:var(--s360-primary);opacity:.35;box-shadow:0 0 20px color-mix(in srgb,var(--s360-primary) 60%,transparent);animation:s360Drift 11s ease-in-out infinite}
.s360-particle:nth-child(1){left:17%;top:25%;animation-duration:12s}
.s360-particle:nth-child(2){left:73%;top:19%;animation-duration:15s;animation-delay:-4s}
.s360-particle:nth-child(3){left:31%;top:78%;animation-duration:14s;animation-delay:-8s}
.s360-particle:nth-child(4){left:83%;top:63%;animation-duration:10s;animation-delay:-2s}
.s360-particle:nth-child(5){left:54%;top:10%;animation-duration:16s;animation-delay:-10s}
@keyframes s360Float{0%,100%{transform:translate(0,0) rotate(0)}50%{transform:translate(20px,18px) rotate(7deg)}}
@keyframes s360Drift{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(18px,-24px) scale(1.35)}}

/* Personalización */
.s360-theme-button{
    position:fixed;right:22px;bottom:22px;z-index:100;width:50px;height:50px;
    border:0;border-radius:16px;display:grid;place-items:center;color:#fff;
    background:linear-gradient(135deg,var(--s360-primary),var(--s360-primary-2));
    box-shadow:0 14px 30px color-mix(in srgb,var(--s360-primary) 28%,transparent);
    cursor:pointer;transition:.2s;
}
.s360-theme-button:hover{transform:translateY(-3px) rotate(4deg)}
.s360-theme-panel{
    position:fixed;right:22px;bottom:82px;z-index:101;width:292px;padding:16px;
    border:1px solid var(--s360-line);border-radius:20px;background:var(--s360-card);
    color:var(--s360-text);box-shadow:0 24px 60px rgba(0,0,0,.18);
    opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px) scale(.98);transition:.2s;
}
.s360-theme-panel.open{opacity:1;visibility:visible;pointer-events:auto;transform:none}
.s360-theme-title{font-size:.88rem;font-weight:800}
.s360-theme-sub{font-size:.68rem;color:var(--s360-muted);margin:3px 0 12px}
.s360-theme-colors{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.s360-theme-option{
    border:1px solid var(--s360-line);border-radius:13px;padding:9px;
    background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left;
}
.s360-theme-option:hover,.s360-theme-option.active{border-color:var(--s360-primary);background:var(--s360-soft)}
.s360-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.s360-theme-option strong{display:block;font-size:.68rem}
.s360-theme-option small{font-size:.58rem;color:var(--s360-muted)}
.s360-mode-button{
    width:100%;margin-top:8px;border:1px solid var(--s360-line);border-radius:13px;
    padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;
    text-align:left;font-size:.7rem;font-weight:700;
}
.s360-mode-button:hover{background:var(--s360-soft);color:var(--s360-primary)}

/* ===== Aplicación del color a la ventana principal ===== */
.cardx,.password-card{
    background:var(--s360-card)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 18%,var(--s360-line))!important;
    box-shadow:0 28px 75px var(--s360-shadow)!important;
}
.form-panel,.main{
    background:var(--s360-panel-tint)!important;
    color:var(--s360-text)!important;
    transition:background .3s ease,color .3s ease;
}
.form-control,.input-group-text,.toggle,.password-toggle{
    background:var(--s360-input)!important;
    color:var(--s360-text)!important;
    border-color:var(--s360-line)!important;
}
.form-control::placeholder{color:var(--s360-muted)!important}
.form-label,h1,h2,h3{color:var(--s360-text)}
.text-muted{color:var(--s360-muted)!important}
.eyebrow,.badgex{
    background:var(--s360-soft)!important;
    color:var(--s360-primary)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 18%,transparent)!important;
}
.recover{color:var(--s360-primary)!important}
.btnlogin,.btn-save,.btnx{
    background:linear-gradient(120deg,var(--s360-primary),var(--s360-primary-2))!important;
    border-color:transparent!important;
    box-shadow:0 12px 24px color-mix(in srgb,var(--s360-primary) 23%,transparent)!important;
}
.input-group:focus-within{
    box-shadow:0 0 0 .25rem color-mix(in srgb,var(--s360-primary) 11%,transparent)!important;
}
.input-group-text{color:var(--s360-primary)!important}
.toggle:hover,.password-toggle:hover{color:var(--s360-primary)!important;background:var(--s360-soft)!important}
.security-note{
    background:var(--s360-soft)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 13%,var(--s360-line))!important;
    color:var(--s360-muted)!important;
}
.password-strength{background:color-mix(in srgb,var(--s360-line) 65%,var(--s360-bg))!important}

/* El panel de marca también sigue el color seleccionado */
.panel,.brand-panel,.side{
    background:
      radial-gradient(circle at 90% 8%,rgba(255,255,255,.16),transparent 13rem),
      linear-gradient(135deg,var(--s360-primary-2),var(--s360-primary))!important;
}
body.s360-dark .form-panel,
body.s360-dark .main{
    background:var(--s360-panel-tint)!important;
}
body.s360-dark .cardx,
body.s360-dark .password-card{
    background:var(--s360-card)!important;
}
body.s360-dark .form-control,
body.s360-dark .input-group-text,
body.s360-dark .toggle,
body.s360-dark .password-toggle{
    background:var(--s360-input)!important;
}
body.s360-dark .security-note{
    background:color-mix(in srgb,var(--s360-primary) 8%,var(--s360-input))!important;
}
body.s360-dark .alert-danger{background:#3a202a!important;color:#ffd9df!important;border-color:#6b3545!important}
body.s360-dark .alert-warning{background:#3a301b!important;color:#ffe7a3!important;border-color:#69551f!important}
body.s360-dark .alert-success{background:#14372d!important;color:#b9f3dc!important;border-color:#23604d!important}

@media(max-width:575px){
    .s360-theme-button{right:14px;bottom:14px}
    .s360-theme-panel{right:12px;bottom:72px;width:min(292px,calc(100vw - 24px))}
}

</style>
</head>
<body>

<div class="s360-login-bg" aria-hidden="true"><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span></div>
<button type="button" class="s360-theme-button" id="s360ThemeButton" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div class="s360-theme-panel" id="s360ThemePanel">
  <div class="s360-theme-title">Personaliza Studia360</div>
  <div class="s360-theme-sub">Tu elección se mantiene en toda la plataforma.</div>
  <div class="s360-theme-colors">
    <button type="button" class="s360-theme-option" data-s360-theme="blue"><div class="s360-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="orange"><div class="s360-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="purple"><div class="s360-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Creativo</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="green"><div class="s360-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
  </div>
  <button type="button" class="s360-mode-button" id="s360ModeButton"></button>
</div>

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

<script>
(function(){
 const KEY="studia360_theme",body=document.body,panel=document.getElementById("s360ThemePanel"),toggle=document.getElementById("s360ThemeButton"),mode=document.getElementById("s360ModeButton");
 let state={theme:"purple",mode:"dark"};
 try{const raw=localStorage.getItem(KEY);if(raw){const x=JSON.parse(raw);if(typeof x==="string"){state.theme=["blue","orange","purple","green"].includes(x)?x:"purple"}else if(x){state.theme=["blue","orange","purple","green"].includes(x.theme)?x.theme:"purple";state.mode=x.mode==="light"?"light":"dark"}}}catch(e){}
 function apply(){body.classList.remove("s360-blue","s360-orange","s360-purple","s360-green","s360-dark");body.classList.add("s360-"+state.theme);if(state.mode==="dark")body.classList.add("s360-dark");document.querySelectorAll("[data-s360-theme]").forEach(b=>b.classList.toggle("active",b.dataset.s360Theme===state.theme));if(mode)mode.innerHTML=state.mode==="dark"?'<i class="bi bi-moon-stars me-2"></i>Modo oscuro':'<i class="bi bi-sun me-2"></i>Modo claro';try{localStorage.setItem(KEY,JSON.stringify(state))}catch(e){}}
 document.querySelectorAll("[data-s360-theme]").forEach(b=>b.addEventListener("click",()=>{state.theme=b.dataset.s360Theme;apply()}));
 mode?.addEventListener("click",()=>{state.mode=state.mode==="dark"?"light":"dark";apply()});
 toggle?.addEventListener("click",e=>{e.stopPropagation();panel?.classList.toggle("open")});
 document.addEventListener("click",e=>{if(panel&&!panel.contains(e.target)&&!toggle.contains(e.target))panel.classList.remove("open")});
 apply();
})();
</script>

</body></html>
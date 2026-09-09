<?php
session_start();

if (isset($_SESSION['id_usuario'])) {
    if ((int)($_SESSION['id_rol'] ?? 0) === 1) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: estudiante/dashboard.php');
    }
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--p:#2467c5;--d:#173f80;--bg:#f4f7fb}
body{min-height:100vh;background:radial-gradient(circle at 10% 10%,rgba(70,135,230,.2),transparent 30%),radial-gradient(circle at 90% 90%,rgba(23,63,128,.15),transparent 30%),var(--bg)}
.cardx{border:0;border-radius:30px;overflow:hidden;box-shadow:0 28px 80px rgba(23,63,128,.16)}
.panel{padding:3.3rem;color:#fff;background:linear-gradient(135deg,var(--d),var(--p));position:relative;overflow:hidden}
.panel:after{content:"";position:absolute;width:300px;height:300px;border:55px solid rgba(255,255,255,.05);border-radius:50%;right:-150px;bottom:-150px}
.panel>*{position:relative;z-index:1}
.logo{width:78px;height:78px;border-radius:24px;background:rgba(255,255,255,.13);display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:2rem}
.form-panel{padding:3.3rem}
.badgex{display:inline-flex;padding:.45rem .8rem;border-radius:999px;background:#eef5ff;color:var(--p);font-size:.78rem;font-weight:700}
.form-control{min-height:54px;border-radius:14px;border-color:#dbe4ef}.input-group-text{border-radius:14px 0 0 14px;background:#fff;border-color:#dbe4ef;color:var(--p)}.input-group .form-control{border-left:0}.toggle{border:1px solid #dbe4ef;border-left:0;border-radius:0 14px 14px 0;background:#fff}.input-group:focus-within{box-shadow:0 0 0 .25rem rgba(36,103,197,.1);border-radius:14px}.btnlogin{min-height:54px;border:0;border-radius:14px;font-weight:700;background:linear-gradient(120deg,var(--p),#3d7fd8);box-shadow:0 12px 24px rgba(36,103,197,.22)}.recover{color:var(--p);font-weight:600;text-decoration:none}
.feature{display:flex;gap:.7rem;align-items:center;margin-top:1rem;color:rgba(255,255,255,.84)}.feature i{width:35px;height:35px;border-radius:11px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center}
@media(max-width:991px){.panel,.form-panel{padding:2.3rem}}
</style>
</head>
<body>
<div class="container py-4">
<div class="row min-vh-100 align-items-center justify-content-center">
<div class="col-12 col-lg-10 col-xl-9">
<div class="card cardx"><div class="row g-0">
<div class="col-lg-5"><div class="panel h-100 d-flex flex-column justify-content-center">
<div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
<div class="small text-uppercase fw-semibold opacity-75">Tu espacio de apoyo académico</div>
<h1 class="display-6 fw-bold mt-2">Bienvenido a Studia360.</h1>
<p class="text-white-50">Continúa tu proceso, consulta tus contenidos y sigue avanzando.</p>
<div class="feature"><i class="bi bi-book-half"></i><span>Contenidos y recursos organizados.</span></div>
<div class="feature"><i class="bi bi-trophy-fill"></i><span>Progreso, niveles y coleccionables.</span></div>
<div class="feature"><i class="bi bi-person-heart"></i><span>Personaliza tu experiencia.</span></div>
</div></div>
<div class="col-lg-7"><div class="form-panel">
<div class="badgex mb-3"><i class="bi bi-shield-lock-fill me-1"></i> ACCESO A STUDIA360</div>
<h2 class="h2 fw-bold mb-2">Inicia sesión</h2>
<p class="text-muted mb-4">Ingresa con tu número de documento y contraseña.</p>
<?php if($error!==''): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form method="POST" action="autenticar.php">
<div class="mb-3"><label class="form-label fw-semibold">Número de documento</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-person-vcard-fill"></i></span><input class="form-control" type="text" name="documento" required inputmode="numeric" autocomplete="username" placeholder="Ingresa tu documento"></div></div>
<div class="mb-2"><label class="form-label fw-semibold">Contraseña</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock-fill"></i></span><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"><button type="button" class="btn toggle" id="toggle"><i class="bi bi-eye"></i></button></div></div>
<div class="d-flex justify-content-end mb-4"><a class="recover small" href="recuperar_password.php"><i class="bi bi-key me-1"></i>¿Olvidaste tu contraseña?</a></div>
<button class="btn btn-primary btnlogin w-100" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión</button>
</form>
<div class="text-center mt-4"><small class="text-muted"><i class="bi bi-shield-check me-1"></i>Acceso protegido de Studia360.</small></div>
</div></div>
</div></div></div></div></div>
<script>
document.getElementById('toggle').addEventListener('click',function(){const p=document.getElementById('password'),i=this.querySelector('i');if(p.type==='password'){p.type='text';i.classList.replace('bi-eye','bi-eye-slash')}else{p.type='password';i.classList.replace('bi-eye-slash','bi-eye')}});
</script>
</body>
</html>
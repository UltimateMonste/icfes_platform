<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

if (isset($_SESSION['id_usuario'])) {
    if ((int)($_SESSION['id_rol'] ?? 0) === 1) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: estudiante/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numeroDocumento = trim((string)($_POST['numero_documento'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($numeroDocumento === '' || $password === '') {
        $error = 'Completa todos los campos para iniciar sesión.';
    } else {
        try {
            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombres, apellidos, correo, password, id_rol, primer_ingreso
                 FROM usuarios
                 WHERE numero_documento = :numero_documento
                 LIMIT 1"
            );
            $stmt->execute([':numero_documento' => $numeroDocumento]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || !password_verify($password, $usuario['password'])) {
                $error = 'El número de documento o la contraseña no son correctos.';
            } else {
                session_regenerate_id(true);

                $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
                $_SESSION['id_rol'] = (int)$usuario['id_rol'];
                $_SESSION['nombres'] = $usuario['nombres'];
                $_SESSION['apellidos'] = $usuario['apellidos'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['primer_ingreso'] = (int)($usuario['primer_ingreso'] ?? 0);

                if ((int)($usuario['primer_ingreso'] ?? 0) === 1) {
                    header('Location: cambiar_password.php');
                    exit;
                }

                if ((int)$usuario['id_rol'] === 1) {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: estudiante/dashboard.php');
                }
                exit;
            }
        } catch (PDOException $e) {
            $error = 'No fue posible iniciar sesión en este momento.';
        }
    }
}
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
:root{
    --primary:#2467c5;
    --dark:#173f80;
    --soft:#f4f7fb;
}
body{
    min-height:100vh;
    background:
        radial-gradient(circle at 8% 15%,rgba(71,139,235,.20),transparent 30%),
        radial-gradient(circle at 92% 85%,rgba(23,63,128,.15),transparent 30%),
        var(--soft);
}
.login-card{
    border:0;
    border-radius:30px;
    overflow:hidden;
    box-shadow:0 28px 80px rgba(23,63,128,.16);
}
.brand-panel{
    position:relative;
    overflow:hidden;
    padding:3.4rem;
    color:#fff;
    background:
        radial-gradient(circle at 85% 15%,rgba(255,255,255,.15),transparent 25%),
        linear-gradient(135deg,var(--dark),var(--primary));
}
.brand-panel:before{
    content:"";
    position:absolute;
    width:320px;height:320px;
    border-radius:50%;
    border:55px solid rgba(255,255,255,.05);
    right:-150px;bottom:-150px;
}
.brand-content{position:relative;z-index:1}
.logo-box{
    width:78px;height:78px;border-radius:24px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(255,255,255,.13);
    font-size:2rem;margin-bottom:2rem;
}
.feature{
    display:flex;align-items:center;gap:.75rem;
    margin-top:1rem;color:rgba(255,255,255,.84);
}
.feature i{
    width:36px;height:36px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(255,255,255,.12);
}
.form-panel{padding:3.4rem}
.badge-login{
    display:inline-flex;align-items:center;gap:.45rem;
    padding:.45rem .85rem;border-radius:999px;
    background:#eef5ff;color:var(--primary);
    font-size:.78rem;font-weight:700;
}
.form-control{
    min-height:54px;border-radius:14px;border-color:#dbe4ef;
}
.input-group-text{
    border-radius:14px 0 0 14px;
    border-color:#dbe4ef;background:#fff;color:var(--primary);
}
.input-group .form-control{border-left:0}
.password-toggle{
    border-color:#dbe4ef;border-left:0;
    border-radius:0 14px 14px 0;background:#fff;
}
.input-group:focus-within{
    border-radius:14px;
    box-shadow:0 0 0 .25rem rgba(36,103,197,.10);
}
.input-group:focus-within .form-control,
.input-group:focus-within .input-group-text,
.input-group:focus-within .password-toggle{
    border-color:#7ca9e7;box-shadow:none;
}
.btn-login{
    min-height:54px;border:0;border-radius:14px;font-weight:700;
    background:linear-gradient(120deg,var(--primary),#3d7fd8);
    box-shadow:0 12px 24px rgba(36,103,197,.22);
    transition:.2s ease;
}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 16px 30px rgba(36,103,197,.28)}
.recover-link{color:var(--primary);font-weight:600;text-decoration:none}
.recover-link:hover{text-decoration:underline}
@media(max-width:991px){
    .brand-panel,.form-panel{padding:2.4rem}
}
@media(max-width:575px){
    .brand-panel{padding:2rem}
    .form-panel{padding:2rem 1.35rem}
}
</style>
</head>

<body>
<div class="container py-4 py-lg-5">
<div class="row min-vh-100 align-items-center justify-content-center">
<div class="col-12 col-lg-10 col-xl-9">

<div class="card login-card">
<div class="row g-0">

<div class="col-lg-5">
<div class="brand-panel h-100 d-flex flex-column justify-content-center">
<div class="brand-content">

<div class="logo-box"><i class="bi bi-mortarboard-fill"></i></div>

<div class="text-uppercase small fw-semibold opacity-75 mb-2">Tu espacio de apoyo académico</div>

<h1 class="display-6 fw-bold mb-3">Aprende, avanza y descubre tu progreso.</h1>

<p class="text-white-50 mb-4">
Studia360 reúne tus contenidos, recursos y progreso en un solo lugar para acompañarte en tu proceso.
</p>

<div class="feature">
<i class="bi bi-book-half"></i>
<span>Contenidos organizados para tu aprendizaje.</span>
</div>

<div class="feature">
<i class="bi bi-trophy-fill"></i>
<span>Progreso, niveles y coleccionables.</span>
</div>

<div class="feature">
<i class="bi bi-person-heart"></i>
<span>Una experiencia adaptada a tu perfil.</span>
</div>

</div>
</div>
</div>

<div class="col-lg-7">
<div class="form-panel">

<div class="badge-login mb-3">
<i class="bi bi-person-circle"></i>
ACCESO A STUDIA360
</div>

<h2 class="h2 fw-bold mb-2">¡Bienvenido de nuevo!</h2>
<p class="text-muted mb-4">Inicia sesión para continuar donde lo dejaste.</p>

<?php if ($error !== ''): ?>
<div class="alert alert-danger d-flex gap-2 align-items-center">
<i class="bi bi-exclamation-triangle-fill"></i>
<div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
</div>
<?php endif; ?>

<form method="POST" autocomplete="on">

<div class="mb-3">
<label class="form-label fw-semibold">Número de documento</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person-vcard-fill"></i></span>
<input type="text" name="numero_documento" class="form-control" required
       value="<?= htmlspecialchars($_POST['numero_documento'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
       placeholder="Ingresa tu número de documento" autocomplete="username">
</div>
</div>

<div class="mb-2">
<label class="form-label fw-semibold">Contraseña</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
<input type="password" id="password" name="password" class="form-control"
       required placeholder="Ingresa tu contraseña" autocomplete="current-password">
<button type="button" class="btn password-toggle" id="togglePassword" aria-label="Mostrar contraseña">
<i class="bi bi-eye"></i>
</button>
</div>
</div>

<div class="d-flex justify-content-end mb-4">
<a href="recuperar_password.php" class="recover-link small">
<i class="bi bi-key me-1"></i>¿Olvidaste tu contraseña?
</a>
</div>

<button type="submit" class="btn btn-primary btn-login w-100">
<i class="bi bi-box-arrow-in-right me-2"></i>
Iniciar sesión
</button>

</form>

<div class="text-center mt-4">
<small class="text-muted">
<i class="bi bi-shield-check me-1"></i>
Tu acceso está protegido dentro de Studia360.
</small>
</div>

</div>
</div>

</div>
</div>

</div>
</div>
</div>

<script>
const toggle = document.getElementById('togglePassword');
const password = document.getElementById('password');

toggle.addEventListener('click', function(){
    const icon = this.querySelector('i');
    if(password.type === 'password'){
        password.type = 'text';
        icon.classList.replace('bi-eye','bi-eye-slash');
    }else{
        password.type = 'password';
        icon.classList.replace('bi-eye-slash','bi-eye');
    }
});
</script>
</body>
</html>

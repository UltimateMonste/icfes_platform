<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

function e(?string $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$estado = trim((string)($_GET['estado'] ?? ''));
$permitidos = ['Pendiente', 'Gestionada', 'Cancelada'];

$sql = "SELECT sr.*, u.nombres, u.apellidos, u.correo, u.grado
        FROM solicitudes_recuperacion sr
        INNER JOIN usuarios u ON u.id_usuario = sr.id_usuario";

$params = [];

if (in_array($estado, $permitidos, true)) {
    $sql .= " WHERE sr.estado = :estado";
    $params[':estado'] = $estado;
}

$sql .= " ORDER BY CASE sr.estado WHEN 'Pendiente' THEN 1 ELSE 2 END,
          sr.fecha_solicitud DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resumen = ['total'=>0,'pendientes'=>0,'gestionadas'=>0];
foreach ($solicitudes as $s) {
    $resumen['total']++;
    if ($s['estado']==='Pendiente') $resumen['pendientes']++;
    if ($s['estado']==='Gestionada') $resumen['gestionadas']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperación de contraseñas | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#26364a}
.top{background:linear-gradient(110deg,#173f80,#2467c5)}
.hero{background:linear-gradient(120deg,#1e5eb8,#173f80);color:#fff;border-radius:25px;padding:2rem}
.cardx{background:#fff;border:1px solid #dce5f0;border-radius:22px;box-shadow:0 10px 28px rgba(31,57,92,.06)}
.stat{padding:1rem;border:1px solid #dce5f0;border-radius:18px;height:100%}
.request{padding:1.15rem;border:1px solid #dce5f0;border-radius:18px;background:#fff}
</style>
</head>
<body>

<nav class="navbar navbar-dark top">
<div class="container">
<a class="navbar-brand fw-bold" href="<?= e(urlAplicacion('/admin/dashboard.php')) ?>"><i class="bi bi-mortarboard-fill me-2"></i>Studia360 <span class="badge bg-light text-primary">Admin</span></a>
<a href="<?= e(urlAplicacion('/admin/dashboard.php')) ?>" class="btn btn-light btn-sm">Dashboard</a>
</div>
</nav>

<main class="container py-5">
<section class="hero mb-4">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
<div>
<div class="small text-uppercase opacity-75">Seguridad</div>
<h1 class="h2 fw-bold mb-1">Solicitudes de recuperación</h1>
<p class="text-white-50 mb-0">Gestiona los cambios de contraseña solicitados por los estudiantes.</p>
</div>
<i class="bi bi-shield-lock-fill display-4"></i>
</div>
</section>

<div class="row g-3 mb-4">
<div class="col-md-4"><div class="stat"><div class="h3 fw-bold"><?= $resumen['total'] ?></div><small class="text-muted">Solicitudes</small></div></div>
<div class="col-md-4"><div class="stat"><div class="h3 fw-bold"><?= $resumen['pendientes'] ?></div><small class="text-muted">Pendientes</small></div></div>
<div class="col-md-4"><div class="stat"><div class="h3 fw-bold"><?= $resumen['gestionadas'] ?></div><small class="text-muted">Gestionadas</small></div></div>
</div>

<section class="cardx p-3 p-md-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<h2 class="h5 fw-bold mb-0">Bandeja de solicitudes</h2>
<form method="GET">
<select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
<option value="">Todos los estados</option>
<?php foreach($permitidos as $op): ?>
<option value="<?= e($op) ?>" <?= $estado===$op?'selected':'' ?>><?= e($op) ?></option>
<?php endforeach; ?>
</select>
</form>
</div>

<div class="d-grid gap-3">
<?php if(!$solicitudes): ?>
<div class="text-center py-5 text-muted"><i class="bi bi-shield-check display-5 d-block mb-3"></i>No hay solicitudes para mostrar.</div>
<?php endif; ?>

<?php foreach($solicitudes as $s): ?>
<div class="request">
<div class="row align-items-center g-3">
<div class="col-md">
<div class="fw-bold"><?= e(trim($s['nombres'].' '.$s['apellidos'])) ?></div>
<div class="small text-muted"><?= e($s['correo']) ?> · Grado <?= e((string)$s['grado']) ?></div>
<div class="small text-muted mt-1"><i class="bi bi-calendar3 me-1"></i><?= e(date('d/m/Y H:i',strtotime($s['fecha_solicitud']))) ?></div>
</div>
<div class="col-auto">
<span class="badge text-bg-<?= $s['estado']==='Pendiente'?'warning':($s['estado']==='Gestionada'?'success':'secondary') ?>"><?= e($s['estado']) ?></span>
</div>
<div class="col-auto">
<a class="btn btn-primary btn-sm" href="gestionar.php?id=<?= (int)$s['id_solicitud'] ?>"><i class="bi bi-key me-1"></i>Gestionar</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</section>
</main>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';

exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    redireccionarLogin('Tu sesión no es válida.');
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function recursoImagen(?string $imagen, string $carpeta = 'avatares'): string {
    $imagen = trim((string)$imagen);
    if ($imagen === '') return '';
    if (preg_match('#^https?://#i', $imagen)) return $imagen;

    $imagen = str_replace('\\', '/', $imagen);
    $imagen = ltrim($imagen, '/');

    if (str_starts_with($imagen, 'assets/')) {
        return urlAplicacion('/' . $imagen);
    }
    if (str_starts_with($imagen, 'uploads/')) {
        return urlAplicacion('/assets/' . $imagen);
    }

    return urlAplicacion('/assets/uploads/' . $carpeta . '/' . rawurlencode(basename($imagen)));
}

if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_perfil'];
if (empty($_SESSION['csrf_perfil_accion'])) { $_SESSION['csrf_perfil_accion'] = bin2hex(random_bytes(32)); }
$mensaje = (string)($_SESSION['mensaje_perfil'] ?? '');
$tipoMensaje = (string)($_SESSION['tipo_mensaje_perfil'] ?? 'success');
unset($_SESSION['mensaje_perfil'], $_SESSION['tipo_mensaje_perfil']);
$tipoMensaje = 'success';

try {
    sincronizarNivel($conexion, $idUsuario);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_avatar') {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('La solicitud de seguridad expiró. Recarga la página.');
        }

        $idAvatar = filter_input(INPUT_POST, 'id_avatar', FILTER_VALIDATE_INT);
        if (!$idAvatar || $idAvatar < 1) {
            throw new RuntimeException('Selecciona un avatar válido.');
        }

        $st = $conexion->prepare("
            SELECT id_avatar, nombre, imagen, puntos_requeridos
            FROM avatares
            WHERE id_avatar = ? AND estado = 'Activo'
            LIMIT 1
        ");
        $st->execute([$idAvatar]);
        $avatar = $st->fetch(PDO::FETCH_ASSOC);

        if (!$avatar) throw new RuntimeException('El avatar seleccionado no está disponible.');

        $st = $conexion->prepare("SELECT puntos FROM usuarios WHERE id_usuario = ?");
        $st->execute([$idUsuario]);
        $puntosUsuario = (int)$st->fetchColumn();

        if ($puntosUsuario < (int)$avatar['puntos_requeridos']) {
            throw new RuntimeException('Aún no tienes suficientes puntos para desbloquear este avatar.');
        }

        $st = $conexion->prepare("
            UPDATE usuarios
            SET id_avatar = ?, avatar = ?
            WHERE id_usuario = ?
        ");
        $st->execute([$idAvatar, $avatar['imagen'], $idUsuario]);

        $mensaje = 'Tu avatar se actualizó correctamente.';
    }
} catch (Throwable $e) {
    if ($e instanceof RuntimeException) {
        $mensaje = $e->getMessage();
        $tipoMensaje = 'danger';
    } else {
        $mensaje = 'No fue posible actualizar el perfil.';
        $tipoMensaje = 'danger';
    }
}

try {
    $st = $conexion->prepare("
        SELECT
            u.id_usuario, u.nombres, u.apellidos, u.correo, u.grado,
            u.avatar, u.id_avatar, u.puntos, u.nivel, u.fecha_registro,
            a.nombre AS avatar_nombre, a.imagen AS avatar_imagen,
            a.puntos_requeridos AS avatar_puntos
        FROM usuarios u
        LEFT JOIN avatares a ON a.id_avatar = u.id_avatar
        WHERE u.id_usuario = ?
        LIMIT 1
    ");
    $st->execute([$idUsuario]);
    $usuario = $st->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) redireccionarDashboardUsuario();

    $gam = obtenerGamificacionUsuario($conexion, $idUsuario);
    $puntos = (int)$gam['puntos'];
    $nivel = $gam['nivel'];
    $avanceNivel = (float)$gam['progreso_nivel'];
    $siguientePuntos = $gam['puntos_siguiente_nivel'];
} catch (Throwable $e) {
    die('No fue posible cargar el perfil.');
}

$avatares = [];
try {
    $st = $conexion->query("
        SELECT id_avatar, nombre, imagen, puntos_requeridos
        FROM avatares
        WHERE estado = 'Activo'
        ORDER BY puntos_requeridos ASC, id_avatar ASC
    ");
    $avatares = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$insignias = [];
try {
    $st = $conexion->prepare("
        SELECT
            i.id_insignia, i.nombre, i.descripcion, i.imagen,
            i.criterio, i.puntos_otorgados,
            CASE WHEN ui.id_usuario IS NULL THEN 0 ELSE 1 END AS obtenida,
            ui.fecha AS fecha_obtenida
        FROM insignias i
        LEFT JOIN usuarios_insignias ui
          ON ui.id_insignia = i.id_insignia
         AND ui.id_usuario = ?
        WHERE i.estado = 'Activa'
        ORDER BY obtenida DESC, i.id_insignia ASC
    ");
    $st->execute([$idUsuario]);
    $insignias = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$estadisticas = [
    'temas_iniciados' => 0,
    'temas_completados' => 0,
    'progreso_promedio' => 0,
    'recursos_vistos' => 0,
    'evaluaciones' => 0
];
try {
    $st = $conexion->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN porcentaje_avance > 0 THEN id_tema END) temas_iniciados,
            COUNT(DISTINCT CASE WHEN porcentaje_avance >= 100 THEN id_tema END) temas_completados,
            COALESCE(ROUND(AVG(porcentaje_avance),0),0) progreso_promedio,
            COALESCE(SUM(recursos_vistos),0) recursos_vistos,
            COALESCE(SUM(evaluaciones_realizadas),0) evaluaciones
        FROM progreso
        WHERE id_usuario = ?
    ");
    $st->execute([$idUsuario]);
    $estadisticas = array_merge($estadisticas, $st->fetch(PDO::FETCH_ASSOC) ?: []);
} catch (Throwable $e) {}

$nombreCompleto = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
$iniciales = '';
foreach (preg_split('/\s+/', $nombreCompleto) as $p) {
    if ($p !== '') $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
    if (mb_strlen($iniciales) >= 2) break;
}
$avatarPrincipal = recursoImagen($usuario['avatar_imagen'] ?: $usuario['avatar']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi perfil | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#2467c5;--dark:#173f80;--soft:#eef5ff;--border:#dce5f0;--text:#26364a}
body{background:radial-gradient(circle at top right,#eaf3ff,transparent 28%),#f4f7fb;color:var(--text)}
.navbar{background:linear-gradient(100deg,var(--dark),var(--blue));box-shadow:0 6px 24px #173f8025}
.hero{border-radius:26px;padding:2rem;color:#fff;background:linear-gradient(120deg,#2467c5,#173f80);box-shadow:0 18px 45px #173f8020}
.avatar{width:125px;height:125px;border-radius:50%;overflow:hidden;background:#e8f1ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:2.4rem;font-weight:800;border:5px solid #ffffffd9}
.avatar img{width:100%;height:100%;object-fit:cover}
.cardx{background:#fff;border:1px solid var(--border);border-radius:21px;box-shadow:0 10px 30px #1f395c10}
.stat{border:1px solid var(--border);border-radius:17px;padding:1rem;background:#fbfdff;height:100%}
.stat i{width:42px;height:42px;border-radius:13px;background:var(--soft);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.progress{height:12px;border-radius:99px}.progress-bar{background:linear-gradient(90deg,#2467c5,#6aa2f3)}
.avatar-option{border:2px solid var(--border);border-radius:18px;padding:1rem;background:#fff;height:100%;transition:.2s}
.avatar-option:hover{transform:translateY(-2px);box-shadow:0 10px 24px #2467c51a}.avatar-option.selected{border-color:var(--blue)}
.avatar-img{width:72px;height:72px;border-radius:50%;overflow:hidden;background:#eef5ff;margin:auto;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:1.7rem;font-weight:800}
.avatar-img img{width:100%;height:100%;object-fit:cover}
.badge-card{border:1px solid var(--border);border-radius:17px;padding:1rem;height:100%}.badge-card.locked{opacity:.5;filter:grayscale(1)}
.badge-img{width:54px;height:54px;border-radius:15px;background:#fff5d9;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b78100;font-size:1.5rem}
.badge-img img{width:100%;height:100%;object-fit:cover}
</style>
</head>
<body>
<nav class="navbar navbar-dark"><div class="container py-2">
<a class="navbar-brand fw-bold" href="<?=h(urlAplicacion('/estudiante/dashboard.php'))?>"><i class="bi bi-mortarboard-fill me-2"></i>Studia360</a>
<a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/estudiante/dashboard.php'))?>"><i class="bi bi-house me-1"></i>Inicio</a>
</div></nav>
<main class="container py-4 py-lg-5">
<section class="hero mb-4">
<div class="row align-items-center g-4">
<div class="col-auto"><div class="avatar"><?php if($avatarPrincipal):?><img src="<?=h($avatarPrincipal)?>" alt="Avatar"><?php else:?><?=h($iniciales)?><?php endif;?></div>
<form class="mt-2 text-center" method="post" action="<?=h(urlAplicacion('/estudiante/perfil_accion.php'))?>" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf_perfil_accion'] ?? '')?>">
<input type="hidden" name="accion" value="foto">
<label class="btn btn-light btn-sm" style="cursor:pointer"><i class="bi bi-camera me-1"></i>Cambiar foto
<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()">
</label>
</form></div>
<div class="col"><div class="text-uppercase small opacity-75">Mi perfil</div><h1 class="display-6 fw-bold mb-1"><?=h($nombreCompleto)?></h1>
<p class="mb-2 text-white-50">Estudiante · Grado <?=h($usuario['grado'])?></p>
<div class="d-flex flex-wrap gap-2"><span class="badge text-bg-light"><?=number_format($puntos)?> puntos</span><span class="badge bg-white text-primary">Nivel <?=h($nivel['id_nivel'])?> · <?=h($nivel['nombre'])?></span></div></div>
</div></section>

<?php if($mensaje):?><div class="alert alert-<?=$tipoMensaje?>"><?=h($mensaje)?></div><?php endif;?>

<div class="row g-4 mb-4">
<div class="col-lg-7"><section class="cardx p-4 h-100">
<h2 class="h5 fw-bold"><i class="bi bi-stars me-2 text-primary"></i>Tu progreso de nivel</h2>
<div class="d-flex justify-content-between align-items-end mt-3"><div><div class="h3 fw-bold mb-0"><?=h($nivel['nombre'])?></div><small class="text-muted"><?=number_format($puntos)?> puntos acumulados</small></div><strong><?=number_format($avanceNivel,0)?>%</strong></div>
<div class="progress mt-3"><div class="progress-bar" style="width:<?=h((string)$avanceNivel)?>%"></div></div>
<div class="small text-muted mt-2"><?php if($siguientePuntos!==null):?>Te faltan <strong><?=number_format(max(0,(int)$siguientePuntos-$puntos))?></strong> puntos para el siguiente nivel.<?php else:?>¡Has alcanzado el nivel máximo configurado!<?php endif;?></div>
<p class="text-muted mt-3 mb-0"><?=h($nivel['descripcion'] ?? 'Sigue aprendiendo y acumulando puntos.')?></p>
</section></div>
<div class="col-lg-5"><section class="cardx p-4 h-100"><h2 class="h5 fw-bold mb-3"><i class="bi bi-person-vcard me-2 text-primary"></i>Datos de cuenta</h2>
<div class="mb-3"><small class="text-muted">Correo</small><div class="fw-semibold"><?=h($usuario['correo'])?></div></div>
<div class="mb-3"><small class="text-muted">Grado</small><div class="fw-semibold"><?=h($usuario['grado'])?></div></div>
<div><small class="text-muted">Puntos</small><div class="fw-semibold"><?=number_format($puntos)?></div></div>
</section></div>
</div>

<section class="cardx p-4 mb-4"><h2 class="h5 fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Resumen de aprendizaje</h2>
<div class="row g-3">
<?php
$stats=[
['bi-book','Temas iniciados',$estadisticas['temas_iniciados']],
['bi-check2-circle','Temas completados',$estadisticas['temas_completados']],
['bi-bar-chart','Progreso promedio',$estadisticas['progreso_promedio'].'%'],
['bi-collection-play','Recursos vistos',$estadisticas['recursos_vistos']],
['bi-ui-checks-grid','Evaluaciones',$estadisticas['evaluaciones']]
];
foreach($stats as $s):?>
<div class="col-6 col-lg"><div class="stat"><i class="bi <?=$s[0]?> mb-2"></i><div class="small text-muted"><?=$s[1]?></div><div class="h4 fw-bold mb-0"><?=h((string)$s[2])?></div></div></div>
<?php endforeach;?>
</div></section>

<section class="cardx p-4 mb-4"><h2 class="h5 fw-bold mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Avatares</h2><p class="text-muted">Desbloquea avatares con tus puntos y selecciona el que represente tu progreso.</p>
<div class="row g-3">
<?php foreach($avatares as $a):
$desbloqueado=$puntos >= (int)$a['puntos_requeridos'];
$seleccionado=(int)$usuario['id_avatar']===(int)$a['id_avatar'];
$img=recursoImagen($a['imagen']);
?>
<div class="col-6 col-md-4 col-lg-3"><div class="avatar-option <?=$seleccionado?'selected':''?>">
<div class="avatar-img"><?php if($img):?><img src="<?=h($img)?>" alt="<?=h($a['nombre'])?>"><?php else:?><i class="bi bi-person"></i><?php endif;?></div>
<div class="text-center mt-2 fw-bold"><?=h($a['nombre'])?></div>
<div class="text-center small text-muted mb-3"><?=number_format((int)$a['puntos_requeridos'])?> puntos</div>
<?php if($desbloqueado):?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="cambiar_avatar"><input type="hidden" name="id_avatar" value="<?=$a['id_avatar']?>">
<button class="btn btn-sm <?=$seleccionado?'btn-primary':'btn-outline-primary'?> w-100"><?=$seleccionado?'Avatar actual':'Seleccionar'?></button></form>
<?php else:?><button class="btn btn-sm btn-outline-secondary w-100" disabled><i class="bi bi-lock me-1"></i>Bloqueado</button><?php endif;?>
</div></div>
<?php endforeach;?>
</div>
</section>

<section class="cardx p-4"><h2 class="h5 fw-bold mb-3"><i class="bi bi-award me-2 text-primary"></i>Insignias y coleccionables</h2>
<div class="row g-3">
<?php if(!$insignias):?><div class="text-muted">Aún no hay insignias configuradas.</div><?php endif;?>
<?php foreach($insignias as $i):$img=recursoImagen($i['imagen'],'insignias');$ob=(int)$i['obtenida']===1;?>
<div class="col-12 col-md-6 col-lg-4"><div class="badge-card <?=$ob?'':'locked'?>"><div class="d-flex gap-3 align-items-center">
<div class="badge-img"><?php if($img):?><img src="<?=h($img)?>" alt=""><?php else:?><i class="bi bi-trophy-fill"></i><?php endif;?></div>
<div><div class="fw-bold"><?=h($i['nombre'])?></div><small class="text-muted"><?=$ob?'Obtenida':'Aún no obtenida'?></small></div></div>
<p class="small text-muted mt-3 mb-1"><?=h($i['descripcion'])?></p>
<?php if(!empty($i['criterio'])):?><div class="small"><strong>Criterio:</strong> <?=h($i['criterio'])?></div><?php endif;?>
</div></div>
<?php endforeach;?>
</div></section>
</main>
</body></html>

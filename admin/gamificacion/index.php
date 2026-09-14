<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/seguridad.php';
require_once __DIR__.'/../../includes/gamificacion.php';

exigirAdmin();

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function imagenCatalogo(?string $imagen, string $carpeta = 'avatares'): string {
    $imagen = trim((string)$imagen);
    if ($imagen === '') return '';

    if (preg_match('#^https?://#i', $imagen)) return $imagen;

    $imagen = str_replace('\\', '/', ltrim($imagen, '/'));

    if (str_starts_with($imagen, 'assets/')) {
        return urlAplicacion('/'.$imagen);
    }

    if (str_starts_with($imagen, 'uploads/')) {
        return urlAplicacion('/assets/'.$imagen);
    }

    return urlAplicacion('/assets/uploads/'.$carpeta.'/'.rawurlencode(basename($imagen)));
}

if (empty($_SESSION['csrf_gam'])) {
    $_SESSION['csrf_gam'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_gam'];
$msg = '';
$tipo = 'success';

function guardarImagenAvatar(string $campo): ?string {
    if (!isset($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No fue posible cargar la imagen.');
    }

    if ((int)$_FILES[$campo]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no puede superar 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$campo]['tmp_name']);
    $ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ][$mime] ?? null;

    if (!$ext) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
    }

    $dir = __DIR__.'/../../assets/uploads/avatares';

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('No fue posible preparar la carpeta de avatares.');
    }

    $nombre = 'avatar_'.date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$ext;
    $dest = $dir.'/'.$nombre;

    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $dest)) {
        throw new RuntimeException('No fue posible guardar la imagen.');
    }

    return 'assets/uploads/avatares/'.$nombre;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Solicitud inválida.');
        }

        $accion = (string)($_POST['accion'] ?? '');

        /*
         * Las recompensas relacionadas con evaluaciones ya no forman
         * parte de Studia360. Se conservan en la BD por compatibilidad,
         * pero no se muestran ni se pueden editar desde este panel.
         */
        if ($accion === 'config') {
            $clave = (string)($_POST['clave'] ?? '');

            if (in_array($clave, ['evaluacion', 'evaluacion_perfecta'], true)) {
                throw new RuntimeException('Esta recompensa ya no forma parte del sistema.');
            }

            $st = $conexion->prepare(
                "UPDATE configuracion_gamificacion
                 SET puntos=?, estado=?
                 WHERE clave=?"
            );

            $st->execute([
                max(0, (int)($_POST['puntos'] ?? 0)),
                ($_POST['estado'] ?? '') === 'Inactivo' ? 'Inactivo' : 'Activo',
                $clave
            ]);

            $msg = 'Recompensa actualizada correctamente.';

        } elseif ($accion === 'nivel') {
            /*
             * Los niveles ya no administran imágenes.
             * El avatar se gestiona exclusivamente en la sección
             * "Avatares por nivel".
             */
            $idNivel = (int)($_POST['id_nivel'] ?? 0);
            $min = max(0, (int)($_POST['min'] ?? 0));
            $max = max($min, (int)($_POST['max'] ?? 0));

            $st = $conexion->prepare(
                "UPDATE niveles
                 SET nombre=?, descripcion=?, puntos_minimos=?, puntos_maximos=?
                 WHERE id_nivel=?"
            );

            $st->execute([
                trim((string)($_POST['nombre'] ?? '')),
                trim((string)($_POST['descripcion'] ?? '')),
                $min,
                $max,
                $idNivel
            ]);

            $msg = 'Nivel actualizado correctamente.';

        } elseif ($accion === 'avatar') {
            $idAvatar = (int)($_POST['id_avatar'] ?? 0);
            $idNivel = (int)($_POST['id_nivel'] ?? 0);

            $check = $conexion->prepare(
                "SELECT id_avatar
                 FROM avatares
                 WHERE id_nivel=? AND id_avatar<>?
                 LIMIT 1"
            );
            $check->execute([$idNivel, $idAvatar]);

            if ($check->fetchColumn()) {
                throw new RuntimeException('Ese nivel ya tiene otro avatar asignado.');
            }

            $st = $conexion->prepare(
                "SELECT imagen FROM avatares WHERE id_avatar=? LIMIT 1"
            );
            $st->execute([$idAvatar]);
            $imagenActual = (string)$st->fetchColumn();

            $nuevaImagen = guardarImagenAvatar('imagen_archivo');
            $imagen = $nuevaImagen ?? ($imagenActual !== '' ? $imagenActual : null);

            $st = $conexion->prepare(
                "UPDATE avatares
                 SET id_nivel=?,
                     nombre=?,
                     imagen=?,
                     puntos_requeridos=(
                         SELECT puntos_minimos
                         FROM niveles
                         WHERE id_nivel=?
                     ),
                     estado=?
                 WHERE id_avatar=?"
            );

            $st->execute([
                $idNivel,
                trim((string)($_POST['nombre'] ?? '')),
                $imagen,
                $idNivel,
                ($_POST['estado'] ?? '') === 'Inactivo' ? 'Inactivo' : 'Activo',
                $idAvatar
            ]);

            $msg = 'Avatar actualizado correctamente.';

        } elseif ($accion === 'insignia') {
            $idInsignia = (int)($_POST['id_insignia'] ?? 0);
            $materia = (int)($_POST['id_materia'] ?? 0);

            if ($materia < 1) {
                throw new RuntimeException('Selecciona una materia para la insignia.');
            }

            $check = $conexion->prepare(
                "SELECT id_insignia
                 FROM insignias
                 WHERE id_materia=? AND id_insignia<>?
                 LIMIT 1"
            );
            $check->execute([$materia, $idInsignia]);

            if ($check->fetchColumn()) {
                throw new RuntimeException('Esa materia ya tiene otra insignia configurada.');
            }

            /*
             * Las insignias son emblemas de texto.
             * No se almacena ni se solicita una imagen.
             */
            $st = $conexion->prepare(
                "UPDATE insignias
                 SET id_materia=?,
                     nombre=?,
                     descripcion=?,
                     criterio='materia_completa',
                     puntos_otorgados=?,
                     estado=?
                 WHERE id_insignia=?"
            );

            $st->execute([
                $materia,
                trim((string)($_POST['nombre'] ?? '')),
                trim((string)($_POST['descripcion'] ?? '')),
                max(0, (int)($_POST['puntos_otorgados'] ?? 0)),
                ($_POST['estado'] ?? '') === 'Inactiva' ? 'Inactiva' : 'Activa',
                $idInsignia
            ]);

            $msg = 'Insignia actualizada correctamente.';

        } elseif ($accion === 'nueva_insignia') {
            $materia = (int)($_POST['id_materia'] ?? 0);

            if ($materia < 1) {
                throw new RuntimeException('Selecciona una materia.');
            }

            $check = $conexion->prepare(
                "SELECT COUNT(*)
                 FROM insignias
                 WHERE id_materia=?"
            );
            $check->execute([$materia]);

            if ((int)$check->fetchColumn() > 0) {
                throw new RuntimeException('Esa materia ya tiene una insignia configurada.');
            }

            $st = $conexion->prepare(
                "INSERT INTO insignias
                    (id_materia, nombre, descripcion, criterio, puntos_otorgados, estado)
                 VALUES (?, ?, ?, 'materia_completa', ?, ?)"
            );

            $st->execute([
                $materia,
                trim((string)($_POST['nombre'] ?? '')),
                trim((string)($_POST['descripcion'] ?? '')),
                max(0, (int)($_POST['puntos_otorgados'] ?? 0)),
                ($_POST['estado'] ?? '') === 'Inactiva' ? 'Inactiva' : 'Activa'
            ]);

            $msg = 'Insignia creada correctamente.';
        }

    } catch (Throwable $e) {
        $msg = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No fue posible guardar los cambios.';
        $tipo = 'danger';
    }
}

/*
 * No mostramos recompensas de evaluaciones.
 */
$configs = $conexion->query(
    "SELECT *
     FROM configuracion_gamificacion
     WHERE clave NOT IN ('evaluacion', 'evaluacion_perfecta')
     ORDER BY id_configuracion"
)->fetchAll(PDO::FETCH_ASSOC);

$niveles = $conexion->query(
    "SELECT *
     FROM niveles
     ORDER BY puntos_minimos, id_nivel"
)->fetchAll(PDO::FETCH_ASSOC);

$avatares = $conexion->query(
    "SELECT a.*, n.nombre AS nivel_nombre, n.puntos_minimos
     FROM avatares a
     INNER JOIN niveles n ON n.id_nivel=a.id_nivel
     ORDER BY a.id_nivel"
)->fetchAll(PDO::FETCH_ASSOC);

$materias = $conexion->query(
    "SELECT id_materia, nombre
     FROM materias
     ORDER BY id_materia"
)->fetchAll(PDO::FETCH_ASSOC);

$insignias = $conexion->query(
    "SELECT i.*, m.nombre AS materia_nombre
     FROM insignias i
     LEFT JOIN materias m ON m.id_materia=i.id_materia
     ORDER BY i.id_insignia"
)->fetchAll(PDO::FETCH_ASSOC);

$materiasConInsignia = array_fill_keys(
    array_map(fn($x) => (string)$x['id_materia'], $insignias),
    true
);

$nivelesOcupados = array_fill_keys(
    array_map(fn($x) => (string)$x['id_nivel'], $avatares),
    true
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gamificación | Studia360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
    --primary:#2563eb;
    --primary-dark:#173f80;
    --ink:#1e293b;
    --muted:#64748b;
    --line:#e2e8f0;
    --soft:#f8fafc;
    --gold:#f59e0b;
}

*{box-sizing:border-box}

body{
    margin:0;
    background:
        radial-gradient(circle at 90% 0%, #eaf2ff 0, transparent 28%),
        #f6f8fc;
    color:var(--ink);
    font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.navbar{
    background:linear-gradient(105deg,#12366f,#2563c7);
    box-shadow:0 5px 20px rgba(15,42,84,.14);
}

.navbar-brand{
    letter-spacing:-.2px;
}

.page{
    max-width:1180px;
}

.hero{
    position:relative;
    overflow:hidden;
    border-radius:26px;
    padding:30px 32px;
    color:#fff;
    background:
        radial-gradient(circle at 90% 15%, rgba(255,255,255,.18), transparent 25%),
        linear-gradient(115deg,#1d4ed8,#173f80);
    box-shadow:0 18px 45px rgba(37,99,235,.18);
}

.hero-icon{
    width:70px;
    height:70px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:22px;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.18);
    font-size:32px;
}

.section-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:22px;
    padding:24px;
    box-shadow:0 8px 28px rgba(30,41,59,.045);
}

.section-title{
    display:flex;
    align-items:flex-start;
    gap:12px;
}

.section-title-icon{
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 42px;
    border-radius:13px;
    background:#eff6ff;
    color:var(--primary);
    font-size:20px;
}

.section-title h2{
    font-size:1.15rem;
    margin:0;
    font-weight:750;
}

.section-title p{
    margin:3px 0 0;
    color:var(--muted);
    font-size:.9rem;
}

.item{
    height:100%;
    border:1px solid var(--line);
    border-radius:18px;
    padding:19px;
    background:#fff;
    transition:.18s ease;
}

.item:hover{
    border-color:#cbd8ea;
    box-shadow:0 8px 24px rgba(30,64,175,.06);
    transform:translateY(-1px);
}

.form-control,.form-select,.input-group-text{
    border-color:#d7e0eb;
}

.form-control,.form-select{
    border-radius:11px;
    min-height:43px;
}

.form-control:focus,.form-select:focus{
    border-color:#80aef5;
    box-shadow:0 0 0 .2rem rgba(37,99,235,.09);
}

.form-label-small{
    display:block;
    margin-bottom:6px;
    color:#64748b;
    font-size:.78rem;
    font-weight:650;
}

.reward-card{
    border:1px solid var(--line);
    border-radius:17px;
    padding:17px;
    height:100%;
    background:linear-gradient(180deg,#fff,#fbfdff);
}

.reward-icon{
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    background:#eff6ff;
    color:var(--primary);
}

.level-card{
    position:relative;
}

.level-number{
    width:39px;
    height:39px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eef4ff;
    color:var(--primary);
    font-weight:800;
}

.avatar-preview{
    width:72px;
    height:72px;
    object-fit:cover;
    border-radius:20px;
    border:1px solid #dbe5f1;
    background:#eff6ff;
}

.avatar-empty{
    width:72px;
    height:72px;
    border-radius:20px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:var(--primary);
    font-size:28px;
}

.badge-preview{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 12px;
    border-radius:999px;
    background:linear-gradient(135deg,#fff7d6,#ffefad);
    color:#8a5a00;
    border:1px solid #f4d477;
    font-weight:750;
    font-size:.84rem;
}

.badge-preview i{
    color:#d99a00;
}

.help{
    color:#718096;
    font-size:.8rem;
}

.alert{
    border-radius:14px;
}

.empty{
    padding:24px;
    text-align:center;
    border:1px dashed #cbd5e1;
    border-radius:16px;
    color:var(--muted);
    background:#f8fafc;
}

@media(max-width:767px){
    .hero{padding:24px}
    .section-card{padding:18px}
}
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>

<nav class="navbar navbar-dark">
    <div class="container page py-2">
        <div class="d-flex align-items-center justify-content-between w-100 gap-3">
            <a class="navbar-brand fw-bold text-white text-decoration-none"
               href="<?=h(urlAplicacion('/admin/dashboard.php'))?>">
                <i class="bi bi-mortarboard-fill me-2"></i>Studia360
                <span class="badge bg-light text-primary ms-1">Admin</span>
            </a>

            <a class="btn btn-light btn-sm"
               href="<?=h(urlAplicacion('/admin/dashboard.php'))?>">
                <i class="bi bi-grid-1x2 me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>

<main class="container page py-4 py-lg-5">

    <section class="hero mb-4">
        <div class="d-flex align-items-center justify-content-between gap-4">
            <div>
                <div class="small text-uppercase fw-bold opacity-75 mb-2">
                    Sistema de recompensas
                </div>
                <h1 class="h2 fw-bold mb-2">Gamificación</h1>
                <p class="mb-0 text-white-50">
                    Configura el progreso, los niveles, los avatares y los emblemas
                    que reconocen el avance de los estudiantes.
                </p>
            </div>

            <div class="hero-icon d-none d-md-flex">
                <i class="bi bi-stars"></i>
            </div>
        </div>
    </section>

    <?php if ($msg): ?>
        <div class="alert alert-<?=$tipo?> alert-dismissible fade show shadow-sm mb-4">
            <?=h($msg)?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- RECOMPENSAS -->
    <section class="section-card mb-4">
        <div class="section-title mb-4">
            <div class="section-title-icon">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div>
                <h2>Recompensas por interacción</h2>
                <p>
                    Puntos que el estudiante puede obtener al interactuar con los contenidos.
                </p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($configs as $c): ?>
                <div class="col-12 col-lg-6">
                    <div class="reward-card">
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="accion" value="config">
                            <input type="hidden" name="clave" value="<?=h($c['clave'])?>">

                            <div class="d-flex gap-3 mb-3">
                                <div class="reward-icon">
                                    <i class="bi bi-stars"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?=h($c['nombre'])?></div>
                                    <div class="small text-muted">
                                        <?=h($c['descripcion'])?>
                                    </div>
                                </div>
                            </div>

                            <div class="input-group">
                                <input class="form-control"
                                       type="number"
                                       min="0"
                                       name="puntos"
                                       value="<?=$c['puntos']?>">
                                <span class="input-group-text">puntos</span>
                            </div>

                            <select class="form-select mt-2" name="estado">
                                <option value="Activo" <?=$c['estado']==='Activo'?'selected':''?>>
                                    Activo
                                </option>
                                <option value="Inactivo" <?=$c['estado']==='Inactivo'?'selected':''?>>
                                    Inactivo
                                </option>
                            </select>

                            <button class="btn btn-primary btn-sm mt-3">
                                <i class="bi bi-check2 me-1"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- NIVELES -->
    <section class="section-card mb-4" id="niveles">
        <div class="section-title mb-4">
            <div class="section-title-icon">
                <i class="bi bi-bar-chart-steps"></i>
            </div>
            <div>
                <h2>Niveles</h2>
                <p>
                    Los puntos acumulados determinan el nivel alcanzado por cada estudiante.
                </p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($niveles as $n): ?>
                <div class="col-12 col-lg-6">
                    <div class="item level-card">
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="accion" value="nivel">
                            <input type="hidden" name="id_nivel" value="<?=$n['id_nivel']?>">

                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="level-number">
                                    <?=$n['id_nivel']?>
                                </div>
                                <div>
                                    <div class="fw-bold">Nivel <?=$n['id_nivel']?></div>
                                    <div class="small text-muted">
                                        <?=$n['puntos_minimos']?> – <?=$n['puntos_maximos']?> puntos
                                    </div>
                                </div>
                            </div>

                            <label class="form-label-small">Nombre del nivel</label>
                            <input class="form-control mb-3"
                                   name="nombre"
                                   value="<?=h($n['nombre'])?>"
                                   required>

                            <label class="form-label-small">Descripción</label>
                            <textarea class="form-control mb-3"
                                      name="descripcion"
                                      rows="2"><?=h($n['descripcion'])?></textarea>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label-small">Puntos mínimos</label>
                                    <input class="form-control"
                                           type="number"
                                           min="0"
                                           name="min"
                                           value="<?=$n['puntos_minimos']?>">
                                </div>

                                <div class="col-6">
                                    <label class="form-label-small">Puntos máximos</label>
                                    <input class="form-control"
                                           type="number"
                                           min="0"
                                           name="max"
                                           value="<?=$n['puntos_maximos']?>">
                                </div>
                            </div>

                            <button class="btn btn-outline-primary btn-sm mt-3">
                                <i class="bi bi-save me-1"></i>Guardar nivel
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- AVATARES -->
    <section class="section-card mb-4" id="avatares">
        <div class="section-title mb-2">
            <div class="section-title-icon">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
                <h2>Avatares por nivel</h2>
                <p>
                    Cada nivel tiene un avatar de perfil. Al alcanzar el nivel,
                    el estudiante desbloquea su avatar.
                </p>
            </div>
        </div>

        <div class="help mb-4">
            El administrador puede cambiar el nombre, el nivel y la imagen.
            Para la imagen se aceptan JPG, PNG y WEBP de hasta 5 MB.
        </div>

        <div class="row g-3">
            <?php foreach ($avatares as $a):
                $img = imagenCatalogo($a['imagen']);
            ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="item">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <?php if ($img): ?>
                                <img class="avatar-preview"
                                     src="<?=h($img)?>"
                                     alt="<?=h($a['nombre'])?>">
                            <?php else: ?>
                                <div class="avatar-empty">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <div class="fw-bold"><?=h($a['nombre'])?></div>
                                <span class="badge rounded-pill text-bg-primary">
                                    Nivel <?=$a['id_nivel']?>
                                </span>
                            </div>
                        </div>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="accion" value="avatar">
                            <input type="hidden" name="id_avatar" value="<?=$a['id_avatar']?>">

                            <label class="form-label-small">Nombre del avatar</label>
                            <input class="form-control mb-3"
                                   name="nombre"
                                   value="<?=h($a['nombre'])?>"
                                   required>

                            <label class="form-label-small">Nivel asociado</label>
                            <select class="form-select mb-3"
                                    name="id_nivel"
                                    required>
                                <?php foreach ($niveles as $n):
                                    $ocupado = isset($nivelesOcupados[(string)$n['id_nivel']])
                                        && (int)$a['id_nivel'] !== (int)$n['id_nivel'];
                                ?>
                                    <option value="<?=$n['id_nivel']?>"
                                        <?=$a['id_nivel']==$n['id_nivel']?'selected':''?>
                                        <?=$ocupado?'disabled':''?>>
                                        Nivel <?=$n['id_nivel']?> · <?=h($n['nombre'])?>
                                        <?=$ocupado?' · ya asignado':''?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="form-label-small">Nueva imagen</label>
                            <input class="form-control mb-3"
                                   type="file"
                                   name="imagen_archivo"
                                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                            <select class="form-select"
                                    name="estado">
                                <option value="Activo" <?=$a['estado']==='Activo'?'selected':''?>>
                                    Activo
                                </option>
                                <option value="Inactivo" <?=$a['estado']==='Inactivo'?'selected':''?>>
                                    Inactivo
                                </option>
                            </select>

                            <button class="btn btn-primary btn-sm mt-3 w-100">
                                <i class="bi bi-cloud-arrow-up me-1"></i>
                                Guardar avatar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- INSIGNIAS / EMBLEMAS -->
    <section class="section-card" id="insignias">
        <div class="section-title mb-2">
            <div class="section-title-icon">
                <i class="bi bi-award-fill"></i>
            </div>
            <div>
                <h2>Emblemas por materia</h2>
                <p>
                    Reconocimientos que se muestran junto al perfil del estudiante
                    cuando completa una materia.
                </p>
            </div>
        </div>

        <div class="help mb-4">
            Cada materia puede tener un único emblema. El sistema lo entrega
            automáticamente cuando el estudiante completa todos los temas de
            esa materia correspondientes a su grado.
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ($insignias as $i): ?>
                <div class="col-12 col-xl-6">
                    <div class="item">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <div class="mb-2">
                                    <span class="badge-preview">
                                        <i class="bi bi-award-fill"></i>
                                        <?=h($i['nombre'])?>
                                    </span>
                                </div>
                                <div class="small text-muted">
                                    <?=h($i['materia_nombre'] ?: 'Sin materia')?>
                                </div>
                            </div>

                            <span class="badge rounded-pill <?=$i['estado']==='Activa'?'text-bg-success':'text-bg-secondary'?>">
                                <?=$i['estado']==='Activa'?'Activa':'Inactiva'?>
                            </span>
                        </div>

                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="accion" value="insignia">
                            <input type="hidden" name="id_insignia" value="<?=$i['id_insignia']?>">

                            <label class="form-label-small">Materia</label>
                            <select class="form-select mb-3"
                                    name="id_materia"
                                    required>
                                <?php foreach ($materias as $m): ?>
                                    <option value="<?=$m['id_materia']?>"
                                        <?=$i['id_materia']==$m['id_materia']?'selected':''?>>
                                        <?=h($m['nombre'])?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="form-label-small">Título del emblema</label>
                            <input class="form-control mb-3"
                                   name="nombre"
                                   value="<?=h($i['nombre'])?>"
                                   placeholder="Maestro de Ciencias"
                                   required>

                            <label class="form-label-small">Descripción</label>
                            <textarea class="form-control mb-3"
                                      name="descripcion"
                                      rows="2"
                                      placeholder="Completaste todos los temas de la materia."><?=h($i['descripcion'])?></textarea>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label-small">Puntos de recompensa</label>
                                    <input class="form-control"
                                           type="number"
                                           min="0"
                                           name="puntos_otorgados"
                                           value="<?=$i['puntos_otorgados']?>">
                                </div>

                                <div class="col-6">
                                    <label class="form-label-small">Estado</label>
                                    <select class="form-select" name="estado">
                                        <option value="Activa" <?=$i['estado']==='Activa'?'selected':''?>>
                                            Activa
                                        </option>
                                        <option value="Inactiva" <?=$i['estado']==='Inactiva'?'selected':''?>>
                                            Inactiva
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-sm mt-3 w-100">
                                <i class="bi bi-check2 me-1"></i>
                                Guardar emblema
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="border-top pt-4">
            <h3 class="h5 fw-bold mb-1">Crear nuevo emblema</h3>
            <p class="text-muted small mb-3">
                Solo aparecen materias que todavía no tienen un emblema.
            </p>

            <form method="post">
                <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                <input type="hidden" name="accion" value="nueva_insignia">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-small">Materia</label>
                        <select class="form-select"
                                name="id_materia"
                                required>
                            <option value="">Seleccionar...</option>

                            <?php foreach ($materias as $m):
                                if (isset($materiasConInsignia[(string)$m['id_materia']])) continue;
                            ?>
                                <option value="<?=$m['id_materia']?>">
                                    <?=h($m['nombre'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label-small">Título del emblema</label>
                        <input class="form-control"
                               name="nombre"
                               placeholder="Maestro de Ciencias"
                               required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-small">Puntos</label>
                        <input class="form-control"
                               type="number"
                               min="0"
                               name="puntos_otorgados"
                               value="0">
                    </div>

                    <div class="col-12">
                        <label class="form-label-small">Descripción</label>
                        <textarea class="form-control"
                                  name="descripcion"
                                  rows="2"
                                  placeholder="Reconocimiento por completar todos los temas de la materia."></textarea>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-success">
                            <i class="bi bi-plus-circle me-1"></i>
                            Crear emblema
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

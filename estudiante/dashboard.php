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

function fotoPerfil(?string $foto): string {
    $foto = trim((string)$foto);
    if ($foto === '') return '';
    if (preg_match('#^https?://#i', $foto)) return $foto;

    $foto = str_replace('\\', '/', ltrim($foto, '/'));

    if (str_starts_with($foto, 'assets/')) {
        return urlAplicacion('/' . $foto);
    }
    if (str_starts_with($foto, 'uploads/')) {
        return urlAplicacion('/assets/' . $foto);
    }

    return urlAplicacion('/assets/uploads/avatares/' . basename($foto));
}

try {
    $st = $conexion->prepare("
        SELECT nombres, apellidos, grado, avatar
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ");
    $st->execute([$idUsuario]);
    $usuario = $st->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        redireccionarLogin('Usuario no encontrado.');
    }

    sincronizarNivel($conexion, $idUsuario);
    $gam = obtenerGamificacionUsuario($conexion, $idUsuario);
    $progresoGeneral = obtenerProgresoGeneral($conexion, $idUsuario);

    // No se filtra por el grado del estudiante:
    // cualquier estudiante puede explorar 9°, 10° y 11°.
    $st = $conexion->query("
        SELECT
            m.id_materia,
            m.nombre,
            m.descripcion,
            COUNT(t.id_tema) AS total_temas
        FROM materias m
        LEFT JOIN temas t ON t.id_materia = m.id_materia
        GROUP BY m.id_materia, m.nombre, m.descripcion
        ORDER BY m.nombre
    ");
    $materias = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die('No fue posible cargar el dashboard.');
}

$foto = fotoPerfil($usuario['avatar'] ?? '');
$nombre = trim((string)$usuario['nombres']);
$nivel = $gam['nivel'];
$puntos = (int)$gam['puntos'];
$progresoNivel = (float)$gam['progreso_nivel'];
$siguienteNivel = $gam['puntos_siguiente_nivel'];

$iniciales = '';
foreach (preg_split('/\s+/', trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''))) as $parte) {
    if ($parte !== '') {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($iniciales) >= 2) break;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inicio | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
    --azul:#2563b8;
    --azul-oscuro:#173f78;
    --azul-suave:#edf5ff;
    --morado:#7457d9;
    --verde:#24a276;
    --amarillo:#f5b83d;
    --texto:#24364b;
    --gris:#718096;
    --fondo:#f7f9fc;
}
*{box-sizing:border-box}
body{
    margin:0;
    background:var(--fondo);
    color:var(--texto);
    font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
}
.navbar{
    background:#fff;
    border-bottom:1px solid #e8edf4;
}
.logo{
    width:40px;height:40px;border-radius:13px;
    display:flex;align-items:center;justify-content:center;
    color:#fff;background:linear-gradient(135deg,#2875cf,#173f78);
}
.profile-nav{
    width:42px;height:42px;border-radius:50%;overflow:hidden;
    background:var(--azul-suave);color:var(--azul);
    display:flex;align-items:center;justify-content:center;
    font-weight:800;border:2px solid #dcecff;
}
.profile-nav img{width:100%;height:100%;object-fit:cover}

.welcome{
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,#2875cf 0%,#1d4f91 65%,#173f78 100%);
    border-radius:28px;padding:30px;color:#fff;
    box-shadow:0 18px 40px rgba(31,79,145,.18);
}
.welcome:after{
    content:"";
    position:absolute;width:210px;height:210px;border-radius:50%;
    right:-55px;top:-90px;background:rgba(255,255,255,.09);
}
.welcome-avatar{
    width:78px;height:78px;border-radius:24px;
    overflow:hidden;flex:none;background:#eaf3ff;color:var(--azul);
    border:3px solid rgba(255,255,255,.75);
    display:flex;align-items:center;justify-content:center;
    font-size:1.65rem;font-weight:900;
}
.welcome-avatar img{width:100%;height:100%;object-fit:cover}
.xp-pill{
    display:inline-flex;align-items:center;gap:7px;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);
    padding:8px 13px;border-radius:999px;font-weight:700;
}
.quick{
    background:#fff;border:1px solid #e6ebf2;border-radius:19px;
    padding:17px 18px;height:100%;
}
.quick-icon{
    width:42px;height:42px;border-radius:13px;
    display:flex;align-items:center;justify-content:center;
    background:var(--azul-suave);color:var(--azul);font-size:1.15rem;
}
.level-card{
    background:#fff;border:1px solid #e6ebf2;border-radius:22px;padding:21px;
}
.progress{height:9px;background:#edf1f6;border-radius:99px}
.progress-bar{background:linear-gradient(90deg,#2875cf,#63a3ec);border-radius:99px}

.explore{
    background:#fff;border:1px solid #e6ebf2;border-radius:23px;
    padding:20px;
}
.grade-filter{
    display:flex;gap:8px;flex-wrap:wrap;
    background:#f1f4f8;padding:5px;border-radius:15px;
}
.grade-btn{
    border:0;background:transparent;color:#66768a;
    padding:9px 20px;border-radius:11px;font-weight:800;
    transition:.18s;
}
.grade-btn:hover{color:var(--azul)}
.grade-btn.active{
    background:#fff;color:var(--azul);
    box-shadow:0 3px 10px rgba(35,57,83,.1);
}
.subject{
    background:#fff;border:1px solid #e5ebf2;border-radius:22px;
    padding:18px;height:100%;transition:.2s ease;
}
.subject:hover{
    transform:translateY(-4px);
    box-shadow:0 15px 35px rgba(30,58,92,.10);
    border-color:#d7e6fa;
}
.subject-icon{
    width:58px;height:58px;border-radius:17px;
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:1.45rem;margin-bottom:17px;
    background:linear-gradient(135deg,#2875cf,#4f8ed3);
}
.subject:nth-child(2) .subject-icon{background:linear-gradient(135deg,#7457d9,#9b83e8)}
.subject:nth-child(3) .subject-icon{background:linear-gradient(135deg,#24a276,#49bd98)}
.subject:nth-child(4) .subject-icon{background:linear-gradient(135deg,#ed9c37,#f4c15d)}
.subject:nth-child(5) .subject-icon{background:linear-gradient(135deg,#d65d7d,#e98da4)}
.subject:nth-child(6) .subject-icon{background:linear-gradient(135deg,#4d7bb5,#72a0d6)}
.subject p{min-height:43px}
.btn-study{
    border:0;border-radius:13px;font-weight:800;
    padding:10px 14px;background:#edf5ff;color:var(--azul);
}
.btn-study:hover{background:var(--azul);color:#fff}
.muted{color:var(--gris)}
@media(max-width:767px){
    .welcome{padding:22px;border-radius:22px}
    .welcome-avatar{width:65px;height:65px;border-radius:19px}
}
</style>
</head>

<body>

<nav class="navbar">
<div class="container py-2">
    <div class="d-flex align-items-center gap-2">
        <span class="logo"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="fw-bold fs-5">Studia360</span>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a class="profile-nav text-decoration-none" href="<?=h(urlAplicacion('/estudiante/perfil.php'))?>">
            <?php if ($foto): ?>
                <img src="<?=h($foto)?>" alt="Mi perfil">
            <?php else: ?>
                <?=h($iniciales)?>
            <?php endif; ?>
        </a>
        <a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/estudiante/perfil.php'))?>">Mi perfil</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?=h(urlAplicacion('/cerrar_sesion.php'))?>" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>
</nav>

<main class="container py-4 py-lg-5">

<section class="welcome mb-4">
    <div class="d-flex align-items-center justify-content-between gap-4 position-relative" style="z-index:1">
        <div class="d-flex align-items-center gap-3">
            <div class="welcome-avatar">
                <?php if ($foto): ?>
                    <img src="<?=h($foto)?>" alt="Foto de <?=h($nombre)?>">
                <?php else: ?>
                    <?=h($iniciales)?>
                <?php endif; ?>
            </div>

            <div>
                <div class="small text-uppercase opacity-75 fw-semibold">Qué bueno verte</div>
                <h1 class="h2 fw-bold mb-1">¡Hola, <?=h($nombre)?>! 👋</h1>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="xp-pill"><i class="bi bi-star-fill"></i><?=number_format($puntos)?> XP</span>
                    <span class="xp-pill"><i class="bi bi-trophy-fill"></i><?=h($nivel['nombre'])?></span>
                </div>
            </div>
        </div>

        <div class="d-none d-md-block text-end">
            <div class="small opacity-75">Tu progreso</div>
            <div class="display-6 fw-bold"><?=number_format((float)($progresoGeneral['porcentaje'] ?? 0),0)?>%</div>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <div><div class="small muted">Experiencia</div><strong><?=number_format($puntos)?> XP</strong></div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-trophy-fill"></i></div>
            <div><div class="small muted">Nivel</div><strong><?=h($nivel['nombre'])?></strong></div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="quick d-flex align-items-center gap-3">
            <div class="quick-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div><div class="small muted">Progreso</div><strong><?=number_format((float)($progresoGeneral['porcentaje'] ?? 0),0)?>% completado</strong></div>
        </div>
    </div>
</div>

<section class="level-card mb-4">
    <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
            <div class="small text-primary fw-bold">SIGUE AVANZANDO</div>
            <h2 class="h5 fw-bold mb-0"><?=h($nivel['nombre'])?></h2>
        </div>
        <strong><?=number_format($progresoNivel,0)?>%</strong>
    </div>
    <div class="progress">
        <div class="progress-bar" style="width:<?=h((string)$progresoNivel)?>%"></div>
    </div>
    <div class="small muted mt-2">
        <?php if ($siguienteNivel !== null): ?>
            Te faltan <?=number_format(max(0,(int)$siguienteNivel-$puntos))?> XP para subir de nivel.
        <?php else: ?>
            ¡Llegaste al nivel máximo! 🏆
        <?php endif; ?>
    </div>
</section>

<section class="explore mb-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h2 class="h4 fw-bold mb-1">¿Qué quieres aprender hoy?</h2>
            <div class="small muted">Elige un grado y descubre sus contenidos.</div>
        </div>

        <div class="grade-filter" id="selectorGrado">
            <button type="button" class="grade-btn" data-grado="9">9°</button>
            <button type="button" class="grade-btn" data-grado="10">10°</button>
            <button type="button" class="grade-btn" data-grado="11">11°</button>
        </div>
    </div>
</section>

<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h2 class="h4 fw-bold mb-1">Tus materias</h2>
        <div class="small muted" id="textoGrado">Contenidos de grado <?=h($usuario['grado'])?>°</div>
    </div>
</div>

<div class="row g-4">
<?php foreach ($materias as $m): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <article class="subject">
            <div class="subject-icon"><i class="bi bi-book-half"></i></div>
            <h3 class="h5 fw-bold mb-2"><?=h($m['nombre'])?></h3>
            <p class="small muted mb-3">
                <?=h($m['descripcion'] ?: 'Explora, aprende y avanza a tu ritmo.')?>
            </p>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small muted">
                    <i class="bi bi-journal-text me-1"></i>
                    <?=number_format((int)$m['total_temas'])?> temas
                </span>
            </div>

            <a class="btn-study w-100 d-block text-center text-decoration-none"
               href="#"
               data-materia="<?=h((string)$m['id_materia'])?>">
                Explorar <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </article>
    </div>
<?php endforeach; ?>

<?php if (!$materias): ?>
    <div class="col-12">
        <div class="alert alert-light border">Aún no hay materias disponibles.</div>
    </div>
<?php endif; ?>
</div>

</main>

<script>
(function(){
    const base = <?=json_encode(urlAplicacion('/estudiante/grado.php?grado='))?>;
    const botones = document.querySelectorAll('#selectorGrado .grade-btn');
    const enlaces = document.querySelectorAll('[data-materia]');
    const texto = document.getElementById('textoGrado');

    let gradoActual = <?=json_encode((string)$usuario['grado'])?>;

    function actualizar(){
        enlaces.forEach(function(enlace){
            enlace.href = base + gradoActual + '&id_materia=' + enlace.dataset.materia;
        });
        texto.textContent = 'Contenidos de grado ' + gradoActual + '°';
    }

    botones.forEach(function(btn){
        btn.addEventListener('click', function(){
            gradoActual = this.dataset.grado;
            botones.forEach(function(b){ b.classList.toggle('active', b === btn); });
            actualizar();
        });
    });

    const inicial = document.querySelector('#selectorGrado [data-grado="' + gradoActual + '"]');
    if(inicial) inicial.classList.add('active');

    actualizar();
})();
</script>

</body>
</html>

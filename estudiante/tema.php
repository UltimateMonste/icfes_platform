<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/seguridad.php";
exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$idTema = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idTema || $idTema <= 0) {
    header('Location: dashboard.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function youtubeId(string $url): ?string {
    $patterns = [
        '~youtu\.be/([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/watch\?(?:[^#]*&)?v=([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/embed/([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~i',
        '~youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})~i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $m)) return $m[1];
    }
    return null;
}

function recursoImagen(?string $imagen, string $tipo, string $url): ?string {
    $imagen = trim((string)$imagen);
    if ($imagen !== '') {
        if (preg_match('~^https?://~i', $imagen) || str_starts_with($imagen, '/')) return $imagen;
        return '/icfes_platform/' . ltrim($imagen, '/');
    }
    if ($tipo === 'video') {
        $id = youtubeId($url);
        if ($id) return 'https://img.youtube.com/vi/' . rawurlencode($id) . '/hqdefault.jpg';
    }
    return null;
}

function recursoIcono(string $tipo): string {
    return match ($tipo) {
        'video' => 'bi-play-circle-fill',
        'pdf' => 'bi-file-earmark-pdf-fill',
        'juego' => 'bi-controller',
        'simulador' => 'bi-display',
        'app' => 'bi-phone',
        'presentacion' => 'bi-easel2-fill',
        'articulo' => 'bi-file-text-fill',
        'blog' => 'bi-journal-text',
        default => 'bi-link-45deg'
    };
}

function recursoTipo(string $tipo): string {
    return match ($tipo) {
        'video' => 'Video',
        'pdf' => 'PDF',
        'juego' => 'Juego',
        'simulador' => 'Simulador',
        'app' => 'Aplicación',
        'presentacion' => 'Presentación',
        'articulo' => 'Artículo',
        'blog' => 'Blog',
        default => ucfirst($tipo)
    };
}

$tema = null;
$contenido = null;
$recursos = [];
$progreso = [
    'porcentaje_avance' => 0,
    'recursos_vistos' => 0,
    'evaluaciones_realizadas' => 0,
    'ultima_actividad' => null
];
$error = null;

try {
    $stmt = $conexion->prepare("SELECT t.id_tema,t.nombre AS tema,t.descripcion,t.grado,t.id_materia,m.nombre AS materia,m.descripcion AS descripcion_materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=? LIMIT 1");
    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $conexion->prepare("SELECT contenido,fecha_actualizacion FROM contenido_temas WHERE id_tema=? AND estado='Publicado' ORDER BY id_contenido DESC LIMIT 1");
    $stmt->execute([$idTema]);
    $contenido = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $conexion->prepare("SELECT id_recurso,titulo,tipo,url,descripcion,imagen,autor,fuente,visitas FROM recursos WHERE id_tema=? AND estado='Activo' ORDER BY fecha_publicacion DESC,id_recurso DESC");
    $stmt->execute([$idTema]);
    $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT porcentaje_avance,recursos_vistos,evaluaciones_realizadas,ultima_actividad FROM progreso WHERE id_usuario=? AND id_tema=? LIMIT 1");
    $stmt->execute([$idUsuario,$idTema]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fila) $progreso = array_merge($progreso,$fila);
} catch (PDOException $ex) {
    $error = 'No fue posible cargar la información del tema.';
}

$porcentaje = max(0,min(100,(float)$progreso['porcentaje_avance']));
$estado = $porcentaje >= 100 ? 'Tema completado' : ($porcentaje > 0 ? 'En progreso' : 'Tema pendiente');
$estadoIcono = $porcentaje >= 100 ? 'bi-check-circle-fill' : ($porcentaje > 0 ? 'bi-play-circle' : 'bi-circle');
$nombreEstudiante = trim(($_SESSION['nombres'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? '')) ?: 'Estudiante';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($tema['tema'] ?? 'Tema') ?> | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--blue:#0d6efd;--bg:#f4f7fb;--text:#1f2937;--muted:#64748b;--border:#e2e8f0}
body{background:radial-gradient(circle at top left,rgba(13,110,253,.07),transparent 32%),var(--bg);color:var(--text)}
.navbar-studia{background:#20252b;min-height:58px;box-shadow:0 3px 15px rgba(15,23,42,.12)}
.brand{color:#fff;text-decoration:none;font-size:1.18rem;font-weight:700}.brand:hover{color:#fff}
.page{max-width:1160px;margin:auto;padding:24px 16px 60px}.crumb{font-size:.9rem;color:var(--muted);margin-bottom:14px}.crumb a{color:var(--blue);text-decoration:none}
.hero{background:linear-gradient(135deg,#0d6efd,#164fa6);color:#fff;border-radius:0 0 24px 24px;padding:32px 28px;margin-bottom:24px;box-shadow:0 18px 40px rgba(13,110,253,.17)}
.kicker{font-size:.82rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700;opacity:.82}.hero h1{font-weight:800;margin:5px 0 7px}.hero p{margin:0;opacity:.9}
.cardx{background:#fff;border:1px solid rgba(226,232,240,.9);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06)}.content{padding:30px}.title{font-weight:800;font-size:1.1rem;margin-bottom:20px}
.topic{font-size:1.03rem;line-height:1.75;overflow-wrap:anywhere}.topic img{max-width:100%!important;height:auto!important;border-radius:12px}.topic iframe,.topic video{max-width:100%}.topic table{max-width:100%;overflow:auto;display:block}
.empty{text-align:center;color:var(--muted);padding:55px 20px}.empty i{font-size:2.8rem;display:block;margin-bottom:14px}
.side{padding:20px;margin-bottom:16px}.side-title{font-weight:800;margin-bottom:16px}.track{height:9px;background:#e9eef5;border-radius:99px;overflow:hidden}.fill{height:100%;background:linear-gradient(90deg,#0d6efd,#4f8dfd);border-radius:99px}
.resources{margin-top:30px}.resources h2{font-size:1.5rem;font-weight:800;margin-bottom:4px}.resources>p{color:var(--muted);margin-bottom:18px}
.rcard{height:100%;background:#fff;border:1px solid var(--border);border-radius:17px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.05);transition:.18s}.rcard:hover{transform:translateY(-3px);box-shadow:0 15px 30px rgba(15,23,42,.09)}
.thumb{position:relative;aspect-ratio:16/9;background:#eaf2ff;overflow:hidden}.thumb img{width:100%;height:100%;object-fit:cover;display:block}.placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:3rem}.play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:3.6rem;background:linear-gradient(rgba(0,0,0,.03),rgba(0,0,0,.25));text-shadow:0 2px 10px rgba(0,0,0,.4)}
.rbody{padding:18px}.ricon{width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#eaf2ff;color:var(--blue);font-size:1.15rem}.rtitle{font-weight:800;line-height:1.3;margin:0}.badge-type{display:inline-block;background:var(--blue);color:#fff;border-radius:6px;padding:4px 8px;font-size:.72rem;font-weight:700;margin-top:7px}.desc{color:var(--muted);font-size:.92rem;margin:10px 0 15px;min-height:42px}.rbtn{display:inline-flex;gap:5px;align-items:center;text-decoration:none;border:1px solid var(--blue);color:var(--blue);border-radius:8px;padding:7px 11px;font-size:.87rem;font-weight:600}.rbtn:hover{background:var(--blue);color:#fff}.back{display:inline-flex;align-items:center;gap:6px;color:#fff;border:1px solid rgba(255,255,255,.6);border-radius:8px;padding:7px 11px;text-decoration:none;font-size:.86rem}.back:hover{color:#fff;background:rgba(255,255,255,.1)}
@media(max-width:767px){.page{padding:18px 12px 45px}.hero{padding:25px 20px}.content{padding:20px}.hero h1{font-size:1.8rem}}
</style>
</head>
<body>
<nav class="navbar navbar-studia"><div class="container d-flex justify-content-between align-items-center"><a class="brand" href="dashboard.php"><i class="bi bi-mortarboard-fill me-1"></i>Studia360</a><div class="d-flex align-items-center gap-2"><span class="text-white small d-none d-md-inline"><i class="bi bi-person-circle me-1"></i><?= e($nombreEstudiante) ?></span><a class="back" href="grado.php?grado=<?= e($tema['grado']) ?>"><i class="bi bi-arrow-left"></i>Volver</a><a class="back" href="../cerrar_sesion.php"><i class="bi bi-box-arrow-right"></i>Cerrar sesión</a></div></div></nav>
<main class="page">
<?php if($error): ?><div class="alert alert-danger rounded-4 border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?></div><?php else: ?>
<div class="crumb"><a href="dashboard.php">Inicio</a><span class="mx-1">/</span><a href="grado.php?grado=<?= e($tema['grado']) ?>">Grado <?= e($tema['grado']) ?>°</a><span class="mx-1">/</span><?= e($tema['materia']) ?></div>
<section class="hero"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="kicker"><?= e($tema['materia']) ?> · <?= e($tema['grado']) ?>°</div><h1><?= e($tema['tema']) ?></h1><?php if($tema['descripcion']): ?><p><?= e($tema['descripcion']) ?></p><?php endif; ?></div><div class="text-md-end"><div class="small opacity-75">Tu progreso</div><strong class="fs-4"><?= number_format($porcentaje,0) ?>%</strong></div></div></section>
<div class="row g-4"><div class="col-lg-8"><section class="cardx content"><div class="title"><i class="bi bi-book-half text-primary me-2"></i>Contenido del tema</div><?php if($contenido && trim((string)$contenido['contenido'])!==''): ?><article class="topic"><?= $contenido['contenido'] ?></article><?php else: ?><div class="empty"><i class="bi bi-journal-x"></i><h3 class="h5">Contenido próximamente</h3><p class="mb-0">Este tema todavía no tiene una lección publicada.</p></div><?php endif; ?></section></div>
<div class="col-lg-4"><aside class="cardx side"><div class="side-title"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Tu progreso</div><div class="d-flex justify-content-between mb-2"><span class="small text-secondary">Avance</span><strong><?= number_format($porcentaje,0) ?>%</strong></div><div class="track"><div class="fill" style="width:<?= number_format($porcentaje,2,'.','') ?>%"></div></div><div class="small text-secondary mt-3"><i class="bi <?= e($estadoIcono) ?> text-primary me-1"></i><?= e($estado) ?></div><div class="small text-secondary mt-3"><div class="d-flex justify-content-between py-1"><span>Recursos vistos</span><strong><?= (int)$progreso['recursos_vistos'] ?></strong></div><div class="d-flex justify-content-between py-1"><span>Evaluaciones</span><strong><?= (int)$progreso['evaluaciones_realizadas'] ?></strong></div></div></aside>
<aside class="cardx side"><div class="side-title"><i class="bi bi-info-circle text-primary me-2"></i>Información</div><div class="small text-secondary mb-3">Materia<strong class="d-block text-dark"><?= e($tema['materia']) ?></strong></div><div class="small text-secondary mb-3">Grado<strong class="d-block text-dark"><?= e($tema['grado']) ?>°</strong></div><div class="small text-secondary">Recursos<strong class="d-block text-dark"><?= count($recursos) ?> <?= count($recursos)===1?'disponible':'disponibles' ?></strong></div></aside></div></div>
<?php if($recursos): ?><section class="resources"><h2><i class="bi bi-collection-play text-primary me-2"></i>Recursos complementarios</h2><p>Material adicional para profundizar en este tema.</p><div class="row g-4"><?php foreach($recursos as $r): $tipo=strtolower((string)$r['tipo']);$img=recursoImagen($r['imagen']??null,$tipo,(string)$r['url']);$icon=recursoIcono($tipo);$esVideo=$tipo==='video'; ?><div class="col-md-6 col-xl-4"><article class="rcard"><?php if($img): ?><div class="thumb"><img src="<?= e($img) ?>" alt="Miniatura de <?= e($r['titulo']) ?>" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><?php if($esVideo): ?><div class="play"><i class="bi bi-play-circle-fill"></i></div><?php else: ?><div class="placeholder" style="display:none"><i class="bi <?= e($icon) ?>"></i></div><?php endif; ?></div><?php else: ?><div class="thumb"><div class="placeholder"><i class="bi <?= e($icon) ?>"></i></div></div><?php endif; ?><div class="rbody"><div class="d-flex align-items-start gap-3"><div class="ricon"><i class="bi <?= e($icon) ?>"></i></div><div class="flex-grow-1"><h3 class="rtitle"><?= e($r['titulo']) ?></h3><span class="badge-type"><?= e(recursoTipo($tipo)) ?></span></div></div><p class="desc"><?= e($r['descripcion'] ?: 'Recurso complementario para continuar tu preparación.') ?></p><?php if($r['autor']||$r['fuente']): ?><div class="text-secondary small mb-3"><?php if($r['autor']): ?><i class="bi bi-person me-1"></i><?= e($r['autor']) ?><?php endif; ?><?php if($r['autor']&&$r['fuente']): ?> · <?php endif; ?><?php if($r['fuente']): ?><?= e($r['fuente']) ?><?php endif; ?></div><?php endif; ?><a class="rbtn" href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir recurso <i class="bi bi-box-arrow-up-right"></i></a></div></article></div><?php endforeach; ?></div></section><?php else: ?><section class="resources"><h2><i class="bi bi-collection text-primary me-2"></i>Recursos complementarios</h2><p>Todavía no hay recursos adicionales publicados para este tema.</p></section><?php endif; ?>
<?php endif; ?></main></body></html>

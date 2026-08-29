<?php
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

$idTema = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idTema || $idTema <= 0) redireccionarDashboardUsuario();

if (empty($_SESSION['csrf_recursos'])) $_SESSION['csrf_recursos'] = bin2hex(random_bytes(32));

$tema = null; $recursos = []; $errores = []; $mensajes = [];

try {
    $stmt = $conexion->prepare(
        'SELECT t.id_tema,t.nombre,t.grado,m.nombre AS materia
         FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia
         WHERE t.id_tema=? LIMIT 1'
    );
    $stmt->execute([$idTema]); $tema = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tema) redireccionarDashboardUsuario();

    $stmt = $conexion->prepare('SELECT * FROM recursos WHERE id_tema=? ORDER BY id_recurso DESC');
    $stmt->execute([$idTema]); $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['ok'])) $mensajes[] = 'El recurso se guardó correctamente.';
    if (isset($_GET['deleted'])) $mensajes[] = 'El recurso se eliminó correctamente.';
} catch (PDOException $e) { $errores[] = 'No fue posible cargar los recursos del tema.'; }

$tipoLabels = [
    'video'=>['Video','bi-play-circle-fill'], 'pdf'=>['PDF','bi-file-earmark-pdf-fill'],
    'app'=>['Actividad','bi-controller'], 'juego'=>['Juego','bi-controller'],
    'simulador'=>['Simulador','bi-window-stack'], 'presentacion'=>['Presentación','bi-easel-fill'],
    'articulo'=>['Artículo','bi-file-text-fill'], 'blog'=>['Blog','bi-journal-text']
];

$urlGuardar = urlAplicacion('/admin/contenidos/guardar_recurso.php');
$urlEditarTema = urlAplicacion('/admin/contenidos/editar_tema.php?id='.$idTema);
$urlDashboard = urlAplicacion('/admin/dashboard.php');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recursos | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#172033}.navbar{box-shadow:0 3px 16px rgba(0,0,0,.12)}.page{max-width:1500px;margin:auto}.hero{background:linear-gradient(135deg,#0d6efd,#084298);color:#fff;border-radius:20px;padding:28px;box-shadow:0 15px 35px rgba(13,110,253,.16)}.card-soft{border:0;border-radius:20px;box-shadow:0 10px 30px rgba(20,35,60,.08)}.resource{height:100%;background:#fff;border:1px solid #e4e9f0;border-radius:18px;padding:18px}.icon{width:48px;height:48px;border-radius:14px;background:#eaf2ff;color:#0d6efd;display:flex;align-items:center;justify-content:center;font-size:21px;flex:0 0 auto}.url{background:#f7f9fc;border-radius:10px;padding:9px;font-size:.82rem;word-break:break-all}.badge-type{background:#eaf2ff;color:#0d6efd}.local{background:#dff7e8;color:#13733b}
</style></head><body>
<nav class="navbar navbar-dark bg-dark py-3"><div class="container-fluid px-3 px-lg-4"><a class="navbar-brand fw-bold" href="<?=htmlspecialchars($urlDashboard)?>"><i class="bi bi-mortarboard-fill"></i> Studia360</a><div class="d-flex align-items-center gap-2 text-white"><span class="d-none d-md-inline"><i class="bi bi-shield-check"></i> Administrador</span><a class="btn btn-outline-light btn-sm" href="<?=htmlspecialchars(urlAplicacion('/cerrar_sesion.php'))?>"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></div></div></nav>
<main class="container-fluid px-3 px-lg-5 py-4"><div class="page">
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"><div><div class="text-primary fw-bold small">RECURSOS COMPLEMENTARIOS</div><h1 class="fw-bold mb-1">Recursos de <?=htmlspecialchars($tema['nombre'])?></h1><div class="text-muted"><?=htmlspecialchars($tema['materia'])?> · <?=htmlspecialchars($tema['grado'])?>°</div></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?=htmlspecialchars($urlEditarTema)?>"><i class="bi bi-arrow-left"></i> Volver al editor</a></div></div>
<?php foreach($mensajes as $m):?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill"></i> <?=htmlspecialchars($m)?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach;?>
<?php foreach($errores as $e):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=htmlspecialchars($e)?></div><?php endforeach;?>
<div class="hero mb-4"><div class="row align-items-center g-3"><div class="col-lg-8"><div class="small fw-bold text-uppercase opacity-75">Contenido dinámico</div><h2 class="fw-bold">Haz que el tema sea más interactivo</h2><p class="mb-0 opacity-75">Añade videos, PDFs, actividades, juegos, simuladores, presentaciones y enlaces externos. Los PDFs remotos se intentan guardar automáticamente en el servidor local.</p></div><div class="col-lg-4 text-lg-end"><span class="fs-5"><i class="bi bi-collection-play"></i> <?=count($recursos)?> recurso(s)</span></div></div></div>
<div class="card card-soft mb-4"><div class="card-body p-4"><h4 class="fw-bold mb-1"><i class="bi bi-plus-circle text-primary"></i> Añadir recurso</h4><p class="text-muted mb-4">Los campos marcados con * son obligatorios.</p>
<form method="post" action="<?=htmlspecialchars($urlGuardar)?>" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_recursos'])?>"><input type="hidden" name="id_tema" value="<?=$idTema?>">
<div class="col-lg-6"><label class="form-label fw-semibold">Título *</label><input name="titulo" class="form-control" maxlength="200" required placeholder="Ej. Guía de ejercicios"></div>
<div class="col-md-6 col-lg-3"><label class="form-label fw-semibold">Tipo *</label><select name="tipo" id="tipo" class="form-select" required><?php foreach($tipoLabels as $k=>$v):?><option value="<?=$k?>"><?=htmlspecialchars($v[0])?></option><?php endforeach;?></select></div>
<div class="col-md-6 col-lg-3"><label class="form-label fw-semibold">Estado</label><select name="estado" class="form-select"><option value="Activo">Activo</option><option value="Inactivo">Inactivo</option></select></div>
<div class="col-12"><label class="form-label fw-semibold">URL del recurso</label><input name="url" id="url" type="url" class="form-control" maxlength="500" placeholder="https://..."><div class="form-text">Videos, actividades, juegos y sitios externos se muestran mediante este enlace. Para PDFs, Studia360 intentará crear una copia local.</div></div>
<div class="col-md-6"><label class="form-label fw-semibold">Archivo PDF (opcional)</label><input name="archivo" id="archivo" type="file" class="form-control" accept="application/pdf,.pdf"><div class="form-text">Máximo 25 MB. Si se selecciona, tendrá prioridad sobre la URL.</div></div>
<div class="col-md-6"><label class="form-label fw-semibold">Miniatura (opcional)</label><input name="imagen" type="file" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG o WEBP. Máximo 5 MB.</div></div>
<div class="col-12"><label class="form-label fw-semibold">Descripción</label><textarea name="descripcion" class="form-control" rows="3" maxlength="1000" placeholder="Explica qué encontrará el estudiante."></textarea></div>
<div class="col-md-6"><label class="form-label">Autor</label><input name="autor" class="form-control" maxlength="150"></div><div class="col-md-6"><label class="form-label">Fuente</label><input name="fuente" class="form-control" maxlength="150" placeholder="YouTube, Khan Academy, etc."></div>
<div class="col-12"><button class="btn btn-primary px-4"><i class="bi bi-plus-lg"></i> Añadir recurso</button></div>
</form></div></div>
<div class="row g-4">
<?php if(!$recursos):?><div class="col-12"><div class="card card-soft"><div class="card-body text-center py-5"><i class="bi bi-inbox fs-1 text-muted"></i><h4 class="mt-3">Todavía no hay recursos</h4><p class="text-muted mb-0">Cuando añadas uno aparecerá aquí y también en la vista del estudiante.</p></div></div></div><?php endif;?>
<?php foreach($recursos as $r): $tipo=$tipoLabels[$r['tipo']]??['Recurso','bi-link-45deg']; $local=strpos((string)$r['url'],'/assets/uploads/recursos/')!==false; ?>
<div class="col-md-6 col-xl-4"><div class="resource d-flex flex-column"><div class="d-flex gap-3"><div class="icon"><i class="bi <?=htmlspecialchars($tipo[1])?>"></i></div><div class="flex-grow-1"><h5 class="fw-bold mb-1"><?=htmlspecialchars($r['titulo'])?></h5><span class="badge badge-type"><?=htmlspecialchars($tipo[0])?></span> <span class="badge <?=$r['estado']==='Activo'?'local':'text-bg-secondary'?>"><?=htmlspecialchars($r['estado'])?></span></div></div><?php if(!empty($r['imagen'])):?><img src="<?=htmlspecialchars($r['imagen'])?>" class="img-fluid rounded-3 mt-3" style="max-height:150px;object-fit:cover" alt=""><?php endif;?><p class="text-muted mt-3 mb-2"><?=nl2br(htmlspecialchars($r['descripcion']??''))?></p><div class="url mb-3"><i class="bi bi-link-45deg"></i> <?=htmlspecialchars($r['url'])?> <?php if($local):?><span class="badge local">Local</span><?php endif;?></div><div class="mt-auto d-flex gap-2"><a target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm" href="<?=htmlspecialchars($r['url'])?>"><i class="bi bi-box-arrow-up-right"></i> Abrir</a><form method="post" action="<?=htmlspecialchars(urlAplicacion('/admin/contenidos/eliminar_recurso.php'))?>" onsubmit="return confirm('¿Eliminar este recurso?');"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_recursos'])?>"><input type="hidden" name="id_recurso" value="<?=intval($r['id_recurso'])?>"><input type="hidden" name="id_tema" value="<?=$idTema?>"><button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button></form></div></div></div>
<?php endforeach;?></div>
</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>

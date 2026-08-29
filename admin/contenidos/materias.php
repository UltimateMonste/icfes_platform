<?php
/**
 * Studia360 - Gestión de materias
 * Archivo: admin/contenidos/materias.php
 */
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
$errores=[]; $mensajes=[];
function e(?string $valor): string { return htmlspecialchars($valor ?? "", ENT_QUOTES, "UTF-8"); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion=trim($_POST["accion"] ?? "");
    $idMateria=filter_var($_POST["id_materia"] ?? null,FILTER_VALIDATE_INT);
    if ($accion === "crear") {
        $nombre=trim($_POST["nombre"] ?? ""); $descripcion=trim($_POST["descripcion"] ?? "");
        if($nombre==="") $errores[]="El nombre de la materia es obligatorio.";
        if(mb_strlen($nombre)>100) $errores[]="El nombre de la materia no puede superar los 100 caracteres.";
        if(empty($errores)) try {
            $stmt=$conexion->prepare("SELECT COUNT(*) FROM materias WHERE LOWER(nombre)=LOWER(?)"); $stmt->execute([$nombre]);
            if((int)$stmt->fetchColumn()>0) $errores[]="Ya existe una materia con ese nombre.";
            else { $stmt=$conexion->prepare("INSERT INTO materias (nombre,descripcion) VALUES (?,?)"); $stmt->execute([$nombre,$descripcion!==""?$descripcion:null]); $mensajes[]="La materia se creó correctamente."; }
        } catch(PDOException $e){$errores[]="No fue posible crear la materia.";}
    } elseif($accion === "eliminar") {
        if(!$idMateria || $idMateria<=0) $errores[]="La materia seleccionada no es válida.";
        else try {
            $stmt=$conexion->prepare("SELECT COUNT(*) FROM temas WHERE id_materia=?"); $stmt->execute([$idMateria]); $cantidad=(int)$stmt->fetchColumn();
            if($cantidad>0) $errores[]="No puedes eliminar esta materia porque tiene {$cantidad} tema(s) asociado(s). Elimina o reasigna primero esos temas.";
            else { $stmt=$conexion->prepare("DELETE FROM materias WHERE id_materia=?"); $stmt->execute([$idMateria]); $stmt->rowCount()>0 ? $mensajes[]="La materia se eliminó correctamente." : $errores[]="La materia no existe o ya fue eliminada."; }
        } catch(PDOException $e){$errores[]="No fue posible eliminar la materia.";}
    }
}

$materias=[];
try {
    $stmt=$conexion->query("SELECT m.id_materia,m.nombre,m.descripcion,COUNT(t.id_tema) AS cantidad_temas FROM materias m LEFT JOIN temas t ON t.id_materia=m.id_materia GROUP BY m.id_materia,m.nombre,m.descripcion ORDER BY m.nombre ASC");
    $materias=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){$errores[]="No fue posible cargar las materias.";}
$urlDashboard=urlAplicacion("/admin/dashboard.php");
$urlTodosTemas=urlAplicacion("/admin/contenidos/temas.php");
$urlCerrarSesion=urlAplicacion("/cerrar_sesion.php");
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Materias | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--primary:#0d6efd;--dark:#20252b;--bg:#f4f7fb;--border:#e2e8f0;--muted:#64748b}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#172033;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.navbar-studia{background:var(--dark);min-height:58px;box-shadow:0 3px 14px rgba(15,23,42,.12)}.brand{font-weight:800}.page{width:min(1180px,calc(100% - 32px));margin:30px auto 60px}.hero{background:linear-gradient(135deg,#0d6efd,#084298);color:#fff;border-radius:22px;padding:30px;margin-bottom:22px;box-shadow:0 16px 35px rgba(13,110,253,.16)}.hero h1{font-size:clamp(2rem,4vw,3rem);font-weight:850;letter-spacing:-.04em}.hero p{max-width:700px}.quick-actions{display:flex;gap:10px;flex-wrap:wrap}.quick-actions .btn,.subject-actions .btn{border-radius:11px;font-weight:700}.all-topics{background:#fff;border:1px solid #dbe7f7;border-radius:20px;padding:19px 22px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:18px;box-shadow:0 7px 22px rgba(15,23,42,.045)}.all-icon,.subject-icon{display:flex;align-items:center;justify-content:center;background:#edf4ff;color:var(--primary);flex-shrink:0}.all-icon{width:48px;height:48px;border-radius:14px;font-size:1.35rem}.subject-card{height:100%;background:#fff;border:1px solid var(--border);border-radius:20px;padding:22px;box-shadow:0 7px 22px rgba(15,23,42,.05);transition:.18s;display:flex;flex-direction:column}.subject-card:hover{transform:translateY(-4px);box-shadow:0 15px 32px rgba(15,23,42,.1);border-color:#c7d2e3}.subject-icon{width:54px;height:54px;border-radius:16px;font-size:1.5rem}.subject-title{font-weight:850;letter-spacing:-.02em}.subject-description{color:var(--muted);line-height:1.65;min-height:52px}.topic-count{background:#eef5ff;color:#0b5ed7;font-weight:750;border:1px solid #d9e8ff}.subject-actions{margin-top:auto}.alert{border-radius:14px}@media(max-width:767px){.page{width:calc(100% - 20px);margin-top:15px}.hero{padding:24px;border-radius:18px}.all-topics{align-items:flex-start;flex-direction:column}.all-topics .btn{width:100%}}
</style></head><body>
<nav class="navbar navbar-dark navbar-studia"><div class="container-fluid px-3 px-md-4"><a href="<?=e($urlDashboard)?>" class="navbar-brand brand"><i class="bi bi-mortarboard-fill me-2"></i>Studia360</a><div class="d-flex align-items-center gap-2"><span class="text-white small d-none d-md-inline"><i class="bi bi-shield-check me-1"></i><?=e(trim(($_SESSION["nombres"]??"")." ".($_SESSION["apellidos"]??""))?:"Administrador")?></span><a href="<?=e($urlDashboard)?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a><a href="<?=e($urlCerrarSesion)?>" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Salir</a></div></div></nav>
<main class="page"><section class="hero"><div class="d-flex justify-content-between align-items-center flex-wrap gap-4"><div><div class="small text-uppercase fw-bold opacity-75 mb-2">Administración académica</div><h1 class="mb-2">Materias</h1><p class="mb-0 opacity-75">Organiza las áreas de estudio de Studia360 y accede directamente a los temas pertenecientes a cada una.</p></div><div class="quick-actions"><a href="<?=e($urlTodosTemas)?>" class="btn btn-light"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Ver todos los temas</a><button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg me-1"></i>Nueva materia</button></div></div></section>
<?php foreach($mensajes as $m):?><div class="alert alert-success border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2"></i><?=e($m)?></div><?php endforeach;?>
<?php foreach($errores as $er):?><div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?=e($er)?></div><?php endforeach;?>
<div class="all-topics"><div class="d-flex align-items-center gap-3"><div class="all-icon"><i class="bi bi-collection-play-fill"></i></div><div><div class="text-uppercase small fw-bold text-secondary">Acceso general</div><h2 class="h5 fw-bold mb-1">Todos los temas de Studia360</h2><p class="mb-0 text-secondary">Consulta los temas de todas las materias y filtra por grado cuando necesites una vista global.</p></div></div><a href="<?=e($urlTodosTemas)?>" class="btn btn-primary px-4"><i class="bi bi-arrow-right-circle me-1"></i>Ver todos los temas</a></div>
<div class="row g-4">
<?php if(empty($materias)):?><div class="col-12"><div class="bg-white border rounded-4 p-5 text-center"><i class="bi bi-book" style="font-size:3rem;color:#0d6efd"></i><h3 class="h5 mt-3">No hay materias registradas</h3><p class="text-secondary mb-0">Crea la primera materia para comenzar.</p></div></div><?php endif;?>
<?php foreach($materias as $materia):?><div class="col-md-6 col-xl-4"><article class="subject-card"><div class="d-flex gap-3 mb-3"><div class="subject-icon"><i class="bi bi-book-fill"></i></div><div class="flex-grow-1"><h2 class="subject-title h5 mb-2"><?=e($materia["nombre"])?></h2><span class="badge topic-count rounded-pill px-3 py-2"><i class="bi bi-journal-text me-1"></i><?= (int)$materia["cantidad_temas"]?> <?=((int)$materia["cantidad_temas"]===1?"tema":"temas")?></span></div></div><p class="subject-description mb-4"><?=e($materia["descripcion"]?:"Esta materia todavía no tiene una descripción.")?></p><div class="subject-actions"><div class="d-flex gap-2"><a href="editar_materia.php?id=<?=(int)$materia["id_materia"]?>" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-pencil me-1"></i>Editar</a><a href="temas.php?id_materia=<?=(int)$materia["id_materia"]?>" class="btn btn-primary flex-grow-1"><i class="bi bi-journal-text me-1"></i>Ver temas</a></div><form method="POST" class="mt-2" onsubmit="return confirm('¿Seguro que deseas eliminar esta materia? Solo podrá eliminarse si no tiene temas asociados.');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_materia" value="<?=(int)$materia["id_materia"]?>"><button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash3 me-1"></i>Eliminar materia</button></form></div></article></div><?php endforeach;?></div></main>
<div class="modal fade" id="modalCrear" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow"><div class="modal-header px-4"><h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Nueva materia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body px-4"><input type="hidden" name="accion" value="crear"><div class="mb-3"><label class="form-label fw-semibold">Nombre</label><input type="text" name="nombre" class="form-control form-control-lg" maxlength="100" required placeholder="Ej. Matemáticas"></div><div class="mb-3"><label class="form-label fw-semibold">Descripción</label><textarea name="descripcion" class="form-control" rows="4" placeholder="Describe brevemente la materia..."></textarea></div></div><div class="modal-footer px-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Crear materia</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>

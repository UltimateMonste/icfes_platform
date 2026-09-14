<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }

$errores=[];$mensajes=[];
if (empty($_SESSION['csrf_materias'])) $_SESSION['csrf_materias']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_materias'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) $errores[]='La sesión del formulario expiró. Recarga la página.';
    else {
        $accion=(string)($_POST['accion']??'');
        try {
            if ($accion==='crear') {
                $nombre=trim((string)($_POST['nombre']??''));$desc=trim((string)($_POST['descripcion']??''));
                if($nombre==='')$errores[]='El nombre de la materia es obligatorio.';
                elseif(mb_strlen($nombre)>100)$errores[]='El nombre no puede superar los 100 caracteres.';
                if(!$errores){
                    $st=$conexion->prepare("SELECT COUNT(*) FROM materias WHERE LOWER(nombre)=LOWER(?)");$st->execute([$nombre]);
                    if((int)$st->fetchColumn()>0)$errores[]='Ya existe una materia con ese nombre.';
                    else{$st=$conexion->prepare("INSERT INTO materias(nombre,descripcion) VALUES(?,?)");$st->execute([$nombre,$desc!==''?$desc:null]);$mensajes[]='Materia creada correctamente.';}
                }
            } elseif($accion==='eliminar') {
                $id=(int)($_POST['id_materia']??0);
                if($id<=0)$errores[]='La materia seleccionada no es válida.';
                else{$st=$conexion->prepare("SELECT COUNT(*) FROM temas WHERE id_materia=?");$st->execute([$id]);$n=(int)$st->fetchColumn();
                    if($n>0)$errores[]="No puedes eliminar esta materia porque tiene {$n} tema(s) asociado(s).";
                    else{$st=$conexion->prepare("DELETE FROM materias WHERE id_materia=?");$st->execute([$id]);$mensajes[]='Materia eliminada correctamente.';}
                }
            }
        } catch(PDOException $ex){$errores[]='No fue posible completar la operación.';}
    }
}
try{
    $st=$conexion->query("SELECT m.id_materia,m.nombre,m.descripcion,COUNT(t.id_tema) cantidad_temas FROM materias m LEFT JOIN temas t ON t.id_materia=m.id_materia GROUP BY m.id_materia,m.nombre,m.descripcion ORDER BY m.nombre");
    $materias=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $ex){$materias=[];$errores[]='No fue posible cargar las materias.';}
$urlDashboard=urlAplicacion('/admin/dashboard.php');$urlTemas=urlAplicacion('/admin/contenidos/temas.php');$urlSalir=urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Materias | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.materia-card{height:100%;padding:20px;background:#fff;border:1px solid #e4eaf2;border-radius:20px;box-shadow:0 10px 28px rgba(23,32,51,.045);transition:.2s;display:flex;flex-direction:column}
.materia-card:hover{transform:translateY(-3px);box-shadow:0 16px 34px rgba(23,32,51,.08)}
.materia-icon{width:46px;height:46px;border-radius:14px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.materia-actions{margin-top:auto;display:flex;gap:8px}.materia-actions .btn{border-radius:10px;font-size:.82rem;font-weight:700}
.topic-pill{font-size:.72rem;font-weight:750;color:#526176;background:#f8fafc;border:1px solid #e4eaf2}
</style><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">
</head><body>
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex align-items-center justify-content-between">
<a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a>
<div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlDashboard)?>"><i class="bi bi-grid me-1"></i>Dashboard</a><a class="btn btn-outline-secondary s360-btn" href="<?=e($urlSalir)?>">Salir</a></div>
</div></nav>
<main class="s360-shell">
<section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Estructura académica</div><h1 class="h2 mb-2">Materias</h1><p class="mb-0 opacity-75">Administra las áreas de estudio y entra a sus temas sin llenar la pantalla de botones.</p></div><div class="col-auto d-flex gap-2 flex-wrap"><a href="<?=e($urlTemas)?>" class="btn btn-light s360-btn"><i class="bi bi-journals me-1"></i>Todos los temas</a><button class="btn btn-light s360-btn" data-bs-toggle="modal" data-bs-target="#crear"><i class="bi bi-plus-lg me-1"></i>Nueva materia</button></div></div></section>
<?php foreach($mensajes as $m):?><div class="alert alert-success border-0 shadow-sm"><?=e($m)?></div><?php endforeach;?>
<?php foreach($errores as $m):?><div class="alert alert-danger border-0 shadow-sm"><?=e($m)?></div><?php endforeach;?>
<div class="row g-3">
<?php if(!$materias):?><div class="col-12"><div class="s360-empty"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-book"></i></div><h2 class="h5 fw-bold">Aún no hay materias</h2><p class="s360-muted mb-0">Crea la primera para comenzar a organizar los temas.</p></div></div><?php endif;?>
<?php foreach($materias as $m):?>
<div class="col-12 col-md-6 col-xl-4"><article class="materia-card">
<div class="d-flex gap-3 align-items-start mb-3"><div class="materia-icon"><i class="bi bi-book-fill"></i></div><div class="min-w-0"><h2 class="h5 fw-bold mb-2"><?=e($m['nombre'])?></h2><span class="badge rounded-pill topic-pill"><?= (int)$m['cantidad_temas']?> <?=((int)$m['cantidad_temas']===1?'tema':'temas')?></span></div></div>
<p class="s360-muted small mb-4" style="line-height:1.6"><?=e($m['descripcion']?:'Esta materia todavía no tiene una descripción.')?></p>
<div class="materia-actions"><a class="btn btn-primary flex-grow-1" href="temas.php?id_materia=<?=(int)$m['id_materia']?>"><i class="bi bi-arrow-right me-1"></i>Ver temas</a><a class="btn btn-outline-secondary" href="editar_materia.php?id=<?=(int)$m['id_materia']?>" title="Editar materia"><i class="bi bi-pencil"></i></a></div>
<form method="post" class="mt-2" onsubmit="return confirm('¿Eliminar esta materia? Solo es posible si no tiene temas asociados.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_materia" value="<?=(int)$m['id_materia']?>"><button class="btn btn-sm btn-outline-danger w-100" type="submit"><i class="bi bi-trash3 me-1"></i>Eliminar</button></form>
</article></div>
<?php endforeach;?></div>
</main>
<div class="modal fade" id="crear" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="crear"><div class="modal-header px-4"><h2 class="h5 fw-bold mb-0">Nueva materia</h2><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body px-4"><label class="form-label fw-semibold">Nombre</label><input class="form-control mb-3" name="nombre" maxlength="100" required placeholder="Ej. Matemáticas"><label class="form-label fw-semibold">Descripción</label><textarea class="form-control" name="descripcion" rows="4" placeholder="Describe brevemente la materia"></textarea></div><div class="modal-footer px-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Crear materia</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>

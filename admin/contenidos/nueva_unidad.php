<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$errores=[];
if (empty($_SESSION['csrf_unidad'])) $_SESSION['csrf_unidad']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_unidad'];
$materias=[];
try { $materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC); }
catch(PDOException $ex){ $errores[]='No fue posible cargar las materias.'; }
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??''))) $errores[]='La sesión del formulario expiró. Recarga la página.';
  else {
    $idMateria=(int)($_POST['id_materia']??0);
    $grado=(string)($_POST['grado']??'');
    $nombre=trim((string)($_POST['nombre']??''));
    $descripcion=trim((string)($_POST['descripcion']??''));
    if($idMateria<=0) $errores[]='Selecciona una materia.';
    if(!in_array($grado,['9','10','11'],true)) $errores[]='Selecciona un grado válido.';
    if($nombre==='') $errores[]='El nombre de la unidad temática es obligatorio.';
    elseif(mb_strlen($nombre)>100) $errores[]='El nombre no puede superar los 100 caracteres.';
    if(mb_strlen($descripcion)>500) $errores[]='La descripción no puede superar los 500 caracteres.';
    if(!$errores){
      try{
        $st=$conexion->prepare("SELECT COUNT(*) FROM materias WHERE id_materia=?");$st->execute([$idMateria]);
        if(!(int)$st->fetchColumn()) $errores[]='La materia seleccionada no existe.';
        else{
          $st=$conexion->prepare("SELECT COUNT(*) FROM unidades_tematicas WHERE id_materia=? AND grado=? AND LOWER(nombre)=LOWER(?)");
          $st->execute([$idMateria,$grado,$nombre]);
          if((int)$st->fetchColumn()>0) $errores[]='Ya existe una unidad con ese nombre en esa materia y grado.';
          else{
            $st=$conexion->prepare("INSERT INTO unidades_tematicas (id_materia,grado,nombre,descripcion,estado,es_predeterminada) VALUES (?,?,?,?, 'Activa',0)");
            $st->execute([$idMateria,$grado,$nombre,$descripcion!==''?$descripcion:null]);
            $id=(int)$conexion->lastInsertId();
            header('Location: unidades.php?id_materia='.$idMateria.'&grado='.$grado.'&creado=1'); exit;
          }
        }
      }catch(PDOException $ex){$errores[]='No fue posible crear la unidad temática.';}
    }
  }
}
$urlDashboard=urlAplicacion('/admin/dashboard.php');$urlUnidades=urlAplicacion('/admin/contenidos/unidades.php');$urlSalir=urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nueva unidad temática | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>"><style>
body{background:#f6f8fc}.form-card{max-width:760px;margin:auto;background:#fff;border:1px solid #e5eaf1;border-radius:22px;padding:26px;box-shadow:0 12px 32px rgba(23,32,51,.06)}.hint{font-size:.8rem;color:#748196;line-height:1.5}.form-control,.form-select{border-radius:11px}.s360-shell{width:min(900px,calc(100% - 30px));margin:auto;padding:28px 0 60px}.s360-hero{border-radius:22px;padding:24px;background:linear-gradient(125deg,#2563eb,#4f46e5);color:#fff}.s360-hero h1{color:#fff}
</style></head><body class="s360-admin s360-content-theme"><nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border" href="<?=e($urlUnidades)?>"><i class="bi bi-arrow-left me-1"></i>Unidades</a><a class="btn btn-outline-secondary" href="<?=e($urlSalir)?>">Salir</a></div></div></nav><main class="s360-shell"><section class="s360-hero mb-4"><div class="small fw-bold opacity-75 text-uppercase">Estructura académica</div><h1 class="h2 mb-2">Nueva unidad temática</h1><p class="mb-0 opacity-75">Cada unidad pertenece a una materia y a un grado específico.</p></section><?php foreach($errores as $er):?><div class="alert alert-danger border-0 shadow-sm"><?=e($er)?></div><?php endforeach;?><section class="form-card"><form method="post" novalidate><input type="hidden" name="csrf" value="<?=e($csrf)?>"><div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Materia</label><select class="form-select" name="id_materia" required><option value="">Selecciona una materia</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=((string)($_POST['id_materia']??'')===(string)$m['id_materia'])?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label fw-semibold">Grado</label><select class="form-select" name="grado" required><option value="">Selecciona un grado</option><?php foreach(['9'=>'9°','10'=>'10°','11'=>'11°'] as $g=>$label):?><option value="<?=$g?>" <?=((string)($_POST['grado']??'')===$g)?'selected':''?>><?=$label?></option><?php endforeach;?></select></div><div class="col-12"><label class="form-label fw-semibold">Nombre</label><input class="form-control" name="nombre" maxlength="100" required value="<?=e($_POST['nombre']??'')?>" placeholder="Ej. Álgebra"><div class="hint mt-2">El nombre puede repetirse en otros grados. Por ejemplo, “Álgebra” de 9° y “Álgebra” de 10° son unidades diferentes.</div></div><div class="col-12"><label class="form-label fw-semibold">Descripción <span class="text-secondary fw-normal">(opcional)</span></label><textarea class="form-control" name="descripcion" maxlength="500" rows="4" placeholder="Describe brevemente qué se estudiará en esta unidad."><?=e($_POST['descripcion']??'')?></textarea></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light border" href="<?=e($urlUnidades)?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Crear unidad</button></div></form></section></main></body></html>

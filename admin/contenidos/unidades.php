<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$errores=[];$mensajes=[];
if(empty($_SESSION['csrf_unidades']))$_SESSION['csrf_unidades']=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf_unidades'];
$idMateria=filter_var($_GET['id_materia']??null,FILTER_VALIDATE_INT);if(!$idMateria||$idMateria<=0)$idMateria=null;
$grado=(string)($_GET['grado']??'');if(!in_array($grado,['9','10','11'],true))$grado='';
if(($_GET['creado']??'')==='1')$mensajes[]='Unidad temática creada correctamente.';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??'')))$errores[]='La sesión del formulario expiró. Recarga la página.';
 else if(($_POST['accion']??'')==='eliminar'){
  $uid=(int)($_POST['id_unidad']??0);try{$st=$conexion->prepare("SELECT es_predeterminada FROM unidades_tematicas WHERE id_unidad=?");$st->execute([$uid]);$u=$st->fetch(PDO::FETCH_ASSOC);if(!$u)$errores[]='La unidad no existe.';elseif((int)$u['es_predeterminada']===1)$errores[]='La Temática general de transición no se puede eliminar.';else{$st=$conexion->prepare("SELECT COUNT(*) FROM temas WHERE id_unidad=?");$st->execute([$uid]);$n=(int)$st->fetchColumn();if($n>0)$errores[]="No puedes eliminar esta unidad porque tiene {$n} tema(s) asociado(s).";else{$st=$conexion->prepare("DELETE FROM unidades_tematicas WHERE id_unidad=?");$st->execute([$uid]);$mensajes[]='Unidad temática eliminada correctamente.';}}}catch(PDOException $ex){$errores[]='No fue posible eliminar la unidad temática.';}
 }
}
try{$materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);$sql="SELECT u.id_unidad,u.id_materia,u.grado,u.nombre,u.descripcion,u.estado,u.es_predeterminada,m.nombre AS materia,COUNT(t.id_tema) AS total_temas FROM unidades_tematicas u INNER JOIN materias m ON m.id_materia=u.id_materia LEFT JOIN temas t ON t.id_unidad=u.id_unidad WHERE u.estado='Activa'";$p=[];if($idMateria){$sql.=" AND u.id_materia=?";$p[]=$idMateria;}if($grado){$sql.=" AND u.grado=?";$p[]=$grado;}$sql.=" GROUP BY u.id_unidad,m.nombre ORDER BY m.nombre,CAST(u.grado AS UNSIGNED),u.es_predeterminada DESC,u.nombre";$st=$conexion->prepare($sql);$st->execute($p);$unidades=$st->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $ex){$materias=[];$unidades=[];$errores[]='No fue posible cargar las unidades temáticas.';}
$urlDashboard=urlAplicacion('/admin/dashboard.php');$urlNuevo=urlAplicacion('/admin/contenidos/nueva_unidad.php');$urlMaterias=urlAplicacion('/admin/contenidos/materias.php');$urlSalir=urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unidades temáticas | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>"><style>.unit-card{height:100%;padding:20px;background:#fff;border:1px solid #e4eaf2;border-radius:20px;box-shadow:0 10px 28px rgba(23,32,51,.045);display:flex;flex-direction:column}.unit-icon{width:46px;height:46px;border-radius:14px;background:#eff6ff;color:#2563eb;display:grid;place-items:center;font-size:1.15rem}.unit-actions{margin-top:auto;display:flex;gap:8px}.unit-actions .btn{border-radius:10px;font-weight:700}.filterbar{background:#fff;border:1px solid #e4eaf2;border-radius:20px;padding:16px;box-shadow:0 8px 24px rgba(23,32,51,.04)}.chip{border:1px solid #dbe3ee;background:#fff;color:#526176;border-radius:11px;padding:.5rem .75rem;text-decoration:none;font-weight:750;font-size:.8rem}.chip.active{background:#2563eb;color:#fff;border-color:#2563eb}body.s360-content-theme .unit-card,body.s360-content-theme .filterbar{background:var(--s360card,#fff)!important;color:var(--s360text,#1f2937)!important;border-color:var(--s360line,#e5eaf1)!important}body.s360-content-theme.s360-content-dark .unit-card,body.s360-content-theme.s360-content-dark .filterbar{background:#172236!important;color:#edf2f8!important;border-color:#2b374b!important}body.s360-content-theme.s360-content-dark .unit-card .text-secondary,body.s360-content-theme.s360-content-dark .filterbar .text-secondary{color:#9ba8ba!important}body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:#dbe5f2!important}body.s360-content-theme.s360-content-dark .form-select{background:#111827!important;color:#edf2f8!important;border-color:#334158!important}</style><style id="studia360-theme-unit-fix">
:root{--s360a:#2563eb;--s360a2:#4f46e5;--s360soft:#eff6ff;--s360bg:#f6f8fc;--s360card:#fff;--s360text:#1f2937;--s360muted:#748196;--s360line:#e5eaf1;--s360input:#fff}
body.s360-content-theme{background:var(--s360bg)!important;color:var(--s360text)!important}
body.s360-content-theme.s360-accent-orange{--s360a:#f97316;--s360a2:#ea580c;--s360soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--s360a:#8b5cf6;--s360a2:#7c3aed;--s360soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--s360a:#10b981;--s360a2:#059669;--s360soft:#ecfdf5}
body.s360-content-theme .s360-hero{background:linear-gradient(125deg,var(--s360a),var(--s360a2))!important}
body.s360-content-theme .unit-card,body.s360-content-theme .filterbar,body.s360-content-theme .form-card,body.s360-content-theme .s360-empty{background:var(--s360card)!important;color:var(--s360text)!important;border-color:var(--s360line)!important}
body.s360-content-theme .unit-icon{background:var(--s360soft)!important;color:var(--s360a)!important}
body.s360-content-theme .text-secondary,body.s360-content-theme .hint,body.s360-content-theme .meta,body.s360-content-theme .s360-muted{color:var(--s360muted)!important}
body.s360-content-theme .form-label{color:var(--s360text)!important}
body.s360-content-theme .form-control,body.s360-content-theme .form-select{background:var(--s360input)!important;color:var(--s360text)!important;border-color:var(--s360line)!important}
body.s360-content-theme .btn-primary{background:var(--s360a)!important;border-color:var(--s360a)!important;color:#fff!important}
body.s360-content-theme .btn-outline-primary{color:var(--s360a)!important;border-color:color-mix(in srgb,var(--s360a) 35%,var(--s360line))!important;background:var(--s360card)!important}
body.s360-content-theme .btn-light{background:var(--s360card)!important;color:var(--s360text)!important;border-color:var(--s360line)!important}
body.s360-content-theme .btn-outline-secondary{background:transparent!important;color:var(--s360muted)!important;border-color:var(--s360line)!important}
body.s360-content-theme .bg-light{background:var(--s360soft)!important;color:var(--s360text)!important}
body.s360-content-theme .alert{border:1px solid var(--s360line)!important}
body.s360-content-theme.s360-content-dark{--s360bg:#0d1424;--s360card:#172236;--s360text:#edf2f8;--s360muted:#9ba8ba;--s360line:#2b374b;--s360input:#111827}
body.s360-content-theme.s360-content-dark .s360-topbar{background:rgba(13,20,36,.94)!important;border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .unit-card,body.s360-content-theme.s360-content-dark .filterbar,body.s360-content-theme.s360-content-dark .form-card,body.s360-content-theme.s360-content-dark .s360-empty{background:#172236!important;color:#edf2f8!important;border-color:#2b374b!important;box-shadow:0 10px 28px rgba(0,0,0,.22)!important}
body.s360-content-theme.s360-content-dark .unit-icon{background:color-mix(in srgb,var(--s360a) 18%,#111827)!important;color:#fff!important}
body.s360-content-theme.s360-content-dark .form-control,body.s360-content-theme.s360-content-dark .form-select{background:#111827!important;color:#edf2f8!important;border-color:#334158!important}
body.s360-content-theme.s360-content-dark .btn-light{background:#202c41!important;color:#edf2f8!important;border-color:#3a4860!important}
body.s360-content-theme.s360-content-dark .btn-outline-secondary{background:transparent!important;color:#c4cfdd!important;border-color:#46536a!important}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:#dbe5f2!important;border-color:#3a4860!important}
body.s360-content-theme.s360-content-dark .alert-danger{background:#45242b!important;color:#ffd4d9!important}
body.s360-content-theme.s360-content-dark .alert-success{background:#123c32!important;color:#b9f5df!important}
body.s360-content-theme.s360-content-dark .alert-info{background:#153247!important;color:#d8efff!important}
</style>
<style id="studia360-unit-personalization-final">
/* =========================================================
   Studia360 · Personalización final para unidades
   Evita superficies blancas y textos ilegibles en cualquier
   combinación de paleta + modo.
   ========================================================= */
body.s360-content-theme{
  --s360-a:#2563eb;
  --s360-a2:#4f46e5;
  --s360-soft:#eff6ff;
  --s360-bg:#f6f8fc;
  --s360-card:#ffffff;
  --s360-text:#172033;
  --s360-muted:#66758a;
  --s360-line:#dfe6ef;
  --s360-input:#ffffff;
  background:var(--s360-bg)!important;
  color:var(--s360-text)!important;
}
body.s360-content-theme.s360-accent-orange{
  --s360-a:#f97316;--s360-a2:#ea580c;--s360-soft:#fff7ed;
}
body.s360-content-theme.s360-accent-purple{
  --s360-a:#8b5cf6;--s360-a2:#7c3aed;--s360-soft:#f5f3ff;
}
body.s360-content-theme.s360-accent-green{
  --s360-a:#10b981;--s360-a2:#059669;--s360-soft:#ecfdf5;
}

/* Modo oscuro */
body.s360-content-theme.s360-content-dark{
  --s360-bg:#0b1220;
  --s360-card:#172236;
  --s360-text:#f1f5fb;
  --s360-muted:#a8b4c6;
  --s360-line:#2c3a50;
  --s360-input:#101827;
  background:var(--s360-bg)!important;
  color:var(--s360-text)!important;
}

/* Superficies */
body.s360-content-theme .unit-card,
body.s360-content-theme .filterbar,
body.s360-content-theme .form-card,
body.s360-content-theme .s360-empty{
  background:var(--s360-card)!important;
  color:var(--s360-text)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .unit-card,
body.s360-content-theme.s360-content-dark .filterbar,
body.s360-content-theme.s360-content-dark .form-card,
body.s360-content-theme.s360-content-dark .s360-empty{
  background:#172236!important;
  color:#f1f5fb!important;
  border-color:#2c3a50!important;
  box-shadow:0 12px 30px rgba(0,0,0,.20)!important;
}

/* Textos: evita títulos negros sobre tarjetas oscuras */
body.s360-content-theme .unit-card h1,
body.s360-content-theme .unit-card h2,
body.s360-content-theme .unit-card h3,
body.s360-content-theme .form-card h1,
body.s360-content-theme .form-card h2,
body.s360-content-theme .form-card h3,
body.s360-content-theme .filterbar label,
body.s360-content-theme .form-label{
  color:var(--s360-text)!important;
}
body.s360-content-theme.s360-content-dark .unit-card h1,
body.s360-content-theme.s360-content-dark .unit-card h2,
body.s360-content-theme.s360-content-dark .unit-card h3,
body.s360-content-theme.s360-content-dark .form-card h1,
body.s360-content-theme.s360-content-dark .form-card h2,
body.s360-content-theme.s360-content-dark .form-card h3,
body.s360-content-theme.s360-content-dark .filterbar label,
body.s360-content-theme.s360-content-dark .form-label{
  color:#f1f5fb!important;
}
body.s360-content-theme .unit-card .text-secondary,
body.s360-content-theme .form-card .text-secondary,
body.s360-content-theme .hint,
body.s360-content-theme .meta,
body.s360-content-theme .s360-muted{
  color:var(--s360-muted)!important;
}

/* Iconos */
body.s360-content-theme .unit-icon{
  background:var(--s360-soft)!important;
  color:var(--s360-a)!important;
}
body.s360-content-theme.s360-content-dark .unit-icon{
  background:color-mix(in srgb,var(--s360-a) 18%,#101827)!important;
  color:#fff!important;
}

/* Inputs */
body.s360-content-theme .form-control,
body.s360-content-theme .form-select{
  background:var(--s360-input)!important;
  color:var(--s360-text)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme .form-control::placeholder{
  color:var(--s360-muted)!important;
  opacity:1!important;
}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select{
  background:#101827!important;
  color:#f1f5fb!important;
  border-color:#39485e!important;
}
body.s360-content-theme.s360-content-dark .form-select option{
  background:#101827!important;
  color:#f1f5fb!important;
}

/* Botones */
body.s360-content-theme .btn-primary{
  background:var(--s360-a)!important;
  border-color:var(--s360-a)!important;
  color:#fff!important;
}
body.s360-content-theme .btn-light{
  background:var(--s360-card)!important;
  border-color:var(--s360-line)!important;
  color:var(--s360-text)!important;
}
body.s360-content-theme.s360-content-dark .btn-light{
  background:#202c41!important;
  border-color:#3b4a61!important;
  color:#f1f5fb!important;
}
body.s360-content-theme .btn-outline-secondary{
  background:transparent!important;
  color:var(--s360-muted)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .btn-outline-secondary{
  color:#c9d3e0!important;
  border-color:#46556c!important;
}

/* Badges / contadores */
body.s360-content-theme .bg-light{
  background:var(--s360-soft)!important;
  color:var(--s360-text)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .bg-light{
  background:#202c41!important;
  color:#e4ebf4!important;
  border-color:#3b4a61!important;
}

/* Alertas */
body.s360-content-theme .alert{
  color:var(--s360-text)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .alert-danger{
  background:#45242b!important;color:#ffd6db!important;
}
body.s360-content-theme.s360-content-dark .alert-success{
  background:#123c32!important;color:#b9f5df!important;
}
body.s360-content-theme.s360-content-dark .alert-info{
  background:#153247!important;color:#d8efff!important;
}

/* =========================================================
   Personalizador: swatches visibles y panel consistente
   ========================================================= */
body.s360-content-theme .adm-theme-toggle{
  z-index:99999!important;
  color:#fff!important;
  background:linear-gradient(145deg,var(--s360-a),var(--s360-a2))!important;
}
body.s360-content-theme .adm-theme-panel{
  z-index:100000!important;
  background:var(--s360-card)!important;
  color:var(--s360-text)!important;
  border:1px solid var(--s360-line)!important;
  box-shadow:0 22px 60px rgba(10,20,40,.20)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-panel{
  background:#172236!important;
  color:#f1f5fb!important;
  border-color:#334258!important;
  box-shadow:0 24px 65px rgba(0,0,0,.42)!important;
}
body.s360-content-theme .adm-theme-title{
  color:var(--s360-text)!important;
}
body.s360-content-theme .adm-theme-sub{
  color:var(--s360-muted)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-title{
  color:#f1f5fb!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-sub{
  color:#a8b4c6!important;
}
body.s360-content-theme .adm-theme-grid{
  gap:8px!important;
}
body.s360-content-theme .adm-theme-option{
  position:relative!important;
  min-height:74px!important;
  padding:9px!important;
  background:var(--s360-card)!important;
  color:var(--s360-text)!important;
  border:1px solid var(--s360-line)!important;
  border-radius:13px!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-option{
  background:#172236!important;
  color:#f1f5fb!important;
  border-color:#34435a!important;
}
body.s360-content-theme .adm-theme-option:hover{
  border-color:color-mix(in srgb,var(--s360-a) 55%,var(--s360-line))!important;
  background:var(--s360-soft)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-option:hover{
  background:#202c41!important;
}
body.s360-content-theme .adm-theme-option.active{
  border-color:var(--s360-a)!important;
  box-shadow:0 0 0 2px color-mix(in srgb,var(--s360-a) 16%,transparent)!important;
}
body.s360-content-theme .adm-swatch{
  display:block!important;
  width:100%!important;
  height:26px!important;
  min-height:26px!important;
  border-radius:8px!important;
  margin:0 0 8px!important;
  opacity:1!important;
  visibility:visible!important;
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.20),0 2px 7px rgba(0,0,0,.10)!important;
}
body.s360-content-theme .adm-theme-option[data-theme="blue"] .adm-swatch{
  background:linear-gradient(135deg,#2563eb,#4f46e5)!important;
}
body.s360-content-theme .adm-theme-option[data-theme="orange"] .adm-swatch{
  background:linear-gradient(135deg,#f97316,#ea580c)!important;
}
body.s360-content-theme .adm-theme-option[data-theme="purple"] .adm-swatch{
  background:linear-gradient(135deg,#8b5cf6,#7c3aed)!important;
}
body.s360-content-theme .adm-theme-option[data-theme="green"] .adm-swatch{
  background:linear-gradient(135deg,#10b981,#059669)!important;
}
body.s360-content-theme .adm-theme-option strong{
  display:block!important;
  color:var(--s360-text)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-option strong{
  color:#f1f5fb!important;
}
body.s360-content-theme .adm-theme-option small{
  display:block!important;
  color:var(--s360-muted)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-option small{
  color:#9eacbf!important;
}
body.s360-content-theme .adm-mode-btn{
  background:var(--s360-card)!important;
  color:var(--s360-text)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .adm-mode-btn{
  background:#202c41!important;
  color:#f1f5fb!important;
  border-color:#3b4a61!important;
}

/* Barra superior */
body.s360-content-theme .s360-topbar{
  background:rgba(255,255,255,.94)!important;
  border-color:var(--s360-line)!important;
}
body.s360-content-theme.s360-content-dark .s360-topbar{
  background:rgba(11,18,32,.96)!important;
  border-color:#26354a!important;
}
body.s360-content-theme .s360-brand{
  color:var(--s360-text)!important;
}
body.s360-content-theme.s360-content-dark .s360-brand{
  color:#f1f5fb!important;
}
</style>

</head><body class="s360-admin s360-content-theme"><nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border" href="<?=e($urlMaterias)?>"><i class="bi bi-book me-1"></i>Materias</a><a class="btn btn-outline-secondary" href="<?=e($urlSalir)?>">Salir</a></div></div></nav><main class="s360-shell"><section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Estructura académica</div><h1 class="h2 mb-2">Unidades temáticas</h1><p class="mb-0 opacity-75">Cada unidad pertenece a una materia y a un grado específico.</p></div><div class="col-auto"><a class="btn btn-light s360-btn" href="<?=e($urlNuevo)?>"><i class="bi bi-plus-lg me-1"></i>Nueva unidad</a></div></div></section><?php foreach($mensajes as $m):?><div class="alert alert-success border-0 shadow-sm"><?=e($m)?></div><?php endforeach;?><?php foreach($errores as $er):?><div class="alert alert-danger border-0 shadow-sm"><?=e($er)?></div><?php endforeach;?><section class="filterbar mb-4"><form id="filtroUnidades" method="get" class="row g-3 align-items-end"><div class="col-12 col-md-5"><label class="form-label small fw-bold">Materia</label><select class="form-select" name="id_materia" id="filtroMateriaUnidad"><option value="">Todas las materias</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=((int)$idMateria===(int)$m['id_materia'])?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div><div class="col-12 col-md-4"><label class="form-label small fw-bold">Grado</label><div class="d-flex gap-2 flex-wrap"><?php foreach([''=>'Todos','9'=>'9°','10'=>'10°','11'=>'11°'] as $g=>$label):?><a class="chip <?=$grado===$g?'active':''?>" href="?<?=http_build_query(array_filter(['id_materia'=>$idMateria,'grado'=>$g],fn($v)=>$v!==null&&$v!==''))?>"><?=$label?></a><?php endforeach;?></div></div></form></section><div class="row g-3"><?php if(!$unidades):?><div class="col-12"><div class="s360-empty text-center p-5"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-collection"></i></div><h2 class="h5 fw-bold">No hay unidades temáticas</h2><p class="s360-muted mb-0">Crea una unidad para comenzar a organizar los temas.</p></div></div><?php endif;?><?php foreach($unidades as $u):?><div class="col-12 col-md-6 col-xl-4"><article class="unit-card"><div class="d-flex gap-3 align-items-start mb-3"><div class="unit-icon"><i class="bi bi-collection-fill"></i></div><div class="min-w-0"><div class="d-flex gap-2 flex-wrap"><span class="badge rounded-pill text-bg-primary"><?=e($u['grado'])?>°</span><?php if((int)$u['es_predeterminada']===1):?><span class="badge rounded-pill text-bg-secondary">Transición</span><?php endif;?></div><h2 class="h5 fw-bold mt-2 mb-1"><?=e($u['nombre'])?></h2><div class="small text-secondary"><?=e($u['materia'])?></div></div></div><p class="small text-secondary mb-3" style="line-height:1.55"><?=e($u['descripcion']?:'Sin descripción.')?></p><div class="mb-3"><span class="badge rounded-pill bg-light text-dark border"><?=((int)$u['total_temas'])?> <?=((int)$u['total_temas']===1?'tema':'temas')?></span></div><div class="unit-actions"><a class="btn btn-primary flex-grow-1" href="temas.php?id_materia=<?=(int)$u['id_materia']?>&id_unidad=<?=(int)$u['id_unidad']?>&grado=<?=e($u['grado'])?>"><i class="bi bi-journal-text me-1"></i>Ver temas</a><a class="btn btn-outline-secondary" href="editar_unidad.php?id=<?=(int)$u['id_unidad']?>" title="Editar unidad"><i class="bi bi-pencil"></i></a><?php if((int)$u['es_predeterminada']===0):?><form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta unidad temática?');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_unidad" value="<?=$u['id_unidad']?>"><button class="btn btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash3"></i></button></form><?php endif;?></div></article></div><?php endforeach;?></div></main><script>(function(){var f=document.getElementById('filtroUnidades');var m=document.getElementById('filtroMateriaUnidad');if(m)m.addEventListener('change',function(){f.submit();});})();</script><button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button><div id="admThemePanel" class="adm-theme-panel"><div class="adm-theme-title">Personaliza Studia360</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro.</div><div class="adm-theme-grid"><button class="adm-theme-option" type="button" data-theme="blue"><span class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></span><strong>Azul</strong></button><button class="adm-theme-option" type="button" data-theme="orange"><span class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></span><strong>Naranja</strong></button><button class="adm-theme-option" type="button" data-theme="purple"><span class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></span><strong>Violeta</strong></button><button class="adm-theme-option" type="button" data-theme="green"><span class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></span><strong>Verde</strong></button></div><button id="admModeBtn" class="adm-mode-btn" type="button"></button></div><script>(function(){var b=document.body,k='studia360_theme',t={blue:'',orange:'s360-accent-orange',purple:'s360-accent-purple',green:'s360-accent-green'},st={theme:'blue',mode:'light'};try{var x=JSON.parse(localStorage.getItem(k)||'{}');if(t[x.theme]!==undefined)st.theme=x.theme;if(x.mode==='dark'||x.mode==='light')st.mode=x.mode;}catch(e){}function a(){Object.values(t).forEach(function(c){if(c)b.classList.remove(c)});b.classList.add('s360-content-theme');if(t[st.theme])b.classList.add(t[st.theme]);b.classList.toggle('s360-content-dark',st.mode==='dark');document.querySelectorAll('.adm-theme-option').forEach(function(o){o.classList.toggle('active',o.dataset.theme===st.theme)});var m=document.getElementById('admModeBtn');if(m)m.innerHTML=st.mode==='dark'?'<i class="bi bi-sun me-2"></i>Modo claro':'<i class="bi bi-moon-stars me-2"></i>Modo oscuro'}function save(){try{localStorage.setItem(k,JSON.stringify(st))}catch(e){}}a();document.getElementById('admThemeToggle').onclick=function(e){e.stopPropagation();document.getElementById('admThemePanel').classList.toggle('open')};document.querySelectorAll('.adm-theme-option').forEach(function(o){o.onclick=function(){st.theme=o.dataset.theme;save();a()}});document.getElementById('admModeBtn').onclick=function(){st.mode=st.mode==='dark'?'light':'dark';save();a()};})();</script></body></html>

<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$idTema=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$idTema||$idTema<=0){header('Location: temas.php');exit;}
if(empty($_SESSION['csrf_informacion_tema']))$_SESSION['csrf_informacion_tema']=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf_informacion_tema'];
$errores=[];$mensajes=[];$tema=null;$materias=[];
try{
 $st=$conexion->prepare("SELECT t.id_tema,t.id_materia,t.nombre,t.descripcion,t.grado,m.nombre materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=? LIMIT 1");$st->execute([$idTema]);$tema=$st->fetch(PDO::FETCH_ASSOC);
 if(!$tema){header('Location: temas.php');exit;}
 $materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $e){$errores[]='No fue posible cargar la información del tema.';}
if($_SERVER['REQUEST_METHOD']==='POST'&&$tema){
 if(!hash_equals($csrf,(string)($_POST['csrf']??'')))$errores[]='La sesión del formulario expiró. Recarga la página.';
 $nombre=trim((string)($_POST['nombre']??''));$descripcion=trim((string)($_POST['descripcion']??''));$grado=(string)($_POST['grado']??'');$idMateria=(int)($_POST['id_materia']??0);
 if($nombre==='')$errores[]='El nombre del tema es obligatorio.';elseif(mb_strlen($nombre)>150)$errores[]='El nombre no puede superar 150 caracteres.';
 if(mb_strlen($descripcion)>5000)$errores[]='La descripción es demasiado larga.';
 if(!in_array($grado,['9','10','11'],true))$errores[]='El grado seleccionado no es válido.';
 if($idMateria<=0)$errores[]='Selecciona una materia.';
 if(!$errores)try{
  $st=$conexion->prepare("SELECT COUNT(*) FROM materias WHERE id_materia=?");$st->execute([$idMateria]);if(!(int)$st->fetchColumn())$errores[]='La materia seleccionada no existe.';
  $st=$conexion->prepare("SELECT COUNT(*) FROM temas WHERE id_materia=? AND grado=? AND LOWER(nombre)=LOWER(?) AND id_tema<>?");$st->execute([$idMateria,$grado,$nombre,$idTema]);if((int)$st->fetchColumn())$errores[]='Ya existe otro tema con ese nombre en la misma materia y grado.';
  if(!$errores){$st=$conexion->prepare("UPDATE temas SET id_materia=?,nombre=?,descripcion=?,grado=? WHERE id_tema=?");$st->execute([$idMateria,$nombre,$descripcion!==''?$descripcion:null,$grado,$idTema]);$st=$conexion->prepare("SELECT t.id_tema,t.id_materia,t.nombre,t.descripcion,t.grado,m.nombre materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=?");$st->execute([$idTema]);$tema=$st->fetch(PDO::FETCH_ASSOC);$mensajes[]='Los datos del tema se actualizaron correctamente.';}
 }catch(PDOException $e){$errores[]='No fue posible actualizar el tema.';}
}
try{
 $st=$conexion->prepare("SELECT COUNT(*) FROM recursos WHERE id_tema=? AND estado='Activo'");$st->execute([$idTema]);$recursos=(int)$st->fetchColumn();
 $st=$conexion->prepare("SELECT COUNT(*) FROM progreso WHERE id_tema=?");$st->execute([$idTema]);$seguimiento=(int)$st->fetchColumn();
 $st=$conexion->prepare("SELECT estado,fecha_actualizacion FROM contenido_temas WHERE id_tema=? ORDER BY fecha_actualizacion DESC,id_contenido DESC LIMIT 1");$st->execute([$idTema]);$contenido=$st->fetch(PDO::FETCH_ASSOC)?:null;
}catch(PDOException $e){$recursos=0;$seguimiento=0;$contenido=null;}
$urlTemas=urlAplicacion('/admin/contenidos/temas.php');$urlEditor=urlAplicacion('/admin/contenidos/editar_tema.php?id='.$idTema);$urlPreview=urlAplicacion('/admin/contenidos/vista_previa_tema.php?id='.$idTema);$urlDashboard=urlAplicacion('/admin/dashboard.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Editar tema | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>.info-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:18px}.field-label{font-size:.78rem;font-weight:800;color:#526176;margin-bottom:6px}.stat-mini{padding:14px;border:1px solid #e4eaf2;border-radius:16px;background:#f8fafc}.stat-mini strong{font-size:1.3rem}@media(max-width:900px){.info-layout{grid-template-columns:1fr}}</style><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">

<style id="studia360-contenidos-polished">
:root{
  --c-accent:#2563eb;
  --c-accent2:#4f46e5;
  --c-soft:#eff6ff;
  --c-bg:#f6f8fc;
  --c-card:#fff;
  --c-text:#1f2937;
  --c-muted:#748196;
  --c-line:#e5eaf1;
  --c-input:#fff;
  --c-track:#e9eef5;
}
body.s360-content-theme{
  --c-accent:#2563eb;--c-accent2:#4f46e5;--c-soft:#eff6ff;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 7%,transparent),transparent 25rem),var(--c-bg)!important;
  color:var(--c-text)!important;
  transition:background .25s ease,color .25s ease;
}
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}

body.s360-content-theme .adm-theme-toggle{
  position:fixed!important;right:20px!important;bottom:20px!important;z-index:2000!important;
  width:50px!important;height:50px!important;padding:0!important;margin:0!important;
  display:grid!important;place-items:center!important;
  border:0!important;border-radius:16px!important;
  color:#fff!important;background:linear-gradient(145deg,var(--c-accent),var(--c-accent2))!important;
  box-shadow:0 12px 30px color-mix(in srgb,var(--c-accent) 28%,transparent)!important;
  cursor:pointer!important;outline:none!important;transition:transform .2s ease,box-shadow .2s ease!important;
}
body.s360-content-theme .adm-theme-toggle:hover{transform:translateY(-3px)!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.15rem!important;line-height:1!important}

body.s360-content-theme .adm-theme-panel{
  position:fixed!important;right:20px!important;bottom:82px!important;z-index:1999!important;
  width:290px!important;max-width:calc(100vw - 28px)!important;
  padding:16px!important;margin:0!important;
  display:block!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;border-radius:18px!important;
  box-shadow:0 20px 55px rgba(20,35,60,.16)!important;
  opacity:0!important;visibility:hidden!important;pointer-events:none!important;
  transform:translateY(8px) scale(.98)!important;
  transition:opacity .18s ease,transform .18s ease,visibility .18s ease!important;
}
body.s360-content-theme .adm-theme-panel.open{
  opacity:1!important;visibility:visible!important;pointer-events:auto!important;
  transform:none!important;
}
body.s360-content-theme .adm-theme-title{
  margin:0 0 4px!important;font-size:.84rem!important;line-height:1.25!important;
  font-weight:850!important;color:var(--c-text)!important;
}
body.s360-content-theme .adm-theme-sub{
  margin:0 0 13px!important;font-size:.66rem!important;line-height:1.45!important;
  color:var(--c-muted)!important;
}
body.s360-content-theme .adm-theme-grid{
  display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;
}
body.s360-content-theme .adm-theme-option{
  appearance:none!important;width:100%!important;min-height:74px!important;
  padding:9px!important;margin:0!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;box-shadow:none!important;
  font:inherit!important;transition:.18s ease!important;
}
body.s360-content-theme .adm-theme-option:hover,
body.s360-content-theme .adm-theme-option.active{
  border-color:color-mix(in srgb,var(--c-accent) 42%,var(--c-line))!important;
  background:var(--c-soft)!important;transform:translateY(-1px)!important;
}
body.s360-content-theme .adm-swatch{
  display:block!important;width:100%!important;height:23px!important;margin:0 0 7px!important;
  border-radius:8px!important;
}
body.s360-content-theme .adm-theme-option strong{
  display:block!important;font-size:.67rem!important;line-height:1.15!important;
}
body.s360-content-theme .adm-theme-option small{
  display:block!important;margin-top:3px!important;font-size:.57rem!important;
  line-height:1.15!important;color:var(--c-muted)!important;
}
body.s360-content-theme .adm-mode-btn{
  appearance:none!important;width:100%!important;min-height:38px!important;
  margin:9px 0 0!important;padding:8px 10px!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;font:700 .68rem/1.2 system-ui,sans-serif!important;
}
body.s360-content-theme .adm-mode-btn:hover{
  color:var(--c-accent)!important;background:var(--c-soft)!important;
}

/* Capa visual de las pantallas existentes; no cambia su estructura ni sus datos. */
body.s360-content-theme .card,
body.s360-content-theme .card-soft,
body.s360-content-theme .editor-card,
body.s360-content-theme .info-card,
body.s360-content-theme .content-card,
body.s360-content-theme .preview-header,
body.s360-content-theme .resource,
body.s360-content-theme .resource-card,
body.s360-content-theme .recurso-card,
body.s360-content-theme .sidebar-card,
body.s360-content-theme .panel,
body.s360-content-theme .stat,
body.s360-content-theme .theme-info,
body.s360-content-theme .delete-card{
  background:var(--c-card)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,
body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{
  color:var(--c-text);
}
body.s360-content-theme .text-muted,body.s360-content-theme .muted,
body.s360-content-theme .url-help{color:var(--c-muted)!important}
body.s360-content-theme .text-primary{color:var(--c-accent)!important}
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .btn-primary{
  background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .btn-outline-primary{
  color:var(--c-accent)!important;border-color:color-mix(in srgb,var(--c-accent) 30%,var(--c-line))!important;
  background:var(--c-card)!important;
}
body.s360-content-theme .btn-outline-primary:hover{
  color:#fff!important;background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .badge-type,
body.s360-content-theme .icon,
body.s360-content-theme .resource-icon,
body.s360-content-theme .icono-recurso{
  background:var(--c-soft)!important;color:var(--c-accent)!important;
}
body.s360-content-theme .hero,
body.s360-content-theme .preview-hero{
  background:linear-gradient(125deg,var(--c-accent),var(--c-accent2))!important;
}
body.s360-content-theme .table{
  --bs-table-bg:var(--c-card);--bs-table-color:var(--c-text);--bs-table-border-color:var(--c-line);
}
body.s360-content-theme .table thead th{background:var(--c-soft)!important;color:var(--c-text)!important}
body.s360-content-theme .dropdown-menu{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme .dropdown-item{color:var(--c-text)!important}
body.s360-content-theme .dropdown-item:hover{background:var(--c-soft)!important;color:var(--c-accent)!important}

/* Acciones compactas: solo icono, sin deformar los botones. */
body.s360-content-theme .topic-actions .btn,
body.s360-content-theme .recent-row .btn,
body.s360-content-theme .resource .btn.btn-sm,
body.s360-content-theme .resource-card .btn.btn-sm,
body.s360-content-theme .recurso-card .btn.btn-sm{
  width:36px!important;height:36px!important;min-width:36px!important;min-height:36px!important;
  padding:0!important;display:inline-grid!important;place-items:center!important;
  border-radius:10px!important;font-size:0!important;line-height:1!important;
}
body.s360-content-theme .topic-actions .btn i,
body.s360-content-theme .recent-row .btn i,
body.s360-content-theme .resource .btn.btn-sm i,
body.s360-content-theme .resource-card .btn.btn-sm i,
body.s360-content-theme .recurso-card .btn.btn-sm i{
  margin:0!important;font-size:.9rem!important;line-height:1!important;
}

/* Oscuro */
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;--c-card:#172236;--c-text:#edf2f8;--c-muted:#9ba8ba;
  --c-line:#2b374b;--c-input:#111827;--c-track:#263247;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 13%,transparent),transparent 25rem),var(--c-bg)!important;
}
body.s360-content-theme.s360-content-dark .navbar{
  background:rgba(13,20,36,.94)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .navbar-brand,
body.s360-content-theme.s360-content-dark .text-dark,
body.s360-content-theme.s360-content-dark h1,
body.s360-content-theme.s360-content-dark h2,
body.s360-content-theme.s360-content-dark h3,
body.s360-content-theme.s360-content-dark h4,
body.s360-content-theme.s360-content-dark h5,
body.s360-content-theme.s360-content-dark h6{color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .modal-content{
  background:var(--c-card)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .note-toolbar{background:#202c41!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-editable{background:var(--c-input)!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .note-statusbar{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-btn{
  background:#27344a!important;color:#dce4ee!important;border-color:#354158!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-panel{box-shadow:0 22px 60px rgba(0,0,0,.42)!important}

@media(max-width:767px){
  body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}
  body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}
}
</style>


<style id="studia360-info-dark-fix">
/* Solo corrige superficies y contraste; conserva la estructura original. */
body.s360-content-theme{
  --c-bg:#f6f8fc;--c-card:#fff;--c-text:#1f2937;--c-muted:#748196;--c-line:#e5eaf1;--c-input:#fff;
  background:var(--c-bg)!important;color:var(--c-text)!important;
}
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;--c-card:#172236;--c-text:#edf2f8;--c-muted:#9ba8ba;--c-line:#2b374b;--c-input:#111827;
  background:var(--c-bg)!important;color:var(--c-text)!important;
}
/* Las tarjetas que se ven blancas en la captura */
body.s360-content-theme .s360-card,
body.s360-content-theme .stat-mini{
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .field-label,
body.s360-content-theme .s360-section-title,
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,
body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{
  color:var(--c-text)!important;
}
body.s360-content-theme .s360-muted,
body.s360-content-theme .text-muted{
  color:var(--c-muted)!important;
}
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea{
  background:#111827!important;color:#edf2f8!important;border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .btn-light{
  background:#172236!important;color:#edf2f8!important;border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .alert-success{
  background:#123c32!important;color:#9ff0cf!important;border-color:#1d6b56!important;
}
body.s360-content-theme.s360-content-dark .alert-danger{
  background:#451d26!important;color:#ffb4bd!important;border-color:#7a303e!important;
}
/* Personalizador: el script existente ya controla la apertura/selección. */
body.s360-content-theme .adm-theme-panel{
  background:var(--c-card)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-option,
body.s360-content-theme.s360-content-dark .adm-mode-btn{
  background:#111a2b!important;color:#edf2f8!important;border-color:#334158!important;
}
</style>


<style id="studia360-final-surface-fix">
/* Corrección final de superficies: no altera PHP, formularios ni estructura. */
body.s360-content-theme.s360-content-dark{
  background:var(--c-bg)!important;
  color:var(--c-text)!important;
}

/* Barra superior y controles que seguían usando el estilo claro original. */
body.s360-content-theme .s360-topbar{
  background:var(--c-card)!important;
  border-bottom:1px solid var(--c-line)!important;
  color:var(--c-text)!important;
}
body.s360-content-theme.s360-content-dark .s360-topbar{
  background:#0d1424!important;
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .s360-topbar .btn-light,
body.s360-content-theme.s360-content-dark .btn-light{
  background:#172236!important;
  color:#edf2f8!important;
  border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .s360-brand{
  color:#edf2f8!important;
}

/* Tarjetas principales y resumen. */
body.s360-content-theme.s360-content-dark .s360-card,
body.s360-content-theme.s360-content-dark .stat-mini{
  background:#172236!important;
  color:#edf2f8!important;
  border:1px solid #2b374b!important;
  box-shadow:0 10px 30px rgba(0,0,0,.16)!important;
}
body.s360-content-theme.s360-content-dark .stat-mini strong,
body.s360-content-theme.s360-content-dark .stat-mini .fw-bold{
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .field-label{
  color:#dce5f0!important;
}

/* Select nativo: evita que vuelva a aparecer blanco al abrir/mostrar opciones. */
body.s360-content-theme.s360-content-dark select,
body.s360-content-theme.s360-content-dark select option{
  background:#111827!important;
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark select option:checked{
  background:#27344a!important;
}

/* Alertas y textos secundarios. */
body.s360-content-theme.s360-content-dark .alert{
  border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .alert-success{
  background:#123c32!important;color:#a7f3d0!important;
}
body.s360-content-theme.s360-content-dark .alert-danger{
  background:#451d26!important;color:#fecdd3!important;
}

/* Evita que elementos Bootstrap con texto oscuro queden ilegibles. */
body.s360-content-theme.s360-content-dark .text-dark{
  color:#edf2f8!important;
}
</style>

</head><body class="s360-admin">
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlTemas)?>"><i class="bi bi-arrow-left me-1"></i>Temas</a></div></div></nav>
<main class="s360-shell"><section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Editar estructura</div><h1 class="h2 mb-2"><?=e($tema['nombre'])?></h1><p class="mb-0 opacity-75"><?=e($tema['materia'])?> · <?=e($tema['grado'])?>°</p></div><div class="col-auto d-flex gap-2 flex-wrap"><a class="btn btn-light s360-btn" href="<?=e($urlPreview)?>" target="_blank"><i class="bi bi-eye me-1"></i>Vista previa</a><a class="btn btn-outline-light s360-btn" href="<?=e($urlEditor)?>"><i class="bi bi-file-earmark-text me-1"></i>Editar contenido</a></div></div></section>
<?php foreach($mensajes as $m):?><div class="alert alert-success border-0"><?=e($m)?></div><?php endforeach;?><?php foreach($errores as $m):?><div class="alert alert-danger border-0"><?=e($m)?></div><?php endforeach;?>
<div class="info-layout"><section class="s360-card p-4 p-lg-5"><div class="s360-section-title mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Información del tema</div><p class="s360-muted small mb-4">Aquí se modifica lo que identifica al tema: materia, nombre, grado y descripción.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>">
<div class="mb-4"><label class="field-label">Materia</label><select class="form-select" name="id_materia" required><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=$tema['id_materia']==$m['id_materia']?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div>
<div class="mb-4"><label class="field-label">Nombre del tema</label><input class="form-control" name="nombre" maxlength="150" value="<?=e($tema['nombre'])?>" required></div>
<div class="mb-4"><label class="field-label">Grado</label><select class="form-select" name="grado" required><option value="9" <?=$tema['grado']==='9'?'selected':''?>>9° · Noveno</option><option value="10" <?=$tema['grado']==='10'?'selected':''?>>10° · Décimo</option><option value="11" <?=$tema['grado']==='11'?'selected':''?>>11° · Undécimo</option></select></div>
<div class="mb-4"><label class="field-label">Descripción</label><textarea class="form-control" name="descripcion" rows="6" maxlength="5000" placeholder="Describe qué aprenderá el estudiante en este tema."><?=e($tema['descripcion'])?></textarea></div>
<div class="d-flex justify-content-between gap-2 flex-wrap"><a class="btn btn-light border s360-btn" href="<?=e($urlTemas)?>">Cancelar</a><button class="btn btn-primary s360-btn" type="submit"><i class="bi bi-check2 me-1"></i>Guardar cambios</button></div>
</form></section>
<aside class="s360-card p-4"><div class="s360-section-title mb-3">Resumen</div><div class="stat-mini mb-2"><small class="s360-muted">Materia</small><div class="fw-bold"><?=e($tema['materia'])?></div></div><div class="stat-mini mb-2"><small class="s360-muted">Recursos activos</small><div><strong><?=$recursos?></strong></div></div><div class="stat-mini mb-2"><small class="s360-muted">Estudiantes con registro de progreso</small><div><strong><?=$seguimiento?></strong></div></div><div class="stat-mini"><small class="s360-muted">Contenido</small><div class="fw-bold"><?=e($contenido['estado']??'Sin contenido')?></div><?php if(!empty($contenido['fecha_actualizacion'])):?><small class="s360-muted"><?=e(date('d/m/Y H:i',strtotime($contenido['fecha_actualizacion'])))?></small><?php endif;?></div></aside></div></main><!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button></div>

<script id="studia360-contenidos-theme-controller">
(function(){
  'use strict';
  var body=document.body;
  var key='studia360_theme';
  var toggle=document.getElementById('admThemeToggle');
  var panel=document.getElementById('admThemePanel');
  var modeBtn=document.getElementById('admModeBtn');
  var options=document.querySelectorAll('.adm-theme-option');

  var themes={
    blue:{cls:'',label:'Azul'},
    purple:{cls:'s360-accent-purple',label:'Violeta'},
    orange:{cls:'s360-accent-orange',label:'Naranja'},
    green:{cls:'s360-accent-green',label:'Verde'}
  };

  var saved={theme:'blue',mode:'light'};
  try{
    var raw=localStorage.getItem(key);
    if(raw){
      var parsed=JSON.parse(raw);
      if(parsed && typeof parsed==='object') saved=Object.assign(saved,parsed);
    }
  }catch(e){}
  if(!themes[saved.theme]) saved.theme='blue';
  if(saved.mode!=='dark' && saved.mode!=='light') saved.mode='light';

  function persist(){
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
  }

  function apply(){
    body.classList.add('s360-content-theme');
    Object.keys(themes).forEach(function(name){
      if(themes[name].cls) body.classList.remove(themes[name].cls);
    });
    if(themes[saved.theme].cls) body.classList.add(themes[saved.theme].cls);
    body.classList.toggle('s360-content-dark',saved.mode==='dark');

    options.forEach(function(option){
      option.classList.toggle('active',option.getAttribute('data-theme')===saved.theme);
    });

    if(modeBtn){
      modeBtn.innerHTML=saved.mode==='dark'
        ? '<i class="bi bi-moon-stars me-2"></i>Modo oscuro'
        : '<i class="bi bi-sun me-2"></i>Modo claro';
    }
  }

  window.admSetTheme=function(theme){
    if(!themes[theme]) return;
    saved.theme=theme;
    persist();
    apply();
  };

  window.admToggleMode=function(){
    saved.mode=saved.mode==='dark'?'light':'dark';
    persist();
    apply();
  };

  if(toggle && panel){
    toggle.addEventListener('click',function(event){
      event.preventDefault();
      event.stopPropagation();
      panel.classList.toggle('open');
    });
    panel.addEventListener('click',function(event){
      event.stopPropagation();
    });
    document.addEventListener('click',function(){
      panel.classList.remove('open');
    });
  }

  apply();
})();
</script>

</body></html>

<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$errores=[];$nombreAdmin=trim((string)($_SESSION['nombres']??'')) ?: 'Administrador';
$idMateria=filter_var($_GET['id_materia']??null,FILTER_VALIDATE_INT); if(!$idMateria||$idMateria<=0)$idMateria=null;
$gradoFiltro=(string)($_GET['grado']??''); if(!in_array($gradoFiltro,['9','10','11'],true))$gradoFiltro='';
$idUnidad=filter_var($_GET['id_unidad']??null,FILTER_VALIDATE_INT); if(!$idUnidad||$idUnidad<=0)$idUnidad=null;
$unidadSeleccionada=null;
$busqueda=trim((string)($_GET['q']??''));
$materias=[];$materiaSeleccionada=null;$temas=[];
try{$materias=$conexion->query("SELECT id_materia,nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
if($idMateria){$st=$conexion->prepare("SELECT id_materia,nombre,descripcion FROM materias WHERE id_materia=?");$st->execute([$idMateria]);$materiaSeleccionada=$st->fetch(PDO::FETCH_ASSOC);if(!$materiaSeleccionada){$idMateria=null;$errores[]='La materia seleccionada no existe.';}}
if($idUnidad){$st=$conexion->prepare("SELECT u.id_unidad,u.id_materia,u.grado,u.nombre,u.es_predeterminada,m.nombre materia FROM unidades_tematicas u INNER JOIN materias m ON m.id_materia=u.id_materia WHERE u.id_unidad=? AND u.estado='Activa'");$st->execute([$idUnidad]);$unidadSeleccionada=$st->fetch(PDO::FETCH_ASSOC);if(!$unidadSeleccionada){$idUnidad=null;$errores[]='La unidad temática seleccionada no existe.';}else{$idMateria=(int)$unidadSeleccionada['id_materia'];$gradoFiltro=(string)$unidadSeleccionada['grado'];}}
$sql="SELECT t.id_tema,t.id_materia,t.id_unidad,t.nombre AS tema,t.descripcion,t.grado,COALESCE(t.contenido,'') AS contenido,m.nombre AS materia,u.nombre AS unidad,
(SELECT COUNT(*) FROM recursos r WHERE r.id_tema=t.id_tema AND r.estado='Activo') total_recursos,
(SELECT ct.estado FROM contenido_temas ct WHERE ct.id_tema=t.id_tema ORDER BY ct.fecha_actualizacion DESC,ct.id_contenido DESC LIMIT 1) estado_contenido
FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia LEFT JOIN unidades_tematicas u ON u.id_unidad=t.id_unidad WHERE 1=1";$params=[];
if($idMateria){$sql.=" AND t.id_materia=?";$params[]=$idMateria;} if($idUnidad){$sql.=" AND t.id_unidad=?";$params[]=$idUnidad;} if($gradoFiltro){$sql.=" AND t.grado=?";$params[]=$gradoFiltro;} if($busqueda!==''){$sql.=" AND (t.nombre LIKE ? OR t.descripcion LIKE ? OR m.nombre LIKE ?)";$q="%$busqueda%";array_push($params,$q,$q,$q);}
$sql.=" ORDER BY CAST(t.grado AS UNSIGNED),m.nombre,u.nombre,t.nombre";$st=$conexion->prepare($sql);$st->execute($params);$temas=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $ex){$errores[]='No fue posible cargar la información académica.';}
$total=count($temas);$porGrado=['9'=>0,'10'=>0,'11'=>0];foreach($temas as $t)$porGrado[(string)$t['grado']]++;
$urlDashboard=urlAplicacion('/admin/dashboard.php');$urlMaterias=urlAplicacion('/admin/contenidos/materias.php');$urlUnidades=urlAplicacion('/admin/contenidos/unidades.php'.($idMateria?'?id_materia='.$idMateria.($gradoFiltro?'&grado='.$gradoFiltro:''):''));$urlNuevo=urlAplicacion('/admin/contenidos/nuevo_tema.php'.($idUnidad?'?id_unidad='.$idUnidad.'&id_materia='.$idMateria.'&grado='.$gradoFiltro:($idMateria?'?id_materia='.$idMateria.'&grado='.$gradoFiltro:'')));$urlBase=urlAplicacion('/admin/contenidos/temas.php');$urlSalir=urlAplicacion('/cerrar_sesion.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temas | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.topic-card{background:#fff;border:1px solid #e4eaf2;border-radius:19px;padding:18px;box-shadow:0 8px 24px rgba(23,32,51,.045);height:100%;display:flex;flex-direction:column;transition:.18s}.topic-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(23,32,51,.08)}
.topic-icon{width:43px;height:43px;border-radius:13px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center}.grade-pill{font-size:.7rem;font-weight:800;color:#2563eb;background:#eff6ff;border-radius:999px;padding:.3rem .55rem}.topic-desc{color:#64748b;font-size:.87rem;line-height:1.55}.topic-actions{margin-top:auto;display:flex;gap:7px;flex-wrap:wrap}.topic-actions .btn{border-radius:10px;font-size:.78rem;font-weight:700}.filterbar{background:#fff;border:1px solid #e4eaf2;border-radius:20px;padding:16px;box-shadow:0 8px 24px rgba(23,32,51,.04)}.filter-chip{border:1px solid #dbe3ee;background:#fff;color:#526176;border-radius:11px;padding:.55rem .8rem;font-weight:750;font-size:.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}.filter-chip:hover{border-color:#9bb8e7}.filter-chip.active{background:#2563eb;color:#fff;border-color:#2563eb}.subject-select{max-width:330px}
</style><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">




<style id="studia360-content-final-style">
:root{--s360a:#2563eb;--s360a2:#4f46e5;--s360soft:#eff6ff;--s360bg:#f6f8fc;--s360card:#fff;--s360text:#1f2937;--s360muted:#748196;--s360line:#e5eaf1;--s360input:#fff}
body.s360-content-theme{margin:0!important;min-height:100vh;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360a) 7%,transparent),transparent 25rem),var(--s360bg)!important;color:var(--s360text)!important;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif!important;transition:background .25s,color .25s}
body.s360-content-theme.s360-accent-orange{--s360a:#f97316;--s360a2:#ea580c;--s360soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--s360a:#8b5cf6;--s360a2:#7c3aed;--s360soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--s360a:#10b981;--s360a2:#059669;--s360soft:#ecfdf5}
body.s360-content-theme .s360-topbar{background:rgba(255,255,255,.92)!important;border-bottom:1px solid var(--s360line)!important;box-shadow:0 5px 24px rgba(20,35,60,.045)!important;backdrop-filter:blur(16px);position:sticky;top:0;z-index:1000}
body.s360-content-theme .s360-brand{color:var(--s360text)!important;text-decoration:none;font-weight:850!important;letter-spacing:-.035em}
body.s360-content-theme .s360-brand-icon{width:38px!important;height:38px!important;border-radius:12px!important;display:grid!important;place-items:center!important;color:#fff!important;background:linear-gradient(145deg,var(--s360a),var(--s360a2))!important;box-shadow:0 8px 18px color-mix(in srgb,var(--s360a) 20%,transparent)!important}
body.s360-content-theme .s360-shell{width:min(1240px,calc(100% - 30px))!important;max-width:1240px!important;margin:0 auto!important;padding:28px 0 60px!important}
body.s360-content-theme .s360-hero{position:relative!important;overflow:hidden!important;color:#fff!important;border:0!important;border-radius:24px!important;padding:27px 29px!important;background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.15),transparent 17rem),linear-gradient(125deg,var(--s360a),var(--s360a2))!important;box-shadow:0 18px 44px color-mix(in srgb,var(--s360a) 17%,transparent)!important}
body.s360-content-theme .s360-hero h1,body.s360-content-theme .s360-hero h2,body.s360-content-theme .s360-hero h3{color:#fff!important}
body.s360-content-theme .s360-kicker{color:rgba(255,255,255,.76)!important;font-size:.65rem!important;font-weight:850!important;text-transform:uppercase!important;letter-spacing:.08em!important;margin-bottom:5px!important}
body.s360-content-theme .materia-card,body.s360-content-theme .topic-card,body.s360-content-theme .filterbar,body.s360-content-theme .s360-empty{background:var(--s360card)!important;color:var(--s360text)!important;border-color:var(--s360line)!important;box-shadow:0 10px 28px rgba(23,32,51,.055)!important}
body.s360-content-theme .materia-card:hover,body.s360-content-theme .topic-card:hover{border-color:color-mix(in srgb,var(--s360a) 20%,var(--s360line))!important;box-shadow:0 16px 36px rgba(23,32,51,.09)!important}
body.s360-content-theme .materia-icon,body.s360-content-theme .topic-icon,body.s360-content-theme .s360-iconbox{background:var(--s360soft)!important;color:var(--s360a)!important}
body.s360-content-theme .topic-pill,body.s360-content-theme .grade-pill{background:var(--s360soft)!important;color:var(--s360a)!important;border-color:color-mix(in srgb,var(--s360a) 10%,var(--s360line))!important}
body.s360-content-theme .s360-muted,body.s360-content-theme .topic-desc,body.s360-content-theme .text-secondary,body.s360-content-theme .text-muted{color:var(--s360muted)!important}
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{color:var(--s360text)}
body.s360-content-theme .form-control,body.s360-content-theme .form-select,body.s360-content-theme textarea{background:var(--s360input)!important;color:var(--s360text)!important;border:1px solid var(--s360line)!important;border-radius:11px!important}
body.s360-content-theme .form-control:focus,body.s360-content-theme .form-select:focus,body.s360-content-theme textarea:focus{border-color:color-mix(in srgb,var(--s360a) 45%,var(--s360line))!important;box-shadow:0 0 0 4px color-mix(in srgb,var(--s360a) 9%,transparent)!important}
body.s360-content-theme .btn-primary{background:var(--s360a)!important;border-color:var(--s360a)!important;color:#fff!important}
body.s360-content-theme .btn-outline-primary{color:var(--s360a)!important;border-color:color-mix(in srgb,var(--s360a) 32%,var(--s360line))!important;background:var(--s360card)!important}
body.s360-content-theme .btn-outline-primary:hover{color:#fff!important;background:var(--s360a)!important;border-color:var(--s360a)!important}
body.s360-content-theme .filter-chip.active{background:var(--s360a)!important;border-color:var(--s360a)!important;color:#fff!important}
body.s360-content-theme .filter-chip{background:var(--s360card)!important;color:var(--s360muted)!important;border-color:var(--s360line)!important}
body.s360-content-theme .table{--bs-table-bg:var(--s360card);--bs-table-color:var(--s360text);--bs-table-border-color:var(--s360line)}
body.s360-content-theme .adm-theme-toggle{position:fixed!important;right:20px!important;bottom:20px!important;z-index:2050!important;width:50px!important;height:50px!important;padding:0!important;border:0!important;border-radius:16px!important;display:grid!important;place-items:center!important;color:#fff!important;background:linear-gradient(145deg,var(--s360a),var(--s360a2))!important;box-shadow:0 12px 30px color-mix(in srgb,var(--s360a) 28%,transparent)!important;cursor:pointer!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.12rem!important;line-height:1!important}
body.s360-content-theme .adm-theme-panel{position:fixed!important;right:20px!important;bottom:82px!important;z-index:2049!important;width:290px!important;max-width:calc(100vw - 28px)!important;padding:16px!important;margin:0!important;background:var(--s360card)!important;color:var(--s360text)!important;border:1px solid var(--s360line)!important;border-radius:18px!important;box-shadow:0 20px 55px rgba(20,35,60,.16)!important;display:none!important}
body.s360-content-theme .adm-theme-panel.open{display:block!important}
body.s360-content-theme .adm-theme-title{font-size:.84rem!important;font-weight:850!important;margin:0 0 4px!important;color:var(--s360text)!important}
body.s360-content-theme .adm-theme-sub{font-size:.66rem!important;line-height:1.45!important;margin:0 0 13px!important;color:var(--s360muted)!important}
body.s360-content-theme .adm-theme-grid{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important}
body.s360-content-theme .adm-theme-option{appearance:none!important;width:100%!important;min-height:74px!important;padding:9px!important;margin:0!important;border:1px solid var(--s360line)!important;border-radius:12px!important;background:var(--s360card)!important;color:var(--s360text)!important;text-align:left!important;cursor:pointer!important;font:inherit!important;box-shadow:none!important}
body.s360-content-theme .adm-theme-option:hover,body.s360-content-theme .adm-theme-option.active{background:var(--s360soft)!important;border-color:color-mix(in srgb,var(--s360a) 42%,var(--s360line))!important}
body.s360-content-theme .adm-swatch{display:block!important;width:100%!important;height:23px!important;border-radius:8px!important;margin-bottom:7px!important}
body.s360-content-theme .adm-theme-option strong{display:block!important;font-size:.67rem!important}
body.s360-content-theme .adm-theme-option small{display:block!important;margin-top:3px!important;font-size:.57rem!important;color:var(--s360muted)!important}
body.s360-content-theme .adm-mode-btn{appearance:none!important;width:100%!important;min-height:38px!important;margin-top:9px!important;padding:8px 10px!important;border:1px solid var(--s360line)!important;border-radius:12px!important;background:var(--s360card)!important;color:var(--s360text)!important;text-align:left!important;cursor:pointer!important;font:700 .68rem/1.2 system-ui,sans-serif!important}
body.s360-content-theme .adm-mode-btn:hover{background:var(--s360soft)!important;color:var(--s360a)!important}
body.s360-content-theme .topic-actions .btn{width:36px!important;height:36px!important;min-width:36px!important;padding:0!important;display:inline-grid!important;place-items:center!important;font-size:0!important;border-radius:10px!important}
body.s360-content-theme .topic-actions .btn i{font-size:.9rem!important;margin:0!important}
body.s360-content-theme .materia-actions .btn:not(.flex-grow-1){width:36px!important;height:36px!important;padding:0!important;display:inline-grid!important;place-items:center!important;font-size:0!important}
body.s360-content-theme .materia-actions .btn:not(.flex-grow-1) i{margin:0!important;font-size:.9rem!important}
body.s360-content-theme.s360-content-dark{--s360bg:#0d1424;--s360card:#172236;--s360text:#edf2f8;--s360muted:#9ba8ba;--s360line:#2b374b;--s360input:#111827;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360a) 13%,transparent),transparent 25rem),var(--s360bg)!important}
body.s360-content-theme.s360-content-dark .s360-topbar{background:rgba(13,20,36,.94)!important;border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .btn-light{background:var(--s360card)!important;color:var(--s360text)!important;border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .btn-outline-secondary{color:#b8c2d0!important;border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .modal-content{background:var(--s360card)!important;color:var(--s360text)!important;border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .modal-header,body.s360-content-theme.s360-content-dark .modal-footer{border-color:var(--s360line)!important}
body.s360-content-theme.s360-content-dark .btn-close{filter:invert(1) grayscale(1) brightness(2)}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:var(--s360text)!important}
@media(max-width:767px){body.s360-content-theme .s360-shell{width:min(100% - 20px,1240px)!important;padding-top:20px!important}body.s360-content-theme .s360-hero{padding:22px!important;border-radius:21px!important}body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}}
</style>


<style id="studia360-final-theme-fix">
/* --- Superficies --- */
body.s360-content-theme .materia-card,
body.s360-content-theme .topic-card,
body.s360-content-theme .filterbar,
body.s360-content-theme .s360-empty {
  background: var(--s360card) !important;
  color: var(--s360text) !important;
  border-color: var(--s360line) !important;
}

body.s360-content-theme.s360-content-dark .materia-card,
body.s360-content-theme.s360-content-dark .topic-card,
body.s360-content-theme.s360-content-dark .filterbar,
body.s360-content-theme.s360-content-dark .s360-empty {
  background: #172236 !important;
  color: #edf2f8 !important;
  border-color: #2b374b !important;
}

/* --- Iconos: nunca cajas blancas en oscuro --- */
body.s360-content-theme .materia-icon,
body.s360-content-theme .topic-icon,
body.s360-content-theme .s360-iconbox {
  background: var(--s360soft) !important;
  color: var(--s360a) !important;
}
body.s360-content-theme.s360-content-dark .materia-icon,
body.s360-content-theme.s360-content-dark .topic-icon,
body.s360-content-theme.s360-content-dark .s360-iconbox {
  background: color-mix(in srgb, var(--s360a) 16%, #111827) !important;
  color: #fff !important;
}

/* --- Chips y badges de temas --- */
body.s360-content-theme .s360-chip,
body.s360-content-theme .grade-pill,
body.s360-content-theme .topic-pill,
body.s360-content-theme .filter-chip {
  background: var(--s360soft) !important;
  color: var(--s360a) !important;
  border-color: color-mix(in srgb, var(--s360a) 20%, var(--s360line)) !important;
}
body.s360-content-theme.s360-content-dark .s360-chip,
body.s360-content-theme.s360-content-dark .grade-pill,
body.s360-content-theme.s360-content-dark .topic-pill,
body.s360-content-theme.s360-content-dark .filter-chip {
  background: #202c41 !important;
  color: #dbe5f2 !important;
  border-color: #3a4860 !important;
}
body.s360-content-theme.s360-content-dark .s360-chip.success {
  background: #123c32 !important;
  color: #6ee7b7 !important;
  border-color: #1d6b56 !important;
}
body.s360-content-theme.s360-content-dark .s360-chip.warning {
  background: #493515 !important;
  color: #fbbf24 !important;
  border-color: #7a571d !important;
}

/* --- Textos que antes quedaban casi negros sobre oscuro --- */
body.s360-content-theme.s360-content-dark .materia-card h2,
body.s360-content-theme.s360-content-dark .topic-card h2,
body.s360-content-theme.s360-content-dark .materia-card p,
body.s360-content-theme.s360-content-dark .topic-desc,
body.s360-content-theme.s360-content-dark .topic-card .text-secondary,
body.s360-content-theme.s360-content-dark .filterbar label,
body.s360-content-theme.s360-content-dark .filterbar .text-secondary {
  color: #edf2f8 !important;
}
body.s360-content-theme.s360-content-dark .topic-desc,
body.s360-content-theme.s360-content-dark .filterbar .text-secondary {
  color: #9ba8ba !important;
}

/* --- Filtros e inputs --- */
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select {
  background: #111827 !important;
  color: #edf2f8 !important;
  border-color: #334158 !important;
}
body.s360-content-theme.s360-content-dark .form-control::placeholder {
  color: #7f8ca1 !important;
}

/* --- Botones claros de navegación: ya no parecen recuadros blancos --- */
body.s360-content-theme.s360-content-dark .btn-light {
  background: #172236 !important;
  color: #edf2f8 !important;
  border-color: #334158 !important;
}
body.s360-content-theme.s360-content-dark .btn-outline-secondary {
  background: transparent !important;
  color: #c4cfdd !important;
  border-color: #46536a !important;
}

/* --- Modal --- */
body.s360-content-theme.s360-content-dark .modal-content {
  background: #172236 !important;
  color: #edf2f8 !important;
  border-color: #334158 !important;
}
body.s360-content-theme.s360-content-dark .modal-header,
body.s360-content-theme.s360-content-dark .modal-footer {
  border-color: #2b374b !important;
}
body.s360-content-theme.s360-content-dark .btn-close {
  filter: invert(1) grayscale(1) brightness(2);
}

/* --- Personalizador: siempre por encima de todo --- */
body.s360-content-theme .adm-theme-toggle {
  z-index: 99999 !important;
}
body.s360-content-theme .adm-theme-panel {
  z-index: 100000 !important;
}

/* --- Acciones compactas --- */
body.s360-content-theme .topic-actions .btn:not(:first-child),
body.s360-content-theme .materia-actions .btn:not(.flex-grow-1) {
  min-width: 36px !important;
  width: 36px !important;
  height: 36px !important;
  padding: 0 !important;
  display: inline-grid !important;
  place-items: center !important;
  font-size: 0 !important;
  border-radius: 10px !important;
}
body.s360-content-theme .topic-actions .btn:not(:first-child) i,
body.s360-content-theme .materia-actions .btn:not(.flex-grow-1) i {
  margin: 0 !important;
  font-size: .9rem !important;
}
</style>

</head><body class="s360-admin s360-content-theme">
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlMaterias)?>">Materias</a><a class="btn btn-outline-secondary s360-btn" href="<?=e($urlSalir)?>">Salir</a></div></div></nav>
<main class="s360-shell">
<section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Administración académica</div><h1 class="h2 mb-2"><?= $unidadSeleccionada?'Temas de '.e($unidadSeleccionada['nombre']).' · '.e($unidadSeleccionada['grado']).'°':($materiaSeleccionada?'Temas de '.e($materiaSeleccionada['nombre']):'Todos los temas')?></h1><p class="mb-0 opacity-75">Encuentra, edita y previsualiza cualquier tema desde un solo lugar.</p></div><div class="col-auto"><a id="btnNuevoTema" class="btn btn-light s360-btn" href="<?=e($urlNuevo)?>" style="position:relative;z-index:20;pointer-events:auto!important"><i class="bi bi-plus-lg me-1"></i>Nuevo tema</a></div></div></section>
<?php foreach($errores as $er):?><div class="alert alert-danger border-0"><?=e($er)?></div><?php endforeach;?>
<section class="filterbar mb-4">
<form method="get" class="row g-3 align-items-end">
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Materia</label><select class="form-select subject-select" name="id_materia" onchange="this.form.submit()"><option value="">Todas las materias</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=(int)$idMateria===(int)$m['id_materia']?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div>
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Buscar tema</label><input class="form-control" name="q" value="<?=e($busqueda)?>" placeholder="Nombre, descripción o materia"></div>
<div class="col-12 col-lg-4"><label class="form-label small fw-bold">Grado</label><div class="d-flex flex-wrap gap-2"><?php foreach([''=>'Todos','9'=>'9°','10'=>'10°','11'=>'11°'] as $g=>$label):?><a class="filter-chip <?=$gradoFiltro===$g?'active':''?>" href="<?=e($urlBase.'?'.http_build_query(array_filter(['id_materia'=>$idMateria,'grado'=>$g,'q'=>$busqueda],fn($v)=>$v!==null&&$v!=='')))?>"><?=$label?> <span class="badge rounded-pill <?=$gradoFiltro===$g?'text-bg-light text-primary':'text-bg-secondary'?>"><?=$g===''?$total:$porGrado[$g]?></span></a><?php endforeach;?></div></div>
</form>
<div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top"><span class="small text-secondary"><strong><?=$total?></strong> tema(s) encontrado(s)</span><?php if($idMateria||$gradoFiltro||$busqueda):?><a class="small text-decoration-none fw-bold" href="<?=e($urlBase)?>">Limpiar filtros</a><?php endif;?></div>
</section>
<div class="row g-3">
<?php if(!$temas):?><div class="col-12"><div class="s360-empty"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-journal-x"></i></div><h2 class="h5 fw-bold">No encontramos temas</h2><p class="s360-muted mb-0">Prueba con otros filtros o crea un nuevo tema.</p></div></div><?php endif;?>
<?php foreach($temas as $t):$estado=(string)($t['estado_contenido']??'');if($estado==='')$estado=trim((string)($t['contenido']??''))!==''?'Publicado':'';?>
<div class="col-12 col-md-6 col-xl-4"><article class="topic-card">
<div class="d-flex gap-3 align-items-start mb-3"><div class="topic-icon"><i class="bi bi-journal-text"></i></div><div class="min-w-0"><div class="grade-pill d-inline-flex"><?=$t['grado']?>°</div><h2 class="h6 fw-bold mt-2 mb-1"><?=e($t['tema'])?></h2><?php if(!$materiaSeleccionada):?><div class="small text-secondary"><?=e($t['materia'])?><?php if(!empty($t['unidad'])):?> · <?=e($t['unidad'])?><?php endif;?></div><?php endif;?></div></div>
<p class="topic-desc mb-3"><?=e($t['descripcion']?:'Sin descripción.')?></p><div class="d-flex flex-wrap gap-2 mb-3"><span class="s360-chip"><i class="bi bi-paperclip"></i><?=$t['total_recursos']?> recurso(s)</span><?php if($estado==='Publicado'):?><span class="s360-chip success"><i class="bi bi-check-circle"></i>Publicado</span><?php elseif($estado==='Borrador'):?><span class="s360-chip warning"><i class="bi bi-pencil"></i>Borrador</span><?php else:?><span class="s360-chip"><i class="bi bi-file-earmark"></i>Sin contenido</span><?php endif;?></div>
<div class="topic-actions"><a class="btn btn-primary" href="informacion_tema.php?id=<?=$t['id_tema']?>"><i class="bi bi-pencil me-1"></i>Editar tema</a><a class="btn btn-outline-primary" href="editar_tema.php?id=<?=$t['id_tema']?>"><i class="bi bi-file-earmark-text me-1"></i>Contenido</a><a class="btn btn-outline-secondary" href="vista_previa_tema.php?id=<?=$t['id_tema']?>" target="_blank"><i class="bi bi-eye me-1"></i>Vista previa</a><a class="btn btn-outline-danger ms-auto" href="eliminar_tema.php?id=<?=$t['id_tema']?>" onclick="return confirm('¿Eliminar este tema?');" title="Eliminar"><i class="bi bi-trash3"></i></a></div>
</article></div>
<?php endforeach;?></div></main>



<script id="filtros-automaticos">(function(){var f=document.querySelector('form[method="get"]');if(!f)return;var m=f.querySelector('[name="id_materia"]'),g=f.querySelector('[name="grado"]'),u=f.querySelector('[name="id_unidad"]'),q=f.querySelector('[name="q"]');function sync(){if(!u)return;Array.from(u.options).forEach(function(o){if(!o.value)return;o.hidden=(m&&m.value&&o.dataset.materia!==m.value)||(g&&g.value&&o.dataset.grado!==g.value);});if(u.selectedOptions[0]&&u.selectedOptions[0].hidden)u.value='';}if(m)m.addEventListener('change',function(){sync();f.submit()});if(u)u.addEventListener('change',function(){f.submit()});if(q){var timer; q.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){f.submit()},450)});q.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();clearTimeout(timer);f.submit()}})}sync();})();</script>
<script>
(function(){
  var btn=document.getElementById('btnNuevoTema');
  if(!btn) return;
  btn.addEventListener('click',function(e){
    e.stopPropagation();
    window.location.assign(btn.getAttribute('href'));
  });
})();
</script>

<!-- Studia360: personalizador de apariencia -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza Studia360</div>
<div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" type="button" data-theme="blue"><span class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></span><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" type="button" data-theme="orange"><span class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></span><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" type="button" data-theme="purple"><span class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></span><strong>Violeta</strong><small>Creativo</small></button>
<button class="adm-theme-option" type="button" data-theme="green"><span class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></span><strong>Verde</strong><small>Calma</small></button>
</div>
<button id="admModeBtn" class="adm-mode-btn" type="button"></button>
</div>

<script id="studia360-theme-controller">
(function () {
  "use strict";

  var body = document.body;
  var KEY = "studia360_theme";
  var themes = {
    blue:   { cls: "",                 a: "#2563eb", a2: "#4f46e5", soft: "#eff6ff" },
    orange: { cls: "s360-accent-orange", a: "#f97316", a2: "#ea580c", soft: "#fff7ed" },
    purple: { cls: "s360-accent-purple", a: "#8b5cf6", a2: "#7c3aed", soft: "#f5f3ff" },
    green:  { cls: "s360-accent-green", a: "#10b981", a2: "#059669", soft: "#ecfdf5" }
  };

  var state = { theme: "blue", mode: "light" };

  try {
    var raw = localStorage.getItem(KEY);
    if (raw) {
      var saved = JSON.parse(raw);
      if (saved && typeof saved === "object") {
        if (themes[saved.theme]) state.theme = saved.theme;
        if (saved.mode === "dark" || saved.mode === "light") state.mode = saved.mode;
      }
    }
  } catch (e) {}

  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
  }

  function apply() {
    Object.keys(themes).forEach(function (name) {
      if (themes[name].cls) body.classList.remove(themes[name].cls);
    });

    body.classList.add("s360-content-theme");
    if (themes[state.theme].cls) body.classList.add(themes[state.theme].cls);
    body.classList.toggle("s360-content-dark", state.mode === "dark");
    body.classList.toggle("adm-dark", state.mode === "dark");

    document.querySelectorAll(".adm-theme-option").forEach(function (button) {
      button.classList.toggle("active", button.getAttribute("data-theme") === state.theme);
    });

    var modeButton = document.getElementById("admModeBtn");
    if (modeButton) {
      modeButton.innerHTML = state.mode === "dark"
        ? '<i class="bi bi-sun me-2"></i>Modo claro'
        : '<i class="bi bi-moon-stars me-2"></i>Modo oscuro';
    }
  }

  window.admSetTheme = function (name) {
    if (!themes[name]) return;
    state.theme = name;
    save();
    apply();
  };

  window.admToggleMode = function () {
    state.mode = state.mode === "dark" ? "light" : "dark";
    save();
    apply();
  };

  apply();

  var toggle = document.getElementById("admThemeToggle");
  var panel = document.getElementById("admThemePanel");
  if (toggle && panel) {
    toggle.onclick = function (event) {
      event.preventDefault();
      event.stopPropagation();
      panel.classList.toggle("open");
    };
    panel.onclick = function (event) { event.stopPropagation(); };
    document.addEventListener("click", function (event) {
      if (!panel.contains(event.target) && !toggle.contains(event.target)) {
        panel.classList.remove("open");
      }
    });
  }

  document.querySelectorAll(".adm-theme-option").forEach(function (button) {
    button.onclick = function (event) {
      event.preventDefault();
      event.stopPropagation();
      window.admSetTheme(button.getAttribute("data-theme"));
    };
  });

  var mode = document.getElementById("admModeBtn");
  if (mode) {
    mode.onclick = function (event) {
      event.preventDefault();
      event.stopPropagation();
      window.admToggleMode();
    };
  }
})();
</script>


</body></html>

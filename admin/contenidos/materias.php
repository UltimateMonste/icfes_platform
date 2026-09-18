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
                    $su=$conexion->prepare("SELECT COUNT(*) FROM unidades_tematicas WHERE id_materia=?");$su->execute([$id]);$nu=(int)$su->fetchColumn();
                    if($n>0 || $nu>0)$errores[]="No puedes eliminar esta materia porque tiene {$n} tema(s) asociado(s).";
                    else{$st=$conexion->prepare("DELETE FROM materias WHERE id_materia=?");$st->execute([$id]);$mensajes[]='Materia eliminada correctamente.';}
                }
            }
        } catch(PDOException $ex){$errores[]='No fue posible completar la operación.';}
    }
}
try{
    $st=$conexion->query("SELECT m.id_materia,m.nombre,m.descripcion,COUNT(DISTINCT t.id_tema) cantidad_temas, COUNT(DISTINCT u.id_unidad) cantidad_unidades FROM materias m LEFT JOIN temas t ON t.id_materia=m.id_materia LEFT JOIN unidades_tematicas u ON u.id_materia=m.id_materia AND u.estado='Activa' GROUP BY m.id_materia,m.nombre,m.descripcion ORDER BY m.nombre");
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


<style id="studia360-local-theme-fix">
body.adm-dark,body.s360-content-dark{background:#0d1424!important;color:#edf2f8!important}
body.adm-dark .cardx,body.adm-dark .kv,body.s360-content-dark .cardx,body.s360-content-dark .kv,body.s360-content-dark .materia-card{background:#172236!important;color:#edf2f8!important;border-color:#2b374b!important}
body.adm-dark .kv strong,body.adm-dark .cardx h2,body.s360-content-dark .cardx h2,body.s360-content-dark .materia-card h2,body.s360-content-dark .materia-card p{color:#edf2f8!important}
body.adm-dark .muted,body.adm-dark .text-muted,body.s360-content-dark .muted,body.s360-content-dark .s360-muted,body.s360-content-dark .text-muted{color:#9ba8ba!important}
body.adm-dark .progress,body.s360-content-dark .progress{background:#263449!important}
body.adm-dark .photo,body.adm-dark .placeholder{border-color:#2b374b!important}
body.adm-dark .placeholder{background:#202c41!important}
body.adm-dark .alert-light{background:#111a2b!important;color:#edf2f8!important;border-color:#334158!important}
body.s360-content-theme .materia-card{background:var(--s360card,#fff)!important;color:var(--s360text,#1f2937)!important;border-color:var(--s360line,#e5eaf1)!important}
body.s360-content-theme .materia-card h2,body.s360-content-theme .materia-card p{color:var(--s360text,#1f2937)!important}
body.s360-content-theme.s360-content-dark .materia-card h2,body.s360-content-theme.s360-content-dark .materia-card p{color:#edf2f8!important}
body.s360-content-theme .materia-icon{background:var(--s360soft,#eff6ff)!important;color:var(--s360a,#2563eb)!important}
body.s360-content-theme .topic-pill{background:var(--s360soft,#eff6ff)!important;color:var(--s360a,#2563eb)!important;border-color:var(--s360line,#e5eaf1)!important}
body.s360-content-theme.s360-content-dark .topic-pill{background:#202c41!important;color:#cbd5e1!important;border-color:#334158!important}
body.s360-content-theme.s360-content-dark .btn-light{background:#172236!important;color:#edf2f8!important;border-color:#334158!important}
body.s360-content-theme.s360-content-dark .btn-outline-secondary{background:transparent!important;color:#b8c2d0!important;border-color:#46536a!important}
body.s360-content-theme.s360-content-dark .btn-outline-danger{color:#ff8f9d!important;border-color:#a33b4b!important}
body.s360-content-theme.s360-content-dark .modal-content{background:#172236!important;color:#edf2f8!important;border-color:#334158!important}
body.s360-content-theme.s360-content-dark .modal-header,body.s360-content-theme.s360-content-dark .modal-footer{border-color:#2b374b!important}
body.s360-content-theme.s360-content-dark .modal .form-control{background:#111827!important;color:#edf2f8!important;border-color:#334158!important}
body.s360-content-theme.s360-content-dark .modal .form-control::placeholder{color:#7f8ca1!important}
body.s360-content-theme.s360-content-dark .btn-close{filter:invert(1) grayscale(1) brightness(2)}
body.adm-dark .adm-theme-panel,body.s360-content-theme.s360-content-dark .adm-theme-panel{background:#172236!important;color:#edf2f8!important;border-color:#334158!important}
body.adm-dark .adm-theme-option,body.adm-dark .adm-mode-btn,body.s360-content-theme.s360-content-dark .adm-theme-option,body.s360-content-theme.s360-content-dark .adm-mode-btn{background:#111a2b!important;color:#edf2f8!important;border-color:#334158!important}
</style>


<style id="studia360-materias-fix">
body.s360-content-theme .materia-card{
  background:var(--s360card)!important;color:var(--s360text)!important;
  border-color:var(--s360line)!important;
}
body.s360-content-theme .materia-card h2{
  color:var(--s360text)!important;
}
body.s360-content-theme .materia-card .s360-muted{
  color:var(--s360muted)!important;
}
body.s360-content-theme .materia-icon{
  background:var(--s360soft)!important;color:var(--s360a)!important;
}
body.s360-content-theme.s360-content-dark .materia-icon{
  background:color-mix(in srgb,var(--s360a) 16%,#111827)!important;
  color:#fff!important;
}
body.s360-content-theme .topic-pill{
  background:var(--s360soft)!important;
  color:var(--s360a)!important;
  border-color:color-mix(in srgb,var(--s360a) 20%,var(--s360line))!important;
}
body.s360-content-theme.s360-content-dark .topic-pill{
  background:#202c41!important;color:#dbe5f2!important;border-color:#3a4860!important;
}
body.s360-content-theme .adm-theme-option.active{
  outline:2px solid color-mix(in srgb,var(--s360a) 40%,transparent)!important;
  outline-offset:1px!important;
}
</style>

</head><body class="s360-admin s360-content-theme">
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex align-items-center justify-content-between">
<a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a>
<div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlDashboard)?>"><i class="bi bi-grid me-1"></i>Dashboard</a><a class="btn btn-outline-secondary s360-btn" href="<?=e($urlSalir)?>">Salir</a></div>
</div></nav>
<main class="s360-shell">
<section class="s360-hero mb-4"><div class="row align-items-center g-3"><div class="col"><div class="s360-kicker">Estructura académica</div><h1 class="h2 mb-2">Materias</h1><p class="mb-0 opacity-75">Administra las áreas de estudio y entra a sus temas sin llenar la pantalla de botones.</p></div><div class="col-auto d-flex gap-2 flex-wrap"><a href="<?=e($urlTemas)?>" class="btn btn-light s360-btn"><i class="bi bi-journals me-1"></i>Todos los temas</a><button id="btnNuevaMateria" class="btn btn-light s360-btn" type="button" data-bs-toggle="modal" data-bs-target="#crear" style="position:relative;z-index:20;pointer-events:auto!important"><i class="bi bi-plus-lg me-1"></i>Nueva materia</button></div></div></section>
<?php foreach($mensajes as $m):?><div class="alert alert-success border-0 shadow-sm"><?=e($m)?></div><?php endforeach;?>
<?php foreach($errores as $m):?><div class="alert alert-danger border-0 shadow-sm"><?=e($m)?></div><?php endforeach;?>
<div class="row g-3">
<?php if(!$materias):?><div class="col-12"><div class="s360-empty"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-book"></i></div><h2 class="h5 fw-bold">Aún no hay materias</h2><p class="s360-muted mb-0">Crea la primera para comenzar a organizar los temas.</p></div></div><?php endif;?>
<?php foreach($materias as $m):?>
<div class="col-12 col-md-6 col-xl-4"><article class="materia-card">
<div class="d-flex gap-3 align-items-start mb-3"><div class="materia-icon"><i class="bi bi-book-fill"></i></div><div class="min-w-0"><h2 class="h5 fw-bold mb-2"><?=e($m['nombre'])?></h2><span class="badge rounded-pill topic-pill"><?= (int)$m['cantidad_unidades']?> <?=((int)$m['cantidad_unidades']===1?'unidad':'unidades')?></span> <span class="badge rounded-pill topic-pill ms-1"><?= (int)$m['cantidad_temas']?> <?=((int)$m['cantidad_temas']===1?'tema':'temas')?></span></div></div>
<p class="s360-muted small mb-4" style="line-height:1.6"><?=e($m['descripcion']?:'Esta materia todavía no tiene una descripción.')?></p>
<div class="materia-actions"><a class="btn btn-primary flex-grow-1" href="unidades.php?id_materia=<?=(int)$m['id_materia']?>"><i class="bi bi-collection me-1"></i>Ver unidades</a><a class="btn btn-outline-secondary" href="editar_materia.php?id=<?=(int)$m['id_materia']?>" title="Editar materia"><i class="bi bi-pencil"></i></a></div>
<form method="post" class="mt-2" onsubmit="return confirm('¿Eliminar esta materia? Solo es posible si no tiene temas ni unidades temáticas asociadas.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_materia" value="<?=(int)$m['id_materia']?>"><button class="btn btn-sm btn-outline-danger w-100" type="submit"><i class="bi bi-trash3 me-1"></i>Eliminar</button></form>
</article></div>
<?php endforeach;?></div>
</main>
<div class="modal fade" id="crear" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="accion" value="crear"><div class="modal-header px-4"><h2 class="h5 fw-bold mb-0">Nueva materia</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body px-4"><label class="form-label fw-semibold">Nombre</label><input class="form-control mb-3" name="nombre" maxlength="100" required placeholder="Ej. Matemáticas"><label class="form-label fw-semibold">Descripción</label><textarea class="form-control" name="descripcion" rows="4" placeholder="Describe brevemente la materia"></textarea></div><div class="modal-footer px-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Crear materia</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>




<script>
(function(){
  var btn=document.getElementById('btnNuevaMateria');
  var modalEl=document.getElementById('crear');
  if(!btn || !modalEl) return;

  btn.addEventListener('click',function(e){
    e.stopPropagation();

    if(window.bootstrap && window.bootstrap.Modal){
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    modalEl.classList.add('show');
    modalEl.style.display='block';
    modalEl.style.position='fixed';
    modalEl.style.inset='0';
    modalEl.style.zIndex='9999';
    modalEl.style.background='rgba(15,23,42,.55)';
    modalEl.setAttribute('aria-hidden','false');
    document.body.classList.add('modal-open');
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
(function(){
'use strict';
var body=document.body, key='studia360_theme';
var themes={
  blue:{cls:''},
  orange:{cls:'s360-accent-orange'},
  purple:{cls:'s360-accent-purple'},
  green:{cls:'s360-accent-green'}
};
var state={theme:'blue',mode:'light'};
try{
  var raw=localStorage.getItem(key);
  if(raw){
    var p=JSON.parse(raw);
    if(p&&typeof p==='object'){
      if(themes[p.theme]) state.theme=p.theme;
      if(p.mode==='dark'||p.mode==='light') state.mode=p.mode;
    }
  }
}catch(e){}

function persist(){
  try{localStorage.setItem(key,JSON.stringify(state));}catch(e){}
}
function apply(){
  Object.keys(themes).forEach(function(k){
    if(themes[k].cls) body.classList.remove(themes[k].cls);
  });
  body.classList.add('s360-content-theme');
  if(themes[state.theme].cls) body.classList.add(themes[state.theme].cls);
  body.classList.toggle('s360-content-dark',state.mode==='dark');
  body.classList.toggle('adm-dark',state.mode==='dark');

  document.querySelectorAll('.adm-theme-option').forEach(function(btn){
    btn.classList.toggle('active',btn.getAttribute('data-theme')===state.theme);
    btn.setAttribute('aria-pressed',btn.getAttribute('data-theme')===state.theme?'true':'false');
  });
  var mode=document.getElementById('admModeBtn');
  if(mode) mode.innerHTML=state.mode==='dark'
    ? '<i class="bi bi-sun me-2"></i>Modo claro'
    : '<i class="bi bi-moon-stars me-2"></i>Modo oscuro';
}
window.admSetTheme=function(theme){
  if(!themes[theme]) return false;
  state.theme=theme;
  persist();
  apply();
  return false;
};
window.admToggleMode=function(){
  state.mode=state.mode==='dark'?'light':'dark';
  persist();
  apply();
  return false;
};

var toggle=document.getElementById('admThemeToggle');
var panel=document.getElementById('admThemePanel');
if(toggle && panel){
  toggle.addEventListener('click',function(e){
    e.preventDefault(); e.stopPropagation();
    panel.classList.toggle('open');
  });
  panel.addEventListener('click',function(e){
    e.stopPropagation();
    var option=e.target.closest('.adm-theme-option');
    if(option){
      e.preventDefault();
      window.admSetTheme(option.getAttribute('data-theme'));
      return;
    }
    var mode=e.target.closest('#admModeBtn');
    if(mode){
      e.preventDefault();
      window.admToggleMode();
    }
  });
  document.addEventListener('click',function(e){
    if(!panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove('open');
  });
}
apply();
})();
</script>


</body></html>

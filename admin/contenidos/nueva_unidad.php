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
body.s360-content-theme.s360-content-dark .form-card{background:#172236!important;color:#edf2f8!important;border-color:#2b374b!important}body.s360-content-theme.s360-content-dark .form-control,body.s360-content-theme.s360-content-dark .form-select{background:#111827!important;color:#edf2f8!important;border-color:#334158!important}</style><style id="studia360-theme-unit-fix">
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

</head><body class="s360-admin s360-content-theme"><nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlDashboard)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-primary">Admin</span></a><div class="d-flex gap-2"><a class="btn btn-light border" href="<?=e($urlUnidades)?>"><i class="bi bi-arrow-left me-1"></i>Unidades</a><a class="btn btn-outline-secondary" href="<?=e($urlSalir)?>">Salir</a></div></div></nav><main class="s360-shell"><section class="s360-hero mb-4"><div class="small fw-bold opacity-75 text-uppercase">Estructura académica</div><h1 class="h2 mb-2">Nueva unidad temática</h1><p class="mb-0 opacity-75">Cada unidad pertenece a una materia y a un grado específico.</p></section><?php foreach($errores as $er):?><div class="alert alert-danger border-0 shadow-sm"><?=e($er)?></div><?php endforeach;?><section class="form-card"><form method="post" novalidate><input type="hidden" name="csrf" value="<?=e($csrf)?>"><div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Materia</label><select class="form-select" name="id_materia" required><option value="">Selecciona una materia</option><?php foreach($materias as $m):?><option value="<?=$m['id_materia']?>" <?=((string)($_POST['id_materia']??'')===(string)$m['id_materia'])?'selected':''?>><?=e($m['nombre'])?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label fw-semibold">Grado</label><select class="form-select" name="grado" required><option value="">Selecciona un grado</option><?php foreach(['9'=>'9°','10'=>'10°','11'=>'11°'] as $g=>$label):?><option value="<?=$g?>" <?=((string)($_POST['grado']??'')===$g)?'selected':''?>><?=$label?></option><?php endforeach;?></select></div><div class="col-12"><label class="form-label fw-semibold">Nombre</label><input class="form-control" name="nombre" maxlength="100" required value="<?=e($_POST['nombre']??'')?>" placeholder="Ej. Álgebra"><div class="hint mt-2">El nombre puede repetirse en otros grados. Por ejemplo, “Álgebra” de 9° y “Álgebra” de 10° son unidades diferentes.</div></div><div class="col-12"><label class="form-label fw-semibold">Descripción <span class="text-secondary fw-normal">(opcional)</span></label><textarea class="form-control" name="descripcion" maxlength="500" rows="4" placeholder="Describe brevemente qué se estudiará en esta unidad."><?=e($_POST['descripcion']??'')?></textarea></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light border" href="<?=e($urlUnidades)?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Crear unidad</button></div></form></section></main><button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button><div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia"><div class="adm-theme-title">Personaliza Studia360</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro.</div><div class="adm-theme-grid"><button class="adm-theme-option" type="button" data-theme="blue"><span class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></span><strong>Azul</strong><small>Clásico</small></button><button class="adm-theme-option" type="button" data-theme="orange"><span class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></span><strong>Naranja</strong><small>Enérgico</small></button><button class="adm-theme-option" type="button" data-theme="purple"><span class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></span><strong>Violeta</strong><small>Creativo</small></button><button class="adm-theme-option" type="button" data-theme="green"><span class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></span><strong>Verde</strong><small>Calma</small></button></div><button id="admModeBtn" class="adm-mode-btn" type="button"></button></div><script id="studia360-unit-theme-controller">(function(){var b=document.body,k="studia360_theme",t={blue:"",orange:"s360-accent-orange",purple:"s360-accent-purple",green:"s360-accent-green"},s={theme:"blue",mode:"light"};try{var x=JSON.parse(localStorage.getItem(k)||"{}");if(t[x.theme]!==undefined)s.theme=x.theme;if(x.mode==="dark"||x.mode==="light")s.mode=x.mode}catch(e){}function save(){try{localStorage.setItem(k,JSON.stringify(s))}catch(e){}}function apply(){Object.keys(t).forEach(function(n){if(t[n])b.classList.remove(t[n])});b.classList.add("s360-content-theme");if(t[s.theme])b.classList.add(t[s.theme]);b.classList.toggle("s360-content-dark",s.mode==="dark");document.querySelectorAll(".adm-theme-option").forEach(function(o){o.classList.toggle("active",o.dataset.theme===s.theme)});var m=document.getElementById("admModeBtn");if(m)m.innerHTML=s.mode==="dark"?'<i class="bi bi-sun me-2"></i>Modo claro':'<i class="bi bi-moon-stars me-2"></i>Modo oscuro'}apply();var q=document.getElementById("admThemeToggle"),p=document.getElementById("admThemePanel");if(q&&p){q.onclick=function(e){e.preventDefault();e.stopPropagation();p.classList.toggle("open")};p.onclick=function(e){e.stopPropagation()};document.addEventListener("click",function(e){if(!p.contains(e.target)&&!q.contains(e.target))p.classList.remove("open")})}document.querySelectorAll(".adm-theme-option").forEach(function(o){o.onclick=function(){s.theme=o.dataset.theme;save();apply()}});var m=document.getElementById("admModeBtn");if(m)m.onclick=function(){s.mode=s.mode==="dark"?"light":"dark";save();apply()}})();</script></body></html>

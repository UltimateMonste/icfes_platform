<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';
exigirEstudiante();

$idUsuario=(int)($_SESSION['id_usuario']??0);
$grado=(string)($_GET['grado']??'');
$idMateria=(int)($_GET['id_materia']??0);
$idUnidad=(int)($_GET['id_unidad']??0);
if(!in_array($grado,['9','10','11'],true)){$grado='11';}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$materia=null;
$unidad=null;
$materias=[];
$unidades=[];
$temas=[];

try{
    // Nivel 1: materias.
    if($idMateria<=0){
        $st=$conexion->prepare("
            SELECT m.id_materia,m.nombre,m.descripcion,COUNT(t.id_tema) AS total_temas
            FROM materias m
            LEFT JOIN temas t ON t.id_materia=m.id_materia AND t.grado=?
            GROUP BY m.id_materia,m.nombre,m.descripcion
            ORDER BY m.nombre
        ");
        $st->execute([$grado]);
        $materias=$st->fetchAll(PDO::FETCH_ASSOC);
    }else{
        // Validamos que la materia exista.
        $st=$conexion->prepare("SELECT id_materia,nombre,descripcion FROM materias WHERE id_materia=? LIMIT 1");
        $st->execute([$idMateria]);
        $materia=$st->fetch(PDO::FETCH_ASSOC);
        if(!$materia){
            $idMateria=0;
        }
    }

    // Nivel 2: unidades temáticas de la materia.
    if($idMateria>0 && $idUnidad<=0){
        $st=$conexion->prepare("
            SELECT
                u.id_unidad,u.nombre,u.descripcion,u.es_predeterminada,
                COUNT(t.id_tema) AS total_temas,
                COALESCE(ROUND(AVG(COALESCE(p.porcentaje_avance,0))),0) AS progreso
            FROM unidades_tematicas u
            LEFT JOIN temas t
                ON t.id_unidad=u.id_unidad AND t.grado=?
            LEFT JOIN progreso p
                ON p.id_tema=t.id_tema AND p.id_usuario=?
            WHERE u.id_materia=? AND u.estado='Activa'
            GROUP BY u.id_unidad,u.nombre,u.descripcion,u.es_predeterminada
            HAVING COUNT(t.id_tema)>0 OR u.es_predeterminada=0
            ORDER BY u.es_predeterminada DESC,u.nombre
        ");
        $st->execute([$grado,$idUsuario,$idMateria]);
        $unidades=$st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Nivel 3: temas de la unidad seleccionada.
    if($idMateria>0 && $idUnidad>0){
        $st=$conexion->prepare("
            SELECT
                u.id_unidad,u.nombre AS unidad,
                t.id_tema,t.nombre,t.descripcion,t.id_materia,m.nombre AS materia,
                COALESCE(p.porcentaje_avance,0) porcentaje_avance,
                COALESCE(p.recursos_vistos,0) recursos_vistos,
                COALESCE(p.evaluaciones_realizadas,0) evaluaciones_realizadas
            FROM unidades_tematicas u
            INNER JOIN materias m ON m.id_materia=u.id_materia
            INNER JOIN temas t ON t.id_unidad=u.id_unidad
            LEFT JOIN progreso p ON p.id_tema=t.id_tema AND p.id_usuario=?
            WHERE u.id_unidad=? AND u.id_materia=? AND u.estado='Activa' AND t.grado=?
            ORDER BY t.id_tema
        ");
        $st->execute([$idUsuario,$idUnidad,$idMateria,$grado]);
        $temas=$st->fetchAll(PDO::FETCH_ASSOC);
        if(!$temas){
            // La unidad puede no tener temas para este grado; seguimos mostrando la unidad para no romper la navegación.
            $st=$conexion->prepare("SELECT id_unidad,nombre,descripcion,es_predeterminada FROM unidades_tematicas WHERE id_unidad=? AND id_materia=? AND estado='Activa' LIMIT 1");
            $st->execute([$idUnidad,$idMateria]);
            $unidad=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        }else{
            $unidad=['id_unidad'=>$idUnidad,'nombre'=>$temas[0]['unidad'],'descripcion'=>'','es_predeterminada'=>0];
        }
    }
}catch(Throwable $e){
    die('No fue posible cargar los contenidos.');
}

$foto='';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contenidos | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#f4f7fb;color:#26364a}.navbar{background:linear-gradient(100deg,#173f80,#2467c5)}.cardx{background:#fff;border:1px solid #dce5f0;border-radius:19px;box-shadow:0 8px 25px #1f395c0c}.topic{transition:.2s}.topic:hover{transform:translateY(-3px)}.progress{height:9px}.progress-bar{background:linear-gradient(90deg,#2467c5,#65a1f0)}</style>
<style>
.grade-switcher{
  display:inline-flex;align-items:center;gap:4px;
  padding:4px;background:var(--sd-card,#fff);
  border:1px solid var(--sd-line,#e6ebf2);
  border-radius:12px;
  box-shadow:0 5px 18px rgba(20,35,60,.06);
}
.grade-switcher a{
  min-width:42px;padding:7px 10px;text-align:center;
  border-radius:9px;text-decoration:none;
  color:var(--sd-muted,#7b8798);font-size:.72rem;font-weight:800;
  transition:.18s ease;
}
.grade-switcher a:hover{color:var(--sd-accent,#2563eb);background:var(--sd-accent-soft,#eff6ff)}
.grade-switcher a.active{
  color:#fff;background:var(--sd-accent,#2563eb);
  box-shadow:0 4px 10px color-mix(in srgb,var(--sd-accent,#2563eb) 22%,transparent);
}
@media(max-width:575px){
  .grade-switcher{width:100%;justify-content:space-between}
  .grade-switcher a{flex:1}
}
</style><style id="studia360-common-style">
:root{
  --sd-accent:#2563eb;
  --sd-accent-2:#4f46e5;
  --sd-accent-soft:#eff6ff;
  --sd-bg:#f7f9fc;
  --sd-card:#ffffff;
  --sd-text:#1f2a3d;
  --sd-muted:#7b8798;
  --sd-line:#e6ebf2;
  --sd-success:#10b981;
  --sd-warning:#f59e0b;
  --sd-danger:#ef4444;
  --sd-shadow:0 12px 34px rgba(31,55,86,.055);
  --sd-shadow-hover:0 18px 42px rgba(31,55,86,.09);
  --sd-radius:20px;
}
html{scroll-behavior:smooth}
body.sd-page{
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 8%,transparent),transparent 28rem),
    var(--sd-bg)!important;
  color:var(--sd-text);
  transition:background .25s,color .25s;
}
.sd-navbar{
  position:sticky;top:0;z-index:1030;
  min-height:68px;
  background:rgba(255,255,255,.9)!important;
  border-bottom:1px solid var(--sd-line)!important;
  box-shadow:0 5px 24px rgba(31,55,86,.035);
  backdrop-filter:blur(15px);
}
.sd-brand{
  display:flex;align-items:center;gap:9px;
  color:var(--sd-text)!important;font-weight:850!important;
  letter-spacing:-.035em;text-decoration:none;
}
.sd-logo{
  width:38px;height:38px;border-radius:12px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 8px 18px color-mix(in srgb,var(--sd-accent) 22%,transparent);
}
.sd-nav-actions{display:flex;align-items:center;gap:7px}
.sd-nav-btn{
  height:38px;border:1px solid var(--sd-line);background:#fff!important;
  color:#637086!important;border-radius:11px!important;
  font-size:.73rem!important;font-weight:750!important;
}
.sd-nav-btn:hover{background:var(--sd-accent-soft)!important;color:var(--sd-accent)!important}
.sd-avatar-mini{
  width:34px;height:34px;border-radius:50%;overflow:hidden;
  display:grid;place-items:center;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-weight:850;border:2px solid #fff;
  box-shadow:0 2px 9px rgba(20,40,70,.08);
}
.sd-avatar-mini img{width:100%;height:100%;object-fit:cover}
.sd-shell{width:min(1240px,calc(100% - 30px));margin:auto;padding:27px 0 55px}
.sd-page-title{font-size:1.65rem;font-weight:850;letter-spacing:-.04em;margin:0}
.sd-page-sub{font-size:.78rem;color:var(--sd-muted);margin:.25rem 0 0}
.sd-card{
  background:var(--sd-card)!important;
  border:1px solid var(--sd-line)!important;
  border-radius:var(--sd-radius)!important;
  box-shadow:var(--sd-shadow)!important;
}
.sd-card:hover{box-shadow:var(--sd-shadow-hover)!important}
.sd-btn{
  border-radius:10px!important;font-size:.74rem!important;
  font-weight:750!important;padding:.52rem .76rem!important;
}
.sd-btn-primary{
  color:#fff!important;background:var(--sd-accent)!important;border-color:var(--sd-accent)!important;
  box-shadow:0 7px 16px color-mix(in srgb,var(--sd-accent) 14%,transparent);
}
.sd-btn-outline{
  color:var(--sd-accent)!important;background:var(--sd-card)!important;border:1px solid color-mix(in srgb,var(--sd-accent) 22%,var(--sd-line))!important;
}
.sd-btn-outline:hover{background:var(--sd-accent-soft)!important}
.sd-form .form-control,.sd-form .form-select{
  border:1px solid var(--sd-line)!important;border-radius:11px!important;
  min-height:40px;box-shadow:none!important;
}
.sd-form .form-control:focus,.sd-form .form-select:focus{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff)!important;
  box-shadow:0 0 0 4px color-mix(in srgb,var(--sd-accent) 9%,transparent)!important;
}
.sd-muted{color:var(--sd-muted)!important}
.sd-eyebrow{
  display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;
  color:var(--sd-accent);background:var(--sd-accent-soft);
  border:1px solid color-mix(in srgb,var(--sd-accent) 12%,#fff);
  font-size:.61rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em;
}
.sd-hero{
  position:relative;overflow:hidden;color:#fff;
  border-radius:25px;padding:27px 29px;
  background:
    radial-gradient(circle at 90% 12%,rgba(255,255,255,.15),transparent 17rem),
    linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 18px 44px color-mix(in srgb,var(--sd-accent) 15%,transparent);
}
.sd-hero:after{
  content:"";position:absolute;width:200px;height:200px;border-radius:50%;
  right:-75px;bottom:-110px;border:27px solid rgba(255,255,255,.055);
}
.sd-hero>*{position:relative;z-index:1}
.sd-fade{animation:sdFade .38s ease both}
@keyframes sdFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.sd-theme-toggle{
  position:fixed;right:20px;bottom:20px;z-index:1045;
  width:48px;height:48px;border:0;border-radius:16px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 12px 28px color-mix(in srgb,var(--sd-accent) 25%,transparent);
  cursor:pointer;transition:transform .2s,box-shadow .2s;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(4deg)}
.sd-theme-panel{
  position:fixed;right:20px;bottom:78px;z-index:1046;width:285px;
  padding:15px;background:var(--sd-card);border:1px solid var(--sd-line);
  border-radius:18px;box-shadow:0 20px 50px rgba(20,35,60,.15);
  transform:translateY(8px) scale(.98);opacity:0;pointer-events:none;
  transition:.2s;
}
.sd-theme-panel.open{transform:none;opacity:1;pointer-events:auto}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--sd-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
  border:1px solid var(--sd-line);background:var(--sd-card);border-radius:12px;
  padding:9px;text-align:left;cursor:pointer;color:var(--sd-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff);
  background:var(--sd-accent-soft);
}
.sd-theme-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.sd-theme-option strong{display:block;font-size:.66rem}
.sd-theme-option small{font-size:.57rem;color:var(--sd-muted)}
.sd-theme-mode{
  width:100%;margin-top:8px;border:1px solid var(--sd-line);background:var(--sd-card);
  border-radius:12px;padding:8px;color:var(--sd-text);font-size:.68rem;font-weight:750;
  cursor:pointer;text-align:left;
}

/* Student badges/titles */
.sd-title-row{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
.sd-title-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 8px;border-radius:999px;
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.18);
  color:#fff;font-size:.62rem;font-weight:800;
}
.sd-title-badge.dark{
  color:#795f13;background:#fff8df;border-color:#efdfaa;
}
.sd-title-badge i{font-size:.65rem}

/* Dark mode */
body.sd-dark{
  --sd-bg:#111827;--sd-card:#182235;--sd-text:#edf2f7;--sd-muted:#9aa7ba;--sd-line:#2a3549;
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 13%,transparent),transparent 28rem),
    var(--sd-bg)!important;
}
body.sd-dark .sd-navbar{background:rgba(17,24,39,.88)!important}
body.sd-dark .sd-nav-btn{background:#182235!important;color:#aab5c5!important}
body.sd-dark .sd-card,
body.sd-dark .card,
body.sd-dark .cardx,
body.sd-dark .panel,
body.sd-dark .subject,
body.sd-dark .quick,
body.sd-dark .level-card,
body.sd-dark .explore,
body.sd-dark .resource-card,
body.sd-dark .evaluation-card,
body.sd-dark .stat,
body.sd-dark .avatar-option,
body.sd-dark .message-card{background:var(--sd-card)!important;color:var(--sd-text)!important}
body.sd-dark .form-control,body.sd-dark .form-select{
  background:#111827!important;color:var(--sd-text)!important;border-color:var(--sd-line)!important;
}
body.sd-dark .form-control::placeholder{color:#718096}
body.sd-dark .text-dark,body.sd-dark h1,body.sd-dark h2,body.sd-dark h3,body.sd-dark h4,body.sd-dark h5,
body.sd-dark .fw-bold,body.sd-dark .fw-semibold{color:var(--sd-text)!important}
body.sd-dark .text-muted,body.sd-dark .muted{color:var(--sd-muted)!important}
body.sd-dark .sd-theme-option,body.sd-dark .sd-theme-mode{background:var(--sd-card);color:var(--sd-text)}
body.sd-dark .table{--bs-table-bg:var(--sd-card);--bs-table-color:var(--sd-text)}
body.sd-dark .dropdown-menu{background:#182235;border-color:var(--sd-line)}
body.sd-dark .dropdown-item{color:#dce4ee}
body.sd-dark .dropdown-item:hover{background:#202d42}
body.sd-dark .bg-light{background:#202d42!important}
body.sd-dark .border{border-color:var(--sd-line)!important}

/* Accent themes */
body.sd-accent-orange{--sd-accent:#f97316;--sd-accent-2:#ea580c;--sd-accent-soft:#fff7ed}
body.sd-accent-purple{--sd-accent:#8b5cf6;--sd-accent-2:#7c3aed;--sd-accent-soft:#f5f3ff}
body.sd-accent-green{--sd-accent:#10b981;--sd-accent-2:#059669;--sd-accent-soft:#ecfdf5}

@media(max-width:767px){
  .sd-shell{width:min(100% - 20px,1240px);padding-top:19px}
  .sd-page-title{font-size:1.4rem}
  .sd-theme-toggle{right:14px;bottom:14px}
  .sd-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation:none!important;transition:none!important}
}
</style>
<link rel="stylesheet" href="studia360-estudiante.css">
<style id="grado-theme-overrides">
body.sd-page{background:var(--sd-bg)!important;color:var(--sd-text)!important}
.cardx{background:var(--sd-card)!important;border-color:var(--sd-line)!important;color:var(--sd-text)!important}
.cardx .text-muted,.cardx p{color:var(--sd-muted)!important}
.topic:hover{border-color:color-mix(in srgb,var(--sd-accent) 24%,var(--sd-line))}
.progress{background:color-mix(in srgb,var(--sd-accent) 9%,var(--sd-line))!important}
.progress-bar{background:linear-gradient(90deg,var(--sd-accent),var(--sd-accent-2))!important}
.btn-primary{background:var(--sd-accent)!important;border-color:var(--sd-accent)!important}
.btn-primary:hover{background:var(--sd-accent-2)!important;border-color:var(--sd-accent-2)!important}
.text-primary{color:var(--sd-accent)!important}
.badge.text-bg-light{background:var(--sd-accent-soft)!important;color:var(--sd-accent)!important}
</style><style id="hierarchy-styles">
.sd-unit-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:var(--sd-accent-soft);color:var(--sd-accent);font-size:1.05rem;border:1px solid color-mix(in srgb,var(--sd-accent) 10%,var(--sd-line));}
.sd-back{display:inline-flex;align-items:center;gap:7px;text-decoration:none;color:var(--sd-muted);font-size:.72rem;font-weight:800;}
.sd-back:hover{color:var(--sd-accent)}
.sd-predetermined,.sd-topic-label{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 8px;font-size:.58rem;font-weight:850;background:var(--sd-accent-soft);color:var(--sd-accent);border:1px solid color-mix(in srgb,var(--sd-accent) 10%,var(--sd-line));}
.sd-topic-label{width:max-content}
.sd-progress{height:8px;background:color-mix(in srgb,var(--sd-accent) 8%,var(--sd-line));}
.sd-progress .progress-bar{background:linear-gradient(90deg,var(--sd-accent),var(--sd-accent-2));}
body.sd-dark .sd-unit-icon,body.sd-dark .sd-predetermined,body.sd-dark .sd-topic-label{background:color-mix(in srgb,var(--sd-accent) 15%,var(--sd-card));border-color:var(--sd-line);}
</style>
</head><body class="sd-page">

<nav class="navbar sd-navbar">
  <div class="container-fluid px-3 px-lg-4">
    <a href="dashboard.php" class="sd-brand">
      <span class="sd-logo"><i class="bi bi-stars"></i></span>
      <span>Studia360</span>
    </a>
    <div class="sd-nav-actions">
      <div class="dropdown">
        <button class="btn sd-nav-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Explorar</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm p-2" style="border-radius:14px">
          <li><a class="dropdown-item rounded-2 small" href="dashboard.php"><i class="bi bi-house me-2"></i>Inicio</a></li>
          <li><a class="dropdown-item rounded-2 small" href="grado.php?grado=9"><i class="bi bi-book me-2"></i>Materias y temas</a></li>
          <li><a class="dropdown-item rounded-2 small" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
          <li><a class="dropdown-item rounded-2 small" href="sugerencias.php"><i class="bi bi-chat-left-heart me-2"></i>Quejas y recomendaciones</a></li>
          <li><a class="dropdown-item rounded-2 small" href="recuperacion.php"><i class="bi bi-key me-2"></i>Notificaciones</a></li>
        </ul>
      </div>
      <a href="perfil.php" class="sd-avatar-mini d-none d-sm-grid" title="Mi perfil">
        <?php if (!empty($foto)): ?><img src="<?=h($foto)?>" alt="Mi perfil"><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
      </a>
      <a href="perfil.php" class="btn sd-nav-btn d-none d-md-inline-flex"><i class="bi bi-person me-1"></i>Perfil</a>
      <a href="../cerrar_sesion.php" class="btn sd-nav-btn" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Salir</span></a>
    </div>
  </div>
</nav>

<main class="sd-shell">
<?php if($idMateria<=0): ?>
  <div class="mb-4 d-flex justify-content-between align-items-end gap-3 flex-wrap sd-fade">
    <div>
      <span class="sd-eyebrow"><i class="bi bi-book-half"></i> Aprendizaje</span>
      <h1 class="sd-page-title mt-2">¿Qué materia estudiarás?</h1>
      <p class="sd-page-sub">Explora tus materias y entra en la temática que quieras aprender.</p>
    </div>
    <nav class="grade-switcher" aria-label="Cambiar de grado">
      <a href="grado.php?grado=9" class="<?= $grado==='9'?'active':'' ?>">9°</a>
      <a href="grado.php?grado=10" class="<?= $grado==='10'?'active':'' ?>">10°</a>
      <a href="grado.php?grado=11" class="<?= $grado==='11'?'active':'' ?>">11°</a>
    </nav>
  </div>
  <div class="row g-3 sd-fade">
    <?php foreach($materias as $m): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <article class="sd-card topic p-4 h-100 d-flex flex-column">
          <div class="sd-unit-icon mb-3"><i class="bi bi-book-half"></i></div>
          <h2 class="h5 fw-bold mb-2"><?=h($m['nombre'])?></h2>
          <p class="small sd-muted mb-3"><?=h($m['descripcion'] ?: 'Explora las temáticas disponibles y aprende a tu ritmo.')?></p>
          <div class="mt-auto d-flex justify-content-between align-items-center gap-2">
            <span class="small sd-muted"><i class="bi bi-journal-text me-1"></i><?=number_format((int)$m['total_temas'])?> tema(s)</span>
            <a class="sd-btn sd-btn-primary text-decoration-none" href="<?=h('grado.php?grado='.urlencode($grado).'&id_materia='.(int)$m['id_materia'])?>">Explorar <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
    <?php if(!$materias): ?>
      <div class="col-12"><div class="sd-card p-5 text-center"><div class="sd-unit-icon mx-auto mb-3"><i class="bi bi-book"></i></div><div class="fw-bold">Aún no hay materias disponibles</div><div class="small sd-muted mt-1">Cuando haya materias con contenidos para este grado, aparecerán aquí.</div></div></div>
    <?php endif; ?>
  </div>

<?php elseif($idUnidad<=0): ?>
  <div class="mb-4 sd-fade">
    <a class="sd-back" href="dashboard.php"><i class="bi bi-arrow-left"></i> Volver al dashboard</a>
    <div class="mt-3">
      <span class="sd-eyebrow"><i class="bi bi-layers"></i> <?=h($materia['nombre'] ?? 'Materia')?></span>
      <h1 class="sd-page-title mt-2">¿Qué temática estudiarás?</h1>
      <p class="sd-page-sub"><?=h($materia['descripcion'] ?? '')?></p>
    </div>
  </div>
  <div class="row g-3 sd-fade">
    <?php foreach($unidades as $u): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <article class="sd-card topic p-4 h-100 d-flex flex-column">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="sd-unit-icon"><i class="bi bi-collection"></i></div>
            <?php if((int)$u['es_predeterminada']===1): ?><span class="sd-predetermined">Transición</span><?php endif; ?>
          </div>
          <h2 class="h5 fw-bold mb-2"><?=h($u['nombre'])?></h2>
          <p class="small sd-muted mb-3"><?=h($u['descripcion'] ?: 'Explora los temas que pertenecen a esta temática.')?></p>
          <div class="mt-auto">
            <div class="d-flex justify-content-between small sd-muted mb-2"><span><?=number_format((int)$u['total_temas'])?> tema(s)</span><strong><?=number_format((float)$u['progreso'],0)?>%</strong></div>
            <div class="progress sd-progress mb-3"><div class="progress-bar" style="width:<?=h((string)$u['progreso'])?>%"></div></div>
            <a class="sd-btn sd-btn-primary w-100 text-center text-decoration-none d-block" href="<?=h('grado.php?grado='.urlencode($grado).'&id_materia='.(int)$idMateria.'&id_unidad='.(int)$u['id_unidad'])?>">Ver temas <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
    <?php if(!$unidades): ?>
      <div class="col-12"><div class="sd-card p-5 text-center"><div class="sd-unit-icon mx-auto mb-3"><i class="bi bi-collection"></i></div><div class="fw-bold">No hay temáticas disponibles</div><div class="small sd-muted mt-1">Esta materia todavía no tiene unidades temáticas activas para este grado.</div></div></div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="mb-4 sd-fade">
    <a class="sd-back" href="grado.php?grado=<?=h($grado)?>&id_materia=<?=h((string)$idMateria)?>"><i class="bi bi-arrow-left"></i> Volver a temáticas</a>
    <div class="mt-3">
      <span class="sd-eyebrow"><i class="bi bi-collection"></i> <?=h($materia['nombre'] ?? 'Materia')?></span>
      <h1 class="sd-page-title mt-2"><?=h($unidad['nombre'] ?? 'Temas')?></h1>
      <p class="sd-page-sub">Elige un tema para comenzar a estudiar.</p>
    </div>
  </div>
  <div class="row g-3 sd-fade">
    <?php foreach($temas as $t): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <article class="sd-card topic p-4 h-100 d-flex flex-column">
          <span class="sd-topic-label mb-3"><i class="bi bi-journal-text"></i> Tema</span>
          <h2 class="h5 fw-bold mb-2"><?=h($t['nombre'])?></h2>
          <p class="small sd-muted mb-3"><?=h($t['descripcion']??'')?></p>
          <div class="mt-auto">
            <div class="d-flex justify-content-between small sd-muted mb-1"><span>Progreso</span><strong><?=number_format((float)$t['porcentaje_avance'],0)?>%</strong></div>
            <div class="progress sd-progress mb-3"><div class="progress-bar" style="width:<?=h((string)$t['porcentaje_avance'])?>%"></div></div>
            <a class="sd-btn sd-btn-primary w-100 text-center text-decoration-none d-block" href="<?=h(urlAplicacion('/estudiante/tema.php?id='.(int)$t['id_tema'].'&return_grado='.urlencode($grado).'&return_materia='.(int)$idMateria.'&return_unidad='.(int)$idUnidad))?>">Estudiar tema <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
    <?php if(!$temas): ?>
      <div class="col-12"><div class="sd-card p-5 text-center"><div class="sd-unit-icon mx-auto mb-3"><i class="bi bi-journal-x"></i></div><div class="fw-bold">Esta temática aún no tiene temas para este grado</div><div class="small sd-muted mt-1">Puedes volver a las temáticas y elegir otra.</div></div></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</main>
<button id="sdThemeToggle" class="sd-theme-toggle" type="button" aria-label="Cambiar apariencia" title="Personalizar apariencia">
  <i class="bi bi-palette2"></i>
</button>
<div id="sdThemePanel" class="sd-theme-panel" aria-label="Personalizar apariencia">
  <div class="sd-theme-title">Personaliza Studia360</div>
  <div class="sd-theme-sub">Elige el color que te guste y decide si prefieres fondo claro u oscuro.</div>
  <div class="sd-theme-row">
    <button class="sd-theme-option" data-theme="blue" onclick="studiaTheme('blue')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
    <button class="sd-theme-option" data-theme="orange" onclick="studiaTheme('orange')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
    <button class="sd-theme-option" data-theme="purple" onclick="studiaTheme('purple')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Creativo</small></button>
    <button class="sd-theme-option" data-theme="green" onclick="studiaTheme('green')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
  </div>
  <button id="sdThemeMode" class="sd-theme-mode" type="button" onclick="studiaThemeMode()"></button>
</div>

<script>
(function(){
  const body=document.body;
  const key="studia360_theme";
  const themes={
    blue:{label:"Azul",className:""},
    orange:{label:"Naranja",className:"sd-accent-orange"},
    purple:{label:"Violeta",className:"sd-accent-purple"},
    green:{label:"Verde",className:"sd-accent-green"}
  };
  function applyTheme(theme,mode){
    Object.values(themes).forEach(t=>{if(t.className) body.classList.remove(t.className)});
    if(themes[theme]?.className) body.classList.add(themes[theme].className);
    body.classList.toggle("sd-dark",mode==="dark");
    document.querySelectorAll(".sd-theme-option").forEach(el=>el.classList.toggle("active",el.dataset.theme===theme));
    const modeBtn=document.getElementById("sdThemeMode");
    if(modeBtn) modeBtn.innerHTML=(mode==="dark"?"<i class='bi bi-moon-stars me-2'></i>Modo oscuro":"<i class='bi bi-sun me-2'></i>Modo claro");
  }
  let saved={theme:"blue",mode:"light"};
  try{saved=Object.assign(saved,JSON.parse(localStorage.getItem(key)||"{}"));}catch(e){}
  applyTheme(saved.theme,saved.mode);
  window.studiaTheme=function(theme){
    saved.theme=theme;
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  window.studiaThemeMode=function(){
    saved.mode=saved.mode==="dark"?"light":"dark";
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  const toggle=document.getElementById("sdThemeToggle");
  const panel=document.getElementById("sdThemePanel");
  toggle?.addEventListener("click",()=>panel?.classList.toggle("open"));
  document.addEventListener("click",e=>{
    if(panel && panel.classList.contains("open") && !panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove("open");
  });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

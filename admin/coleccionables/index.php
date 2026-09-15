<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
    $avatares=$conexion->query("SELECT a.*,COUNT(u.id_usuario) AS usuarios_con_avatar FROM avatares a LEFT JOIN usuarios u ON u.id_avatar=a.id_avatar GROUP BY a.id_avatar ORDER BY a.puntos_requeridos,a.id_avatar")->fetchAll(PDO::FETCH_ASSOC);
    $insignias=$conexion->query("SELECT i.*,COUNT(ui.id_usuario) AS usuarios_con_insignia FROM insignias i LEFT JOIN usuarios_insignias ui ON ui.id_insignia=i.id_insignia GROUP BY i.id_insignia ORDER BY i.id_insignia")->fetchAll(PDO::FETCH_ASSOC);
    $usuarios=(int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE id_rol=(SELECT id_rol FROM roles WHERE nombre='Estudiante' LIMIT 1) AND estado='Activo'")->fetchColumn();
    $desbloqueos=(int)$conexion->query("SELECT COUNT(*) FROM usuarios_insignias")->fetchColumn();
} catch(Throwable $e) { $avatares=[];$insignias=[];$usuarios=0;$desbloqueos=0; }
$activosA=count(array_filter($avatares,fn($a)=>$a['estado']==='Activo'));
$activosI=count(array_filter($insignias,fn($i)=>$i['estado']==='Activa'));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Coleccionables | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>
:root{
  --a-accent:#8b5cf6;
  --a-accent-2:#7c3aed;
  --a-soft:#f5f3ff;
  --a-bg:#f6f8fc;
  --a-card:#fff;
  --a-card-2:#fafbfe;
  --a-text:#202a3b;
  --a-muted:#7b8798;
  --a-line:#e4e9f1;
  --a-success:#10b981;
  --a-shadow:0 12px 32px rgba(31,48,76,.055);
  --a-shadow-hover:0 18px 42px rgba(31,48,76,.10);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body.admin-coleccionables{
  margin:0;
  min-height:100vh;
  background:
    radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--a-accent) 8%,transparent),transparent 28rem),
    var(--a-bg)!important;
  color:var(--a-text)!important;
  font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
  transition:background .25s ease,color .25s ease;
}
a{text-decoration:none}
.top{
  position:sticky;
  top:0;
  z-index:1030;
  background:rgba(255,255,255,.9)!important;
  border:0!important;
  border-bottom:1px solid var(--a-line)!important;
  box-shadow:0 5px 24px rgba(31,48,76,.045)!important;
  backdrop-filter:blur(16px);
}
.top .container{
  min-height:68px;
}
.admin-brand{
  display:inline-flex;
  align-items:center;
  gap:9px;
  color:var(--a-text)!important;
  font-weight:850!important;
  letter-spacing:-.035em;
}
.admin-logo{
  width:38px;height:38px;border-radius:12px;
  display:grid;place-items:center;
  color:#fff;
  background:linear-gradient(145deg,var(--a-accent),var(--a-accent-2));
  box-shadow:0 8px 18px color-mix(in srgb,var(--a-accent) 23%,transparent);
}
.admin-brand .admin-badge{
  margin-left:2px;
  border:1px solid color-mix(in srgb,var(--a-accent) 14%,var(--a-line));
  background:var(--a-soft)!important;
  color:var(--a-accent)!important;
  border-radius:999px!important;
  font-size:.58rem;
  padding:.38rem .55rem;
}
.admin-nav-btn{
  border:1px solid var(--a-line)!important;
  background:var(--a-card)!important;
  color:#667389!important;
  border-radius:11px!important;
  font-size:.69rem!important;
  font-weight:800!important;
  transition:.18s ease;
}
.admin-nav-btn:hover{
  color:var(--a-accent)!important;
  background:var(--a-soft)!important;
  transform:translateY(-1px);
}
.admin-nav-btn.primary{
  background:var(--a-accent)!important;
  color:#fff!important;
  border-color:var(--a-accent)!important;
}
main.container{
  max-width:1160px;
}
.hero{
  position:relative;
  overflow:hidden;
  isolation:isolate;
  border-radius:25px!important;
  padding:30px!important;
  color:#fff!important;
  background:
    radial-gradient(circle at 88% 8%,rgba(255,255,255,.15),transparent 15rem),
    linear-gradient(125deg,var(--a-accent),var(--a-accent-2))!important;
  border:0!important;
  box-shadow:0 18px 44px color-mix(in srgb,var(--a-accent) 17%,transparent)!important;
}
.hero:before{
  content:"";
  position:absolute;
  width:230px;height:230px;
  right:-90px;bottom:-145px;
  border-radius:50%;
  border:28px solid rgba(255,255,255,.055);
  z-index:-1;
}
.hero:after{
  content:"✦";
  position:absolute;
  right:110px;
  top:24px;
  color:rgba(255,255,255,.15);
  font-size:34px;
  animation:floatStar 4s ease-in-out infinite;
}
.hero .hero-icon{
  width:66px;height:66px;border-radius:19px;
  display:grid;place-items:center;
  border:1px solid rgba(255,255,255,.2);
  background:rgba(255,255,255,.10);
  font-size:1.8rem;
  animation:softFloat 4.5s ease-in-out infinite;
}
.hero h1{color:#fff!important;letter-spacing:-.045em}
.hero p{color:rgba(255,255,255,.76)!important}
@keyframes softFloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-4px) rotate(2deg)}}
@keyframes floatStar{0%,100%{transform:translateY(0) rotate(0);opacity:.45}50%{transform:translateY(-7px) rotate(12deg);opacity:.8}}

.stat{
  height:100%;
  padding:17px!important;
  background:var(--a-card)!important;
  border:1px solid var(--a-line)!important;
  border-radius:17px!important;
  box-shadow:var(--a-shadow)!important;
  transition:.18s ease;
}
.stat:hover{
  transform:translateY(-2px);
  box-shadow:var(--a-shadow-hover)!important;
  border-color:color-mix(in srgb,var(--a-accent) 20%,var(--a-line))!important;
}
.stat-icon{
  width:39px;height:39px;border-radius:12px;
  display:grid;place-items:center;
  background:var(--a-soft);
  color:var(--a-accent);
  margin-bottom:11px;
}
.stat .muted{color:var(--a-muted)!important;font-size:.68rem}
.stat strong{font-size:1.55rem;color:var(--a-text)!important;letter-spacing:-.03em}

.cardx{
  background:var(--a-card)!important;
  color:var(--a-text)!important;
  border:1px solid var(--a-line)!important;
  border-radius:20px!important;
  box-shadow:var(--a-shadow)!important;
  transition:.18s ease;
}
.cardx:hover{box-shadow:var(--a-shadow-hover)!important}
.cardx h2{color:var(--a-text)!important;letter-spacing:-.025em}
.muted{color:var(--a-muted)!important}
.item{
  height:100%;
  padding:17px!important;
  background:var(--a-card-2)!important;
  color:var(--a-text)!important;
  border:1px solid var(--a-line)!important;
  border-radius:17px!important;
  transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .25s;
}
.item:hover{
  transform:translateY(-3px);
  background:var(--a-card)!important;
  border-color:color-mix(in srgb,var(--a-accent) 22%,var(--a-line))!important;
  box-shadow:var(--a-shadow-hover);
}
.icon{
  width:52px!important;height:52px!important;
  border-radius:15px!important;
  display:grid!important;place-items:center!important;
  background:var(--a-soft)!important;
  color:var(--a-accent)!important;
  border:1px solid color-mix(in srgb,var(--a-accent) 10%,var(--a-line))!important;
  font-size:1.25rem!important;
}
.item h3{color:var(--a-text)!important}
.item .badge{
  font-size:.58rem!important;
  font-weight:800!important;
  padding:.38rem .56rem!important;
}
.badge-soft{
  background:var(--a-soft)!important;
  color:var(--a-accent)!important;
  border-color:color-mix(in srgb,var(--a-accent) 12%,var(--a-line))!important;
}
.btn{
  border-radius:11px!important;
  font-weight:800!important;
  transition:.18s ease;
}
.btn-outline-primary{
  color:var(--a-accent)!important;
  border-color:color-mix(in srgb,var(--a-accent) 30%,var(--a-line))!important;
  background:var(--a-card)!important;
}
.btn-outline-primary:hover{
  background:var(--a-soft)!important;
  border-color:var(--a-accent)!important;
  transform:translateY(-1px);
}
.btn-primary{
  background:var(--a-accent)!important;
  border-color:var(--a-accent)!important;
  color:#fff!important;
}
.text-primary{color:var(--a-accent)!important}

/* Personalizador: mismo sistema de persistencia del dashboard del estudiante */
.sd-theme-toggle{
  position:fixed;
  right:20px;
  bottom:20px;
  z-index:1045;
  width:48px;height:48px;
  border:0;
  border-radius:16px;
  display:grid;place-items:center;
  color:#fff;
  background:linear-gradient(145deg,var(--a-accent),var(--a-accent-2));
  box-shadow:0 12px 28px color-mix(in srgb,var(--a-accent) 25%,transparent);
  cursor:pointer;
  transition:transform .2s ease,box-shadow .2s ease;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(4deg)}
.sd-theme-panel{
  position:fixed;
  right:20px;
  bottom:78px;
  z-index:1046;
  width:285px;
  padding:15px;
  background:var(--a-card);
  color:var(--a-text);
  border:1px solid var(--a-line);
  border-radius:18px;
  box-shadow:0 20px 50px rgba(20,35,60,.15);
  transform:translateY(8px) scale(.98);
  opacity:0;
  pointer-events:none;
  transition:.2s ease;
}
.sd-theme-panel.open{
  transform:none;
  opacity:1;
  pointer-events:auto;
}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--a-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
  border:1px solid var(--a-line);
  background:var(--a-card);
  border-radius:12px;
  padding:9px;
  text-align:left;
  cursor:pointer;
  color:var(--a-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
  border-color:color-mix(in srgb,var(--a-accent) 45%,var(--a-line));
  background:var(--a-soft);
}
.sd-theme-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.sd-theme-option strong{display:block;font-size:.66rem}
.sd-theme-option small{font-size:.57rem;color:var(--a-muted)}
.sd-theme-mode{
  width:100%;
  margin-top:8px;
  border:1px solid var(--a-line);
  background:var(--a-card);
  border-radius:12px;
  padding:8px;
  color:var(--a-text);
  font-size:.68rem;
  font-weight:750;
  cursor:pointer;
  text-align:left;
}

/* Temas exactamente compatibles con el dashboard del estudiante */
body.sd-accent-orange{--a-accent:#f97316;--a-accent-2:#ea580c;--a-soft:#fff7ed}
body.sd-accent-purple{--a-accent:#8b5cf6;--a-accent-2:#7c3aed;--a-soft:#f5f3ff}
body.sd-accent-green{--a-accent:#10b981;--a-accent-2:#059669;--a-soft:#ecfdf5}

body.sd-dark{
  --a-bg:#111827;
  --a-card:#182235;
  --a-card-2:#151f31;
  --a-text:#edf2f7;
  --a-muted:#9aa7ba;
  --a-line:#2a3549;
  background:
    radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--a-accent) 13%,transparent),transparent 28rem),
    var(--a-bg)!important;
}
body.sd-dark .top{
  background:rgba(17,24,39,.9)!important;
  border-color:var(--a-line)!important;
}
body.sd-dark .admin-brand{color:var(--a-text)!important}
body.sd-dark .admin-nav-btn{
  background:var(--a-card)!important;
  color:#aeb9ca!important;
  border-color:var(--a-line)!important;
}
body.sd-dark .admin-nav-btn:hover{background:#202c41!important;color:#c4b5fd!important}
body.sd-dark .cardx,
body.sd-dark .stat,
body.sd-dark .item{
  background:var(--a-card)!important;
  color:var(--a-text)!important;
  border-color:var(--a-line)!important;
}
body.sd-dark .item{background:var(--a-card-2)!important}
body.sd-dark .item:hover{background:var(--a-card)!important}
body.sd-dark .icon,
body.sd-dark .stat-icon{background:#202c41!important}
body.sd-dark .btn-outline-primary{
  background:var(--a-card)!important;
  border-color:#3b4960!important;
}
body.sd-dark .btn-outline-primary:hover{background:#202c41!important}
body.sd-dark .sd-theme-panel,
body.sd-dark .sd-theme-option,
body.sd-dark .sd-theme-mode{
  background:var(--a-card);
  color:var(--a-text);
}
body.sd-dark .dropdown-menu{
  background:var(--a-card)!important;
  border-color:var(--a-line)!important;
}
body.sd-dark .dropdown-item{color:var(--a-text)!important}
body.sd-dark .dropdown-item:hover{background:#202c41!important}

@media(max-width:767px){
  main.container{padding-top:20px!important}
  .hero{padding:22px!important}
  .hero .hero-icon{width:56px;height:56px;font-size:1.5rem}
  .sd-theme-toggle{right:14px;bottom:14px}
  .sd-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation:none!important;transition:none!important}
}
</style>
</head><body class="admin-coleccionables"><nav class="navbar top">
<div class="container py-2">
  <a class="admin-brand" href="<?=e(urlAplicacion('/admin/dashboard.php'))?>">
    <span class="admin-logo"><i class="bi bi-stars"></i></span>
    <span>Studia360</span>
    <span class="admin-badge">Admin</span>
  </a>
  <div class="d-flex gap-2 align-items-center">
    <a class="btn admin-nav-btn d-none d-sm-inline-flex" href="<?=e(urlAplicacion('/admin/gamificacion/index.php'))?>">
      <i class="bi bi-sliders2 me-1"></i> Configurar
    </a>
    <a class="btn admin-nav-btn primary" href="<?=e(urlAplicacion('/admin/dashboard.php'))?>">
      <i class="bi bi-grid-1x2 me-1"></i> Dashboard
    </a>
  </div>
</div>
</nav>
<main class="container py-4 py-lg-5"><section class="hero mb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-4"><div><div class="small text-uppercase fw-bold opacity-75 mb-2">Personalización y recompensas</div><h1 class="h2 fw-bold mb-2">Coleccionables</h1><p class="mb-0 text-white-50">Consulta el catálogo de avatares e insignias y observa cómo se están utilizando entre los estudiantes.</p></div><div class="hero-icon"><i class="bi bi-stars"></i></div></div></section>
<div class="row g-3 mb-4"><div class="col-6 col-lg-3"><div class="stat"><div class="stat-icon"><i class="bi bi-person-badge"></i></div><div class="muted small">Avatares activos</div><strong><?=$activosA?></strong></div></div><div class="col-6 col-lg-3"><div class="stat"><div class="stat-icon"><i class="bi bi-award"></i></div><div class="muted small">Insignias activas</div><strong><?=$activosI?></strong></div></div><div class="col-6 col-lg-3"><div class="stat"><div class="stat-icon"><i class="bi bi-people"></i></div><div class="muted small">Estudiantes</div><strong><?=$usuarios?></strong></div></div><div class="col-6 col-lg-3"><div class="stat"><div class="stat-icon"><i class="bi bi-trophy"></i></div><div class="muted small">Insignias obtenidas</div><strong><?=$desbloqueos?></strong></div></div></div>
<section class="cardx p-4 mb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Avatares</h2><p class="muted small mb-0">Elementos de personalización desbloqueables según los puntos acumulados.</p></div><a href="<?=e(urlAplicacion('/admin/gamificacion/index.php'))?>#avatares" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Administrar avatares</a></div><div class="row g-3"><?php foreach($avatares as $a):?><div class="col-md-6 col-xl-4"><article class="item"><div class="d-flex gap-3 align-items-center mb-3"><div class="icon"><i class="bi bi-person-badge-fill"></i></div><div><h3 class="h6 fw-bold mb-1"><?=e($a['nombre'])?></h3><span class="badge rounded-pill <?=$a['estado']==='Activo'?'text-bg-success':'text-bg-secondary'?>"><?=e($a['estado'])?></span></div></div><div class="d-flex justify-content-between small mb-1"><span class="muted">Desbloqueo</span><strong><?=number_format((int)$a['puntos_requeridos'])?> XP</strong></div><div class="d-flex justify-content-between small"><span class="muted">Usuarios que lo tienen seleccionado</span><strong><?=number_format((int)$a['usuarios_con_avatar'])?></strong></div></article></div><?php endforeach;?><?php if(!$avatares):?><div class="col-12 text-center muted py-4">No hay avatares configurados.</div><?php endif;?></div></section>
<section class="cardx p-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Insignias</h2><p class="muted small mb-0">Recompensas que se otorgan automáticamente cuando el estudiante cumple su criterio.</p></div><a href="<?=e(urlAplicacion('/admin/gamificacion/index.php'))?>#insignias" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Administrar insignias</a></div><div class="row g-3"><?php foreach($insignias as $i):?><div class="col-md-6 col-xl-4"><article class="item"><div class="d-flex gap-3 align-items-center mb-3"><div class="icon"><i class="bi bi-award-fill"></i></div><div><h3 class="h6 fw-bold mb-1"><?=e($i['nombre'])?></h3><span class="badge rounded-pill <?=$i['estado']==='Activa'?'text-bg-success':'text-bg-secondary'?>"><?=e($i['estado'])?></span></div></div><p class="small muted mb-3"><?=e($i['descripcion'] ?: 'Sin descripción configurada.')?></p><div class="d-flex justify-content-between small mb-1"><span class="muted">Criterio</span><strong><?=e($i['criterio'] ?: 'Sin criterio')?></strong></div><div class="d-flex justify-content-between small"><span class="muted">Estudiantes que la obtuvieron</span><strong><?=number_format((int)$i['usuarios_con_insignia'])?></strong></div></article></div><?php endforeach;?><?php if(!$insignias):?><div class="col-12"><div class="text-center py-5 muted"><i class="bi bi-award display-5 d-block mb-2"></i><strong>No hay insignias creadas todavía.</strong><p class="mb-0">Puedes configurarlas desde el módulo de gamificación.</p></div></div><?php endif;?></div></section>
</main><button id="sdThemeToggle" class="sd-theme-toggle" type="button" aria-label="Cambiar apariencia" title="Personalizar apariencia">
  <i class="bi bi-palette2"></i>
</button>
<div id="sdThemePanel" class="sd-theme-panel" aria-label="Personalizar apariencia">
  <div class="sd-theme-title">Personaliza Studia360</div>
  <div class="sd-theme-sub">Elige el color que te guste y decide si prefieres fondo claro u oscuro.</div>
  <div class="sd-theme-row">
    <button class="sd-theme-option" data-theme="blue" onclick="studiaTheme('blue')" type="button">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div>
      <strong>Azul</strong><small>Clásico</small>
    </button>
    <button class="sd-theme-option" data-theme="orange" onclick="studiaTheme('orange')" type="button">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div>
      <strong>Naranja</strong><small>Enérgico</small>
    </button>
    <button class="sd-theme-option" data-theme="purple" onclick="studiaTheme('purple')" type="button">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div>
      <strong>Violeta</strong><small>Creativo</small>
    </button>
    <button class="sd-theme-option" data-theme="green" onclick="studiaTheme('green')" type="button">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div>
      <strong>Verde</strong><small>Calma</small>
    </button>
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
    Object.values(themes).forEach(t=>{
      if(t.className) body.classList.remove(t.className);
    });
    if(themes[theme]?.className) body.classList.add(themes[theme].className);
    body.classList.toggle("sd-dark",mode==="dark");

    document.querySelectorAll(".sd-theme-option").forEach(el=>{
      el.classList.toggle("active",el.dataset.theme===theme);
    });

    const modeBtn=document.getElementById("sdThemeMode");
    if(modeBtn){
      modeBtn.innerHTML=(mode==="dark"
        ? "<i class='bi bi-moon-stars me-2'></i>Modo oscuro"
        : "<i class='bi bi-sun me-2'></i>Modo claro");
    }
  }

  let saved={theme:"blue",mode:"light"};
  try{
    saved=Object.assign(saved,JSON.parse(localStorage.getItem(key)||"{}"));
  }catch(e){}

  applyTheme(saved.theme,saved.mode);

  window.studiaTheme=function(theme){
    if(!themes[theme]) return;
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

  toggle?.addEventListener("click",()=>{
    panel?.classList.toggle("open");
  });

  document.addEventListener("click",e=>{
    if(panel && panel.classList.contains("open") &&
       !panel.contains(e.target) && !toggle.contains(e.target)){
      panel.classList.remove("open");
    }
  });
})();
</script>

</body></html>

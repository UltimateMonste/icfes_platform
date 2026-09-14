<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($idUsuario <= 0) redireccionarLogin('Tu sesión no es válida.');
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$notificaciones = [];

// Respuestas de solicitudes de recuperación de contraseña.
try {
    $st = $conexion->prepare("SELECT estado, mensaje_admin, fecha_solicitud, fecha_gestion FROM solicitudes_recuperacion WHERE id_usuario=? AND mensaje_admin IS NOT NULL AND mensaje_admin<>'' ORDER BY COALESCE(fecha_gestion,fecha_solicitud) DESC");
    $st->execute([$idUsuario]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $notificaciones[] = [
            'tipo' => 'Seguridad',
            'icono' => 'bi-shield-check',
            'titulo' => $n['estado'] === 'Gestionada' ? 'Solicitud de contraseña gestionada' : 'Actualización de seguridad',
            'mensaje' => $n['mensaje_admin'],
            'fecha' => $n['fecha_gestion'] ?: $n['fecha_solicitud'],
            'color' => 'success'
        ];
    }
} catch (Throwable $e) {}

// Respuestas del administrador a sugerencias, quejas, errores o felicitaciones.
try {
    $st = $conexion->prepare("SELECT asunto, tipo, respuesta, estado, fecha FROM sugerencias WHERE id_usuario=? AND respuesta IS NOT NULL AND respuesta<>'' ORDER BY fecha DESC, id_sugerencia DESC");
    $st->execute([$idUsuario]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $notificaciones[] = [
            'tipo' => 'Comunidad',
            'icono' => $n['tipo'] === 'Queja' ? 'bi-chat-square-text' : ($n['tipo'] === 'Error' ? 'bi-bug' : 'bi-chat-left-heart'),
            'titulo' => 'Respuesta a: ' . $n['asunto'],
            'mensaje' => $n['respuesta'],
            'fecha' => $n['fecha'],
            'color' => 'primary'
        ];
    }
} catch (Throwable $e) {}

usort($notificaciones, fn($a,$b) => strtotime((string)$b['fecha']) <=> strtotime((string)$a['fecha']));
$notificaciones = array_slice($notificaciones, 0, 30);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notificaciones | Studia360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
  --sd-accent:#8b5cf6;
  --sd-accent-2:#7c3aed;
  --sd-accent-soft:#f5f3ff;
  --sd-bg:#f6f7fb;
  --sd-card:#fff;
  --sd-text:#1f2937;
  --sd-muted:#728096;
  --sd-line:#e5e9f1;
  --sd-shadow:0 12px 34px rgba(31,55,86,.06);
  --sd-shadow-hover:0 18px 42px rgba(31,55,86,.10);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body.sd-page{
  min-height:100vh;
  margin:0;
  color:var(--sd-text);
  background:
    radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--sd-accent) 9%,transparent),transparent 30rem),
    var(--sd-bg);
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  transition:background .25s,color .25s;
}

/* Navegación */
.sd-navbar{
  position:sticky;top:0;z-index:1030;
  min-height:68px;
  background:rgba(255,255,255,.90)!important;
  border-bottom:1px solid var(--sd-line);
  backdrop-filter:blur(16px);
  box-shadow:0 5px 24px rgba(31,55,86,.035);
}
.sd-brand{
  display:flex;align-items:center;gap:9px;
  color:var(--sd-text)!important;text-decoration:none;
  font-weight:850;letter-spacing:-.035em;
}
.sd-logo{
  width:38px;height:38px;border-radius:12px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 8px 18px color-mix(in srgb,var(--sd-accent) 22%,transparent);
}
.sd-nav-actions{display:flex;align-items:center;gap:7px}
.sd-nav-btn{
  height:38px;border:1px solid var(--sd-line)!important;
  background:var(--sd-card)!important;color:#657186!important;
  border-radius:11px!important;font-size:.73rem!important;font-weight:750!important;
}
.sd-nav-btn:hover{background:var(--sd-accent-soft)!important;color:var(--sd-accent)!important}

/* Contenedor */
.sd-shell{
  width:min(1120px,calc(100% - 30px));
  margin:auto;
  padding:28px 0 60px;
}
.sd-fade{animation:sdFade .38s ease both}
@keyframes sdFade{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}

/* Encabezado */
.sd-notifications-hero{
  position:relative;overflow:hidden;
  border-radius:25px;padding:28px 30px;
  color:#fff;
  background:
    radial-gradient(circle at 91% 10%,rgba(255,255,255,.18),transparent 17rem),
    linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 18px 44px color-mix(in srgb,var(--sd-accent) 18%,transparent);
}
.sd-notifications-hero:before{
  content:"";position:absolute;width:180px;height:180px;
  right:-70px;bottom:-105px;border:26px solid rgba(255,255,255,.055);
  border-radius:50%;
}
.sd-hero-icon{
  width:60px;height:60px;border-radius:18px;
  display:grid;place-items:center;
  background:rgba(255,255,255,.13);
  border:1px solid rgba(255,255,255,.20);
  font-size:1.45rem;
}
.sd-hero-kicker{
  font-size:.65rem;font-weight:850;text-transform:uppercase;
  letter-spacing:.09em;opacity:.72;margin-bottom:3px;
}
.sd-hero-title{
  font-size:clamp(1.65rem,3vw,2.05rem);
  font-weight:850;letter-spacing:-.045em;margin:0 0 5px;
}
.sd-hero-text{margin:0;font-size:.82rem;opacity:.82;max-width:680px}

/* Tarjeta principal */
.sd-card{
  background:var(--sd-card);
  border:1px solid var(--sd-line);
  border-radius:21px;
  box-shadow:var(--sd-shadow);
}
.sd-card-head{
  display:flex;justify-content:space-between;align-items:center;
  gap:15px;padding:20px 22px 15px;
}
.sd-section-title{
  display:flex;align-items:center;gap:9px;
  font-size:1rem;font-weight:850;letter-spacing:-.02em;
}
.sd-section-title i{color:var(--sd-accent)}
.sd-section-sub{font-size:.72rem;color:var(--sd-muted);margin-top:3px}
.sd-count{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 9px;border-radius:999px;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-size:.61rem;font-weight:850;white-space:nowrap;
}

/* Notificaciones */
.sd-notification-list{padding:0 22px 22px}
.sd-notification{
  display:flex;gap:14px;
  padding:16px;
  border:1px solid var(--sd-line);
  border-radius:17px;
  background:var(--sd-card);
  transition:transform .18s,box-shadow .18s,border-color .18s;
}
.sd-notification:hover{
  transform:translateY(-2px);
  border-color:color-mix(in srgb,var(--sd-accent) 20%,var(--sd-line));
  box-shadow:var(--sd-shadow-hover);
}
.sd-notification-icon{
  width:44px;height:44px;min-width:44px;
  border-radius:13px;display:grid;place-items:center;
  background:var(--sd-accent-soft);
  border:1px solid color-mix(in srgb,var(--sd-accent) 12%,var(--sd-line));
  color:var(--sd-accent);
  font-size:1.05rem;
}
.sd-notification.success .sd-notification-icon{
  background:#ecfdf5;border-color:#d1fae5;color:#059669;
}
.sd-notification-top{
  display:flex;justify-content:space-between;
  align-items:flex-start;gap:10px;flex-wrap:wrap;
}
.sd-tag{
  display:inline-flex;align-items:center;
  padding:4px 7px;border-radius:999px;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-size:.56rem;font-weight:850;
}
.sd-notification.success .sd-tag{
  background:#ecfdf5;color:#059669;
}
.sd-notification-title{
  color:var(--sd-text);font-size:.84rem;
  font-weight:800;margin:4px 0 5px;
}
.sd-notification-message{
  color:var(--sd-muted);font-size:.75rem;
  line-height:1.55;white-space:pre-line;margin:0;
}
.sd-notification-time{
  color:var(--sd-muted);font-size:.61rem;white-space:nowrap;
}

/* Vacío */
.sd-empty{
  padding:65px 22px;
  text-align:center;color:var(--sd-muted);
}
.sd-empty-icon{
  width:62px;height:62px;margin:0 auto 14px;
  border-radius:18px;display:grid;place-items:center;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-size:1.45rem;
}
.sd-empty h3{font-size:.95rem;color:var(--sd-text);font-weight:850;margin-bottom:5px}
.sd-empty p{font-size:.72rem;margin-bottom:18px}

/* Botones */
.sd-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  min-height:38px;padding:8px 13px;border-radius:11px;
  font-size:.7rem;font-weight:800;text-decoration:none;
}
.sd-btn-outline{
  color:var(--sd-accent);background:var(--sd-card);
  border:1px solid color-mix(in srgb,var(--sd-accent) 25%,var(--sd-line));
}
.sd-btn-outline:hover{
  color:var(--sd-accent);background:var(--sd-accent-soft);
}

/* Personalizador */
.sd-theme-toggle{
  position:fixed;right:20px;bottom:20px;z-index:1045;
  width:48px;height:48px;border:0;border-radius:16px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 12px 28px color-mix(in srgb,var(--sd-accent) 25%,transparent);
  cursor:pointer;transition:.2s;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(4deg)}
.sd-theme-panel{
  position:fixed;right:20px;bottom:78px;z-index:1046;
  width:285px;padding:15px;
  background:var(--sd-card);border:1px solid var(--sd-line);
  border-radius:18px;box-shadow:0 20px 50px rgba(20,35,60,.15);
  transform:translateY(8px) scale(.98);opacity:0;
  pointer-events:none;transition:.2s;
}
.sd-theme-panel.open{transform:none;opacity:1;pointer-events:auto}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--sd-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
  border:1px solid var(--sd-line);background:var(--sd-card);
  border-radius:12px;padding:8px;text-align:left;
  cursor:pointer;color:var(--sd-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,var(--sd-line));
  background:var(--sd-accent-soft);
}
.sd-theme-swatch{height:22px;border-radius:7px;margin-bottom:5px}
.sd-theme-option strong{display:block;font-size:.64rem}
.sd-theme-option small{font-size:.55rem;color:var(--sd-muted)}
.sd-theme-mode{
  width:100%;margin-top:8px;border:1px solid var(--sd-line);
  background:var(--sd-card);color:var(--sd-text);
  border-radius:12px;padding:8px;text-align:left;
  font-size:.67rem;font-weight:750;cursor:pointer;
}

/* Oscuro */
body.sd-dark{
  --sd-bg:#0f1727;
  --sd-card:#182235;
  --sd-text:#edf2f7;
  --sd-muted:#9aa7ba;
  --sd-line:#2a3549;
}
body.sd-dark .sd-navbar{background:rgba(15,23,39,.90)!important}
body.sd-dark .sd-nav-btn{background:#182235!important;color:#aab5c5!important}
body.sd-dark .sd-card{box-shadow:0 14px 34px rgba(0,0,0,.12)}
body.sd-dark .sd-notification{background:#182235}
body.sd-dark .sd-notification-icon{
  background:#0e1728;border-color:#2c3a51;color:#a78bfa;
}
body.sd-dark .sd-notification.success .sd-notification-icon{
  background:#10251e;border-color:#234638;color:#6ee7b7;
}
body.sd-dark .sd-empty-icon{background:#0e1728}
body.sd-dark .sd-theme-panel,
body.sd-dark .sd-theme-option,
body.sd-dark .sd-theme-mode{background:#182235;color:#edf2f7}
body.sd-dark .sd-theme-option:hover,
body.sd-dark .sd-theme-option.active{background:#202d42}
body.sd-dark .sd-btn-outline{background:#182235}

/* Acentos */
body.sd-accent-blue{--sd-accent:#2563eb;--sd-accent-2:#4f46e5;--sd-accent-soft:#eff6ff}
body.sd-accent-orange{--sd-accent:#f97316;--sd-accent-2:#ea580c;--sd-accent-soft:#fff7ed}
body.sd-accent-purple{--sd-accent:#8b5cf6;--sd-accent-2:#7c3aed;--sd-accent-soft:#f5f3ff}
body.sd-accent-green{--sd-accent:#10b981;--sd-accent-2:#059669;--sd-accent-soft:#ecfdf5}

@media(max-width:700px){
  .sd-shell{width:min(100% - 20px,1120px);padding-top:18px}
  .sd-notifications-hero{padding:21px}
  .sd-hero-icon{width:52px;height:52px}
  .sd-card-head{padding:17px 15px 13px}
  .sd-notification-list{padding:0 15px 15px}
  .sd-notification{padding:13px}
  .sd-notification-time{width:100%}
  .sd-theme-toggle{right:14px;bottom:14px}
  .sd-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation:none!important;transition:none!important}
}
</style>
</head>

<body class="sd-page">

<nav class="navbar sd-navbar">
  <div class="container-fluid px-3 px-lg-4">
    <a href="dashboard.php" class="sd-brand">
      <span class="sd-logo"><i class="bi bi-stars"></i></span>
      <span>Studia360</span>
    </a>
    <div class="sd-nav-actions">
      <a href="dashboard.php" class="btn sd-nav-btn">
        <i class="bi bi-house me-1"></i>
        <span class="d-none d-sm-inline">Inicio</span>
      </a>
      <a href="sugerencias.php" class="btn sd-nav-btn">
        <i class="bi bi-chat-left-heart me-1"></i>
        <span class="d-none d-sm-inline">Buzón</span>
      </a>
      <a href="perfil.php" class="btn sd-nav-btn">
        <i class="bi bi-person me-1"></i>
        <span class="d-none d-sm-inline">Perfil</span>
      </a>
      <a href="../cerrar_sesion.php" class="btn sd-nav-btn" title="Cerrar sesión">
        <i class="bi bi-box-arrow-right"></i>
        <span class="d-none d-sm-inline ms-1">Salir</span>
      </a>
    </div>
  </div>
</nav>

<main class="sd-shell sd-fade">

  <section class="sd-notifications-hero mb-4">
    <div class="d-flex align-items-center gap-3 position-relative" style="z-index:1">
      <div class="sd-hero-icon"><i class="bi bi-bell-fill"></i></div>
      <div>
        <div class="sd-hero-kicker">Centro de avisos</div>
        <h1 class="sd-hero-title">Notificaciones</h1>
        <p class="sd-hero-text">Aquí aparecerán las respuestas y mensajes que el administrador tenga para ti.</p>
      </div>
    </div>
  </section>

  <section class="sd-card">
    <div class="sd-card-head">
      <div>
        <div class="sd-section-title">
          <i class="bi bi-inbox-fill"></i>
          <span>Tus avisos</span>
        </div>
        <div class="sd-section-sub">Respuestas de seguridad y comunicación.</div>
      </div>
      <span class="sd-count">
        <i class="bi bi-bell"></i>
        <?=count($notificaciones)?> <?=count($notificaciones)===1?'aviso':'avisos'?>
      </span>
    </div>

    <?php if (!$notificaciones): ?>
      <div class="sd-empty">
        <div class="sd-empty-icon"><i class="bi bi-bell-slash"></i></div>
        <h3>No tienes notificaciones nuevas</h3>
        <p>Cuando el administrador responda una solicitud o mensaje, aparecerá aquí.</p>
        <a href="sugerencias.php" class="sd-btn sd-btn-outline">
          <i class="bi bi-chat-left-heart"></i> Ir al buzón
        </a>
      </div>
    <?php else: ?>
      <div class="sd-notification-list d-grid gap-2">
      <?php foreach ($notificaciones as $n): ?>
        <article class="sd-notification <?=h($n['color'])?>">
          <div class="sd-notification-icon">
            <i class="bi <?=h($n['icono'])?>"></i>
          </div>

          <div class="flex-grow-1 min-w-0">
            <div class="sd-notification-top">
              <div>
                <span class="sd-tag"><?=h($n['tipo'])?></span>
                <h2 class="sd-notification-title"><?=h($n['titulo'])?></h2>
              </div>
              <time class="sd-notification-time">
                <?=h(date('d/m/Y H:i',strtotime((string)$n['fecha'])))?>
              </time>
            </div>
            <p class="sd-notification-message"><?=h($n['mensaje'])?></p>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<button id="sdThemeToggle" class="sd-theme-toggle" type="button"
        aria-label="Cambiar apariencia" title="Personalizar apariencia">
  <i class="bi bi-palette2"></i>
</button>

<div id="sdThemePanel" class="sd-theme-panel" aria-label="Personalizar apariencia">
  <div class="sd-theme-title">Personaliza Studia360</div>
  <div class="sd-theme-sub">Elige un color y decide entre modo claro u oscuro.</div>

  <div class="sd-theme-row">
    <button class="sd-theme-option" data-theme="blue" onclick="studiaTheme('blue')">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div>
      <strong>Azul</strong><small>Clásico</small>
    </button>
    <button class="sd-theme-option" data-theme="orange" onclick="studiaTheme('orange')">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div>
      <strong>Naranja</strong><small>Enérgico</small>
    </button>
    <button class="sd-theme-option" data-theme="purple" onclick="studiaTheme('purple')">
      <div class="sd-theme-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div>
      <strong>Violeta</strong><small>Creativo</small>
    </button>
    <button class="sd-theme-option" data-theme="green" onclick="studiaTheme('green')">
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
    blue:"sd-accent-blue",
    orange:"sd-accent-orange",
    purple:"sd-accent-purple",
    green:"sd-accent-green"
  };

  let saved={theme:"purple",mode:"dark"};
  try{
    saved=Object.assign(saved,JSON.parse(localStorage.getItem(key)||"{}"));
  }catch(e){}

  function applyTheme(){
    Object.values(themes).forEach(c=>body.classList.remove(c));
    body.classList.add(themes[saved.theme] || themes.purple);
    body.classList.toggle("sd-dark",saved.mode==="dark");

    document.querySelectorAll(".sd-theme-option").forEach(el=>{
      el.classList.toggle("active",el.dataset.theme===saved.theme);
    });

    const mode=document.getElementById("sdThemeMode");
    if(mode){
      mode.innerHTML=saved.mode==="dark"
        ? "<i class='bi bi-moon-stars me-2'></i>Modo oscuro"
        : "<i class='bi bi-sun me-2'></i>Modo claro";
    }
  }

  window.studiaTheme=function(theme){
    if(!themes[theme]) return;
    saved.theme=theme;
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme();
  };

  window.studiaThemeMode=function(){
    saved.mode=saved.mode==="dark"?"light":"dark";
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme();
  };

  applyTheme();

  const toggle=document.getElementById("sdThemeToggle");
  const panel=document.getElementById("sdThemePanel");

  toggle?.addEventListener("click",function(){
    panel?.classList.toggle("open");
  });

  document.addEventListener("click",function(e){
    if(panel && panel.classList.contains("open") &&
       !panel.contains(e.target) && !toggle.contains(e.target)){
      panel.classList.remove("open");
    }
  });
})();
</script>
</body>
</html>

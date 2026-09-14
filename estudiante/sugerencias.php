<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';

exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

if ($idUsuario <= 0) {
    redireccionarLogin('Tu sesión no es válida.');
}

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_sugerencias'])) {
    $_SESSION['csrf_sugerencias'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_sugerencias'];
$alerta = null;
$tipoAlerta = 'success';

$tiposPermitidos = ['Sugerencia', 'Queja', 'Felicitacion', 'Error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'enviar') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $alerta = 'La solicitud expiró. Recarga la página e inténtalo nuevamente.';
        $tipoAlerta = 'danger';
    } else {
        $asunto = trim((string)($_POST['asunto'] ?? ''));
        $mensaje = trim((string)($_POST['mensaje'] ?? ''));
        $tipo = trim((string)($_POST['tipo'] ?? 'Sugerencia'));

        if (!in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'Sugerencia';
        }

        if ($asunto === '' || mb_strlen($asunto) < 4) {
            $alerta = 'Escribe un asunto de al menos 4 caracteres.';
            $tipoAlerta = 'danger';
        } elseif ($mensaje === '' || mb_strlen($mensaje) < 10) {
            $alerta = 'Cuéntanos un poco más. El mensaje debe tener al menos 10 caracteres.';
            $tipoAlerta = 'danger';
        } else {
            try {
                $stmt = $conexion->prepare(
                    'INSERT INTO sugerencias (id_usuario, asunto, mensaje, tipo, estado)
                     VALUES (:id_usuario, :asunto, :mensaje, :tipo, "Pendiente")'
                );

                $stmt->execute([
                    ':id_usuario' => $idUsuario,
                    ':asunto' => mb_substr($asunto, 0, 150),
                    ':mensaje' => $mensaje,
                    ':tipo' => $tipo
                ]);

                $alerta = 'Tu mensaje fue enviado correctamente. El administrador podrá revisarlo y responderte.';
                $tipoAlerta = 'success';
            } catch (PDOException $e) {
                $alerta = 'No fue posible enviar el mensaje en este momento.';
                $tipoAlerta = 'danger';
            }
        }
    }
}

$solicitudes = [];

try {
    $stmt = $conexion->prepare(
        'SELECT id_sugerencia, asunto, mensaje, tipo, estado, respuesta, fecha
         FROM sugerencias
         WHERE id_usuario = :id_usuario
         ORDER BY fecha DESC, id_sugerencia DESC'
    );
    $stmt->execute([':id_usuario' => $idUsuario]);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $solicitudes = [];
}

$resumen = [
    'total' => count($solicitudes),
    'pendientes' => 0,
    'respondidas' => 0,
    'cerradas' => 0
];

foreach ($solicitudes as $item) {
    if ($item['estado'] === 'Pendiente') $resumen['pendientes']++;
    if ($item['estado'] === 'Respondida') $resumen['respondidas']++;
    if ($item['estado'] === 'Cerrada') $resumen['cerradas']++;
}

function claseTipo(string $tipo): array
{
    return match ($tipo) {
        'Queja' => ['bi-chat-square-text', 'danger'],
        'Felicitacion' => ['bi-heart-fill', 'success'],
        'Error' => ['bi-bug-fill', 'warning'],
        default => ['bi-lightbulb-fill', 'primary']
    };
}

function claseEstado(string $estado): array
{
    return match ($estado) {
        'Respondida' => ['Respondida', 'success', 'bi-reply-fill'],
        'Cerrada' => ['Cerrada', 'secondary', 'bi-check2-circle'],
        default => ['Pendiente', 'warning', 'bi-hourglass-split']
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buzón de sugerencias | Studia360</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root{
            --blue:#2467c5;
            --dark:#173f80;
            --soft:#eef5ff;
            --text:#26364a;
            --muted:#718096;
            --border:#dce5f0;
        }

        body{
            min-height:100vh;
            color:var(--text);
            background:
                radial-gradient(circle at top right,rgba(58,124,220,.12),transparent 30%),
                #f4f7fb;
        }

        .topbar{
            background:linear-gradient(110deg,var(--dark),var(--blue));
            box-shadow:0 5px 22px rgba(23,63,128,.18);
        }

        .hero{
            color:#fff;
            border-radius:25px;
            padding:2rem;
            background:
                radial-gradient(circle at 90% 15%,rgba(255,255,255,.17),transparent 24%),
                linear-gradient(120deg,#1f5fb7,#173f80);
            box-shadow:0 18px 45px rgba(27,69,130,.17);
        }

        .hero-icon{
            width:74px;height:74px;border-radius:22px;
            display:flex;align-items:center;justify-content:center;
            font-size:2rem;background:rgba(255,255,255,.14);
            border:1px solid rgba(255,255,255,.22);
        }

        .card-soft{
            background:rgba(255,255,255,.96);
            border:1px solid var(--border);
            border-radius:22px;
            box-shadow:0 10px 30px rgba(33,55,87,.06);
        }

        .section-card{padding:1.45rem;}

        .section-title{
            font-size:1.12rem;
            font-weight:800;
        }

        .section-title i{color:var(--blue);}

        .stat{
            padding:1rem;
            border:1px solid var(--border);
            border-radius:18px;
            background:linear-gradient(180deg,#fff,#f8fbff);
            height:100%;
        }

        .stat-icon{
            width:42px;height:42px;border-radius:13px;
            display:flex;align-items:center;justify-content:center;
            color:var(--blue);background:var(--soft);
        }

        .stat-number{font-size:1.45rem;font-weight:800;}

        .type-option{
            cursor:pointer;
            border:2px solid var(--border);
            border-radius:16px;
            padding:.8rem;
            text-align:center;
            transition:.2s ease;
            height:100%;
        }

        .type-option:hover{
            transform:translateY(-2px);
            border-color:#aac7f1;
        }

        .type-option input{display:none;}

        .type-option:has(input:checked){
            border-color:var(--blue);
            background:var(--soft);
            box-shadow:0 0 0 4px rgba(36,103,197,.08);
        }

        .type-icon{
            width:38px;height:38px;border-radius:12px;
            display:flex;align-items:center;justify-content:center;
            margin:0 auto .45rem;
            background:#f2f6fc;
            color:var(--blue);
        }

        .message-card{
            border:1px solid var(--border);
            border-radius:19px;
            overflow:hidden;
            background:#fff;
            transition:.2s ease;
        }

        .message-card:hover{
            box-shadow:0 12px 28px rgba(25,54,91,.08);
        }

        .message-main{padding:1.15rem 1.2rem;}

        .message-icon{
            width:45px;height:45px;border-radius:14px;
            display:flex;align-items:center;justify-content:center;
            font-size:1.2rem;
            flex:none;
        }

        .response-box{
            margin:0 1.2rem 1.2rem;
            padding:1rem;
            border-radius:15px;
            background:#edf8f1;
            border:1px solid #d1ead9;
        }

        .empty{
            text-align:center;
            padding:3rem 1.5rem;
            color:var(--muted);
        }

        textarea.form-control{
            min-height:155px;
            resize:vertical;
        }

        .form-control,.form-select{
            border-color:var(--border);
            border-radius:13px;
            padding:.72rem .85rem;
        }

        .form-control:focus,.form-select:focus{
            border-color:#7ca9e5;
            box-shadow:0 0 0 .25rem rgba(36,103,197,.10);
        }

        @media(max-width:767px){
            .hero{padding:1.4rem;}
        }
    </style>
<style id="studia360-common-style">
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

<style id="sd-icon-final-fix">
/* ===== Studia360 · Iconos: corrección definitiva =====
   Se fuerza el color tanto del contenedor como del glifo para que
   Bootstrap / temas anteriores no puedan dejarlos blancos. */
body.sd-page .stat-icon,
body.sd-page .type-icon,
body.sd-page .message-icon{
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    box-sizing:border-box !important;
    overflow:hidden !important;
    opacity:1 !important;
}

body.sd-page .stat-icon i,
body.sd-page .type-icon i,
body.sd-page .message-icon i{
    display:inline-block !important;
    opacity:1 !important;
    -webkit-text-fill-color:currentColor !important;
}

/* Estadísticas */
body.sd-page .stat-icon{
    background:var(--sd-card,#fff) !important;
    color:var(--sd-accent,#2563eb) !important;
    border:1px solid var(--sd-line,#e6ebf2) !important;
}
body.sd-page .stat-icon i{
    color:var(--sd-accent,#2563eb) !important;
    font-size:1.05rem !important;
}

/* Oscuro: caja oscura + icono claro/acento */
body.sd-dark .stat-icon{
    background:#101827 !important;
    border-color:#2b3950 !important;
    color:#c4b5fd !important;
}
body.sd-dark .stat-icon i{
    color:#c4b5fd !important;
}

/* Selector de tipo */
body.sd-page .type-icon{
    background:var(--sd-accent-soft,#eff6ff) !important;
    color:var(--sd-accent,#2563eb) !important;
    border:1px solid color-mix(in srgb,var(--sd-accent,#2563eb) 18%,var(--sd-line,#e6ebf2)) !important;
}
body.sd-page .type-icon i{
    color:var(--sd-accent,#2563eb) !important;
}
body.sd-dark .type-icon{
    background:#0c1423 !important;
    border-color:#2d3b53 !important;
    color:#a78bfa !important;
}
body.sd-dark .type-icon i{
    color:#a78bfa !important;
}

/* Iconos del historial */
body.sd-page .message-icon{
    background:var(--sd-accent-soft,#eff6ff) !important;
    color:var(--sd-accent,#2563eb) !important;
    border:1px solid color-mix(in srgb,var(--sd-accent,#2563eb) 15%,var(--sd-line,#e6ebf2)) !important;
}
body.sd-page .message-icon i{
    color:var(--sd-accent,#2563eb) !important;
}
body.sd-dark .message-icon,
body.sd-dark .message-icon.bg-primary-subtle,
body.sd-dark .message-icon.bg-danger-subtle,
body.sd-dark .message-icon.bg-warning-subtle,
body.sd-dark .message-icon.bg-success-subtle,
body.sd-dark .message-icon.bg-secondary-subtle{
    background:#0c1423 !important;
    border-color:#2d3b53 !important;
    color:#a78bfa !important;
}
body.sd-dark .message-icon i{
    color:#a78bfa !important;
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
      <div class="dropdown">
        <button class="btn sd-nav-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Explorar</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm p-2" style="border-radius:14px">
          <li><a class="dropdown-item rounded-2 small" href="dashboard.php"><i class="bi bi-house me-2"></i>Inicio</a></li>
          <li><a class="dropdown-item rounded-2 small" href="grado.php?grado=9"><i class="bi bi-book me-2"></i>Materias y temas</a></li>
          <li><a class="dropdown-item rounded-2 small" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
          <li><a class="dropdown-item rounded-2 small" href="sugerencias.php"><i class="bi bi-chat-left-heart me-2"></i>Quejas y recomendaciones</a></li>
          <li><a class="dropdown-item rounded-2 small" href="recuperacion.php"><i class="bi bi-key me-2"></i>Recuperar contraseña</a></li>
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


<main class="container py-4 py-lg-5">

    <section class="hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-auto">
                <div class="hero-icon"><i class="bi bi-envelope-paper-heart"></i></div>
            </div>
            <div class="col">
                <div class="text-uppercase small fw-semibold opacity-75 mb-1">Tu voz cuenta</div>
                <h1 class="h2 fw-bold mb-2">Buzón de sugerencias</h1>
                <p class="mb-0 text-white-50">
                    Comparte ideas, reporta problemas o envía un mensaje al equipo de Studia360.
                </p>
            </div>
        </div>
    </section>

    <?php if ($alerta !== null): ?>
        <div class="alert alert-<?= e($tipoAlerta) ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi <?= $tipoAlerta === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= e($alerta) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat">
                <div class="stat-icon mb-3"><i class="bi bi-inboxes"></i></div>
                <div class="stat-number"><?= $resumen['total'] ?></div>
                <div class="small text-muted">Mensajes enviados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat">
                <div class="stat-icon mb-3"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-number"><?= $resumen['pendientes'] ?></div>
                <div class="small text-muted">Pendientes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat">
                <div class="stat-icon mb-3"><i class="bi bi-reply"></i></div>
                <div class="stat-number"><?= $resumen['respondidas'] ?></div>
                <div class="small text-muted">Respondidas</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat">
                <div class="stat-icon mb-3"><i class="bi bi-check2-circle"></i></div>
                <div class="stat-number"><?= $resumen['cerradas'] ?></div>
                <div class="small text-muted">Cerradas</div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-5">
            <section class="card-soft section-card sticky-lg-top" style="top:1rem;">
                <div class="section-title mb-2">
                    <i class="bi bi-send-plus me-2"></i>
                    Enviar un mensaje
                </div>

                <p class="small text-muted mb-4">
                    Selecciona el tipo de mensaje y cuéntanos cómo podemos mejorar.
                </p>

                <form method="POST">
                    <input type="hidden" name="accion" value="enviar">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">¿Qué quieres comunicar?</label>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="type-option">
                                    <input type="radio" name="tipo" value="Sugerencia" checked>
                                    <span class="type-icon"><i class="bi bi-lightbulb-fill"></i></span>
                                    <span class="small fw-semibold">Sugerencia</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="type-option">
                                    <input type="radio" name="tipo" value="Error">
                                    <span class="type-icon"><i class="bi bi-bug-fill"></i></span>
                                    <span class="small fw-semibold">Reportar error</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="type-option">
                                    <input type="radio" name="tipo" value="Queja">
                                    <span class="type-icon"><i class="bi bi-chat-square-text"></i></span>
                                    <span class="small fw-semibold">Queja</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="type-option">
                                    <input type="radio" name="tipo" value="Felicitacion">
                                    <span class="type-icon"><i class="bi bi-heart-fill"></i></span>
                                    <span class="small fw-semibold">Felicitación</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="asunto" class="form-label fw-semibold">Asunto</label>
                        <input
                            type="text"
                            class="form-control"
                            id="asunto"
                            name="asunto"
                            maxlength="150"
                            required
                            placeholder="Resume tu mensaje"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="mensaje" class="form-label fw-semibold">Mensaje</label>
                        <textarea
                            class="form-control"
                            id="mensaje"
                            name="mensaje"
                            required
                            minlength="10"
                            placeholder="Escribe aquí los detalles de tu mensaje..."
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-send me-2"></i>
                        Enviar mensaje
                    </button>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="card-soft section-card">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <div class="section-title mb-1">
                            <i class="bi bi-clock-history me-2"></i>
                            Historial de mensajes
                        </div>
                        <div class="small text-muted">
                            Consulta el estado y las respuestas del administrador.
                        </div>
                    </div>
                </div>

                <?php if (count($solicitudes) === 0): ?>
                    <div class="empty">
                        <i class="bi bi-chat-left-dots display-5 d-block mb-3"></i>
                        <h5 class="fw-bold">Todavía no has enviado mensajes</h5>
                        <p class="mb-0">Cuando envíes una sugerencia o reporte aparecerá aquí.</p>
                    </div>
                <?php else: ?>

                    <div class="d-grid gap-3">

                        <?php foreach ($solicitudes as $item): ?>
                            <?php
                                [$icono, $color] = claseTipo((string)$item['tipo']);
                                [$estadoTexto, $estadoColor, $estadoIcono] = claseEstado((string)$item['estado']);
                            ?>

                            <article class="message-card">

                                <div class="message-main">
                                    <div class="d-flex gap-3">

                                        <div class="message-icon bg-<?= e($color) ?>-subtle text-<?= e($color) ?>">
                                            <i class="bi <?= e($icono) ?>"></i>
                                        </div>

                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                                <div>
                                                    <div class="small text-<?= e($color) ?> fw-semibold mb-1">
                                                        <?= e($item['tipo']) ?>
                                                    </div>
                                                    <h3 class="h6 fw-bold mb-1"><?= e($item['asunto']) ?></h3>
                                                </div>

                                                <span class="badge text-bg-<?= e($estadoColor) ?>">
                                                    <i class="bi <?= e($estadoIcono) ?> me-1"></i>
                                                    <?= e($estadoTexto) ?>
                                                </span>
                                            </div>

                                            <p class="text-muted small mb-2">
                                                <?= nl2br(e($item['mensaje'])) ?>
                                            </p>

                                            <div class="small text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= e(date('d/m/Y H:i', strtotime((string)$item['fecha']))) ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <?php if (trim((string)$item['respuesta']) !== ''): ?>
                                    <div class="response-box">
                                        <div class="fw-bold text-success mb-2">
                                            <i class="bi bi-reply-fill me-1"></i>
                                            Respuesta del administrador
                                        </div>
                                        <div class="small">
                                            <?= nl2br(e($item['respuesta'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>
            </section>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
</body>
</html>

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
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container py-1">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(urlAplicacion('/estudiante/dashboard.php')) ?>">
            <i class="bi bi-mortarboard-fill"></i>
            Studia360
        </a>

        <div class="d-flex gap-2">
            <a href="<?= e(urlAplicacion('/estudiante/perfil.php')) ?>" class="btn btn-sm btn-outline-light">
                <i class="bi bi-person-circle me-1"></i>Perfil
            </a>
            <a href="<?= e(urlAplicacion('/estudiante/dashboard.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-grid me-1"></i>Inicio
            </a>
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
</body>
</html>

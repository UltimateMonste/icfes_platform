<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/seguridad.php';

exigirAdmin();

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_gestionar_sugerencia'])) {
    $_SESSION['csrf_gestionar_sugerencia'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_gestionar_sugerencia'];
$idSugerencia = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idSugerencia || $idSugerencia <= 0) {
    header('Location: index.php');
    exit;
}

$estadosPermitidos = ['Pendiente', 'Respondida', 'Cerrada'];
$alerta = null;
$tipoAlerta = 'success';

function cargarSugerencia(PDO $conexion, int $id): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.*,
            u.nombres,
            u.apellidos,
            u.correo,
            u.grado
         FROM sugerencias s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.id_sugerencia = :id
         LIMIT 1"
    );

    $stmt->execute([':id' => $id]);
    $dato = $stmt->fetch(PDO::FETCH_ASSOC);

    return $dato ?: null;
}

$sugerencia = cargarSugerencia($conexion, $idSugerencia);

if (!$sugerencia) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $alerta = 'La solicitud expiró. Recarga la página e inténtalo nuevamente.';
        $tipoAlerta = 'danger';
    } else {
        $accion = (string)($_POST['accion'] ?? '');

        try {
            if ($accion === 'guardar') {
                $respuesta = trim((string)($_POST['respuesta'] ?? ''));
                $estado = trim((string)($_POST['estado'] ?? 'Pendiente'));

                if (!in_array($estado, $estadosPermitidos, true)) {
                    $estado = 'Pendiente';
                }

                if ($respuesta !== '' && $estado === 'Pendiente') {
                    $estado = 'Respondida';
                }

                $stmt = $conexion->prepare(
                    "UPDATE sugerencias
                     SET respuesta = :respuesta,
                         estado = :estado
                     WHERE id_sugerencia = :id"
                );

                $stmt->execute([
                    ':respuesta' => $respuesta !== '' ? $respuesta : null,
                    ':estado' => $estado,
                    ':id' => $idSugerencia
                ]);

                $alerta = 'Los cambios fueron guardados correctamente.';
                $tipoAlerta = 'success';
            }

            if ($accion === 'cerrar') {
                $stmt = $conexion->prepare(
                    "UPDATE sugerencias
                     SET estado = 'Cerrada'
                     WHERE id_sugerencia = :id"
                );
                $stmt->execute([':id' => $idSugerencia]);

                $alerta = 'La solicitud fue cerrada.';
                $tipoAlerta = 'success';
            }

            $sugerencia = cargarSugerencia($conexion, $idSugerencia) ?? $sugerencia;
        } catch (PDOException $e) {
            $alerta = 'No fue posible guardar los cambios.';
            $tipoAlerta = 'danger';
        }
    }
}

function tipoUi(string $tipo): array
{
    return match ($tipo) {
        'Queja' => ['bi-chat-square-text-fill', 'danger', 'Queja'],
        'Felicitacion' => ['bi-heart-fill', 'success', 'Felicitación'],
        'Error' => ['bi-bug-fill', 'warning', 'Reporte de error'],
        default => ['bi-lightbulb-fill', 'primary', 'Sugerencia']
    };
}

function estadoUi(string $estado): array
{
    return match ($estado) {
        'Respondida' => ['Respondida', 'success', 'bi-reply-fill'],
        'Cerrada' => ['Cerrada', 'secondary', 'bi-check2-circle'],
        default => ['Pendiente', 'warning', 'bi-hourglass-split']
    };
}

[$tipoIcono, $tipoColor, $tipoNombre] = tipoUi((string)$sugerencia['tipo']);
[$estadoNombre, $estadoColor, $estadoIcono] = estadoUi((string)$sugerencia['estado']);

$nombreCompleto = trim($sugerencia['nombres'] . ' ' . $sugerencia['apellidos']);
$iniciales = '';

foreach (preg_split('/\s+/', $nombreCompleto) as $parte) {
    if ($parte !== '') {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($iniciales) >= 2) break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gestionar mensaje | Studia360</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root{
            --primary:#2467c5;
            --primary-dark:#173f80;
            --soft:#eef5ff;
            --border:#dce5f0;
            --text:#26364a;
            --muted:#718096;
        }

        body{
            min-height:100vh;
            color:var(--text);
            background:
                radial-gradient(circle at top right,rgba(56,121,220,.11),transparent 30%),
                #f4f7fb;
        }

        .topbar{
            background:linear-gradient(105deg,var(--primary-dark),var(--primary));
            box-shadow:0 5px 20px rgba(22,58,117,.18);
        }

        .hero{
            padding:1.75rem 2rem;
            border-radius:24px;
            color:#fff;
            background:
                radial-gradient(circle at 88% 12%,rgba(255,255,255,.15),transparent 24%),
                linear-gradient(120deg,#1e5eb8,#173f80);
            box-shadow:0 18px 42px rgba(25,64,124,.16);
        }

        .soft-card{
            background:rgba(255,255,255,.96);
            border:1px solid var(--border);
            border-radius:22px;
            box-shadow:0 10px 28px rgba(31,57,92,.06);
        }

        .card-pad{padding:1.45rem;}

        .section-title{
            font-size:1.1rem;
            font-weight:800;
        }

        .message-icon{
            width:58px;height:58px;
            border-radius:18px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.55rem;
        }

        .student-avatar{
            width:64px;height:64px;
            border-radius:20px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(145deg,#edf4ff,#d7e6fc);
            color:var(--primary);
            font-size:1.25rem;
            font-weight:800;
        }

        .message-content{
            line-height:1.75;
            color:#46566b;
            white-space:normal;
        }

        textarea.form-control{
            min-height:220px;
            resize:vertical;
        }

        .form-control,.form-select{
            border-color:var(--border);
            border-radius:13px;
            padding:.75rem .9rem;
        }

        .form-control:focus,.form-select:focus{
            border-color:#7aa8e6;
            box-shadow:0 0 0 .25rem rgba(36,103,197,.10);
        }

        .info-row{
            padding:.85rem 0;
            border-bottom:1px solid #edf1f5;
        }

        .info-row:last-child{border-bottom:0;}

        .info-label{
            font-size:.78rem;
            color:var(--muted);
            margin-bottom:.2rem;
        }

        .timeline-dot{
            width:10px;height:10px;border-radius:50%;
            background:var(--primary);
            margin-top:.35rem;
            flex:none;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container py-1">
        <a href="<?= e(urlAplicacion('/admin/dashboard.php')) ?>" class="navbar-brand fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-mortarboard-fill"></i>
            Studia360
            <span class="badge bg-light text-primary ms-1">Admin</span>
        </a>

        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i>
                Volver al buzón
            </a>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">

    <section class="hero mb-4">

        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">

            <div class="d-flex align-items-center gap-3">
                <div class="message-icon bg-white bg-opacity-10">
                    <i class="bi <?= e($tipoIcono) ?>"></i>
                </div>

                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-1">
                        <?= e($tipoNombre) ?>
                    </div>

                    <h1 class="h3 fw-bold mb-1">
                        <?= e($sugerencia['asunto']) ?>
                    </h1>

                    <div class="text-white-50 small">
                        Solicitud #<?= (int)$sugerencia['id_sugerencia'] ?>
                    </div>
                </div>
            </div>

            <span class="badge text-bg-<?= e($estadoColor) ?> fs-6">
                <i class="bi <?= e($estadoIcono) ?> me-1"></i>
                <?= e($estadoNombre) ?>
            </span>

        </div>

    </section>

    <?php if ($alerta !== null): ?>
        <div class="alert alert-<?= e($tipoAlerta) ?> alert-dismissible fade show shadow-sm">
            <i class="bi <?= $tipoAlerta === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= e($alerta) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-8">

            <section class="soft-card card-pad mb-4">

                <div class="section-title mb-4">
                    <i class="bi bi-chat-left-text text-primary me-2"></i>
                    Mensaje del estudiante
                </div>

                <div class="message-content">
                    <?= nl2br(e((string)$sugerencia['mensaje'])) ?>
                </div>

                <hr class="my-4">

                <div class="d-flex align-items-center gap-2 text-muted small">
                    <div class="timeline-dot"></div>
                    Enviado el <?= e(date('d/m/Y \a \l\a\s H:i', strtotime((string)$sugerencia['fecha']))) ?>
                </div>

            </section>

            <section class="soft-card card-pad">

                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                    <div>
                        <div class="section-title mb-1">
                            <i class="bi bi-reply-fill text-primary me-2"></i>
                            Respuesta y gestión
                        </div>

                        <div class="small text-muted">
                            Escribe una respuesta para el estudiante y actualiza el estado de la solicitud.
                        </div>
                    </div>
                </div>

                <form method="POST">

                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="accion" value="guardar">

                    <div class="mb-3">
                        <label for="respuesta" class="form-label fw-semibold">
                            Respuesta del administrador
                        </label>

                        <textarea
                            id="respuesta"
                            name="respuesta"
                            class="form-control"
                            placeholder="Escribe aquí la respuesta que verá el estudiante..."
                        ><?= e((string)($sugerencia['respuesta'] ?? '')) ?></textarea>

                        <div class="form-text">
                            Si escribes una respuesta y el estado está pendiente, se marcará automáticamente como respondida.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="estado" class="form-label fw-semibold">
                            Estado
                        </label>

                        <select id="estado" name="estado" class="form-select">
                            <?php foreach ($estadosPermitidos as $estado): ?>
                                <option
                                    value="<?= e($estado) ?>"
                                    <?= $sugerencia['estado'] === $estado ? 'selected' : '' ?>
                                >
                                    <?= e($estado) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i>
                            Guardar cambios
                        </button>

                    </div>

                </form>

                <?php if ($sugerencia['estado'] !== 'Cerrada'): ?>

                    <form method="POST" class="mt-3" onsubmit="return confirm('¿Deseas cerrar esta solicitud?');">

                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="accion" value="cerrar">

                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-check2-circle me-1"></i>
                            Cerrar solicitud
                        </button>

                    </form>

                <?php endif; ?>

            </section>

        </div>

        <div class="col-lg-4">

            <section class="soft-card card-pad">

                <div class="section-title mb-4">
                    <i class="bi bi-person-circle text-primary me-2"></i>
                    Estudiante
                </div>

                <div class="d-flex align-items-center gap-3 mb-4">

                    <div class="student-avatar">
                        <?= e($iniciales ?: 'E') ?>
                    </div>

                    <div>
                        <div class="fw-bold">
                            <?= e($nombreCompleto) ?>
                        </div>

                        <div class="small text-muted">
                            Estudiante de <?= e($sugerencia['grado']) ?>°
                        </div>
                    </div>

                </div>

                <div class="info-row">
                    <div class="info-label">Correo electrónico</div>
                    <div class="fw-semibold text-break">
                        <?= e($sugerencia['correo']) ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Tipo de mensaje</div>
                    <div class="fw-semibold">
                        <i class="bi <?= e($tipoIcono) ?> text-<?= e($tipoColor) ?> me-1"></i>
                        <?= e($tipoNombre) ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Estado actual</div>
                    <div>
                        <span class="badge text-bg-<?= e($estadoColor) ?>">
                            <?= e($estadoNombre) ?>
                        </span>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">ID de la solicitud</div>
                    <div class="fw-semibold">
                        #<?= (int)$sugerencia['id_sugerencia'] ?>
                    </div>
                </div>

            </section>

        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

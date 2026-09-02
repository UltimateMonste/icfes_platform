<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/seguridad.php';

exigirAdmin();

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$estadoFiltro = trim((string)($_GET['estado'] ?? ''));
$tipoFiltro = trim((string)($_GET['tipo'] ?? ''));
$buscar = trim((string)($_GET['q'] ?? ''));

$estadosPermitidos = ['Pendiente', 'Respondida', 'Cerrada'];
$tiposPermitidos = ['Sugerencia', 'Queja', 'Felicitacion', 'Error'];

$sql = "
    SELECT
        s.id_sugerencia,
        s.asunto,
        s.mensaje,
        s.tipo,
        s.estado,
        s.respuesta,
        s.fecha,
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.correo,
        u.grado
    FROM sugerencias s
    INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
    WHERE 1 = 1
";

$params = [];

if (in_array($estadoFiltro, $estadosPermitidos, true)) {
    $sql .= " AND s.estado = :estado";
    $params[':estado'] = $estadoFiltro;
}

if (in_array($tipoFiltro, $tiposPermitidos, true)) {
    $sql .= " AND s.tipo = :tipo";
    $params[':tipo'] = $tipoFiltro;
}

if ($buscar !== '') {
    $sql .= " AND (
        s.asunto LIKE :buscar
        OR s.mensaje LIKE :buscar
        OR u.nombres LIKE :buscar
        OR u.apellidos LIKE :buscar
        OR u.correo LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= "
    ORDER BY
        CASE s.estado
            WHEN 'Pendiente' THEN 1
            WHEN 'Respondida' THEN 2
            ELSE 3
        END,
        s.fecha DESC,
        s.id_sugerencia DESC
";

$mensajes = [];

try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensajes = [];
}

try {
    $resumenStmt = $conexion->query("
        SELECT
            COUNT(*) AS total,
            SUM(estado = 'Pendiente') AS pendientes,
            SUM(estado = 'Respondida') AS respondidas,
            SUM(estado = 'Cerrada') AS cerradas
        FROM sugerencias
    ");
    $resumenDb = $resumenStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $resumenDb = [];
}

$resumen = [
    'total' => (int)($resumenDb['total'] ?? 0),
    'pendientes' => (int)($resumenDb['pendientes'] ?? 0),
    'respondidas' => (int)($resumenDb['respondidas'] ?? 0),
    'cerradas' => (int)($resumenDb['cerradas'] ?? 0)
];

function tipoUi(string $tipo): array
{
    return match ($tipo) {
        'Queja' => ['bi-chat-square-text-fill', 'danger', 'Queja'],
        'Felicitacion' => ['bi-heart-fill', 'success', 'Felicitación'],
        'Error' => ['bi-bug-fill', 'warning', 'Error'],
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gestión de sugerencias | Studia360</title>

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
            padding:2rem;
            border-radius:25px;
            color:#fff;
            background:
                radial-gradient(circle at 88% 12%,rgba(255,255,255,.16),transparent 24%),
                linear-gradient(120deg,#1e5eb8,#173f80);
            box-shadow:0 18px 42px rgba(25,64,124,.16);
        }

        .hero-icon{
            width:72px;
            height:72px;
            border-radius:22px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:2rem;
            background:rgba(255,255,255,.13);
            border:1px solid rgba(255,255,255,.22);
        }

        .soft-card{
            background:rgba(255,255,255,.96);
            border:1px solid var(--border);
            border-radius:22px;
            box-shadow:0 10px 28px rgba(31,57,92,.06);
        }

        .stat-card{
            padding:1rem;
            border:1px solid var(--border);
            border-radius:18px;
            background:linear-gradient(180deg,#fff,#f8fbff);
            height:100%;
        }

        .stat-icon{
            width:42px;height:42px;
            border-radius:13px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:var(--soft);
            color:var(--primary);
            font-size:1.2rem;
        }

        .stat-number{
            font-size:1.5rem;
            font-weight:800;
            line-height:1;
        }

        .filters{
            padding:1.2rem;
        }

        .form-control,.form-select{
            border-color:var(--border);
            border-radius:13px;
            min-height:44px;
        }

        .form-control:focus,.form-select:focus{
            border-color:#7aa8e6;
            box-shadow:0 0 0 .25rem rgba(36,103,197,.10);
        }

        .message-card{
            padding:1.15rem;
            border:1px solid var(--border);
            border-radius:20px;
            background:#fff;
            transition:.2s ease;
        }

        .message-card:hover{
            transform:translateY(-2px);
            box-shadow:0 14px 30px rgba(29,55,88,.08);
        }

        .type-icon{
            width:48px;height:48px;
            border-radius:15px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.25rem;
            flex:none;
        }

        .student-avatar{
            width:36px;height:36px;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#edf3fb;
            color:var(--primary);
            font-weight:800;
            font-size:.8rem;
        }

        .message-preview{
            color:var(--muted);
            display:-webkit-box;
            -webkit-line-clamp:2;
            -webkit-box-orient:vertical;
            overflow:hidden;
        }

        .section-title{
            font-size:1.1rem;
            font-weight:800;
        }

        @media(max-width:767px){
            .hero{padding:1.4rem;}
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container py-1">

        <a href="<?= e(urlAplicacion('/admin/dashboard.php')) ?>" class="navbar-brand fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-mortarboard-fill"></i>
            <span>Studia360</span>
            <span class="badge bg-light text-primary ms-1">Admin</span>
        </a>

        <div class="d-flex gap-2">
            <a href="<?= e(urlAplicacion('/admin/dashboard.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-grid me-1"></i>
                Dashboard
            </a>

            <a href="<?= e(urlAplicacion('/logout.php')) ?>" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right me-1"></i>
                Salir
            </a>
        </div>

    </div>
</nav>

<main class="container py-4 py-lg-5">

    <section class="hero mb-4">

        <div class="row align-items-center g-4">

            <div class="col-auto">
                <div class="hero-icon">
                    <i class="bi bi-inboxes-fill"></i>
                </div>
            </div>

            <div class="col">
                <div class="text-uppercase small fw-semibold opacity-75 mb-1">
                    Comunicación con estudiantes
                </div>

                <h1 class="h2 fw-bold mb-2">
                    Buzón de sugerencias
                </h1>

                <p class="mb-0 text-white-50">
                    Gestiona ideas, reportes, quejas y mensajes enviados por los estudiantes.
                </p>
            </div>

        </div>

    </section>

    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon mb-3">
                    <i class="bi bi-inboxes"></i>
                </div>
                <div class="stat-number"><?= $resumen['total'] ?></div>
                <div class="small text-muted mt-1">Total</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon mb-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-number"><?= $resumen['pendientes'] ?></div>
                <div class="small text-muted mt-1">Pendientes</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon mb-3">
                    <i class="bi bi-reply"></i>
                </div>
                <div class="stat-number"><?= $resumen['respondidas'] ?></div>
                <div class="small text-muted mt-1">Respondidas</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon mb-3">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="stat-number"><?= $resumen['cerradas'] ?></div>
                <div class="small text-muted mt-1">Cerradas</div>
            </div>
        </div>

    </div>

    <section class="soft-card filters mb-4">

        <form method="GET">

            <div class="row g-3 align-items-end">

                <div class="col-lg-5">
                    <label for="q" class="form-label fw-semibold small">
                        Buscar
                    </label>

                    <input
                        type="search"
                        class="form-control"
                        id="q"
                        name="q"
                        value="<?= e($buscar) ?>"
                        placeholder="Asunto, estudiante o correo..."
                    >
                </div>

                <div class="col-md-4 col-lg-3">
                    <label for="tipo" class="form-label fw-semibold small">
                        Tipo
                    </label>

                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos los tipos</option>
                        <option value="Sugerencia" <?= $tipoFiltro === 'Sugerencia' ? 'selected' : '' ?>>Sugerencias</option>
                        <option value="Error" <?= $tipoFiltro === 'Error' ? 'selected' : '' ?>>Reportes de error</option>
                        <option value="Queja" <?= $tipoFiltro === 'Queja' ? 'selected' : '' ?>>Quejas</option>
                        <option value="Felicitacion" <?= $tipoFiltro === 'Felicitacion' ? 'selected' : '' ?>>Felicitaciones</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label for="estado" class="form-label fw-semibold small">
                        Estado
                    </label>

                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="Pendiente" <?= $estadoFiltro === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Respondida" <?= $estadoFiltro === 'Respondida' ? 'selected' : '' ?>>Respondida</option>
                        <option value="Cerrada" <?= $estadoFiltro === 'Cerrada' ? 'selected' : '' ?>>Cerrada</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i>
                        Filtrar
                    </button>

                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

            </div>

        </form>

    </section>

    <section class="soft-card p-3 p-md-4">

        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="section-title mb-1">
                    <i class="bi bi-chat-square-text me-2 text-primary"></i>
                    Mensajes recibidos
                </div>

                <div class="small text-muted">
                    <?= count($mensajes) ?> resultado<?= count($mensajes) === 1 ? '' : 's' ?> encontrado<?= count($mensajes) === 1 ? '' : 's' ?>.
                </div>
            </div>
        </div>

        <?php if (count($mensajes) === 0): ?>

            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-5 d-block mb-3"></i>
                <h5 class="fw-bold text-dark">No hay mensajes para mostrar</h5>
                <p class="mb-0">Prueba cambiando los filtros o espera nuevos mensajes de los estudiantes.</p>
            </div>

        <?php else: ?>

            <div class="d-grid gap-3">

                <?php foreach ($mensajes as $mensaje): ?>

                    <?php
                        [$tipoIcono, $tipoColor, $tipoNombre] = tipoUi((string)$mensaje['tipo']);
                        [$estadoNombre, $estadoColor, $estadoIcono] = estadoUi((string)$mensaje['estado']);

                        $nombre = trim($mensaje['nombres'] . ' ' . $mensaje['apellidos']);
                        $iniciales = '';
                        foreach (preg_split('/\s+/', $nombre) as $parte) {
                            if ($parte !== '') {
                                $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
                            }
                            if (mb_strlen($iniciales) >= 2) break;
                        }
                    ?>

                    <article class="message-card">

                        <div class="d-flex gap-3">

                            <div class="type-icon bg-<?= e($tipoColor) ?>-subtle text-<?= e($tipoColor) ?>">
                                <i class="bi <?= e($tipoIcono) ?>"></i>
                            </div>

                            <div class="flex-grow-1 min-w-0">

                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">

                                    <div>
                                        <div class="small text-<?= e($tipoColor) ?> fw-semibold mb-1">
                                            <?= e($tipoNombre) ?>
                                        </div>

                                        <h2 class="h6 fw-bold mb-1">
                                            <?= e($mensaje['asunto']) ?>
                                        </h2>
                                    </div>

                                    <span class="badge text-bg-<?= e($estadoColor) ?>">
                                        <i class="bi <?= e($estadoIcono) ?> me-1"></i>
                                        <?= e($estadoNombre) ?>
                                    </span>

                                </div>

                                <p class="message-preview small mb-3">
                                    <?= e($mensaje['mensaje']) ?>
                                </p>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                                    <div class="d-flex align-items-center gap-2">

                                        <div class="student-avatar">
                                            <?= e($iniciales ?: 'E') ?>
                                        </div>

                                        <div class="small">
                                            <div class="fw-semibold"><?= e($nombre) ?></div>
                                            <div class="text-muted">
                                                <?= e($mensaje['grado']) ?>° · <?= e($mensaje['correo']) ?>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="d-flex align-items-center gap-3">

                                        <span class="small text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= e(date('d/m/Y H:i', strtotime((string)$mensaje['fecha']))) ?>
                                        </span>

                                        <a
                                            href="gestionar.php?id=<?= (int)$mensaje['id_sugerencia'] ?>"
                                            class="btn btn-sm btn-primary"
                                        >
                                            <i class="bi bi-arrow-right-circle me-1"></i>
                                            Gestionar
                                        </a>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

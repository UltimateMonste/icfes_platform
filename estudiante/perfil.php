<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';

exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

if ($idUsuario <= 0) {
    redireccionarLogin('Tu sesión no es válida.');
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function h(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function avatarUrl(?string $imagen): string
{
    $imagen = trim((string)$imagen);

    if ($imagen === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $imagen)) {
        return $imagen;
    }

    return urlAplicacion('/uploads/avatares/' . rawurlencode(basename($imagen)));
}

if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_perfil'];
$mensaje = '';
$tipoMensaje = 'success';

/*
|--------------------------------------------------------------------------
| CARGAR USUARIO
|--------------------------------------------------------------------------
*/
try {
    $stmt = $conexion->prepare(
        'SELECT
            u.id_usuario,
            u.nombres,
            u.apellidos,
            u.correo,
            u.grado,
            u.avatar,
            u.id_avatar,
            u.puntos,
            u.nivel,
            u.fecha_registro,
            a.nombre AS avatar_nombre,
            a.imagen AS avatar_imagen,
            a.puntos_requeridos AS avatar_puntos
         FROM usuarios u
         LEFT JOIN avatares a ON a.id_avatar = u.id_avatar
         WHERE u.id_usuario = :id_usuario
         LIMIT 1'
    );

    $stmt->execute([':id_usuario' => $idUsuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        redireccionarDashboardUsuario();
    }
} catch (PDOException $e) {
    die('No fue posible cargar el perfil. Detalle técnico: ' . h($e->getMessage()));
}

/*
|--------------------------------------------------------------------------
| CAMBIAR AVATAR
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_avatar') {
    $tokenRecibido = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrfToken, $tokenRecibido)) {
        $mensaje = 'La solicitud expiró. Recarga la página e inténtalo nuevamente.';
        $tipoMensaje = 'danger';
    } else {
        $idAvatar = filter_input(INPUT_POST, 'id_avatar', FILTER_VALIDATE_INT);

        if (!$idAvatar || $idAvatar <= 0) {
            $mensaje = 'Selecciona un avatar válido.';
            $tipoMensaje = 'danger';
        } else {
            try {
                $stmtAvatar = $conexion->prepare(
                    "SELECT id_avatar, nombre, imagen, puntos_requeridos
                     FROM avatares
                     WHERE id_avatar = :id_avatar
                       AND estado = 'Activo'
                     LIMIT 1"
                );

                $stmtAvatar->execute([':id_avatar' => $idAvatar]);
                $avatarSeleccionado = $stmtAvatar->fetch(PDO::FETCH_ASSOC);

                if (!$avatarSeleccionado) {
                    $mensaje = 'El avatar seleccionado no está disponible.';
                    $tipoMensaje = 'danger';
                } elseif ((int)$usuario['puntos'] < (int)$avatarSeleccionado['puntos_requeridos']) {
                    $mensaje = 'Aún no tienes suficientes puntos para desbloquear este avatar.';
                    $tipoMensaje = 'danger';
                } else {
                    $stmtActualizar = $conexion->prepare(
                        'UPDATE usuarios
                         SET id_avatar = :id_avatar,
                             avatar = :imagen
                         WHERE id_usuario = :id_usuario'
                    );

                    $stmtActualizar->execute([
                        ':id_avatar' => (int)$avatarSeleccionado['id_avatar'],
                        ':imagen' => (string)$avatarSeleccionado['imagen'],
                        ':id_usuario' => $idUsuario
                    ]);

                    $usuario['id_avatar'] = (int)$avatarSeleccionado['id_avatar'];
                    $usuario['avatar'] = $avatarSeleccionado['imagen'];
                    $usuario['avatar_nombre'] = $avatarSeleccionado['nombre'];
                    $usuario['avatar_imagen'] = $avatarSeleccionado['imagen'];
                    $usuario['avatar_puntos'] = $avatarSeleccionado['puntos_requeridos'];

                    $mensaje = 'Tu avatar se actualizó correctamente.';
                    $tipoMensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = 'No fue posible actualizar tu avatar.';
                $tipoMensaje = 'danger';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| NIVEL ACTUAL Y SIGUIENTE
|--------------------------------------------------------------------------
*/
try {
    $stmtNivel = $conexion->prepare(
        'SELECT *
         FROM niveles
         WHERE id_nivel = :id_nivel
         LIMIT 1'
    );
    $stmtNivel->execute([':id_nivel' => (int)$usuario['nivel']]);
    $nivelActual = $stmtNivel->fetch(PDO::FETCH_ASSOC);

    if (!$nivelActual) {
        $stmtNivel = $conexion->prepare(
            'SELECT *
             FROM niveles
             WHERE puntos_minimos <= :puntos
               AND puntos_maximos >= :puntos
             ORDER BY id_nivel ASC
             LIMIT 1'
        );
        $stmtNivel->execute([':puntos' => (int)$usuario['puntos']]);
        $nivelActual = $stmtNivel->fetch(PDO::FETCH_ASSOC);
    }

    $nivelActual = $nivelActual ?: [
        'id_nivel' => 1,
        'nombre' => 'Iniciado',
        'descripcion' => 'Continúa avanzando para subir de nivel.',
        'puntos_minimos' => 0,
        'puntos_maximos' => 0,
        'imagen' => null
    ];

    $stmtSiguiente = $conexion->prepare(
        'SELECT *
         FROM niveles
         WHERE puntos_minimos > :puntos
         ORDER BY puntos_minimos ASC
         LIMIT 1'
    );
    $stmtSiguiente->execute([':puntos' => (int)$usuario['puntos']]);
    $siguienteNivel = $stmtSiguiente->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $nivelActual = [
        'id_nivel' => (int)$usuario['nivel'],
        'nombre' => 'Nivel ' . (int)$usuario['nivel'],
        'descripcion' => 'Sigue aprendiendo y acumulando puntos.',
        'puntos_minimos' => 0,
        'puntos_maximos' => 0,
        'imagen' => null
    ];
    $siguienteNivel = null;
}

$puntos = (int)$usuario['puntos'];

if ($siguienteNivel) {
    $metaPuntos = max((int)$siguienteNivel['puntos_minimos'], 1);
    $inicioNivel = max((int)$nivelActual['puntos_minimos'], 0);
    $rangoNivel = max($metaPuntos - $inicioNivel, 1);
    $avanceNivel = (($puntos - $inicioNivel) / $rangoNivel) * 100;
    $avanceNivel = max(0, min(100, $avanceNivel));
    $faltanPuntos = max(0, $metaPuntos - $puntos);
} else {
    $avanceNivel = 100;
    $faltanPuntos = 0;
}

/*
|--------------------------------------------------------------------------
| AVATARES
|--------------------------------------------------------------------------
*/
$avatares = [];

try {
    $stmtAvatares = $conexion->query(
        "SELECT id_avatar, nombre, imagen, puntos_requeridos
         FROM avatares
         WHERE estado = 'Activo'
         ORDER BY puntos_requeridos ASC, id_avatar ASC"
    );

    $avatares = $stmtAvatares->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avatares = [];
}

/*
|--------------------------------------------------------------------------
| INSIGNIAS
|--------------------------------------------------------------------------
*/
$insignias = [];

try {
    $stmtInsignias = $conexion->prepare(
        "SELECT
            i.id_insignia,
            i.nombre,
            i.descripcion,
            i.imagen,
            i.criterio,
            i.puntos_otorgados,
            CASE WHEN ui.id_usuario IS NULL THEN 0 ELSE 1 END AS obtenida,
            ui.fecha AS fecha_obtenida
         FROM insignias i
         LEFT JOIN usuarios_insignias ui
            ON ui.id_insignia = i.id_insignia
           AND ui.id_usuario = :id_usuario
         WHERE i.estado = 'Activa'
         ORDER BY obtenida DESC, i.id_insignia ASC"
    );

    $stmtInsignias->execute([':id_usuario' => $idUsuario]);
    $insignias = $stmtInsignias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $insignias = [];
}

$totalInsignias = count($insignias);
$insigniasObtenidas = count(array_filter(
    $insignias,
    static fn(array $i): bool => (int)$i['obtenida'] === 1
));

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS DEL PROGRESO
|--------------------------------------------------------------------------
*/
$estadisticas = [
    'temas_iniciados' => 0,
    'temas_completados' => 0,
    'progreso_promedio' => 0,
    'recursos_vistos' => 0,
    'evaluaciones' => 0
];

try {
    $stmtEstadisticas = $conexion->prepare(
        'SELECT
            COUNT(DISTINCT CASE WHEN p.porcentaje_avance > 0 THEN p.id_tema END) AS temas_iniciados,
            COUNT(DISTINCT CASE WHEN p.porcentaje_avance >= 100 THEN p.id_tema END) AS temas_completados,
            COALESCE(ROUND(AVG(p.porcentaje_avance), 0), 0) AS progreso_promedio,
            COALESCE(SUM(p.recursos_vistos), 0) AS recursos_vistos,
            COALESCE(SUM(p.evaluaciones_realizadas), 0) AS evaluaciones
         FROM progreso p
         WHERE p.id_usuario = :id_usuario'
    );

    $stmtEstadisticas->execute([':id_usuario' => $idUsuario]);
    $resultadoEstadisticas = $stmtEstadisticas->fetch(PDO::FETCH_ASSOC);

    if ($resultadoEstadisticas) {
        $estadisticas = array_merge($estadisticas, $resultadoEstadisticas);
    }
} catch (PDOException $e) {
    // Se mantienen valores en cero.
}

/*
|--------------------------------------------------------------------------
| AVATAR PRINCIPAL
|--------------------------------------------------------------------------
*/
$nombreCompleto = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
$iniciales = '';

foreach (preg_split('/\s+/', $nombreCompleto) as $palabra) {
    if ($palabra !== '') {
        $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
    }
    if (mb_strlen($iniciales) >= 2) {
        break;
    }
}

$avatarPrincipal = avatarUrl(
    $usuario['avatar_imagen'] ?: $usuario['avatar']
);

$fechaRegistro = '';
if (!empty($usuario['fecha_registro'])) {
    try {
        $fechaRegistro = (new DateTime($usuario['fecha_registro']))->format('d/m/Y');
    } catch (Throwable $e) {
        $fechaRegistro = '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Mi perfil | Studia360</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --studia-blue: #2467c5;
            --studia-blue-dark: #173f80;
            --studia-soft: #eef5ff;
            --studia-text: #26364a;
            --studia-muted: #6d7a8c;
            --studia-border: #dce5f0;
            --studia-shadow: 0 16px 40px rgba(28, 54, 90, .10);
        }

        body {
            background:
                radial-gradient(circle at top right, rgba(50, 119, 220, .12), transparent 28%),
                #f3f6fa;
            color: var(--studia-text);
            min-height: 100vh;
        }

        .navbar-studia {
            background: linear-gradient(100deg, #173f80, #2467c5);
            box-shadow: 0 4px 20px rgba(20, 61, 125, .18);
        }

        .brand-icon {
            font-size: 1.35rem;
        }

        .hero-profile {
            border-radius: 24px;
            padding: 2rem;
            color: white;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.17), transparent 28%),
                linear-gradient(120deg, #1d5db5, #173f80);
            box-shadow: var(--studia-shadow);
        }

        .profile-avatar {
            width: 122px;
            height: 122px;
            border-radius: 50%;
            border: 5px solid rgba(255,255,255,.85);
            overflow: hidden;
            background: linear-gradient(145deg, #eaf2ff, #c9dcfb);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.35rem;
            font-weight: 800;
            color: var(--studia-blue);
            box-shadow: 0 12px 30px rgba(0,0,0,.18);
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-card,
        .section-card {
            border: 1px solid var(--studia-border);
            border-radius: 22px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 10px 28px rgba(31, 57, 92, .06);
        }

        .section-card {
            padding: 1.5rem;
            height: 100%;
        }

        .section-title {
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
        }

        .section-title i {
            color: var(--studia-blue);
        }

        .stat-card {
            border: 1px solid var(--studia-border);
            border-radius: 18px;
            padding: 1rem;
            background: linear-gradient(180deg, #fff, #f8fbff);
            height: 100%;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--studia-blue);
            background: var(--studia-soft);
            font-size: 1.25rem;
        }

        .stat-number {
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1;
        }

        .level-name {
            font-size: 1.35rem;
            font-weight: 800;
        }

        .progress {
            height: 12px;
            border-radius: 999px;
            background: #e7edf5;
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 999px;
            background: linear-gradient(90deg, #2b70d0, #6aa2f3);
        }

        .avatar-option {
            position: relative;
            border: 2px solid var(--studia-border);
            border-radius: 20px;
            padding: 1rem;
            background: #fff;
            transition: .2s ease;
            height: 100%;
        }

        .avatar-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(35, 78, 136, .10);
        }

        .avatar-option.selected {
            border-color: var(--studia-blue);
            box-shadow: 0 0 0 4px rgba(36,103,197,.10);
        }

        .avatar-option.locked {
            opacity: .72;
            background: #f7f8fa;
        }

        .avatar-preview {
            width: 72px;
            height: 72px;
            margin: 0 auto .8rem;
            border-radius: 50%;
            background: linear-gradient(145deg, #eef4fd, #d8e7fb);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: var(--studia-blue);
            font-size: 1.8rem;
            font-weight: 800;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lock-badge,
        .selected-badge {
            position: absolute;
            top: .65rem;
            right: .65rem;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
        }

        .lock-badge {
            background: #eef0f4;
            color: #687486;
        }

        .selected-badge {
            background: #dff3e7;
            color: #17884b;
        }

        .badge-card {
            border: 1px solid var(--studia-border);
            border-radius: 18px;
            padding: 1rem;
            background: #fff;
            height: 100%;
        }

        .badge-card.locked {
            filter: grayscale(1);
            opacity: .55;
        }

        .badge-image {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: #fff7dc;
            color: #b78100;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .badge-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-row {
            padding: .8rem 0;
            border-bottom: 1px solid #edf1f5;
        }

        .info-row:last-child {
            border-bottom: 0;
        }

        .info-label {
            color: var(--studia-muted);
            font-size: .83rem;
            margin-bottom: .2rem;
        }

        .info-value {
            font-weight: 700;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--studia-muted);
        }

        @media (max-width: 767.98px) {
            .hero-profile {
                padding: 1.4rem;
            }

            .profile-avatar {
                width: 96px;
                height: 96px;
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark navbar-studia">
    <div class="container py-1">
        <a
            class="navbar-brand fw-bold d-flex align-items-center gap-2"
            href="<?= h(urlAplicacion('/estudiante/dashboard.php')) ?>"
        >
            <i class="bi bi-mortarboard-fill brand-icon"></i>
            <span>Studia360</span>
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="text-white-50 d-none d-md-inline">
                <i class="bi bi-person-circle me-1"></i>
                <?= h($usuario['nombres']) ?>
            </span>

            <a
                href="<?= h(urlAplicacion('/estudiante/dashboard.php')) ?>"
                class="btn btn-sm btn-light"
            >
                <i class="bi bi-grid me-1"></i>
                Inicio
            </a>

            <a
                href="<?= h(urlAplicacion('/logout.php')) ?>"
                class="btn btn-sm btn-outline-light"
            >
                <i class="bi bi-box-arrow-right me-1"></i>
                Salir
            </a>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= h($tipoMensaje) ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi <?= $tipoMensaje === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= h($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CABECERA DEL PERFIL -->
    <section class="hero-profile mb-4">
        <div class="row align-items-center g-4">

            <div class="col-auto">
                <div class="profile-avatar">
                    <?php if ($avatarPrincipal !== ''): ?>
                        <img
                            src="<?= h($avatarPrincipal) ?>"
                            alt="Avatar de <?= h($nombreCompleto) ?>"
                            onerror="this.style.display='none'; this.parentElement.querySelector('.avatar-fallback-main').style.display='flex';"
                        >
                    <?php endif; ?>

                    <span
                        class="avatar-fallback-main"
                        style="<?= $avatarPrincipal !== '' ? 'display:none;' : 'display:flex;' ?>"
                    >
                        <?= h($iniciales ?: 'S') ?>
                    </span>
                </div>
            </div>

            <div class="col">
                <div class="text-uppercase small fw-semibold opacity-75 mb-1">
                    Mi perfil
                </div>

                <h1 class="h2 fw-bold mb-1">
                    <?= h($nombreCompleto) ?>
                </h1>

                <p class="mb-3 text-white-50">
                    <?= h($usuario['grado']) ?>° · <?= h($usuario['correo']) ?>
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill text-bg-light px-3 py-2">
                        <i class="bi bi-stars text-primary me-1"></i>
                        <?= number_format($puntos) ?> puntos
                    </span>

                    <span class="badge rounded-pill text-bg-light px-3 py-2">
                        <i class="bi bi-award text-primary me-1"></i>
                        Nivel <?= (int)$nivelActual['id_nivel'] ?> · <?= h($nivelActual['nombre']) ?>
                    </span>
                </div>
            </div>

            <div class="col-lg-auto">
                <a
                    href="#personalizacion"
                    class="btn btn-light fw-semibold"
                >
                    <i class="bi bi-palette me-1"></i>
                    Personalizar
                </a>
            </div>

        </div>
    </section>

    <div class="row g-4">

        <!-- COLUMNA PRINCIPAL -->
        <div class="col-lg-8">

            <!-- PROGRESO Y NIVEL -->
            <section class="section-card mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="section-title mb-1">
                            <i class="bi bi-bar-chart-line me-2"></i>
                            Mi progreso
                        </div>

                        <div class="text-muted small">
                            Tu avance dentro de Studia360.
                        </div>
                    </div>

                    <span class="badge text-bg-primary px-3 py-2">
                        Nivel <?= (int)$nivelActual['id_nivel'] ?>
                    </span>
                </div>

                <div class="profile-card p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="level-name">
                                <?= h($nivelActual['nombre']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= h($nivelActual['descripcion']) ?>
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="fw-bold text-primary fs-5">
                                <?= number_format($puntos) ?>
                            </div>
                            <div class="small text-muted">puntos</div>
                        </div>
                    </div>

                    <div class="progress mb-2">
                        <div
                            class="progress-bar"
                            role="progressbar"
                            style="width: <?= number_format($avanceNivel, 2, '.', '') ?>%"
                            aria-valuenow="<?= number_format($avanceNivel, 0) ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        ></div>
                    </div>

                    <div class="d-flex justify-content-between small text-muted">
                        <?php if ($siguienteNivel): ?>
                            <span>
                                <?= number_format($puntos) ?> / <?= number_format((int)$siguienteNivel['puntos_minimos']) ?> puntos
                            </span>

                            <span class="fw-semibold">
                                Faltan <?= number_format($faltanPuntos) ?> puntos
                            </span>
                        <?php else: ?>
                            <span>Has alcanzado el nivel máximo disponible.</span>
                            <span class="fw-semibold">¡Excelente!</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ESTADÍSTICAS -->
            <section class="mb-4">
                <div class="section-title">
                    <i class="bi bi-activity me-2"></i>
                    Mis estadísticas
                </div>

                <div class="row g-3">

                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon mb-3">
                                <i class="bi bi-play-circle"></i>
                            </div>
                            <div class="stat-number">
                                <?= (int)$estadisticas['temas_iniciados'] ?>
                            </div>
                            <div class="small text-muted mt-1">
                                Temas iniciados
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon mb-3">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div class="stat-number">
                                <?= (int)$estadisticas['temas_completados'] ?>
                            </div>
                            <div class="small text-muted mt-1">
                                Temas completados
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon mb-3">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div class="stat-number">
                                <?= (int)$estadisticas['progreso_promedio'] ?>%
                            </div>
                            <div class="small text-muted mt-1">
                                Progreso promedio
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon mb-3">
                                <i class="bi bi-collection-play"></i>
                            </div>
                            <div class="stat-number">
                                <?= (int)$estadisticas['recursos_vistos'] ?>
                            </div>
                            <div class="small text-muted mt-1">
                                Recursos explorados
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon mb-3">
                                <i class="bi bi-patch-question"></i>
                            </div>
                            <div class="stat-number">
                                <?= (int)$estadisticas['evaluaciones'] ?>
                            </div>
                            <div class="small text-muted mt-1">
                                Evaluaciones realizadas
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- INSIGNIAS -->
            <section class="section-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <div class="section-title mb-1">
                            <i class="bi bi-trophy me-2"></i>
                            Mis insignias
                        </div>

                        <div class="small text-muted">
                            <?= $insigniasObtenidas ?> de <?= $totalInsignias ?> conseguidas.
                        </div>
                    </div>

                    <span class="badge text-bg-warning">
                        <?= $insigniasObtenidas ?>/<?= $totalInsignias ?>
                    </span>
                </div>

                <?php if ($totalInsignias > 0): ?>

                    <div class="row g-3">
                        <?php foreach ($insignias as $insignia): ?>

                            <?php
                                $obtenida = (int)$insignia['obtenida'] === 1;
                                $imagenInsignia = trim((string)$insignia['imagen']);
                            ?>

                            <div class="col-md-6">
                                <div class="badge-card <?= !$obtenida ? 'locked' : '' ?>">
                                    <div class="d-flex gap-3">

                                        <div class="badge-image">
                                            <?php if ($imagenInsignia !== ''): ?>
                                                <img
                                                    src="<?= h(avatarUrl($imagenInsignia)) ?>"
                                                    alt="<?= h($insignia['nombre']) ?>"
                                                    onerror="this.style.display='none';"
                                                >
                                            <?php else: ?>
                                                <i class="bi bi-award-fill"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="fw-bold">
                                                <?= h($insignia['nombre']) ?>

                                                <?php if (!$obtenida): ?>
                                                    <i class="bi bi-lock-fill text-muted ms-1"></i>
                                                <?php endif; ?>
                                            </div>

                                            <div class="small text-muted mt-1">
                                                <?= h($insignia['descripcion']) ?>
                                            </div>

                                            <div class="mt-2 small">
                                                <?php if ($obtenida): ?>
                                                    <span class="text-success fw-semibold">
                                                        <i class="bi bi-check-circle-fill me-1"></i>
                                                        Conseguida
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="bi bi-lock me-1"></i>
                                                        <?= h($insignia['criterio'] ?: 'Continúa avanzando para desbloquearla.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>

                <?php else: ?>

                    <div class="empty-state">
                        <i class="bi bi-award display-6 d-block mb-2"></i>
                        Aún no hay insignias disponibles.
                    </div>

                <?php endif; ?>
            </section>

        </div>

        <!-- COLUMNA LATERAL -->
        <div class="col-lg-4">

            <!-- INFORMACIÓN -->
            <section class="section-card mb-4">
                <div class="section-title">
                    <i class="bi bi-person-vcard me-2"></i>
                    Información personal
                </div>

                <div class="info-row">
                    <div class="info-label">Nombre</div>
                    <div class="info-value"><?= h($nombreCompleto) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Correo electrónico</div>
                    <div class="info-value text-break"><?= h($usuario['correo']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Grado</div>
                    <div class="info-value"><?= h($usuario['grado']) ?>°</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Miembro desde</div>
                    <div class="info-value">
                        <?= h($fechaRegistro ?: 'No disponible') ?>
                    </div>
                </div>
            </section>

            <!-- PERSONALIZACIÓN -->
            <section
                class="section-card"
                id="personalizacion"
            >
                <div class="section-title">
                    <i class="bi bi-palette me-2"></i>
                    Personalización
                </div>

                <p class="small text-muted mb-4">
                    Elige un avatar entre los que has desbloqueado con tus puntos.
                </p>

                <div class="row g-3">

                    <?php if (count($avatares) > 0): ?>

                        <?php foreach ($avatares as $avatar): ?>

                            <?php
                                $desbloqueado = $puntos >= (int)$avatar['puntos_requeridos'];
                                $seleccionado = (int)$avatar['id_avatar'] === (int)$usuario['id_avatar'];
                                $imagenAvatar = avatarUrl($avatar['imagen']);
                            ?>

                            <div class="col-12">
                                <div
                                    class="avatar-option
                                    <?= $seleccionado ? 'selected' : '' ?>
                                    <?= !$desbloqueado ? 'locked' : '' ?>"
                                >

                                    <?php if ($seleccionado): ?>
                                        <span class="selected-badge">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                    <?php elseif (!$desbloqueado): ?>
                                        <span class="lock-badge">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                    <?php endif; ?>

                                    <div class="d-flex align-items-center gap-3">

                                        <div class="avatar-preview mb-0">
                                            <?php if ($imagenAvatar !== ''): ?>
                                                <img
                                                    src="<?= h($imagenAvatar) ?>"
                                                    alt="<?= h($avatar['nombre']) ?>"
                                                    onerror="this.style.display='none';"
                                                >
                                            <?php else: ?>
                                                <i class="bi bi-person-fill"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="fw-bold">
                                                <?= h($avatar['nombre']) ?>
                                            </div>

                                            <div class="small text-muted mb-2">
                                                <?php if ($desbloqueado): ?>
                                                    <i class="bi bi-unlock me-1"></i>
                                                    Desbloqueado
                                                <?php else: ?>
                                                    <i class="bi bi-stars me-1"></i>
                                                    Requiere <?= number_format((int)$avatar['puntos_requeridos']) ?> puntos
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($seleccionado): ?>

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-success"
                                                    disabled
                                                >
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Avatar actual
                                                </button>

                                            <?php elseif ($desbloqueado): ?>

                                                <form method="POST">
                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="cambiar_avatar"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf"
                                                        value="<?= h($csrfToken) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_avatar"
                                                        value="<?= (int)$avatar['id_avatar'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-primary"
                                                    >
                                                        Usar avatar
                                                    </button>
                                                </form>

                                            <?php else: ?>

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-light"
                                                    disabled
                                                >
                                                    <i class="bi bi-lock me-1"></i>
                                                    Bloqueado
                                                </button>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>
                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state py-4">
                            <i class="bi bi-person-bounding-box display-6 d-block mb-2"></i>
                            No hay avatares disponibles.
                        </div>

                    <?php endif; ?>

                </div>
            </section>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$idTema = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$idTema || $idTema <= 0) {
    header("Location: dashboard.php");
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

function youtubeId(string $url): ?string
{
    $patterns = [
        '~youtu\.be/([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/watch\?(?:[^#]*&)?v=([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/embed/([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
    }

    return null;
}

function iconoRecurso(string $tipo): string
{
    return match (strtolower($tipo)) {
        "video" => "bi-play-circle-fill",
        "pdf" => "bi-file-earmark-pdf-fill",
        "juego" => "bi-controller",
        "simulador" => "bi-display",
        "app" => "bi-phone",
        "presentacion" => "bi-easel2-fill",
        "articulo" => "bi-file-text-fill",
        "blog" => "bi-journal-text",
        default => "bi-link-45deg"
    };
}

function miniaturaRecurso(array $recurso): ?string
{
    $imagen = trim((string)($recurso["imagen"] ?? ""));

    if ($imagen !== "") {
        if (preg_match('~^https?://~i', $imagen)) {
            return $imagen;
        }

        if (str_starts_with($imagen, "/")) {
            return $imagen;
        }

        return "/icfes_platform/" . ltrim($imagen, "/");
    }

    if (strtolower((string)$recurso["tipo"]) === "video") {
        $id = youtubeId((string)$recurso["url"]);

        if ($id) {
            return "https://img.youtube.com/vi/" . rawurlencode($id) . "/hqdefault.jpg";
        }
    }

    return null;
}

try {

    /*
     * Información del tema
     */
    $sql = "
        SELECT
            t.id_tema,
            t.nombre AS tema,
            t.descripcion,
            t.grado,
            m.id_materia,
            m.nombre AS materia
        FROM temas t
        INNER JOIN materias m
            ON m.id_materia = t.id_materia
        WHERE t.id_tema = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([$idTema]);

    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        header("Location: ../dashboard.php");
        exit;
    }

    /*
     * Contenido.
     *
     * A diferencia de la vista del estudiante, la vista previa del
     * administrador permite visualizar también contenido en borrador.
     */
    $sqlContenido = "
        SELECT
            id_contenido,
            contenido,
            estado,
            fecha_actualizacion
        FROM contenido_temas
        WHERE id_tema = ?
        ORDER BY id_contenido DESC
        LIMIT 1
    ";

    $stmtContenido = $conexion->prepare($sqlContenido);
    $stmtContenido->execute([$idTema]);

    $contenido = $stmtContenido->fetch(PDO::FETCH_ASSOC);

    /*
     * Recursos.
     *
     * En vista previa mostramos tanto Activos como Inactivos para que
     * el administrador pueda comprobar todo lo que está configurando.
     */
    $sqlRecursos = "
        SELECT
            id_recurso,
            titulo,
            tipo,
            url,
            descripcion,
            imagen,
            fecha_publicacion,
            autor,
            fuente,
            estado
        FROM recursos
        WHERE id_tema = ?
        ORDER BY id_recurso DESC
    ";

    $stmtRecursos = $conexion->prepare($sqlRecursos);
    $stmtRecursos->execute([$idTema]);

    $recursos = $stmtRecursos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Error al cargar la vista previa: " . e($e->getMessage()));
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Vista previa | <?= e($tema["tema"]) ?> | Studia360
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        body {
            background: #f4f7fb;
            color: #1f2937;
        }

        .admin-bar {
            background: #20252b;
            color: #fff;
            padding: 12px 0;
        }

        .admin-bar-title {
            font-weight: 700;
        }

        .admin-label {
            background: #ffc107;
            color: #212529;
            border-radius: 6px;
            padding: 5px 9px;
            font-size: .75rem;
            font-weight: 800;
        }

        .page {
            max-width: 1160px;
            margin: auto;
            padding: 28px 18px 60px;
        }

        .hero {
            background: linear-gradient(
                135deg,
                #0d6efd,
                #164fa6
            );
            color: #fff;
            padding: 35px 30px;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 15px 35px rgba(13, 110, 253, .16);
            margin-bottom: 25px;
        }

        .hero small {
            opacity: .8;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }

        .hero h1 {
            font-weight: 800;
            margin: 7px 0;
        }

        .card-studia {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        }

        .content {
            padding: 30px;
        }

        .content-body {
            font-size: 1.03rem;
            line-height: 1.75;
        }

        .content-body img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
        }

        .content-body iframe,
        .content-body video {
            max-width: 100%;
        }

        .resource-card {
            height: 100%;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        .resource-image {
            aspect-ratio: 16 / 9;
            background: #eaf2ff;
            position: relative;
            overflow: hidden;
        }

        .resource-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .placeholder {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #0d6efd;
        }

        .play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #fff;
            background: rgba(0,0,0,.12);
        }

        .resource-body {
            padding: 18px;
        }

        .resource-body h3 {
            font-size: 1.05rem;
            font-weight: 800;
        }

        .status {
            font-size: .72rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 5px;
        }

        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-inactive {
            background: #f8d7da;
            color: #842029;
        }

    </style>

</head>

<body>

<div class="admin-bar">

    <div class="container d-flex justify-content-between align-items-center">

        <div class="admin-bar-title">
            <i class="bi bi-mortarboard-fill me-2"></i>
            Studia360
            <span class="ms-2 opacity-50">|</span>
            <span class="ms-2">Vista previa</span>
        </div>

        <div class="d-flex gap-2 align-items-center">

            <span class="admin-label">
                VISTA DE ADMINISTRADOR
            </span>

            <a
                href="editar_tema.php?id=<?= (int)$tema["id_tema"] ?>"
                class="btn btn-light btn-sm"
            >
                <i class="bi bi-pencil me-1"></i>
                Editar
            </a>

        </div>

    </div>

</div>

<main class="page">

    <div class="mb-3">

        <a
            href="editar_tema.php?id=<?= (int)$tema["id_tema"] ?>"
            class="text-decoration-none"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Volver al editor
        </a>

    </div>

    <section class="hero">

        <small>
            <?= e($tema["materia"]) ?> ·
            <?= e($tema["grado"]) ?>°
        </small>

        <h1>
            <?= e($tema["tema"]) ?>
        </h1>

        <?php if (!empty($tema["descripcion"])): ?>

            <p class="mb-0 opacity-75">
                <?= e($tema["descripcion"]) ?>
            </p>

        <?php endif; ?>

    </section>

    <div class="row g-4">

        <div class="col-lg-8">

            <section class="card-studia content">

                <h2 class="h5 fw-bold mb-4">

                    <i class="bi bi-book-half text-primary me-2"></i>

                    Contenido del tema

                    <?php if ($contenido): ?>

                        <span
                            class="badge ms-2
                            <?= strtolower((string)$contenido["estado"]) === "publicado"
                                ? "text-bg-success"
                                : "text-bg-warning"
                            ?>"
                        >
                            <?= e($contenido["estado"]) ?>
                        </span>

                    <?php endif; ?>

                </h2>

                <?php if (
                    $contenido &&
                    trim((string)$contenido["contenido"]) !== ""
                ): ?>

                    <article class="content-body">

                        <?= $contenido["contenido"] ?>

                    </article>

                <?php else: ?>

                    <div class="text-center py-5 text-secondary">

                        <i
                            class="bi bi-file-earmark-x"
                            style="font-size:3rem;"
                        ></i>

                        <h3 class="h5 mt-3">
                            No hay contenido guardado
                        </h3>

                        <p class="mb-0">
                            Utiliza el editor para agregar contenido al tema.
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        </div>

        <div class="col-lg-4">

            <div class="card-studia p-4">

                <h2 class="h5 fw-bold mb-4">
                    <i class="bi bi-info-circle text-primary me-2"></i>
                    Información del tema
                </h2>

                <div class="mb-3">

                    <small class="text-secondary">
                        Materia
                    </small>

                    <strong class="d-block">
                        <?= e($tema["materia"]) ?>
                    </strong>

                </div>

                <div class="mb-3">

                    <small class="text-secondary">
                        Grado
                    </small>

                    <strong class="d-block">
                        <?= e($tema["grado"]) ?>°
                    </strong>

                </div>

                <div>

                    <small class="text-secondary">
                        Recursos
                    </small>

                    <strong class="d-block">
                        <?= count($recursos) ?>
                    </strong>

                </div>

            </div>

        </div>

    </div>

    <?php if (!empty($recursos)): ?>

        <section class="mt-5">

            <div class="mb-4">

                <h2 class="h4 fw-bold">
                    <i class="bi bi-collection-play text-primary me-2"></i>
                    Recursos complementarios
                </h2>

                <p class="text-secondary mb-0">
                    Vista previa de los recursos configurados para este tema.
                </p>

            </div>

            <div class="row g-4">

                <?php foreach ($recursos as $recurso): ?>

                    <?php

                    $tipo = strtolower((string)$recurso["tipo"]);

                    $imagen = miniaturaRecurso($recurso);

                    $esVideo = $tipo === "video";

                    ?>

                    <div class="col-md-6 col-xl-4">

                        <article class="resource-card">

                            <div class="resource-image">

                                <?php if ($imagen): ?>

                                    <img
                                        src="<?= e($imagen) ?>"
                                        alt="<?= e($recurso["titulo"]) ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                    >

                                    <div
                                        class="placeholder"
                                        style="display:none;"
                                    >
                                        <i class="bi <?= e(iconoRecurso($tipo)) ?>"></i>
                                    </div>

                                    <?php if ($esVideo): ?>

                                        <div class="play">
                                            <i class="bi bi-play-circle-fill"></i>
                                        </div>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <div class="placeholder">
                                        <i class="bi <?= e(iconoRecurso($tipo)) ?>"></i>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="resource-body">

                                <div class="d-flex justify-content-between gap-2">

                                    <h3 class="mb-0">
                                        <?= e($recurso["titulo"]) ?>
                                    </h3>

                                    <span
                                        class="status
                                        <?= strtolower((string)$recurso["estado"]) === "activo"
                                            ? "status-active"
                                            : "status-inactive"
                                        ?>"
                                    >
                                        <?= e($recurso["estado"]) ?>
                                    </span>

                                </div>

                                <div class="text-primary small fw-semibold mt-2">

                                    <i class="bi <?= e(iconoRecurso($tipo)) ?> me-1"></i>

                                    <?= e(ucfirst($tipo)) ?>

                                </div>

                                <?php if (!empty($recurso["descripcion"])): ?>

                                    <p class="text-secondary small mt-3 mb-3">
                                        <?= e($recurso["descripcion"]) ?>
                                    </p>

                                <?php endif; ?>

                                <a
                                    href="<?= e($recurso["url"]) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    Abrir recurso
                                    <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>

                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
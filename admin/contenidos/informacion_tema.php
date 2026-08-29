<?php
/**
 * Studia360
 * Administración de información de un tema
 *
 * Ubicación:
 * admin/contenidos/informacion_tema.php
 *
 * Esta página administra los datos estructurales del tema:
 * nombre, materia, grado y descripción.
 * El contenido enriquecido se administra desde editar_tema.php.
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$mensajes = [];

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idTema || $idTema <= 0) {
    header("Location: temas.php");
    exit;
}

function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_informacion_tema"])) {
    $_SESSION["csrf_informacion_tema"] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION["csrf_informacion_tema"];

/*
|--------------------------------------------------------------------------
| CARGAR TEMA
|--------------------------------------------------------------------------
*/
$tema = null;

try {
    $stmt = $conexion->prepare("
        SELECT
            t.id_tema,
            t.id_materia,
            t.nombre,
            t.descripcion,
            t.grado,
            m.nombre AS materia
        FROM temas t
        INNER JOIN materias m
            ON m.id_materia = t.id_materia
        WHERE t.id_tema = ?
        LIMIT 1
    ");

    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        header("Location: temas.php");
        exit;
    }
} catch (PDOException $e) {
    $errores[] = "No fue posible cargar la información del tema.";
}

/*
|--------------------------------------------------------------------------
| MATERIAS
|--------------------------------------------------------------------------
*/
$materias = [];

if ($tema) {
    try {
        $stmt = $conexion->query("
            SELECT
                id_materia,
                nombre,
                descripcion
            FROM materias
            ORDER BY nombre ASC
        ");

        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errores[] = "No fue posible cargar las materias.";
    }
}

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS DEL TEMA
|--------------------------------------------------------------------------
*/
$estadisticas = [
    "recursos" => 0,
    "evaluaciones" => 0,
    "preguntas" => 0,
    "estudiantes" => 0,
    "progreso_promedio" => 0,
    "contenido_estado" => null,
    "fecha_contenido" => null
];

if ($tema) {
    try {
        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM recursos
            WHERE id_tema = ?
              AND estado = 'Activo'
        ");
        $stmt->execute([$idTema]);
        $estadisticas["recursos"] = (int)$stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM evaluaciones
            WHERE id_tema = ?
        ");
        $stmt->execute([$idTema]);
        $estadisticas["evaluaciones"] = (int)$stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM preguntas
            WHERE id_tema = ?
        ");
        $stmt->execute([$idTema]);
        $estadisticas["preguntas"] = (int)$stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM progreso
            WHERE id_tema = ?
        ");
        $stmt->execute([$idTema]);
        $estadisticas["estudiantes"] = (int)$stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT COALESCE(AVG(porcentaje_avance), 0)
            FROM progreso
            WHERE id_tema = ?
        ");
        $stmt->execute([$idTema]);
        $estadisticas["progreso_promedio"] = (float)$stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT
                estado,
                fecha_actualizacion
            FROM contenido_temas
            WHERE id_tema = ?
            ORDER BY
                CASE
                    WHEN estado = 'Publicado' THEN 1
                    ELSE 2
                END,
                fecha_actualizacion DESC,
                id_contenido DESC
            LIMIT 1
        ");
        $stmt->execute([$idTema]);
        $contenido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contenido) {
            $estadisticas["contenido_estado"] = $contenido["estado"];
            $estadisticas["fecha_contenido"] = $contenido["fecha_actualizacion"];
        }
    } catch (PDOException $e) {
        // Las estadísticas son complementarias; no bloqueamos la edición.
    }
}

/*
|--------------------------------------------------------------------------
| ACTUALIZAR INFORMACIÓN
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && $tema) {

    $token = (string)($_POST["csrf"] ?? "");

    if (
        !hash_equals(
            (string)$_SESSION["csrf_informacion_tema"],
            $token
        )
    ) {
        $errores[] = "La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.";
    }

    $idMateria = (int)($_POST["id_materia"] ?? 0);
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $descripcion = trim((string)($_POST["descripcion"] ?? ""));
    $grado = trim((string)($_POST["grado"] ?? ""));

    if ($idMateria <= 0) {
        $errores[] = "Debes seleccionar una materia.";
    }

    if ($nombre === "") {
        $errores[] = "El nombre del tema es obligatorio.";
    } elseif (mb_strlen($nombre) > 150) {
        $errores[] = "El nombre no puede superar los 150 caracteres.";
    }

    if (mb_strlen($descripcion) > 5000) {
        $errores[] = "La descripción es demasiado larga.";
    }

    if (!in_array($grado, ["9", "10", "11"], true)) {
        $errores[] = "El grado seleccionado no es válido.";
    }

    /*
     * Validar materia.
     */
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM materias
                WHERE id_materia = ?
            ");
            $stmt->execute([$idMateria]);

            if ((int)$stmt->fetchColumn() === 0) {
                $errores[] = "La materia seleccionada no existe.";
            }
        } catch (PDOException $e) {
            $errores[] = "No fue posible validar la materia.";
        }
    }

    /*
     * Evitar duplicados dentro de la misma materia y grado.
     */
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM temas
                WHERE id_materia = ?
                  AND grado = ?
                  AND LOWER(nombre) = LOWER(?)
                  AND id_tema <> ?
            ");

            $stmt->execute([
                $idMateria,
                $grado,
                $nombre,
                $idTema
            ]);

            if ((int)$stmt->fetchColumn() > 0) {
                $errores[] = "Ya existe otro tema con ese nombre dentro de la misma materia y grado.";
            }
        } catch (PDOException $e) {
            $errores[] = "No fue posible comprobar si el nombre está disponible.";
        }
    }

    /*
     * Actualizar.
     */
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("
                UPDATE temas
                SET
                    id_materia = ?,
                    nombre = ?,
                    descripcion = ?,
                    grado = ?
                WHERE id_tema = ?
            ");

            $stmt->execute([
                $idMateria,
                $nombre,
                $descripcion !== "" ? $descripcion : null,
                $grado,
                $idTema
            ]);

            /*
             * Recargar materia relacionada.
             */
            $stmt = $conexion->prepare("
                SELECT nombre
                FROM materias
                WHERE id_materia = ?
                LIMIT 1
            ");
            $stmt->execute([$idMateria]);
            $nombreMateria = (string)$stmt->fetchColumn();

            $tema["id_materia"] = $idMateria;
            $tema["nombre"] = $nombre;
            $tema["descripcion"] = $descripcion;
            $tema["grado"] = $grado;
            $tema["materia"] = $nombreMateria;

            $mensajes[] = "La información del tema se actualizó correctamente.";

        } catch (PDOException $e) {
            $errores[] = "No fue posible actualizar el tema.";
        }
    }
}

$nombreAdmin = trim((string)($_SESSION["nombres"] ?? "Administrador"));
if ($nombreAdmin === "") {
    $nombreAdmin = "Administrador";
}

$gradoNombres = [
    "9" => "Noveno",
    "10" => "Décimo",
    "11" => "Undécimo"
];

$avancePromedio = max(
    0,
    min(
        100,
        (float)$estadisticas["progreso_promedio"]
    )
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Información del tema | Studia360</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>
        :root {
            --primary: #0d6efd;
            --dark: #20252b;
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #64748b;
        }

        body {
            background: var(--bg);
            color: var(--text);
        }

        .navbar-studia {
            background: var(--dark);
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 30px 18px 60px;
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #084298);
            color: #fff;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 18px 45px rgba(13,110,253,.18);
            margin-bottom: 24px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .78rem;
            font-weight: 700;
            opacity: .75;
        }

        .hero h1 {
            font-weight: 800;
            margin: 5px 0 8px;
        }

        .hero p {
            color: rgba(255,255,255,.85);
            max-width: 760px;
            margin-bottom: 0;
        }

        .hero-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .card-studia {
            border: 0;
            border-radius: 22px;
            box-shadow: 0 12px 35px rgba(20,35,60,.08);
        }

        .stat-card {
            border: 1px solid #e5eaf0;
            border-radius: 18px;
            background: #fff;
            padding: 18px;
            height: 100%;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: var(--primary);
            font-size: 1.15rem;
        }

        .stat-number {
            font-size: 1.45rem;
            font-weight: 800;
            margin-top: 12px;
        }

        .stat-label {
            color: var(--muted);
            font-size: .88rem;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            padding: .75rem .9rem;
            border-color: #dbe2ea;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13,110,253,.10);
        }

        .section-title {
            font-weight: 800;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: .82rem;
            font-weight: 700;
        }

        .status-publicado {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-borrador {
            background: #fff3cd;
            color: #664d03;
        }

        .status-vacio {
            background: #e9ecef;
            color: #495057;
        }

        .progress {
            height: 9px;
            border-radius: 99px;
        }

        .btn {
            border-radius: 11px;
        }

        .notice {
            border-radius: 16px;
            background: #eef6ff;
            border: 1px solid #d8eaff;
        }

        @media (max-width: 767px) {
            .hero {
                border-radius: 18px;
                padding: 24px;
            }

            .hero-top {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark navbar-studia">
    <div class="container-fluid px-3 px-md-4">

        <a
            href="../dashboard.php"
            class="navbar-brand fw-bold"
        >
            <i class="bi bi-mortarboard-fill me-2"></i>
            Studia360
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="text-white-50 d-none d-md-inline">
                <?= e($nombreAdmin) ?>
            </span>

            <a
                href="temas.php<?= $tema ? "?id_materia=" . (int)$tema["id_materia"] : "" ?>"
                class="btn btn-outline-light btn-sm"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Temas
            </a>
        </div>

    </div>
</nav>

<main class="page">

    <?php foreach ($mensajes as $mensaje): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= e($mensaje) ?>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($tema): ?>

        <section class="hero">

            <div class="hero-top">

                <div>
                    <div class="eyebrow">
                        Información del tema
                    </div>

                    <h1 class="display-6">
                        <?= e($tema["nombre"]) ?>
                    </h1>

                    <p>
                        <?= e(
                            $tema["descripcion"]
                            ?: "Este tema todavía no tiene una descripción."
                        ) ?>
                    </p>
                </div>

                <div class="hero-actions">

                    <a
                        href="editar_tema.php?id=<?= $idTema ?>"
                        class="btn btn-light"
                    >
                        <i class="bi bi-file-earmark-richtext me-1"></i>
                        Administrar contenido
                    </a>

                    <a
                        href="<?= e(
                            "/icfes_platform/estudiante/tema.php?id=" . $idTema
                        ) ?>"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-outline-light"
                    >
                        <i class="bi bi-eye me-1"></i>
                        Vista previa
                    </a>

                </div>

            </div>

        </section>

        <div class="row g-4 mb-4">

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-collection-play"></i>
                    </div>

                    <div class="stat-number">
                        <?= (int)$estadisticas["recursos"] ?>
                    </div>

                    <div class="stat-label">
                        Recursos activos
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-ui-checks-grid"></i>
                    </div>

                    <div class="stat-number">
                        <?= (int)$estadisticas["evaluaciones"] ?>
                    </div>

                    <div class="stat-label">
                        Evaluaciones
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-patch-question"></i>
                    </div>

                    <div class="stat-number">
                        <?= (int)$estadisticas["preguntas"] ?>
                    </div>

                    <div class="stat-label">
                        Preguntas
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>

                    <div class="stat-number">
                        <?= (int)$estadisticas["estudiantes"] ?>
                    </div>

                    <div class="stat-label">
                        Estudiantes con progreso
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4">

            <div class="col-lg-8">

                <div class="card card-studia bg-white">
                    <div class="card-body p-4 p-md-5">

                        <div class="mb-4">
                            <h2 class="h4 section-title mb-1">
                                <i class="bi bi-sliders2 text-primary me-2"></i>
                                Datos del tema
                            </h2>

                            <p class="text-secondary mb-0">
                                Modifica dónde aparece el tema y cómo se identifica.
                            </p>
                        </div>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e($csrf) ?>"
                            >

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Materia
                                    </label>

                                    <select
                                        name="id_materia"
                                        class="form-select"
                                        required
                                    >
                                        <?php foreach ($materias as $materia): ?>
                                            <option
                                                value="<?= (int)$materia["id_materia"] ?>"
                                                <?= (int)$tema["id_materia"] === (int)$materia["id_materia"] ? "selected" : "" ?>
                                            >
                                                <?= e($materia["nombre"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Grado
                                    </label>

                                    <select
                                        name="grado"
                                        class="form-select"
                                        required
                                    >
                                        <?php foreach ($gradoNombres as $valor => $etiqueta): ?>
                                            <option
                                                value="<?= e($valor) ?>"
                                                <?= (string)$tema["grado"] === (string)$valor ? "selected" : "" ?>
                                            >
                                                <?= e($etiqueta) ?> (<?= e($valor) ?>°)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                </div>

                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Nombre del tema
                                    </label>

                                    <input
                                        type="text"
                                        name="nombre"
                                        class="form-control form-control-lg"
                                        maxlength="150"
                                        value="<?= e($tema["nombre"]) ?>"
                                        required
                                    >

                                </div>

                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Descripción
                                    </label>

                                    <textarea
                                        name="descripcion"
                                        class="form-control"
                                        rows="6"
                                        maxlength="5000"
                                        placeholder="Describe brevemente el tema..."
                                    ><?= e($tema["descripcion"]) ?></textarea>

                                </div>

                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between flex-wrap gap-2">

                                <a
                                    href="temas.php<?= $tema ? "?id_materia=" . (int)$tema["id_materia"] : "" ?>"
                                    class="btn btn-outline-secondary"
                                >
                                    <i class="bi bi-x-lg me-1"></i>
                                    Cancelar
                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-primary px-4"
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Guardar cambios
                                </button>

                            </div>

                        </form>

                    </div>
                </div>

            </div>

            <div class="col-lg-4">

                <div class="card card-studia bg-white mb-4">
                    <div class="card-body p-4">

                        <h2 class="h5 fw-bold mb-3">
                            <i class="bi bi-file-earmark-richtext text-primary me-2"></i>
                            Estado del contenido
                        </h2>

                        <?php
                        $estado = $estadisticas["contenido_estado"];

                        if ($estado === "Publicado"):
                        ?>
                            <span class="status status-publicado">
                                <i class="bi bi-check-circle-fill"></i>
                                Contenido publicado
                            </span>

                        <?php elseif ($estado === "Borrador"): ?>

                            <span class="status status-borrador">
                                <i class="bi bi-pencil-square"></i>
                                Borrador guardado
                            </span>

                        <?php else: ?>

                            <span class="status status-vacio">
                                <i class="bi bi-file-earmark"></i>
                                Sin contenido
                            </span>

                        <?php endif; ?>

                        <?php if (!empty($estadisticas["fecha_contenido"])): ?>
                            <div class="small text-secondary mt-3">
                                Última actualización:
                                <br>
                                <?= e((string)$estadisticas["fecha_contenido"]) ?>
                            </div>
                        <?php endif; ?>

                        <a
                            href="editar_tema.php?id=<?= $idTema ?>"
                            class="btn btn-primary w-100 mt-4"
                        >
                            <i class="bi bi-pencil-square me-1"></i>
                            Abrir editor
                        </a>

                    </div>
                </div>

                <div class="card card-studia bg-white">
                    <div class="card-body p-4">

                        <h2 class="h5 fw-bold mb-3">
                            <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                            Progreso promedio
                        </h2>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">
                                Avance registrado
                            </span>

                            <strong>
                                <?= number_format($avancePromedio, 0) ?>%
                            </strong>
                        </div>

                        <div class="progress bg-light">
                            <div
                                class="progress-bar"
                                role="progressbar"
                                style="width: <?= $avancePromedio ?>%;"
                                aria-valuenow="<?= $avancePromedio ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            ></div>
                        </div>

                        <div class="notice p-3 mt-4 small">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            Este porcentaje corresponde al promedio registrado
                            para los estudiantes que tienen progreso en este tema.
                        </div>

                    </div>
                </div>

            </div>

        </div>

    <?php endif; ?>

</main>

</body>
</html>

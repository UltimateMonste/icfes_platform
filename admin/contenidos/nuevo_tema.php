<?php
/**
 * Studia360
 * Creación de temas
 *
 * Archivo:
 * admin/contenidos/nuevo_tema.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$nombre = "";
$descripcion = "";
$grado = "";
$idMateria = "";

$nombreAdmin = trim($_SESSION["nombres"] ?? "");
if ($nombreAdmin === "") {
    $nombreAdmin = "Administrador";
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$urlDashboard = urlAplicacion("/admin/dashboard.php");
$urlIndex = urlAplicacion("/admin/contenidos/index.php");
$urlTemas = urlAplicacion("/admin/contenidos/temas.php");
$urlCerrarSesion = urlAplicacion("/cerrar_sesion.php");

$materias = [];

try {
    $stmt = $conexion->query(
        "SELECT id_materia, nombre, descripcion
         FROM materias
         ORDER BY nombre ASC"
    );

    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errores[] = "No fue posible cargar las materias.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $grado = trim($_POST["grado"] ?? "");
    $idMateria = trim($_POST["id_materia"] ?? "");

    if ($nombre === "") {
        $errores[] = "El nombre del tema es obligatorio.";
    } elseif (mb_strlen($nombre) > 150) {
        $errores[] = "El nombre del tema no puede superar los 150 caracteres.";
    }

    if (mb_strlen($descripcion) > 5000) {
        $errores[] = "La descripción es demasiado larga.";
    }

    if (!in_array($grado, ["9", "10", "11"], true)) {
        $errores[] = "Debes seleccionar un grado válido.";
    }

    $idMateriaInt = filter_var(
        $idMateria,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if (!$idMateriaInt) {
        $errores[] = "Debes seleccionar una materia.";
    }

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare(
                "SELECT id_materia
                 FROM materias
                 WHERE id_materia = ?
                 LIMIT 1"
            );
            $stmt->execute([$idMateriaInt]);

            if (!$stmt->fetchColumn()) {
                $errores[] = "La materia seleccionada no existe.";
            }

            if (empty($errores)) {

                $stmt = $conexion->prepare(
                    "SELECT COUNT(*)
                     FROM temas
                     WHERE id_materia = ?
                     AND grado = ?
                     AND LOWER(nombre) = LOWER(?)"
                );

                $stmt->execute([
                    $idMateriaInt,
                    $grado,
                    $nombre
                ]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $errores[] =
                        "Ya existe un tema con ese nombre en esa materia y grado.";
                }
            }

            if (empty($errores)) {

                $stmt = $conexion->prepare(
                    "INSERT INTO temas
                        (id_materia, nombre, descripcion, grado)
                     VALUES
                        (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $idMateriaInt,
                    $nombre,
                    $descripcion !== "" ? $descripcion : null,
                    $grado
                ]);

                $idNuevoTema = (int)$conexion->lastInsertId();

                header(
                    "Location: " .
                    urlAplicacion(
                        "/admin/contenidos/editar_tema.php?id=" .
                        $idNuevoTema .
                        "&creado=1"
                    )
                );
                exit;
            }

        } catch (PDOException $e) {
            $errores[] =
                "No fue posible crear el tema. Verifica la conexión y la estructura de la tabla temas.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Nuevo tema | Studia360</title>

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
            --blue: #0d6efd;
            --blue-dark: #084298;
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #667085;
            --border: #e3e8f0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family:
                Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", sans-serif;
        }

        .navbar {
            box-shadow: 0 4px 18px rgba(20,35,60,.12);
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.15);
            margin-right: .55rem;
        }

        .page {
            max-width: 1100px;
            margin: auto;
            padding: 2rem 1rem 4rem;
        }

        .hero {
            border-radius: 22px;
            padding: 1.8rem 2rem;
            color: #fff;
            background:
                linear-gradient(135deg, var(--blue), var(--blue-dark));
            box-shadow: 0 16px 35px rgba(13,110,253,.16);
        }

        .hero-kicker {
            text-transform: uppercase;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            opacity: .82;
        }

        .form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(20,35,60,.07);
        }

        .form-control,
        .form-select {
            border-color: #d7dee9;
            border-radius: 12px;
            padding: .75rem .9rem;
            color: #172033 !important;
            background-color: #fff !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 .2rem rgba(13,110,253,.12);
        }

        /* Evita el select negro que aparecía en algunas configuraciones del navegador. */
        .form-select option {
            color: #172033 !important;
            background: #fff !important;
        }

        .form-label {
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .help {
            color: var(--muted);
            font-size: .82rem;
        }

        .grade-option {
            cursor: pointer;
        }

        .grade-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .grade-box {
            border: 2px solid #e1e6ef;
            border-radius: 16px;
            padding: 1rem;
            height: 100%;
            transition: .18s ease;
            background: #fff;
        }

        .grade-box:hover {
            border-color: #9bbcff;
            transform: translateY(-2px);
        }

        .grade-option input:checked + .grade-box {
            border-color: var(--blue);
            background: #eef5ff;
            box-shadow: 0 8px 20px rgba(13,110,253,.10);
        }

        .grade-number {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: var(--blue);
            font-weight: 800;
        }

        @media (max-width: 767px) {
            .page { padding-top: 1rem; }
            .hero { padding: 1.4rem; }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid px-3 px-lg-4">

        <a class="navbar-brand fw-bold" href="<?= e($urlDashboard) ?>">
            <span class="brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </span>
            Studia360
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="text-white small d-none d-md-inline">
                <i class="bi bi-shield-check me-1"></i>
                <?= e($nombreAdmin) ?>
            </span>

            <a
                href="<?= e($urlCerrarSesion) ?>"
                class="btn btn-outline-light btn-sm"
            >
                <i class="bi bi-box-arrow-right me-1"></i>
                Cerrar sesión
            </a>
        </div>

    </div>
</nav>

<main class="page">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="<?= e($urlIndex) ?>" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>
                Centro de contenidos
            </a>
        </div>

        <a href="<?= e($urlTemas) ?>" class="btn btn-outline-primary">
            <i class="bi bi-journal-text me-1"></i>
            Ver temas
        </a>
    </div>

    <section class="hero mb-4">
        <div class="hero-kicker mb-2">Administración académica</div>
        <h1 class="fw-bold mb-2">Crear nuevo tema</h1>
        <p class="mb-0 opacity-75">
            Primero define la ubicación académica del tema.
            Después podrás construir su contenido, agregar recursos
            y publicarlo desde el editor.
        </p>
    </section>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($materias)): ?>

        <div class="alert alert-warning rounded-4 shadow-sm">
            <div class="d-flex gap-3 align-items-start">
                <i class="bi bi-info-circle-fill fs-4"></i>
                <div>
                    <strong>No hay materias disponibles.</strong>
                    <div class="small mt-1">
                        Debes crear al menos una materia antes de crear un tema.
                    </div>
                    <a
                        href="<?= e(urlAplicacion("/admin/contenidos/materias.php")) ?>"
                        class="btn btn-sm btn-warning mt-3"
                    >
                        <i class="bi bi-book me-1"></i>
                        Gestionar materias
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>

        <form method="POST" class="form-card p-4 p-lg-5">

            <div class="mb-4">
                <h2 class="h5 fw-bold mb-1">Información del tema</h2>
                <p class="help mb-0">
                    Estos datos aparecerán en los listados y en la vista del estudiante.
                </p>
            </div>

            <div class="row g-4">

                <div class="col-12">
                    <label for="nombre" class="form-label">
                        Nombre del tema
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        class="form-control form-control-lg"
                        maxlength="150"
                        value="<?= e($nombre) ?>"
                        placeholder="Ej. Biología Básica"
                        required
                    >

                    <div class="help mt-2">
                        Máximo 150 caracteres.
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="id_materia" class="form-label">
                        Materia
                    </label>

                    <select
                        id="id_materia"
                        name="id_materia"
                        class="form-select form-select-lg"
                        required
                    >
                        <option value="" disabled <?= $idMateria === "" ? "selected" : "" ?>>
                            Selecciona una materia
                        </option>

                        <?php foreach ($materias as $materia): ?>
                            <option
                                value="<?= (int)$materia["id_materia"] ?>"
                                <?= (string)$idMateria === (string)$materia["id_materia"] ? "selected" : "" ?>
                            >
                                <?= e($materia["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="help mt-2">
                        El tema quedará asociado a esta materia.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label d-block">
                        Grado
                    </label>

                    <div class="row g-3">

                        <?php
                        $grados = [
                            "9" => [
                                "nombre" => "Noveno",
                                "descripcion" => "Fundamentos y bases.",
                                "icono" => "bi-1-circle-fill"
                            ],
                            "10" => [
                                "nombre" => "Décimo",
                                "descripcion" => "Profundización.",
                                "icono" => "bi-2-circle-fill"
                            ],
                            "11" => [
                                "nombre" => "Undécimo",
                                "descripcion" => "Preparación avanzada.",
                                "icono" => "bi-3-circle-fill"
                            ]
                        ];
                        ?>

                        <?php foreach ($grados as $valor => $datos): ?>
                            <div class="col-md-4">
                                <label class="grade-option d-block position-relative">
                                    <input
                                        type="radio"
                                        name="grado"
                                        value="<?= e($valor) ?>"
                                        <?= $grado === $valor ? "checked" : "" ?>
                                        required
                                    >

                                    <div class="grade-box">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="grade-number">
                                                <i class="bi <?= e($datos["icono"]) ?>"></i>
                                            </div>

                                            <div>
                                                <div class="fw-bold">
                                                    <?= e($datos["nombre"]) ?>
                                                    <span class="text-muted">
                                                        (<?= e($valor) ?>°)
                                                    </span>
                                                </div>

                                                <div class="help">
                                                    <?= e($datos["descripcion"]) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="col-12">
                    <label for="descripcion" class="form-label">
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        class="form-control"
                        rows="5"
                        maxlength="5000"
                        placeholder="Describe brevemente qué aprenderá o trabajará el estudiante en este tema..."
                    ><?= e($descripcion) ?></textarea>

                    <div class="help mt-2">
                        Esta descripción es independiente del contenido completo que posteriormente se editará con Summernote.
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">

                <a
                    href="<?= e($urlTemas) ?>"
                    class="btn btn-outline-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="btn btn-primary px-4"
                >
                    <i class="bi bi-check-lg me-1"></i>
                    Crear tema
                </button>

            </div>

        </form>

    <?php endif; ?>

</main>

</body>
</html>

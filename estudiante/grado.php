<?php
/**
 * Studia360
 * Ruta de aprendizaje por grado y materia.
 *
 * Archivo:
 * estudiante/grado.php
 *
 * Funcionamiento:
 * - grado.php?grado=11              -> muestra todas las materias del grado.
 * - grado.php?grado=11&id_materia=1 -> muestra únicamente esa materia.
 */

declare(strict_types=1);

require_once __DIR__ . "/../includes/seguridad.php";

/* Evita que "Atrás" muestre una copia vieja del progreso. */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

exigirEstudiante();

$idUsuario = (int)($_SESSION["id_usuario"] ?? 0);
$nombres = trim((string)($_SESSION["nombres"] ?? "Estudiante"));

$grado = trim((string)($_GET["grado"] ?? ""));
$idMateria = filter_var(
    $_GET["id_materia"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idMateria || $idMateria < 1) {
    $idMateria = null;
}

$gradosPermitidos = [
    "9" => [
        "nombre" => "Noveno",
        "descripcion" => "Fortalece tus bases y comienza tu preparación.",
        "icono" => "bi-1-circle-fill"
    ],
    "10" => [
        "nombre" => "Décimo",
        "descripcion" => "Desarrolla y profundiza tus conocimientos.",
        "icono" => "bi-2-circle-fill"
    ],
    "11" => [
        "nombre" => "Undécimo",
        "descripcion" => "Prepárate para alcanzar tu mejor resultado en Saber 11°.",
        "icono" => "bi-3-circle-fill"
    ]
];

if (!isset($gradosPermitidos[$grado])) {
    header("Location: dashboard.php");
    exit;
}

$datosGrado = $gradosPermitidos[$grado];

$materias = [];
$temas = [];
$materiaSeleccionada = null;
$errores = [];

try {
    /*
     * Si viene id_materia, filtramos desde SQL.
     * Así no cargamos ni mostramos materias que el estudiante
     * no seleccionó.
     */
    $sql = "
        SELECT
            t.id_tema,
            t.id_materia,
            t.nombre AS tema,
            t.descripcion,
            t.grado,
            m.nombre AS materia,
            m.descripcion AS descripcion_materia,
            COALESCE(p.porcentaje_avance, 0) AS porcentaje_avance
        FROM temas t
        INNER JOIN materias m
            ON m.id_materia = t.id_materia
        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = :id_usuario
        WHERE t.grado = :grado
    ";

    $params = [
        ":id_usuario" => $idUsuario,
        ":grado" => $grado
    ];

    if ($idMateria !== null) {
        $sql .= " AND t.id_materia = :id_materia ";
        $params[":id_materia"] = $idMateria;
    }

    $sql .= "
        ORDER BY
            m.nombre ASC,
            t.id_tema ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $temas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($temas as $tema) {
        $materiaId = (int)$tema["id_materia"];

        if (!isset($materias[$materiaId])) {
            $materias[$materiaId] = [
                "id_materia" => $materiaId,
                "nombre" => $tema["materia"],
                "descripcion" => $tema["descripcion_materia"],
                "temas" => []
            ];
        }

        $avance = (float)$tema["porcentaje_avance"];
        $avance = max(0, min(100, $avance));
        $tema["porcentaje_avance"] = $avance;

        $materias[$materiaId]["temas"][] = $tema;
    }

    /*
     * Cuando se seleccionó una materia, validamos que exista
     * realmente dentro del grado solicitado.
     */
    if ($idMateria !== null) {
        if (isset($materias[$idMateria])) {
            $materiaSeleccionada = $materias[$idMateria];
        } else {
            /*
             * La materia no pertenece a ese grado o no existe.
             * Evitamos mostrar una pantalla incoherente.
             */
            $idMateria = null;
            $materias = [];
            $temas = [];
            $errores[] = "La materia seleccionada no tiene contenidos disponibles para este grado.";
        }
    }

} catch (PDOException $e) {
    $errores[] = "No fue posible cargar los contenidos del grado.";
}

/*
 * Progreso mostrado en la cabecera.
 * Si se está viendo una materia, corresponde solamente
 * a los temas de esa materia.
 */
$totalTemas = count($temas);
$sumaProgreso = 0;

foreach ($temas as $tema) {
    $sumaProgreso += (float)$tema["porcentaje_avance"];
}

$progresoVista = $totalTemas > 0
    ? (int)round($sumaProgreso / $totalTemas)
    : 0;

$tituloVista = $materiaSeleccionada
    ? $materiaSeleccionada["nombre"]
    : $datosGrado["nombre"];

$descripcionVista = $materiaSeleccionada
    ? ($materiaSeleccionada["descripcion"] ?: "Contenido disponible para este grado.")
    : $datosGrado["descripcion"];

function e(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($tituloVista) ?> · <?= e($datosGrado["nombre"]) ?> | Studia360
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
        :root {
            --primary: #0d6efd;
            --primary-dark: #084298;
            --background: #f4f7fb;
            --text: #162033;
            --muted: #667085;
            --border: #e5eaf1;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-size: .95rem;
        }

        .navbar-studia {
            background: linear-gradient(90deg, #0b2348, #0d6efd);
            box-shadow: 0 5px 20px rgba(13, 110, 253, .15);
        }

        .navbar-brand {
            font-weight: 800;
            letter-spacing: -.2px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 18px 60px;
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #084298);
            color: white;
            border-radius: 22px;
            padding: 30px;
            box-shadow: 0 18px 40px rgba(13, 110, 253, .17);
            overflow: hidden;
            position: relative;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
            right: -90px;
            top: -110px;
        }

        .grade-icon {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.14);
            font-size: 1.8rem;
            flex: 0 0 auto;
        }

        .hero h1 {
            font-weight: 800;
            letter-spacing: -.7px;
        }

        .hero-progress {
            min-width: 220px;
        }

        .hero-progress .progress {
            height: 8px;
            background: rgba(255,255,255,.22);
        }

        .hero-progress .progress-bar {
            background: white;
        }

        .toolbar {
            margin: 18px 0 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }

        .filter-title {
            color: var(--muted);
            font-size: .88rem;
        }

        .materia-card {
            border: 1px solid var(--border);
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 25px rgba(25, 39, 63, .06);
            overflow: hidden;
            height: 100%;
        }

        .materia-header {
            padding: 21px 22px;
            background: linear-gradient(180deg, #fff, #f8fbff);
            border-bottom: 1px solid var(--border);
        }

        .materia-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .materia-title {
            font-weight: 800;
            margin-bottom: 2px;
        }

        .tema-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 17px;
            background: white;
            height: 100%;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .tema-card:hover {
            transform: translateY(-2px);
            border-color: #b8d0ff;
            box-shadow: 0 10px 25px rgba(13,110,253,.09);
        }

        .tema-link {
            text-decoration: none;
            color: inherit;
        }

        .tema-link:hover {
            color: inherit;
        }

        .tema-title {
            font-weight: 700;
            color: #172033;
        }

        .tema-description {
            color: var(--muted);
            font-size: .88rem;
            min-height: 40px;
        }

        .progress {
            height: 7px;
            border-radius: 99px;
            background: #e9edf2;
        }

        .progress-bar {
            border-radius: 99px;
        }

        .empty-state {
            background: white;
            border: 1px dashed #cfd8e5;
            border-radius: 18px;
            padding: 55px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: #eef4ff;
            color: var(--primary);
            font-size: 1.8rem;
        }

        .materia-filter {
            border-radius: 12px;
        }

        @media (max-width: 767px) {
            .page {
                padding: 18px 12px 45px;
            }

            .hero {
                padding: 23px;
            }

            .hero-progress {
                min-width: 0;
                width: 100%;
                margin-top: 12px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-studia navbar-dark py-3">
    <div class="container-fluid px-3 px-lg-4">
        <a
            class="navbar-brand"
            href="dashboard.php"
        >
            <i class="bi bi-mortarboard-fill me-1"></i>
            Studia360
        </a>

        <div class="d-flex align-items-center gap-2 text-white">
            <span class="d-none d-sm-inline">
                <i class="bi bi-person-circle me-1"></i>
                <?= e($nombres) ?>
            </span>

            <a
                href="dashboard.php"
                class="btn btn-outline-light btn-sm"
            >
                <i class="bi bi-house-door me-1"></i>
                Inicio
            </a>

            <a
                href="../cerrar_sesion.php"
                class="btn btn-light btn-sm"
            >
                Salir
            </a>
        </div>
    </div>
</nav>

<main class="page">

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= e($error) ?>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endforeach; ?>

    <!-- HERO -->
    <section class="hero mb-4">
        <div class="row align-items-center g-4 position-relative" style="z-index:1;">

            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3">
                    <div class="grade-icon">
                        <i class="bi <?= e($datosGrado["icono"]) ?>"></i>
                    </div>

                    <div>
                        <div class="small fw-semibold opacity-75 text-uppercase">
                            Ruta de aprendizaje · <?= e($grado) ?>°
                        </div>

                        <h1 class="mb-1">
                            <?= e($tituloVista) ?>
                        </h1>

                        <p class="mb-0 opacity-75">
                            <?= e($descripcionVista) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="hero-progress ms-lg-auto">
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Progreso</span>
                        <strong><?= $progresoVista ?>%</strong>
                    </div>

                    <div class="progress">
                        <div
                            class="progress-bar"
                            style="width: <?= $progresoVista ?>%;"
                        ></div>
                    </div>

                    <div class="small opacity-75 mt-2">
                        <?= $totalTemas ?>
                        <?= $totalTemas === 1 ? "tema disponible" : "temas disponibles" ?>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- BARRA DE NAVEGACIÓN -->
    <div class="toolbar">

        <div>
            <?php if ($materiaSeleccionada): ?>
                <div class="filter-title">
                    <i class="bi bi-funnel-fill me-1"></i>
                    Mostrando únicamente:
                    <strong><?= e($materiaSeleccionada["nombre"]) ?></strong>
                </div>
            <?php else: ?>
                <div class="filter-title">
                    <i class="bi bi-grid me-1"></i>
                    Mostrando todas las materias de <?= e($datosGrado["nombre"]) ?>.
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap">

            <?php if ($materiaSeleccionada): ?>
                <a
                    href="grado.php?grado=<?= e($grado) ?>"
                    class="btn btn-outline-primary btn-sm"
                >
                    <i class="bi bi-grid me-1"></i>
                    Ver todas las materias
                </a>
            <?php endif; ?>

            <a
                href="dashboard.php"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Volver al inicio
            </a>

        </div>
    </div>

    <?php if (empty($materias)): ?>

        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-journal-x"></i>
            </div>

            <h4 class="fw-bold">No hay contenidos disponibles</h4>

            <p class="text-muted mb-3">
                Actualmente no hay temas disponibles para esta selección.
            </p>

            <?php if ($materiaSeleccionada): ?>
                <a
                    href="grado.php?grado=<?= e($grado) ?>"
                    class="btn btn-primary"
                >
                    <i class="bi bi-grid me-1"></i>
                    Ver todas las materias
                </a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($materias as $materia): ?>

                <?php
                    $temasMateria = $materia["temas"];
                    $cantidadMateria = count($temasMateria);
                    $sumaMateria = 0;

                    foreach ($temasMateria as $tm) {
                        $sumaMateria += (float)$tm["porcentaje_avance"];
                    }

                    $progresoMateria = $cantidadMateria > 0
                        ? (int)round($sumaMateria / $cantidadMateria)
                        : 0;

                    $esSeleccionada =
                        $idMateria !== null &&
                        (int)$materia["id_materia"] === $idMateria;
                ?>

                <div class="col-12">

                    <section class="materia-card">

                        <!-- CABECERA MATERIA -->
                        <div class="materia-header">

                            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">

                                <div class="materia-icon">
                                    <i class="bi bi-book-half"></i>
                                </div>

                                <div class="flex-grow-1">

                                    <div class="d-flex align-items-center gap-2 flex-wrap">

                                        <h2 class="h5 materia-title mb-0">
                                            <?= e($materia["nombre"]) ?>
                                        </h2>

                                        <?php if ($esSeleccionada): ?>
                                            <span class="badge text-bg-primary">
                                                Materia seleccionada
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <p class="text-muted small mb-0 mt-1">
                                        <?= e($materia["descripcion"] ?: "Contenido de apoyo para este grado.") ?>
                                    </p>

                                </div>

                                <div class="text-md-end">

                                    <div class="small text-muted">
                                        <?= $cantidadMateria ?>
                                        <?= $cantidadMateria === 1 ? "tema" : "temas" ?>
                                    </div>

                                    <div class="fw-bold text-primary">
                                        <?= $progresoMateria ?>%
                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- TEMAS -->
                        <div class="p-3 p-md-4">

                            <?php if (empty($temasMateria)): ?>

                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-journal-x me-1"></i>
                                    No hay temas disponibles.
                                </div>

                            <?php else: ?>

                                <div class="row g-3">

                                    <?php foreach ($temasMateria as $tema): ?>

                                        <?php
                                            $avance = (float)$tema["porcentaje_avance"];
                                            $completado = $avance >= 100;
                                        ?>

                                        <div class="col-md-6 col-xl-4">

                                            <a
                                                href="tema.php?id=<?= (int)$tema["id_tema"] ?>&return_grado=<?= urlencode($grado) ?>&return_materia=<?= (int)$tema["id_materia"] ?>"
                                                class="tema-link"
                                            >

                                                <article class="tema-card">

                                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">

                                                        <div class="tema-title">
                                                            <?= e($tema["tema"]) ?>
                                                        </div>

                                                        <span class="badge border text-dark bg-light">
                                                            <?= (int)$avance ?>%
                                                        </span>

                                                    </div>

                                                    <div class="tema-description mb-3">
                                                        <?= e($tema["descripcion"] ?: "Continúa con este contenido para avanzar en tu preparación.") ?>
                                                    </div>

                                                    <div class="progress mb-2">
                                                        <div
                                                            class="progress-bar <?= $completado ? "bg-success" : "" ?>"
                                                            style="width: <?= $avance ?>%;"
                                                        ></div>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center small">

                                                        <?php if ($completado): ?>

                                                            <span class="text-success fw-semibold">
                                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                                Completado
                                                            </span>

                                                        <?php elseif ($avance > 0): ?>

                                                            <span class="text-primary fw-semibold">
                                                                <i class="bi bi-play-circle-fill me-1"></i>
                                                                En progreso
                                                            </span>

                                                        <?php else: ?>

                                                            <span class="text-muted">
                                                                <i class="bi bi-circle me-1"></i>
                                                                Sin comenzar
                                                            </span>

                                                        <?php endif; ?>

                                                        <span class="text-primary">
                                                            Ver tema
                                                            <i class="bi bi-arrow-right ms-1"></i>
                                                        </span>

                                                    </div>

                                                </article>

                                            </a>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<script>
/* Si el navegador recupera la página desde BFCache, refrescar el progreso. */
window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>

</body>
</html>

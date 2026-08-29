<?php
/**
 * Studia360
 * Eliminación de temas
 *
 * Archivo:
 * admin/contenidos/eliminar_tema.php
 *
 * IMPORTANTE:
 * La eliminación se realiza mediante POST para evitar eliminaciones
 * accidentales mediante enlaces GET.
 */

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$urlDashboard = urlAplicacion("/admin/dashboard.php");
$urlTemas = urlAplicacion("/admin/contenidos/temas.php");
$urlMaterias = urlAplicacion("/admin/contenidos/materias.php");

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$idTema) {
    header("Location: " . $urlTemas);
    exit;
}

$tema = null;

try {
    $stmt = $conexion->prepare(
        "SELECT
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
         LIMIT 1"
    );

    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    header("Location: " . $urlTemas);
    exit;
}

if (!$tema) {
    header("Location: " . $urlTemas);
    exit;
}

/*
 * Eliminación.
 *
 * Las relaciones de contenido, recursos, evaluaciones y progreso que
 * tengan FOREIGN KEY con ON DELETE CASCADE serán eliminadas por MySQL.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $confirmacion = $_POST["confirmar"] ?? "";

    if ($confirmacion !== "ELIMINAR") {
        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/eliminar_tema.php?id=" .
                $idTema .
                "&error=confirmacion"
            )
        );
        exit;
    }

    try {

        $conexion->beginTransaction();

        /*
         * Primero eliminamos explícitamente las relaciones que pueden
         * no tener ON DELETE CASCADE en instalaciones anteriores.
         *
         * Si alguna tabla no existe en una instalación antigua, la
         * eliminación principal se intenta igualmente.
         */

        $tablasRelacionadas = [
            "contenido_temas",
            "recursos",
            "evaluaciones",
            "progreso_temas"
        ];

        foreach ($tablasRelacionadas as $tabla) {

            try {
                $stmt = $conexion->prepare(
                    "DELETE FROM `$tabla` WHERE id_tema = ?"
                );

                $stmt->execute([$idTema]);

            } catch (PDOException $e) {
                /*
                 * Si la tabla no existe o la instalación utiliza
                 * únicamente ON DELETE CASCADE, continuamos.
                 */
            }
        }

        $stmt = $conexion->prepare(
            "DELETE FROM temas
             WHERE id_tema = ?
             LIMIT 1"
        );

        $stmt->execute([$idTema]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                "No se encontró el tema para eliminar."
            );
        }

        $conexion->commit();

        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/temas.php?eliminado=1"
            )
        );
        exit;

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        header(
            "Location: " .
            urlAplicacion(
                "/admin/contenidos/eliminar_tema.php?id=" .
                $idTema .
                "&error=eliminacion"
            )
        );
        exit;
    }
}

$error = $_GET["error"] ?? "";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Eliminar tema | Studia360</title>

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
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #667085;
            --border: #e4e9f1;
            --danger: #dc3545;
            --danger-dark: #a71d2a;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .navbar {
            box-shadow: 0 4px 18px rgba(20, 35, 60, .12);
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
            min-height: calc(100vh - 62px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem 4rem;
        }

        .delete-card {
            width: 100%;
            max-width: 680px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(20,35,60,.10);
            overflow: hidden;
        }

        .delete-header {
            padding: 2rem;
            text-align: center;
            background:
                linear-gradient(
                    135deg,
                    #fff4f4,
                    #fff
                );
            border-bottom: 1px solid #f0d8db;
        }

        .danger-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1rem;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fde8ea;
            color: var(--danger);
            font-size: 2rem;
        }

        .delete-header h1 {
            font-weight: 800;
            letter-spacing: -.025em;
            margin-bottom: .45rem;
        }

        .delete-header p {
            color: var(--muted);
            margin-bottom: 0;
        }

        .delete-body {
            padding: 2rem;
        }

        .theme-info {
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 1rem 1.1rem;
            background: #fafbfd;
            margin-bottom: 1.25rem;
        }

        .theme-name {
            font-size: 1.15rem;
            font-weight: 800;
        }

        .theme-meta {
            color: var(--muted);
            font-size: .86rem;
            margin-top: .25rem;
        }

        .warning {
            border: 1px solid #f2c7cc;
            background: #fff5f6;
            border-radius: 15px;
            padding: 1rem;
            color: #7a1f29;
        }

        .warning ul {
            margin-bottom: 0;
            padding-left: 1.15rem;
        }

        .confirm-label {
            font-weight: 700;
        }

        .form-control {
            border-radius: 12px;
            padding: .75rem .9rem;
        }

        .form-control:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 .2rem rgba(220,53,69,.12);
        }

        .btn {
            border-radius: 11px;
        }

        @media (max-width: 575px) {
            .delete-header,
            .delete-body {
                padding: 1.4rem;
            }

            .delete-card {
                border-radius: 18px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid px-3 px-lg-4">

        <a
            class="navbar-brand fw-bold"
            href="<?= e($urlDashboard) ?>"
        >
            <span class="brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </span>

            Studia360
        </a>

        <a
            href="<?= e($urlTemas) ?>"
            class="btn btn-outline-light btn-sm"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Volver a temas
        </a>

    </div>
</nav>

<main class="page">

    <section class="delete-card">

        <header class="delete-header">

            <div class="danger-icon">
                <i class="bi bi-trash3-fill"></i>
            </div>

            <h1>Eliminar tema</h1>

            <p>
                Esta acción es permanente y no debe realizarse
                si todavía necesitas conservar este contenido.
            </p>

        </header>

        <div class="delete-body">

            <?php if ($error === "confirmacion"): ?>
                <div class="alert alert-warning rounded-4 border-0">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                    Debes escribir exactamente
                    <strong>ELIMINAR</strong>
                    para confirmar.
                </div>
            <?php elseif ($error === "eliminacion"): ?>
                <div class="alert alert-danger rounded-4 border-0">
                    <i class="bi bi-x-circle-fill me-1"></i>
                    No fue posible eliminar el tema.
                    Verifica las relaciones de la base de datos e inténtalo nuevamente.
                </div>
            <?php endif; ?>

            <div class="theme-info">

                <div class="theme-name">
                    <?= e($tema["nombre"]) ?>
                </div>

                <div class="theme-meta">
                    <i class="bi bi-book me-1"></i>
                    <?= e($tema["materia"]) ?>

                    <span class="mx-1">·</span>

                    <i class="bi bi-mortarboard me-1"></i>
                    <?= e($tema["grado"]) ?>°
                </div>

                <?php if (!empty($tema["descripcion"])): ?>
                    <div class="small text-muted mt-2">
                        <?= e($tema["descripcion"]) ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="warning mb-4">

                <div class="fw-bold mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Antes de continuar
                </div>

                <ul>
                    <li>
                        El tema dejará de estar disponible para los estudiantes.
                    </li>

                    <li>
                        Su contenido y los recursos asociados pueden eliminarse.
                    </li>

                    <li>
                        Las evaluaciones y relaciones vinculadas al tema
                        también pueden verse afectadas.
                    </li>

                    <li>
                        <strong>Esta operación no tiene botón de deshacer.</strong>
                    </li>
                </ul>

            </div>

            <form method="POST">

                <div class="mb-3">

                    <label
                        for="confirmar"
                        class="form-label confirm-label"
                    >
                        Escribe <strong>ELIMINAR</strong> para confirmar
                    </label>

                    <input
                        type="text"
                        id="confirmar"
                        name="confirmar"
                        class="form-control"
                        autocomplete="off"
                        placeholder="ELIMINAR"
                        required
                    >

                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">

                    <a
                        href="<?= e($urlTemas) ?>"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-x-lg me-1"></i>
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="btn btn-danger px-4"
                    >
                        <i class="bi bi-trash3-fill me-1"></i>
                        Eliminar definitivamente
                    </button>

                </div>

            </form>

        </div>

    </section>

</main>

</body>
</html>

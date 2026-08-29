<?php

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

    header("Location: materias.php");
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
| OBTENER MATERIAS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conexion->query("
        SELECT
            id_materia,
            nombre
        FROM materias
        ORDER BY nombre ASC
    ");

    $materias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "No fue posible cargar las materias."
    );
}


/*
|--------------------------------------------------------------------------
| OBTENER TEMA
|--------------------------------------------------------------------------
*/

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

    $stmt->execute([
        $idTema
    ]);

    $tema =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {

        header("Location: materias.php");
        exit;
    }

} catch (PDOException $e) {

    die(
        "No fue posible cargar el tema."
    );
}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre =
        trim($_POST["nombre"] ?? "");

    $descripcion =
        trim($_POST["descripcion"] ?? "");

    $grado =
        trim($_POST["grado"] ?? "");

    $idMateria =
        filter_var(
            $_POST["id_materia"] ?? null,
            FILTER_VALIDATE_INT
        );


    if ($nombre === "") {

        $errores[] =
            "El nombre del tema es obligatorio.";
    }

    if (mb_strlen($nombre) > 150) {

        $errores[] =
            "El nombre no puede superar los 150 caracteres.";
    }

    if (!$idMateria || $idMateria <= 0) {

        $errores[] =
            "La materia seleccionada no es válida.";
    }

    if (!in_array(
        $grado,
        ["9", "10", "11"],
        true
    )) {

        $errores[] =
            "El grado seleccionado no es válido.";
    }


    /*
     * Comprobar duplicado.
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

                $errores[] =
                    "Ya existe otro tema con ese nombre para esa materia y grado.";
            }

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible comprobar los datos.";
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
                $descripcion !== ""
                    ? $descripcion
                    : null,
                $grado,
                $idTema
            ]);

            $mensajes[] =
                "La información del tema se actualizó correctamente.";


            /*
             * Actualizar datos mostrados.
             */

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

            $stmt->execute([
                $idTema
            ]);

            $tema =
                $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible actualizar el tema.";
        }
    }
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
    Información del tema | Studia360
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
}

.navbar {
    background: #20252b;
}

.page {
    max-width: 900px;
    margin: auto;
    padding: 35px 18px 60px;
}

.card-studia {
    border: 0;
    border-radius: 20px;
    box-shadow:
        0 10px 30px rgba(20,35,60,.08);
}

</style>

</head>

<body>

<nav class="navbar navbar-dark">

<div class="container">

<a
    href="../dashboard.php"
    class="navbar-brand fw-bold"
>

<i class="bi bi-mortarboard-fill me-2"></i>

Studia360

</a>

<a
    href="temas.php?id_materia=<?= (int)$tema["id_materia"] ?>"
    class="btn btn-outline-light btn-sm"
>

<i class="bi bi-arrow-left me-1"></i>

Volver a temas

</a>

</div>

</nav>


<main class="page">

<div class="card-studia bg-white p-4 p-md-5">

<h1 class="h3 fw-bold mb-2">

<i class="bi bi-gear text-primary me-2"></i>

Información del tema

</h1>

<p class="text-secondary mb-4">

Aquí puedes modificar la estructura académica del tema.

</p>


<?php foreach ($mensajes as $mensaje): ?>

<div class="alert alert-success">

<i class="bi bi-check-circle-fill me-2"></i>

<?= e($mensaje) ?>

</div>

<?php endforeach; ?>


<?php foreach ($errores as $error): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= e($error) ?>

</div>

<?php endforeach; ?>


<form method="POST">


<div class="mb-4">

<label class="form-label fw-semibold">

Materia

</label>

<select
    name="id_materia"
    class="form-select form-select-lg"
    required
>

<?php foreach ($materias as $materia): ?>

<option
    value="<?= (int)$materia["id_materia"] ?>"
    <?= (int)$materia["id_materia"] === (int)$tema["id_materia"]
        ? "selected"
        : ""
    ?>
>

<?= e($materia["nombre"]) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="mb-4">

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


<div class="mb-4">

<label class="form-label fw-semibold">

Grado

</label>

<select
    name="grado"
    class="form-select form-select-lg"
    required
>

<option
    value="9"
    <?= $tema["grado"] === "9"
        ? "selected"
        : ""
    ?>
>
    9°
</option>

<option
    value="10"
    <?= $tema["grado"] === "10"
        ? "selected"
        : ""
    ?>
>
    10°
</option>

<option
    value="11"
    <?= $tema["grado"] === "11"
        ? "selected"
        : ""
    ?>
>
    11°
</option>

</select>

</div>


<div class="mb-4">

<label class="form-label fw-semibold">

Descripción

</label>

<textarea
    name="descripcion"
    class="form-control"
    rows="6"
><?= e($tema["descripcion"]) ?></textarea>

</div>


<div class="d-flex justify-content-between flex-wrap gap-2">

<a
    href="temas.php?id_materia=<?= (int)$tema["id_materia"] ?>"
    class="btn btn-secondary"
>

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

</main>

</body>

</html>
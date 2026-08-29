<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$mensajes = [];

$idMateria = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idMateria || $idMateria <= 0) {

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
| OBTENER MATERIA
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conexion->prepare("
        SELECT
            id_materia,
            nombre,
            descripcion
        FROM materias
        WHERE id_materia = ?
        LIMIT 1
    ");

    $stmt->execute([
        $idMateria
    ]);

    $materia =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$materia) {

        header("Location: materias.php");
        exit;
    }

} catch (PDOException $e) {

    die(
        "No fue posible cargar la materia."
    );
}


/*
|--------------------------------------------------------------------------
| GUARDAR CAMBIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre =
        trim($_POST["nombre"] ?? "");

    $descripcion =
        trim($_POST["descripcion"] ?? "");


    if ($nombre === "") {

        $errores[] =
            "El nombre es obligatorio.";
    }

    if (mb_strlen($nombre) > 100) {

        $errores[] =
            "El nombre no puede superar los 100 caracteres.";
    }


    /*
     * Comprobar duplicados.
     */

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM materias
                WHERE LOWER(nombre) = LOWER(?)
                AND id_materia <> ?
            ");

            $stmt->execute([
                $nombre,
                $idMateria
            ]);

            if ((int)$stmt->fetchColumn() > 0) {

                $errores[] =
                    "Ya existe otra materia con ese nombre.";
            }

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible validar la materia.";
        }
    }


    /*
     * Actualizar.
     */

    if (empty($errores)) {

        try {

            $stmt = $conexion->prepare("
                UPDATE materias
                SET
                    nombre = ?,
                    descripcion = ?
                WHERE id_materia = ?
            ");

            $stmt->execute([
                $nombre,
                $descripcion !== ""
                    ? $descripcion
                    : null,
                $idMateria
            ]);

            $mensajes[] =
                "La materia se actualizó correctamente.";

            $materia["nombre"] =
                $nombre;

            $materia["descripcion"] =
                $descripcion;

        } catch (PDOException $e) {

            $errores[] =
                "No fue posible actualizar la materia.";
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
    Editar materia | Studia360
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
    padding: 35px 18px;
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
    href="materias.php"
    class="btn btn-outline-light btn-sm"
>
    <i class="bi bi-arrow-left me-1"></i>
    Materias
</a>

</div>

</nav>


<main class="page">

<div class="card-studia bg-white p-4 p-md-5">

<div class="mb-4">

<h1 class="h3 fw-bold">

<i class="bi bi-pencil-square text-primary me-2"></i>

Editar materia

</h1>

<p class="text-secondary mb-0">

Modifica la información básica de esta materia.

</p>

</div>


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

Nombre de la materia

</label>

<input
    type="text"
    name="nombre"
    class="form-control form-control-lg"
    maxlength="100"
    value="<?= e($materia["nombre"]) ?>"
    required
>

</div>


<div class="mb-4">

<label class="form-label fw-semibold">

Descripción

</label>

<textarea
    name="descripcion"
    class="form-control"
    rows="6"
    placeholder="Describe la materia..."
><?= e($materia["descripcion"]) ?></textarea>

</div>


<div class="d-flex justify-content-between flex-wrap gap-2">

<a
    href="materias.php"
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
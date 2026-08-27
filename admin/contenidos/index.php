<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$temas = [];

$gradoSeleccionado =
    trim($_GET["grado"] ?? "");

$materiaSeleccionada =
    (int)($_GET["materia"] ?? 0);

$errores = [];


/*
|--------------------------------------------------------------------------
| MATERIAS
|--------------------------------------------------------------------------
*/

$materias = [];


try {

    $sqlMaterias = "

        SELECT

            id_materia,
            nombre

        FROM materias

        ORDER BY
            nombre ASC

    ";


    $stmtMaterias =
        $conexion->query(
            $sqlMaterias
        );


    $materias =
        $stmtMaterias->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar las materias.";

}


/*
|--------------------------------------------------------------------------
| TEMAS
|--------------------------------------------------------------------------
*/

try {

    $sql = "

        SELECT

            t.id_tema,
            t.nombre,
            t.descripcion,
            t.grado,
            m.nombre AS materia,

            CASE

                WHEN
                    t.contenido IS NULL
                    OR TRIM(t.contenido) = ''

                THEN 0

                ELSE 1

            END AS tiene_contenido

        FROM temas t

        INNER JOIN materias m
            ON t.id_materia = m.id_materia

        WHERE 1 = 1

    ";


    $parametros = [];


    /*
    |--------------------------------------------------------------------------
    | FILTRO GRADO
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $gradoSeleccionado,
            ["9", "10", "11"],
            true
        )
    ) {

        $sql .= "

            AND t.grado = ?

        ";

        $parametros[] =
            $gradoSeleccionado;

    }


    /*
    |--------------------------------------------------------------------------
    | FILTRO MATERIA
    |--------------------------------------------------------------------------
    */

    if (
        $materiaSeleccionada > 0
    ) {

        $sql .= "

            AND t.id_materia = ?

        ";

        $parametros[] =
            $materiaSeleccionada;

    }


    $sql .= "

        ORDER BY

            t.grado ASC,
            m.nombre ASC,
            t.nombre ASC

    ";


    $stmt =
        $conexion->prepare(
            $sql
        );


    $stmt->execute(
        $parametros
    );


    $temas =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar los temas.";

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

        Contenidos |

        ICFES Platform

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

        .navbar-brand {

            font-weight: 600;

        }


        .panel {

            border: 0;

            box-shadow:
                0 0.125rem 0.35rem
                rgba(0,0,0,.08);

        }


        .estado-contenido {

            font-size: .8rem;

        }

    </style>

</head>


<body class="bg-light">


<nav class="navbar navbar-dark bg-dark">

    <div class="container-fluid">

        <a
            href="../dashboard.php"
            class="navbar-brand"
        >

            <i
                class="bi bi-mortarboard-fill"
            ></i>

            ICFES Platform

        </a>


        <div
            class="d-flex gap-2 align-items-center"
        >

            <span
                class="text-white d-none d-md-inline"
            >

                <i
                    class="bi bi-shield-check"
                ></i>

                Administrador

            </span>


            <a
                href="../../cerrar_sesion.php"
                class="btn btn-outline-light btn-sm"
            >

                <i
                    class="bi bi-box-arrow-right"
                ></i>

                Cerrar sesión

            </a>

        </div>

    </div>

</nav>



<div class="container-fluid py-4">


    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            mb-4
        "
    >

        <div>

            <h2 class="mb-1">

                <i
                    class="
                        bi
                        bi-journal-richtext
                    "
                ></i>

                Contenidos académicos

            </h2>


            <p class="text-muted mb-0">

                Administra el contenido educativo
                de los temas.

            </p>

        </div>


        <a
            href="../dashboard.php"
            class="btn btn-outline-secondary"
        >

            <i
                class="bi bi-arrow-left"
            ></i>

            Volver

        </a>

    </div>



    <?php foreach (
        $errores
        as $error
    ): ?>

        <div
            class="alert alert-danger"
        >

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endforeach; ?>



    <!-- FILTROS -->

    <div
        class="
            card
            panel
            mb-4
        "
    >

        <div class="card-body">


            <form
                method="GET"
                class="row g-3"
            >


                <div
                    class="col-md-4"
                >

                    <label
                        class="form-label"
                    >

                        Grado

                    </label>


                    <select
                        name="grado"
                        class="form-select"
                    >

                        <option value="">

                            Todos los grados

                        </option>


                        <option
                            value="9"
                            <?= $gradoSeleccionado === "9"
                                ? "selected"
                                : "" ?>
                        >

                            9°

                        </option>


                        <option
                            value="10"
                            <?= $gradoSeleccionado === "10"
                                ? "selected"
                                : "" ?>
                        >

                            10°

                        </option>


                        <option
                            value="11"
                            <?= $gradoSeleccionado === "11"
                                ? "selected"
                                : "" ?>
                        >

                            11°

                        </option>

                    </select>

                </div>



                <div
                    class="col-md-4"
                >

                    <label
                        class="form-label"
                    >

                        Materia

                    </label>


                    <select
                        name="materia"
                        class="form-select"
                    >

                        <option value="0">

                            Todas las materias

                        </option>


                        <?php foreach (
                            $materias
                            as $materia
                        ): ?>

                            <option
                                value="<?= (int)$materia["id_materia"] ?>"
                                <?= $materiaSeleccionada ===
                                    (int)$materia["id_materia"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $materia["nombre"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div
                    class="
                        col-md-4
                        d-flex
                        align-items-end
                    "
                >

                    <button
                        type="submit"
                        class="
                            btn
                            btn-primary
                            w-100
                        "
                    >

                        <i
                            class="bi bi-funnel-fill"
                        ></i>

                        Filtrar

                    </button>

                </div>


            </form>

        </div>

    </div>



    <!-- TEMAS -->

    <div
        class="
            card
            panel
        "
    >

        <div class="card-body">


            <div
                class="
                    d-flex
                    justify-content-between
                    align-items-center
                    mb-3
                "
            >

                <h5 class="mb-0">

                    Temas

                </h5>


                <span
                    class="badge text-bg-primary"
                >

                    <?= count($temas) ?>

                </span>

            </div>



            <?php if (
                empty($temas)
            ): ?>

                <div
                    class="
                        text-center
                        py-5
                        text-muted
                    "
                >

                    <i
                        class="
                            bi
                            bi-journal-x
                            fs-1
                        "
                    ></i>


                    <p class="mt-3 mb-0">

                        No se encontraron temas.

                    </p>

                </div>


            <?php else: ?>


                <div
                    class="table-responsive"
                >

                    <table
                        class="
                            table
                            table-hover
                            align-middle
                        "
                    >

                        <thead>

                            <tr>

                                <th>
                                    Grado
                                </th>

                                <th>
                                    Materia
                                </th>

                                <th>
                                    Tema
                                </th>

                                <th>
                                    Contenido
                                </th>

                                <th
                                    class="text-end"
                                >
                                    Acción
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $temas
                                as $tema
                            ): ?>


                                <tr>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                text-bg-light
                                                border
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $tema["grado"]
                                            ) ?>°

                                        </span>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $tema["materia"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $tema["nombre"]
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $tema["descripcion"]
                                            )
                                        ): ?>

                                            <div
                                                class="
                                                    small
                                                    text-muted
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $tema["descripcion"]
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            (int)$tema[
                                                "tiene_contenido"
                                            ] === 1
                                        ): ?>

                                            <span
                                                class="
                                                    badge
                                                    text-bg-success
                                                    estado-contenido
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-check-circle-fill
                                                    "
                                                ></i>

                                                Publicado

                                            </span>


                                        <?php else: ?>

                                            <span
                                                class="
                                                    badge
                                                    text-bg-warning
                                                    estado-contenido
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-pencil-fill
                                                    "
                                                ></i>

                                                Sin contenido

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td
                                        class="text-end"
                                    >

                                        <a
                                            href="editar_tema.php?id=<?= (int)$tema["id_tema"] ?>"
                                            class="
                                                btn
                                                btn-primary
                                                btn-sm
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    bi-pencil-square
                                                "
                                            ></i>

                                            Editar

                                        </a>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>

    </div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
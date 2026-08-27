<?php

require_once __DIR__ . "/includes/seguridad.php";

exigirEstudiante();


/*
|--------------------------------------------------------------------------
| DATOS DEL ESTUDIANTE
|--------------------------------------------------------------------------
*/

$idUsuario =
    (int)($_SESSION["id_usuario"] ?? 0);

$nombres =
    trim(
        $_SESSION["nombres"]
        ?? "Estudiante"
    );


/*
|--------------------------------------------------------------------------
| ID DEL TEMA
|--------------------------------------------------------------------------
*/

$idTema =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (
    !$idTema ||
    $idTema <= 0
) {

    header(
        "Location: dashboard.php"
    );

    exit;

}


$tema = null;

$recursos = [];

$errores = [];

$avance = 0;


/*
|--------------------------------------------------------------------------
| OBTENER TEMA
|--------------------------------------------------------------------------
*/

try {

    $sqlTema = "
        SELECT
            t.id_tema,
            t.id_materia,
            t.nombre,
            t.descripcion,
            t.contenido,
            t.grado,

            m.nombre AS materia,
            m.descripcion AS descripcion_materia

        FROM temas t

        INNER JOIN materias m
            ON t.id_materia = m.id_materia

        WHERE t.id_tema = ?

        LIMIT 1
    ";


    $stmtTema =
        $conexion->prepare(
            $sqlTema
        );


    $stmtTema->execute([
        $idTema
    ]);


    $tema =
        $stmtTema->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$tema) {

        header(
            "Location: dashboard.php"
        );

        exit;

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar el tema.";

}


/*
|--------------------------------------------------------------------------
| PROGRESO
|--------------------------------------------------------------------------
*/

if ($tema) {

    try {

        $sqlProgreso = "
            SELECT
                porcentaje_avance
            FROM progreso

            WHERE id_usuario = ?
            AND id_tema = ?

            LIMIT 1
        ";


        $stmtProgreso =
            $conexion->prepare(
                $sqlProgreso
            );


        $stmtProgreso->execute([
            $idUsuario,
            $idTema
        ]);


        $resultadoProgreso =
            $stmtProgreso->fetch(
                PDO::FETCH_ASSOC
            );


        if ($resultadoProgreso) {

            $avance =
                (float)
                $resultadoProgreso[
                    "porcentaje_avance"
                ];

        }


    } catch (PDOException $e) {

        $avance = 0;

    }

}


/*
|--------------------------------------------------------------------------
| NORMALIZAR AVANCE
|--------------------------------------------------------------------------
*/

if ($avance < 0) {

    $avance = 0;

}


if ($avance > 100) {

    $avance = 100;

}


/*
|--------------------------------------------------------------------------
| OBTENER RECURSOS
|--------------------------------------------------------------------------
*/

if ($tema) {

    try {

        $sqlRecursos = "
            SELECT
                id_recurso,
                titulo,
                tipo,
                url,
                descripcion,
                imagen,
                autor,
                fuente

            FROM recursos

            WHERE id_tema = ?

            AND estado = 'Activo'

            ORDER BY
                fecha_publicacion DESC,
                id_recurso DESC
        ";


        $stmtRecursos =
            $conexion->prepare(
                $sqlRecursos
            );


        $stmtRecursos->execute([
            $idTema
        ]);


        $recursos =
            $stmtRecursos->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        $recursos = [];

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

        <?= htmlspecialchars(
            $tema["nombre"]
        ) ?>

        |

        ICFES Platform

    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        body {

            background:
                #f5f7fb;

            color:
                #212529;

        }


        .navbar-brand {

            font-weight: 600;

        }


        .hero-tema {

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #084298
                );

            color:
                white;

            border:
                0;

            border-radius:
                18px;

        }


        .contenido-card {

            border:
                0;

            border-radius:
                16px;

            box-shadow:
                0 0.35rem 1rem
                rgba(
                    0,
                    0,
                    0,
                    0.07
                );

        }


        .contenido-tema {

            font-size:
                17px;

            line-height:
                1.8;

        }


        .contenido-tema h1 {

            margin-top:
                1.5rem;

            margin-bottom:
                1rem;

        }


        .contenido-tema h2 {

            margin-top:
                1.8rem;

            margin-bottom:
                1rem;

        }


        .contenido-tema h3 {

            margin-top:
                1.5rem;

            margin-bottom:
                0.8rem;

        }


        .contenido-tema img {

            max-width:
                100%;

            height:
                auto;

            border-radius:
                10px;

        }


        .contenido-tema table {

            width:
                100%;

            margin:
                20px 0;

            border-collapse:
                collapse;

        }


        .contenido-tema table td,
        .contenido-tema table th {

            border:
                1px solid #dee2e6;

            padding:
                10px;

        }


        .contenido-tema blockquote {

            border-left:
                4px solid #0d6efd;

            padding:
                10px 20px;

            background:
                #f0f5ff;

            border-radius:
                8px;

        }


        .contenido-tema iframe {

            width:
                100%;

            max-width:
                800px;

            min-height:
                450px;

            border:
                0;

            border-radius:
                12px;

        }


        /*
        |--------------------------------------------------------------------------
        | BLOQUES EDUCATIVOS
        |--------------------------------------------------------------------------
        */

        .info-box {

            padding:
                18px;

            border-radius:
                10px;

            margin:
                20px 0;

            border-left:
                5px solid #0d6efd;

            background:
                #e9f2ff;

        }


        .important-box {

            padding:
                18px;

            border-radius:
                10px;

            margin:
                20px 0;

            border-left:
                5px solid #dc3545;

            background:
                #fff0f1;

        }


        .example-box {

            padding:
                18px;

            border-radius:
                10px;

            margin:
                20px 0;

            border-left:
                5px solid #198754;

            background:
                #eaf7ef;

        }


        .exercise-box {

            padding:
                18px;

            border-radius:
                10px;

            margin:
                20px 0;

            border-left:
                5px solid #ffc107;

            background:
                #fff9e6;

        }


        .remember-box {

            padding:
                18px;

            border-radius:
                10px;

            margin:
                20px 0;

            border-left:
                5px solid #6f42c1;

            background:
                #f4efff;

        }


        .bloque-label {

            font-weight:
                700;

            margin-bottom:
                8px;

        }


        .barra-progreso {

            height:
                9px;

            border-radius:
                20px;

        }


        .recurso-card {

            border:
                0;

            border-radius:
                12px;

            box-shadow:
                0 0.25rem 0.7rem
                rgba(
                    0,
                    0,
                    0,
                    0.06
                );

            height:
                100%;

        }


        .tipo-recurso {

            font-size:
                0.75rem;

            text-transform:
                uppercase;

            letter-spacing:
                0.04em;

        }


        @media (
            max-width: 767px
        ) {

            .contenido-tema {

                font-size:
                    16px;

            }


            .contenido-tema iframe {

                min-height:
                    250px;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav
    class="navbar navbar-dark bg-primary"
>

    <div class="container">

        <a
            href="dashboard.php"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            ICFES Platform

        </a>


        <div
            class="d-flex align-items-center gap-2"
        >

            <span
                class="text-white d-none d-md-inline"
            >

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $nombres
                ) ?>

            </span>


            <a
                href="cerrar_sesion.php"
                class="btn btn-light btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                Cerrar sesión

            </a>

        </div>

    </div>

</nav>



<div class="container py-4">


    <!-- =====================================================
         ERRORES
    ====================================================== -->

    <?php foreach (
        $errores
        as $error
    ): ?>

        <div
            class="alert alert-danger"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endforeach; ?>



    <!-- =====================================================
         BOTÓN VOLVER
    ====================================================== -->

    <div class="mb-3">

        <a
            href="grado.php?grado=<?= urlencode(
                $tema["grado"]
            ) ?>"
            class="btn btn-outline-primary"
        >

            <i class="bi bi-arrow-left"></i>

            Volver a <?= htmlspecialchars(
                $tema["grado"]
            ) ?>°

        </a>

    </div>



    <!-- =====================================================
         CABECERA DEL TEMA
    ====================================================== -->

    <div
        class="card hero-tema shadow-sm mb-4"
    >

        <div class="card-body p-4 p-md-5">

            <div class="small opacity-75 mb-2">

                <?= htmlspecialchars(
                    $tema["materia"]
                ) ?>

                ·

                <?= htmlspecialchars(
                    $tema["grado"]
                ) ?>°

            </div>


            <h1 class="mb-3">

                <?= htmlspecialchars(
                    $tema["nombre"]
                ) ?>

            </h1>


            <?php if (
                !empty(
                    $tema["descripcion"]
                )
            ): ?>

                <p
                    class="mb-0 opacity-75"
                >

                    <?= htmlspecialchars(
                        $tema["descripcion"]
                    ) ?>

                </p>

            <?php endif; ?>

        </div>

    </div>



    <!-- =====================================================
         PROGRESO
    ====================================================== -->

    <div
        class="card contenido-card mb-4"
    >

        <div class="card-body">

            <div
                class="d-flex justify-content-between align-items-center mb-2"
            >

                <span
                    class="text-muted"
                >

                    Tu progreso

                </span>


                <strong>

                    <?= (int)$avance ?>%

                </strong>

            </div>


            <div
                class="progress barra-progreso"
            >

                <div
                    class="progress-bar"
                    role="progressbar"
                    style="width: <?= (float)$avance ?>%;"
                    aria-valuenow="<?= (float)$avance ?>"
                    aria-valuemin="0"
                    aria-valuemax="100"
                ></div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         CONTENIDO EDUCATIVO
    ====================================================== -->

    <div
        class="card contenido-card mb-5"
    >

        <div
            class="card-body p-4 p-md-5"
        >

            <?php if (
                !empty(
                    trim(
                        $tema["contenido"]
                        ?? ""
                    )
                )
            ): ?>

                <article
                    class="contenido-tema"
                >

                    <?= $tema["contenido"] ?>

                </article>

            <?php else: ?>

                <div
                    class="text-center py-5"
                >

                    <i
                        class="bi bi-journal-text fs-1 text-muted"
                    ></i>


                    <h4 class="mt-3">

                        Contenido en preparación

                    </h4>


                    <p
                        class="text-muted mb-0"
                    >

                        Este tema todavía no tiene
                        una lección desarrollada.

                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>



    <!-- =====================================================
         RECURSOS
    ====================================================== -->

    <?php if (
        !empty($recursos)
    ): ?>

        <div class="mb-5">

            <div class="mb-3">

                <h3>

                    <i class="bi bi-collection-play"></i>

                    Recursos complementarios

                </h3>


                <p
                    class="text-muted"
                >

                    Material adicional para
                    profundizar en este tema.

                </p>

            </div>


            <div class="row g-4">

                <?php foreach (
                    $recursos
                    as $recurso
                ): ?>

                    <div
                        class="col-md-6 col-lg-4"
                    >

                        <div
                            class="card recurso-card"
                        >

                            <?php if (
                                !empty(
                                    $recurso["imagen"]
                                )
                            ): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        $recurso["imagen"]
                                    ) ?>"
                                    class="card-img-top"
                                    alt="<?= htmlspecialchars(
                                        $recurso["titulo"]
                                    ) ?>"
                                >

                            <?php endif; ?>


                            <div class="card-body">

                                <div
                                    class="tipo-recurso text-primary mb-2"
                                >

                                    <?= htmlspecialchars(
                                        $recurso["tipo"]
                                    ) ?>

                                </div>


                                <h5>

                                    <?= htmlspecialchars(
                                        $recurso["titulo"]
                                    ) ?>

                                </h5>


                                <?php if (
                                    !empty(
                                        $recurso["descripcion"]
                                    )
                                ): ?>

                                    <p
                                        class="text-muted"
                                    >

                                        <?= htmlspecialchars(
                                            $recurso["descripcion"]
                                        ) ?>

                                    </p>

                                <?php endif; ?>


                                <a
                                    href="<?= htmlspecialchars(
                                        $recurso["url"]
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-outline-primary"
                                >

                                    Abrir recurso

                                    <i
                                        class="bi bi-box-arrow-up-right"
                                    ></i>

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>



</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
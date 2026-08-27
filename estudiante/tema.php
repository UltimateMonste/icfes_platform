<?php

require_once __DIR__ . "/../includes/seguridad.php";

exigirEstudiante();


/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$idUsuario =
    (int)($_SESSION["id_usuario"] ?? 0);

$nombres =
    trim($_SESSION["nombres"] ?? "Estudiante");


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


if (!$idTema || $idTema <= 0) {

    header(
        "Location: dashboard.php"
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$tema = null;

$recursos = [];

$evaluaciones = [];

$progreso = 0;

$error = null;


/*
|--------------------------------------------------------------------------
| CONSULTAR TEMA
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | TEMA + MATERIA
    |--------------------------------------------------------------------------
    */

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

        WHERE t.id_tema = :id_tema

        LIMIT 1

    ";


    $stmtTema =
        $conexion->prepare(
            $sqlTema
        );


    $stmtTema->execute([

        ":id_tema" =>
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


    /*
    |--------------------------------------------------------------------------
    | PROGRESO DEL ESTUDIANTE
    |--------------------------------------------------------------------------
    */

    $sqlProgreso = "

        SELECT

            porcentaje_avance,
            recursos_vistos,
            evaluaciones_realizadas,
            ultima_actividad

        FROM progreso

        WHERE id_usuario = :id_usuario

        AND id_tema = :id_tema

        LIMIT 1

    ";


    $stmtProgreso =
        $conexion->prepare(
            $sqlProgreso
        );


    $stmtProgreso->execute([

        ":id_usuario" =>
            $idUsuario,

        ":id_tema" =>
            $idTema

    ]);


    $datosProgreso =
        $stmtProgreso->fetch(
            PDO::FETCH_ASSOC
        );


    if ($datosProgreso) {

        $progreso =
            (float)(
                $datosProgreso[
                    "porcentaje_avance"
                ]
                ?? 0
            );

    }


    if ($progreso < 0) {

        $progreso = 0;

    }


    if ($progreso > 100) {

        $progreso = 100;

    }


    /*
    |--------------------------------------------------------------------------
    | RECURSOS
    |--------------------------------------------------------------------------
    */

    $sqlRecursos = "

        SELECT

            id_recurso,
            titulo,
            tipo,
            url,
            descripcion,
            imagen,
            autor,
            fuente,
            fecha_publicacion,
            orden

        FROM recursos

        WHERE id_tema = :id_tema

        AND estado = 'Activo'

        ORDER BY

            orden ASC,
            id_recurso ASC

    ";


    $stmtRecursos =
        $conexion->prepare(
            $sqlRecursos
        );


    $stmtRecursos->execute([

        ":id_tema" =>
            $idTema

    ]);


    $recursos =
        $stmtRecursos->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | EVALUACIONES
    |--------------------------------------------------------------------------
    */

    $sqlEvaluaciones = "

        SELECT

            id_evaluacion,
            titulo,
            tipo,
            descripcion,
            tiempo_limite,
            intentos_permitidos,
            puntaje_maximo,
            estado

        FROM evaluaciones

        WHERE id_tema = :id_tema

        AND estado = 'Activo'

        ORDER BY
            id_evaluacion ASC

    ";


    $stmtEvaluaciones =
        $conexion->prepare(
            $sqlEvaluaciones
        );


    $stmtEvaluaciones->execute([

        ":id_tema" =>
            $idTema

    ]);


    $evaluaciones =
        $stmtEvaluaciones->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $error =
        "No fue posible cargar el contenido del tema.";

}


/*
|--------------------------------------------------------------------------
| DATOS VISUALES
|--------------------------------------------------------------------------
*/

$estadoProgreso =
    "Sin comenzar";


if ($progreso >= 100) {

    $estadoProgreso =
        "Completado";

} elseif ($progreso > 0) {

    $estadoProgreso =
        "En progreso";

}


/*
|--------------------------------------------------------------------------
| ICONOS DE RECURSOS
|--------------------------------------------------------------------------
*/

function iconoRecurso(
    string $tipo
): string {

    return match (
        strtolower($tipo)
    ) {

        "video" =>
            "bi-play-btn-fill",

        "articulo" =>
            "bi-file-text-fill",

        "blog" =>
            "bi-journal-text",

        "app" =>
            "bi-phone-fill",

        "pdf" =>
            "bi-file-earmark-pdf-fill",

        "juego" =>
            "bi-controller",

        "simulador" =>
            "bi-display-fill",

        "presentacion" =>
            "bi-easel-fill",

        default =>
            "bi-link-45deg"

    };

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

            background: #f5f7fb;

            color: #212529;

        }


        .navbar-brand {

            font-weight: 600;

        }


        .cabecera-tema {

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #084298
                );

            color: white;

            border: 0;

            border-radius: 16px;

        }


        .etiqueta-materia {

            font-size: 0.78rem;

            text-transform: uppercase;

            letter-spacing: 0.05em;

            opacity: 0.8;

        }


        .contenido-card {

            border: 0;

            border-radius: 16px;

            box-shadow:
                0 0.15rem 0.5rem
                rgba(0, 0, 0, 0.06);

        }


        .contenido-educativo {

            font-size: 1.05rem;

            line-height: 1.8;

        }


        .contenido-educativo img {

            max-width: 100%;

            height: auto;

            border-radius: 10px;

        }


        .contenido-educativo table {

            width: 100%;

            margin: 1rem 0;

            border-collapse: collapse;

        }


        .contenido-educativo th,
        .contenido-educativo td {

            border:
                1px solid #dee2e6;

            padding: 0.65rem;

        }


        .contenido-educativo blockquote {

            border-left:
                4px solid #0d6efd;

            padding-left: 1rem;

            color: #6c757d;

            font-style: italic;

        }


        .recurso-card {

            border:
                1px solid #e9ecef;

            border-radius: 12px;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .recurso-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 0.4rem 1rem
                rgba(0, 0, 0, 0.08);

        }


        .icono-recurso {

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e9f2ff;

            color: #0d6efd;

            border-radius: 10px;

            font-size: 1.3rem;

            flex-shrink: 0;

        }


        .barra-progreso {

            height: 10px;

            border-radius: 20px;

        }


        .card-lateral {

            border: 0;

            border-radius: 16px;

            box-shadow:
                0 0.15rem 0.5rem
                rgba(0, 0, 0, 0.06);

        }


        .evaluacion-card {

            border:
                1px solid #e9ecef;

            border-radius: 12px;

        }


        @media (max-width: 767px) {

            .contenido-educativo {

                font-size: 1rem;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-dark bg-primary">

    <div class="container">

        <a
            href="dashboard.php"
            class="navbar-brand"
        >

            <i
                class="bi bi-mortarboard-fill"
            ></i>

            ICFES Platform

        </a>


        <div
            class="d-flex align-items-center gap-2"
        >

            <span
                class="text-white d-none d-md-inline"
            >

                <i
                    class="bi bi-person-circle"
                ></i>

                <?= htmlspecialchars(
                    $nombres
                ) ?>

            </span>


            <a
                href="../cerrar_sesion.php"
                class="btn btn-light btn-sm"
            >

                <i
                    class="bi bi-box-arrow-right"
                ></i>

                Cerrar sesión

            </a>

        </div>

    </div>

</nav>



<!-- =========================================================
     CONTENIDO
========================================================= -->

<div class="container py-4">


    <?php if ($error): ?>

        <div
            class="alert alert-danger"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php else: ?>


        <!-- =================================================
             VOLVER
        ================================================== -->

        <div class="mb-3">

            <a
                href="grado.php?grado=<?= urlencode($tema["grado"]) ?>"
                class="btn btn-outline-primary btn-sm"
            >

                <i
                    class="bi bi-arrow-left"
                ></i>

                Volver a
                <?= htmlspecialchars(
                    $tema["grado"]
                ) ?>°

            </a>

        </div>



        <!-- =================================================
             CABECERA
        ================================================== -->

        <div
            class="card cabecera-tema shadow-sm mb-4"
        >

            <div class="card-body p-4">

                <div class="row align-items-center">


                    <div class="col-lg-8">


                        <div
                            class="etiqueta-materia mb-2"
                        >

                            <?= htmlspecialchars(
                                $tema["materia"]
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                $tema["grado"]
                            ) ?>°

                        </div>


                        <h1 class="mb-2">

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


                    <div
                        class="col-lg-4 mt-4 mt-lg-0"
                    >

                        <div>

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    mb-2
                                "
                            >

                                <span>

                                    Tu progreso

                                </span>


                                <strong>

                                    <?= $progreso ?>%

                                </strong>

                            </div>


                            <div
                                class="
                                    progress
                                    barra-progreso
                                    bg-white
                                    bg-opacity-25
                                "
                            >

                                <div
                                    class="
                                        progress-bar
                                        bg-white
                                    "
                                    role="progressbar"
                                    style="
                                        width:
                                        <?= $progreso ?>%;
                                    "
                                    aria-valuenow="<?= $progreso ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>

                            </div>


                            <div
                                class="small mt-2 opacity-75"
                            >

                                <?= htmlspecialchars(
                                    $estadoProgreso
                                ) ?>

                            </div>

                        </div>

                    </div>


                </div>

            </div>

        </div>



        <!-- =================================================
             CONTENIDO + LATERAL
        ================================================== -->

        <div class="row g-4">


            <!-- =================================================
                 CONTENIDO PRINCIPAL
            ================================================== -->

            <div class="col-lg-8">


                <div
                    class="
                        card
                        contenido-card
                        mb-4
                    "
                >

                    <div
                        class="card-body p-4 p-md-5"
                    >


                        <div
                            class="
                                d-flex
                                align-items-center
                                gap-2
                                mb-4
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-book-half
                                    text-primary
                                    fs-4
                                "
                            ></i>


                            <h3 class="mb-0">

                                Aprende

                            </h3>

                        </div>


                        <?php if (
                            !empty(
                                trim(
                                    (string)$tema[
                                        "contenido"
                                    ]
                                )
                            )
                        ): ?>

                            <div
                                class="contenido-educativo"
                            >

                                <?= $tema["contenido"] ?>

                            </div>


                        <?php else: ?>


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
                                        bi-journal-text
                                        fs-1
                                    "
                                ></i>


                                <h5 class="mt-3">

                                    Contenido en preparación

                                </h5>


                                <p class="mb-0">

                                    Este tema todavía no
                                    tiene contenido educativo
                                    publicado.

                                </p>

                            </div>


                        <?php endif; ?>


                    </div>

                </div>



                <!-- =================================================
                     RECURSOS
                ================================================== -->

                <?php if (
                    !empty($recursos)
                ): ?>


                    <div
                        class="
                            card
                            contenido-card
                            mb-4
                        "
                    >

                        <div class="card-body p-4">


                            <div
                                class="
                                    d-flex
                                    align-items-center
                                    gap-2
                                    mb-4
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-collection-play-fill
                                        text-primary
                                        fs-4
                                    "
                                ></i>


                                <h3 class="mb-0">

                                    Recursos complementarios

                                </h3>

                            </div>


                            <div
                                class="row g-3"
                            >


                                <?php foreach (
                                    $recursos
                                    as $recurso
                                ): ?>


                                    <div
                                        class="col-12"
                                    >

                                        <div
                                            class="
                                                recurso-card
                                                p-3
                                            "
                                        >

                                            <div
                                                class="
                                                    d-flex
                                                    align-items-start
                                                    gap-3
                                                "
                                            >


                                                <div
                                                    class="icono-recurso"
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            <?= iconoRecurso(
                                                                $recurso["tipo"]
                                                            ) ?>
                                                        "
                                                    ></i>

                                                </div>


                                                <div
                                                    class="flex-grow-1"
                                                >

                                                    <h5
                                                        class="mb-1"
                                                    >

                                                        <?= htmlspecialchars(
                                                            $recurso["titulo"]
                                                        ) ?>

                                                    </h5>


                                                    <div
                                                        class="
                                                            small
                                                            text-muted
                                                            mb-2
                                                        "
                                                    >

                                                        <?= htmlspecialchars(
                                                            ucfirst(
                                                                $recurso["tipo"]
                                                            )
                                                        ) ?>


                                                        <?php if (
                                                            !empty(
                                                                $recurso["autor"]
                                                            )
                                                        ): ?>

                                                            ·

                                                            <?= htmlspecialchars(
                                                                $recurso["autor"]
                                                            ) ?>

                                                        <?php endif; ?>

                                                    </div>


                                                    <?php if (
                                                        !empty(
                                                            $recurso["descripcion"]
                                                        )
                                                    ): ?>

                                                        <p
                                                            class="
                                                                text-muted
                                                                mb-3
                                                            "
                                                        >

                                                            <?= htmlspecialchars(
                                                                $recurso["descripcion"]
                                                            ) ?>

                                                        </p>

                                                    <?php endif; ?>


                                                    <?php if (
                                                        !empty(
                                                            $recurso["url"]
                                                        )
                                                    ): ?>

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $recurso["url"]
                                                            ) ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="
                                                                btn
                                                                btn-outline-primary
                                                                btn-sm
                                                            "
                                                        >

                                                            <i
                                                                class="
                                                                    bi
                                                                    bi-box-arrow-up-right
                                                                "
                                                            ></i>

                                                            Abrir recurso

                                                        </a>

                                                    <?php endif; ?>


                                                </div>


                                            </div>

                                        </div>

                                    </div>


                                <?php endforeach; ?>


                            </div>

                        </div>

                    </div>


                <?php endif; ?>



                <!-- =================================================
                     EVALUACIONES
                ================================================== -->

                <?php if (
                    !empty($evaluaciones)
                ): ?>


                    <div
                        class="
                            card
                            contenido-card
                            mb-4
                        "
                    >

                        <div class="card-body p-4">


                            <div
                                class="
                                    d-flex
                                    align-items-center
                                    gap-2
                                    mb-4
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-clipboard-check-fill
                                        text-primary
                                        fs-4
                                    "
                                ></i>


                                <h3 class="mb-0">

                                    Evaluaciones

                                </h3>

                            </div>


                            <?php foreach (
                                $evaluaciones
                                as $evaluacion
                            ): ?>


                                <div
                                    class="
                                        evaluacion-card
                                        p-3
                                        mb-3
                                    "
                                >

                                    <div
                                        class="
                                            d-flex
                                            justify-content-between
                                            align-items-start
                                            gap-3
                                        "
                                    >

                                        <div>

                                            <h5
                                                class="mb-1"
                                            >

                                                <?= htmlspecialchars(
                                                    $evaluacion["titulo"]
                                                ) ?>

                                            </h5>


                                            <div
                                                class="
                                                    small
                                                    text-muted
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $evaluacion["tipo"]
                                                    )
                                                ) ?>


                                                <?php if (
                                                    $evaluacion[
                                                        "tiempo_limite"
                                                    ]
                                                    !== null
                                                ): ?>

                                                    ·

                                                    <?= (int)$evaluacion[
                                                        "tiempo_limite"
                                                    ] ?>

                                                    minutos

                                                <?php endif; ?>


                                                <?php if (
                                                    $evaluacion[
                                                        "puntaje_maximo"
                                                    ]
                                                    !== null
                                                ): ?>

                                                    ·

                                                    <?= htmlspecialchars(
                                                        $evaluacion[
                                                            "puntaje_maximo"
                                                        ]
                                                    ) ?>

                                                    puntos

                                                <?php endif; ?>

                                            </div>


                                            <?php if (
                                                !empty(
                                                    $evaluacion[
                                                        "descripcion"
                                                    ]
                                                )
                                            ): ?>

                                                <p
                                                    class="
                                                        text-muted
                                                        mt-2
                                                        mb-0
                                                    "
                                                >

                                                    <?= htmlspecialchars(
                                                        $evaluacion[
                                                            "descripcion"
                                                        ]
                                                    ) ?>

                                                </p>

                                            <?php endif; ?>

                                        </div>


                                        <a
                                            href="#"
                                            class="
                                                btn
                                                btn-primary
                                                btn-sm
                                            "
                                        >

                                            Comenzar

                                        </a>

                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 COLUMNA LATERAL
            ================================================== -->

            <div class="col-lg-4">


                <div
                    class="
                        card
                        card-lateral
                        mb-4
                    "
                >

                    <div class="card-body p-4">


                        <h5>

                            <i
                                class="
                                    bi
                                    bi-graph-up-arrow
                                    text-primary
                                "
                            ></i>

                            Tu avance

                        </h5>


                        <div
                            class="
                                display-5
                                fw-bold
                                text-primary
                                my-3
                            "
                        >

                            <?= $progreso ?>%

                        </div>


                        <div
                            class="
                                progress
                                barra-progreso
                                mb-2
                            "
                        >

                            <div
                                class="progress-bar"
                                style="
                                    width:
                                    <?= $progreso ?>%;
                                "
                            ></div>

                        </div>


                        <div
                            class="small text-muted"
                        >

                            <?= htmlspecialchars(
                                $estadoProgreso
                            ) ?>

                        </div>


                    </div>

                </div>



                <div
                    class="
                        card
                        card-lateral
                    "
                >

                    <div class="card-body p-4">


                        <h5 class="mb-3">

                            <i
                                class="
                                    bi
                                    bi-info-circle-fill
                                    text-primary
                                "
                            ></i>

                            Información

                        </h5>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                mb-2
                            "
                        >

                            <span
                                class="text-muted"
                            >

                                Grado

                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $tema["grado"]
                                ) ?>°

                            </strong>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                            "
                        >

                            <span
                                class="text-muted"
                            >

                                Materia

                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $tema["materia"]
                                ) ?>

                            </strong>

                        </div>


                    </div>

                </div>


            </div>


        </div>


    <?php endif; ?>


</div>



<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
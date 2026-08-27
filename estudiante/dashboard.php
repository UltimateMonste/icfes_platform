<?php

require_once __DIR__ . "/../includes/seguridad.php";

exigirEstudiante();


/*
|--------------------------------------------------------------------------
| DATOS DEL ESTUDIANTE
|--------------------------------------------------------------------------
*/

$idUsuario =
    (int)($_SESSION["id_usuario"] ?? 0);

$nombres =
    trim($_SESSION["nombres"] ?? "Estudiante");

$puntos =
    (int)($_SESSION["puntos"] ?? 0);

$nivel =
    (int)($_SESSION["nivel"] ?? 1);

$gradoEstudiante =
    trim($_SESSION["grado"] ?? "");


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$errores = [];

$contenidoGrados = [];

$progresoGeneral = 0;


/*
|--------------------------------------------------------------------------
| GRADOS DISPONIBLES
|--------------------------------------------------------------------------
*/

$grados = [

    "9" => [
        "nombre" =>
            "Noveno",

        "descripcion" =>
            "Fortalece tus bases y comienza tu preparación.",

        "icono" =>
            "bi-1-circle-fill"
    ],


    "10" => [
        "nombre" =>
            "Décimo",

        "descripcion" =>
            "Desarrolla y profundiza tus conocimientos.",

        "icono" =>
            "bi-2-circle-fill"
    ],


    "11" => [
        "nombre" =>
            "Undécimo",

        "descripcion" =>
            "Prepárate para alcanzar tu mejor resultado en Saber 11°.",

        "icono" =>
            "bi-3-circle-fill"
    ]

];


/*
|--------------------------------------------------------------------------
| INICIALIZAR INFORMACIÓN DE LOS GRADOS
|--------------------------------------------------------------------------
*/

foreach (
    $grados
    as $grado => $datos
) {

    $contenidoGrados[$grado] = [

        "total_temas" =>
            0,

        "progreso" =>
            0

    ];

}


/*
|--------------------------------------------------------------------------
| OBTENER RESUMEN DE CONTENIDOS
|--------------------------------------------------------------------------
|
| El dashboard solamente necesita:
|
| - Cantidad de temas por grado.
| - Progreso del estudiante por grado.
|
| No cargamos los temas completos porque serán mostrados
| posteriormente en grado.php.
|
|--------------------------------------------------------------------------
*/

try {

    $sql = "

        SELECT

            t.grado,

            COUNT(t.id_tema) AS total_temas,

            COALESCE(
                ROUND(
                    AVG(
                        COALESCE(
                            p.porcentaje_avance,
                            0
                        )
                    )
                ),
                0
            ) AS progreso

        FROM temas t

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = :id_usuario

        WHERE t.grado IN (
            '9',
            '10',
            '11'
        )

        GROUP BY
            t.grado

    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([

        ":id_usuario" =>
            $idUsuario

    ]);


    $resultados =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | GUARDAR RESULTADOS
    |--------------------------------------------------------------------------
    */

    foreach (
        $resultados
        as $resultado
    ) {

        $grado =
            (string)$resultado["grado"];


        if (
            !isset(
                $contenidoGrados[$grado]
            )
        ) {

            continue;

        }


        $totalTemas =
            (int)$resultado["total_temas"];


        $progreso =
            (float)$resultado["progreso"];


        /*
        |--------------------------------------------------------------------------
        | NORMALIZAR PROGRESO
        |--------------------------------------------------------------------------
        */

        if ($progreso < 0) {

            $progreso = 0;

        }


        if ($progreso > 100) {

            $progreso = 100;

        }


        $contenidoGrados[$grado] = [

            "total_temas" =>
                $totalTemas,

            "progreso" =>
                $progreso

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR PROGRESO GENERAL
    |--------------------------------------------------------------------------
    |
    | Se calcula como el promedio del progreso de todos los temas
    | disponibles en 9°, 10° y 11°.
    |
    |--------------------------------------------------------------------------
    */

    $totalTemasGeneral = 0;

    $sumaProgresoGeneral = 0;


    foreach (
        $resultados
        as $resultado
    ) {

        $totalTemas =
            (int)$resultado["total_temas"];


        $progreso =
            (float)$resultado["progreso"];


        $totalTemasGeneral +=
            $totalTemas;


        $sumaProgresoGeneral +=
            ($progreso * $totalTemas);

    }


    if (
        $totalTemasGeneral > 0
    ) {

        $progresoGeneral =
            round(
                $sumaProgresoGeneral /
                $totalTemasGeneral
            );

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar la información de los contenidos.";

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
        Dashboard | ICFES Platform
    </title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | GENERAL
        |--------------------------------------------------------------------------
        */

        body {

            font-size: 0.95rem;

        }


        /*
        |--------------------------------------------------------------------------
        | NAVBAR
        |--------------------------------------------------------------------------
        */

        .navbar-brand {

            font-weight: 600;

        }


        /*
        |--------------------------------------------------------------------------
        | TARJETA DE BIENVENIDA
        |--------------------------------------------------------------------------
        */

        .bienvenida {

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #084298
                );

            color: white;

            border: 0;

        }


        /*
        |--------------------------------------------------------------------------
        | TARJETAS DE GRADO
        |--------------------------------------------------------------------------
        */

        .tarjeta-grado {

            border: 0;

            box-shadow:
                0 0.15rem 0.5rem
                rgba(0, 0, 0, 0.08);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;

            height: 100%;

        }


        .tarjeta-grado:hover {

            transform:
                translateY(-4px);

            box-shadow:
                0 0.5rem 1rem
                rgba(0, 0, 0, 0.12);

        }


        /*
        |--------------------------------------------------------------------------
        | ICONOS DE GRADO
        |--------------------------------------------------------------------------
        */

        .icono-grado {

            width: 70px;

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            margin:
                0 auto 1rem;

            font-size: 2rem;

            background:
                #e9f2ff;

            color:
                #0d6efd;

        }


        /*
        |--------------------------------------------------------------------------
        | BARRAS DE PROGRESO
        |--------------------------------------------------------------------------
        */

        .barra-progreso {

            height: 10px;

            border-radius: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | ESTADÍSTICAS
        |--------------------------------------------------------------------------
        */

        .estadistica {

            border: 0;

            box-shadow:
                0 0.125rem 0.35rem
                rgba(0, 0, 0, 0.06);

        }


        /*
        |--------------------------------------------------------------------------
        | TEXTO DE PROGRESO
        |--------------------------------------------------------------------------
        */

        .progreso-texto {

            font-size: 0.8rem;

        }


        /*
        |--------------------------------------------------------------------------
        | BOTÓN DE GRADO
        |--------------------------------------------------------------------------
        */

        .boton-grado {

            min-height: 42px;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 767px) {

            .bienvenida h2 {

                font-size: 1.4rem;

            }


            .tarjeta-grado {

                margin-bottom: 0;

            }

        }

    </style>

</head>


<body class="bg-light">


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-dark bg-primary">

    <div class="container">


        <!-- LOGO -->

        <a
            href="dashboard.php"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            ICFES Platform

        </a>



        <!-- USUARIO -->

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
     CONTENIDO PRINCIPAL
========================================================= -->

<div class="container py-4">


    <!-- =====================================================
         MENSAJES DE ERROR
    ====================================================== -->

    <?php foreach (
        $errores
        as $error
    ): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars(
                $error
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endforeach; ?>



    <!-- =====================================================
         BIENVENIDA
    ====================================================== -->

    <div
        class="card bienvenida shadow-sm mb-4"
    >

        <div class="card-body p-4">


            <div
                class="row align-items-center"
            >


                <!-- MENSAJE -->

                <div class="col-md-8">


                    <div
                        class="small mb-2 opacity-75"
                    >

                        PLATAFORMA DE PREPARACIÓN SABER 11°

                    </div>


                    <h2 class="mb-2">

                        ¡Hola,
                        <?= htmlspecialchars(
                            $nombres
                        ) ?>!

                    </h2>


                    <p class="mb-0 opacity-75">

                        Elige el grado que quieres estudiar
                        y comienza a avanzar en tus contenidos.

                        Puedes acceder libremente a
                        <strong>
                            9°, 10° y 11°
                        </strong>.

                    </p>

                </div>



                <!-- PROGRESO GENERAL -->

                <div
                    class="col-md-4 mt-3 mt-md-0"
                >

                    <div
                        class="text-md-end"
                    >

                        <div
                            class="small opacity-75"
                        >

                            Progreso general

                        </div>


                        <div
                            class="fs-2 fw-bold"
                        >

                            <?= $progresoGeneral ?>%

                        </div>


                        <div
                            class="progress barra-progreso bg-white bg-opacity-25"
                        >

                            <div
                                class="progress-bar bg-white"
                                role="progressbar"
                                style="
                                    width:
                                    <?= $progresoGeneral ?>%;
                                "
                                aria-valuenow="<?= $progresoGeneral ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            ></div>

                        </div>

                    </div>

                </div>


            </div>

        </div>

    </div>



    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div
        class="row g-3 mb-5"
    >


        <!-- PUNTOS -->

        <div
            class="col-6 col-md-4"
        >

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">


                    <div
                        class="text-muted small"
                    >

                        <i
                            class="bi bi-star-fill text-warning"
                        ></i>

                        Puntos

                    </div>


                    <div
                        class="fs-4 fw-bold"
                    >

                        <?= $puntos ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- NIVEL -->

        <div
            class="col-6 col-md-4"
        >

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">


                    <div
                        class="text-muted small"
                    >

                        <i
                            class="bi bi-trophy-fill text-warning"
                        ></i>

                        Nivel

                    </div>


                    <div
                        class="fs-4 fw-bold"
                    >

                        <?= $nivel ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- GRADO -->

        <div
            class="col-12 col-md-4"
        >

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">


                    <div
                        class="text-muted small"
                    >

                        <i
                            class="bi bi-mortarboard-fill text-primary"
                        ></i>

                        Mi grado

                    </div>


                    <div
                        class="fs-4 fw-bold"
                    >

                        <?php if (
                            $gradoEstudiante !== ""
                        ): ?>

                            <?= htmlspecialchars(
                                $gradoEstudiante
                            ) ?>°

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- =====================================================
         SELECTOR DE GRADOS
    ====================================================== -->

    <section>


        <!-- TÍTULO -->

        <div
            class="text-center mb-4"
        >

            <h3 class="mb-1">

                ¿Qué grado quieres estudiar?

            </h3>


            <p
                class="text-muted mb-0"
            >

                Puedes explorar los contenidos de cualquier grado.

            </p>

        </div>



        <!-- TARJETAS -->

        <div
            class="row g-4"
        >


            <?php foreach (
                $grados
                as $grado => $datos
            ): ?>


                <?php

                $progresoGrado =
                    $contenidoGrados[$grado]["progreso"]
                    ?? 0;


                $totalTemasGrado =
                    $contenidoGrados[$grado]["total_temas"]
                    ?? 0;

                ?>


                <!-- =================================================
                     TARJETA DEL GRADO
                ================================================== -->

                <div
                    class="col-md-4"
                >

                    <div
                        class="card tarjeta-grado"
                    >

                        <div
                            class="card-body text-center p-4 d-flex flex-column"
                        >


                            <!-- ICONO -->

                            <div
                                class="icono-grado"
                            >

                                <i
                                    class="
                                        bi
                                        <?= htmlspecialchars(
                                            $datos["icono"]
                                        )
                                        ?>
                                    "
                                ></i>

                            </div>



                            <!-- NOMBRE -->

                            <h4>

                                <?= htmlspecialchars(
                                    $datos["nombre"]
                                ) ?>

                                <span
                                    class="text-muted"
                                >

                                    (<?= htmlspecialchars(
                                        $grado
                                    ) ?>°)

                                </span>

                            </h4>



                            <!-- DESCRIPCIÓN -->

                            <p
                                class="text-muted"
                            >

                                <?= htmlspecialchars(
                                    $datos["descripcion"]
                                ) ?>

                            </p>



                            <!-- PROGRESO -->

                            <div
                                class="text-start mt-3"
                            >


                                <div
                                    class="d-flex justify-content-between mb-1"
                                >

                                    <span
                                        class="
                                            progreso-texto
                                            text-muted
                                        "
                                    >

                                        Tu progreso

                                    </span>


                                    <strong
                                        class="progreso-texto"
                                    >

                                        <?= $progresoGrado ?>%

                                    </strong>

                                </div>


                                <div
                                    class="progress barra-progreso"
                                >

                                    <div
                                        class="progress-bar"
                                        role="progressbar"
                                        style="
                                            width:
                                            <?= $progresoGrado ?>%;
                                        "
                                        aria-valuenow="<?= $progresoGrado ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    ></div>

                                </div>


                                <div
                                    class="
                                        small
                                        text-muted
                                        mt-2
                                    "
                                >

                                    <?= $totalTemasGrado ?>

                                    tema(s) disponible(s)

                                </div>

                            </div>



                            <!-- BOTÓN -->

                            <div
                                class="mt-auto pt-4"
                            >

                                <a
                                    href="
                                        grado.php?grado=
                                        <?= urlencode(
                                            $grado
                                        )
                                        ?>
                                    "
                                    class="
                                        btn
                                        btn-primary
                                        w-100
                                        boton-grado
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-arrow-right-circle-fill
                                        "
                                    ></i>

                                    Explorar <?= htmlspecialchars(
                                        $grado
                                    ) ?>°

                                </a>

                            </div>


                        </div>

                    </div>

                </div>


            <?php endforeach; ?>


        </div>

    </section>


</div>



<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="
        https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js
    "
></script>


</body>

</html>
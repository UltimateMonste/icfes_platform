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

$grados = [
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


$contenidoGrados = [];

$progresoGeneral = 0;


/*
|--------------------------------------------------------------------------
| OBTENER CONTENIDOS Y PROGRESO
|--------------------------------------------------------------------------
|
| El estudiante puede consultar TODOS los grados.
|
*/

try {

    /*
    |--------------------------------------------------------------------------
    | TEMAS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            t.id_tema,
            t.id_materia,
            t.nombre AS tema,
            t.descripcion,
            t.grado,
            m.nombre AS materia,

            COALESCE(
                p.porcentaje_avance,
                0
            ) AS porcentaje_avance

        FROM temas t

        INNER JOIN materias m
            ON t.id_materia = m.id_materia

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = ?

        WHERE t.grado IN ('9', '10', '11')

        ORDER BY
            t.grado ASC,
            m.id_materia ASC,
            t.id_tema ASC
    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([
        $idUsuario
    ]);


    $temas =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | ORGANIZAR POR GRADO
    |--------------------------------------------------------------------------
    */

    foreach ($grados as $grado => $datos) {

        $contenidoGrados[$grado] = [
            "temas" => [],
            "total_temas" => 0,
            "progreso" => 0
        ];

    }


    foreach ($temas as $tema) {

        $grado =
            (string)$tema["grado"];


        if (!isset($contenidoGrados[$grado])) {

            continue;

        }


        $porcentaje =
            (float)$tema["porcentaje_avance"];


        /*
        |--------------------------------------------------------------------------
        | NORMALIZAR PORCENTAJE
        |--------------------------------------------------------------------------
        */

        if ($porcentaje < 0) {

            $porcentaje = 0;

        }


        if ($porcentaje > 100) {

            $porcentaje = 100;

        }


        $tema["porcentaje_avance"] =
            $porcentaje;


        $contenidoGrados[$grado]["temas"][] =
            $tema;

    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR PROGRESO POR GRADO
    |--------------------------------------------------------------------------
    */

    foreach (
        $contenidoGrados
        as $grado => &$contenido
    ) {

        $totalTemas =
            count($contenido["temas"]);


        $contenido["total_temas"] =
            $totalTemas;


        if ($totalTemas > 0) {

            $suma = 0;


            foreach (
                $contenido["temas"]
                as $tema
            ) {

                $suma +=
                    (float)$tema["porcentaje_avance"];

            }


            $contenido["progreso"] =
                round(
                    $suma / $totalTemas
                );

        } else {

            $contenido["progreso"] =
                0;

        }

    }

    unset($contenido);


    /*
    |--------------------------------------------------------------------------
    | PROGRESO GENERAL
    |--------------------------------------------------------------------------
    */

    $totalTemasGeneral = 0;

    $sumaProgresoGeneral = 0;


    foreach (
        $contenidoGrados
        as $contenido
    ) {

        foreach (
            $contenido["temas"]
            as $tema
        ) {

            $totalTemasGeneral++;

            $sumaProgresoGeneral +=
                (float)$tema["porcentaje_avance"];

        }

    }


    if ($totalTemasGeneral > 0) {

        $progresoGeneral =
            round(
                $sumaProgresoGeneral /
                $totalTemasGeneral
            );

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar los contenidos.";

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

            font-size: 0.95rem;

        }


        .navbar-brand {

            font-weight: 600;

        }


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

            background: #e9f2ff;

            color: #0d6efd;

        }


        .barra-progreso {

            height: 10px;

            border-radius: 20px;

        }


        .estadistica {

            border: 0;

            box-shadow:
                0 0.125rem 0.35rem
                rgba(0, 0, 0, 0.06);

        }


        .tema-item {

            border: 1px solid #e9ecef;

            border-radius: 10px;

            padding: 12px;

            margin-bottom: 8px;

            background: #fff;

        }


        .tema-item:last-child {

            margin-bottom: 0;

        }


        .tema-enlace {

            text-decoration: none;

            color: inherit;

        }


        .tema-enlace:hover {

            color: inherit;

        }


        .grado-badge {

            font-size: 0.75rem;

        }


        .progreso-texto {

            font-size: 0.8rem;

        }


        .seccion-grado {

            scroll-margin-top: 80px;

        }


        @media (max-width: 767px) {

            .bienvenida h2 {

                font-size: 1.4rem;

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

        <a
            href="dashboard.php"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            ICFES Platform

        </a>


        <div class="d-flex align-items-center gap-2">

            <span
                class="text-white d-none d-md-inline"
            >

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars($nombres) ?>

            </span>


            <a
                href="../cerrar_sesion.php"
                class="btn btn-light btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                Cerrar sesión

            </a>

        </div>

    </div>

</nav>



<!-- =========================================================
     CONTENIDO
========================================================= -->

<div class="container py-4">


    <!-- =====================================================
         MENSAJES
    ====================================================== -->

    <?php foreach ($errores as $error): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars($error) ?>


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


            <div class="row align-items-center">


                <div class="col-md-8">


                    <div class="small mb-2 opacity-75">

                        PLATAFORMA DE PREPARACIÓN SABER 11°

                    </div>


                    <h2 class="mb-2">

                        ¡Hola,
                        <?= htmlspecialchars($nombres) ?>!

                    </h2>


                    <p class="mb-0 opacity-75">

                        Elige el grado que quieres estudiar
                        y comienza a avanzar en tus contenidos.

                        Puedes acceder libremente a
                        <strong>9°, 10° y 11°</strong>.

                    </p>

                </div>


                <div
                    class="col-md-4 mt-3 mt-md-0"
                >

                    <div
                        class="text-md-end"
                    >

                        <div class="small opacity-75">

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
                                style="width: <?= $progresoGeneral ?>%;"
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
        class="row g-3 mb-4"
    >


        <div class="col-6 col-md-4">

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">

                    <div
                        class="text-muted small"
                    >

                        <i class="bi bi-star-fill text-warning"></i>

                        Puntos

                    </div>


                    <div class="fs-4 fw-bold">

                        <?= $puntos ?>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4">

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">

                    <div
                        class="text-muted small"
                    >

                        <i class="bi bi-trophy-fill text-warning"></i>

                        Nivel

                    </div>


                    <div class="fs-4 fw-bold">

                        <?= $nivel ?>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-12 col-md-4">

            <div
                class="card estadistica h-100"
            >

                <div class="card-body">

                    <div
                        class="text-muted small"
                    >

                        <i class="bi bi-mortarboard-fill text-primary"></i>

                        Mi grado

                    </div>


                    <div class="fs-4 fw-bold">

                        <?php if ($gradoEstudiante !== ""): ?>

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

    <div class="mb-4">


        <div class="text-center mb-4">

            <h3 class="mb-1">

                ¿Qué grado quieres estudiar?

            </h3>


            <p class="text-muted mb-0">

                Puedes explorar los contenidos de cualquier grado.

            </p>

        </div>


        <div class="row g-4">


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


                <div class="col-md-4">


                    <div
                        class="card tarjeta-grado"
                    >

                        <div
                            class="card-body text-center p-4"
                        >


                            <div class="icono-grado">

                                <i
                                    class="bi <?= $datos["icono"] ?>"
                                ></i>

                            </div>


                            <h4>

                                <?= htmlspecialchars(
                                    $datos["nombre"]
                                ) ?>

                                <span class="text-muted">

                                    (<?= $grado ?>°)

                                </span>

                            </h4>


                            <p
                                class="text-muted"
                            >

                                <?= htmlspecialchars(
                                    $datos["descripcion"]
                                ) ?>

                            </p>


                            <div
                                class="text-start mt-4"
                            >

                                <div
                                    class="d-flex justify-content-between mb-1"
                                >

                                    <span
                                        class="progreso-texto text-muted"
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
                                        style="width: <?= $progresoGrado ?>%;"
                                        aria-valuenow="<?= $progresoGrado ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    ></div>

                                </div>


                                <div
                                    class="small text-muted mt-2"
                                >

                                    <?= $totalTemasGrado ?>

                                    tema(s) disponible(s)

                                </div>

                            </div>


                            <a
                                href="#grado-<?= $grado ?>"
                                class="btn btn-primary w-100 mt-4"
                            >

                                <i class="bi bi-arrow-right-circle-fill"></i>

                                Explorar <?= $grado ?>°

                            </a>


                        </div>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>

    </div>



    <!-- =====================================================
         CONTENIDOS POR GRADO
    ====================================================== -->

    <div class="mt-5">


        <div class="text-center mb-4">

            <h3>

                Contenidos disponibles

            </h3>


            <p class="text-muted">

                Selecciona un tema para comenzar o continuar
                tu preparación.

            </p>

        </div>


        <?php foreach (
            $grados
            as $grado => $datos
        ): ?>


            <?php

            $temasGrado =
                $contenidoGrados[$grado]["temas"]
                ?? [];

            $progresoGrado =
                $contenidoGrados[$grado]["progreso"]
                ?? 0;

            ?>


            <section
                id="grado-<?= $grado ?>"
                class="seccion-grado mb-5"
            >


                <div
                    class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3"
                >


                    <div>

                        <h4 class="mb-1">

                            <?= htmlspecialchars(
                                $datos["nombre"]
                            ) ?>

                            <span class="text-muted">

                                · <?= $grado ?>°

                            </span>

                        </h4>


                        <div class="text-muted small">

                            <?= count($temasGrado) ?>

                            tema(s)

                        </div>

                    </div>


                    <div
                        class="mt-2 mt-md-0"
                    >

                        <span
                            class="badge bg-primary grado-badge"
                        >

                            <?= $progresoGrado ?>% completado

                        </span>

                    </div>

                </div>



                <?php if (empty($temasGrado)): ?>


                    <div
                        class="card border-0 shadow-sm"
                    >

                        <div
                            class="card-body text-center py-4"
                        >

                            <i
                                class="bi bi-journal-x fs-2 text-muted"
                            ></i>


                            <p
                                class="text-muted mb-0 mt-2"
                            >

                                Aún no hay contenidos disponibles
                                para este grado.

                            </p>

                        </div>

                    </div>


                <?php else: ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | AGRUPAR TEMAS POR MATERIA
                    |--------------------------------------------------------------------------
                    */

                    $materias =
                        [];

                    foreach (
                        $temasGrado
                        as $tema
                    ) {

                        $nombreMateria =
                            $tema["materia"];

                        if (
                            !isset(
                                $materias[
                                    $nombreMateria
                                ]
                            )
                        ) {

                            $materias[
                                $nombreMateria
                            ] = [];

                        }

                        $materias[
                            $nombreMateria
                        ][] = $tema;

                    }

                    ?>


                    <?php foreach (
                        $materias
                        as $nombreMateria => $temasMateria
                    ): ?>


                        <div class="mb-4">


                            <h5 class="mb-3">

                                <i
                                    class="bi bi-book-half text-primary"
                                ></i>

                                <?= htmlspecialchars(
                                    $nombreMateria
                                ) ?>

                            </h5>


                            <div
                                class="row g-3"
                            >


                                <?php foreach (
                                    $temasMateria
                                    as $tema
                                ): ?>


                                    <?php

                                    $avance =
                                        (float)$tema[
                                            "porcentaje_avance"
                                        ];

                                    ?>


                                    <div
                                        class="col-md-6 col-lg-4"
                                    >


                                        <a
                                            href="tema.php?id=<?= (int)$tema["id_tema"] ?>"
                                            class="tema-enlace"
                                        >


                                            <div
                                                class="tema-item h-100"
                                            >


                                                <div
                                                    class="d-flex justify-content-between align-items-start gap-2"
                                                >


                                                    <div>

                                                        <strong>

                                                            <?= htmlspecialchars(
                                                                $tema["tema"]
                                                            ) ?>

                                                        </strong>


                                                        <?php if (
                                                            !empty(
                                                                $tema["descripcion"]
                                                            )
                                                        ): ?>

                                                            <div
                                                                class="small text-muted mt-1"
                                                            >

                                                                <?= htmlspecialchars(
                                                                    $tema["descripcion"]
                                                                ) ?>

                                                            </div>

                                                        <?php endif; ?>

                                                    </div>


                                                    <span
                                                        class="badge bg-light text-dark border"
                                                    >

                                                        <?= $avance ?>%

                                                    </span>

                                                </div>


                                                <div
                                                    class="progress barra-progreso mt-3"
                                                >

                                                    <div
                                                        class="progress-bar"
                                                        role="progressbar"
                                                        style="width: <?= $avance ?>%;"
                                                        aria-valuenow="<?= $avance ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                    ></div>

                                                </div>


                                                <div
                                                    class="d-flex justify-content-between mt-2"
                                                >

                                                    <span
                                                        class="small text-muted"
                                                    >

                                                        <?php if (
                                                            $avance >= 100
                                                        ): ?>

                                                            <i
                                                                class="bi bi-check-circle-fill text-success"
                                                            ></i>

                                                            Completado

                                                        <?php elseif (
                                                            $avance > 0
                                                        ): ?>

                                                            <i
                                                                class="bi bi-play-circle-fill text-primary"
                                                            ></i>

                                                            En progreso

                                                        <?php else: ?>

                                                            <i
                                                                class="bi bi-circle text-muted"
                                                            ></i>

                                                            Sin comenzar

                                                        <?php endif; ?>

                                                    </span>


                                                    <span
                                                        class="small text-primary"
                                                    >

                                                        Ver tema

                                                        <i
                                                            class="bi bi-arrow-right"
                                                        ></i>

                                                    </span>

                                                </div>


                                            </div>


                                        </a>


                                    </div>


                                <?php endforeach; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </section>


        <?php endforeach; ?>


    </div>


</div>



<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
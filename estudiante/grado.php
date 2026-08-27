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


/*
|--------------------------------------------------------------------------
| RECIBIR GRADO
|--------------------------------------------------------------------------
*/

$grado =
    trim($_GET["grado"] ?? "");


/*
|--------------------------------------------------------------------------
| VALIDAR GRADO
|--------------------------------------------------------------------------
*/

$gradosPermitidos = [
    "9" => [
        "nombre" => "Noveno",
        "descripcion" =>
            "Fortalece tus bases y comienza tu preparación.",
        "icono" => "bi-1-circle-fill"
    ],

    "10" => [
        "nombre" => "Décimo",
        "descripcion" =>
            "Desarrolla y profundiza tus conocimientos.",
        "icono" => "bi-2-circle-fill"
    ],

    "11" => [
        "nombre" => "Undécimo",
        "descripcion" =>
            "Prepárate para alcanzar tu mejor resultado en Saber 11°.",
        "icono" => "bi-3-circle-fill"
    ]
];


if (!isset($gradosPermitidos[$grado])) {

    header(
        "Location: dashboard.php"
    );

    exit;
}


$datosGrado =
    $gradosPermitidos[$grado];


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$materias = [];

$errores = [];


/*
|--------------------------------------------------------------------------
| CONSULTAR TEMAS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            t.id_tema,
            t.id_materia,
            t.nombre AS tema,
            t.descripcion,
            t.grado,

            m.nombre AS materia,
            m.descripcion AS descripcion_materia,

            COALESCE(
                p.porcentaje_avance,
                0
            ) AS porcentaje_avance

        FROM temas t

        INNER JOIN materias m
            ON t.id_materia = m.id_materia

        LEFT JOIN progreso p
            ON p.id_tema = t.id_tema
            AND p.id_usuario = :id_usuario

        WHERE t.grado = :grado

        ORDER BY
            m.id_materia ASC,
            t.id_tema ASC
    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([
        ":id_usuario" => $idUsuario,
        ":grado" => $grado
    ]);


    $temas =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | AGRUPAR TEMAS POR MATERIA
    |--------------------------------------------------------------------------
    */

    foreach ($temas as $tema) {

        $idMateria =
            (int)$tema["id_materia"];


        if (!isset($materias[$idMateria])) {

            $materias[$idMateria] = [
                "nombre" =>
                    $tema["materia"],

                "descripcion" =>
                    $tema["descripcion_materia"],

                "temas" => []
            ];

        }


        $avance =
            (float)$tema["porcentaje_avance"];


        /*
        |--------------------------------------------------------------------------
        | NORMALIZAR PORCENTAJE
        |--------------------------------------------------------------------------
        */

        if ($avance < 0) {

            $avance = 0;

        }


        if ($avance > 100) {

            $avance = 100;

        }


        $tema["porcentaje_avance"] =
            $avance;


        $materias[$idMateria]["temas"][] =
            $tema;

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar los contenidos del grado.";

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
        <?= htmlspecialchars($datosGrado["nombre"]) ?>
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

            font-size: 0.95rem;

        }


        .navbar-brand {

            font-weight: 600;

        }


        .encabezado-grado {

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #084298
                );

            color: white;

            border: 0;

        }


        .materia-card {

            border: 0;

            box-shadow:
                0 0.15rem 0.5rem
                rgba(0, 0, 0, 0.08);

            border-radius: 12px;

            overflow: hidden;

        }


        .materia-header {

            background: #f8f9fa;

            border-bottom:
                1px solid #e9ecef;

        }


        .tema-item {

            border:
                1px solid #e9ecef;

            border-radius: 10px;

            padding: 15px;

            background: white;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .tema-item:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 0.35rem 0.8rem
                rgba(0, 0, 0, 0.08);

        }


        .tema-enlace {

            text-decoration: none;

            color: inherit;

        }


        .tema-enlace:hover {

            color: inherit;

        }


        .barra-progreso {

            height: 8px;

            border-radius: 20px;

        }


        .icono-materia {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 10px;

            background: #e9f2ff;

            color: #0d6efd;

            font-size: 1.25rem;

        }


        .estado-tema {

            font-size: 0.8rem;

        }


        .icono-grado {

            width: 70px;

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: rgba(255,255,255,0.15);

            font-size: 2rem;

        }


        @media (max-width: 767px) {

            .encabezado-grado h1 {

                font-size: 1.6rem;

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
         ERRORES
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
         ENCABEZADO DEL GRADO
    ====================================================== -->

    <div
        class="card encabezado-grado shadow-sm mb-4"
    >

        <div class="card-body p-4">

            <div class="row align-items-center">


                <div class="col-md-8">

                    <div
                        class="d-flex align-items-center gap-3"
                    >

                        <div class="icono-grado">

                            <i
                                class="bi <?= htmlspecialchars(
                                    $datosGrado["icono"]
                                ) ?>"
                            ></i>

                        </div>


                        <div>

                            <div
                                class="small opacity-75"
                            >

                                RUTA DE APRENDIZAJE

                            </div>


                            <h1 class="mb-1">

                                <?= htmlspecialchars(
                                    $datosGrado["nombre"]
                                ) ?>

                                <span class="opacity-75">

                                    (<?= htmlspecialchars($grado) ?>°)

                                </span>

                            </h1>


                            <p class="mb-0 opacity-75">

                                <?= htmlspecialchars(
                                    $datosGrado["descripcion"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>


                <div class="col-md-4 text-md-end mt-3 mt-md-0">

                    <div class="small opacity-75">

                        Contenidos disponibles

                    </div>


                    <div class="fs-2 fw-bold">

                        <?= count($temas) ?>

                    </div>


                    <div class="small opacity-75">

                        tema(s)

                    </div>

                </div>


            </div>

        </div>

    </div>



    <!-- =====================================================
         BOTÓN VOLVER
    ====================================================== -->

    <div class="mb-4">

        <a
            href="dashboard.php"
            class="btn btn-outline-primary"
        >

            <i class="bi bi-arrow-left"></i>

            Volver al dashboard

        </a>

    </div>



    <!-- =====================================================
         MATERIAS
    ====================================================== -->

    <?php if (empty($materias)): ?>

        <div
            class="card border-0 shadow-sm"
        >

            <div
                class="card-body text-center py-5"
            >

                <i
                    class="bi bi-journal-x fs-1 text-muted"
                ></i>


                <h5 class="mt-3">

                    No hay contenidos disponibles

                </h5>


                <p class="text-muted mb-0">

                    Actualmente no hay temas registrados
                    para este grado.

                </p>

            </div>

        </div>


    <?php else: ?>


        <div class="row g-4">


            <?php foreach (
                $materias
                as $materia
            ): ?>


                <?php

                $temasMateria =
                    $materia["temas"];

                ?>


                <div class="col-12">


                    <div
                        class="card materia-card"
                    >


                        <!-- =================================
                             CABECERA MATERIA
                        ================================== -->

                        <div
                            class="card-body materia-header p-4"
                        >

                            <div
                                class="d-flex align-items-center gap-3"
                            >

                                <div class="icono-materia">

                                    <i
                                        class="bi bi-book-half"
                                    ></i>

                                </div>


                                <div>

                                    <h4 class="mb-1">

                                        <?= htmlspecialchars(
                                            $materia["nombre"]
                                        ) ?>

                                    </h4>


                                    <?php if (
                                        !empty(
                                            $materia["descripcion"]
                                        )
                                    ): ?>

                                        <p
                                            class="text-muted mb-0"
                                        >

                                            <?= htmlspecialchars(
                                                $materia["descripcion"]
                                            ) ?>

                                        </p>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>



                        <!-- =================================
                             TEMAS
                        ================================== -->

                        <div class="card-body p-4">


                            <div class="row g-3">


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

                                                        <h6
                                                            class="mb-1"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $tema["tema"]
                                                            ) ?>

                                                        </h6>


                                                        <?php if (
                                                            !empty(
                                                                $tema["descripcion"]
                                                            )
                                                        ): ?>

                                                            <div
                                                                class="small text-muted"
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



                                                <!-- PROGRESO -->

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



                                                <!-- ESTADO -->

                                                <div
                                                    class="estado-tema mt-2"
                                                >

                                                    <?php if (
                                                        $avance >= 100
                                                    ): ?>

                                                        <span
                                                            class="text-success"
                                                        >

                                                            <i
                                                                class="bi bi-check-circle-fill"
                                                            ></i>

                                                            Completado

                                                        </span>


                                                    <?php elseif (
                                                        $avance > 0
                                                    ): ?>

                                                        <span
                                                            class="text-primary"
                                                        >

                                                            <i
                                                                class="bi bi-play-circle-fill"
                                                            ></i>

                                                            En progreso

                                                        </span>


                                                    <?php else: ?>

                                                        <span
                                                            class="text-muted"
                                                        >

                                                            <i
                                                                class="bi bi-circle"
                                                            ></i>

                                                            Sin comenzar

                                                        </span>

                                                    <?php endif; ?>


                                                    <span
                                                        class="float-end text-primary"
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


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>



<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
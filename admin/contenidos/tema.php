<?php

require_once __DIR__ . "/../includes/seguridad.php";

$usuarioActual = exigirEstudianteOAdmin();
$rolActual = (int)($usuarioActual['id_rol'] ?? 0);
$esAdministrador = ($rolActual === 1);


// =========================================================
// DATOS BÁSICOS
// =========================================================

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idTema || $idTema <= 0) {

    redireccionarDashboardUsuario($usuarioActual);
    exit;

}


$errores = [];


// =========================================================
// USUARIO
// =========================================================

$idUsuario =
    (int)($usuarioActual["id_usuario"] ?? 0);

$nombres =
    trim(($usuarioActual["nombres"] ?? $_SESSION["nombres"] ?? "Usuario"));

if ($nombres === "") {
    $nombres = $esAdministrador ? "Administrador" : "Estudiante";
}


// =========================================================
// TEMA
// =========================================================

$tema = null;
$contenido = null;
$recursos = [];
$progreso = 0;


try {

    /*
    |---------------------------------------------------------
    | TEMA
    |---------------------------------------------------------
    */

    $sqlTema = "
        SELECT
            t.id_tema,
            t.nombre AS tema_nombre,
            t.descripcion AS tema_descripcion,
            t.grado,
            m.id_materia,
            m.nombre AS materia_nombre,
            m.descripcion AS materia_descripcion

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

        $errores[] =
            "El tema solicitado no existe.";

    }


    /*
    |---------------------------------------------------------
    | CONTENIDO
    |---------------------------------------------------------
    */

    if ($tema) {

        $sqlContenido = "
            SELECT
                id_contenido,
                contenido,
                estado,
                fecha_actualizacion

            FROM contenido_temas

            WHERE id_tema = ?

            AND estado = 'Publicado'

            LIMIT 1
        ";

        $stmtContenido =
            $conexion->prepare(
                $sqlContenido
            );

        $stmtContenido->execute([
            $idTema
        ]);

        $contenido =
            $stmtContenido->fetch(
                PDO::FETCH_ASSOC
            );

    }


    /*
    |---------------------------------------------------------
    | RECURSOS
    |---------------------------------------------------------
    */

    if ($tema) {

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
                visitas,
                estado

            FROM recursos

            WHERE id_tema = ?

            AND estado = 'Activo'

            ORDER BY
                id_recurso ASC
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

    }


    /*
    |---------------------------------------------------------
    | PROGRESO
    |---------------------------------------------------------
    */

    if (
        $tema &&
        $idUsuario > 0
    ) {

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

        $avance =
            $stmtProgreso->fetchColumn();


        if (
            $avance !== false
        ) {

            $progreso =
                (float)$avance;

        }

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar el contenido del tema.";

}


// =========================================================
// NORMALIZAR PROGRESO
// =========================================================

if ($progreso < 0) {

    $progreso = 0;

}

if ($progreso > 100) {

    $progreso = 100;

}

$progreso =
    round($progreso);


// =========================================================
// DETERMINAR SI EXISTE CONTENIDO
// =========================================================

$hayContenido =
    !empty($contenido)
    &&
    isset($contenido["contenido"])
    &&
    trim(
        $contenido["contenido"]
    ) !== "";

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
            $tema["tema_nombre"] ?? "Tema"
        ) ?>

        | Studia360

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
                #f4f7fb;

            color:
                #172033;

        }


        .navbar {

            box-shadow:
                0 2px 10px
                rgba(0,0,0,.08);

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

            box-shadow:
                0 10px 30px
                rgba(13,110,253,.15);

        }


        .contenido-card {

            background:
                white;

            border:
                0;

            border-radius:
                18px;

            box-shadow:
                0 8px 30px
                rgba(0,0,0,.07);

        }


        .contenido-educativo {

            font-size:
                17px;

            line-height:
                1.8;

            color:
                #263244;

        }


        .contenido-educativo h1,
        .contenido-educativo h2,
        .contenido-educativo h3,
        .contenido-educativo h4,
        .contenido-educativo h5,
        .contenido-educativo h6 {

            color:
                #172033;

            margin-top:
                1.5em;

            margin-bottom:
                .7em;

            line-height:
                1.3;

        }


        .contenido-educativo h1:first-child,
        .contenido-educativo h2:first-child,
        .contenido-educativo h3:first-child {

            margin-top:
                0;

        }


        .contenido-educativo img {

            max-width:
                100%;

            height:
                auto;

            border-radius:
                10px;

        }


        .contenido-educativo table {

            width:
                100%;

            border-collapse:
                collapse;

            margin:
                20px 0;

        }


        .contenido-educativo table td,
        .contenido-educativo table th {

            border:
                1px solid #dee2e6;

            padding:
                10px;

        }


        .contenido-educativo blockquote {

            border-left:
                4px solid #0d6efd;

            padding:
                12px 18px;

            background:
                #f0f6ff;

            margin:
                20px 0;

            border-radius:
                0 10px 10px 0;

        }


        .contenido-educativo iframe {

            max-width:
                100%;

            width:
                100%;

            min-height:
                400px;

            border:
                0;

            border-radius:
                12px;

        }


        .recurso-card {

            border:
                1px solid #e8edf3;

            border-radius:
                14px;

            transition:
                transform .2s ease,
                box-shadow .2s ease;

            height:
                100%;

        }


        .recurso-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 8px 20px
                rgba(0,0,0,.08);

        }


        .icono-recurso {

            width:
                48px;

            height:
                48px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                12px;

            background:
                #e9f2ff;

            color:
                #0d6efd;

            font-size:
                1.25rem;

        }


        .barra-progreso {

            height:
                9px;

            border-radius:
                20px;

        }


        .estado-vacio {

            padding:
                60px 20px;

            text-align:
                center;

            color:
                #6c757d;

        }


        .sidebar-card {

            border:
                0;

            border-radius:
                16px;

            box-shadow:
                0 6px 22px
                rgba(0,0,0,.06);

        }


        @media (
            max-width: 767px
        ) {

            .contenido-educativo {

                font-size:
                    16px;

            }


            .hero-tema h1 {

                font-size:
                    1.6rem;

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
            href="<?= htmlspecialchars(
                $esAdministrador
                    ? urlAplicacion("/admin/dashboard.php")
                    : urlAplicacion("/estudiante/dashboard.php")
            ) ?>"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            Studia360

        </a>


        <div class="d-flex align-items-center gap-2">

            <span
                class="text-white d-none d-md-inline"
            >

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $nombres
                ) ?>

                <?php if ($esAdministrador): ?>
                    <span class="badge text-bg-light text-primary ms-1">Vista previa</span>
                <?php endif; ?>

            </span>


            <a
                href="<?= htmlspecialchars(urlAplicacion("/cerrar_sesion.php")) ?>"
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


    <?php foreach ($errores as $error): ?>

        <div
            class="alert alert-danger"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endforeach; ?>


    <?php if ($esAdministrador && $tema): ?>

        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="bi bi-eye-fill"></i>
            <strong>Vista previa del administrador.</strong>
            Estás viendo la versión publicada que reciben los estudiantes.
        </div>

    <?php endif; ?>


    <?php if ($tema): ?>


        <!-- =================================================
             ENCABEZADO
        ================================================== -->

        <div
            class="card hero-tema mb-4"
        >

            <div class="card-body p-4">

                <div
                    class="d-flex flex-column flex-md-row justify-content-between gap-4"
                >

                    <div>

                        <div
                            class="small text-uppercase opacity-75 mb-2"
                        >

                            <?= htmlspecialchars(
                                $tema["materia_nombre"]
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                $tema["grado"]
                            ) ?>°

                        </div>


                        <h1 class="mb-2">

                            <?= htmlspecialchars(
                                $tema["tema_nombre"]
                            ) ?>

                        </h1>


                        <p class="mb-0 opacity-75">

                            <?= htmlspecialchars(
                                $tema["tema_descripcion"]
                                ?? ""
                            ) ?>

                        </p>

                    </div>


                    <div
                        class="text-md-end"
                    >

                        <?php if ($esAdministrador): ?>

                            <a
                                href="<?= htmlspecialchars(urlAplicacion('/admin/contenidos/editar_tema.php?id=' . (int)$idTema)) ?>"
                                class="btn btn-light"
                            >

                                <i class="bi bi-pencil-square"></i>

                                Volver al editor

                            </a>

                        <?php else: ?>

                            <a
                                href="grado.php?grado=<?= urlencode(
                                    $tema["grado"]
                                ) ?>"
                                class="btn btn-light"
                            >

                                <i class="bi bi-arrow-left"></i>

                                Volver a temas

                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>



        <div class="row g-4">


            <!-- =============================================
                 CONTENIDO PRINCIPAL
            ============================================== -->

            <div class="col-12 col-lg-8 col-xl-9">


                <?php if ($hayContenido): ?>


                    <div
                        class="card contenido-card"
                    >

                        <div
                            class="card-body p-4 p-lg-5"
                        >

                            <div
                                class="contenido-educativo"
                            >

                                <?= $contenido["contenido"] ?>

                            </div>

                        </div>

                    </div>


                <?php else: ?>


                    <div
                        class="card contenido-card"
                    >

                        <div
                            class="estado-vacio"
                        >

                            <i
                                class="bi bi-journal-x fs-1 d-block mb-3"
                            ></i>


                            <h4>

                                Contenido próximamente

                            </h4>


                            <p class="mb-0">

                                Este tema todavía no tiene
                                una lección publicada.

                            </p>

                        </div>

                    </div>


                <?php endif; ?>



                <!-- =========================================
                     RECURSOS
                ========================================== -->

                <?php if (
                    !empty($recursos)
                ): ?>

                    <div class="mt-4">

                        <div class="mb-3">

                            <h3 class="h4 mb-1">

                                <i class="bi bi-collection-play text-primary"></i>

                                Recursos complementarios

                            </h3>


                            <p class="text-muted mb-0">

                                Material adicional para profundizar
                                en este tema.

                            </p>

                        </div>


                        <div class="row g-3">


                            <?php foreach (
                                $recursos
                                as $recurso
                            ): ?>


                                <div
                                    class="col-12 col-md-6"
                                >

                                    <div
                                        class="card recurso-card"
                                    >

                                        <div class="card-body">

                                            <div
                                                class="d-flex gap-3"
                                            >

                                                <div
                                                    class="icono-recurso flex-shrink-0"
                                                >

                                                    <?php

                                                    $iconos = [

                                                        "video" =>
                                                            "bi-play-circle-fill",

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
                                                            "bi-cpu-fill",

                                                        "presentacion" =>
                                                            "bi-easel-fill"

                                                    ];

                                                    $icono =
                                                        $iconos[
                                                            $recurso["tipo"]
                                                        ]
                                                        ??
                                                        "bi-link-45deg";

                                                    ?>


                                                    <i
                                                        class="bi <?= $icono ?>"
                                                    ></i>

                                                </div>


                                                <div
                                                    class="flex-grow-1"
                                                >

                                                    <h5 class="h6 mb-1">

                                                        <?= htmlspecialchars(
                                                            $recurso["titulo"]
                                                        ) ?>

                                                    </h5>


                                                    <span
                                                        class="badge text-bg-primary mb-2"
                                                    >

                                                        <?= htmlspecialchars(
                                                            ucfirst(
                                                                $recurso["tipo"]
                                                            )
                                                        ) ?>

                                                    </span>


                                                    <?php if (
                                                        !empty(
                                                            $recurso["descripcion"]
                                                        )
                                                    ): ?>

                                                        <p
                                                            class="small text-muted mb-3"
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
                                                        class="btn btn-sm btn-outline-primary"
                                                    >

                                                        Abrir recurso

                                                        <i
                                                            class="bi bi-box-arrow-up-right"
                                                        ></i>

                                                    </a>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>

                <?php endif; ?>


            </div>



            <!-- =============================================
                 SIDEBAR
            ============================================== -->

            <div class="col-12 col-lg-4 col-xl-3">


                <div
                    class="card sidebar-card mb-3"
                >

                    <div class="card-body">

                        <h5 class="h6 mb-3">

                            <i class="bi bi-graph-up-arrow text-primary"></i>

                            <?= $esAdministrador ? "Vista del tema" : "Tu progreso" ?>

                        </h5>


                        <div
                            class="d-flex justify-content-between mb-2"
                        >

                            <span
                                class="small text-muted"
                            >

                                Avance

                            </span>


                            <strong>

                                <?= $progreso ?>%

                            </strong>

                        </div>


                        <div
                            class="progress barra-progreso"
                        >

                            <div
                                class="progress-bar"
                                role="progressbar"
                                style="width: <?= $progreso ?>%"
                                aria-valuenow="<?= $progreso ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            ></div>

                        </div>


                        <div
                            class="small text-muted mt-3"
                        >

                            <?php if (
                                $progreso >= 100
                            ): ?>

                                <i
                                    class="bi bi-check-circle-fill text-success"
                                ></i>

                                Tema completado.

                            <?php elseif (
                                $progreso > 0
                            ): ?>

                                <i
                                    class="bi bi-clock-fill text-warning"
                                ></i>

                                Tema en progreso.

                            <?php else: ?>

                                <i
                                    class="bi bi-circle text-secondary"
                                ></i>

                                Tema pendiente.

                            <?php endif; ?>

                        </div>

                    </div>

                </div>



                <div
                    class="card sidebar-card"
                >

                    <div class="card-body">

                        <h5 class="h6 mb-3">

                            <i class="bi bi-info-circle text-primary"></i>

                            Información

                        </h5>


                        <div class="small">


                            <div class="mb-3">

                                <span
                                    class="text-muted"
                                >

                                    Materia

                                </span>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $tema["materia_nombre"]
                                    ) ?>

                                </div>

                            </div>


                            <div class="mb-3">

                                <span
                                    class="text-muted"
                                >

                                    Grado

                                </span>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $tema["grado"]
                                    ) ?>°

                                </div>

                            </div>


                            <div>

                                <span
                                    class="text-muted"
                                >

                                    Recursos

                                </span>

                                <div class="fw-semibold">

                                    <?= count(
                                        $recursos
                                    ) ?>

                                    disponibles

                                </div>

                            </div>

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
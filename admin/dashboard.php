<?php

require_once __DIR__ . "/../includes/seguridad.php";

exigirAdmin();


// =====================================================
// VARIABLES PRINCIPALES
// =====================================================

$total_estudiantes = 0;
$total_materias = 0;
$total_temas = 0;
$total_temas_contenido = 0;
$total_temas_sin_contenido = 0;
$total_recursos = 0;
$total_evaluaciones = 0;
$total_sugerencias = 0;

$temas_por_grado = [
    "9" => 0,
    "10" => 0,
    "11" => 0
];

$contenido_por_grado = [
    "9" => 0,
    "10" => 0,
    "11" => 0
];

$error_estadisticas = false;


// =====================================================
// DATOS DEL ADMINISTRADOR
// =====================================================

$nombresAdmin =
    trim($_SESSION["nombres"] ?? "");

$apellidosAdmin =
    trim($_SESSION["apellidos"] ?? "");

$nombreCompleto =
    trim(
        $nombresAdmin . " " . $apellidosAdmin
    );


if ($nombreCompleto === "") {

    $nombreCompleto = "Administrador";

}


// Primer nombre para la bienvenida

$primerNombre =
    explode(
        " ",
        $nombresAdmin
    )[0] ?? "Administrador";


// =====================================================
// ESTADÍSTICAS
// =====================================================

try {


    // -------------------------------------------------
    // ESTUDIANTES
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM usuarios
            WHERE id_rol = 2
            "
        );

    $total_estudiantes =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // MATERIAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM materias
            "
        );

    $total_materias =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM temas
            "
        );

    $total_temas =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS CON CONTENIDO
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM temas
            WHERE contenido IS NOT NULL
            AND TRIM(contenido) <> ''
            "
        );

    $total_temas_contenido =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // TEMAS SIN CONTENIDO
    // -------------------------------------------------

    $total_temas_sin_contenido =
        max(
            0,
            $total_temas -
            $total_temas_contenido
        );


    // -------------------------------------------------
    // RECURSOS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM recursos
            "
        );

    $total_recursos =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // EVALUACIONES
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM evaluaciones
            "
        );

    $total_evaluaciones =
        (int)$consulta->fetchColumn();


    // -------------------------------------------------
    // SUGERENCIAS
    // -------------------------------------------------

    $consulta =
        $conexion->query(
            "
            SELECT COUNT(*)
            FROM sugerencias
            "
        );

    $total_sugerencias =
        (int)$consulta->fetchColumn();


    // =================================================
    // TEMAS POR GRADO
    // =================================================

    $consulta =
        $conexion->query(
            "
            SELECT
                grado,
                COUNT(*) AS cantidad
            FROM temas
            WHERE grado IN ('9', '10', '11')
            GROUP BY grado
            "
        );


    while (
        $fila =
        $consulta->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        $grado =
            (string)$fila["grado"];


        if (
            isset(
                $temas_por_grado[$grado]
            )
        ) {

            $temas_por_grado[$grado] =
                (int)$fila["cantidad"];

        }

    }


    // =================================================
    // CONTENIDO POR GRADO
    // =================================================

    $consulta =
        $conexion->query(
            "
            SELECT
                grado,
                COUNT(*) AS cantidad
            FROM temas
            WHERE grado IN ('9', '10', '11')
            AND contenido IS NOT NULL
            AND TRIM(contenido) <> ''
            GROUP BY grado
            "
        );


    while (
        $fila =
        $consulta->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        $grado =
            (string)$fila["grado"];


        if (
            isset(
                $contenido_por_grado[$grado]
            )
        ) {

            $contenido_por_grado[$grado] =
                (int)$fila["cantidad"];

        }

    }


} catch (PDOException $e) {

    $error_estadisticas = true;

}


// =====================================================
// PORCENTAJE DE CONTENIDO
// =====================================================

$porcentaje_contenido = 0;


if ($total_temas > 0) {

    $porcentaje_contenido =
        round(
            (
                $total_temas_contenido /
                $total_temas
            ) * 100
        );

}


// =====================================================
// FUNCIÓN DE ESCAPE
// =====================================================

function e($valor): string
{

    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );

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

    <meta
        name="description"
        content="Panel de administración de ICFES Platform"
    >


    <title>
        Panel de administración | ICFES Platform
    </title>


    <!-- =================================================
         BOOTSTRAP
    ================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =================================================
         BOOTSTRAP ICONS
    ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =================================================
         ESTILOS
    ================================================== -->

    <style>

        :root {

            --admin-primary: #2563eb;

            --admin-primary-dark: #1d4ed8;

            --admin-sidebar: #111827;

            --admin-sidebar-hover: #1f2937;

            --admin-background: #f4f6fa;

            --admin-card: #ffffff;

            --admin-text: #111827;

            --admin-muted: #6b7280;

            --admin-border: #e5e7eb;

        }


        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            background:
                var(--admin-background);

            color:
                var(--admin-text);

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

        }


        /* =================================================
           SIDEBAR
        ================================================== */

        .sidebar {

            position: fixed;

            top: 0;

            left: 0;

            bottom: 0;

            width: 260px;

            background:
                var(--admin-sidebar);

            color: white;

            z-index: 1050;

            display: flex;

            flex-direction: column;

            transition:
                transform .25s ease;

        }


        .sidebar-brand {

            padding:
                1.35rem 1.35rem;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

        }


        .sidebar-brand a {

            color: white;

            text-decoration: none;

            display: flex;

            align-items: center;

            gap: .75rem;

            font-size: 1.15rem;

            font-weight: 700;

        }


        .sidebar-brand-icon {

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                var(--admin-primary);

            border-radius: 11px;

            font-size: 1.2rem;

        }


        .sidebar-subtitle {

            color:
                #9ca3af;

            font-size: .72rem;

            margin-top: .35rem;

            padding-left: 52px;

        }


        .sidebar-menu {

            padding:
                1.25rem .85rem;

            overflow-y: auto;

            flex: 1;

        }


        .sidebar-label {

            color:
                #6b7280;

            font-size: .68rem;

            font-weight: 700;

            letter-spacing: .08em;

            text-transform: uppercase;

            padding:
                .75rem .75rem .45rem;

        }


        .sidebar-link {

            display: flex;

            align-items: center;

            gap: .8rem;

            width: 100%;

            padding:
                .72rem .8rem;

            margin-bottom: .2rem;

            border-radius: 9px;

            color:
                #d1d5db;

            text-decoration: none;

            font-size: .9rem;

            transition:
                background .2s ease,
                color .2s ease;

        }


        .sidebar-link:hover {

            background:
                var(--admin-sidebar-hover);

            color: white;

        }


        .sidebar-link.active {

            background:
                var(--admin-primary);

            color: white;

        }


        .sidebar-link i {

            width: 20px;

            text-align: center;

            font-size: 1rem;

        }


        .sidebar-footer {

            padding: 1rem;

            border-top:
                1px solid
                rgba(255,255,255,.08);

        }


        .admin-mini-profile {

            display: flex;

            align-items: center;

            gap: .7rem;

            padding: .65rem;

            border-radius: 10px;

            background:
                rgba(255,255,255,.05);

        }


        .admin-avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                #374151;

            color: white;

            font-size: 1rem;

        }


        .admin-mini-name {

            font-size: .82rem;

            font-weight: 600;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .admin-mini-role {

            color: #9ca3af;

            font-size: .7rem;

        }


        /* =================================================
           MAIN
        ================================================== */

        .main-wrapper {

            margin-left: 260px;

            min-height: 100vh;

        }


        /* =================================================
           TOPBAR
        ================================================== */

        .topbar {

            height: 72px;

            background:
                white;

            border-bottom:
                1px solid
                var(--admin-border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 1.75rem;

            position: sticky;

            top: 0;

            z-index: 1000;

        }


        .topbar-title {

            font-size: .95rem;

            font-weight: 600;

        }


        .topbar-subtitle {

            color:
                var(--admin-muted);

            font-size: .75rem;

        }


        .topbar-user {

            display: flex;

            align-items: center;

            gap: .7rem;

        }


        .topbar-user-icon {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background:
                #eff6ff;

            color:
                var(--admin-primary);

            display: flex;

            align-items: center;

            justify-content: center;

        }


        /* =================================================
           CONTENT
        ================================================== */

        .dashboard-content {

            padding:
                1.75rem;

        }


        .welcome-title {

            font-size: 1.65rem;

            font-weight: 750;

            margin-bottom: .25rem;

        }


        .welcome-text {

            color:
                var(--admin-muted);

            margin-bottom: 0;

        }


        /* =================================================
           GENERAL CARDS
        ================================================== */

        .admin-card {

            background:
                var(--admin-card);

            border:
                1px solid
                var(--admin-border);

            border-radius: 15px;

            box-shadow:
                0 2px 8px
                rgba(15,23,42,.035);

        }


        /* =================================================
           STATS
        ================================================== */

        .stat-card {

            position: relative;

            overflow: hidden;

            height: 100%;

            transition:
                transform .2s ease,
                box-shadow .2s ease;

        }


        .stat-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 10px 25px
                rgba(15,23,42,.08);

        }


        .stat-card-body {

            padding: 1.25rem;

        }


        .stat-label {

            color:
                var(--admin-muted);

            font-size: .8rem;

            font-weight: 600;

            margin-bottom: .35rem;

        }


        .stat-number {

            font-size: 1.9rem;

            font-weight: 750;

            line-height: 1;

        }


        .stat-icon {

            width: 48px;

            height: 48px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.25rem;

        }


        .stat-footer {

            border-top:
                1px solid
                var(--admin-border);

            padding:
                .7rem 1.25rem;

            font-size: .75rem;

        }


        .stat-footer a {

            text-decoration: none;

            font-weight: 600;

        }


        .icon-blue {

            background:
                #eff6ff;

            color:
                #2563eb;

        }


        .icon-green {

            background:
                #ecfdf5;

            color:
                #059669;

        }


        .icon-yellow {

            background:
                #fffbeb;

            color:
                #d97706;

        }


        .icon-cyan {

            background:
                #ecfeff;

            color:
                #0891b2;

        }


        .icon-red {

            background:
                #fef2f2;

            color:
                #dc2626;

        }


        .icon-purple {

            background:
                #f5f3ff;

            color:
                #7c3aed;

        }


        /* =================================================
           SECTION TITLE
        ================================================== */

        .section-title {

            font-size: 1rem;

            font-weight: 700;

            margin-bottom: 0;

        }


        .section-description {

            font-size: .75rem;

            color:
                var(--admin-muted);

            margin-bottom: 0;

        }


        /* =================================================
           QUICK ACTIONS
        ================================================== */

        .quick-action {

            display: flex;

            align-items: center;

            gap: .85rem;

            padding: 1rem;

            border:
                1px solid
                var(--admin-border);

            border-radius: 12px;

            text-decoration: none;

            color:
                var(--admin-text);

            height: 100%;

            transition:
                transform .2s ease,
                border-color .2s ease,
                box-shadow .2s ease;

        }


        .quick-action:hover {

            transform:
                translateY(-2px);

            border-color:
                #bfdbfe;

            box-shadow:
                0 6px 18px
                rgba(37,99,235,.08);

            color:
                var(--admin-text);

        }


        .quick-action-icon {

            width: 44px;

            height: 44px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            font-size: 1.1rem;

        }


        .quick-action-title {

            font-size: .85rem;

            font-weight: 700;

            margin-bottom: .15rem;

        }


        .quick-action-text {

            color:
                var(--admin-muted);

            font-size: .7rem;

        }


        /* =================================================
           CONTENT MANAGEMENT
        ================================================== */

        .content-management {

            padding: 1.4rem;

        }


        .content-progress-number {

            font-size: 2.3rem;

            font-weight: 750;

        }


        .content-progress-label {

            color:
                var(--admin-muted);

            font-size: .78rem;

        }


        .progress {

            height: 9px;

            border-radius: 20px;

            background:
                #eef2f7;

        }


        .progress-bar {

            border-radius: 20px;

        }


        .grade-mini-card {

            border:
                1px solid
                var(--admin-border);

            border-radius: 12px;

            padding: 1rem;

        }


        .grade-number {

            font-size: 1.4rem;

            font-weight: 750;

        }


        .grade-description {

            color:
                var(--admin-muted);

            font-size: .7rem;

        }


        .grade-link {

            font-size: .72rem;

            text-decoration: none;

            font-weight: 600;

        }


        /* =================================================
           ACTIVITY
        ================================================== */

        .empty-state {

            text-align: center;

            padding:
                2.25rem 1rem;

            color:
                var(--admin-muted);

        }


        .empty-state i {

            font-size: 2.2rem;

            opacity: .5;

        }


        /* =================================================
           ALERT
        ================================================== */

        .dashboard-alert {

            border: 0;

            border-radius: 12px;

        }


        /* =================================================
           MOBILE
        ================================================== */

        .mobile-menu-button {

            display: none;

        }


        .sidebar-overlay {

            display: none;

        }


        @media (max-width: 991.98px) {

            .sidebar {

                transform:
                    translateX(-100%);

            }


            .sidebar.show {

                transform:
                    translateX(0);

            }


            .main-wrapper {

                margin-left: 0;

            }


            .mobile-menu-button {

                display: inline-flex;

            }


            .sidebar-overlay {

                position: fixed;

                inset: 0;

                background:
                    rgba(0,0,0,.45);

                z-index: 1040;

            }


            .sidebar-overlay.show {

                display: block;

            }

        }


        @media (max-width: 575.98px) {

            .dashboard-content {

                padding: 1rem;

            }


            .topbar {

                padding:
                    0 1rem;

            }


            .topbar-subtitle {

                display: none;

            }


            .welcome-title {

                font-size: 1.35rem;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     OVERLAY MOBILE
====================================================== -->

<div
    id="sidebarOverlay"
    class="sidebar-overlay"
></div>



<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside
    id="sidebar"
    class="sidebar"
>


    <!-- BRAND -->

    <div class="sidebar-brand">

        <a
            href="dashboard.php"
        >

            <div class="sidebar-brand-icon">

                <i
                    class="bi bi-mortarboard-fill"
                ></i>

            </div>

            <div>

                ICFES Platform

                <div
                    class="sidebar-subtitle"
                >

                    Administración

                </div>

            </div>

        </a>

    </div>



    <!-- MENÚ -->

    <div class="sidebar-menu">


        <div
            class="sidebar-label"
        >

            Principal

        </div>


        <a
            href="dashboard.php"
            class="sidebar-link active"
        >

            <i
                class="bi bi-grid-1x2-fill"
            ></i>

            Dashboard

        </a>



        <div
            class="sidebar-label mt-2"
        >

            Académico

        </div>


        <a
            href="contenidos/index.php"
            class="sidebar-link"
        >

            <i
                class="bi bi-journal-richtext"
            ></i>

            Contenidos

        </a>


        <a
            href="contenidos/index.php"
            class="sidebar-link"
        >

            <i
                class="bi bi-book-half"
            ></i>

            Temas

        </a>


        <a
            href="#"
            class="sidebar-link"
            onclick="
                alert(
                    'La gestión de recursos estará disponible en la siguiente etapa.'
                );
                return false;
            "
        >

            <i
                class="bi bi-collection-play-fill"
            ></i>

            Recursos

        </a>


        <a
            href="#"
            class="sidebar-link"
            onclick="
                alert(
                    'La gestión de evaluaciones estará disponible en la siguiente etapa.'
                );
                return false;
            "
        >

            <i
                class="bi bi-clipboard-check-fill"
            ></i>

            Evaluaciones

        </a>



        <div
            class="sidebar-label mt-2"
        >

            Usuarios

        </div>


        <a
            href="estudiantes/index.php"
            class="sidebar-link"
        >

            <i
                class="bi bi-people-fill"
            ></i>

            Estudiantes

        </a>



        <div
            class="sidebar-label mt-2"
        >

            Comunicación

        </div>


        <a
            href="#"
            class="sidebar-link"
            onclick="
                alert(
                    'La gestión de sugerencias estará disponible en la siguiente etapa.'
                );
                return false;
            "
        >

            <i
                class="bi bi-chat-left-text-fill"
            ></i>

            Sugerencias

        </a>

    </div>



    <!-- PERFIL -->

    <div class="sidebar-footer">

        <div
            class="admin-mini-profile"
        >

            <div class="admin-avatar">

                <i
                    class="bi bi-person-fill"
                ></i>

            </div>


            <div
                class="flex-grow-1 overflow-hidden"
            >

                <div
                    class="admin-mini-name"
                >

                    <?= e($nombreCompleto) ?>

                </div>


                <div
                    class="admin-mini-role"
                >

                    Administrador

                </div>

            </div>

        </div>

    </div>


</aside>



<!-- =====================================================
     CONTENIDO PRINCIPAL
====================================================== -->

<div class="main-wrapper">


    <!-- =================================================
         TOPBAR
    ================================================== -->

    <header class="topbar">


        <div
            class="d-flex align-items-center gap-3"
        >

            <button
                id="mobileMenuButton"
                type="button"
                class="
                    btn
                    btn-light
                    border
                    mobile-menu-button
                "
                aria-label="Abrir menú"
            >

                <i
                    class="bi bi-list fs-5"
                ></i>

            </button>


            <div>

                <div
                    class="topbar-title"
                >

                    Panel de administración

                </div>


                <div
                    class="topbar-subtitle"
                >

                    Gestión general de ICFES Platform

                </div>

            </div>

        </div>



        <div
            class="topbar-user"
        >

            <div
                class="text-end d-none d-sm-block"
            >

                <div
                    class="fw-semibold small"
                >

                    <?= e($nombreCompleto) ?>

                </div>


                <div
                    class="text-muted"
                    style="font-size:.7rem;"
                >

                    Administrador

                </div>

            </div>


            <div
                class="topbar-user-icon"
            >

                <i
                    class="bi bi-person-fill"
                ></i>

            </div>


            <a
                href="../cerrar_sesion.php"
                class="
                    btn
                    btn-outline-secondary
                    btn-sm
                "
                title="Cerrar sesión"
            >

                <i
                    class="bi bi-box-arrow-right"
                ></i>

                <span
                    class="d-none d-md-inline"
                >

                    Salir

                </span>

            </a>

        </div>


    </header>



    <!-- =================================================
         DASHBOARD
    ================================================== -->

    <main class="dashboard-content">


        <!-- =================================================
             BIENVENIDA
        ================================================== -->

        <div
            class="
                d-flex
                justify-content-between
                align-items-end
                gap-3
                mb-4
                flex-wrap
            "
        >

            <div>

                <h1
                    class="welcome-title"
                >

                    Hola,
                    <?= e($primerNombre) ?> 👋

                </h1>


                <p
                    class="welcome-text"
                >

                    Aquí puedes controlar y administrar
                    el contenido de la plataforma.

                </p>

            </div>


            <div>

                <a
                    href="contenidos/index.php"
                    class="
                        btn
                        btn-primary
                    "
                >

                    <i
                        class="
                            bi
                            bi-plus-lg
                        "
                    ></i>

                    Administrar contenidos

                </a>

            </div>

        </div>



        <!-- =================================================
             ERROR
        ================================================== -->

        <?php if (
            $error_estadisticas
        ): ?>

            <div
                class="
                    alert
                    alert-danger
                    dashboard-alert
                    mb-4
                "
            >

                <i
                    class="
                        bi
                        bi-exclamation-triangle-fill
                    "
                ></i>

                No fue posible cargar una o varias
                estadísticas del sistema.

            </div>

        <?php endif; ?>



        <!-- =================================================
             ESTADÍSTICAS PRINCIPALES
        ================================================== -->

        <div
            class="
                row
                g-3
                mb-4
            "
        >


            <!-- ESTUDIANTES -->

            <div
                class="
                    col-12
                    col-sm-6
                    col-xl-3
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-start
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Estudiantes

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_estudiantes ?>

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-blue
                            "
                        >

                            <i
                                class="bi bi-people-fill"
                            ></i>

                        </div>

                    </div>


                    <div
                        class="
                            stat-footer
                            bg-white
                        "
                    >

                        <a
                            href="estudiantes/index.php"
                            class="text-primary"
                        >

                            Gestionar estudiantes

                            <i
                                class="bi bi-arrow-right"
                            ></i>

                        </a>

                    </div>

                </div>

            </div>



            <!-- MATERIAS -->

            <div
                class="
                    col-12
                    col-sm-6
                    col-xl-3
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-start
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Materias

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_materias ?>

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-green
                            "
                        >

                            <i
                                class="bi bi-book-fill"
                            ></i>

                        </div>

                    </div>


                    <div
                        class="
                            stat-footer
                            bg-white
                        "
                    >

                        <span
                            class="text-muted"
                        >

                            Estructura académica

                        </span>

                    </div>

                </div>

            </div>



            <!-- TEMAS -->

            <div
                class="
                    col-12
                    col-sm-6
                    col-xl-3
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-start
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Temas

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_temas ?>

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-yellow
                            "
                        >

                            <i
                                class="bi bi-journal-text"
                            ></i>

                        </div>

                    </div>


                    <div
                        class="
                            stat-footer
                            bg-white
                        "
                    >

                        <a
                            href="contenidos/index.php"
                            class="text-warning"
                        >

                            Administrar temas

                            <i
                                class="bi bi-arrow-right"
                            ></i>

                        </a>

                    </div>

                </div>

            </div>



            <!-- RECURSOS -->

            <div
                class="
                    col-12
                    col-sm-6
                    col-xl-3
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-start
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Recursos

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_recursos ?>

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-cyan
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-collection-play-fill
                                "
                            ></i>

                        </div>

                    </div>


                    <div
                        class="
                            stat-footer
                            bg-white
                        "
                    >

                        <span
                            class="text-muted"
                        >

                            Recursos registrados

                        </span>

                    </div>

                </div>

            </div>


        </div>



        <!-- =================================================
             SEGUNDA FILA ESTADÍSTICAS
        ================================================== -->

        <div
            class="
                row
                g-3
                mb-4
            "
        >


            <!-- EVALUACIONES -->

            <div
                class="
                    col-12
                    col-md-4
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-center
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Evaluaciones

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_evaluaciones ?>

                            </div>


                            <div
                                class="
                                    text-muted
                                    mt-1
                                "
                                style="font-size:.7rem;"
                            >

                                Registradas en el sistema

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-red
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-clipboard-check-fill
                                "
                            ></i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- SUGERENCIAS -->

            <div
                class="
                    col-12
                    col-md-4
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-center
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Sugerencias

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $total_sugerencias ?>

                            </div>


                            <div
                                class="
                                    text-muted
                                    mt-1
                                "
                                style="font-size:.7rem;"
                            >

                                Recibidas

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-purple
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-chat-left-text-fill
                                "
                            ></i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- CONTENIDO -->

            <div
                class="
                    col-12
                    col-md-4
                "
            >

                <div
                    class="
                        admin-card
                        stat-card
                    "
                >

                    <div
                        class="
                            stat-card-body
                            d-flex
                            justify-content-between
                            align-items-center
                        "
                    >

                        <div>

                            <div
                                class="stat-label"
                            >

                                Contenido publicado

                            </div>


                            <div
                                class="stat-number"
                            >

                                <?= $porcentaje_contenido ?>%

                            </div>


                            <div
                                class="
                                    text-muted
                                    mt-1
                                "
                                style="font-size:.7rem;"
                            >

                                <?= $total_temas_contenido ?>
                                de
                                <?= $total_temas ?>
                                temas

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-green
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-file-earmark-check-fill
                                "
                            ></i>

                        </div>

                    </div>

                </div>

            </div>


        </div>



        <!-- =================================================
             CONTENIDO ACADÉMICO
        ================================================== -->

        <div
            class="
                row
                g-3
                mb-4
            "
        >


            <!-- ESTADO DE CONTENIDOS -->

            <div
                class="
                    col-12
                    col-lg-7
                "
            >

                <div
                    class="
                        admin-card
                        h-100
                    "
                >

                    <div
                        class="
                            content-management
                        "
                    >


                        <div
                            class="
                                d-flex
                                justify-content-between
                                align-items-start
                                mb-4
                            "
                        >

                            <div>

                                <h2
                                    class="section-title"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-journal-richtext
                                            text-primary
                                            me-1
                                        "
                                    ></i>

                                    Contenido académico

                                </h2>


                                <p
                                    class="
                                        section-description
                                        mt-1
                                    "
                                >

                                    Estado general de los
                                    contenidos de los temas.

                                </p>

                            </div>


                            <a
                                href="contenidos/index.php"
                                class="
                                    btn
                                    btn-sm
                                    btn-outline-primary
                                "
                            >

                                Gestionar

                                <i
                                    class="
                                        bi
                                        bi-arrow-right
                                    "
                                ></i>

                            </a>

                        </div>



                        <!-- PROGRESO -->

                        <div
                            class="
                                d-flex
                                align-items-end
                                gap-3
                                mb-3
                            "
                        >

                            <div>

                                <div
                                    class="
                                        content-progress-number
                                    "
                                >

                                    <?= $porcentaje_contenido ?>%

                                </div>


                                <div
                                    class="
                                        content-progress-label
                                    "
                                >

                                    de los temas tienen
                                    contenido publicado

                                </div>

                            </div>


                            <div
                                class="flex-grow-1"
                            >

                                <div
                                    class="
                                        progress
                                        mb-2
                                    "
                                >

                                    <div
                                        class="
                                            progress-bar
                                            bg-primary
                                        "
                                        style="
                                            width:
                                            <?= $porcentaje_contenido ?>%;
                                        "
                                    ></div>

                                </div>


                                <div
                                    class="
                                        d-flex
                                        justify-content-between
                                        small
                                        text-muted
                                    "
                                >

                                    <span>

                                        <?= $total_temas_contenido ?>
                                        publicados

                                    </span>


                                    <span>

                                        <?= $total_temas_sin_contenido ?>
                                        pendientes

                                    </span>

                                </div>

                            </div>

                        </div>



                        <!-- ESTADOS -->

                        <div
                            class="
                                row
                                g-3
                                mt-2
                            "
                        >


                            <div
                                class="
                                    col-6
                                "
                            >

                                <div
                                    class="
                                        p-3
                                        rounded-3
                                    "
                                    style="
                                        background:#ecfdf5;
                                    "
                                >

                                    <div
                                        class="
                                            d-flex
                                            align-items-center
                                            gap-2
                                        "
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-check-circle-fill
                                                text-success
                                            "
                                        ></i>


                                        <span
                                            class="
                                                small
                                                text-muted
                                            "
                                        >

                                            Publicados

                                        </span>

                                    </div>


                                    <div
                                        class="
                                            fs-4
                                            fw-bold
                                            mt-1
                                        "
                                    >

                                        <?= $total_temas_contenido ?>

                                    </div>

                                </div>

                            </div>



                            <div
                                class="
                                    col-6
                                "
                            >

                                <div
                                    class="
                                        p-3
                                        rounded-3
                                    "
                                    style="
                                        background:#fffbeb;
                                    "
                                >

                                    <div
                                        class="
                                            d-flex
                                            align-items-center
                                            gap-2
                                        "
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-pencil-square
                                                text-warning
                                            "
                                        ></i>


                                        <span
                                            class="
                                                small
                                                text-muted
                                            "
                                        >

                                            Pendientes

                                        </span>

                                    </div>


                                    <div
                                        class="
                                            fs-4
                                            fw-bold
                                            mt-1
                                        "
                                    >

                                        <?= $total_temas_sin_contenido ?>

                                    </div>

                                </div>

                            </div>


                        </div>


                    </div>

                </div>

            </div>



            <!-- GRADOS -->

            <div
                class="
                    col-12
                    col-lg-5
                "
            >

                <div
                    class="
                        admin-card
                        h-100
                        p-4
                    "
                >


                    <div
                        class="
                            d-flex
                            justify-content-between
                            align-items-start
                            mb-3
                        "
                    >

                        <div>

                            <h2
                                class="section-title"
                            >

                                <i
                                    class="
                                        bi
                                        bi-mortarboard-fill
                                        text-primary
                                        me-1
                                    "
                                ></i>

                                Distribución académica

                            </h2>


                            <p
                                class="
                                    section-description
                                    mt-1
                                "
                            >

                                Temas y contenidos por grado.

                            </p>

                        </div>

                    </div>



                    <div
                        class="
                            d-flex
                            flex-column
                            gap-2
                        "
                    >


                        <!-- 9 -->

                        <div
                            class="grade-mini-card"
                        >

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div
                                        class="grade-number"
                                    >

                                        9°

                                    </div>


                                    <div
                                        class="
                                            grade-description
                                        "
                                    >

                                        <?= $temas_por_grado["9"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["9"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="
                                        contenidos/index.php?grado=9
                                    "
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i
                                        class="
                                            bi
                                            bi-arrow-right
                                        "
                                    ></i>

                                </a>

                            </div>

                        </div>



                        <!-- 10 -->

                        <div
                            class="grade-mini-card"
                        >

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div
                                        class="grade-number"
                                    >

                                        10°

                                    </div>


                                    <div
                                        class="
                                            grade-description
                                        "
                                    >

                                        <?= $temas_por_grado["10"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["10"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="
                                        contenidos/index.php?grado=10
                                    "
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i
                                        class="
                                            bi
                                            bi-arrow-right
                                        "
                                    ></i>

                                </a>

                            </div>

                        </div>



                        <!-- 11 -->

                        <div
                            class="grade-mini-card"
                        >

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div
                                        class="grade-number"
                                    >

                                        11°

                                    </div>


                                    <div
                                        class="
                                            grade-description
                                        "
                                    >

                                        <?= $temas_por_grado["11"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["11"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="
                                        contenidos/index.php?grado=11
                                    "
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i
                                        class="
                                            bi
                                            bi-arrow-right
                                        "
                                    ></i>

                                </a>

                            </div>

                        </div>


                    </div>

                </div>

            </div>


        </div>



        <!-- =================================================
             ACCIONES RÁPIDAS
        ================================================== -->

        <div
            class="
                admin-card
                p-4
                mb-4
            "
        >


            <div
                class="mb-3"
            >

                <h2
                    class="section-title"
                >

                    Acciones rápidas

                </h2>


                <p
                    class="
                        section-description
                        mt-1
                    "
                >

                    Accede rápidamente a las herramientas
                    principales de administración.

                </p>

            </div>



            <div
                class="
                    row
                    g-3
                "
            >


                <!-- CONTENIDOS -->

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-3
                    "
                >

                    <a
                        href="contenidos/index.php"
                        class="quick-action"
                    >

                        <div
                            class="
                                quick-action-icon
                                icon-blue
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-journal-richtext
                                "
                            ></i>

                        </div>


                        <div>

                            <div
                                class="
                                    quick-action-title
                                "
                            >

                                Contenidos

                            </div>


                            <div
                                class="
                                    quick-action-text
                                "
                            >

                                Editar contenido de temas

                            </div>

                        </div>

                    </a>

                </div>



                <!-- ESTUDIANTES -->

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-3
                    "
                >

                    <a
                        href="estudiantes/index.php"
                        class="quick-action"
                    >

                        <div
                            class="
                                quick-action-icon
                                icon-green
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-people-fill
                                "
                            ></i>

                        </div>


                        <div>

                            <div
                                class="
                                    quick-action-title
                                "
                            >

                                Estudiantes

                            </div>


                            <div
                                class="
                                    quick-action-text
                                "
                            >

                                Gestionar usuarios

                            </div>

                        </div>

                    </a>

                </div>



                <!-- TEMAS -->

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-3
                    "
                >

                    <a
                        href="contenidos/index.php"
                        class="quick-action"
                    >

                        <div
                            class="
                                quick-action-icon
                                icon-yellow
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-pencil-square
                                "
                            ></i>

                        </div>


                        <div>

                            <div
                                class="
                                    quick-action-title
                                "
                            >

                                Editar temas

                            </div>


                            <div
                                class="
                                    quick-action-text
                                "
                            >

                                Descripción y contenido

                            </div>

                        </div>

                    </a>

                </div>



                <!-- RECURSOS -->

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-3
                    "
                >

                    <a
                        href="contenidos/index.php"
                        class="quick-action"
                    >

                        <div
                            class="
                                quick-action-icon
                                icon-cyan
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-collection-play-fill
                                "
                            ></i>

                        </div>


                        <div>

                            <div
                                class="
                                    quick-action-title
                                "
                            >

                                Recursos

                            </div>


                            <div
                                class="
                                    quick-action-text
                                "
                            >

                                Acceso desde cada tema

                            </div>

                        </div>

                    </a>

                </div>


            </div>


        </div>



        <!-- =================================================
             RESUMEN INFERIOR
        ================================================== -->

        <div
            class="
                row
                g-3
            "
        >


            <!-- RESUMEN DE CONTENIDOS -->

            <div
                class="
                    col-12
                    col-lg-8
                "
            >

                <div
                    class="
                        admin-card
                        p-4
                        h-100
                    "
                >


                    <div
                        class="
                            d-flex
                            justify-content-between
                            align-items-center
                            mb-3
                        "
                    >

                        <div>

                            <h2
                                class="section-title"
                            >

                                Estado del proyecto

                            </h2>


                            <p
                                class="
                                    section-description
                                    mt-1
                                "
                            >

                                Resumen de los módulos
                                actualmente conectados.

                            </p>

                        </div>

                    </div>



                    <div
                        class="
                            table-responsive
                        "
                    >

                        <table
                            class="
                                table
                                align-middle
                                mb-0
                            "
                        >

                            <thead>

                                <tr>

                                    <th
                                        class="border-0"
                                    >

                                        Módulo

                                    </th>

                                    <th
                                        class="border-0"
                                    >

                                        Registros

                                    </th>

                                    <th
                                        class="border-0"
                                    >

                                        Estado

                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <tr>

                                    <td>

                                        <i
                                            class="
                                                bi
                                                bi-people-fill
                                                text-primary
                                                me-2
                                            "
                                        ></i>

                                        Estudiantes

                                    </td>

                                    <td>

                                        <?= $total_estudiantes ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                                text-bg-success
                                            "
                                        >

                                            Disponible

                                        </span>

                                    </td>

                                </tr>



                                <tr>

                                    <td>

                                        <i
                                            class="
                                                bi
                                                bi-journal-text
                                                text-warning
                                                me-2
                                            "
                                        ></i>

                                        Temas

                                    </td>

                                    <td>

                                        <?= $total_temas ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                                text-bg-success
                                            "
                                        >

                                            Disponible

                                        </span>

                                    </td>

                                </tr>



                                <tr>

                                    <td>

                                        <i
                                            class="
                                                bi
                                                bi-file-earmark-richtext
                                                text-primary
                                                me-2
                                            "
                                        ></i>

                                        Contenido educativo

                                    </td>

                                    <td>

                                        <?= $total_temas_contenido ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                                text-bg-success
                                            "
                                        >

                                            Editor activo

                                        </span>

                                    </td>

                                </tr>



                                <tr>

                                    <td>

                                        <i
                                            class="
                                                bi
                                                bi-collection-play-fill
                                                text-info
                                                me-2
                                            "
                                        ></i>

                                        Recursos

                                    </td>

                                    <td>

                                        <?= $total_recursos ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                                text-bg-success
                                            "
                                        >

                                            Disponible

                                        </span>

                                    </td>

                                </tr>



                                <tr>

                                    <td>

                                        <i
                                            class="
                                                bi
                                                bi-clipboard-check-fill
                                                text-danger
                                                me-2
                                            "
                                        ></i>

                                        Evaluaciones

                                    </td>

                                    <td>

                                        <?= $total_evaluaciones ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                                text-bg-warning
                                            "
                                        >

                                            En desarrollo

                                        </span>

                                    </td>

                                </tr>


                            </tbody>

                        </table>

                    </div>


                </div>

            </div>



            <!-- ACCESO CONTENIDOS -->

            <div
                class="
                    col-12
                    col-lg-4
                "
            >

                <div
                    class="
                        admin-card
                        p-4
                        h-100
                    "
                    style="
                        background:
                        linear-gradient(
                            145deg,
                            #1d4ed8,
                            #2563eb
                        );
                        color:white;
                    "
                >


                    <div
                        class="
                            d-flex
                            align-items-center
                            justify-content-center
                        "
                        style="
                            width:55px;
                            height:55px;
                            border-radius:14px;
                            background:
                            rgba(255,255,255,.15);
                        "
                    >

                        <i
                            class="
                                bi
                                bi-magic
                                fs-4
                            "
                        ></i>

                    </div>


                    <h3
                        class="
                            mt-4
                            mb-2
                            fw-bold
                        "
                        style="font-size:1.25rem;"
                    >

                        Construye tus temas

                    </h3>


                    <p
                        class="
                            mb-4
                            opacity-75
                        "
                        style="
                            font-size:.82rem;
                            line-height:1.6;
                        "
                    >

                        Utiliza el editor visual para crear,
                        ampliar y mantener actualizado el
                        contenido educativo que verán los
                        estudiantes.

                    </p>


                    <a
                        href="contenidos/index.php"
                        class="
                            btn
                            btn-light
                            w-100
                        "
                    >

                        <i
                            class="
                                bi
                                bi-pencil-square
                            "
                        ></i>

                        Abrir editor de contenidos

                    </a>


                </div>

            </div>


        </div>


    </main>

</div>



<!-- =====================================================
     JAVASCRIPT
====================================================== -->

<script>

    const sidebar =
        document.getElementById(
            "sidebar"
        );


    const overlay =
        document.getElementById(
            "sidebarOverlay"
        );


    const menuButton =
        document.getElementById(
            "mobileMenuButton"
        );


    function abrirMenu() {

        sidebar.classList.add(
            "show"
        );

        overlay.classList.add(
            "show"
        );

    }


    function cerrarMenu() {

        sidebar.classList.remove(
            "show"
        );

        overlay.classList.remove(
            "show"
        );

    }


    menuButton?.addEventListener(
        "click",
        abrirMenu
    );


    overlay?.addEventListener(
        "click",
        cerrarMenu
    );


    document
        .querySelectorAll(
            ".sidebar-link"
        )
        .forEach(
            function(enlace) {

                enlace.addEventListener(
                    "click",
                    function() {

                        if (
                            window.innerWidth <= 991
                        ) {

                            cerrarMenu();

                        }

                    }
                );

            }
        );


    window.addEventListener(
        "resize",
        function() {

            if (
                window.innerWidth > 991
            ) {

                cerrarMenu();

            }

        }
    );

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
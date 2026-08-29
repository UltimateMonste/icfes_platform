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

$primerNombre =
    explode(
        " ",
        $nombresAdmin
    )[0] ?? "Administrador";

if ($primerNombre === "") {

    $primerNombre = "Administrador";

}


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
        content="Panel de administración de Studia360"
    >

    <title>
        Dashboard | Studia360
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
                1.35rem;

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

            font-weight: 750;

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

            box-shadow:
                0 5px 18px
                rgba(37,99,235,.25);

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
                1.25rem 1rem;

            overflow-y: auto;

            flex: 1;

        }


        .sidebar-label {

            color:
                #6b7280;

            font-size: .68rem;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .08em;

            padding:
                .75rem .75rem .45rem;

        }


        .sidebar-link {

            display: flex;

            align-items: center;

            gap: .75rem;

            color:
                #d1d5db;

            text-decoration: none;

            padding:
                .72rem .8rem;

            border-radius: 10px;

            font-size: .84rem;

            margin-bottom: .2rem;

            transition:
                background .2s ease,
                color .2s ease,
                transform .2s ease;

        }


        .sidebar-link i {

            width: 20px;

            text-align: center;

            font-size: 1rem;

        }


        .sidebar-link:hover {

            color: white;

            background:
                var(--admin-sidebar-hover);

            transform:
                translateX(2px);

        }


        .sidebar-link.active {

            color: white;

            background:
                var(--admin-primary);

            box-shadow:
                0 5px 15px
                rgba(37,99,235,.2);

        }


        .sidebar-footer {

            padding:
                1rem;

            border-top:
                1px solid
                rgba(255,255,255,.08);

        }


        .admin-mini-profile {

            display: flex;

            align-items: center;

            gap: .7rem;

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

            flex-shrink: 0;

        }


        .admin-mini-name {

            font-size: .82rem;

            font-weight: 600;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .admin-mini-role {

            color:
                #9ca3af;

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

            background: white;

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

            font-weight: 650;

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

            max-width: 1600px;

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

            padding:
                1.25rem;

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
           SECTION
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

            padding:
                1.4rem;

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
           MODULE CARDS
        ================================================== */

        .module-card {

            border:
                1px solid
                var(--admin-border);

            border-radius: 12px;

            padding: 1rem;

            height: 100%;

        }


        .module-icon {

            width: 40px;
            height: 40px;

            border-radius: 10px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 1rem;

        }


        .module-title {

            font-size: .85rem;

            font-weight: 700;

        }


        .module-description {

            color:
                var(--admin-muted);

            font-size: .72rem;

            line-height: 1.5;

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

                Studia360

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

        <div class="sidebar-label">
            Principal
        </div>


        <a
            href="dashboard.php"
            class="sidebar-link active"
        >

            <i class="bi bi-grid-1x2-fill"></i>

            Dashboard

        </a>


        <div class="sidebar-label mt-2">
            Académico
        </div>


        <a
            href="contenidos/index.php"
            class="sidebar-link"
        >

            <i class="bi bi-journal-richtext"></i>

            Contenidos

        </a>


        <a
            href="contenidos/materias.php"
            class="sidebar-link"
        >

            <i class="bi bi-book-half"></i>

            Materias y temas

        </a>


        <a
            href="contenidos/materias.php"
            class="sidebar-link"
        >

            <i class="bi bi-diagram-3-fill"></i>

            Estructura académica

        </a>


        <a
            href="contenidos/index.php"
            class="sidebar-link"
        >

            <i class="bi bi-collection-play-fill"></i>

            Recursos

        </a>


        <a
            href="contenidos/index.php"
            class="sidebar-link"
        >

            <i class="bi bi-clipboard-check-fill"></i>

            Evaluaciones

        </a>


        <div class="sidebar-label mt-2">
            Usuarios
        </div>


        <a
            href="estudiantes/index.php"
            class="sidebar-link"
        >

            <i class="bi bi-people-fill"></i>

            Estudiantes

        </a>


        <div class="sidebar-label mt-2">
            Comunicación
        </div>


        <a
            href="#sugerencias"
            class="sidebar-link"
        >

            <i class="bi bi-chat-left-text-fill"></i>

            Sugerencias

        </a>


        <div class="sidebar-label mt-2">
            Personalización
        </div>


        <a
            href="#niveles"
            class="sidebar-link"
        >

            <i class="bi bi-bar-chart-fill"></i>

            Niveles y progreso

        </a>


        <a
            href="#coleccionables"
            class="sidebar-link"
        >

            <i class="bi bi-stars"></i>

            Coleccionables

        </a>

    </div>


    <!-- PERFIL -->

    <div class="sidebar-footer">

        <div class="admin-mini-profile">

            <div class="admin-avatar">

                <i class="bi bi-person-fill"></i>

            </div>


            <div
                class="flex-grow-1 overflow-hidden"
            >

                <div class="admin-mini-name">

                    <?= e($nombreCompleto) ?>

                </div>

                <div class="admin-mini-role">

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

        <div class="d-flex align-items-center gap-3">

            <button
                type="button"
                id="mobileMenuButton"
                class="btn btn-outline-secondary btn-sm mobile-menu-button"
                aria-label="Abrir menú"
            >

                <i class="bi bi-list fs-5"></i>

            </button>


            <div>

                <div class="topbar-title">

                    Panel de administración

                </div>

                <div class="topbar-subtitle">

                    Gestión general de Studia360

                </div>

            </div>

        </div>


        <div class="topbar-user">

            <div class="text-end d-none d-sm-block">

                <div class="fw-semibold small">

                    <?= e($nombreCompleto) ?>

                </div>

                <div
                    class="text-muted"
                    style="font-size:.7rem;"
                >

                    Administrador

                </div>

            </div>


            <div class="topbar-user-icon">

                <i class="bi bi-person-fill"></i>

            </div>


            <a
                href="../cerrar_sesion.php"
                class="btn btn-outline-secondary btn-sm"
                title="Cerrar sesión"
            >

                <i class="bi bi-box-arrow-right"></i>

                <span class="d-none d-md-inline">
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

                <h1 class="welcome-title">

                    Hola,
                    <?= e($primerNombre) ?> 👋

                </h1>


                <p class="welcome-text">

                    Aquí puedes controlar y administrar
                    el contenido de Studia360.

                </p>

            </div>


            <div>

                <a
                    href="contenidos/materias.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-lg"></i>

                    Administrar materias

                </a>

            </div>

        </div>


        <!-- =================================================
             ERROR
        ================================================== -->

        <?php if ($error_estadisticas): ?>

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
                            align-items-center
                        "
                    >

                        <div>

                            <div class="stat-label">
                                Estudiantes
                            </div>

                            <div class="stat-number">
                                <?= $total_estudiantes ?>
                            </div>

                            <div
                                class="text-muted mt-1"
                                style="font-size:.7rem;"
                            >

                                Registrados

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-blue
                            "
                        >

                            <i class="bi bi-people-fill"></i>

                        </div>

                    </div>


                    <div class="stat-footer">

                        <a
                            href="estudiantes/index.php"
                            class="text-primary"
                        >

                            Gestionar estudiantes

                            <i class="bi bi-arrow-right"></i>

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
                            align-items-center
                        "
                    >

                        <div>

                            <div class="stat-label">
                                Materias
                            </div>

                            <div class="stat-number">
                                <?= $total_materias ?>
                            </div>

                            <div
                                class="text-muted mt-1"
                                style="font-size:.7rem;"
                            >

                                Registradas

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-green
                            "
                        >

                            <i class="bi bi-book-fill"></i>

                        </div>

                    </div>


                    <div class="stat-footer">

                        <a
                            href="contenidos/materias.php"
                            class="text-success"
                        >

                            Gestionar materias

                            <i class="bi bi-arrow-right"></i>

                        </a>

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
                            align-items-center
                        "
                    >

                        <div>

                            <div class="stat-label">
                                Temas
                            </div>

                            <div class="stat-number">
                                <?= $total_temas ?>
                            </div>

                            <div
                                class="text-muted mt-1"
                                style="font-size:.7rem;"
                            >

                                <?= $total_temas_contenido ?>
                                con contenido

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-yellow
                            "
                        >

                            <i class="bi bi-journal-text"></i>

                        </div>

                    </div>


                    <div class="stat-footer">

                        <a
                            href="contenidos/temas.php"
                            class="text-warning"
                        >

                            Gestionar temas

                            <i class="bi bi-arrow-right"></i>

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
                            align-items-center
                        "
                    >

                        <div>

                            <div class="stat-label">
                                Recursos
                            </div>

                            <div class="stat-number">
                                <?= $total_recursos ?>
                            </div>

                            <div
                                class="text-muted mt-1"
                                style="font-size:.7rem;"
                            >

                                Asociados a temas

                            </div>

                        </div>


                        <div
                            class="
                                stat-icon
                                icon-cyan
                            "
                        >

                            <i class="bi bi-collection-play-fill"></i>

                        </div>

                    </div>


                    <div class="stat-footer">

                        <a
                            href="contenidos/index.php"
                            class="text-info"
                        >

                            Administrar recursos

                            <i class="bi bi-arrow-right"></i>

                        </a>

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

                    <div class="content-management">


                        <div
                            class="
                                d-flex
                                justify-content-between
                                align-items-start
                                mb-4
                            "
                        >

                            <div>

                                <h2 class="section-title">

                                    <i
                                        class="
                                            bi
                                            bi-bar-chart-fill
                                            text-primary
                                            me-1
                                        "
                                    ></i>

                                    Estado del contenido

                                </h2>


                                <p
                                    class="
                                        section-description
                                        mt-1
                                    "
                                >

                                    Progreso general de construcción
                                    de los temas.

                                </p>

                            </div>


                            <div
                                class="text-end"
                            >

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

                                    completado

                                </div>

                            </div>

                        </div>


                        <div class="progress mb-4">

                            <div
                                class="
                                    progress-bar
                                    bg-primary
                                "
                                role="progressbar"
                                style="
                                    width:
                                    <?= $porcentaje_contenido ?>%;
                                "
                            ></div>

                        </div>


                        <div class="row g-3">


                            <div class="col-6">

                                <div
                                    class="
                                        p-3
                                        rounded-3
                                        bg-light
                                    "
                                >

                                    <div
                                        class="
                                            fw-bold
                                            fs-5
                                        "
                                    >

                                        <?= $total_temas_contenido ?>

                                    </div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                        "
                                    >

                                        Temas con contenido

                                    </div>

                                </div>

                            </div>


                            <div class="col-6">

                                <div
                                    class="
                                        p-3
                                        rounded-3
                                        bg-light
                                    "
                                >

                                    <div
                                        class="
                                            fw-bold
                                            fs-5
                                        "
                                    >

                                        <?= $total_temas_sin_contenido ?>

                                    </div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                        "
                                    >

                                        Pendientes

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- DISTRIBUCIÓN POR GRADO -->

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

                    <div class="mb-3">

                        <h2 class="section-title">

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


                    <div
                        class="
                            d-flex
                            flex-column
                            gap-2
                        "
                    >


                        <!-- 9 -->

                        <div class="grade-mini-card">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div class="grade-number">
                                        9°
                                    </div>

                                    <div class="grade-description">

                                        <?= $temas_por_grado["9"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["9"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="contenidos/index.php?grado=9"
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            </div>

                        </div>


                        <!-- 10 -->

                        <div class="grade-mini-card">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div class="grade-number">
                                        10°
                                    </div>

                                    <div class="grade-description">

                                        <?= $temas_por_grado["10"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["10"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="contenidos/index.php?grado=10"
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            </div>

                        </div>


                        <!-- 11 -->

                        <div class="grade-mini-card">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                "
                            >

                                <div>

                                    <div class="grade-number">
                                        11°
                                    </div>

                                    <div class="grade-description">

                                        <?= $temas_por_grado["11"] ?>
                                        temas ·
                                        <?= $contenido_por_grado["11"] ?>
                                        con contenido

                                    </div>

                                </div>


                                <a
                                    href="contenidos/index.php?grado=11"
                                    class="grade-link text-primary"
                                >

                                    Ver

                                    <i class="bi bi-arrow-right"></i>

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

            <div class="mb-3">

                <h2 class="section-title">

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


            <div class="row g-3">


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

                            <div class="quick-action-title">

                                Contenidos

                            </div>


                            <div class="quick-action-text">

                                Administra el contenido
                                de los temas.

                            </div>

                        </div>

                    </a>

                </div>


                <!-- MATERIAS -->

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-3
                    "
                >

                    <a
                        href="contenidos/materias.php"
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
                                    bi-book-fill
                                "
                            ></i>

                        </div>


                        <div>

                            <div class="quick-action-title">

                                Materias

                            </div>


                            <div class="quick-action-text">

                                Crea y modifica materias
                                y su estructura.

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
                        href="contenidos/materias.php"
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
                                    bi-journal-text
                                "
                            ></i>

                        </div>


                        <div>

                            <div class="quick-action-title">

                                Temas

                            </div>


                            <div class="quick-action-text">

                                Crea, organiza y edita
                                los temas.

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
                                icon-purple
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

                            <div class="quick-action-title">

                                Estudiantes

                            </div>


                            <div class="quick-action-text">

                                Consulta y administra
                                estudiantes.

                            </div>

                        </div>

                    </a>

                </div>

            </div>

        </div>


        <!-- =================================================
             RESUMEN DEL SISTEMA
        ================================================== -->

        <div
            class="
                admin-card
                p-4
                mb-4
            "
        >

            <div class="mb-3">

                <h2 class="section-title">

                    Resumen del sistema

                </h2>


                <p
                    class="
                        section-description
                        mt-1
                    "
                >

                    Estado actual de los módulos de Studia360.

                </p>

            </div>


            <div class="row g-3">


                <!-- CONTENIDOS -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-blue
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-journal-richtext
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Contenidos

                        </div>


                        <div class="module-description mb-3">

                            Editor visual para crear
                            y mantener las lecciones.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-success
                            "
                        >

                            Conectado

                        </span>

                    </div>

                </div>


                <!-- MATERIAS -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-green
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-book-fill
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Materias

                        </div>


                        <div class="module-description mb-3">

                            Organización de materias
                            y estructura académica.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-success
                            "
                        >

                            Disponible

                        </span>

                    </div>

                </div>


                <!-- RECURSOS -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-cyan
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-collection-play-fill
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Recursos

                        </div>


                        <div class="module-description mb-3">

                            PDFs, vídeos, enlaces y
                            actividades complementarias.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>


                <!-- EVALUACIONES -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-yellow
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-clipboard-check-fill
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Evaluaciones

                        </div>


                        <div class="module-description mb-3">

                            Sistema de actividades y
                            evaluación del aprendizaje.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>


                <!-- SUGERENCIAS -->

                <div
                    class="col-12 col-md-6 col-xl-3"
                    id="sugerencias"
                >

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-purple
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-chat-left-text-fill
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Sugerencias

                        </div>


                        <div class="module-description mb-3">

                            Buzón para ideas, solicitudes
                            y reclamos de estudiantes.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>


                <!-- NIVELES -->

                <div
                    class="col-12 col-md-6 col-xl-3"
                    id="niveles"
                >

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-blue
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-bar-chart-fill
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Niveles y progreso

                        </div>


                        <div class="module-description mb-3">

                            Configuración de puntos,
                            niveles y progreso.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>


                <!-- COLECCIONABLES -->

                <div
                    class="col-12 col-md-6 col-xl-3"
                    id="coleccionables"
                >

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-purple
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-stars
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Coleccionables

                        </div>


                        <div class="module-description mb-3">

                            Personalización, avatares y
                            objetos desbloqueables.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>


                <!-- PROGRESO -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="module-card">

                        <div
                            class="
                                module-icon
                                icon-green
                                mb-3
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-graph-up-arrow
                                "
                            ></i>

                        </div>


                        <div class="module-title mb-1">

                            Progreso estudiantil

                        </div>


                        <div class="module-description mb-3">

                            Seguimiento del avance de cada
                            estudiante.

                        </div>


                        <span
                            class="
                                badge
                                rounded-pill
                                text-bg-warning
                            "
                        >

                            En desarrollo

                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             BLOQUE FINAL
        ================================================== -->

        <div
            class="
                admin-card
                p-4
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
                    row
                    align-items-center
                    g-4
                "
            >

                <div class="col-12 col-lg-8">

                    <div
                        class="
                            d-flex
                            align-items-center
                            gap-3
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


                        <div>

                            <h3
                                class="
                                    mb-1
                                    fw-bold
                                "
                                style="
                                    font-size:1.2rem;
                                "
                            >

                                Construye Studia360

                            </h3>


                            <p
                                class="
                                    mb-0
                                    opacity-75
                                "
                                style="
                                    font-size:.82rem;
                                    line-height:1.6;
                                "
                            >

                                Crea materias, organiza temas y
                                utiliza el editor visual para
                                construir contenido de apoyo
                                para los estudiantes.

                            </p>

                        </div>

                    </div>

                </div>


                <div
                    class="
                        col-12
                        col-lg-4
                    "
                >

                    <a
                        href="contenidos/materias.php"
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
                                me-1
                            "
                        ></i>

                        Administrar estructura

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
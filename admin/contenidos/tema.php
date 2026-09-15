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

<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">

<style id="studia360-contenidos-polished">
:root{
  --c-accent:#2563eb;
  --c-accent2:#4f46e5;
  --c-soft:#eff6ff;
  --c-bg:#f6f8fc;
  --c-card:#fff;
  --c-text:#1f2937;
  --c-muted:#748196;
  --c-line:#e5eaf1;
  --c-input:#fff;
  --c-track:#e9eef5;
}
body.s360-content-theme{
  --c-accent:#2563eb;--c-accent2:#4f46e5;--c-soft:#eff6ff;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 7%,transparent),transparent 25rem),var(--c-bg)!important;
  color:var(--c-text)!important;
  transition:background .25s ease,color .25s ease;
}
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}

body.s360-content-theme .adm-theme-toggle{
  position:fixed!important;right:20px!important;bottom:20px!important;z-index:2000!important;
  width:50px!important;height:50px!important;padding:0!important;margin:0!important;
  display:grid!important;place-items:center!important;
  border:0!important;border-radius:16px!important;
  color:#fff!important;background:linear-gradient(145deg,var(--c-accent),var(--c-accent2))!important;
  box-shadow:0 12px 30px color-mix(in srgb,var(--c-accent) 28%,transparent)!important;
  cursor:pointer!important;outline:none!important;transition:transform .2s ease,box-shadow .2s ease!important;
}
body.s360-content-theme .adm-theme-toggle:hover{transform:translateY(-3px)!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.15rem!important;line-height:1!important}

body.s360-content-theme .adm-theme-panel{
  position:fixed!important;right:20px!important;bottom:82px!important;z-index:1999!important;
  width:290px!important;max-width:calc(100vw - 28px)!important;
  padding:16px!important;margin:0!important;
  display:block!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;border-radius:18px!important;
  box-shadow:0 20px 55px rgba(20,35,60,.16)!important;
  opacity:0!important;visibility:hidden!important;pointer-events:none!important;
  transform:translateY(8px) scale(.98)!important;
  transition:opacity .18s ease,transform .18s ease,visibility .18s ease!important;
}
body.s360-content-theme .adm-theme-panel.open{
  opacity:1!important;visibility:visible!important;pointer-events:auto!important;
  transform:none!important;
}
body.s360-content-theme .adm-theme-title{
  margin:0 0 4px!important;font-size:.84rem!important;line-height:1.25!important;
  font-weight:850!important;color:var(--c-text)!important;
}
body.s360-content-theme .adm-theme-sub{
  margin:0 0 13px!important;font-size:.66rem!important;line-height:1.45!important;
  color:var(--c-muted)!important;
}
body.s360-content-theme .adm-theme-grid{
  display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;
}
body.s360-content-theme .adm-theme-option{
  appearance:none!important;width:100%!important;min-height:74px!important;
  padding:9px!important;margin:0!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;box-shadow:none!important;
  font:inherit!important;transition:.18s ease!important;
}
body.s360-content-theme .adm-theme-option:hover,
body.s360-content-theme .adm-theme-option.active{
  border-color:color-mix(in srgb,var(--c-accent) 42%,var(--c-line))!important;
  background:var(--c-soft)!important;transform:translateY(-1px)!important;
}
body.s360-content-theme .adm-swatch{
  display:block!important;width:100%!important;height:23px!important;margin:0 0 7px!important;
  border-radius:8px!important;
}
body.s360-content-theme .adm-theme-option strong{
  display:block!important;font-size:.67rem!important;line-height:1.15!important;
}
body.s360-content-theme .adm-theme-option small{
  display:block!important;margin-top:3px!important;font-size:.57rem!important;
  line-height:1.15!important;color:var(--c-muted)!important;
}
body.s360-content-theme .adm-mode-btn{
  appearance:none!important;width:100%!important;min-height:38px!important;
  margin:9px 0 0!important;padding:8px 10px!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;font:700 .68rem/1.2 system-ui,sans-serif!important;
}
body.s360-content-theme .adm-mode-btn:hover{
  color:var(--c-accent)!important;background:var(--c-soft)!important;
}

/* Capa visual de las pantallas existentes; no cambia su estructura ni sus datos. */
body.s360-content-theme .card,
body.s360-content-theme .card-soft,
body.s360-content-theme .editor-card,
body.s360-content-theme .info-card,
body.s360-content-theme .content-card,
body.s360-content-theme .preview-header,
body.s360-content-theme .resource,
body.s360-content-theme .resource-card,
body.s360-content-theme .recurso-card,
body.s360-content-theme .sidebar-card,
body.s360-content-theme .panel,
body.s360-content-theme .stat,
body.s360-content-theme .theme-info,
body.s360-content-theme .delete-card{
  background:var(--c-card)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,
body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{
  color:var(--c-text);
}
body.s360-content-theme .text-muted,body.s360-content-theme .muted,
body.s360-content-theme .url-help{color:var(--c-muted)!important}
body.s360-content-theme .text-primary{color:var(--c-accent)!important}
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .btn-primary{
  background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .btn-outline-primary{
  color:var(--c-accent)!important;border-color:color-mix(in srgb,var(--c-accent) 30%,var(--c-line))!important;
  background:var(--c-card)!important;
}
body.s360-content-theme .btn-outline-primary:hover{
  color:#fff!important;background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .badge-type,
body.s360-content-theme .icon,
body.s360-content-theme .resource-icon,
body.s360-content-theme .icono-recurso{
  background:var(--c-soft)!important;color:var(--c-accent)!important;
}
body.s360-content-theme .hero,
body.s360-content-theme .preview-hero{
  background:linear-gradient(125deg,var(--c-accent),var(--c-accent2))!important;
}
body.s360-content-theme .table{
  --bs-table-bg:var(--c-card);--bs-table-color:var(--c-text);--bs-table-border-color:var(--c-line);
}
body.s360-content-theme .table thead th{background:var(--c-soft)!important;color:var(--c-text)!important}
body.s360-content-theme .dropdown-menu{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme .dropdown-item{color:var(--c-text)!important}
body.s360-content-theme .dropdown-item:hover{background:var(--c-soft)!important;color:var(--c-accent)!important}

/* Acciones compactas: solo icono, sin deformar los botones. */
body.s360-content-theme .topic-actions .btn,
body.s360-content-theme .recent-row .btn,
body.s360-content-theme .resource .btn.btn-sm,
body.s360-content-theme .resource-card .btn.btn-sm,
body.s360-content-theme .recurso-card .btn.btn-sm{
  width:36px!important;height:36px!important;min-width:36px!important;min-height:36px!important;
  padding:0!important;display:inline-grid!important;place-items:center!important;
  border-radius:10px!important;font-size:0!important;line-height:1!important;
}
body.s360-content-theme .topic-actions .btn i,
body.s360-content-theme .recent-row .btn i,
body.s360-content-theme .resource .btn.btn-sm i,
body.s360-content-theme .resource-card .btn.btn-sm i,
body.s360-content-theme .recurso-card .btn.btn-sm i{
  margin:0!important;font-size:.9rem!important;line-height:1!important;
}

/* Oscuro */
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;--c-card:#172236;--c-text:#edf2f8;--c-muted:#9ba8ba;
  --c-line:#2b374b;--c-input:#111827;--c-track:#263247;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 13%,transparent),transparent 25rem),var(--c-bg)!important;
}
body.s360-content-theme.s360-content-dark .navbar{
  background:rgba(13,20,36,.94)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .navbar-brand,
body.s360-content-theme.s360-content-dark .text-dark,
body.s360-content-theme.s360-content-dark h1,
body.s360-content-theme.s360-content-dark h2,
body.s360-content-theme.s360-content-dark h3,
body.s360-content-theme.s360-content-dark h4,
body.s360-content-theme.s360-content-dark h5,
body.s360-content-theme.s360-content-dark h6{color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .modal-content{
  background:var(--c-card)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .note-toolbar{background:#202c41!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-editable{background:var(--c-input)!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .note-statusbar{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-btn{
  background:#27344a!important;color:#dce4ee!important;border-color:#354158!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-panel{box-shadow:0 22px 60px rgba(0,0,0,.42)!important}

@media(max-width:767px){
  body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}
  body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}
}
</style>

</head>


<body class="s360-admin">


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


<!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button></div>

<script id="studia360-contenidos-theme-controller">
(function(){
  'use strict';
  var body=document.body;
  var key='studia360_theme';
  var toggle=document.getElementById('admThemeToggle');
  var panel=document.getElementById('admThemePanel');
  var modeBtn=document.getElementById('admModeBtn');
  var options=document.querySelectorAll('.adm-theme-option');

  var themes={
    blue:{cls:'',label:'Azul'},
    purple:{cls:'s360-accent-purple',label:'Violeta'},
    orange:{cls:'s360-accent-orange',label:'Naranja'},
    green:{cls:'s360-accent-green',label:'Verde'}
  };

  var saved={theme:'blue',mode:'light'};
  try{
    var raw=localStorage.getItem(key);
    if(raw){
      var parsed=JSON.parse(raw);
      if(parsed && typeof parsed==='object') saved=Object.assign(saved,parsed);
    }
  }catch(e){}
  if(!themes[saved.theme]) saved.theme='blue';
  if(saved.mode!=='dark' && saved.mode!=='light') saved.mode='light';

  function persist(){
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
  }

  function apply(){
    body.classList.add('s360-content-theme');
    Object.keys(themes).forEach(function(name){
      if(themes[name].cls) body.classList.remove(themes[name].cls);
    });
    if(themes[saved.theme].cls) body.classList.add(themes[saved.theme].cls);
    body.classList.toggle('s360-content-dark',saved.mode==='dark');

    options.forEach(function(option){
      option.classList.toggle('active',option.getAttribute('data-theme')===saved.theme);
    });

    if(modeBtn){
      modeBtn.innerHTML=saved.mode==='dark'
        ? '<i class="bi bi-moon-stars me-2"></i>Modo oscuro'
        : '<i class="bi bi-sun me-2"></i>Modo claro';
    }
  }

  window.admSetTheme=function(theme){
    if(!themes[theme]) return;
    saved.theme=theme;
    persist();
    apply();
  };

  window.admToggleMode=function(){
    saved.mode=saved.mode==='dark'?'light':'dark';
    persist();
    apply();
  };

  if(toggle && panel){
    toggle.addEventListener('click',function(event){
      event.preventDefault();
      event.stopPropagation();
      panel.classList.toggle('open');
    });
    panel.addEventListener('click',function(event){
      event.stopPropagation();
    });
    document.addEventListener('click',function(){
      panel.classList.remove('open');
    });
  }

  apply();
})();
</script>

</body>

</html>
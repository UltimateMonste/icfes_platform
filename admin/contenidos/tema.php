<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


// =========================================================
// CONFIGURACIÓN
// =========================================================

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idTema || $idTema <= 0) {

    header("Location: ../dashboard.php");
    exit;

}


$errores = [];
$mensajes = [];


// =========================================================
// CSRF
// =========================================================

if (
    empty($_SESSION["csrf_contenido_tema"])
) {

    $_SESSION["csrf_contenido_tema"] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION["csrf_contenido_tema"];


// =========================================================
// OBTENER TEMA
// =========================================================

$tema = null;

try {

    $sql = "
        SELECT
            t.id_tema,
            t.nombre AS tema_nombre,
            t.descripcion AS tema_descripcion,
            t.grado,
            m.id_materia,
            m.nombre AS materia_nombre

        FROM temas t

        INNER JOIN materias m
            ON t.id_materia = m.id_materia

        WHERE t.id_tema = ?

        LIMIT 1
    ";

    $stmt =
        $conexion->prepare($sql);

    $stmt->execute([
        $idTema
    ]);

    $tema =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$tema) {

        $errores[] =
            "El tema solicitado no existe.";

    }

} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar la información del tema.";

}


// =========================================================
// CONTENIDO EXISTENTE
// =========================================================

$contenido = "";
$estadoContenido = "Publicado";
$fechaActualizacion = null;

if ($tema) {

    try {

        $sqlContenido = "
            SELECT
                id_contenido,
                contenido,
                estado,
                fecha_actualizacion

            FROM contenido_temas

            WHERE id_tema = ?

            LIMIT 1
        ";

        $stmtContenido =
            $conexion->prepare(
                $sqlContenido
            );

        $stmtContenido->execute([
            $idTema
        ]);

        $contenidoExistente =
            $stmtContenido->fetch(
                PDO::FETCH_ASSOC
            );


        if ($contenidoExistente) {

            $contenido =
                $contenidoExistente["contenido"];

            $estadoContenido =
                $contenidoExistente["estado"];

            $fechaActualizacion =
                $contenidoExistente[
                    "fecha_actualizacion"
                ];

        }

    } catch (PDOException $e) {

        $errores[] =
            "No fue posible cargar el contenido existente.";

    }

}


// =========================================================
// GUARDAR CONTENIDO
// =========================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && $tema
) {

    $tokenFormulario =
        $_POST["csrf_token"] ?? "";


    if (
        !hash_equals(
            $csrfToken,
            $tokenFormulario
        )
    ) {

        $errores[] =
            "La sesión de seguridad ha expirado. Recarga la página e inténtalo nuevamente.";

    }


    $contenidoFormulario =
        $_POST["contenido"] ?? "";

    $estadoFormulario =
        $_POST["estado"] ?? "Borrador";


    if (
        !in_array(
            $estadoFormulario,
            ["Publicado", "Borrador"],
            true
        )
    ) {

        $estadoFormulario =
            "Borrador";

    }


    /*
    |---------------------------------------------------------
    | VALIDACIÓN
    |---------------------------------------------------------
    */

    $contenidoTexto =
        trim(
            strip_tags(
                $contenidoFormulario
            )
        );


    if (
        $contenidoTexto === ""
    ) {

        $errores[] =
            "El contenido del tema no puede estar vacío.";

    }


    /*
    |---------------------------------------------------------
    | GUARDAR
    |---------------------------------------------------------
    */

    if (
        empty($errores)
    ) {

        try {

            /*
            |-------------------------------------------------
            | ¿YA EXISTE?
            |-------------------------------------------------
            */

            $sqlExiste = "
                SELECT
                    id_contenido

                FROM contenido_temas

                WHERE id_tema = ?

                LIMIT 1
            ";

            $stmtExiste =
                $conexion->prepare(
                    $sqlExiste
                );

            $stmtExiste->execute([
                $idTema
            ]);

            $idContenido =
                $stmtExiste->fetchColumn();


            /*
            |-------------------------------------------------
            | ACTUALIZAR
            |-------------------------------------------------
            */

            if ($idContenido) {

                $sqlActualizar = "
                    UPDATE contenido_temas

                    SET
                        contenido = ?,
                        estado = ?,
                        fecha_actualizacion = CURRENT_TIMESTAMP

                    WHERE id_tema = ?
                ";

                $stmtActualizar =
                    $conexion->prepare(
                        $sqlActualizar
                    );

                $stmtActualizar->execute([
                    $contenidoFormulario,
                    $estadoFormulario,
                    $idTema
                ]);

            }

            /*
            |-------------------------------------------------
            | INSERTAR
            |-------------------------------------------------
            */

            else {

                $sqlInsertar = "
                    INSERT INTO contenido_temas
                    (
                        id_tema,
                        contenido,
                        estado
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ";

                $stmtInsertar =
                    $conexion->prepare(
                        $sqlInsertar
                    );

                $stmtInsertar->execute([
                    $idTema,
                    $contenidoFormulario,
                    $estadoFormulario
                ]);

            }


            /*
            |-------------------------------------------------
            | ACTUALIZAR VARIABLES
            |-------------------------------------------------
            */

            $contenido =
                $contenidoFormulario;

            $estadoContenido =
                $estadoFormulario;

            $fechaActualizacion =
                date("Y-m-d H:i:s");


            $mensajes[] =
                "El contenido del tema se guardó correctamente.";


        } catch (PDOException $e) {

            $errores[] =
                "No fue posible guardar el contenido. " .
                $e->getMessage();

        }

    }

}


// =========================================================
// RECURSOS DEL TEMA
// =========================================================

$recursos = [];

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
                fuente,
                visitas,
                estado

            FROM recursos

            WHERE id_tema = ?

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

    } catch (PDOException $e) {

        $errores[] =
            "No fue posible cargar los recursos del tema.";

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
            $tema["tema_nombre"] ?? "Tema"
        ) ?>

        | Administración

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


    <!-- =====================================================
         SUMMERNOTE
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../../assets/summernote/summernote-lite.min.css"
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


        .encabezado {

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


        .editor-card {

            border:
                0;

            border-radius:
                18px;

            box-shadow:
                0 8px 30px
                rgba(0,0,0,.07);

        }


        .editor-card .card-header {

            background:
                white;

            border-bottom:
                1px solid #edf0f4;

            border-radius:
                18px 18px 0 0;

        }


        .note-editor {

            border:
                1px solid #dee2e6 !important;

            border-radius:
                0 0 10px 10px;

            overflow:
                hidden;

        }


        .note-toolbar {

            background:
                #f8f9fa !important;

            border-bottom:
                1px solid #dee2e6 !important;

        }


        .note-editable {

            min-height:
                520px;

            background:
                white;

            font-size:
                16px;

            line-height:
                1.75;

            padding:
                30px !important;

        }


        .estado-badge {

            font-size:
                .8rem;

        }


        .info-box {

            border:
                0;

            border-radius:
                14px;

            background:
                #e9f7ff;

            color:
                #075985;

        }


        .recurso-item {

            border:
                1px solid #e8edf3;

            border-radius:
                12px;

            padding:
                14px;

            background:
                white;

        }


        .recurso-item + .recurso-item {

            margin-top:
                10px;

        }


        .acciones {

            position:
                sticky;

            bottom:
                15px;

            z-index:
                10;

        }


        .acciones-inner {

            background:
                rgba(255,255,255,.96);

            backdrop-filter:
                blur(8px);

            border:
                1px solid #e3e8ef;

            border-radius:
                14px;

            padding:
                12px 16px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.10);

        }


        .preview-contenido {

            background:
                white;

            border:
                1px solid #e5eaf0;

            border-radius:
                14px;

            padding:
                25px;

        }


        .preview-contenido img {

            max-width:
                100%;

            height:
                auto;

        }


        .preview-contenido table {

            width:
                100%;

        }


        .preview-contenido iframe {

            max-width:
                100%;

        }


        @media (
            max-width: 767px
        ) {

            .note-editable {

                min-height:
                    400px;

                padding:
                    18px !important;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-dark bg-dark">

    <div class="container-fluid">

        <a
            href="../dashboard.php"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            ICFES Platform

        </a>


        <div class="d-flex align-items-center gap-3">

            <span class="text-white">

                <i class="bi bi-shield-check"></i>

                Administrador

            </span>


            <a
                href="../cerrar_sesion.php"
                class="btn btn-outline-light btn-sm"
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

<div class="container-fluid py-4">


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


    <?php foreach ($mensajes as $mensaje): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?= htmlspecialchars($mensaje) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endforeach; ?>


    <?php if ($tema): ?>


        <!-- =================================================
             ENCABEZADO
        ================================================== -->

        <div class="card encabezado mb-4">

            <div class="card-body p-4">

                <div
                    class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3"
                >

                    <div>

                        <div
                            class="small text-uppercase opacity-75 mb-2"
                        >

                            Administración de contenidos

                        </div>


                        <h1 class="h2 mb-2">

                            <?= htmlspecialchars(
                                $tema["tema_nombre"]
                            ) ?>

                        </h1>


                        <p class="mb-0 opacity-75">

                            <?= htmlspecialchars(
                                $tema["materia_nombre"]
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                $tema["grado"]
                            ) ?>°

                        </p>

                    </div>


                    <div
                        class="d-flex gap-2 flex-wrap"
                    >

                        <a
                            href="../../estudiante/tema.php?id=<?= $idTema ?>"
                            target="_blank"
                            class="btn btn-light"
                        >

                            <i class="bi bi-eye"></i>

                            Ver tema

                        </a>


                        <a
                            href="../dashboard.php"
                            class="btn btn-outline-light"
                        >

                            <i class="bi bi-arrow-left"></i>

                            Volver

                        </a>

                    </div>

                </div>

            </div>

        </div>



        <!-- =================================================
             INFORMACIÓN
        ================================================== -->

        <div
            class="alert info-box mb-4"
        >

            <div
                class="d-flex gap-3 align-items-start"
            >

                <i class="bi bi-info-circle-fill fs-4"></i>

                <div>

                    <strong>
                        Editor de contenido educativo
                    </strong>

                    <div class="small mt-1">

                        Construye aquí la lección completa.
                        Puedes utilizar títulos, imágenes,
                        tablas, enlaces, listas, vídeos,
                        bloques de información y mucho más.

                    </div>

                </div>

            </div>

        </div>



        <div class="row g-4">


            <!-- =============================================
                 EDITOR
            ============================================== -->

            <div class="col-12 col-xl-9">

                <form
                    method="POST"
                    id="formContenido"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $csrfToken
                        ) ?>"
                    >


                    <div class="card editor-card">

                        <div
                            class="card-header p-3"
                        >

                            <div
                                class="d-flex justify-content-between align-items-center flex-wrap gap-2"
                            >

                                <div>

                                    <h5 class="mb-1">

                                        <i class="bi bi-pencil-square text-primary"></i>

                                        Contenido de la lección

                                    </h5>

                                    <small class="text-muted">

                                        Este contenido será mostrado
                                        a los estudiantes cuando esté publicado.

                                    </small>

                                </div>


                                <span
                                    id="estadoActual"
                                    class="badge rounded-pill estado-badge
                                    <?= $estadoContenido === "Publicado"
                                        ? "text-bg-success"
                                        : "text-bg-warning" ?>"
                                >

                                    <?= htmlspecialchars(
                                        $estadoContenido
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="card-body p-3">


                            <textarea
                                id="editorContenido"
                                name="contenido"
                            ><?= htmlspecialchars(
                                $contenido
                            ) ?></textarea>


                            <div class="row mt-4 g-3">


                                <div class="col-md-6">

                                    <label
                                        for="estado"
                                        class="form-label fw-semibold"
                                    >

                                        Estado del contenido

                                    </label>


                                    <select
                                        name="estado"
                                        id="estado"
                                        class="form-select"
                                    >

                                        <option
                                            value="Borrador"
                                            <?= $estadoContenido === "Borrador"
                                                ? "selected"
                                                : "" ?>
                                        >

                                            Borrador

                                        </option>


                                        <option
                                            value="Publicado"
                                            <?= $estadoContenido === "Publicado"
                                                ? "selected"
                                                : "" ?>
                                        >

                                            Publicado

                                        </option>

                                    </select>

                                    <div class="form-text">

                                        Los borradores no serán visibles
                                        para los estudiantes.

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">

                                        Última actualización

                                    </label>


                                    <div
                                        class="form-control bg-light"
                                    >

                                        <?php if (
                                            $fechaActualizacion
                                        ): ?>

                                            <i class="bi bi-clock"></i>

                                            <?= htmlspecialchars(
                                                $fechaActualizacion
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">

                                                Aún no se ha guardado contenido.

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>



                    <!-- =====================================
                         BOTONES
                    ====================================== -->

                    <div class="acciones mt-3">

                        <div
                            class="acciones-inner d-flex justify-content-between align-items-center flex-wrap gap-2"
                        >

                            <button
                                type="button"
                                id="btnLimpiar"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-eraser"></i>

                                Limpiar editor

                            </button>


                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    name="estado"
                                    value="Borrador"
                                    class="btn btn-outline-warning"
                                    onclick="document.getElementById('estado').value='Borrador';"
                                >

                                    <i class="bi bi-file-earmark"></i>

                                    Guardar borrador

                                </button>


                                <button
                                    type="submit"
                                    name="estado"
                                    value="Publicado"
                                    class="btn btn-primary"
                                    onclick="document.getElementById('estado').value='Publicado';"
                                >

                                    <i class="bi bi-cloud-arrow-up"></i>

                                    Guardar y publicar

                                </button>

                            </div>

                        </div>

                    </div>

                </form>

            </div>



            <!-- =============================================
                 INFORMACIÓN LATERAL
            ============================================== -->

            <div class="col-12 col-xl-3">


                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-body">

                        <h5 class="mb-3">

                            <i class="bi bi-journal-text text-primary"></i>

                            Sobre el tema

                        </h5>


                        <p class="text-muted small">

                            <?= htmlspecialchars(
                                $tema["tema_descripcion"]
                                ?? "Sin descripción."
                            ) ?>

                        </p>


                        <hr>


                        <div class="small">

                            <div class="mb-2">

                                <strong>Materia:</strong>

                                <br>

                                <?= htmlspecialchars(
                                    $tema["materia_nombre"]
                                ) ?>

                            </div>


                            <div>

                                <strong>Grado:</strong>

                                <br>

                                <?= htmlspecialchars(
                                    $tema["grado"]
                                ) ?>°

                            </div>

                        </div>

                    </div>

                </div>



                <!-- =========================================
                     RECURSOS
                ========================================== -->

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-center mb-3"
                        >

                            <h5 class="mb-0">

                                <i class="bi bi-collection-play text-primary"></i>

                                Recursos

                            </h5>


                            <span class="badge text-bg-light">

                                <?= count($recursos) ?>

                            </span>

                        </div>


                        <?php if (
                            empty($recursos)
                        ): ?>

                            <div
                                class="text-center text-muted py-4"
                            >

                                <i
                                    class="bi bi-folder2-open fs-2 d-block mb-2"
                                ></i>

                                <small>

                                    Este tema todavía no tiene
                                    recursos complementarios.

                                </small>

                            </div>

                        <?php else: ?>


                            <?php foreach (
                                $recursos
                                as $recurso
                            ): ?>

                                <div class="recurso-item">

                                    <div
                                        class="fw-semibold small"
                                    >

                                        <?= htmlspecialchars(
                                            $recurso["titulo"]
                                        ) ?>

                                    </div>


                                    <div class="mt-1">

                                        <span
                                            class="badge text-bg-primary"
                                        >

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $recurso["tipo"]
                                                )
                                            ) ?>

                                        </span>


                                        <?php if (
                                            $recurso["estado"] === "Activo"
                                        ): ?>

                                            <span
                                                class="badge text-bg-success"
                                            >

                                                Activo

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>


                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- =================================================
             VISTA PREVIA
        ================================================== -->

        <div class="card border-0 shadow-sm mt-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-display text-primary"></i>

                    Vista previa

                </h5>

            </div>


            <div class="card-body">

                <div
                    id="vistaPrevia"
                    class="preview-contenido"
                >

                    <?php if (
                        trim($contenido) !== ""
                    ): ?>

                        <?= $contenido ?>

                    <?php else: ?>

                        <div
                            class="text-center text-muted py-5"
                        >

                            <i
                                class="bi bi-file-earmark-text fs-1 d-block mb-3"
                            ></i>

                            <p class="mb-0">

                                La vista previa aparecerá
                                cuando escribas contenido.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    <?php endif; ?>


</div>



<!-- =========================================================
     JQUERY LOCAL
========================================================= -->

<script
    src="../../assets/js/jquery-3.7.1.min.js"
></script>


<!-- =========================================================
     SUMMERNOTE LOCAL
========================================================= -->

<script
    src="../../assets/summernote/summernote-lite.min.js"
></script>


<script>

$(document).ready(function () {


    /*
    |----------------------------------------------------------
    | INICIALIZAR SUMMERNOTE
    |----------------------------------------------------------
    */

    $('#editorContenido').summernote({

        height: 520,

        minHeight: 400,

        maxHeight: null,

        lang: 'es-ES',

        placeholder:
            'Comienza a construir la lección...',

        dialogsInBody: true,

        toolbar: [

            [
                'style',
                [
                    'style'
                ]
            ],

            [
                'font',
                [
                    'bold',
                    'italic',
                    'underline',
                    'strikethrough',
                    'clear'
                ]
            ],

            [
                'fontname',
                [
                    'fontname'
                ]
            ],

            [
                'fontsize',
                [
                    'fontsize'
                ]
            ],

            [
                'color',
                [
                    'color'
                ]
            ],

            [
                'para',
                [
                    'ul',
                    'ol',
                    'paragraph'
                ]
            ],

            [
                'table',
                [
                    'table'
                ]
            ],

            [
                'insert',
                [
                    'link',
                    'picture',
                    'video',
                    'hr'
                ]
            ],

            [
                'view',
                [
                    'fullscreen',
                    'codeview',
                    'help'
                ]
            ]

        ],


        callbacks: {

            onChange:
                function(contents) {

                    actualizarVistaPrevia(
                        contents
                    );

                }

        }

    });



    /*
    |----------------------------------------------------------
    | VISTA PREVIA
    |----------------------------------------------------------
    */

    function actualizarVistaPrevia(
        contenido
    ) {

        const contenedor =
            $('#vistaPrevia');


        if (
            !contenido ||
            contenido.trim() === ''
        ) {

            contenedor.html(
                '<div class="text-center text-muted py-5">' +
                '<i class="bi bi-file-earmark-text fs-1 d-block mb-3"></i>' +
                '<p class="mb-0">' +
                'La vista previa aparecerá cuando escribas contenido.' +
                '</p>' +
                '</div>'
            );

            return;

        }


        contenedor.html(
            contenido
        );

    }



    /*
    |----------------------------------------------------------
    | LIMPIAR
    |----------------------------------------------------------
    */

    $('#btnLimpiar').on(
        'click',
        function () {

            const confirmar =
                confirm(
                    '¿Seguro que deseas limpiar todo el contenido del editor? Esta acción no se guardará hasta que pulses un botón de guardado.'
                );


            if (
                confirmar
            ) {

                $('#editorContenido')
                    .summernote(
                        'code',
                        ''
                    );

                actualizarVistaPrevia(
                    ''
                );

            }

        }
    );



    /*
    |----------------------------------------------------------
    | CAMBIO DE ESTADO
    |----------------------------------------------------------
    */

    $('#estado').on(
        'change',
        function () {

            const estado =
                $(this).val();

            const badge =
                $('#estadoActual');


            badge.removeClass(
                'text-bg-success text-bg-warning'
            );


            if (
                estado === 'Publicado'
            ) {

                badge
                    .addClass(
                        'text-bg-success'
                    )
                    .text(
                        'Publicado'
                    );

            } else {

                badge
                    .addClass(
                        'text-bg-warning'
                    )
                    .text(
                        'Borrador'
                    );

            }

        }
    );



    /*
    |----------------------------------------------------------
    | CONFIRMAR ENVÍO
    |----------------------------------------------------------
    */

    $('#formContenido').on(
        'submit',
        function () {

            const contenido =
                $('#editorContenido')
                    .summernote(
                        'code'
                    )
                    .trim();


            if (
                contenido === ''
            ) {

                alert(
                    'Debes escribir contenido antes de guardar.'
                );

                return false;

            }

            return true;

        }
    );


});

</script>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
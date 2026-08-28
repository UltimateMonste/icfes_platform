<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$mensajes = [];
$tema = null;
$contenidoEditor = '';
$estadoContenido = null;
$idTema = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idTema || $idTema <= 0) {
    header('Location: ' . urlAplicacion('/admin/dashboard.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| LIMPIAR HTML DEL EDITOR
|--------------------------------------------------------------------------
|
| El administrador controla el contenido, pero aun así eliminamos scripts,
| eventos JavaScript, iframes externos no permitidos y data: URLs.
|
*/
function limpiarContenidoHTML($html)
{
    if (trim($html) === '') {
        return '';
    }

    // Eliminar etiquetas peligrosas.
    $html = preg_replace(
        [
            '#<script\b[^>]*>.*?</script>#is',
            '#<style\b[^>]*>.*?</style>#is',
            '#<object\b[^>]*>.*?</object>#is',
            '#<embed\b[^>]*>.*?</embed>#is',
            '#<applet\b[^>]*>.*?</applet>#is',
            '#<form\b[^>]*>.*?</form>#is'
        ],
        '',
        $html
    );

    // Eliminar eventos JavaScript: onclick, onload, onerror, etc.
    $html = preg_replace(
        '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        '',
        $html
    );

    // Eliminar javascript: en atributos.
    $html = preg_replace(
        '/(href|src|action)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i',
        '',
        $html
    );

    // Evitar que alguien pegue una imagen Base64 enorme desde codeview.
    $html = preg_replace(
        '/\s+(src|href)\s*=\s*([\'"])data:[^\'"]*\2/i',
        '',
        $html
    );

    // Permitir únicamente iframes educativos de YouTube/Vimeo.
    if (stripos($html, '<iframe') !== false) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $cargado = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($cargado) {
            $iframes = $dom->getElementsByTagName('iframe');
            $eliminar = [];

            foreach ($iframes as $iframe) {
                $src = trim($iframe->getAttribute('src'));

                $permitido = preg_match(
                    '#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#i',
                    $src
                );

                if (!$permitido) {
                    $eliminar[] = $iframe;
                } else {
                    // Atributos recomendados para los vídeos incrustados.
                    $iframe->setAttribute('loading', 'lazy');
                    $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                    $iframe->setAttribute('allowfullscreen', 'allowfullscreen');
                }
            }

            foreach ($eliminar as $iframe) {
                if ($iframe->parentNode) {
                    $iframe->parentNode->removeChild($iframe);
                }
            }

            $html = $dom->saveHTML();
        }

        libxml_clear_errors();
    }

    return trim($html);
}


try {

    /*
    |--------------------------------------------------------------------------
    | OBTENER TEMA
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
        WHERE t.id_tema = ?
        LIMIT 1
    ";

    $stmtTema = $conexion->prepare($sqlTema);
    $stmtTema->execute([$idTema]);
    $tema = $stmtTema->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        header('Location: ' . urlAplicacion('/admin/dashboard.php'));
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER CONTENIDO
    |--------------------------------------------------------------------------
    |
    | Si existe borrador, se carga primero.
    | Si no existe, se carga la publicación.
    | Si no existe ningún registro, se utiliza el contenido histórico
    | almacenado en temas.contenido.
    |
    */

    $sqlContenido = "
        SELECT
            id_contenido,
            contenido,
            estado,
            fecha_creacion,
            fecha_actualizacion
        FROM contenido_temas
        WHERE id_tema = ?
        ORDER BY
            CASE
                WHEN estado = 'Borrador' THEN 0
                ELSE 1
            END,
            fecha_actualizacion DESC,
            id_contenido DESC
        LIMIT 1
    ";

    $stmtContenido = $conexion->prepare($sqlContenido);
    $stmtContenido->execute([$idTema]);
    $contenidoGuardado = $stmtContenido->fetch(PDO::FETCH_ASSOC);

    if ($contenidoGuardado) {
        $contenidoEditor = $contenidoGuardado['contenido'] ?? '';
        $estadoContenido = $contenidoGuardado['estado'] ?? null;
    } else {
        $contenidoEditor = $tema['contenido'] ?? '';
        $estadoContenido =
            trim($contenidoEditor) !== ''
                ? 'Publicado'
                : null;
    }


    /*
    |--------------------------------------------------------------------------
    | PROCESAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $accion = $_POST['accion'] ?? '';

        // La acción siempre debe venir explícitamente del botón pulsado.
        // Si por alguna razón no llega, no publicamos accidentalmente.
        if (!in_array($accion, ['borrador', 'publicar'], true)) {
            $accion = 'borrador';
        }

        $contenido = limpiarContenidoHTML(
            $_POST['contenido'] ?? ''
        );


        if ($contenido === '') {

            $errores[] =
                'El contenido está vacío. Escribe contenido antes de guardarlo.';

        } else {

            try {

                $conexion->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | GUARDAR BORRADOR
                |--------------------------------------------------------------------------
                */

                if ($accion === 'borrador') {

                    $stmtBuscar =
                        $conexion->prepare(
                            "
                            SELECT id_contenido
                            FROM contenido_temas
                            WHERE id_tema = ?
                              AND estado = 'Borrador'
                            ORDER BY id_contenido DESC
                            LIMIT 1
                            "
                        );

                    $stmtBuscar->execute([
                        $idTema
                    ]);

                    $idBorrador =
                        $stmtBuscar->fetchColumn();


                    if ($idBorrador) {

                        $stmtGuardar =
                            $conexion->prepare(
                                "
                                UPDATE contenido_temas
                                SET
                                    contenido = ?,
                                    fecha_actualizacion = CURRENT_TIMESTAMP
                                WHERE id_contenido = ?
                                "
                            );

                        $stmtGuardar->execute([
                            $contenido,
                            $idBorrador
                        ]);

                    } else {

                        $stmtGuardar =
                            $conexion->prepare(
                                "
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
                                        'Borrador'
                                    )
                                "
                            );

                        $stmtGuardar->execute([
                            $idTema,
                            $contenido
                        ]);

                    }


                    $conexion->commit();


                    $mensajes[] =
                        'El borrador se guardó correctamente. Todavía no es visible para los estudiantes.';

                    $estadoContenido =
                        'Borrador';

                    $contenidoEditor =
                        $contenido;

                }


                /*
                |--------------------------------------------------------------------------
                | PUBLICAR
                |--------------------------------------------------------------------------
                */

                else {

                    $stmtBuscar =
                        $conexion->prepare(
                            "
                            SELECT id_contenido
                            FROM contenido_temas
                            WHERE id_tema = ?
                              AND estado = 'Publicado'
                            ORDER BY id_contenido DESC
                            LIMIT 1
                            "
                        );

                    $stmtBuscar->execute([
                        $idTema
                    ]);

                    $idPublicado =
                        $stmtBuscar->fetchColumn();


                    if ($idPublicado) {

                        $stmtPublicar =
                            $conexion->prepare(
                                "
                                UPDATE contenido_temas
                                SET
                                    contenido = ?,
                                    fecha_actualizacion = CURRENT_TIMESTAMP
                                WHERE id_contenido = ?
                                "
                            );

                        $stmtPublicar->execute([
                            $contenido,
                            $idPublicado
                        ]);

                    } else {

                        $stmtPublicar =
                            $conexion->prepare(
                                "
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
                                        'Publicado'
                                    )
                                "
                            );

                        $stmtPublicar->execute([
                            $idTema,
                            $contenido
                        ]);

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SINCRONIZAR CAMPO HISTÓRICO
                    |--------------------------------------------------------------------------
                    */

                    $stmtTemaContenido =
                        $conexion->prepare(
                            "
                            UPDATE temas
                            SET contenido = ?
                            WHERE id_tema = ?
                            "
                        );

                    $stmtTemaContenido->execute([
                        $contenido,
                        $idTema
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | ELIMINAR BORRADOR
                    |--------------------------------------------------------------------------
                    |
                    | Una vez publicada la versión preparada, el borrador deja
                    | de ser necesario porque la nueva publicación se convierte
                    | en la versión oficial.
                    |
                    */

                    $stmtEliminarBorrador =
                        $conexion->prepare(
                            "
                            DELETE FROM contenido_temas
                            WHERE id_tema = ?
                              AND estado = 'Borrador'
                            "
                        );

                    $stmtEliminarBorrador->execute([
                        $idTema
                    ]);


                    $conexion->commit();


                    $mensajes[] =
                        'El contenido se publicó correctamente y ya está disponible para los estudiantes.';

                    $estadoContenido =
                        'Publicado';

                    $contenidoEditor =
                        $contenido;

                }

            } catch (PDOException $e) {

                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                $errores[] =
                    'No fue posible guardar el contenido. Verifica la estructura de la tabla contenido_temas y que el servidor permita la operación.';

            }

        }

    }

} catch (PDOException $e) {

    $errores[] =
        'No fue posible cargar el tema.';

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
        Editar tema | Studia360
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


    <!-- Summernote -->

    <link
        rel="stylesheet"
        href="../../assets/summernote/summernote-lite.min.css"
    >


    <style>

        body {
            background: #f5f7fb;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .editor-card {
            border: 0;
            border-radius: 16px;
            box-shadow:
                0 0.35rem 1rem
                rgba(0, 0, 0, 0.08);
        }

        .editor-container {
            background: white;
            border-radius: 12px;
        }

        .note-editor {
            border-radius: 10px !important;
            border:
                1px solid #dee2e6 !important;
            overflow: hidden;
        }

        .note-toolbar {
            background: #f8f9fa !important;
            border-bottom:
                1px solid #dee2e6 !important;
        }

        .note-editable {
            min-height: 650px;
            font-size: 16px;
            line-height: 1.7;
            padding: 35px !important;
            background: white;
        }

        .note-statusbar {
            background: #f8f9fa;
        }

        .info-box,
        .important-box,
        .example-box,
        .exercise-box,
        .remember-box {
            padding: 16px 18px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .info-box {
            border-left: 5px solid #0d6efd;
            background: #e9f2ff;
        }

        .important-box {
            border-left: 5px solid #dc3545;
            background: #fff0f1;
        }

        .example-box {
            border-left: 5px solid #198754;
            background: #eaf7ef;
        }

        .exercise-box {
            border-left: 5px solid #ffc107;
            background: #fff9e6;
        }

        .remember-box {
            border-left: 5px solid #6f42c1;
            background: #f4efff;
        }

        .bloque-label {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .acciones-editor {
            position: sticky;
            bottom: 0;
            z-index: 20;
            background: white;
            padding: 15px;
            border-top:
                1px solid #dee2e6;
        }

        .estado-editor {
            min-height: 34px;
        }

        .upload-status {
            display: none;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #e9f2ff;
            color: #084298;
            font-size: 0.9rem;
        }

        .upload-status.show {
            display: flex;
        }

        .upload-status.error {
            background: #fff0f1;
            color: #842029;
        }

        .upload-status.success {
            background: #eaf7ef;
            color: #146c43;
        }

        .note-editable img {
            max-width: 100%;
            height: auto;
        }

        @media (max-width: 767px) {

            .note-editable {
                padding: 20px !important;
                min-height: 500px;
            }

            .acciones-editor {
                position: static;
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
            href="<?= htmlspecialchars(
                urlAplicacion('/admin/dashboard.php'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="navbar-brand"
        >

            <i class="bi bi-mortarboard-fill"></i>

            Studia360

        </a>


        <div class="d-flex align-items-center gap-2">

            <span class="text-white d-none d-md-inline">

                <i class="bi bi-shield-check"></i>

                Administrador

            </span>


            <a
                href="<?= htmlspecialchars(
                    urlAplicacion('/cerrar_sesion.php'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
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


    <!-- ENCABEZADO -->

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4"
    >

        <div>

            <div class="text-primary fw-semibold small">

                ADMINISTRACIÓN DE CONTENIDOS

            </div>


            <h2 class="mb-1">

                <?= htmlspecialchars(
                    $tema["nombre"] ?? "Tema",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </h2>


            <div class="text-muted">

                <?= htmlspecialchars(
                    $tema["materia"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                ·

                <?= htmlspecialchars(
                    $tema["grado"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>°

            </div>

        </div>


        <div class="mt-3 mt-md-0 d-flex gap-2">

            <a
                href="<?= htmlspecialchars(
                    urlAplicacion(
                        '/estudiante/tema.php?id=' . (int)$idTema
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                target="_blank"
                rel="noopener"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-eye"></i>

                Ver tema

            </a>


            <a
                href="<?= htmlspecialchars(
                    urlAplicacion('/admin/dashboard.php'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Volver

            </a>

        </div>

    </div>



    <!-- MENSAJES -->

    <?php foreach ($mensajes as $mensaje): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?= htmlspecialchars(
                $mensaje,
                ENT_QUOTES,
                "UTF-8"
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endforeach; ?>


    <?php foreach ($errores as $error): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endforeach; ?>



    <!-- =====================================================
         INFORMACIÓN
    ====================================================== -->

    <div class="alert alert-info border-0 shadow-sm">

        <i class="bi bi-info-circle-fill"></i>

        <strong>
            Editor de contenido de Studia360
        </strong>

        <br>

        Construye el contenido del tema utilizando texto,
        imágenes, tablas, enlaces, vídeos y bloques educativos.

        <div class="small mt-2">

            <i class="bi bi-image"></i>

            Las imágenes se almacenan como archivos en el servidor
            y no como Base64 dentro de la base de datos.

        </div>

    </div>



    <!-- =====================================================
         ESTADO
    ====================================================== -->

    <?php if ($estadoContenido): ?>

        <div class="mb-3 estado-editor">

            <?php if ($estadoContenido === 'Borrador'): ?>

                <span class="badge text-bg-warning text-dark px-3 py-2">

                    <i class="bi bi-pencil-square"></i>

                    Borrador guardado

                </span>

                <span class="text-muted small ms-2">

                    No visible para estudiantes hasta publicar.

                </span>

            <?php else: ?>

                <span class="badge text-bg-success px-3 py-2">

                    <i class="bi bi-check-circle"></i>

                    Contenido publicado

                </span>

            <?php endif; ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         EDITOR
    ====================================================== -->

    <div class="card editor-card">

        <div class="card-body p-3 p-md-4">

            <form
                method="POST"
                id="formContenido"
                autocomplete="off"
            >

                <input type="hidden" name="accion" id="accion" value="">

                <div class="editor-container">

                    <textarea
                        id="contenido"
                        name="contenido"
                    ><?= htmlspecialchars(
                        $contenidoEditor,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?></textarea>

                </div>


                <div
                    id="uploadStatus"
                    class="upload-status"
                    role="status"
                    aria-live="polite"
                >

                    <i class="bi bi-cloud-arrow-up"></i>

                    <span id="uploadStatusText">
                        Subiendo imagen...
                    </span>

                </div>


                <div
                    class="acciones-editor mt-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
                >

                    <div class="text-muted small">

                        <i class="bi bi-shield-check"></i>

                        Contenido administrado por Studia360.

                    </div>


                    <div class="d-flex gap-2 flex-wrap">

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="btnLimpiar"
                        >

                            <i class="bi bi-eraser"></i>

                            Limpiar

                        </button>


                        <button
                            type="button"
                            class="btn btn-outline-secondary px-4"
                            id="btnBorrador"
                        >

                            <i class="bi bi-file-earmark-text"></i>

                            Guardar borrador

                        </button>


                        <button
                            type="button"
                            class="btn btn-primary px-4"
                            id="btnPublicar"
                        >

                            <i class="bi bi-cloud-arrow-up"></i>

                            Publicar contenido

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


</div>



<!-- =========================================================
     jQuery LOCAL
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


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>



<script>

$(document).ready(function () {


    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    const URL_SUBIDA_IMAGEN =
        "<?= htmlspecialchars(
            urlAplicacion(
                '/admin/contenidos/upload_imagen.php'
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>";


    /*
    |--------------------------------------------------------------------------
    | ELEMENTOS
    |--------------------------------------------------------------------------
    */

    const $editor =
        $("#contenido");

    const $form =
        $("#formContenido");

    const $uploadStatus =
        $("#uploadStatus");

    const $uploadStatusText =
        $("#uploadStatusText");

    const $btnBorrador =
        $("#btnBorrador");

    const $btnPublicar =
        $("#btnPublicar");


    /*
    |--------------------------------------------------------------------------
    | ESTADO DE SUBIDAS
    |--------------------------------------------------------------------------
    */

    let imagenesSubiendo = 0;


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR ESTADO DE SUBIDA
    |--------------------------------------------------------------------------
    */

    function mostrarEstadoSubida(
        mensaje,
        tipo
    ) {

        $uploadStatus
            .removeClass(
                "error success"
            )
            .addClass("show");

        if (tipo) {
            $uploadStatus.addClass(tipo);
        }

        $uploadStatusText.text(
            mensaje
        );

    }


    function ocultarEstadoSubida() {

        if (
            imagenesSubiendo <= 0
        ) {

            $uploadStatus
                .removeClass(
                    "show error success"
                );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREAR BLOQUE EDUCATIVO
    |--------------------------------------------------------------------------
    */

    function crearBloque(
        clase,
        titulo,
        icono,
        texto
    ) {

        return `
            <div class="${clase}">
                <div class="bloque-label">
                    ${icono} ${titulo}
                </div>
                <p>
                    ${texto}
                </p>
            </div>
            <p><br></p>
        `;

    }


    /*
    |--------------------------------------------------------------------------
    | INICIALIZAR SUMMERNOTE
    |--------------------------------------------------------------------------
    */

    $editor.summernote({

        lang: "es-ES",

        height: 700,

        minHeight: 500,

        maxHeight: null,

        focus: false,

        placeholder:
            "Comienza a construir la lección...",

        tabsize: 2,


        /*
        |--------------------------------------------------------------------------
        | TOOLBAR
        |--------------------------------------------------------------------------
        */

        toolbar: [

            [
                "style",
                [
                    "style"
                ]
            ],

            [
                "font",
                [
                    "bold",
                    "italic",
                    "underline",
                    "strikethrough",
                    "clear"
                ]
            ],

            [
                "fontname",
                [
                    "fontname"
                ]
            ],

            [
                "fontsize",
                [
                    "fontsize"
                ]
            ],

            [
                "color",
                [
                    "color"
                ]
            ],

            [
                "para",
                [
                    "ul",
                    "ol",
                    "paragraph"
                ]
            ],

            [
                "height",
                [
                    "height"
                ]
            ],

            [
                "table",
                [
                    "table"
                ]
            ],

            [
                "insert",
                [
                    "link",
                    "picture",
                    "video",
                    "hr"
                ]
            ],

            [
                "view",
                [
                    "fullscreen",
                    "codeview",
                    "help"
                ]
            ],

            [
                "misc",
                [
                    "undo",
                    "redo"
                ]
            ]

        ],


        /*
        |--------------------------------------------------------------------------
        | FORMATOS
        |--------------------------------------------------------------------------
        */

        styleTags: [
            "p",
            "blockquote",
            "pre",
            "h1",
            "h2",
            "h3",
            "h4",
            "h5",
            "h6"
        ],


        /*
        |--------------------------------------------------------------------------
        | FUENTES
        |--------------------------------------------------------------------------
        */

        fontNames: [
            "Arial",
            "Arial Black",
            "Comic Sans MS",
            "Courier New",
            "Georgia",
            "Helvetica",
            "Impact",
            "Tahoma",
            "Times New Roman",
            "Trebuchet MS",
            "Verdana"
        ],


        /*
        |--------------------------------------------------------------------------
        | TAMAÑOS
        |--------------------------------------------------------------------------
        */

        fontSizes: [
            "8",
            "10",
            "12",
            "14",
            "16",
            "18",
            "20",
            "24",
            "28",
            "32",
            "36",
            "48"
        ],


        /*
        |--------------------------------------------------------------------------
        | IMÁGENES
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Ya NO usamos FileReader/readAsDataURL.
        |
        | La imagen se envía mediante AJAX al servidor.
        |
        */

        callbacks: {

            onImageUpload: function (
                files
            ) {

                if (!files || !files.length) {
                    return;
                }

                for (
                    let i = 0;
                    i < files.length;
                    i++
                ) {

                    subirImagen(
                        files[i]
                    );

                }

            },


            onPaste: function () {

                /*
                | Summernote procesa normalmente las imágenes
                | pegadas. No convertimos contenido a Base64
                | manualmente aquí.
                */

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SUBIR IMAGEN AL SERVIDOR
    |--------------------------------------------------------------------------
    */

    function subirImagen(
        archivo
    ) {

        if (
            !archivo ||
            !archivo.type ||
            archivo.type.indexOf(
                "image/"
            ) !== 0
        ) {

            mostrarEstadoSubida(
                "El archivo seleccionado no es una imagen.",
                "error"
            );

            return;

        }


        const tiposPermitidos = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp"
        ];


        if (
            !tiposPermitidos.includes(
                archivo.type
            )
        ) {

            mostrarEstadoSubida(
                "Formato no permitido. Usa JPG, PNG, GIF o WEBP.",
                "error"
            );

            return;

        }


        const maximo =
            8 * 1024 * 1024;


        if (
            archivo.size > maximo
        ) {

            mostrarEstadoSubida(
                "La imagen supera el límite de 8 MB.",
                "error"
            );

            return;

        }


        const datos =
            new FormData();

        datos.append(
            "file",
            archivo
        );


        imagenesSubiendo++;


        mostrarEstadoSubida(
            "Subiendo imagen...",
            null
        );


        $.ajax({

            url:
                URL_SUBIDA_IMAGEN,

            type:
                "POST",

            data:
                datos,

            processData:
                false,

            contentType:
                false,

            dataType:
                "json",


            success: function (
                respuesta
            ) {

                if (
                    respuesta &&
                    respuesta.success &&
                    respuesta.url
                ) {

                    $editor.summernote(
                        "insertImage",
                        respuesta.url,
                        function (
                            $image
                        ) {

                            $image.attr(
                                "alt",
                                archivo.name
                            );

                            $image.css({
                                "max-width":
                                    "100%",
                                "height":
                                    "auto"
                            });

                        }
                    );


                    mostrarEstadoSubida(
                        "Imagen subida correctamente.",
                        "success"
                    );

                } else {

                    mostrarEstadoSubida(
                        respuesta.message ||
                        "No fue posible subir la imagen.",
                        "error"
                    );

                }

            },


            error: function (
                xhr
            ) {

                let mensaje =
                    "No fue posible subir la imagen.";

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {

                    mensaje =
                        xhr.responseJSON.message;

                }

                mostrarEstadoSubida(
                    mensaje,
                    "error"
                );

            },


            complete: function () {

                imagenesSubiendo--;


                setTimeout(
                    function () {

                        ocultarEstadoSubida();

                    },
                    2500
                );

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | BOTONES DE BLOQUES EDUCATIVOS
    |--------------------------------------------------------------------------
    */

    const toolbar =
        $(".note-toolbar");


    function agregarBotonBloque(
        texto,
        clase,
        titulo,
        icono,
        textoInicial
    ) {

        const boton =
            $(`
                <button
                    type="button"
                    class="btn btn-light btn-sm"
                    title="${titulo}"
                >
                    ${icono}
                    ${texto}
                </button>
            `);


        boton.on(
            "click",
            function () {

                $editor.summernote(
                    "pasteHTML",
                    crearBloque(
                        clase,
                        titulo,
                        icono,
                        textoInicial
                    )
                );

            }
        );


        return boton;

    }


    const grupoBloques =
        $("<div>")
            .addClass(
                "btn-group ms-1 flex-wrap"
            );


    grupoBloques.append(

        agregarBotonBloque(
            "Concepto",
            "info-box",
            "CONCEPTO CLAVE",
            "💡",
            "Escribe aquí el concepto principal."
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Importante",
            "important-box",
            "IMPORTANTE",
            "⚠️",
            "Escribe aquí una información importante."
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Ejemplo",
            "example-box",
            "EJEMPLO",
            "🔎",
            "Escribe aquí un ejemplo."
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Ejercicio",
            "exercise-box",
            "EJERCICIO",
            "📝",
            "Escribe aquí el ejercicio o actividad."
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Recuerda",
            "remember-box",
            "RECUERDA",
            "📌",
            "Escribe aquí aquello que el estudiante debe recordar."
        )

    );


    toolbar.append(
        grupoBloques
    );


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR
    |--------------------------------------------------------------------------
    */

    $("#btnLimpiar").on(
        "click",
        function () {

            const confirmar =
                confirm(
                    "¿Seguro que quieres eliminar todo el contenido de este tema? Esta acción no guarda los cambios."
                );


            if (!confirmar) {
                return;
            }


            $editor.summernote(
                "code",
                ""
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ENVÍO DEL FORMULARIO
    |--------------------------------------------------------------------------
    */

    const $accion = $("#accion");
    let enviando = false;

    function enviarFormulario(accion) {

        if (enviando) {
            return;
        }

        if (imagenesSubiendo > 0) {
            alert(
                "Espera a que termine la subida de las imágenes antes de guardar el contenido."
            );
            return;
        }

        const contenido = $editor.summernote("code").trim();

        if (contenido === "" || contenido === "<p><br></p>") {
            const confirmar = confirm(
                "El contenido está vacío. ¿Quieres continuar?"
            );
            if (!confirmar) {
                return;
            }
        }

        // El valor se establece explícitamente según el botón pulsado.
        $accion.val(accion);

        // Sincronizamos el contenido justo antes de enviar.
        $("#contenido").val($editor.summernote("code"));

        enviando = true;
        $btnBorrador.prop("disabled", true);
        $btnPublicar.prop("disabled", true);

        // Indicador visual inmediato para evitar dudas y doble clic.
        if (accion === "publicar") {
            $btnPublicar.html('<span class="spinner-border spinner-border-sm me-1"></span> Publicando...');
        } else {
            $btnBorrador.html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');
        }

        $form.off("submit.studia");
        $form.trigger("submit");
    }

    $btnBorrador.on("click", function () {
        enviarFormulario("borrador");
    });

    $btnPublicar.on("click", function () {
        enviarFormulario("publicar");
    });

    // Seguridad adicional: si otro script intenta enviar el formulario,
    // exigimos que exista una acción explícita.
    $form.on("submit.studia", function (evento) {
        if (!$accion.val()) {
            evento.preventDefault();
            return false;
        }
    });

});

</script>


</body>

</html>

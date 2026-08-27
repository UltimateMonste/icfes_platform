<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$errores = [];

$mensajes = [];

$tema = null;

$idTema = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| VALIDAR ID
|--------------------------------------------------------------------------
*/

if (!$idTema || $idTema <= 0) {

    header("Location: ../dashboard.php");

    exit;

}


/*
|--------------------------------------------------------------------------
| PROCESAR FORMULARIO
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $contenido =
        $_POST["contenido"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | LIMPIEZA BÁSICA DE CONTENIDO
    |--------------------------------------------------------------------------
    |
    | El administrador puede utilizar HTML enriquecido.
    | Eliminamos únicamente elementos peligrosos.
    |
    */

    $contenido =
        limpiarContenidoHTML($contenido);


    try {

        $sqlUpdate = "
            UPDATE temas
            SET contenido = ?
            WHERE id_tema = ?
        ";


        $stmtUpdate =
            $conexion->prepare($sqlUpdate);


        $stmtUpdate->execute([
            $contenido,
            $idTema
        ]);


        $mensajes[] =
            "El contenido del tema se guardó correctamente.";


    } catch (PDOException $e) {

        $errores[] =
            "No fue posible guardar el contenido del tema.";

    }

}


/*
|--------------------------------------------------------------------------
| OBTENER TEMA
|--------------------------------------------------------------------------
*/

try {

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


    $stmtTema =
        $conexion->prepare($sqlTema);


    $stmtTema->execute([
        $idTema
    ]);


    $tema =
        $stmtTema->fetch(PDO::FETCH_ASSOC);


    if (!$tema) {

        header("Location: ../dashboard.php");

        exit;

    }


} catch (PDOException $e) {

    $errores[] =
        "No fue posible cargar el tema.";

}


/*
|--------------------------------------------------------------------------
| FUNCIÓN DE LIMPIEZA HTML
|--------------------------------------------------------------------------
*/

function limpiarContenidoHTML($html)
{

    if (
        trim($html) === ""
    ) {

        return "";

    }


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR ELEMENTOS PELIGROSOS
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        [
            "#<script\b[^>]*>.*?</script>#is",
            "#<object\b[^>]*>.*?</object>#is",
            "#<embed\b[^>]*>.*?</embed>#is",
            "#<applet\b[^>]*>.*?</applet>#is"
        ],
        "",
        $html
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR EVENTOS JAVASCRIPT
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        '',
        $html
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR JAVASCRIPT EN URL
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        '/(href|src|action)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i',
        '',
        $html
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR IFRAME NO SEGURO
    |--------------------------------------------------------------------------
    |
    | Permitimos principalmente YouTube y Vimeo.
    |
    */

    if (
        stripos($html, "<iframe") !== false
    ) {

        $dom =
            new DOMDocument(
                "1.0",
                "UTF-8"
            );


        libxml_use_internal_errors(true);


        $dom->loadHTML(
            '<?xml encoding="UTF-8">' .
            $html,
            LIBXML_HTML_NOIMPLIED |
            LIBXML_HTML_NODEFDTD
        );


        $iframes =
            $dom->getElementsByTagName(
                "iframe"
            );


        $eliminar = [];


        foreach (
            $iframes as $iframe
        ) {

            $src =
                $iframe->getAttribute(
                    "src"
                );


            $permitido =
                preg_match(
                    '#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#i',
                    $src
                );


            if (!$permitido) {

                $eliminar[] =
                    $iframe;

            }

        }


        foreach (
            $eliminar as $iframe
        ) {

            $iframe->parentNode->removeChild(
                $iframe
            );

        }


        $html =
            $dom->saveHTML();


        libxml_clear_errors();

    }


    return trim($html);

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

        Editar tema |
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

        }


        .note-editable {

            min-height: 650px;

            font-size: 16px;

            line-height: 1.7;

            padding: 35px !important;

            background: white;

        }


        .note-toolbar {

            background: #f8f9fa !important;

            border-bottom:
                1px solid #dee2e6 !important;

        }


        .info-box {

            padding: 16px 18px;

            border-radius: 10px;

            margin: 20px 0;

            border-left: 5px solid #0d6efd;

            background: #e9f2ff;

        }


        .important-box {

            padding: 16px 18px;

            border-radius: 10px;

            margin: 20px 0;

            border-left: 5px solid #dc3545;

            background: #fff0f1;

        }


        .example-box {

            padding: 16px 18px;

            border-radius: 10px;

            margin: 20px 0;

            border-left: 5px solid #198754;

            background: #eaf7ef;

        }


        .exercise-box {

            padding: 16px 18px;

            border-radius: 10px;

            margin: 20px 0;

            border-left: 5px solid #ffc107;

            background: #fff9e6;

        }


        .remember-box {

            padding: 16px 18px;

            border-radius: 10px;

            margin: 20px 0;

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


        @media (
            max-width: 767px
        ) {

            .note-editable {

                padding: 20px !important;

                min-height: 500px;

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


        <div
            class="d-flex align-items-center gap-2"
        >

            <span
                class="text-white d-none d-md-inline"
            >

                <i class="bi bi-shield-check"></i>

                Administrador

            </span>


            <a
                href="../../cerrar_sesion.php"
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

            <div
                class="text-primary fw-semibold small"
            >

                ADMINISTRACIÓN DE CONTENIDOS

            </div>


            <h2 class="mb-1">

                <?= htmlspecialchars(
                    $tema["nombre"] ?? "Tema"
                ) ?>

            </h2>


            <div class="text-muted">

                <?= htmlspecialchars(
                    $tema["materia"] ?? ""
                ) ?>

                ·

                <?= htmlspecialchars(
                    $tema["grado"] ?? ""
                ) ?>°

            </div>

        </div>


        <div
            class="mt-3 mt-md-0"
        >

            <a
                href="../../tema.php?id=<?= (int)$idTema ?>"
                target="_blank"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-eye"></i>

                Ver tema

            </a>


            <a
                href="../dashboard.php"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Volver

            </a>

        </div>

    </div>



    <!-- MENSAJES -->

    <?php foreach (
        $mensajes
        as $mensaje
    ): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?= htmlspecialchars(
                $mensaje
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endforeach; ?>


    <?php foreach (
        $errores
        as $error
    ): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

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
         INFORMACIÓN
    ====================================================== -->

    <div
        class="alert alert-info border-0 shadow-sm"
    >

        <i class="bi bi-info-circle-fill"></i>

        <strong>
            Editor de contenido educativo
        </strong>

        <br>

        Desde aquí puedes construir la lección completa
        del tema utilizando texto, imágenes, tablas,
        enlaces, vídeos y bloques educativos.

    </div>



    <!-- =====================================================
         EDITOR
    ====================================================== -->

    <div class="card editor-card">

        <div class="card-body p-3 p-md-4">

            <form
                method="POST"
                id="formContenido"
            >

                <div class="editor-container">

                    <textarea
                        id="contenido"
                        name="contenido"
                    ><?= htmlspecialchars(
                        $tema["contenido"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?></textarea>

                </div>


                <div
                    class="acciones-editor mt-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
                >

                    <div
                        class="text-muted small"
                    >

                        <i class="bi bi-shield-check"></i>

                        Contenido administrado por ICFES Platform.

                    </div>


                    <div
                        class="d-flex gap-2"
                    >

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="btnLimpiar"
                        >

                            <i class="bi bi-eraser"></i>

                            Limpiar

                        </button>


                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >

                            <i class="bi bi-save"></i>

                            Guardar contenido

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



<script>

$(document).ready(function () {


    /*
    |--------------------------------------------------------------------------
    | BLOQUES EDUCATIVOS
    |--------------------------------------------------------------------------
    */


    function crearBloque(
        clase,
        titulo,
        icono
    ) {

        return `
            <div class="${clase}">
                <div class="bloque-label">
                    ${icono} ${titulo}
                </div>
                <p>
                    Escribe aquí el contenido...
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

    $("#contenido").summernote({

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
        | Summernote Lite puede incrustar imágenes directamente
        | en el HTML mediante Base64.
        |
        */

        callbacks: {

            onImageUpload: function (
                files
            ) {

                for (
                    let i = 0;
                    i < files.length;
                    i++
                ) {

                    let reader =
                        new FileReader();


                    reader.onloadend =
                        function () {

                            $("#contenido")
                                .summernote(
                                    "insertImage",
                                    reader.result
                                );

                        };


                    reader.readAsDataURL(
                        files[i]
                    );

                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | BOTONES DE BLOQUES
    |--------------------------------------------------------------------------
    */

    const toolbar =
        $(".note-toolbar");


    /*
    |--------------------------------------------------------------------------
    | CREAR BOTONES
    |--------------------------------------------------------------------------
    */

    function agregarBotonBloque(
        texto,
        clase,
        titulo,
        icono
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

                $("#contenido")
                    .summernote(
                        "pasteHTML",
                        crearBloque(
                            clase,
                            titulo,
                            icono
                        )
                    );

            }
        );


        return boton;

    }


    /*
    |--------------------------------------------------------------------------
    | GRUPO DE BLOQUES
    |--------------------------------------------------------------------------
    */

    const grupoBloques =
        $("<div>")
            .addClass(
                "btn-group ms-1"
            );


    grupoBloques.append(

        agregarBotonBloque(
            "Concepto",
            "info-box",
            "CONCEPTO CLAVE",
            "💡"
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Importante",
            "important-box",
            "IMPORTANTE",
            "⚠️"
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Ejemplo",
            "example-box",
            "EJEMPLO",
            "🔎"
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Ejercicio",
            "exercise-box",
            "EJERCICIO",
            "📝"
        )

    );


    grupoBloques.append(

        agregarBotonBloque(
            "Recuerda",
            "remember-box",
            "RECUERDA",
            "📌"
        )

    );


    toolbar.append(
        grupoBloques
    );


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR EDITOR
    |--------------------------------------------------------------------------
    */

    $("#btnLimpiar").on(
        "click",
        function () {

            const confirmar =
                confirm(
                    "¿Seguro que quieres eliminar todo el contenido de este tema?"
                );


            if (!confirmar) {

                return;

            }


            $("#contenido")
                .summernote(
                    "code",
                    ""
                );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | EVITAR ENVÍO VACÍO ACCIDENTAL
    |--------------------------------------------------------------------------
    */

    $("#formContenido").on(
        "submit",
        function () {

            const contenido =
                $("#contenido")
                    .summernote(
                        "code"
                    )
                    .trim();


            if (
                contenido === "" ||
                contenido === "<p><br></p>"
            ) {

                const confirmar =
                    confirm(
                        "El contenido está vacío. ¿Quieres guardar de todas formas?"
                    );


                if (!confirmar) {

                    return false;

                }

            }

        }
    );


});

</script>


</body>

</html>
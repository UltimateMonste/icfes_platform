<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


/*
|--------------------------------------------------------------------------
| SOLO POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    header(
        "Location: index.php"
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| DATOS
|--------------------------------------------------------------------------
*/

$idTema =
    filter_input(
        INPUT_POST,
        "id_tema",
        FILTER_VALIDATE_INT
    );


$descripcion =
    trim(
        $_POST["descripcion"] ?? ""
    );


$contenido =
    $_POST["contenido"] ?? "";


/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

$errores = [];


if (
    !$idTema ||
    $idTema <= 0
) {

    $errores[] =
        "El tema seleccionado no es válido.";

}


if (
    mb_strlen($descripcion) > 1000
) {

    $errores[] =
        "La descripción no puede superar los 1000 caracteres.";

}


/*
|--------------------------------------------------------------------------
| SANITIZAR HTML
|--------------------------------------------------------------------------
*/

function limpiarContenidoHTML(
    string $html
): string {


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR ELEMENTOS PELIGROSOS
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        "/<script\b[^>]*>(.*?)<\/script>/is",
        "",
        $html
    );


    $html = preg_replace(
        "/<iframe\b[^>]*>(.*?)<\/iframe>/is",
        "",
        $html
    );


    $html = preg_replace(
        "/<object\b[^>]*>(.*?)<\/object>/is",
        "",
        $html
    );


    $html = preg_replace(
        "/<embed\b[^>]*>/is",
        "",
        $html
    );


    $html = preg_replace(
        "/\son\w+\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i",
        "",
        $html
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR JAVASCRIPT EN URL
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        "/(href|src)\s*=\s*([\"'])\s*javascript:[^\"']*\2/i",
        "",
        $html
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR DATA URL PELIGROSA
    |--------------------------------------------------------------------------
    */

    $html = preg_replace(
        "/(href|src)\s*=\s*([\"'])\s*data:text\/html[^\"']*\2/i",
        "",
        $html
    );


    return trim($html);

}


$contenido =
    limpiarContenidoHTML(
        $contenido
    );


/*
|--------------------------------------------------------------------------
| SI HAY ERRORES
|--------------------------------------------------------------------------
*/

if (!empty($errores)) {

    $_SESSION["errores_contenido"] =
        $errores;


    header(
        "Location: editar_tema.php?id=" .
        (int)$idTema
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| GUARDAR
|--------------------------------------------------------------------------
*/

try {

    $sql = "

        UPDATE temas

        SET

            descripcion = ?,

            contenido = ?

        WHERE id_tema = ?

    ";


    $stmt =
        $conexion->prepare(
            $sql
        );


    $stmt->execute([

        $descripcion !== ""
            ? $descripcion
            : null,

        $contenido !== ""
            ? $contenido
            : null,

        $idTema

    ]);


    $_SESSION["mensaje_contenido"] =
        "El contenido del tema se guardó correctamente.";


    header(
        "Location: editar_tema.php?id=" .
        (int)$idTema
    );

    exit;


} catch (PDOException $e) {

    $_SESSION["errores_contenido"] = [

        "No fue posible guardar el contenido."

    ];


    header(
        "Location: editar_tema.php?id=" .
        (int)$idTema
    );

    exit;

}
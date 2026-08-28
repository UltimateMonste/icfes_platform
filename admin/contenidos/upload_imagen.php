<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


/*
|--------------------------------------------------------------------------
| RESPUESTA JSON
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| VALIDAR PETICIÓN
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| VALIDAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES["file"]) ||
    !is_array($_FILES["file"])
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "No se recibió ninguna imagen."
    ]);

    exit;

}


$archivo = $_FILES["file"];


/*
|--------------------------------------------------------------------------
| ERRORES DE SUBIDA
|--------------------------------------------------------------------------
*/

if (
    $archivo["error"] !== UPLOAD_ERR_OK
) {

    $mensaje = "No fue posible subir la imagen.";

    switch ($archivo["error"]) {

        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:

            $mensaje =
                "La imagen supera el tamaño permitido por PHP.";

            break;

        case UPLOAD_ERR_PARTIAL:

            $mensaje =
                "La imagen se subió parcialmente.";

            break;

        case UPLOAD_ERR_NO_FILE:

            $mensaje =
                "No se seleccionó ninguna imagen.";

            break;

    }


    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $mensaje
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| TAMAÑO MÁXIMO
|--------------------------------------------------------------------------
|
| 8 MB
|
*/

$maximoBytes =
    8 * 1024 * 1024;


if (
    $archivo["size"] > $maximoBytes
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "La imagen no puede superar los 8 MB."
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| VALIDAR MIME REAL
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mime =
    $finfo->file(
        $archivo["tmp_name"]
    );


$tiposPermitidos = [

    "image/jpeg" => "jpg",

    "image/png" => "png",

    "image/gif" => "gif",

    "image/webp" => "webp"

];


if (
    !isset(
        $tiposPermitidos[$mime]
    )
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP."
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| VERIFICAR QUE REALMENTE SEA UNA IMAGEN
|--------------------------------------------------------------------------
*/

$informacionImagen =
    @getimagesize(
        $archivo["tmp_name"]
    );


if (
    $informacionImagen === false
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "El archivo seleccionado no es una imagen válida."
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| CREAR DIRECTORIO
|--------------------------------------------------------------------------
*/

$directorio =
    __DIR__ .
    "/../../assets/uploads/contenidos";


if (
    !is_dir($directorio)
) {

    if (
        !mkdir(
            $directorio,
            0755,
            true
        )
    ) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "No fue posible crear el directorio de imágenes."
        ]);

        exit;

    }

}


/*
|--------------------------------------------------------------------------
| NOMBRE SEGURO
|--------------------------------------------------------------------------
*/

$extension =
    $tiposPermitidos[$mime];


$nombreArchivo =
    bin2hex(
        random_bytes(16)
    ) .
    "_" .
    time() .
    "." .
    $extension;


$rutaFisica =
    $directorio .
    "/" .
    $nombreArchivo;


/*
|--------------------------------------------------------------------------
| MOVER ARCHIVO
|--------------------------------------------------------------------------
*/

if (
    !move_uploaded_file(
        $archivo["tmp_name"],
        $rutaFisica
    )
) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "No fue posible guardar la imagen."
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| URL PÚBLICA
|--------------------------------------------------------------------------
*/

$url =
    urlAplicacion(
        "/assets/uploads/contenidos/" .
        $nombreArchivo
    );


/*
|--------------------------------------------------------------------------
| RESPUESTA
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "url" => $url,
    "filename" => $nombreArchivo
]);

exit;
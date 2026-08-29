<?php
/** Studia360 - subida de imágenes para Summernote */
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();
header('Content-Type: application/json; charset=UTF-8');

function respuestaImagen(bool $ok, string $message, ?string $url = null, int $code = 200): void
{
    http_response_code($code);
    $r = ['success' => $ok, 'message' => $message];
    if ($url !== null) $r['url'] = $url;
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaImagen(false, 'Método no permitido.', null, 405);
}

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_editor']) || !hash_equals($_SESSION['csrf_editor'], $csrf)) {
    respuestaImagen(false, 'La sesión de edición no es válida. Recarga la página e inténtalo de nuevo.', null, 403);
}

$directorio = __DIR__ . '/../../assets/uploads/contenidos';
if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
    respuestaImagen(false, 'No fue posible crear el directorio de imágenes.', null, 500);
}

$tipos = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

function guardarImagenBytes(string $bytes, string $extension, string $directorio): ?string
{
    $nombre = bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
    if (file_put_contents($ruta, $bytes, LOCK_EX) === false) return null;
    return urlAplicacion('/assets/uploads/contenidos/' . $nombre);
}

/* 1. Imagen subida desde el PC. */
if (isset($_FILES['file']) && is_array($_FILES['file'])) {
    $f = $_FILES['file'];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        respuestaImagen(false, 'No fue posible subir la imagen.', null, 400);
    }
    if (($f['size'] ?? 0) > 8 * 1024 * 1024) {
        respuestaImagen(false, 'La imagen no puede superar los 8 MB.', null, 400);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($tipos[$mime]) || @getimagesize($f['tmp_name']) === false) {
        respuestaImagen(false, 'Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP.', null, 400);
    }

    $bytes = file_get_contents($f['tmp_name']);
    if ($bytes === false) respuestaImagen(false, 'No fue posible leer la imagen.', null, 500);

    $url = guardarImagenBytes($bytes, $tipos[$mime], $directorio);
    if ($url === null) respuestaImagen(false, 'No fue posible guardar la imagen.', null, 500);
    respuestaImagen(true, 'Imagen guardada localmente.', $url);
}

/* 2. Imagen remota: se descarga y queda alojada en Studia360. */
$urlOrigen = trim((string)($_POST['url'] ?? ''));
if ($urlOrigen === '') {
    respuestaImagen(false, 'No se recibió ninguna imagen ni URL.', null, 400);
}

if (!filter_var($urlOrigen, FILTER_VALIDATE_URL)) {
    respuestaImagen(false, 'La URL de la imagen no es válida.', null, 400);
}

$partes = parse_url($urlOrigen);
$esquema = strtolower($partes['scheme'] ?? '');
$host = strtolower($partes['host'] ?? '');
if (!in_array($esquema, ['http', 'https'], true) || $host === '') {
    respuestaImagen(false, 'Solo se permiten URLs HTTP/HTTPS.', null, 400);
}

// Protección básica contra solicitudes a la propia máquina/red privada.
if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    respuestaImagen(false, 'No se permiten direcciones locales.', null, 400);
}
$ip = @gethostbyname($host);
if (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    respuestaImagen(false, 'No se permiten destinos de red privada.', null, 400);
}

$ch = curl_init($urlOrigen);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'Studia360/1.0',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$bytes = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($bytes === false || $http < 200 || $http >= 300) {
    respuestaImagen(false, 'No fue posible descargar la imagen desde esa URL.', null, 400);
}
if (strlen($bytes) > 8 * 1024 * 1024) {
    respuestaImagen(false, 'La imagen remota supera los 8 MB.', null, 400);
}

$tmp = tempnam(sys_get_temp_dir(), 'studia360_img_');
if ($tmp === false || file_put_contents($tmp, $bytes, LOCK_EX) === false) {
    if ($tmp) @unlink($tmp);
    respuestaImagen(false, 'No fue posible procesar la imagen remota.', null, 500);
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$info = @getimagesize($tmp);
@unlink($tmp);

if (!isset($tipos[$mime]) || $info === false) {
    respuestaImagen(false, 'La URL no apunta a una imagen JPG, PNG, GIF o WEBP válida.', null, 400);
}

$urlLocal = guardarImagenBytes($bytes, $tipos[$mime], $directorio);
if ($urlLocal === null) {
    respuestaImagen(false, 'No fue posible guardar la imagen remota.', null, 500);
}

respuestaImagen(true, 'Imagen descargada y guardada localmente.', $urlLocal);

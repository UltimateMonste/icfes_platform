<?php
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . urlAplicacion('/admin/contenidos/index.php')); exit;
}

$idTema = filter_input(INPUT_POST, 'id_tema', FILTER_VALIDATE_INT);
$csrf = (string)($_POST['csrf'] ?? '');
if (!$idTema || empty($_SESSION['csrf_recursos']) || !hash_equals($_SESSION['csrf_recursos'], $csrf)) {
    die('Solicitud no válida.');
}

$permitidos = ['video','articulo','blog','app','pdf','juego','simulador','presentacion'];
$titulo = trim((string)($_POST['titulo'] ?? ''));
$tipo = trim((string)($_POST['tipo'] ?? ''));
$estado = in_array($_POST['estado'] ?? '', ['Activo','Inactivo'], true) ? $_POST['estado'] : 'Activo';
$url = trim((string)($_POST['url'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$autor = trim((string)($_POST['autor'] ?? ''));
$fuente = trim((string)($_POST['fuente'] ?? ''));

if ($titulo === '' || mb_strlen($titulo) > 200 || !in_array($tipo, $permitidos, true)) die('Los datos del recurso no son válidos.');
if (mb_strlen($descripcion) > 1000 || mb_strlen($autor) > 150 || mb_strlen($fuente) > 150) die('Uno de los campos supera el tamaño permitido.');

$stmt = $conexion->prepare('SELECT id_tema FROM temas WHERE id_tema=? LIMIT 1');
$stmt->execute([$idTema]);
if (!$stmt->fetchColumn()) die('El tema seleccionado no existe.');

$directorio = __DIR__ . '/../../assets/uploads/recursos';
if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) die('No fue posible crear la carpeta de recursos.');

function guardarArchivoRecurso(string $tmp, string $extension, string $directorio): ?string {
    $nombre = bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
    return move_uploaded_file($tmp, $ruta) ? urlAplicacion('/assets/uploads/recursos/' . $nombre) : null;
}

/* PDF subido desde el equipo. */
if (isset($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['archivo'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 25 * 1024 * 1024) die('El PDF no es válido o supera los 25 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime !== 'application/pdf') die('El archivo seleccionado no es un PDF válido.');
    $url = guardarArchivoRecurso($f['tmp_name'], 'pdf', $directorio);
    if ($url === null) die('No fue posible guardar el PDF.');
    $tipo = 'pdf';
}

/* Miniatura opcional. */
$imagenUrl = null;
if (isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['imagen'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) die('La miniatura no es válida o supera los 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($map[$mime]) || @getimagesize($f['tmp_name']) === false) die('La miniatura debe ser JPG, PNG o WEBP válida.');
    $imagenUrl = guardarArchivoRecurso($f['tmp_name'], $map[$mime], $directorio);
    if ($imagenUrl === null) die('No fue posible guardar la miniatura.');
}

/* --------------------------------------------------------------------------
 * PDF remoto: se descarga obligatoriamente cuando el tipo es PDF.
 * -------------------------------------------------------------------------- */
if ($tipo === 'pdf' && $url !== '' && $imagenUrl !== null) {
    // No cambia nada: el PDF ya fue proporcionado por archivo.
}

if ($tipo === 'pdf' && $url !== '' && !str_contains($url, '/assets/uploads/recursos/')) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) die('La URL del PDF no es válida.');
    $p = parse_url($url); $scheme = strtolower($p['scheme'] ?? ''); $host = strtolower($p['host'] ?? '');
    if ($scheme !== 'https' || $host === '') die('Para descargar un PDF remoto debes usar una URL HTTPS válida.');
    if (in_array($host, ['localhost','127.0.0.1','::1'], true)) die('No se permiten direcciones locales.');
    $ip = @gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) die('No se permiten destinos de red privada.');

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'Studia360/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $data = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($data === false || $http < 200 || $http >= 300 || strlen($data) > 25 * 1024 * 1024) die('No fue posible descargar el PDF desde la URL indicada.');
    if (stripos($contentType, 'application/pdf') === false && !str_starts_with(ltrim($data), '%PDF')) die('La URL no apunta a un archivo PDF válido.');
    $nombre = bin2hex(random_bytes(16)) . '_' . time() . '.pdf';
    if (file_put_contents($directorio . DIRECTORY_SEPARATOR . $nombre, $data, LOCK_EX) === false) die('No fue posible guardar la copia local del PDF.');
    $url = urlAplicacion('/assets/uploads/recursos/' . $nombre);
}

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) die('Debes proporcionar una URL válida o subir un PDF.');

try {
    $stmt = $conexion->prepare('INSERT INTO recursos (id_tema,titulo,tipo,url,descripcion,imagen,autor,fuente,estado) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$idTema,$titulo,$tipo,$url,$descripcion !== '' ? $descripcion : null,$imagenUrl,$autor !== '' ? $autor : null,$fuente !== '' ? $fuente : null,$estado]);
    header('Location: ' . urlAplicacion('/admin/contenidos/recursos.php?id='.$idTema.'&ok=1')); exit;
} catch (PDOException $e) {
    die('No fue posible guardar el recurso en la base de datos.');
}

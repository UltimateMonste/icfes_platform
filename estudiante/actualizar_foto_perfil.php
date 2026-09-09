<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
exigirEstudiante();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    redireccionarLogin('Tu sesión no es válida.');
}

if (empty($_SESSION['csrf_foto_perfil'])) {
    $_SESSION['csrf_foto_perfil'] = bin2hex(random_bytes(32));
}

function volverPerfil(string $mensaje, string $tipo='success'): never {
    $_SESSION['mensaje_perfil'] = $mensaje;
    $_SESSION['tipo_mensaje_perfil'] = $tipo;
    header('Location: ' . urlAplicacion('/estudiante/perfil.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverPerfil('Solicitud no válida.', 'danger');
}

if (!hash_equals($_SESSION['csrf_foto_perfil'], (string)($_POST['csrf'] ?? ''))) {
    volverPerfil('La solicitud de seguridad expiró. Recarga el perfil.', 'danger');
}

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    volverPerfil('Selecciona una imagen válida.', 'danger');
}

$file = $_FILES['foto'];
if ((int)$file['size'] > 5 * 1024 * 1024) {
    volverPerfil('La foto no puede superar 5 MB.', 'danger');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$permitidos = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if (!isset($permitidos[$mime])) {
    volverPerfil('Solo se permiten imágenes JPG, PNG o WEBP.', 'danger');
}

$directorio = dirname(__DIR__) . '/assets/uploads/avatares';
if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
    volverPerfil('No fue posible preparar la carpeta de imágenes.', 'danger');
}

$extension = $permitidos[$mime];
$nombre = 'usuario_' . $idUsuario . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

if (!move_uploaded_file($file['tmp_name'], $destino)) {
    volverPerfil('No fue posible guardar la foto.', 'danger');
}

try {
    $st = $conexion->prepare("SELECT avatar FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->execute([$idUsuario]);
    $anterior = (string)$st->fetchColumn();

    $rutaBD = 'assets/uploads/avatares/' . $nombre;

    $st = $conexion->prepare("UPDATE usuarios SET avatar=?, id_avatar=NULL WHERE id_usuario=?");
    $st->execute([$rutaBD, $idUsuario]);

    // Borra únicamente archivos personalizados anteriores del mismo usuario.
    if (preg_match('/^assets\/uploads\/avatares\/usuario_' . preg_quote((string)$idUsuario, '/') . '_[a-f0-9]+\.(jpg|png|webp)$/i', $anterior)) {
        $archivoAnterior = dirname(__DIR__) . '/' . $anterior;
        if (is_file($archivoAnterior)) {
            @unlink($archivoAnterior);
        }
    }

    volverPerfil('Tu foto de perfil se actualizó correctamente.');
} catch (Throwable $e) {
    @unlink($destino);
    volverPerfil('No fue posible actualizar tu foto de perfil.', 'danger');
}

<?php
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . urlAplicacion('/admin/dashboard.php')); exit;
}

$idRecurso = filter_input(INPUT_POST, 'id_recurso', FILTER_VALIDATE_INT);
$idTema = filter_input(INPUT_POST, 'id_tema', FILTER_VALIDATE_INT);
$csrf = (string)($_POST['csrf'] ?? '');

if (!$idRecurso || !$idTema || empty($_SESSION['csrf_recursos']) || !hash_equals($_SESSION['csrf_recursos'], $csrf)) die('Solicitud no válida.');

try {
    $stmt = $conexion->prepare('SELECT url, imagen FROM recursos WHERE id_recurso=? AND id_tema=? LIMIT 1');
    $stmt->execute([$idRecurso, $idTema]);
    $recurso = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recurso) {
        foreach (['url','imagen'] as $campo) {
            $valor = (string)($recurso[$campo] ?? '');
            if (strpos($valor, '/assets/uploads/recursos/') !== false) {
                $nombre = basename((string)parse_url($valor, PHP_URL_PATH));
                $ruta = __DIR__ . '/../../assets/uploads/recursos/' . $nombre;
                if (is_file($ruta)) @unlink($ruta);
            }
        }
        $del = $conexion->prepare('DELETE FROM recursos WHERE id_recurso=? AND id_tema=?');
        $del->execute([$idRecurso, $idTema]);
    }

    header('Location: ' . urlAplicacion('/admin/contenidos/recursos.php?id='.$idTema.'&deleted=1')); exit;
} catch (PDOException $e) {
    die('No fue posible eliminar el recurso.');
}

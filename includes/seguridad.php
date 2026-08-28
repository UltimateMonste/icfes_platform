<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/funciones.php";

/*
|--------------------------------------------------------------------------
| URL BASE DE LA APLICACIÓN
|--------------------------------------------------------------------------
| Se calcula automáticamente para que funcione en XAMPP/localhost sin
| depender de rutas relativas como ../ o ../../.
|--------------------------------------------------------------------------
*/
function obtenerBaseURLAplicacion()
{
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $applicationRoot = realpath(__DIR__ . '/..');

    if ($documentRoot && $applicationRoot) {
        $documentRoot = str_replace('\\', '/', $documentRoot);
        $applicationRoot = str_replace('\\', '/', $applicationRoot);

        if (strpos($applicationRoot, $documentRoot) === 0) {
            $relative = substr($applicationRoot, strlen($documentRoot));
            $baseUrl = '/' . trim($relative, '/');
        }
    }

    if (empty($baseUrl) || $baseUrl === '/') {
        $baseUrl = '/icfes_platform';
    }

    return rtrim($baseUrl, '/');
}


function urlAplicacion($ruta = '')
{
    $base = obtenerBaseURLAplicacion();
    $ruta = '/' . ltrim($ruta, '/');

    return $base . $ruta;
}


function redireccionarLogin($mensaje = '')
{
    $url = urlAplicacion('/login.php');

    if ($mensaje !== '') {
        $url .= '?error=' . urlencode($mensaje);
    }

    header('Location: ' . $url);
    exit;
}


function redireccionarDashboardUsuario($usuario = null)
{
    if ($usuario === null) {
        $usuario = obtenerEstadoUsuario();
    }

    if (!$usuario) {
        redireccionarLogin('La sesión no es válida.');
    }

    $rol = (int)($usuario['id_rol'] ?? 0);

    if ($rol === 1) {
        header('Location: ' . urlAplicacion('/admin/dashboard.php'));
        exit;
    }

    if ($rol === 2) {
        header('Location: ' . urlAplicacion('/estudiante/dashboard.php'));
        exit;
    }

    session_unset();
    session_destroy();

    redireccionarLogin('El rol de usuario no es válido.');
}


/*
|--------------------------------------------------------------------------
| EXIGIR INICIO DE SESIÓN
|--------------------------------------------------------------------------
*/
function exigirLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        redireccionarLogin('Debes iniciar sesión para acceder a esta página.');
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER ESTADO ACTUAL DEL USUARIO
|--------------------------------------------------------------------------
*/
function obtenerEstadoUsuario()
{
    global $conexion;

    if (!isset($_SESSION['id_usuario'])) {
        return false;
    }

    try {
        $sql = "
            SELECT
                id_usuario,
                nombres,
                apellidos,
                correo,
                id_rol,
                grado,
                puntos,
                nivel,
                primer_ingreso,
                estado
            FROM usuarios
            WHERE id_usuario = :id_usuario
            LIMIT 1
        ";

        $consulta = $conexion->prepare($sql);
        $consulta->execute([
            ':id_usuario' => (int)$_SESSION['id_usuario']
        ]);

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $_SESSION['nombres'] = $usuario['nombres'];
            $_SESSION['apellidos'] = $usuario['apellidos'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['grado'] = $usuario['grado'];
            $_SESSION['puntos'] = $usuario['puntos'];
            $_SESSION['nivel'] = $usuario['nivel'];
            $_SESSION['primer_ingreso'] = $usuario['primer_ingreso'];
        }

        return $usuario ?: false;

    } catch (PDOException $e) {
        return false;
    }
}


/*
|--------------------------------------------------------------------------
| VALIDAR CUENTA
|--------------------------------------------------------------------------
*/
function validarUsuarioActual()
{
    exigirLogin();

    $usuario = obtenerEstadoUsuario();

    if (!$usuario) {
        session_unset();
        session_destroy();
        redireccionarLogin('La sesión no es válida.');
    }

    if (($usuario['estado'] ?? '') !== 'Activo') {
        session_unset();
        session_destroy();
        redireccionarLogin('Su cuenta se encuentra inactiva.');
    }

    return $usuario;
}


/*
|--------------------------------------------------------------------------
| EXIGIR ADMINISTRADOR
|--------------------------------------------------------------------------
| Si un estudiante intenta entrar a una página administrativa, no verá
| un 403: volverá automáticamente a su dashboard.
|--------------------------------------------------------------------------
*/
function exigirAdmin()
{
    $usuario = validarUsuarioActual();

    if ((int)$usuario['id_rol'] !== 1) {
        redireccionarDashboardUsuario($usuario);
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR ESTUDIANTE
|--------------------------------------------------------------------------
| El administrador puede utilizar páginas explícitamente preparadas para
| vista previa, por lo que esas páginas no deben llamar a esta función.
|--------------------------------------------------------------------------
*/
function exigirEstudiante()
{
    $usuario = validarUsuarioActual();

    if ((int)$usuario['id_rol'] !== 2) {
        redireccionarDashboardUsuario($usuario);
    }

    if ((int)$usuario['primer_ingreso'] === 1) {
        header('Location: ' . urlAplicacion('/cambiar_password.php'));
        exit;
    }

    $_SESSION['primer_ingreso'] = $usuario['primer_ingreso'];
}


/*
|--------------------------------------------------------------------------
| PERMITIR ESTUDIANTE O ADMINISTRADOR
|--------------------------------------------------------------------------
| Para páginas compartidas como estudiante/tema.php.
| Devuelve el usuario autenticado y valida la cuenta.
|--------------------------------------------------------------------------
*/
function exigirEstudianteOAdmin()
{
    $usuario = validarUsuarioActual();

    $rol = (int)$usuario['id_rol'];

    if ($rol !== 1 && $rol !== 2) {
        redireccionarDashboardUsuario($usuario);
    }

    if ($rol === 2 && (int)$usuario['primer_ingreso'] === 1) {
        header('Location: ' . urlAplicacion('/cambiar_password.php'));
        exit;
    }

    $_SESSION['primer_ingreso'] = $usuario['primer_ingreso'];

    return $usuario;
}

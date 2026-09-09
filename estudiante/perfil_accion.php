<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/gamificacion.php';

exigirEstudiante();

$idUsuario=(int)($_SESSION['id_usuario']??0);
if(empty($_SESSION['csrf_perfil_accion'])){
    $_SESSION['csrf_perfil_accion']=bin2hex(random_bytes(32));
}
$csrf=$_SESSION['csrf_perfil_accion'];

function volverPerfilAccion(string $m,string $t='success'): never {
    $_SESSION['mensaje_perfil']=$m;
    $_SESSION['tipo_mensaje_perfil']=$t;
    header('Location: '.urlAplicacion('/estudiante/perfil.php'));
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($csrf,(string)($_POST['csrf']??''))){
    volverPerfilAccion('Solicitud inválida o expirada.','danger');
}

$accion=(string)($_POST['accion']??'');

try{
    if($accion==='avatar'){
        $idAvatar=(int)($_POST['id_avatar']??0);

        $st=$conexion->prepare("SELECT puntos FROM usuarios WHERE id_usuario=? LIMIT 1");
        $st->execute([$idUsuario]);
        $puntos=(int)$st->fetchColumn();

        $st=$conexion->prepare("SELECT id_avatar,nombre,imagen,puntos_requeridos FROM avatares WHERE id_avatar=? AND estado='Activo' LIMIT 1");
        $st->execute([$idAvatar]);
        $a=$st->fetch(PDO::FETCH_ASSOC);

        if(!$a) throw new RuntimeException('El avatar no está disponible.');
        if($puntos<(int)$a['puntos_requeridos']) throw new RuntimeException('No tienes suficientes puntos para este avatar.');

        $st=$conexion->prepare("UPDATE usuarios SET id_avatar=?,avatar=? WHERE id_usuario=?");
        $st->execute([$idAvatar,$a['imagen'],$idUsuario]);

        volverPerfilAccion('Avatar actualizado correctamente.');
    }

    if($accion==='foto'){
        if(!isset($_FILES['foto']) || $_FILES['foto']['error']!==UPLOAD_ERR_OK){
            throw new RuntimeException('Selecciona una imagen.');
        }

        $f=$_FILES['foto'];
        if((int)$f['size']>5*1024*1024) throw new RuntimeException('La imagen no puede superar 5 MB.');

        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
        if(!$ext) throw new RuntimeException('Solo se permiten JPG, PNG o WEBP.');

        $dir=__DIR__.'/../assets/uploads/avatares';
        if(!is_dir($dir)) mkdir($dir,0755,true);

        $name='usuario_'.$idUsuario.'_'.bin2hex(random_bytes(8)).'.'.$ext;
        $dest=$dir.'/'.$name;

        if(!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('No fue posible guardar la imagen.');

        $st=$conexion->prepare("SELECT avatar FROM usuarios WHERE id_usuario=? LIMIT 1");
        $st->execute([$idUsuario]);
        $old=(string)$st->fetchColumn();

        $ruta='assets/uploads/avatares/'.$name;
        $st=$conexion->prepare("UPDATE usuarios SET avatar=?,id_avatar=NULL WHERE id_usuario=?");
        $st->execute([$ruta,$idUsuario]);

        if(preg_match('/^assets\/uploads\/avatares\/usuario_'.preg_quote((string)$idUsuario,'/').'_[a-f0-9]+\.(jpg|png|webp)$/i',$old)){
            $oldFile=__DIR__.'/../'.$old;
            if(is_file($oldFile)) @unlink($oldFile);
        }

        volverPerfilAccion('Foto de perfil actualizada correctamente.');
    }

    throw new RuntimeException('Acción no reconocida.');
}catch(Throwable $e){
    volverPerfilAccion($e instanceof RuntimeException?$e->getMessage():'No fue posible actualizar el perfil.','danger');
}

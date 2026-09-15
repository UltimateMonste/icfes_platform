<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seguridad.php';
exigirAdmin();

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fotoPerfilAdmin(?string $foto): string {
    $foto = trim((string)$foto);
    if ($foto === '') return '';
    if (preg_match('#^https?://#i', $foto)) return $foto;
    $foto = str_replace('\\','/',ltrim($foto,'/'));
    if (str_starts_with($foto,'assets/')) return urlAplicacion('/'.$foto);
    if (str_starts_with($foto,'uploads/')) return urlAplicacion('/assets/'.$foto);
    return urlAplicacion('/assets/uploads/perfiles/'.basename($foto));
}

$id=(int)($_SESSION['id_usuario']??0);
if($id<=0) redireccionarLogin('La sesión no es válida.');
if(empty($_SESSION['csrf_perfil_admin'])) $_SESSION['csrf_perfil_admin']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_perfil_admin'];
$mensaje='';$tipo='success';

try {
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if(!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('La sesión del formulario expiró. Recarga la página.');
        $accion=(string)($_POST['accion']??'perfil');
        if($accion==='perfil') {
            $nombres=trim((string)($_POST['nombres']??''));
            $apellidos=trim((string)($_POST['apellidos']??''));
            $correo=trim((string)($_POST['correo']??''));
            if($nombres==='') throw new RuntimeException('Los nombres son obligatorios.');
            if($apellidos==='') throw new RuntimeException('Los apellidos son obligatorios.');
            if(!filter_var($correo,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo electrónico no es válido.');
            $st=$conexion->prepare('SELECT COUNT(*) FROM usuarios WHERE correo=? AND id_usuario<>?');$st->execute([$correo,$id]);
            if((int)$st->fetchColumn()>0) throw new RuntimeException('Ese correo ya está registrado por otro usuario.');
            $st=$conexion->prepare('UPDATE usuarios SET nombres=?,apellidos=?,correo=? WHERE id_usuario=? AND id_rol=1');
            $st->execute([$nombres,$apellidos,$correo,$id]);
            $_SESSION['nombres']=$nombres;$_SESSION['apellidos']=$apellidos;$_SESSION['correo']=$correo;
            $mensaje='Tus datos se actualizaron correctamente.';
        } elseif($accion==='password') {
            $actual=(string)($_POST['password_actual']??'');$nueva=(string)($_POST['password_nueva']??'');$confirm=(string)($_POST['password_confirmar']??'');
            if($actual===''||$nueva===''||$confirm==='') throw new RuntimeException('Completa los tres campos de contraseña.');
            if(strlen($nueva)<8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            if($nueva!==$confirm) throw new RuntimeException('La confirmación de contraseña no coincide.');
            $st=$conexion->prepare('SELECT password FROM usuarios WHERE id_usuario=? AND id_rol=1 LIMIT 1');$st->execute([$id]);$hash=(string)$st->fetchColumn();
            if(!password_verify($actual,$hash)) throw new RuntimeException('La contraseña actual no es correcta.');
            $st=$conexion->prepare('UPDATE usuarios SET password=?,fecha_cambio_password=NOW(),primer_ingreso=0 WHERE id_usuario=? AND id_rol=1');$st->execute([password_hash($nueva,PASSWORD_DEFAULT),$id]);
            $mensaje='Tu contraseña se cambió correctamente.';
        } elseif($accion==='foto') {
            if(!isset($_FILES['foto'])||$_FILES['foto']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Selecciona una imagen válida.');
            $f=$_FILES['foto']; if((int)$f['size']>5*1024*1024) throw new RuntimeException('La foto no puede superar 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
            if(!$ext) throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
            $dir=__DIR__.'/../assets/uploads/perfiles';if(!is_dir($dir)&&!mkdir($dir,0755,true)) throw new RuntimeException('No fue posible preparar la carpeta de fotos.');
            $nombre='admin_'.$id.'_'.bin2hex(random_bytes(8)).'.'.$ext;$dest=$dir.'/'.$nombre;
            if(!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('No fue posible guardar la foto.');
            $st=$conexion->prepare('SELECT avatar FROM usuarios WHERE id_usuario=? LIMIT 1');$st->execute([$id]);$old=(string)$st->fetchColumn();
            $ruta='assets/uploads/perfiles/'.$nombre;$st=$conexion->prepare('UPDATE usuarios SET avatar=?,id_avatar=NULL WHERE id_usuario=? AND id_rol=1');$st->execute([$ruta,$id]);
            if(preg_match('/^assets\/uploads\/perfiles\/admin_'.preg_quote((string)$id,'/').'_[a-f0-9]+\.(jpg|png|webp)$/i',$old)) { $oldFile=__DIR__.'/../'.$old;if(is_file($oldFile)) @unlink($oldFile); }
            $mensaje='Tu foto de perfil se actualizó correctamente.';
        }
    }
    $st=$conexion->prepare('SELECT nombres,apellidos,correo,avatar,fecha_registro,ultimo_acceso FROM usuarios WHERE id_usuario=? AND id_rol=1 LIMIT 1');$st->execute([$id]);$admin=$st->fetch(PDO::FETCH_ASSOC);
    if(!$admin) redireccionarLogin('Administrador no encontrado.');
} catch(Throwable $e) { $tipo='danger';$mensaje=$e instanceof RuntimeException?$e->getMessage():'No fue posible actualizar el perfil.'; if(empty($admin)) { $st=$conexion->prepare('SELECT nombres,apellidos,correo,avatar,fecha_registro,ultimo_acceso FROM usuarios WHERE id_usuario=? AND id_rol=1 LIMIT 1');$st->execute([$id]);$admin=$st->fetch(PDO::FETCH_ASSOC)?:[]; } }
$foto=fotoPerfilAdmin($admin['avatar']??'');
$nombreCompleto=trim(($admin['nombres']??'').' '.($admin['apellidos']??''));
$iniciales='';foreach(preg_split('/\s+/',trim($nombreCompleto)) as $parte){if($parte!=='')$iniciales.=mb_strtoupper(mb_substr($parte,0,1));if(mb_strlen($iniciales)>=2)break;}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi perfil | Studia360</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><style>
:root{--blue:#2563eb;--navy:#173f78;--bg:#f6f8fc;--border:#e4eaf2;--text:#23344b;--muted:#718096}body{background:radial-gradient(circle at top right,#eaf2ff,transparent 28%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.top{background:linear-gradient(105deg,#173f78,#2563b8);box-shadow:0 5px 20px rgba(23,63,120,.14)}.shell{max-width:1100px;margin:auto;padding:32px 16px 60px}.hero{border-radius:26px;padding:28px;color:#fff;background:linear-gradient(125deg,#2563b8,#173f78);box-shadow:0 18px 42px rgba(23,63,120,.16)}.cardx{background:#fff;border:1px solid var(--border);border-radius:22px;box-shadow:0 10px 28px rgba(31,57,92,.055)}.profile-photo{width:150px;height:150px;border-radius:38px;object-fit:cover;border:5px solid #fff;box-shadow:0 10px 30px rgba(0,0,0,.12);background:#eaf2ff}.placeholder{width:150px;height:150px;border-radius:38px;background:#eaf2ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:900;border:5px solid #fff}.form-control{border-radius:12px;padding:.7rem .85rem}.btn{border-radius:11px}.muted{color:var(--muted)}
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head><body class="s360-admin"><nav class="navbar navbar-dark top"><div class="container-fluid px-3 px-lg-4 py-2"><a class="navbar-brand fw-bold" href="<?=h(urlAplicacion('/admin/dashboard.php'))?>"><i class="bi bi-mortarboard-fill me-2"></i>Studia360</a><div class="d-flex gap-2"><a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/admin/dashboard.php'))?>"><i class="bi bi-arrow-left me-1"></i>Dashboard</a><a class="btn btn-outline-light btn-sm" href="<?=h(urlAplicacion('/cerrar_sesion.php'))?>">Salir</a></div></div></nav>
<main class="shell"><section class="hero mb-4"><div class="small text-uppercase fw-bold opacity-75">Cuenta administrativa</div><h1 class="h2 fw-bold mt-1 mb-2">Mi perfil</h1><p class="mb-0 opacity-75">Personaliza tu información y tu foto de perfil. Estos datos pertenecen únicamente a tu cuenta de administrador.</p></section>
<?php if($mensaje):?><div class="alert alert-<?=$tipo?> border-0 shadow-sm"><?=h($mensaje)?></div><?php endif;?>
<section class="cardx p-4 mb-4"><div class="row align-items-center g-4"><div class="col-auto"><?php if($foto):?><img src="<?=h($foto)?>" class="profile-photo" alt="Foto de perfil"><?php else:?><div class="placeholder"><?=h($iniciales?:'AD')?></div><?php endif;?></div><div class="col"><div class="small text-uppercase fw-bold text-primary">Administrador</div><h2 class="h3 fw-bold mb-1"><?=h($nombreCompleto?:'Administrador')?></h2><div class="muted"><i class="bi bi-envelope me-1"></i><?=h($admin['correo']??'')?></div></div></div><hr class="my-4"><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="perfil"><div class="col-md-6"><label class="form-label fw-semibold">Nombres</label><input class="form-control" name="nombres" value="<?=h($admin['nombres']??'')?>" required></div><div class="col-md-6"><label class="form-label fw-semibold">Apellidos</label><input class="form-control" name="apellidos" value="<?=h($admin['apellidos']??'')?>" required></div><div class="col-md-8"><label class="form-label fw-semibold">Correo electrónico</label><input class="form-control" type="email" name="correo" value="<?=h($admin['correo']??'')?>" required></div><div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar información</button></div></form></section>
<div class="row g-4"><div class="col-lg-6"><section class="cardx p-4 h-100"><h2 class="h5 fw-bold"><i class="bi bi-camera me-2 text-primary"></i>Foto de perfil</h2><p class="muted small">Sube una imagen que te identifique en el panel administrativo. JPG, PNG o WEBP, máximo 5 MB.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="foto"><input class="form-control mb-3" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required><button class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>Actualizar foto</button></form></section></div><div class="col-lg-6"><section class="cardx p-4 h-100"><h2 class="h5 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>Cambiar contraseña</h2><p class="muted small">La contraseña del administrador sí puede cambiarse desde su propia cuenta.</p><form method="post" class="row g-2"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="accion" value="password"><div class="col-12"><input class="form-control" type="password" name="password_actual" placeholder="Contraseña actual" autocomplete="current-password" required></div><div class="col-md-6"><input class="form-control" type="password" name="password_nueva" placeholder="Nueva contraseña" autocomplete="new-password" required></div><div class="col-md-6"><input class="form-control" type="password" name="password_confirmar" placeholder="Confirmar contraseña" autocomplete="new-password" required></div><div class="col-12"><button class="btn btn-primary"><i class="bi bi-key me-1"></i>Cambiar contraseña</button></div></form></section></div></div></main><!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button></div>
<script>(function(){const b=document.body,k='studia360_admin_theme',t={purple:'adm-accent-purple',blue:'adm-accent-blue',orange:'adm-accent-orange',green:'adm-accent-green'};let s={theme:'purple',mode:'dark'};try{s=Object.assign(s,JSON.parse(localStorage.getItem(k)||'{}'))}catch(e){}function a(){b.classList.add('s360-admin');Object.values(t).forEach(c=>b.classList.remove(c));b.classList.add(t[s.theme]||t.purple);b.classList.toggle('adm-dark',s.mode==='dark');document.querySelectorAll('.adm-theme-option').forEach(x=>x.classList.toggle('active',x.dataset.theme===s.theme));const m=document.getElementById('admModeBtn');if(m)m.innerHTML=s.mode==='dark'?"<i class='bi bi-moon-stars me-2'></i>Modo oscuro":"<i class='bi bi-sun me-2'></i>Modo claro"}window.admSetTheme=function(x){if(!t[x])return;s.theme=x;try{localStorage.setItem(k,JSON.stringify(s))}catch(e){}a()};window.admToggleMode=function(){s.mode=s.mode==='dark'?'light':'dark';try{localStorage.setItem(k,JSON.stringify(s))}catch(e){}a()};a();const q=document.getElementById('admThemeToggle'),p=document.getElementById('admThemePanel');q?.addEventListener('click',()=>p?.classList.toggle('open'));document.addEventListener('click',e=>{if(p?.classList.contains('open')&&!p.contains(e.target)&&!q.contains(e.target))p.classList.remove('open')})})();</script>
</body></html>

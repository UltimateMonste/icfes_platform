<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/seguridad.php';
require_once __DIR__ . '/../../includes/gamificacion.php';
exigirAdmin();

function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(empty($_SESSION['csrf_gam_admin'])) $_SESSION['csrf_gam_admin']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_gam_admin']; $mensaje=''; $tipo='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Solicitud de seguridad inválida.');
        $id=(int)($_POST['id_usuario']??0);
        $p=(int)($_POST['puntos']??0);
        if($id<1 || $p===0) throw new RuntimeException('Indica un estudiante y una cantidad de puntos distinta de cero.');

        $conexion->beginTransaction();
        $st=$conexion->prepare("SELECT puntos FROM usuarios WHERE id_usuario=? FOR UPDATE");
        $st->execute([$id]);
        $actual=$st->fetchColumn();
        if($actual===false) throw new RuntimeException('Estudiante no encontrado.');

        $nuevo=max(0,(int)$actual+$p);
        $st=$conexion->prepare("UPDATE usuarios SET puntos=? WHERE id_usuario=?");
        $st->execute([$nuevo,$id]);

        $st=$conexion->prepare("INSERT INTO historial_puntos(id_usuario,motivo,puntos) VALUES(?,?,?)");
        $st->execute([$id,'ajuste_admin', $p]);
        $conexion->commit();

        sincronizarNivel($conexion,$id);
        $mensaje='Puntos actualizados correctamente.';
    }catch(Throwable $e){
        if($conexion->inTransaction())$conexion->rollBack();
        $mensaje=$e instanceof RuntimeException?$e->getMessage():'No fue posible realizar el ajuste.';
        $tipo='danger';
    }
}

$buscar=trim((string)($_GET['buscar']??''));
$sql="SELECT u.id_usuario,u.nombres,u.apellidos,u.numero_documento,u.grado,u.puntos,u.nivel,
       n.nombre nombre_nivel
       FROM usuarios u
       LEFT JOIN niveles n ON n.id_nivel=u.nivel
       WHERE u.id_rol=(SELECT id_rol FROM roles WHERE nombre='Estudiante' LIMIT 1)";
$params=[];
if($buscar!==''){
    $sql.=" AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR u.numero_documento LIKE ?)";
    $q='%'.$buscar.'%';$params=[$q,$q,$q];
}
$sql.=" ORDER BY u.apellidos,u.nombres LIMIT 100";
try{$st=$conexion->prepare($sql);$st->execute($params);$estudiantes=$st->fetchAll(PDO::FETCH_ASSOC);}
catch(Throwable $e){$estudiantes=[];}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>XP de estudiantes | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#f4f7fb;color:#26364a}.navbar{background:linear-gradient(100deg,#173f80,#2467c5)}.cardx{background:#fff;border:1px solid #dce5f0;border-radius:20px;box-shadow:0 8px 25px #1f395c0c}.table>:not(caption)>*>*{padding:1rem}.badge-xp{font-size:.9rem}</style><link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="s360-admin"><nav class="navbar navbar-dark"><div class="container py-2"><a class="navbar-brand fw-bold" href="<?=h(urlAplicacion('/admin/dashboard.php'))?>">Studia360</a><a class="btn btn-light btn-sm" href="<?=h(urlAplicacion('/admin/gamificacion/index.php'))?>">Gamificación</a></div></nav>
<main class="container py-4"><div class="cardx p-4 mb-4"><h1 class="h3 fw-bold">Puntos de estudiantes</h1><p class="text-muted">Herramienta administrativa para revisar y corregir XP durante las pruebas del sistema.</p>
<form class="row g-2"><div class="col-md-10"><input class="form-control" name="buscar" value="<?=h($buscar)?>" placeholder="Buscar por nombre, apellido o documento"></div><div class="col-md-2"><button class="btn btn-primary w-100">Buscar</button></div></form></div>
<?php if($mensaje):?><div class="alert alert-<?=$tipo?>"><?=h($mensaje)?></div><?php endif;?>
<div class="cardx overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Estudiante</th><th>Documento</th><th>Grado</th><th>Puntos</th><th>Nivel</th><th>Ajustar</th></tr></thead><tbody>
<?php foreach($estudiantes as $e):?><tr><td><strong><?=h($e['nombres'].' '.$e['apellidos'])?></strong></td><td><?=h($e['numero_documento'])?></td><td><?=h($e['grado'])?></td><td><span class="badge text-bg-primary badge-xp"><?=number_format((int)$e['puntos'])?> XP</span></td><td><?=h($e['nombre_nivel']??$e['nivel'])?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="id_usuario" value="<?=$e['id_usuario']?>"><input class="form-control form-control-sm" style="max-width:110px" type="number" name="puntos" placeholder="+/- XP"><button class="btn btn-sm btn-outline-primary">Aplicar</button></form></td></tr>
<?php endforeach;?><?php if(!$estudiantes):?><tr><td colspan="6" class="text-center text-muted py-4">No se encontraron estudiantes.</td></tr><?php endif;?>
</tbody></table></div></div></main><!-- Studia360 Admin: personalizador global -->
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

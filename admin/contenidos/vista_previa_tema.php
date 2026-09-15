<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id||$id<=0){header('Location: temas.php');exit;}
function recursoIcon(string $tipo):string{return match(strtolower($tipo)){ 'video'=>'bi-play-circle-fill','pdf'=>'bi-file-earmark-pdf-fill','juego'=>'bi-controller','simulador'=>'bi-display','app'=>'bi-phone','presentacion'=>'bi-easel2-fill','articulo'=>'bi-file-text-fill','blog'=>'bi-journal-text',default=>'bi-link-45deg'};}
function youtubeId(string $url):?string{foreach(['~youtu\.be/([A-Za-z0-9_-]{11})~i','~youtube\.com/watch\?(?:[^#]*&)?v=([A-Za-z0-9_-]{11})~i','~youtube\.com/embed/([A-Za-z0-9_-]{11})~i','~youtube\.com/shorts/([A-Za-z0-9_-]{11})~i'] as $p)if(preg_match($p,$url,$m))return $m[1];return null;}
function assetUrl(string $value):string{
 $v=trim($value);if($v==='')return '';
 if(preg_match('~^https?://~i',$v))return $v;
 $v=str_replace('\\','/',ltrim($v,'/'));
 if(str_starts_with($v,'icfes_platform/'))$v=substr($v,14);
 return urlAplicacion('/'.ltrim($v,'/'));
}
function normalizarHtml(string $html):string{
 $html=trim($html);if($html==='')return '';
 $html=preg_replace(['#<script\b[^>]*>.*?</script>#is','#<style\b[^>]*>.*?</style>#is','#<object\b[^>]*>.*?</object>#is','#<embed\b[^>]*>.*?</embed>#is','/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu'],'',$html);
 $html=preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript|data):.*?\2/iu','',$html);
 $prev=libxml_use_internal_errors(true);$dom=new DOMDocument('1.0','UTF-8');$ok=$dom->loadHTML('<?xml encoding="UTF-8"><div id="s360root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
 if($ok){$root=$dom->getElementById('s360root');
  foreach($root->getElementsByTagName('font') as $font){$parent=$font->parentNode;while($font->firstChild)$parent->insertBefore($font->firstChild,$font);$parent->removeChild($font);}
  foreach($root->getElementsByTagName('img') as $img){$src=$img->getAttribute('src');if($src)$img->setAttribute('src',assetUrl($src));$img->setAttribute('loading','lazy');$img->setAttribute('decoding','async');$img->setAttribute('style',trim($img->getAttribute('style').';max-width:100%;height:auto;'));}
  foreach($root->getElementsByTagName('iframe') as $frame){$frame->setAttribute('loading','lazy');$frame->setAttribute('allowfullscreen','allowfullscreen');$frame->setAttribute('referrerpolicy','strict-origin-when-cross-origin');}
  $out='';foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);
  $html=$out;
 }libxml_clear_errors();libxml_use_internal_errors($prev);return trim($html);
}
try{
 $st=$conexion->prepare("SELECT t.id_tema,t.nombre tema,t.descripcion,t.grado,m.nombre materia FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia WHERE t.id_tema=? LIMIT 1");$st->execute([$id]);$tema=$st->fetch(PDO::FETCH_ASSOC);if(!$tema){header('Location: temas.php');exit;}
 $st=$conexion->prepare("SELECT id_contenido,contenido,estado,fecha_actualizacion FROM contenido_temas WHERE id_tema=? ORDER BY CASE WHEN estado='Borrador' THEN 0 ELSE 1 END,fecha_actualizacion DESC,id_contenido DESC LIMIT 1");$st->execute([$id]);$contenido=$st->fetch(PDO::FETCH_ASSOC)?:null;
 $st=$conexion->prepare("SELECT titulo,tipo,url,descripcion,imagen,autor,fuente,estado FROM recursos WHERE id_tema=? ORDER BY id_recurso");$st->execute([$id]);$recursos=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $ex){die('No fue posible cargar la vista previa.');}
$html=$contenido?normalizarHtml((string)$contenido['contenido']):'';
$urlTemas=urlAplicacion('/admin/contenidos/temas.php');$urlInfo=urlAplicacion('/admin/contenidos/informacion_tema.php?id='.$id);$urlEditor=urlAplicacion('/admin/contenidos/editar_tema.php?id='.$id);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vista previa · <?=e($tema['tema'])?> | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?=e(urlAplicacion('/admin/assets/studia-admin.css'))?>">
<style>
.preview-shell{max-width:1180px;margin:auto;padding:24px 18px 70px}.preview-header{border-radius:24px;background:#fff;border:1px solid #e4eaf2;padding:16px 18px;box-shadow:0 8px 24px rgba(23,32,51,.05)}.preview-hero{border-radius:25px;padding:34px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;box-shadow:0 18px 45px rgba(37,99,235,.18)}.content-card{background:#fff;border:1px solid #e4eaf2;border-radius:22px;padding:30px;box-shadow:0 10px 28px rgba(23,32,51,.05);overflow:hidden}.content-body{font-size:1.04rem;line-height:1.8;overflow-wrap:anywhere;word-break:break-word}.content-body h1,.content-body h2,.content-body h3,.content-body h4{font-weight:850;letter-spacing:-.025em;margin-top:1.5em}.content-body h1:first-child,.content-body h2:first-child,.content-body h3:first-child{margin-top:0}.content-body img{display:block;max-width:100%!important;width:auto!important;height:auto!important;margin:1rem auto;border-radius:14px}.content-body table{display:block;width:100%!important;max-width:100%;overflow-x:auto;border-collapse:collapse}.content-body td,.content-body th{padding:8px;border:1px solid #dfe6ef}.content-body iframe,.content-body video{display:block;width:100%!important;max-width:100%;min-height:360px;border:0;border-radius:14px;margin:1rem 0}.content-body pre{max-width:100%;overflow:auto;background:#f8fafc;padding:14px;border-radius:12px}.content-body a{overflow-wrap:anywhere}.resource{height:100%;border:1px solid #e4eaf2;border-radius:18px;background:#fff;padding:17px}.resource-icon{width:44px;height:44px;border-radius:13px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.2rem}.resource-link{font-size:.8rem;word-break:break-all}.empty-preview{padding:55px 20px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:18px}
</style>
<style id="studia360-contenidos-polished">
:root{
  --c-accent:#2563eb;
  --c-accent2:#4f46e5;
  --c-soft:#eff6ff;
  --c-bg:#f6f8fc;
  --c-card:#fff;
  --c-text:#1f2937;
  --c-muted:#748196;
  --c-line:#e5eaf1;
  --c-input:#fff;
  --c-track:#e9eef5;
}
body.s360-content-theme{
  --c-accent:#2563eb;--c-accent2:#4f46e5;--c-soft:#eff6ff;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 7%,transparent),transparent 25rem),var(--c-bg)!important;
  color:var(--c-text)!important;
  transition:background .25s ease,color .25s ease;
}
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}

body.s360-content-theme .adm-theme-toggle{
  position:fixed!important;right:20px!important;bottom:20px!important;z-index:2000!important;
  width:50px!important;height:50px!important;padding:0!important;margin:0!important;
  display:grid!important;place-items:center!important;
  border:0!important;border-radius:16px!important;
  color:#fff!important;background:linear-gradient(145deg,var(--c-accent),var(--c-accent2))!important;
  box-shadow:0 12px 30px color-mix(in srgb,var(--c-accent) 28%,transparent)!important;
  cursor:pointer!important;outline:none!important;transition:transform .2s ease,box-shadow .2s ease!important;
}
body.s360-content-theme .adm-theme-toggle:hover{transform:translateY(-3px)!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.15rem!important;line-height:1!important}

body.s360-content-theme .adm-theme-panel{
  position:fixed!important;right:20px!important;bottom:82px!important;z-index:1999!important;
  width:290px!important;max-width:calc(100vw - 28px)!important;
  padding:16px!important;margin:0!important;
  display:block!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;border-radius:18px!important;
  box-shadow:0 20px 55px rgba(20,35,60,.16)!important;
  opacity:0!important;visibility:hidden!important;pointer-events:none!important;
  transform:translateY(8px) scale(.98)!important;
  transition:opacity .18s ease,transform .18s ease,visibility .18s ease!important;
}
body.s360-content-theme .adm-theme-panel.open{
  opacity:1!important;visibility:visible!important;pointer-events:auto!important;
  transform:none!important;
}
body.s360-content-theme .adm-theme-title{
  margin:0 0 4px!important;font-size:.84rem!important;line-height:1.25!important;
  font-weight:850!important;color:var(--c-text)!important;
}
body.s360-content-theme .adm-theme-sub{
  margin:0 0 13px!important;font-size:.66rem!important;line-height:1.45!important;
  color:var(--c-muted)!important;
}
body.s360-content-theme .adm-theme-grid{
  display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;
}
body.s360-content-theme .adm-theme-option{
  appearance:none!important;width:100%!important;min-height:74px!important;
  padding:9px!important;margin:0!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;box-shadow:none!important;
  font:inherit!important;transition:.18s ease!important;
}
body.s360-content-theme .adm-theme-option:hover,
body.s360-content-theme .adm-theme-option.active{
  border-color:color-mix(in srgb,var(--c-accent) 42%,var(--c-line))!important;
  background:var(--c-soft)!important;transform:translateY(-1px)!important;
}
body.s360-content-theme .adm-swatch{
  display:block!important;width:100%!important;height:23px!important;margin:0 0 7px!important;
  border-radius:8px!important;
}
body.s360-content-theme .adm-theme-option strong{
  display:block!important;font-size:.67rem!important;line-height:1.15!important;
}
body.s360-content-theme .adm-theme-option small{
  display:block!important;margin-top:3px!important;font-size:.57rem!important;
  line-height:1.15!important;color:var(--c-muted)!important;
}
body.s360-content-theme .adm-mode-btn{
  appearance:none!important;width:100%!important;min-height:38px!important;
  margin:9px 0 0!important;padding:8px 10px!important;text-align:left!important;
  border:1px solid var(--c-line)!important;border-radius:12px!important;
  background:var(--c-card)!important;color:var(--c-text)!important;
  cursor:pointer!important;font:700 .68rem/1.2 system-ui,sans-serif!important;
}
body.s360-content-theme .adm-mode-btn:hover{
  color:var(--c-accent)!important;background:var(--c-soft)!important;
}

/* Capa visual de las pantallas existentes; no cambia su estructura ni sus datos. */
body.s360-content-theme .card,
body.s360-content-theme .card-soft,
body.s360-content-theme .editor-card,
body.s360-content-theme .info-card,
body.s360-content-theme .content-card,
body.s360-content-theme .preview-header,
body.s360-content-theme .resource,
body.s360-content-theme .resource-card,
body.s360-content-theme .recurso-card,
body.s360-content-theme .sidebar-card,
body.s360-content-theme .panel,
body.s360-content-theme .stat,
body.s360-content-theme .theme-info,
body.s360-content-theme .delete-card{
  background:var(--c-card)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme h1,body.s360-content-theme h2,body.s360-content-theme h3,
body.s360-content-theme h4,body.s360-content-theme h5,body.s360-content-theme h6{
  color:var(--c-text);
}
body.s360-content-theme .text-muted,body.s360-content-theme .muted,
body.s360-content-theme .url-help{color:var(--c-muted)!important}
body.s360-content-theme .text-primary{color:var(--c-accent)!important}
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .btn-primary{
  background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .btn-outline-primary{
  color:var(--c-accent)!important;border-color:color-mix(in srgb,var(--c-accent) 30%,var(--c-line))!important;
  background:var(--c-card)!important;
}
body.s360-content-theme .btn-outline-primary:hover{
  color:#fff!important;background:var(--c-accent)!important;border-color:var(--c-accent)!important;
}
body.s360-content-theme .badge-type,
body.s360-content-theme .icon,
body.s360-content-theme .resource-icon,
body.s360-content-theme .icono-recurso{
  background:var(--c-soft)!important;color:var(--c-accent)!important;
}
body.s360-content-theme .hero,
body.s360-content-theme .preview-hero{
  background:linear-gradient(125deg,var(--c-accent),var(--c-accent2))!important;
}
body.s360-content-theme .table{
  --bs-table-bg:var(--c-card);--bs-table-color:var(--c-text);--bs-table-border-color:var(--c-line);
}
body.s360-content-theme .table thead th{background:var(--c-soft)!important;color:var(--c-text)!important}
body.s360-content-theme .dropdown-menu{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme .dropdown-item{color:var(--c-text)!important}
body.s360-content-theme .dropdown-item:hover{background:var(--c-soft)!important;color:var(--c-accent)!important}

/* Acciones compactas: solo icono, sin deformar los botones. */
body.s360-content-theme .topic-actions .btn,
body.s360-content-theme .recent-row .btn,
body.s360-content-theme .resource .btn.btn-sm,
body.s360-content-theme .resource-card .btn.btn-sm,
body.s360-content-theme .recurso-card .btn.btn-sm{
  width:36px!important;height:36px!important;min-width:36px!important;min-height:36px!important;
  padding:0!important;display:inline-grid!important;place-items:center!important;
  border-radius:10px!important;font-size:0!important;line-height:1!important;
}
body.s360-content-theme .topic-actions .btn i,
body.s360-content-theme .recent-row .btn i,
body.s360-content-theme .resource .btn.btn-sm i,
body.s360-content-theme .resource-card .btn.btn-sm i,
body.s360-content-theme .recurso-card .btn.btn-sm i{
  margin:0!important;font-size:.9rem!important;line-height:1!important;
}

/* Oscuro */
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;--c-card:#172236;--c-text:#edf2f8;--c-muted:#9ba8ba;
  --c-line:#2b374b;--c-input:#111827;--c-track:#263247;
  background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 13%,transparent),transparent 25rem),var(--c-bg)!important;
}
body.s360-content-theme.s360-content-dark .navbar{
  background:rgba(13,20,36,.94)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .navbar-brand,
body.s360-content-theme.s360-content-dark .text-dark,
body.s360-content-theme.s360-content-dark h1,
body.s360-content-theme.s360-content-dark h2,
body.s360-content-theme.s360-content-dark h3,
body.s360-content-theme.s360-content-dark h4,
body.s360-content-theme.s360-content-dark h5,
body.s360-content-theme.s360-content-dark h6{color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea{
  background:var(--c-input)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .bg-light{background:#202c41!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .modal-content{
  background:var(--c-card)!important;color:var(--c-text)!important;border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .note-toolbar{background:#202c41!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-editable{background:var(--c-input)!important;color:var(--c-text)!important}
body.s360-content-theme.s360-content-dark .note-statusbar{background:var(--c-card)!important;border-color:var(--c-line)!important}
body.s360-content-theme.s360-content-dark .note-btn{
  background:#27344a!important;color:#dce4ee!important;border-color:#354158!important;
}
body.s360-content-theme.s360-content-dark .adm-theme-panel{box-shadow:0 22px 60px rgba(0,0,0,.42)!important}

@media(max-width:767px){
  body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}
  body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}
}
</style>

</head><body class="s360-admin">
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlTemas)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-warning">Vista previa</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlInfo)?>"><i class="bi bi-pencil me-1"></i>Editar tema</a><a class="btn btn-primary s360-btn" href="<?=e($urlEditor)?>">Contenido</a></div></div></nav>
<main class="preview-shell"><section class="preview-hero mb-4"><div class="s360-kicker">Vista previa para administración</div><h1 class="h2 fw-bold mt-2 mb-2"><?=e($tema['tema'])?></h1><p class="mb-0 opacity-75"><?=e($tema['materia'])?> · <?=e($tema['grado'])?>°<?php if($tema['descripcion']):?> · <?=e($tema['descripcion'])?><?php endif;?></p></section>
<?php if($contenido):?><div class="mb-3"><span class="s360-chip <?=$contenido['estado']==='Publicado'?'success':'warning'?>"><i class="bi <?=$contenido['estado']==='Publicado'?'bi-check-circle':'bi-pencil-square'?>"></i><?=e($contenido['estado'])?></span> <span class="small text-secondary ms-2">Última actualización: <?=e(date('d/m/Y H:i',strtotime($contenido['fecha_actualizacion'])))?></span></div><?php endif;?>
<div class="row g-4"><div class="col-lg-8"><section class="content-card"><?php if($html):?><article class="content-body"><?=$html?></article><?php else:?><div class="empty-preview"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-file-earmark-text"></i></div><h2 class="h5 fw-bold">Este tema todavía no tiene contenido</h2><p class="s360-muted mb-0">Puedes construir la lección desde el editor de contenido.</p></div><?php endif;?></section></div>
<aside class="col-lg-4"><section class="s360-card p-4 mb-4"><div class="s360-section-title mb-3">Ficha del tema</div><div class="mb-3"><small class="s360-muted">Materia</small><div class="fw-bold"><?=e($tema['materia'])?></div></div><div class="mb-3"><small class="s360-muted">Grado</small><div class="fw-bold"><?=e($tema['grado'])?>°</div></div><div><small class="s360-muted">Recursos</small><div class="fw-bold"><?=count($recursos)?></div></div></section></aside></div>
<?php if($recursos):?><section class="mt-5"><div class="mb-3"><div class="s360-section-title">Recursos complementarios</div><div class="small s360-muted">Así se verán los recursos asociados al tema.</div></div><div class="row g-3"><?php foreach($recursos as $r):$img=assetUrl((string)($r['imagen']??''));if(!$img&&strtolower((string)$r['tipo'])==='video'&&($yt=youtubeId((string)$r['url'])))$img='https://img.youtube.com/vi/'.rawurlencode($yt).'/hqdefault.jpg';?><div class="col-md-6 col-xl-4"><article class="resource"><div class="d-flex gap-3 mb-3"><div class="resource-icon"><i class="bi <?=e(recursoIcon((string)$r['tipo']))?>"></i></div><div><h3 class="h6 fw-bold mb-1"><?=e($r['titulo'])?></h3><span class="small text-secondary"><?=e(ucfirst((string)$r['tipo']))?></span></div></div><?php if($r['descripcion']):?><p class="small text-secondary"><?=e($r['descripcion'])?></p><?php endif;?><a class="resource-link text-decoration-none" href="<?=e($r['url'])?>" target="_blank" rel="noopener noreferrer">Abrir recurso <i class="bi bi-box-arrow-up-right"></i></a></article></div><?php endforeach;?></div></section><?php endif;?>
</main><!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button" onclick="admSetTheme('purple')"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button" onclick="admSetTheme('blue')"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button" onclick="admSetTheme('orange')"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button" onclick="admSetTheme('green')"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button" onclick="admToggleMode()"></button></div>

<script id="studia360-contenidos-theme-controller">
(function(){
  'use strict';
  var body=document.body;
  var key='studia360_theme';
  var toggle=document.getElementById('admThemeToggle');
  var panel=document.getElementById('admThemePanel');
  var modeBtn=document.getElementById('admModeBtn');
  var options=document.querySelectorAll('.adm-theme-option');

  var themes={
    blue:{cls:'',label:'Azul'},
    purple:{cls:'s360-accent-purple',label:'Violeta'},
    orange:{cls:'s360-accent-orange',label:'Naranja'},
    green:{cls:'s360-accent-green',label:'Verde'}
  };

  var saved={theme:'blue',mode:'light'};
  try{
    var raw=localStorage.getItem(key);
    if(raw){
      var parsed=JSON.parse(raw);
      if(parsed && typeof parsed==='object') saved=Object.assign(saved,parsed);
    }
  }catch(e){}
  if(!themes[saved.theme]) saved.theme='blue';
  if(saved.mode!=='dark' && saved.mode!=='light') saved.mode='light';

  function persist(){
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
  }

  function apply(){
    body.classList.add('s360-content-theme');
    Object.keys(themes).forEach(function(name){
      if(themes[name].cls) body.classList.remove(themes[name].cls);
    });
    if(themes[saved.theme].cls) body.classList.add(themes[saved.theme].cls);
    body.classList.toggle('s360-content-dark',saved.mode==='dark');

    options.forEach(function(option){
      option.classList.toggle('active',option.getAttribute('data-theme')===saved.theme);
    });

    if(modeBtn){
      modeBtn.innerHTML=saved.mode==='dark'
        ? '<i class="bi bi-moon-stars me-2"></i>Modo oscuro'
        : '<i class="bi bi-sun me-2"></i>Modo claro';
    }
  }

  window.admSetTheme=function(theme){
    if(!themes[theme]) return;
    saved.theme=theme;
    persist();
    apply();
  };

  window.admToggleMode=function(){
    saved.mode=saved.mode==='dark'?'light':'dark';
    persist();
    apply();
  };

  if(toggle && panel){
    toggle.addEventListener('click',function(event){
      event.preventDefault();
      event.stopPropagation();
      panel.classList.toggle('open');
    });
    panel.addEventListener('click',function(event){
      event.stopPropagation();
    });
    document.addEventListener('click',function(){
      panel.classList.remove('open');
    });
  }

  apply();
})();
</script>

</body></html>

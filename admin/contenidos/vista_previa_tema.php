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
  /* Los colores inline con !important pueden imponerse incluso al tema oscuro.
     Se conserva el resto del estilo (fondos, espaciado, etc.). */
  foreach($root->getElementsByTagName('*') as $el){
   $style=$el->getAttribute('style');
   if($style!==''){
    $style=preg_replace('/(?:^|;)\s*color\s*:\s*[^;]+(?:;|$)/iu',';',$style);
    $style=preg_replace('/;\s*;/',';',$style);
    $style=trim($style,"; \t\r\n");
    if($style!=='')$el->setAttribute('style',$style);else$el->removeAttribute('style');
   }
  }
  foreach($root->getElementsByTagName('img') as $img){$src=$img->getAttribute('src');if($src)$img->setAttribute('src',assetUrl($src));$img->setAttribute('loading','lazy');$img->setAttribute('decoding','async');$img->setAttribute('style',trim($img->getAttribute('style').';max-width:100%;height:auto;'));}
  foreach($root->getElementsByTagName('iframe') as $frame){$frame->setAttribute('loading','lazy');$frame->setAttribute('allowfullscreen','allowfullscreen');$frame->setAttribute('referrerpolicy','strict-origin-when-cross-origin');}
  $out='';foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);
  $html=$out;
 }libxml_clear_errors();libxml_use_internal_errors($prev);return trim($html);
}
try{
 $st=$conexion->prepare("SELECT t.id_tema,t.nombre tema,t.descripcion,t.grado,m.nombre materia,u.nombre unidad FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia LEFT JOIN unidades_tematicas u ON u.id_unidad=t.id_unidad WHERE t.id_tema=? LIMIT 1");$st->execute([$id]);$tema=$st->fetch(PDO::FETCH_ASSOC);if(!$tema){header('Location: temas.php');exit;}
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


<style id="studia360-vista-preview-fix">
/* La tarjeta que contiene la lección se conserva clara por petición del usuario. */
body.s360-content-theme .content-card{
  background:#f8fafc!important;
  color:#172033!important;
  border-color:#dfe6ef!important;
}
body.s360-content-theme .content-body{
  color:#172033!important;
}
body.s360-content-theme .content-body h1,
body.s360-content-theme .content-body h2,
body.s360-content-theme .content-body h3,
body.s360-content-theme .content-body h4,
body.s360-content-theme .content-body h5,
body.s360-content-theme .content-body h6{
  color:#172033!important;
}
body.s360-content-theme .content-body p,
body.s360-content-theme .content-body li,
body.s360-content-theme .content-body td,
body.s360-content-theme .content-body th,
body.s360-content-theme .content-body blockquote{
  color:#263449!important;
}
body.s360-content-theme .content-body a{
  color:var(--c-accent)!important;
}
body.s360-content-theme .content-body pre{
  background:#eef2f7!important;color:#172033!important;
}
body.s360-content-theme .content-body td,
body.s360-content-theme .content-body th{
  border-color:#cfd8e5!important;
}
body.s360-content-theme .empty-preview{
  background:#eef2f7!important;
  color:#263449!important;
  border-color:#c4cfdd!important;
}
body.s360-content-theme .empty-preview h2{
  color:#172033!important;
}
body.s360-content-theme .empty-preview p{
  color:#596a80!important;
}

/* En modo oscuro, solo se mantienen claros el contenido y sus elementos internos.
   Las demás tarjetas dejan de verse blancas. */
body.s360-content-theme.s360-content-dark .preview-header,
body.s360-content-theme.s360-content-dark .resource,
body.s360-content-theme.s360-content-dark .s360-card,
body.s360-content-theme.s360-content-dark .card,
body.s360-content-theme.s360-content-dark .sidebar-card,
body.s360-content-theme.s360-content-dark .panel{
  background:#172236!important;
  color:#edf2f8!important;
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .preview-header .navbar-brand,
body.s360-content-theme.s360-content-dark .s360-card .s360-section-title,
body.s360-content-theme.s360-content-dark .resource h3,
body.s360-content-theme.s360-content-dark .resource .fw-bold{
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .preview-header .text-secondary,
body.s360-content-theme.s360-content-dark .resource .text-secondary,
body.s360-content-theme.s360-content-dark .s360-muted{
  color:#aeb9c9!important;
}
body.s360-content-theme.s360-content-dark .resource-icon,
body.s360-content-theme.s360-content-dark .s360-iconbox{
  background:color-mix(in srgb,var(--c-accent) 16%,#111827)!important;
  color:#fff!important;
}
body.s360-content-theme.s360-content-dark .content-card{
  background:#f8fafc!important;
  color:#172033!important;
  border-color:#dfe6ef!important;
}
body.s360-content-theme.s360-content-dark .content-body,
body.s360-content-theme.s360-content-dark .content-body h1,
body.s360-content-theme.s360-content-dark .content-body h2,
body.s360-content-theme.s360-content-dark .content-body h3,
body.s360-content-theme.s360-content-dark .content-body h4,
body.s360-content-theme.s360-content-dark .content-body h5,
body.s360-content-theme.s360-content-dark .content-body h6{
  color:#172033!important;
}
body.s360-content-theme.s360-content-dark .content-body p,
body.s360-content-theme.s360-content-dark .content-body li,
body.s360-content-theme.s360-content-dark .content-body td,
body.s360-content-theme.s360-content-dark .content-body th,
body.s360-content-theme.s360-content-dark .content-body blockquote{
  color:#263449!important;
}
body.s360-content-theme.s360-content-dark .content-body a{color:var(--c-accent)!important}
body.s360-content-theme.s360-content-dark .content-body pre{
  background:#e9eef5!important;color:#172033!important;
}
body.s360-content-theme.s360-content-dark .content-body td,
body.s360-content-theme.s360-content-dark .content-body th{border-color:#c8d2df!important}
body.s360-content-theme.s360-content-dark .empty-preview{
  background:#eef2f7!important;color:#263449!important;border-color:#c4cfdd!important;
}
body.s360-content-theme.s360-content-dark .empty-preview h2{color:#172033!important}
body.s360-content-theme.s360-content-dark .empty-preview p{color:#596a80!important}

/* Estado y fecha: suficiente contraste sobre fondo oscuro. */
body.s360-content-theme.s360-content-dark .s360-chip{
  background:#202c41!important;color:#dbe5f2!important;border-color:#3a4860!important;
}
body.s360-content-theme.s360-content-dark .s360-chip.success{
  background:#123c32!important;color:#6ee7b7!important;border-color:#1d6b56!important;
}
body.s360-content-theme.s360-content-dark .s360-chip.warning{
  background:#493515!important;color:#fbbf24!important;border-color:#7a571d!important;
}

/* Evitar blancos en botones de navegación en oscuro. */
body.s360-content-theme.s360-content-dark .btn-light{
  background:#172236!important;color:#edf2f8!important;border-color:#334158!important;
}
</style>


<style id="studia360-preview-beauty">
/* ==========================================================
   Vista previa premium: portada + lección + recursos
   ========================================================== */
body.s360-content-theme .preview-shell{
  max-width:1240px!important;
  padding:28px 22px 90px!important;
}

/* Barra superior */
body.s360-content-theme .s360-topbar{
  background:color-mix(in srgb,var(--c-card) 94%,transparent)!important;
  border-bottom:1px solid var(--c-line)!important;
  box-shadow:0 8px 30px rgba(20,35,60,.08)!important;
  backdrop-filter:blur(14px)!important;
  position:sticky!important;
  top:0!important;
  z-index:1200!important;
}
body.s360-content-theme .s360-topbar .btn-light{
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .s360-topbar .btn-light:hover{
  border-color:var(--c-accent)!important;
  color:var(--c-accent)!important;
}
body.s360-content-theme .s360-topbar .btn-primary{
  background:linear-gradient(135deg,var(--c-accent),var(--c-accent2))!important;
  border:0!important;
}

/* Portada */
body.s360-content-theme .preview-hero{
  position:relative!important;
  min-height:245px!important;
  padding:42px 42px!important;
  overflow:hidden!important;
  isolation:isolate!important;
  background:
    radial-gradient(circle at 92% 18%,rgba(255,255,255,.18),transparent 18rem),
    linear-gradient(130deg,var(--c-accent),var(--c-accent2))!important;
  border:1px solid color-mix(in srgb,var(--c-accent) 55%,white)!important;
  box-shadow:0 24px 60px color-mix(in srgb,var(--c-accent) 24%,transparent)!important;
}
body.s360-content-theme .preview-hero:before{
  content:"";
  position:absolute;
  width:300px;height:300px;
  right:-80px;top:-150px;
  border:1px solid rgba(255,255,255,.17);
  border-radius:50%;
  box-shadow:0 0 0 42px rgba(255,255,255,.045),0 0 0 84px rgba(255,255,255,.025);
  z-index:-1;
}
body.s360-content-theme .preview-hero:after{
  content:"✦  ✧  ✦";
  position:absolute;
  right:42px;top:34px;
  color:rgba(255,255,255,.65);
  letter-spacing:13px;
  font-size:1rem;
}
body.s360-content-theme .preview-hero .s360-kicker{
  color:rgba(255,255,255,.78)!important;
  font-weight:850!important;
  letter-spacing:.08em!important;
}
body.s360-content-theme .preview-hero h1{
  color:#fff!important;
  font-size:clamp(2rem,4vw,3.15rem)!important;
  letter-spacing:-.045em!important;
  max-width:800px!important;
}
body.s360-content-theme .preview-hero p{
  color:rgba(255,255,255,.88)!important;
  max-width:900px!important;
  font-size:1rem!important;
}

/* Estado */
body.s360-content-theme .s360-chip{
  display:inline-flex!important;
  align-items:center!important;
  gap:7px!important;
  padding:8px 12px!important;
  border-radius:999px!important;
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
  box-shadow:0 5px 16px rgba(20,35,60,.06)!important;
}
body.s360-content-theme .s360-chip.success{
  color:#059669!important;
  background:color-mix(in srgb,#10b981 10%,var(--c-card))!important;
  border-color:color-mix(in srgb,#10b981 28%,var(--c-line))!important;
}
body.s360-content-theme .s360-chip.warning{
  color:#b45309!important;
  background:color-mix(in srgb,#f59e0b 10%,var(--c-card))!important;
  border-color:color-mix(in srgb,#f59e0b 28%,var(--c-line))!important;
}

/* Panel de contenido: ahora sí forma parte del tema */
body.s360-content-theme .content-card{
  position:relative!important;
  background:
    radial-gradient(circle at 100% 0%,color-mix(in srgb,var(--c-accent) 8%,transparent),transparent 19rem),
    var(--c-card)!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
  border-radius:26px!important;
  padding:0!important;
  overflow:hidden!important;
  box-shadow:0 18px 45px rgba(20,35,60,.10)!important;
}
body.s360-content-theme .content-card:before{
  content:"";
  display:block;
  height:6px;
  background:linear-gradient(90deg,var(--c-accent),var(--c-accent2));
}
body.s360-content-theme .content-body{
  padding:34px 38px 40px!important;
  color:var(--c-text)!important;
  font-size:1.04rem!important;
  line-height:1.82!important;
}
body.s360-content-theme .content-body h1,
body.s360-content-theme .content-body h2,
body.s360-content-theme .content-body h3,
body.s360-content-theme .content-body h4,
body.s360-content-theme .content-body h5,
body.s360-content-theme .content-body h6{
  color:var(--c-text)!important;
}
body.s360-content-theme .content-body h2{
  font-size:1.65rem!important;
  padding-bottom:.55rem!important;
  border-bottom:1px solid var(--c-line)!important;
}
body.s360-content-theme .content-body p,
body.s360-content-theme .content-body li,
body.s360-content-theme .content-body td,
body.s360-content-theme .content-body th,
body.s360-content-theme .content-body blockquote{
  color:var(--c-text)!important;
}
body.s360-content-theme .content-body strong,
body.s360-content-theme .content-body b{
  color:var(--c-text)!important;
}
body.s360-content-theme .content-body a{
  color:var(--c-accent)!important;
  font-weight:700!important;
}
body.s360-content-theme .content-body blockquote{
  border-left:4px solid var(--c-accent)!important;
  background:var(--c-soft)!important;
  padding:14px 18px!important;
  border-radius:0 14px 14px 0!important;
}
body.s360-content-theme .content-body pre{
  background:color-mix(in srgb,var(--c-accent) 7%,var(--c-card))!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
}
body.s360-content-theme .content-body table{
  border-radius:12px!important;
  overflow:auto!important;
}
body.s360-content-theme .content-body td,
body.s360-content-theme .content-body th{
  border-color:var(--c-line)!important;
}
body.s360-content-theme .content-body img{
  box-shadow:0 12px 30px rgba(20,35,60,.12)!important;
}

/* Ficha lateral */
body.s360-content-theme .s360-card{
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
  border-radius:22px!important;
  box-shadow:0 14px 35px rgba(20,35,60,.08)!important;
}
body.s360-content-theme .s360-card .s360-section-title{
  color:var(--c-text)!important;
  font-weight:850!important;
}
body.s360-content-theme .s360-card .fw-bold{
  color:var(--c-text)!important;
}
body.s360-content-theme .s360-card small{
  color:var(--c-muted)!important;
}

/* Recursos complementarios */
body.s360-content-theme .preview-shell > section.mt-5{
  margin-top:42px!important;
  padding-top:28px!important;
  border-top:1px solid var(--c-line)!important;
}
body.s360-content-theme .preview-shell > section.mt-5 .s360-section-title{
  color:var(--c-text)!important;
  font-size:1.45rem!important;
  font-weight:900!important;
}
body.s360-content-theme .resource-card-premium{
  padding:0!important;
  overflow:hidden!important;
  display:flex!important;
  flex-direction:column!important;
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
  border-radius:20px!important;
  box-shadow:0 12px 30px rgba(20,35,60,.08)!important;
  transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease!important;
}
body.s360-content-theme .resource-card-premium:hover{
  transform:translateY(-4px)!important;
  border-color:color-mix(in srgb,var(--c-accent) 35%,var(--c-line))!important;
  box-shadow:0 20px 42px color-mix(in srgb,var(--c-accent) 12%,transparent)!important;
}
body.s360-content-theme .resource-cover{
  height:155px!important;
  position:relative!important;
  overflow:hidden!important;
  background:linear-gradient(135deg,color-mix(in srgb,var(--c-accent) 75%,#111827),var(--c-accent2))!important;
}
body.s360-content-theme .resource-cover img{
  width:100%!important;
  height:100%!important;
  display:block!important;
  object-fit:cover!important;
  transition:transform .35s ease!important;
}
body.s360-content-theme .resource-card-premium:hover .resource-cover img{
  transform:scale(1.045)!important;
}
body.s360-content-theme .resource-cover:after{
  content:"";
  position:absolute;
  inset:0;
  background:linear-gradient(180deg,rgba(0,0,0,.02) 35%,rgba(0,0,0,.55) 100%);
  pointer-events:none;
}
body.s360-content-theme .resource-cover-empty{
  display:grid!important;
  place-items:center!important;
}
body.s360-content-theme .resource-cover-icon{
  width:62px;height:62px;
  border-radius:18px;
  display:grid;place-items:center;
  background:rgba(255,255,255,.16);
  color:#fff;
  font-size:1.55rem;
  border:1px solid rgba(255,255,255,.2);
}
body.s360-content-theme .resource-cover-type{
  position:absolute!important;
  left:13px!important;
  bottom:12px!important;
  z-index:2!important;
  display:inline-flex!important;
  align-items:center!important;
  gap:6px!important;
  padding:6px 9px!important;
  border-radius:999px!important;
  background:rgba(12,20,35,.72)!important;
  color:#fff!important;
  font-size:.68rem!important;
  font-weight:800!important;
  backdrop-filter:blur(8px)!important;
}
body.s360-content-theme .resource-card-body{
  padding:18px!important;
  display:flex!important;
  flex-direction:column!important;
  flex:1!important;
}
body.s360-content-theme .resource-card-title-row{
  display:flex!important;
  justify-content:space-between!important;
  gap:12px!important;
  align-items:flex-start!important;
}
body.s360-content-theme .resource-card-title-row h3{
  color:var(--c-text)!important;
}
body.s360-content-theme .resource-mini-icon{
  flex:0 0 34px!important;
  width:34px!important;height:34px!important;
  border-radius:10px!important;
  display:grid!important;place-items:center!important;
  background:var(--c-soft)!important;
  color:var(--c-accent)!important;
}
body.s360-content-theme .resource-meta,
body.s360-content-theme .resource-source{
  color:var(--c-muted)!important;
  font-size:.72rem!important;
  display:flex!important;
  align-items:center!important;
  gap:6px!important;
}
body.s360-content-theme .resource-description{
  color:var(--c-muted)!important;
  font-size:.82rem!important;
  line-height:1.55!important;
  margin:14px 0!important;
}
body.s360-content-theme .resource-source{
  margin-top:auto!important;
  padding-top:10px!important;
  border-top:1px solid var(--c-line)!important;
}
body.s360-content-theme .resource-link-premium{
  margin-top:14px!important;
  min-height:40px!important;
  padding:9px 12px!important;
  border-radius:11px!important;
  display:flex!important;
  justify-content:space-between!important;
  align-items:center!important;
  text-decoration:none!important;
  color:#fff!important;
  background:linear-gradient(135deg,var(--c-accent),var(--c-accent2))!important;
  font-size:.78rem!important;
  font-weight:800!important;
  box-shadow:0 8px 18px color-mix(in srgb,var(--c-accent) 18%,transparent)!important;
}
body.s360-content-theme .resource-link-premium:hover{
  color:#fff!important;
  filter:brightness(1.05)!important;
}
body.s360-content-theme.s360-content-dark .resource-card-premium,
body.s360-content-theme.s360-content-dark .s360-card{
  background:#172236!important;
  color:#edf2f8!important;
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .resource-description,
body.s360-content-theme.s360-content-dark .resource-meta,
body.s360-content-theme.s360-content-dark .resource-source,
body.s360-content-theme.s360-content-dark .preview-shell > section.mt-5 .small{
  color:#aeb9c9!important;
}
body.s360-content-theme.s360-content-dark .resource-source{
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .resource-mini-icon{
  background:color-mix(in srgb,var(--c-accent) 15%,#111827)!important;
  color:#fff!important;
}
body.s360-content-theme.s360-content-dark .content-card{
  background:
    radial-gradient(circle at 100% 0%,color-mix(in srgb,var(--c-accent) 12%,transparent),transparent 19rem),
    #172236!important;
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .content-body{
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .content-body h1,
body.s360-content-theme.s360-content-dark .content-body h2,
body.s360-content-theme.s360-content-dark .content-body h3,
body.s360-content-theme.s360-content-dark .content-body h4,
body.s360-content-theme.s360-content-dark .content-body h5,
body.s360-content-theme.s360-content-dark .content-body h6,
body.s360-content-theme.s360-content-dark .content-body p,
body.s360-content-theme.s360-content-dark .content-body li,
body.s360-content-theme.s360-content-dark .content-body td,
body.s360-content-theme.s360-content-dark .content-body th,
body.s360-content-theme.s360-content-dark .content-body blockquote,
body.s360-content-theme.s360-content-dark .content-body strong,
body.s360-content-theme.s360-content-dark .content-body b{
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .content-body h2{
  border-color:#2b374b!important;
}
body.s360-content-theme.s360-content-dark .content-body blockquote{
  background:color-mix(in srgb,var(--c-accent) 12%,#111827)!important;
}
body.s360-content-theme.s360-content-dark .content-body pre{
  background:#111827!important;
  border-color:#334158!important;
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .content-body td,
body.s360-content-theme.s360-content-dark .content-body th{
  border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .preview-shell > section.mt-5{
  border-color:#2b374b!important;
}
@media(max-width:767px){
  body.s360-content-theme .preview-shell{padding:18px 12px 80px!important}
  body.s360-content-theme .preview-hero{padding:30px 24px!important;min-height:215px!important}
  body.s360-content-theme .preview-hero:after{right:22px;top:24px}
  body.s360-content-theme .content-body{padding:25px 20px 30px!important}
  body.s360-content-theme .resource-cover{height:145px!important}
}
</style>



<style id="studia360-bloques-predeterminados-final">
/* Bloques predeterminados: una sola identidad visual en editor, vista previa y estudiante. */
.info-box,.important-box,.example-box,.exercise-box,.remember-box{
  display:block!important;box-sizing:border-box!important;
  padding:16px 18px!important;margin:20px 0!important;border-radius:14px!important;
}

/* ADMIN / EDITOR / VISTA PREVIA */
body.s360-content-theme .info-box,
body.s360-content-theme .important-box,
body.s360-content-theme .example-box,
body.s360-content-theme .exercise-box,
body.s360-content-theme .remember-box{
  color:var(--c-text,var(--s360-text,#172033))!important;
  border-top:1px solid transparent!important;border-right:1px solid transparent!important;border-bottom:1px solid transparent!important;
}
body.s360-content-theme .info-box{background:#eff6ff!important;border-left:5px solid #2563eb!important;border-color:#d9e9ff #d9e9ff #d9e9ff #2563eb!important}
body.s360-content-theme .important-box{background:#fff1f2!important;border-left:5px solid #dc3545!important;border-color:#ffdadd #ffdadd #ffdadd #dc3545!important}
body.s360-content-theme .example-box{background:#ecfdf3!important;border-left:5px solid #198754!important;border-color:#d4f3df #d4f3df #d4f3df #198754!important}
body.s360-content-theme .exercise-box{background:#fff9e6!important;border-left:5px solid #e0a800!important;border-color:#f8e8b0 #f8e8b0 #f8e8b0 #e0a800!important}
body.s360-content-theme .remember-box{background:#f5f0ff!important;border-left:5px solid #7c3aed!important;border-color:#e7ddff #e7ddff #e7ddff #7c3aed!important}
body.s360-content-theme .info-box .bloque-label,body.s360-content-theme .important-box .bloque-label,body.s360-content-theme .example-box .bloque-label,body.s360-content-theme .exercise-box .bloque-label,body.s360-content-theme .remember-box .bloque-label,
body.s360-content-theme .info-box p,body.s360-content-theme .important-box p,body.s360-content-theme .example-box p,body.s360-content-theme .exercise-box p,body.s360-content-theme .remember-box p,
body.s360-content-theme .info-box li,body.s360-content-theme .important-box li,body.s360-content-theme .example-box li,body.s360-content-theme .exercise-box li,body.s360-content-theme .remember-box li,
body.s360-content-theme .info-box strong,body.s360-content-theme .important-box strong,body.s360-content-theme .example-box strong,body.s360-content-theme .exercise-box strong,body.s360-content-theme .remember-box strong,
body.s360-content-theme .info-box b,body.s360-content-theme .important-box b,body.s360-content-theme .example-box b,body.s360-content-theme .exercise-box b,body.s360-content-theme .remember-box b{
  color:var(--c-text,var(--s360-text,#172033))!important;
}
body.s360-content-theme .info-box a,body.s360-content-theme .important-box a,body.s360-content-theme .example-box a,body.s360-content-theme .exercise-box a,body.s360-content-theme .remember-box a{color:var(--c-accent,var(--s360-accent,#2563eb))!important}

body.s360-content-theme.s360-content-dark .info-box,body.s360-content-theme.s360-content-dark .important-box,body.s360-content-theme.s360-content-dark .example-box,body.s360-content-theme.s360-content-dark .exercise-box,body.s360-content-theme.s360-content-dark .remember-box{color:#edf2f8!important}
body.s360-content-theme.s360-content-dark .info-box{background:#292348!important;border-color:#453c6b #453c6b #453c6b #8b5cf6!important}
body.s360-content-theme.s360-content-dark .important-box{background:#43262d!important;border-color:#68404a #68404a #68404a #ef6673!important}
body.s360-content-theme.s360-content-dark .example-box{background:#193a2e!important;border-color:#2e5b49 #2e5b49 #2e5b49 #34c58b!important}
body.s360-content-theme.s360-content-dark .exercise-box{background:#463918!important;border-color:#675521 #675521 #675521 #f4c64e!important}
body.s360-content-theme.s360-content-dark .remember-box{background:#32254f!important;border-color:#514070 #514070 #514070 #a78bfa!important}
body.s360-content-theme.s360-content-dark .info-box .bloque-label,body.s360-content-theme.s360-content-dark .important-box .bloque-label,body.s360-content-theme.s360-content-dark .example-box .bloque-label,body.s360-content-theme.s360-content-dark .exercise-box .bloque-label,body.s360-content-theme.s360-content-dark .remember-box .bloque-label,
body.s360-content-theme.s360-content-dark .info-box p,body.s360-content-theme.s360-content-dark .important-box p,body.s360-content-theme.s360-content-dark .example-box p,body.s360-content-theme.s360-content-dark .exercise-box p,body.s360-content-theme.s360-content-dark .remember-box p,
body.s360-content-theme.s360-content-dark .info-box li,body.s360-content-theme.s360-content-dark .important-box li,body.s360-content-theme.s360-content-dark .example-box li,body.s360-content-theme.s360-content-dark .exercise-box li,body.s360-content-theme.s360-content-dark .remember-box li,
body.s360-content-theme.s360-content-dark .info-box strong,body.s360-content-theme.s360-content-dark .important-box strong,body.s360-content-theme.s360-content-dark .example-box strong,body.s360-content-theme.s360-content-dark .exercise-box strong,body.s360-content-theme.s360-content-dark .remember-box strong,
body.s360-content-theme.s360-content-dark .info-box b,body.s360-content-theme.s360-content-dark .important-box b,body.s360-content-theme.s360-content-dark .example-box b,body.s360-content-theme.s360-content-dark .exercise-box b,body.s360-content-theme.s360-content-dark .remember-box b{color:#edf2f8!important}
body.s360-content-theme.s360-content-dark .info-box a,body.s360-content-theme.s360-content-dark .important-box a,body.s360-content-theme.s360-content-dark .example-box a,body.s360-content-theme.s360-content-dark .exercise-box a,body.s360-content-theme.s360-content-dark .remember-box a{color:#c4b5fd!important}

/* ESTUDIANTE */
body.sd-page .info-box,body.sd-page .important-box,body.sd-page .example-box,body.sd-page .exercise-box,body.sd-page .remember-box{
  color:var(--sd-text,#172033)!important;border:1px solid transparent!important;
}
body.sd-page .info-box{background:#eff6ff!important;border-left:5px solid #2563eb!important;border-color:#d9e9ff #d9e9ff #d9e9ff #2563eb!important}
body.sd-page .important-box{background:#fff1f2!important;border-left:5px solid #dc3545!important;border-color:#ffdadd #ffdadd #ffdadd #dc3545!important}
body.sd-page .example-box{background:#ecfdf3!important;border-left:5px solid #198754!important;border-color:#d4f3df #d4f3df #d4f3df #198754!important}
body.sd-page .exercise-box{background:#fff9e6!important;border-left:5px solid #e0a800!important;border-color:#f8e8b0 #f8e8b0 #f8e8b0 #e0a800!important}
body.sd-page .remember-box{background:#f5f0ff!important;border-left:5px solid #7c3aed!important;border-color:#e7ddff #e7ddff #e7ddff #7c3aed!important}
body.sd-page .info-box .bloque-label,body.sd-page .important-box .bloque-label,body.sd-page .example-box .bloque-label,body.sd-page .exercise-box .bloque-label,body.sd-page .remember-box .bloque-label,
body.sd-page .info-box p,body.sd-page .important-box p,body.sd-page .example-box p,body.sd-page .exercise-box p,body.sd-page .remember-box p,
body.sd-page .info-box li,body.sd-page .important-box li,body.sd-page .example-box li,body.sd-page .exercise-box li,body.sd-page .remember-box li,
body.sd-page .info-box strong,body.sd-page .important-box strong,body.sd-page .example-box strong,body.sd-page .exercise-box strong,body.sd-page .remember-box strong,
body.sd-page .info-box b,body.sd-page .important-box b,body.sd-page .example-box b,body.sd-page .exercise-box b,body.sd-page .remember-box b{color:var(--sd-text,#172033)!important}
body.sd-page .info-box a,body.sd-page .important-box a,body.sd-page .example-box a,body.sd-page .exercise-box a,body.sd-page .remember-box a{color:var(--sd-accent,#2563eb)!important}
body.sd-page.sd-dark .info-box,body.sd-page.sd-dark .important-box,body.sd-page.sd-dark .example-box,body.sd-page.sd-dark .exercise-box,body.sd-page.sd-dark .remember-box{color:#edf2f7!important}
body.sd-page.sd-dark .info-box{background:#292348!important;border-color:#453c6b #453c6b #453c6b #8b5cf6!important}
body.sd-page.sd-dark .important-box{background:#43262d!important;border-color:#68404a #68404a #68404a #ef6673!important}
body.sd-page.sd-dark .example-box{background:#193a2e!important;border-color:#2e5b49 #2e5b49 #2e5b49 #34c58b!important}
body.sd-page.sd-dark .exercise-box{background:#463918!important;border-color:#675521 #675521 #675521 #f4c64e!important}
body.sd-page.sd-dark .remember-box{background:#32254f!important;border-color:#514070 #514070 #514070 #a78bfa!important}
body.sd-page.sd-dark .info-box .bloque-label,body.sd-page.sd-dark .important-box .bloque-label,body.sd-page.sd-dark .example-box .bloque-label,body.sd-page.sd-dark .exercise-box .bloque-label,body.sd-page.sd-dark .remember-box .bloque-label,
body.sd-page.sd-dark .info-box p,body.sd-page.sd-dark .important-box p,body.sd-page.sd-dark .example-box p,body.sd-page.sd-dark .exercise-box p,body.sd-page.sd-dark .remember-box p,
body.sd-page.sd-dark .info-box li,body.sd-page.sd-dark .important-box li,body.sd-page.sd-dark .example-box li,body.sd-page.sd-dark .exercise-box li,body.sd-page.sd-dark .remember-box li,
body.sd-page.sd-dark .info-box strong,body.sd-page.sd-dark .important-box strong,body.sd-page.sd-dark .example-box strong,body.sd-page.sd-dark .exercise-box strong,body.sd-page.sd-dark .remember-box strong,
body.sd-page.sd-dark .info-box b,body.sd-page.sd-dark .important-box b,body.sd-page.sd-dark .example-box b,body.sd-page.sd-dark .exercise-box b,body.sd-page.sd-dark .remember-box b{color:#edf2f7!important}
body.sd-page.sd-dark .info-box a,body.sd-page.sd-dark .important-box a,body.sd-page.sd-dark .example-box a,body.sd-page.sd-dark .exercise-box a,body.sd-page.sd-dark .remember-box a{color:#c4b5fd!important}

/* Dentro del editor, los bloques conservan la misma apariencia mientras se editan. */
body.s360-content-theme .note-editable .info-box,body.s360-content-theme .note-editable .important-box,body.s360-content-theme .note-editable .example-box,body.s360-content-theme .note-editable .exercise-box,body.s360-content-theme .note-editable .remember-box{font-size:inherit;line-height:inherit}

/* Encabezado morado del editor: ningún botón queda con texto invisible. */
body.s360-content-theme main .page-wrap > .d-flex.flex-column.flex-lg-row.justify-content-between .btn-outline-secondary,
body.s360-content-theme main .page-wrap > .d-flex.flex-column.flex-lg-row.justify-content-between .btn-light{
  color:#fff!important;background:rgba(255,255,255,.13)!important;border-color:rgba(255,255,255,.48)!important;
}
body.s360-content-theme main .page-wrap > .d-flex.flex-column.flex-lg-row.justify-content-between .btn-outline-secondary:hover,
body.s360-content-theme main .page-wrap > .d-flex.flex-column.flex-lg-row.justify-content-between .btn-light:hover{color:#fff!important;background:rgba(255,255,255,.22)!important;border-color:#fff!important}
</style>

<style id="studia360-preview-dark-text-final">
/* ==========================================================
   CORRECCIÓN FINAL — TEXTO DEL CONTENIDO EN MODO OSCURO
   Algunos contenidos vienen con color definido directamente
   en <span>, <div>, <em>, etc. El color del padre no los
   cambia, por eso se fuerza el contraste en todos los hijos.
   ========================================================== */
body.s360-content-theme.s360-content-dark .content-body *{
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .content-body a{
  color:var(--c-accent)!important;
}
body.s360-content-theme.s360-content-dark .content-body mark,
body.s360-content-theme.s360-content-dark .content-body .highlight{
  color:#172033!important;
}
body.s360-content-theme.s360-content-dark .content-body code{
  color:#e9d5ff!important;
  background:#111827!important;
}
body.s360-content-theme.s360-content-dark .content-body hr{
  border-color:#3a465a!important;
}
body.s360-content-theme.s360-content-dark .content-body figcaption{
  color:#aeb9c9!important;
}
</style>

</head><body class="s360-admin">
<nav class="s360-topbar"><div class="container-fluid px-3 px-lg-4 py-2 d-flex justify-content-between align-items-center"><a class="s360-brand d-flex align-items-center gap-2" href="<?=e($urlTemas)?>"><span class="s360-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>Studia360 <span class="badge text-bg-warning">Vista previa</span></a><div class="d-flex gap-2"><a class="btn btn-light border s360-btn" href="<?=e($urlInfo)?>"><i class="bi bi-pencil me-1"></i>Editar tema</a><a class="btn btn-primary s360-btn" href="<?=e($urlEditor)?>">Contenido</a></div></div></nav>
<main class="preview-shell"><section class="preview-hero mb-4"><div class="s360-kicker">Vista previa para administración</div><h1 class="h2 fw-bold mt-2 mb-2"><?=e($tema['tema'])?></h1><p class="mb-0 opacity-75"><?=e($tema['materia'])?> · <?=e($tema['grado'])?>°<?php if(!empty($tema['unidad'])):?> · <?=e($tema['unidad'])?><?php endif;?><?php if($tema['descripcion']):?> · <?=e($tema['descripcion'])?><?php endif;?></p></section>
<?php if($contenido):?><div class="mb-3"><span class="s360-chip <?=$contenido['estado']==='Publicado'?'success':'warning'?>"><i class="bi <?=$contenido['estado']==='Publicado'?'bi-check-circle':'bi-pencil-square'?>"></i><?=e($contenido['estado'])?></span> <span class="small text-secondary ms-2">Última actualización: <?=e(date('d/m/Y H:i',strtotime($contenido['fecha_actualizacion'])))?></span></div><?php endif;?>
<div class="row g-4"><div class="col-lg-8"><section class="content-card"><?php if($html):?><article class="content-body"><?=$html?></article><?php else:?><div class="empty-preview"><div class="s360-iconbox mx-auto mb-3"><i class="bi bi-file-earmark-text"></i></div><h2 class="h5 fw-bold">Este tema todavía no tiene contenido</h2><p class="s360-muted mb-0">Puedes construir la lección desde el editor de contenido.</p></div><?php endif;?></section></div>
<aside class="col-lg-4"><section class="s360-card p-4 mb-4"><div class="s360-section-title mb-3">Ficha del tema</div><div class="mb-3"><small class="s360-muted">Materia</small><div class="fw-bold"><?=e($tema['materia'])?></div></div><div class="mb-3"><small class="s360-muted">Grado</small><div class="fw-bold"><?=e($tema['grado'])?>°</div></div><?php if(!empty($tema['unidad'])):?><div class="mb-3"><small class="s360-muted">Unidad temática</small><div class="fw-bold"><?=e($tema['unidad'])?></div></div><?php endif;?><div><small class="s360-muted">Recursos</small><div class="fw-bold"><?=count($recursos)?></div></div></section></aside></div>
<?php if($recursos):?><section class="mt-5"><div class="mb-3"><div class="s360-section-title">Recursos complementarios</div><div class="small s360-muted">Así se verán los recursos asociados al tema.</div></div><div class="row g-4"><?php foreach($recursos as $r):$img=assetUrl((string)($r['imagen']??''));if(!$img&&strtolower((string)$r['tipo'])==='video'&&($yt=youtubeId((string)$r['url'])))$img='https://img.youtube.com/vi/'.rawurlencode($yt).'/hqdefault.jpg';?><div class="col-md-6 col-xl-4"><article class="resource resource-card-premium"><?php if($img):?><div class="resource-cover"><img src="<?=e($img)?>" alt="<?=e($r['titulo'])?>" loading="lazy"><span class="resource-cover-type"><i class="bi <?=e(recursoIcon((string)$r['tipo']))?>"></i><?=e(ucfirst((string)$r['tipo']))?></span></div><?php else:?><div class="resource-cover resource-cover-empty"><div class="resource-cover-icon"><i class="bi <?=e(recursoIcon((string)$r['tipo']))?>"></i></div><span class="resource-cover-type"><i class="bi <?=e(recursoIcon((string)$r['tipo']))?>"></i><?=e(ucfirst((string)$r['tipo']))?></span></div><?php endif;?><div class="resource-card-body"><div class="resource-card-title-row"><div><h3 class="h5 fw-bold mb-1"><?=e($r['titulo'])?></h3><?php if(!empty($r['autor'])):?><div class="resource-meta"><i class="bi bi-person"></i><?=e($r['autor'])?></div><?php endif;?></div><div class="resource-mini-icon"><i class="bi <?=e(recursoIcon((string)$r['tipo']))?>"></i></div></div><?php if($r['descripcion']):?><p class="resource-description"><?=e($r['descripcion'])?></p><?php endif;?><?php if(!empty($r['fuente'])):?><div class="resource-source"><i class="bi bi-globe2"></i><?=e($r['fuente'])?></div><?php endif;?><a class="resource-link-premium" href="<?=e($r['url'])?>" target="_blank" rel="noopener noreferrer"><span>Abrir recurso</span><i class="bi bi-arrow-up-right"></i></a></div></article></div><?php endforeach;?></div></section><?php endif;?>
</main><!-- Studia360 Admin: personalizador global -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza el panel</div><div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro en todo el administrador.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" data-theme="purple" type="button"><div class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Studia360</small></button>
<button class="adm-theme-option" data-theme="blue" type="button"><div class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" data-theme="orange" type="button"><div class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" data-theme="green" type="button"><div class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
</div><button id="admModeBtn" class="adm-mode-btn" type="button"></button></div>

<script id="studia360-theme-controller">
(function(){
'use strict';
var body=document.body, key='studia360_theme';
var themes={
  blue:{cls:''},
  orange:{cls:'s360-accent-orange'},
  purple:{cls:'s360-accent-purple'},
  green:{cls:'s360-accent-green'}
};
var state={theme:'blue',mode:'light'};
try{
  var raw=localStorage.getItem(key);
  if(raw){
    var p=JSON.parse(raw);
    if(p&&typeof p==='object'){
      if(themes[p.theme]) state.theme=p.theme;
      if(p.mode==='dark'||p.mode==='light') state.mode=p.mode;
    }
  }
}catch(e){}

function persist(){
  try{localStorage.setItem(key,JSON.stringify(state));}catch(e){}
}
function apply(){
  Object.keys(themes).forEach(function(k){
    if(themes[k].cls) body.classList.remove(themes[k].cls);
  });
  body.classList.add('s360-content-theme');
  if(themes[state.theme].cls) body.classList.add(themes[state.theme].cls);
  body.classList.toggle('s360-content-dark',state.mode==='dark');
  body.classList.toggle('adm-dark',state.mode==='dark');

  document.querySelectorAll('.adm-theme-option').forEach(function(btn){
    btn.classList.toggle('active',btn.getAttribute('data-theme')===state.theme);
    btn.setAttribute('aria-pressed',btn.getAttribute('data-theme')===state.theme?'true':'false');
  });
  var mode=document.getElementById('admModeBtn');
  if(mode) mode.innerHTML=state.mode==='dark'
    ? '<i class="bi bi-sun me-2"></i>Modo claro'
    : '<i class="bi bi-moon-stars me-2"></i>Modo oscuro';
}
window.admSetTheme=function(theme){
  if(!themes[theme]) return false;
  state.theme=theme;
  persist();
  apply();
  return false;
};
window.admToggleMode=function(){
  state.mode=state.mode==='dark'?'light':'dark';
  persist();
  apply();
  return false;
};

var toggle=document.getElementById('admThemeToggle');
var panel=document.getElementById('admThemePanel');
if(toggle && panel){
  toggle.addEventListener('click',function(e){
    e.preventDefault(); e.stopPropagation();
    panel.classList.toggle('open');
  });
  panel.addEventListener('click',function(e){
    e.stopPropagation();
    var option=e.target.closest('.adm-theme-option');
    if(option){
      e.preventDefault();
      window.admSetTheme(option.getAttribute('data-theme'));
      return;
    }
    var mode=e.target.closest('#admModeBtn');
    if(mode){
      e.preventDefault();
      window.admToggleMode();
    }
  });
  document.addEventListener('click',function(e){
    if(!panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove('open');
  });
}
apply();
})();
</script>

</body></html>

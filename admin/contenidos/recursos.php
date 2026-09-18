<?php
require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

$idTema = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idTema || $idTema <= 0) redireccionarDashboardUsuario();

if (empty($_SESSION['csrf_recursos'])) $_SESSION['csrf_recursos'] = bin2hex(random_bytes(32));

$tema = null; $recursos = []; $errores = []; $mensajes = [];

try {
    $stmt = $conexion->prepare(
        'SELECT t.id_tema,t.nombre,t.grado,m.nombre AS materia
         FROM temas t INNER JOIN materias m ON m.id_materia=t.id_materia
         WHERE t.id_tema=? LIMIT 1'
    );
    $stmt->execute([$idTema]); $tema = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tema) redireccionarDashboardUsuario();

    $stmt = $conexion->prepare('SELECT * FROM recursos WHERE id_tema=? ORDER BY id_recurso DESC');
    $stmt->execute([$idTema]); $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['ok'])) $mensajes[] = 'El recurso se guardó correctamente.';
    if (isset($_GET['deleted'])) $mensajes[] = 'El recurso se eliminó correctamente.';
} catch (PDOException $e) { $errores[] = 'No fue posible cargar los recursos del tema.'; }

$tipoLabels = [
    'video'=>['Video','bi-play-circle-fill'], 'pdf'=>['PDF','bi-file-earmark-pdf-fill'],
    'app'=>['Actividad','bi-controller'], 'juego'=>['Juego','bi-controller'],
    'simulador'=>['Simulador','bi-window-stack'], 'presentacion'=>['Presentación','bi-easel-fill'],
    'articulo'=>['Artículo','bi-file-text-fill'], 'blog'=>['Blog','bi-journal-text']
];

$urlGuardar = urlAplicacion('/admin/contenidos/guardar_recurso.php');
$urlEditarTema = urlAplicacion('/admin/contenidos/editar_tema.php?id='.$idTema);
$urlDashboard = urlAplicacion('/admin/dashboard.php');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recursos | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#172033}.navbar{box-shadow:0 3px 16px rgba(0,0,0,.12)}.page{max-width:1500px;margin:auto}.hero{background:linear-gradient(135deg,#0d6efd,#084298);color:#fff;border-radius:20px;padding:28px;box-shadow:0 15px 35px rgba(13,110,253,.16)}.card-soft{border:0;border-radius:20px;box-shadow:0 10px 30px rgba(20,35,60,.08)}.resource{height:100%;background:#fff;border:1px solid #e4e9f0;border-radius:18px;padding:18px}.icon{width:48px;height:48px;border-radius:14px;background:#eaf2ff;color:#0d6efd;display:flex;align-items:center;justify-content:center;font-size:21px;flex:0 0 auto}.url{background:#f7f9fc;border-radius:10px;padding:9px;font-size:.82rem;word-break:break-all}.badge-type{background:#eaf2ff;color:#0d6efd}.local{background:#dff7e8;color:#13733b}
</style><link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">

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


<style id="studia360-recursos-final-fix">
/* ==========================================================
   Corrección final de contraste + modo oscuro para Recursos
   ========================================================== */
body.s360-content-theme{
  --c-accent:#2563eb;
  --c-accent2:#4f46e5;
  --c-soft:#eff6ff;
  --c-bg:#f6f8fc;
  --c-card:#ffffff;
  --c-text:#1f2937;
  --c-muted:#667085;
  --c-line:#dfe6ef;
  --c-input:#ffffff;
}

/* Todo el contenido de la pantalla debe heredar el contraste del tema. */
body.s360-content-theme .page,
body.s360-content-theme .page *{
  box-sizing:border-box;
}
body.s360-content-theme .page{
  color:var(--c-text)!important;
}
body.s360-content-theme .form-label,
body.s360-content-theme label,
body.s360-content-theme h1,
body.s360-content-theme h2,
body.s360-content-theme h3,
body.s360-content-theme h4,
body.s360-content-theme h5,
body.s360-content-theme h6{
  color:var(--c-text)!important;
}
body.s360-content-theme .text-muted,
body.s360-content-theme .form-text,
body.s360-content-theme .text-secondary{
  color:var(--c-muted)!important;
}

/* Inputs normales y selector de archivos. */
body.s360-content-theme .form-control,
body.s360-content-theme .form-select,
body.s360-content-theme textarea{
  background:var(--c-input)!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
  box-shadow:none!important;
}
body.s360-content-theme .form-control::placeholder,
body.s360-content-theme textarea::placeholder{
  color:color-mix(in srgb,var(--c-muted) 82%,transparent)!important;
  opacity:1!important;
}
body.s360-content-theme input[type="file"]{
  color:var(--c-text)!important;
  background:var(--c-input)!important;
}
body.s360-content-theme input[type="file"]::file-selector-button{
  color:var(--c-text)!important;
  background:var(--c-soft)!important;
  border:0!important;
  border-right:1px solid var(--c-line)!important;
  margin-right:12px!important;
  padding:.52rem .85rem!important;
  font-weight:700!important;
}
body.s360-content-theme input[type="file"]::-webkit-file-upload-button{
  color:var(--c-text)!important;
  background:var(--c-soft)!important;
  border:0!important;
  border-right:1px solid var(--c-line)!important;
  margin-right:12px!important;
  padding:.52rem .85rem!important;
  font-weight:700!important;
}
body.s360-content-theme .form-control:focus,
body.s360-content-theme .form-select:focus,
body.s360-content-theme textarea:focus{
  color:var(--c-text)!important;
  background:var(--c-input)!important;
  border-color:color-mix(in srgb,var(--c-accent) 45%,var(--c-line))!important;
  box-shadow:0 0 0 4px color-mix(in srgb,var(--c-accent) 10%,transparent)!important;
}

/* Tarjetas y recuadros */
body.s360-content-theme .card,
body.s360-content-theme .card-soft,
body.s360-content-theme .resource{
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .resource h5{
  color:var(--c-text)!important;
}
body.s360-content-theme .icon,
body.s360-content-theme .badge-type{
  background:var(--c-soft)!important;
  color:var(--c-accent)!important;
}
body.s360-content-theme .url{
  background:color-mix(in srgb,var(--c-soft) 55%,var(--c-card))!important;
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
}
body.s360-content-theme .url i{
  color:var(--c-accent)!important;
}
body.s360-content-theme .local{
  background:color-mix(in srgb,#10b981 14%,var(--c-card))!important;
  color:#059669!important;
  border:1px solid color-mix(in srgb,#10b981 25%,var(--c-line))!important;
}

/* Botones: que el texto nunca quede oculto al cambiar de color. */
body.s360-content-theme .btn-primary{
  color:#fff!important;
  background:linear-gradient(135deg,var(--c-accent),var(--c-accent2))!important;
  border-color:transparent!important;
}
body.s360-content-theme .btn-outline-primary{
  color:var(--c-accent)!important;
  background:var(--c-card)!important;
  border-color:color-mix(in srgb,var(--c-accent) 34%,var(--c-line))!important;
}
body.s360-content-theme .btn-outline-primary:hover{
  color:#fff!important;
  background:var(--c-accent)!important;
  border-color:var(--c-accent)!important;
}
body.s360-content-theme .btn-outline-secondary{
  color:var(--c-text)!important;
  background:var(--c-card)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme .btn-outline-secondary:hover{
  color:var(--c-accent)!important;
  background:var(--c-soft)!important;
  border-color:color-mix(in srgb,var(--c-accent) 34%,var(--c-line))!important;
}

/* Alertas de Bootstrap */
body.s360-content-theme .alert{
  color:var(--c-text)!important;
  border:1px solid var(--c-line)!important;
}
body.s360-content-theme .alert-success{
  background:color-mix(in srgb,#10b981 10%,var(--c-card))!important;
}
body.s360-content-theme .alert-danger{
  background:color-mix(in srgb,#ef4444 9%,var(--c-card))!important;
}

/* Variantes */
body.s360-content-theme.s360-accent-orange{--c-accent:#f97316;--c-accent2:#ea580c;--c-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--c-accent:#8b5cf6;--c-accent2:#7c3aed;--c-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--c-accent:#10b981;--c-accent2:#059669;--c-soft:#ecfdf5}

/* Modo oscuro: no quedan cajas blancas ni letras oscuras invisibles. */
body.s360-content-theme.s360-content-dark{
  --c-bg:#0d1424;
  --c-card:#172236;
  --c-text:#edf2f8;
  --c-muted:#aeb9c9;
  --c-line:#2b374b;
  --c-input:#111827;
  --c-soft:#202c41;
  background:
    radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--c-accent) 13%,transparent),transparent 25rem),
    var(--c-bg)!important;
  color:var(--c-text)!important;
}
body.s360-content-theme.s360-content-dark .card,
body.s360-content-theme.s360-content-dark .card-soft,
body.s360-content-theme.s360-content-dark .resource,
body.s360-content-theme.s360-content-dark .hero{
  background:var(--c-card)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark .hero{
  background:linear-gradient(135deg,var(--c-accent),var(--c-accent2))!important;
  border:0!important;
}
body.s360-content-theme.s360-content-dark .page,
body.s360-content-theme.s360-content-dark .page *,
body.s360-content-theme.s360-content-dark .form-label,
body.s360-content-theme.s360-content-dark label,
body.s360-content-theme.s360-content-dark h1,
body.s360-content-theme.s360-content-dark h2,
body.s360-content-theme.s360-content-dark h3,
body.s360-content-theme.s360-content-dark h4,
body.s360-content-theme.s360-content-dark h5,
body.s360-content-theme.s360-content-dark h6{
  color:var(--c-text);
}
body.s360-content-theme.s360-content-dark .text-muted,
body.s360-content-theme.s360-content-dark .form-text,
body.s360-content-theme.s360-content-dark .text-secondary{
  color:var(--c-muted)!important;
}
body.s360-content-theme.s360-content-dark .form-control,
body.s360-content-theme.s360-content-dark .form-select,
body.s360-content-theme.s360-content-dark textarea,
body.s360-content-theme.s360-content-dark input[type="file"]{
  background:var(--c-input)!important;
  color:var(--c-text)!important;
  border-color:var(--c-line)!important;
}
body.s360-content-theme.s360-content-dark input[type="file"]::file-selector-button,
body.s360-content-theme.s360-content-dark input[type="file"]::-webkit-file-upload-button{
  background:#202c41!important;
  color:#edf2f8!important;
  border-right-color:#3a4860!important;
}
body.s360-content-theme.s360-content-dark .form-select option{
  background:#111827!important;
  color:#edf2f8!important;
}
body.s360-content-theme.s360-content-dark .url{
  background:#111827!important;
  color:#edf2f8!important;
  border-color:#334158!important;
}
body.s360-content-theme.s360-content-dark .url i{
  color:#b9a2ff!important;
}
body.s360-content-theme.s360-content-dark .icon,
body.s360-content-theme.s360-content-dark .badge-type{
  background:color-mix(in srgb,var(--c-accent) 16%,#111827)!important;
  color:#fff!important;
}
body.s360-content-theme.s360-content-dark .local{
  background:#123c32!important;
  color:#6ee7b7!important;
  border-color:#1d6b56!important;
}
body.s360-content-theme.s360-content-dark .btn-outline-primary{
  color:#c9b8ff!important;
  background:var(--c-card)!important;
  border-color:#5a4b86!important;
}
body.s360-content-theme.s360-content-dark .btn-outline-primary:hover{
  color:#fff!important;
  background:var(--c-accent)!important;
  border-color:var(--c-accent)!important;
}
body.s360-content-theme.s360-content-dark .btn-outline-secondary{
  color:#dce4ee!important;
  background:var(--c-card)!important;
  border-color:#3a4860!important;
}
body.s360-content-theme.s360-content-dark .btn-outline-secondary:hover{
  color:#fff!important;
  background:#202c41!important;
}
body.s360-content-theme.s360-content-dark .alert-success{
  background:#123c32!important;
  color:#d7fbe9!important;
  border-color:#1d6b56!important;
}
body.s360-content-theme.s360-content-dark .alert-danger{
  background:#3a1c27!important;
  color:#ffd9e1!important;
  border-color:#6b3040!important;
}

/* Selector de archivo: el nombre del archivo queda siempre visible. */
body.s360-content-theme input[type="file"]{
  overflow:hidden!important;
}
body.s360-content-theme input[type="file"]::-webkit-file-upload-button{
  cursor:pointer;
}
</style>

</head><body class="s360-admin">
<nav class="navbar navbar-dark bg-dark py-3"><div class="container-fluid px-3 px-lg-4"><a class="navbar-brand fw-bold" href="<?=htmlspecialchars($urlDashboard)?>"><i class="bi bi-mortarboard-fill"></i> Studia360</a><div class="d-flex align-items-center gap-2 text-white"><span class="d-none d-md-inline"><i class="bi bi-shield-check"></i> Administrador</span><a class="btn btn-outline-light btn-sm" href="<?=htmlspecialchars(urlAplicacion('/cerrar_sesion.php'))?>"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></div></div></nav>
<main class="container-fluid px-3 px-lg-5 py-4"><div class="page">
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"><div><div class="text-primary fw-bold small">RECURSOS COMPLEMENTARIOS</div><h1 class="fw-bold mb-1">Recursos de <?=htmlspecialchars($tema['nombre'])?></h1><div class="text-muted"><?=htmlspecialchars($tema['materia'])?> · <?=htmlspecialchars($tema['grado'])?>°</div></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?=htmlspecialchars($urlEditarTema)?>"><i class="bi bi-arrow-left"></i> Volver al editor</a></div></div>
<?php foreach($mensajes as $m):?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill"></i> <?=htmlspecialchars($m)?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach;?>
<?php foreach($errores as $e):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=htmlspecialchars($e)?></div><?php endforeach;?>
<div class="hero mb-4"><div class="row align-items-center g-3"><div class="col-lg-8"><div class="small fw-bold text-uppercase opacity-75">Contenido dinámico</div><h2 class="fw-bold">Haz que el tema sea más interactivo</h2><p class="mb-0 opacity-75">Añade videos, PDFs, actividades, juegos, simuladores, presentaciones y enlaces externos. Los PDFs remotos se intentan guardar automáticamente en el servidor local.</p></div><div class="col-lg-4 text-lg-end"><span class="fs-5"><i class="bi bi-collection-play"></i> <?=count($recursos)?> recurso(s)</span></div></div></div>
<div class="card card-soft mb-4"><div class="card-body p-4"><h4 class="fw-bold mb-1"><i class="bi bi-plus-circle text-primary"></i> Añadir recurso</h4><p class="text-muted mb-4">Los campos marcados con * son obligatorios.</p>
<form method="post" action="<?=htmlspecialchars($urlGuardar)?>" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_recursos'])?>"><input type="hidden" name="id_tema" value="<?=$idTema?>">
<div class="col-lg-6"><label class="form-label fw-semibold">Título *</label><input name="titulo" class="form-control" maxlength="200" required placeholder="Ej. Guía de ejercicios"></div>
<div class="col-md-6 col-lg-3"><label class="form-label fw-semibold">Tipo *</label><select name="tipo" id="tipo" class="form-select" required><?php foreach($tipoLabels as $k=>$v):?><option value="<?=$k?>"><?=htmlspecialchars($v[0])?></option><?php endforeach;?></select></div>
<div class="col-md-6 col-lg-3"><label class="form-label fw-semibold">Estado</label><select name="estado" class="form-select"><option value="Activo">Activo</option><option value="Inactivo">Inactivo</option></select></div>
<div class="col-12"><label class="form-label fw-semibold" for="url">URL del recurso <span id="urlRequiredMark" class="text-muted fw-normal">(opcional si subes un PDF)</span></label><input name="url" id="url" type="url" class="form-control" maxlength="500" placeholder="https://..."><div id="urlHelp" class="form-text">Para videos, actividades, juegos y sitios externos usa una URL. Si el tipo es PDF y adjuntas un archivo, <strong>no necesitas colocar ningún vínculo</strong>.</div></div>
<div class="col-md-6"><label class="form-label fw-semibold">Archivo PDF (opcional)</label><input name="archivo" id="archivo" type="file" class="form-control" accept="application/pdf,.pdf"><div class="form-text">Máximo 25 MB. Si se selecciona, tendrá prioridad sobre la URL.</div></div>
<div class="col-md-6"><label class="form-label fw-semibold">Miniatura (opcional)</label><input name="imagen" type="file" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG o WEBP. Máximo 5 MB.</div></div>
<div class="col-12"><label class="form-label fw-semibold">Descripción</label><textarea name="descripcion" class="form-control" rows="3" maxlength="1000" placeholder="Explica qué encontrará el estudiante."></textarea></div>
<div class="col-md-6"><label class="form-label">Autor</label><input name="autor" class="form-control" maxlength="150"></div><div class="col-md-6"><label class="form-label">Fuente</label><input name="fuente" class="form-control" maxlength="150" placeholder="YouTube, Khan Academy, etc."></div>
<div class="col-12"><button class="btn btn-primary px-4"><i class="bi bi-plus-lg"></i> Añadir recurso</button></div>
</form></div></div>
<div class="row g-4">
<?php if(!$recursos):?><div class="col-12"><div class="card card-soft"><div class="card-body text-center py-5"><i class="bi bi-inbox fs-1 text-muted"></i><h4 class="mt-3">Todavía no hay recursos</h4><p class="text-muted mb-0">Cuando añadas uno aparecerá aquí y también en la vista del estudiante.</p></div></div></div><?php endif;?>
<?php foreach($recursos as $r): $tipo=$tipoLabels[$r['tipo']]??['Recurso','bi-link-45deg']; $local=strpos((string)$r['url'],'/assets/uploads/recursos/')!==false; ?>
<div class="col-md-6 col-xl-4"><div class="resource d-flex flex-column"><div class="d-flex gap-3"><div class="icon"><i class="bi <?=htmlspecialchars($tipo[1])?>"></i></div><div class="flex-grow-1"><h5 class="fw-bold mb-1"><?=htmlspecialchars($r['titulo'])?></h5><span class="badge badge-type"><?=htmlspecialchars($tipo[0])?></span> <span class="badge <?=$r['estado']==='Activo'?'local':'text-bg-secondary'?>"><?=htmlspecialchars($r['estado'])?></span></div></div><?php if(!empty($r['imagen'])):?><img src="<?=htmlspecialchars($r['imagen'])?>" class="img-fluid rounded-3 mt-3" style="max-height:150px;object-fit:cover" alt=""><?php endif;?><p class="text-muted mt-3 mb-2"><?=nl2br(htmlspecialchars($r['descripcion']??''))?></p><div class="url mb-3"><i class="bi bi-link-45deg"></i> <?=htmlspecialchars($r['url'])?> <?php if($local):?><span class="badge local">Local</span><?php endif;?></div><div class="mt-auto d-flex gap-2"><a target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm" href="<?=htmlspecialchars($r['url'])?>"><i class="bi bi-box-arrow-up-right"></i> Abrir</a><form method="post" action="<?=htmlspecialchars(urlAplicacion('/admin/contenidos/eliminar_recurso.php'))?>" onsubmit="return confirm('¿Eliminar este recurso?');"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_recursos'])?>"><input type="hidden" name="id_recurso" value="<?=intval($r['id_recurso'])?>"><input type="hidden" name="id_tema" value="<?=$idTema?>"><button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button></form></div></div></div>
<?php endforeach;?></div>
</div></main>

<script id="studia360-recurso-form-fix">
(function(){
  const tipo=document.getElementById('tipo');
  const archivo=document.getElementById('archivo');
  const url=document.getElementById('url');
  const mark=document.getElementById('urlRequiredMark');
  const help=document.getElementById('urlHelp');

  function sync(){
    if(!tipo || !url) return;
    const isPdf=tipo.value==='pdf';
    const hasFile=archivo && archivo.files && archivo.files.length>0;

    /* URL nunca es obligatoria cuando hay un PDF local seleccionado. */
    if(isPdf && hasFile){
      url.removeAttribute('required');
      if(mark) mark.textContent='(opcional: el PDF se subirá desde tu equipo)';
      if(help) help.innerHTML='El PDF seleccionado se guardará en Studia360. <strong>No necesitas colocar ningún vínculo.</strong> Si también pones una URL, el archivo subido tendrá prioridad.';
    }else if(isPdf){
      url.removeAttribute('required');
      if(mark) mark.textContent='(opcional si adjuntas un PDF)';
      if(help) help.innerHTML='Puedes subir el PDF directamente sin vínculo. Si no adjuntas un archivo, entonces debes proporcionar una URL HTTPS al PDF.';
    }else{
      url.removeAttribute('required');
      if(mark) mark.textContent='(necesaria para este tipo)';
      if(help) help.textContent='Videos, actividades, juegos, simuladores, presentaciones y sitios externos necesitan una URL.';
    }
  }
  tipo && tipo.addEventListener('change',sync);
  archivo && archivo.addEventListener('change',sync);
  sync();
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><!-- Studia360 Admin: personalizador global -->
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

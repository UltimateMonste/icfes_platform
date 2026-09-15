<?php
/**
 * Studia360 - Administración de contenido de un tema
 * Archivo: admin/contenidos/editar_tema.php
 */

require_once __DIR__ . '/../../includes/seguridad.php';
exigirAdmin();

$errores = [];
$mensajes = [];
$tema = null;
$contenidoEditor = '';
$estadoContenido = null;
$fechaActualizacion = null;

$idTema = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idTema || $idTema <= 0) {
    redireccionarDashboardUsuario();
}

/* --------------------------------------------------------------------------
 * CSRF para las acciones AJAX del editor.
 * -------------------------------------------------------------------------- */
if (empty($_SESSION['csrf_editor'])) {
    $_SESSION['csrf_editor'] = bin2hex(random_bytes(32));
}
$csrfEditor = $_SESSION['csrf_editor'];

/* --------------------------------------------------------------------------
 * Sanitización del HTML generado por Summernote.
 * -------------------------------------------------------------------------- */
function limpiarContenidoHTML(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // Elementos que no deben formar parte de una lección.
    $html = preg_replace([
        '#<script\b[^>]*>.*?</script>#is',
        '#<style\b[^>]*>.*?</style>#is',
        '#<object\b[^>]*>.*?</object>#is',
        '#<embed\b[^>]*>.*?</embed>#is',
        '#<applet\b[^>]*>.*?</applet>#is',
        '#<form\b[^>]*>.*?</form>#is'
    ], '', $html);

    // Eliminar manejadores de eventos: onclick, onerror, onload, etc.
    $html = preg_replace(
        '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu',
        '',
        $html
    );

    // Nunca aceptar javascript:, vbscript: ni data: en enlaces/recursos.
    $html = preg_replace(
        '/\s+(href|src|action)\s*=\s*(["\'])\s*(?:javascript|vbscript):.*?\2/iu',
        '',
        $html
    );
    $html = preg_replace(
        '/\s+(src|href)\s*=\s*(["\'])\s*data:.*?\2/iu',
        '',
        $html
    );

    // Los únicos iframes permitidos son YouTube/Vimeo.
    if (stripos($html, '<iframe') !== false) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);

        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($ok) {
            $iframes = $dom->getElementsByTagName('iframe');
            $eliminar = [];

            foreach ($iframes as $iframe) {
                $src = trim($iframe->getAttribute('src'));
                $permitido = preg_match(
                    '#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#i',
                    $src
                );

                if (!$permitido) {
                    $eliminar[] = $iframe;
                } else {
                    $iframe->setAttribute('loading', 'lazy');
                    $iframe->setAttribute('allowfullscreen', 'allowfullscreen');
                    $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                }
            }

            foreach ($eliminar as $iframe) {
                if ($iframe->parentNode) {
                    $iframe->parentNode->removeChild($iframe);
                }
            }

            $html = $dom->saveHTML();
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);
    }

    // Quitar etiquetas de formato que suelen traer contenido pegado desde Word/web
    // y que pueden romper la apariencia responsive de la lección.
    $html = preg_replace('#<font\\b[^>]*>(.*?)</font>#is', '$1', $html);
    $html = preg_replace('/\\s+(style)\\s*=\\s*("|\\\').*?\\2/iu', '', $html);

    return trim($html);
}

/* --------------------------------------------------------------------------
 * Cargar tema + último borrador/publicación.
 * -------------------------------------------------------------------------- */
try {
    $stmt = $conexion->prepare(
        'SELECT t.id_tema, t.id_materia, t.nombre, t.descripcion, t.contenido,
                t.grado, m.nombre AS materia, m.descripcion AS descripcion_materia
         FROM temas t
         INNER JOIN materias m ON m.id_materia = t.id_materia
         WHERE t.id_tema = ?
         LIMIT 1'
    );
    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        redireccionarDashboardUsuario();
    }

    $stmt = $conexion->prepare(
        "SELECT id_contenido, contenido, estado, fecha_creacion, fecha_actualizacion
         FROM contenido_temas
         WHERE id_tema = ?
         ORDER BY CASE WHEN estado = 'Borrador' THEN 0 ELSE 1 END,
                  fecha_actualizacion DESC, id_contenido DESC
         LIMIT 1"
    );
    $stmt->execute([$idTema]);
    $guardado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guardado) {
        $contenidoEditor = (string)($guardado['contenido'] ?? '');
        $estadoContenido = $guardado['estado'] ?? null;
        $fechaActualizacion = $guardado['fecha_actualizacion'] ?? null;
    } else {
        // Compatibilidad con versiones anteriores.
        $contenidoEditor = (string)($tema['contenido'] ?? '');
        $estadoContenido = trim($contenidoEditor) !== '' ? 'Publicado' : null;
    }
} catch (PDOException $e) {
    $errores[] = 'No fue posible cargar el tema. Verifica la conexión con la base de datos.';
}

/* --------------------------------------------------------------------------
 * Guardar borrador / publicar.
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errores)) {
    $accion = $_POST['accion'] ?? 'borrador';
    $contenido = limpiarContenidoHTML((string)($_POST['contenido'] ?? ''));

    if (!in_array($accion, ['borrador', 'publicar'], true)) {
        $accion = 'borrador';
    }

    // Validación real: quitar etiquetas para comprobar si hay texto o contenido.
    $textoPlano = trim(html_entity_decode(strip_tags($contenido), ENT_QUOTES, 'UTF-8'));
    $tieneContenidoVisual = (bool)preg_match('/<(img|iframe|table|video|audio|a)\b/i', $contenido);

    if ($textoPlano === '' && !$tieneContenidoVisual) {
        $errores[] = 'El contenido está vacío. Escribe o inserta algún contenido antes de guardarlo.';
    }

    if (empty($errores)) {
        try {
            $conexion->beginTransaction();

            if ($accion === 'borrador') {
                // Mantener un único borrador funcional por tema.
                $stmt = $conexion->prepare(
                    "SELECT id_contenido FROM contenido_temas
                     WHERE id_tema = ? AND estado = 'Borrador'
                     ORDER BY id_contenido DESC LIMIT 1"
                );
                $stmt->execute([$idTema]);
                $idBorrador = $stmt->fetchColumn();

                if ($idBorrador) {
                    $stmt = $conexion->prepare(
                        'UPDATE contenido_temas
                         SET contenido = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                         WHERE id_contenido = ?'
                    );
                    $stmt->execute([$contenido, $idBorrador]);

                    // Limpieza de borradores duplicados de versiones anteriores.
                    $stmt = $conexion->prepare(
                        "DELETE FROM contenido_temas
                         WHERE id_tema = ? AND estado = 'Borrador' AND id_contenido <> ?"
                    );
                    $stmt->execute([$idTema, $idBorrador]);
                } else {
                    $stmt = $conexion->prepare(
                        "INSERT INTO contenido_temas (id_tema, contenido, estado)
                         VALUES (?, ?, 'Borrador')"
                    );
                    $stmt->execute([$idTema, $contenido]);
                }

                $conexion->commit();
                $mensajes[] = 'El borrador se guardó correctamente. Todavía no es visible para los estudiantes.';
                $estadoContenido = 'Borrador';
                $contenidoEditor = $contenido;
                $fechaActualizacion = date('Y-m-d H:i:s');
            } else {
                // Mantener una única publicación vigente por tema.
                $stmt = $conexion->prepare(
                    "SELECT id_contenido FROM contenido_temas
                     WHERE id_tema = ? AND estado = 'Publicado'
                     ORDER BY id_contenido DESC LIMIT 1"
                );
                $stmt->execute([$idTema]);
                $idPublicado = $stmt->fetchColumn();

                if ($idPublicado) {
                    $stmt = $conexion->prepare(
                        'UPDATE contenido_temas
                         SET contenido = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                         WHERE id_contenido = ?'
                    );
                    $stmt->execute([$contenido, $idPublicado]);

                    $stmt = $conexion->prepare(
                        "DELETE FROM contenido_temas
                         WHERE id_tema = ? AND estado = 'Publicado' AND id_contenido <> ?"
                    );
                    $stmt->execute([$idTema, $idPublicado]);
                } else {
                    $stmt = $conexion->prepare(
                        "INSERT INTO contenido_temas (id_tema, contenido, estado)
                         VALUES (?, ?, 'Publicado')"
                    );
                    $stmt->execute([$idTema, $contenido]);
                }

                // Compatibilidad con el campo histórico.
                $stmt = $conexion->prepare('UPDATE temas SET contenido = ? WHERE id_tema = ?');
                $stmt->execute([$contenido, $idTema]);

                // El borrador deja de ser necesario al publicar.
                $stmt = $conexion->prepare(
                    "DELETE FROM contenido_temas WHERE id_tema = ? AND estado = 'Borrador'"
                );
                $stmt->execute([$idTema]);

                $conexion->commit();
                $mensajes[] = 'El contenido se publicó correctamente y ya está disponible para los estudiantes.';
                $estadoContenido = 'Publicado';
                $contenidoEditor = $contenido;
                $fechaActualizacion = date('Y-m-d H:i:s');
            }
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $errores[] = 'No fue posible guardar el contenido. Revisa la estructura de contenido_temas y vuelve a intentarlo.';
        }
    }
}

$base = urlAplicacion('');
$urlTema = urlAplicacion('/estudiante/tema.php?id=' . (int)$idTema);
$urlRecursos = urlAplicacion('/admin/contenidos/recursos.php?id=' . (int)$idTema);
$urlUploadImagen = urlAplicacion('/admin/contenidos/upload_imagen.php');
$urlDashboard = urlAplicacion('/admin/dashboard.php');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar tema | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../../assets/summernote/summernote-lite.min.css">
<style>
:root{--azul:#0d6efd;--fondo:#f4f7fb;--texto:#172033}
body{background:var(--fondo);color:var(--texto)}
.navbar{box-shadow:0 3px 16px rgba(0,0,0,.12)}
.page-wrap{max-width:1500px;margin:auto}
.editor-card,.info-card{border:0;border-radius:20px;box-shadow:0 10px 30px rgba(20,35,60,.08)}
.hero{background:linear-gradient(135deg,#0d6efd,#084298);color:#fff;border-radius:20px;padding:25px;box-shadow:0 15px 35px rgba(13,110,253,.16)}
.status-badge{font-weight:600}
.note-editor{border:1px solid #dce3ec!important;border-radius:14px!important;overflow:hidden}
.note-toolbar{background:#f8fafc!important;border-bottom:1px solid #e1e7ef!important;padding:8px!important}
.note-btn{border-radius:7px!important}
.note-editable{min-height:680px!important;padding:36px!important;background:#fff;font-size:16px;line-height:1.75;overflow-wrap:anywhere;word-break:break-word}.note-editable table{max-width:100%;display:block;overflow-x:auto}.note-editable img{max-width:100%!important;height:auto!important}.note-editable iframe,.note-editable video{max-width:100%;width:100%;border:0;border-radius:12px}
.note-editable img{max-width:100%;height:auto}
.note-editable iframe{max-width:100%;width:100%;min-height:360px;border:0;border-radius:12px}
.bloque-label{font-weight:800;margin-bottom:8px}
.info-box,.important-box,.example-box,.exercise-box,.remember-box{padding:16px 18px;border-radius:14px;margin:20px 0}
.info-box{border-left:5px solid #0d6efd;background:#eaf3ff}
.important-box{border-left:5px solid #dc3545;background:#fff0f1}
.example-box{border-left:5px solid #198754;background:#eaf8ef}
.exercise-box{border-left:5px solid #ffc107;background:#fff8df}
.remember-box{border-left:5px solid #6f42c1;background:#f3efff}
.actions-bar{position:sticky;bottom:0;z-index:30;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid #e1e6ed;padding:14px 0;margin-top:18px}
.url-help{font-size:.86rem;color:#667085}
@media(max-width:767px){.note-editable{min-height:500px!important;padding:20px!important}.actions-bar .btn{width:100%}}
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">


<style id="studia360-theme">
:root{--s360-accent:#2563eb;--s360-accent-2:#4f46e5;--s360-soft:#eff6ff;--s360-bg:#f6f8fc;--s360-card:#fff;--s360-text:#1f2937;--s360-muted:#748196;--s360-line:#e5eaf1;--s360-input:#fff}
body.s360-content-theme{--s360-accent:#2563eb;--s360-accent-2:#4f46e5;--s360-soft:#eff6ff;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360-accent) 8%,transparent),transparent 25rem),var(--s360-bg)!important;color:var(--s360-text)!important;min-height:100vh;transition:background .25s ease,color .25s ease}
body.s360-content-theme.s360-accent-orange{--s360-accent:#f97316;--s360-accent-2:#ea580c;--s360-soft:#fff7ed}
body.s360-content-theme.s360-accent-purple{--s360-accent:#8b5cf6;--s360-accent-2:#7c3aed;--s360-soft:#f5f3ff}
body.s360-content-theme.s360-accent-green{--s360-accent:#10b981;--s360-accent-2:#059669;--s360-soft:#ecfdf5}
body.s360-content-theme .navbar{background:rgba(255,255,255,.94)!important;border-bottom:1px solid var(--s360-line)!important;box-shadow:0 5px 22px rgba(20,35,60,.06)!important}
body.s360-content-theme .navbar-brand{color:var(--s360-text)!important}
body.s360-content-theme .navbar-brand i{color:var(--s360-accent)!important}
body.s360-content-theme .page,body.s360-content-theme .page-wrap{position:relative}
body.s360-content-theme .card-studia,body.s360-content-theme .editor-card,body.s360-content-theme .info-card{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .card-studia h1,body.s360-content-theme .editor-card h1,body.s360-content-theme .editor-card h2,body.s360-content-theme .editor-card h3,body.s360-content-theme .info-card h1,body.s360-content-theme .info-card h2{color:var(--s360-text)!important}
body.s360-content-theme .text-secondary,body.s360-content-theme .text-muted,body.s360-content-theme .url-help{color:var(--s360-muted)!important}
body.s360-content-theme .text-primary{color:var(--s360-accent)!important}
body.s360-content-theme .form-control,body.s360-content-theme .form-select,body.s360-content-theme textarea{background:var(--s360-input)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .form-control:focus,body.s360-content-theme .form-select:focus,body.s360-content-theme textarea:focus{border-color:color-mix(in srgb,var(--s360-accent) 45%,var(--s360-line))!important;box-shadow:0 0 0 4px color-mix(in srgb,var(--s360-accent) 10%,transparent)!important}
body.s360-content-theme .btn-primary{background:var(--s360-accent)!important;border-color:var(--s360-accent)!important;color:#fff!important}
body.s360-content-theme .btn-outline-primary{color:var(--s360-accent)!important;border-color:color-mix(in srgb,var(--s360-accent) 32%,var(--s360-line))!important}
body.s360-content-theme .btn-outline-primary:hover{background:var(--s360-accent)!important;border-color:var(--s360-accent)!important;color:#fff!important}
body.s360-content-theme .actions-bar{background:rgba(255,255,255,.94)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .hero{background:linear-gradient(135deg,var(--s360-accent),var(--s360-accent-2))!important}
body.s360-content-theme .info-box{background:var(--s360-soft)!important;border-left-color:var(--s360-accent)!important;color:var(--s360-text)!important}
body.s360-content-theme .note-editor{border-color:var(--s360-line)!important}
body.s360-content-theme .note-toolbar{background:var(--s360-soft)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .note-btn{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .note-editable{background:var(--s360-input)!important;color:var(--s360-text)!important}
body.s360-content-theme .note-statusbar{background:var(--s360-card)!important;border-color:var(--s360-line)!important}
body.s360-content-theme .adm-theme-toggle{position:fixed!important;right:20px!important;bottom:20px!important;z-index:3000!important;width:50px!important;height:50px!important;padding:0!important;border:0!important;border-radius:16px!important;display:grid!important;place-items:center!important;background:linear-gradient(145deg,var(--s360-accent),var(--s360-accent-2))!important;color:#fff!important;box-shadow:0 12px 30px color-mix(in srgb,var(--s360-accent) 28%,transparent)!important;cursor:pointer!important}
body.s360-content-theme .adm-theme-toggle i{font-size:1.1rem!important}
body.s360-content-theme .adm-theme-panel{position:fixed!important;right:20px!important;bottom:82px!important;width:290px!important;max-width:calc(100vw - 28px)!important;padding:16px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;border:1px solid var(--s360-line)!important;border-radius:18px!important;box-shadow:0 20px 55px rgba(20,35,60,.18)!important;z-index:2999!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important;transform:translateY(8px)!important;transition:.18s ease!important}
body.s360-content-theme .adm-theme-panel.open{opacity:1!important;visibility:visible!important;pointer-events:auto!important;transform:none!important}
body.s360-content-theme .adm-theme-title{font-weight:850!important;font-size:.84rem!important;margin:0 0 4px!important;color:var(--s360-text)!important}
body.s360-content-theme .adm-theme-sub{font-size:.66rem!important;color:var(--s360-muted)!important;margin:0 0 13px!important}
body.s360-content-theme .adm-theme-grid{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important}
body.s360-content-theme .adm-theme-option{appearance:none!important;width:100%!important;min-height:74px!important;padding:9px!important;border:1px solid var(--s360-line)!important;border-radius:12px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;text-align:left!important;cursor:pointer!important;font:inherit!important}
body.s360-content-theme .adm-theme-option:hover,body.s360-content-theme .adm-theme-option.active{background:var(--s360-soft)!important;border-color:color-mix(in srgb,var(--s360-accent) 42%,var(--s360-line))!important}
body.s360-content-theme .adm-swatch{display:block!important;height:23px!important;border-radius:8px!important;margin-bottom:7px!important}
body.s360-content-theme .adm-theme-option strong{display:block!important;font-size:.67rem!important}
body.s360-content-theme .adm-theme-option small{display:block!important;font-size:.57rem!important;color:var(--s360-muted)!important;margin-top:3px!important}
body.s360-content-theme .adm-mode-btn{appearance:none!important;width:100%!important;min-height:38px!important;margin-top:9px!important;padding:8px 10px!important;border:1px solid var(--s360-line)!important;border-radius:12px!important;background:var(--s360-card)!important;color:var(--s360-text)!important;cursor:pointer!important;text-align:left!important;font:700 .68rem/1.2 system-ui,sans-serif!important}
body.s360-content-theme.s360-content-dark{--s360-bg:#0d1424;--s360-card:#172236;--s360-text:#edf2f8;--s360-muted:#9ba8ba;--s360-line:#2b374b;--s360-input:#111827;background:radial-gradient(circle at 92% 0%,color-mix(in srgb,var(--s360-accent) 13%,transparent),transparent 25rem),var(--s360-bg)!important}
body.s360-content-theme.s360-content-dark .navbar{background:rgba(13,20,36,.95)!important}
body.s360-content-theme.s360-content-dark .btn-secondary,body.s360-content-theme.s360-content-dark .btn-outline-secondary{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme.s360-content-dark .bg-white{background:var(--s360-card)!important;color:var(--s360-text)!important}
body.s360-content-theme.s360-content-dark .actions-bar{background:rgba(23,34,54,.95)!important}
body.s360-content-theme.s360-content-dark .modal-content{background:var(--s360-card)!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-content-theme.s360-content-dark .btn-close{filter:invert(1) grayscale(1) brightness(2)}
@media(max-width:767px){body.s360-content-theme .adm-theme-toggle{right:14px!important;bottom:14px!important}body.s360-content-theme .adm-theme-panel{right:12px!important;bottom:72px!important}}
</style>

</head>
<body class="s360-admin s360-content-theme">
<nav class="navbar navbar-dark bg-dark py-3">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold" href="<?= htmlspecialchars($urlDashboard) ?>"><i class="bi bi-mortarboard-fill"></i> Studia360</a>
    <div class="d-flex align-items-center gap-2 text-white">
      <span class="d-none d-md-inline"><i class="bi bi-shield-check"></i> Administrador</span>
      <a class="btn btn-outline-light btn-sm" href="<?= htmlspecialchars(urlAplicacion('/cerrar_sesion.php')) ?>"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container-fluid px-3 px-lg-5 py-4">
<div class="page-wrap">
  <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
    <div>
      <div class="text-primary fw-bold small">ADMINISTRACIÓN DE CONTENIDOS</div>
      <h1 class="fw-bold mb-1"><?= htmlspecialchars($tema['nombre'] ?? 'Tema') ?></h1>
      <div class="text-muted"><?= htmlspecialchars($tema['materia'] ?? '') ?> · <?= htmlspecialchars($tema['grado'] ?? '') ?>°</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(urlAplicacion('/admin/contenidos/informacion_tema.php?id='.(int)$tema['id_tema'])) ?>"><i class="bi bi-pencil-square"></i> Editar datos</a>
      <a
    href="vista_previa_tema.php?id=<?= (int)$tema['id_tema'] ?>"
    target="_blank"
    class="btn btn-outline-primary"
>
    <i class="bi bi-eye me-1"></i>
    Ver tema
</a>
      <a class="btn btn-outline-primary" href="<?= htmlspecialchars($urlRecursos) ?>"><i class="bi bi-collection-play"></i> Gestionar recursos</a>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($urlDashboard) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
  </div>

  <?php foreach ($mensajes as $mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endforeach; ?>
  <?php foreach ($errores as $error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endforeach; ?>

  <div class="hero mb-4">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <div class="text-uppercase small fw-bold opacity-75 mb-2">Editor de contenido</div>
        <h2 class="fw-bold mb-2">Construye la lección de <?= htmlspecialchars($tema['nombre'] ?? 'este tema') ?></h2>
        <p class="mb-0 opacity-75">Texto, imágenes, tablas, enlaces, vídeos, bloques destacados y recursos complementarios.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <?php if ($estadoContenido === 'Borrador'): ?>
          <span class="badge text-bg-warning text-dark status-badge px-3 py-2"><i class="bi bi-pencil-square"></i> Borrador guardado</span>
          <div class="small mt-2 opacity-75">No visible para estudiantes hasta publicar.</div>
        <?php elseif ($estadoContenido === 'Publicado'): ?>
          <span class="badge text-bg-success status-badge px-3 py-2"><i class="bi bi-check-circle"></i> Contenido publicado</span>
          <?php if ($fechaActualizacion): ?><div class="small mt-2 opacity-75">Última actualización: <?= htmlspecialchars($fechaActualizacion) ?></div><?php endif; ?>
        <?php else: ?>
          <span class="badge text-bg-light text-dark status-badge px-3 py-2"><i class="bi bi-file-earmark"></i> Sin contenido</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="alert alert-info border-0 shadow-sm info-card mb-4">
    <div class="fw-bold"><i class="bi bi-info-circle-fill"></i> Imágenes y contenido dinámico</div>
    <div class="small mt-1">Puedes subir una imagen desde tu PC o usar <strong>Imagen desde URL</strong>; Studia360 la descargará y la guardará localmente para que no dependa de la página externa.</div>
  </div>

  <div class="card editor-card">
    <div class="card-body p-3 p-lg-4">
      <form method="post" id="formContenido" autocomplete="off">
        <input type="hidden" name="csrf_editor" value="<?= htmlspecialchars($csrfEditor) ?>">
        <textarea id="contenido" name="contenido"><?= htmlspecialchars($contenidoEditor, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="actions-bar">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="text-muted small"><i class="bi bi-shield-check"></i> Contenido administrado por Studia360.</div>
            <div class="d-flex flex-column flex-sm-row gap-2">
              <button type="button" class="btn btn-outline-secondary px-4" id="btnLimpiar"><i class="bi bi-eraser"></i> Limpiar</button>
              <button type="submit" name="accion" value="borrador" class="btn btn-outline-secondary px-4"><i class="bi bi-file-earmark-text"></i> Guardar borrador</button>
              <button type="submit" name="accion" value="publicar" class="btn btn-primary px-4"><i class="bi bi-cloud-arrow-up"></i> Publicar contenido</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</main>

<script src="../../assets/js/jquery-3.7.1.min.js"></script>
<script src="../../assets/summernote/summernote-lite.min.js"></script>
<script>
$(function () {
  const editor = $('#contenido');
  const csrf = <?= json_encode($csrfEditor) ?>;
  const uploadUrl = <?= json_encode($urlUploadImagen) ?>;

  function subirImagen(file) {
    const data = new FormData();
    data.append('file', file);
    data.append('csrf', csrf);

    $.ajax({
      url: uploadUrl,
      method: 'POST',
      data: data,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (respuesta) {
      if (respuesta.success && respuesta.url) {
        editor.summernote('insertImage', respuesta.url, function ($image) {
          $image.attr('alt', file.name || 'Imagen del tema');
          $image.css('max-width', '100%');
        });
      } else {
        alert(respuesta.message || 'No fue posible subir la imagen.');
      }
    }).fail(function (xhr) {
      let mensaje = 'No fue posible subir la imagen.';
      try { mensaje = xhr.responseJSON.message || mensaje; } catch (e) {}
      alert(mensaje);
    });
  }

  function subirImagenDesdeURL(url) {
    const data = new FormData();
    data.append('url', url);
    data.append('csrf', csrf);

    $.ajax({
      url: uploadUrl,
      method: 'POST',
      data: data,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (respuesta) {
      if (respuesta.success && respuesta.url) {
        editor.summernote('insertImage', respuesta.url, function ($image) {
          $image.attr('alt', 'Imagen del tema');
          $image.css('max-width', '100%');
        });
      } else {
        alert(respuesta.message || 'La URL no contiene una imagen válida.');
      }
    }).fail(function (xhr) {
      let mensaje = 'No fue posible descargar la imagen desde esa URL.';
      try { mensaje = xhr.responseJSON.message || mensaje; } catch (e) {}
      alert(mensaje);
    });
  }

  function crearBloque(clase, titulo, icono) {
    return '<div class="' + clase + '"><div class="bloque-label">' + icono + ' ' + titulo + '</div><p>Escribe aquí el contenido...</p></div><p><br></p>';
  }

  editor.summernote({
    lang: 'es-ES',
    height: 700,
    minHeight: 500,
    placeholder: 'Comienza a construir la lección...',
    tabsize: 2,
    dialogsInBody: true,
    toolbar: [
      ['style', ['style']],
      ['font', ['bold','italic','underline','strikethrough','clear']],
            ['para', ['ul','ol','paragraph']],
      ['height', ['height']],
      ['table', ['table']],
      ['insert', ['link','picture','video','hr']],
      ['view', ['fullscreen','codeview','help']],
      ['misc', ['undo','redo']]
    ],
    styleTags: ['p','blockquote','pre','h1','h2','h3','h4','h5','h6'],
    fontSizes: ['8','10','12','14','16','18','20','24','28','32','36','48'],
    callbacks: {
      onImageUpload: function (files) {
        for (let i = 0; i < files.length; i++) subirImagen(files[i]);
      }
    }
  });

  // Botones propios para acciones que Summernote no resuelve por sí solo.
  const toolbar = $('.note-toolbar');
  const customGroup = $('<div class="btn-group ms-1 mt-1"></div>');

  const btnURL = $('<button type="button" class="btn btn-light btn-sm" title="Descargar una imagen desde una URL"><i class="bi bi-cloud-download"></i> Imagen URL</button>');
  btnURL.on('click', function () {
    const url = prompt('Pega la URL directa de la imagen (JPG, PNG, GIF o WEBP):');
    if (url && url.trim()) subirImagenDesdeURL(url.trim());
  });
  customGroup.append(btnURL);

  const btnRecurso = $('<button type="button" class="btn btn-light btn-sm" title="Insertar un aviso para usar recursos complementarios"><i class="bi bi-collection-play"></i> Recurso</button>');
  btnRecurso.on('click', function () {
    editor.summernote('pasteHTML', '<div class="info-box"><div class="bloque-label">📚 RECURSO COMPLEMENTARIO</div><p>Describe aquí qué debe consultar el estudiante. Los videos, PDFs y actividades también pueden gestionarse desde <strong>Gestionar recursos</strong>.</p></div><p><br></p>');
  });
  customGroup.append(btnRecurso);

  [
    ['💡 Concepto','info-box','CONCEPTO CLAVE'],
    ['⚠️ Importante','important-box','IMPORTANTE'],
    ['🔎 Ejemplo','example-box','EJEMPLO'],
    ['📝 Ejercicio','exercise-box','EJERCICIO'],
    ['📌 Recuerda','remember-box','RECUERDA']
  ].forEach(function (item) {
    const b = $('<button type="button" class="btn btn-light btn-sm"></button>').text(item[0]);
    b.on('click', function () { editor.summernote('pasteHTML', crearBloque(item[1], item[2], item[0].split(' ')[0])); });
    customGroup.append(b);
  });

  toolbar.append(customGroup);

  $('#btnLimpiar').on('click', function () {
    if (confirm('¿Seguro que quieres eliminar todo el contenido del editor? Esta acción no guarda cambios.')) {
      editor.summernote('code', '');
    }
  });

  $('#formContenido').on('submit', function (event) {
    editor.val(editor.summernote('code'));
    const contenido = editor.summernote('code').trim();
    const accion = $(document.activeElement).val();

    if ((contenido === '' || contenido === '<p><br></p>') && accion === 'publicar') {
      alert('No puedes publicar un tema sin contenido.');
      event.preventDefault();
    }
  });
});
</script>

<!-- Studia360: personalizador de apariencia -->
<button id="admThemeToggle" class="adm-theme-toggle" type="button" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div id="admThemePanel" class="adm-theme-panel" aria-label="Personalizar apariencia">
<div class="adm-theme-title">Personaliza Studia360</div>
<div class="adm-theme-sub">Elige un color y usa el modo claro u oscuro.</div>
<div class="adm-theme-grid">
<button class="adm-theme-option" type="button" data-theme="blue"><span class="adm-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></span><strong>Azul</strong><small>Clásico</small></button>
<button class="adm-theme-option" type="button" data-theme="orange"><span class="adm-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></span><strong>Naranja</strong><small>Enérgico</small></button>
<button class="adm-theme-option" type="button" data-theme="purple"><span class="adm-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></span><strong>Violeta</strong><small>Creativo</small></button>
<button class="adm-theme-option" type="button" data-theme="green"><span class="adm-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></span><strong>Verde</strong><small>Calma</small></button>
</div>
<button id="admModeBtn" class="adm-mode-btn" type="button"></button>
</div>
<script>
(function(){
'use strict';
var body=document.body,key='studia360_theme',toggle=document.getElementById('admThemeToggle'),panel=document.getElementById('admThemePanel'),mode=document.getElementById('admModeBtn'),opts=document.querySelectorAll('.adm-theme-option');
var state={theme:'blue',mode:'light'};
try{var raw=localStorage.getItem(key);if(raw){var p=JSON.parse(raw);if(p&&typeof p==='object'){if(typeof p.theme==='string')state.theme=p.theme;if(p.mode==='dark'||p.mode==='light')state.mode=p.mode;}}}catch(e){}
var cls={blue:'',orange:'s360-accent-orange',purple:'s360-accent-purple',green:'s360-accent-green'};
if(!cls[state.theme])state.theme='blue';
function save(){try{localStorage.setItem(key,JSON.stringify(state));}catch(e){}}
function apply(){
Object.keys(cls).forEach(function(k){if(cls[k])body.classList.remove(cls[k]);});
body.classList.add('s360-content-theme');
if(cls[state.theme])body.classList.add(cls[state.theme]);
body.classList.toggle('s360-content-dark',state.mode==='dark');
opts.forEach(function(o){o.classList.toggle('active',o.dataset.theme===state.theme);});
mode.innerHTML=state.mode==='dark'?'<i class="bi bi-moon-stars me-2"></i>Modo oscuro':'<i class="bi bi-sun me-2"></i>Modo claro';
save();
}
opts.forEach(function(o){o.addEventListener('click',function(){state.theme=o.dataset.theme;apply();});});
mode.addEventListener('click',function(){state.mode=state.mode==='dark'?'light':'dark';apply();});
toggle.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();panel.classList.toggle('open');});
panel.addEventListener('click',function(e){e.stopPropagation();});
document.addEventListener('click',function(){panel.classList.remove('open');});
apply();
})();
</script>

</body>
</html>

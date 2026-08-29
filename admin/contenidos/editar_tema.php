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
.note-editable{min-height:680px!important;padding:36px!important;background:#fff;font-size:16px;line-height:1.75}
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
</head>
<body>
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
      ['fontname', ['fontname']],
      ['fontsize', ['fontsize']],
      ['color', ['color']],
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
</body>
</html>

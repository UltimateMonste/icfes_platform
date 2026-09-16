<?php
/**
 * Studia360
 * Vista de tema para estudiantes
 * Fase 2 - Progreso y gamificación
 *
 * Archivo:
 * estudiante/tema.php
 */

declare(strict_types=1);

require_once __DIR__ . "/../includes/seguridad.php";
require_once __DIR__ . "/../includes/gamificacion.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

exigirEstudiante();

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$idUsuario = (int)($_SESSION["id_usuario"] ?? 0);
$idTema = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

$returnGrado = trim((string)($_GET["return_grado"] ?? ""));
$returnMateria = filter_input(INPUT_GET, "return_materia", FILTER_VALIDATE_INT);
$returnUnidad = filter_input(INPUT_GET, "return_unidad", FILTER_VALIDATE_INT);
if (!$returnMateria || $returnMateria < 1) {
    $returnMateria = null;
}
if (!$returnUnidad || $returnUnidad < 1) {
    $returnUnidad = null;
}

if ($idUsuario <= 0 || !$idTema) {
    redireccionarDashboardUsuario();
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_tema"])) {
    $_SESSION["csrf_tema"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_tema"];

$errores = [];
$mensaje = "";
$recompensa = null;

/*
|--------------------------------------------------------------------------
| PROCESAR INTERACCIONES
|--------------------------------------------------------------------------
|
| - iniciar: registra que el estudiante comenzó el tema (10% mínimo).
| - recurso: registra la visualización de un recurso activo.
| - completar: marca el tema al 100% y otorga XP una sola vez.
|
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = (string)($_POST["csrf"] ?? "");

    if (!hash_equals($csrf, $token)) {
        $errores[] = "La sesión de seguridad expiró. Recarga la página e inténtalo nuevamente.";
    } else {

        $accion = trim((string)($_POST["accion"] ?? ""));

        try {

            /*
             * Cargamos el tema primero para validar todas las acciones.
             */
            $stmtTemaPost = $conexion->prepare("
                SELECT id_tema
                FROM temas
                WHERE id_tema = ?
                LIMIT 1
            ");
            $stmtTemaPost->execute([$idTema]);

            if (!$stmtTemaPost->fetchColumn()) {
                redireccionarDashboardUsuario();
            }

            $progresoActual = obtenerProgresoTema(
                $conexion,
                $idUsuario,
                (int)$idTema
            );

            if ($accion === "iniciar") {

                /*
                 * No damos XP por abrir un tema.
                 * Solamente registramos actividad.
                 */
                $avanceActual = (float)$progresoActual["porcentaje_avance"];

                if ($avanceActual < 10) {
                    actualizarProgresoTema(
                        $conexion,
                        $idUsuario,
                        (int)$idTema,
                        null,
                        null
                    );
                }

                $mensaje = "Tu progreso se ha guardado.";

            } elseif ($accion === "recurso") {

                $idRecurso = filter_var(
                    $_POST["id_recurso"] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$idRecurso) {
                    throw new RuntimeException("El recurso no es válido.");
                }

                $stmtRecurso = $conexion->prepare("
                    SELECT
                        id_recurso,
                        tipo,
                        titulo
                    FROM recursos
                    WHERE id_recurso = ?
                      AND id_tema = ?
                      AND estado = 'Activo'
                    LIMIT 1
                ");

                $stmtRecurso->execute([
                    $idRecurso,
                    $idTema
                ]);

                $recurso = $stmtRecurso->fetch(PDO::FETCH_ASSOC);

                if (!$recurso) {
                    throw new RuntimeException("El recurso no está disponible.");
                }

                /*
                 * Para no incrementar recursos_vistos infinitamente,
                 * utilizamos una marca en sesión por estudiante/navegador.
                 *
                 * El progreso persistente sigue guardando la cantidad.
                 */
                if (!isset($_SESSION["studia360_recursos_vistos"])) {
                    $_SESSION["studia360_recursos_vistos"] = [];
                }

                $claveRecurso = $idUsuario . "_" . $idTema . "_" . $idRecurso;

                $yaVisto = !empty(
                    $_SESSION["studia360_recursos_vistos"][$claveRecurso]
                );

                $vistos = (int)$progresoActual["recursos_vistos"];

                if (!$yaVisto) {
                    $vistos++;
                    $_SESSION["studia360_recursos_vistos"][$claveRecurso] = true;
                }

                /*
                 * Cantidad total de recursos/evaluaciones.
                 * La ponderación deja espacio al contenido principal.
                 */
                $stmtTotales = $conexion->prepare("
                    SELECT
                        (
                            SELECT COUNT(*)
                            FROM recursos
                            WHERE id_tema = ?
                              AND estado = 'Activo'
                        ) AS total_recursos,

                        (
                            SELECT COUNT(*)
                            FROM evaluaciones
                            WHERE id_tema = ?
                        ) AS total_evaluaciones
                ");

                $stmtTotales->execute([
                    $idTema,
                    $idTema
                ]);

                $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC);

                $totalRecursos = (int)($totales["total_recursos"] ?? 0);
                $totalEvaluaciones = (int)($totales["total_evaluaciones"] ?? 0);
                $evaluacionesRealizadas = (int)$progresoActual["evaluaciones_realizadas"];

                /*
                 * 10% por iniciar + hasta 60% por recursos +
                 * hasta 30% por evaluaciones.
                 *
                 * El 100% se reserva para "completar tema".
                 */
                $avance = 10;

                if ($totalRecursos > 0) {
                    $avance += min(
                        60,
                        ($vistos / $totalRecursos) * 60
                    );
                } else {
                    $avance += 60;
                }

                if ($totalEvaluaciones > 0) {
                    $avance += min(
                        30,
                        ($evaluacionesRealizadas / $totalEvaluaciones) * 30
                    );
                } else {
                    $avance += 30;
                }

                $avance = min(99, round($avance, 2));

                actualizarProgresoTema(
                    $conexion,
                    $idUsuario,
                    (int)$idTema,
                    $vistos,
                    $evaluacionesRealizadas
                );

                $mensaje = "Recurso registrado en tu progreso.";

            } elseif ($accion === "completar") {

                $puntosAntes = (int)obtenerGamificacionUsuario($conexion, $idUsuario)["puntos"];
                $progresoCompletado = completarTema($conexion, $idUsuario, (int)$idTema);
                $gamDespues = obtenerGamificacionUsuario($conexion, $idUsuario);
                $ganados = max(0, (int)$gamDespues["puntos"] - $puntosAntes);
                $mensaje = "¡Tema completado! Ganaste " . $ganados . " XP.";

            } else {
                throw new RuntimeException("La acción solicitada no es válida.");
            }

        } catch (Throwable $e) {
            $errores[] = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CARGAR TEMA
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conexion->prepare("
        SELECT
            t.id_tema,
            t.id_materia,
            t.id_unidad,
            t.nombre AS tema,
            t.descripcion,
            t.grado,
            m.nombre AS materia,
            m.descripcion AS descripcion_materia,
            u.nombre AS unidad,
            u.descripcion AS descripcion_unidad
        FROM temas t
        INNER JOIN materias m
            ON m.id_materia = t.id_materia
        LEFT JOIN unidades_tematicas u
            ON u.id_unidad = t.id_unidad
        WHERE t.id_tema = ?
        LIMIT 1
    ");

    $stmt->execute([$idTema]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tema) {
        redireccionarDashboardUsuario();
    }

    /*
     * Solo el contenido publicado es visible.
     */
    $stmtContenido = $conexion->prepare("
        SELECT
            id_contenido,
            contenido,
            fecha_actualizacion
        FROM contenido_temas
        WHERE id_tema = ?
          AND estado = 'Publicado'
        ORDER BY fecha_actualizacion DESC, id_contenido DESC
        LIMIT 1
    ");

    $stmtContenido->execute([$idTema]);
    $contenido = $stmtContenido->fetch(PDO::FETCH_ASSOC);

    /*
     * Recursos activos.
     */
    $stmtRecursos = $conexion->prepare("
        SELECT
            id_recurso,
            titulo,
            tipo,
            url,
            descripcion,
            imagen,
            autor,
            fuente,
            visitas
        FROM recursos
        WHERE id_tema = ?
          AND estado = 'Activo'
        ORDER BY id_recurso ASC
    ");

    $stmtRecursos->execute([$idTema]);
    $recursos = $stmtRecursos->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Evaluaciones asociadas.
     */
    $stmtEvaluaciones = $conexion->prepare("
        SELECT
            id_evaluacion,
            titulo,
            descripcion,
            puntaje_maximo,
            tiempo_minutos,
            estado
        FROM evaluaciones
        WHERE id_tema = ?
        ORDER BY id_evaluacion ASC
    ");

    $stmtEvaluaciones->execute([$idTema]);
    $evaluaciones = []; // Las evaluaciones fueron retiradas del recorrido educativo de Studia360.

    /*
     * Progreso actualizado.
     */
    $progreso = obtenerProgresoTema(
        $conexion,
        $idUsuario,
        (int)$idTema
    );

    /*
     * Registrar el inicio ANTES de cargar la información de gamificación.
     *
     * Esto es importante: si primero consultamos los datos del usuario y
     * después guardamos el 10%, la pantalla queda mostrando los valores
     * anteriores hasta que el usuario abandona la página y vuelve al
     * dashboard. Al guardar primero y consultar después, la interfaz refleja
     * el cambio inmediatamente en la misma visita al tema.
     */
    if (
        (float)$progreso["porcentaje_avance"] < 10
    ) {
        $progreso = actualizarProgresoTema(
            $conexion,
            $idUsuario,
            (int)$idTema,
            null,
            null
        );
    }

    /*
     * Ahora sí consultamos la gamificación, para que cualquier cambio
     * registrado al abrir el tema ya esté disponible en la vista.
     */
    $gamificacion = obtenerGamificacionUsuario(
        $conexion,
        $idUsuario
    );

} catch (Throwable $e) {
    $errores[] = "No fue posible cargar el tema. Inténtalo nuevamente.";
    $tema = null;
    $contenido = null;
    $recursos = [];
    $evaluaciones = [];
    $progreso = [
        "porcentaje_avance" => 0,
        "recursos_vistos" => 0,
        "evaluaciones_realizadas" => 0,
    ];
    $gamificacion = [
        "puntos" => 0,
        "nivel" => [
            "nombre" => "Iniciado",
            "puntos_minimos" => 0,
            "puntos_maximos" => 100
        ],
        "progreso_nivel" => 0
    ];
}

/*
|--------------------------------------------------------------------------
| DATOS VISUALES
|--------------------------------------------------------------------------
*/
$avance = max(
    0,
    min(100, (float)($progreso["porcentaje_avance"] ?? 0))
);

$completado = (float)($progreso["porcentaje_avance"] ?? 0) >= 100;

$tipoIconos = [
    "video" => "bi-play-circle-fill",
    "pdf" => "bi-file-earmark-pdf-fill",
    "articulo" => "bi-file-text-fill",
    "blog" => "bi-journal-richtext",
    "app" => "bi-grid-1x2-fill",
    "juego" => "bi-controller",
    "simulador" => "bi-sliders2",
    "presentacion" => "bi-easel2-fill"
];

$tipoNombres = [
    "video" => "Video",
    "pdf" => "PDF",
    "articulo" => "Artículo",
    "blog" => "Blog",
    "app" => "Aplicación",
    "juego" => "Juego",
    "simulador" => "Simulador",
    "presentacion" => "Presentación"
];


/*
|--------------------------------------------------------------------------
| PORTADAS DE RECURSOS
|--------------------------------------------------------------------------
*/
function extraerIdYoutube(string $url): ?string
{
    $url = trim($url);

    if ($url === "") {
        return null;
    }

    $patrones = [
        '~youtu\.be/([A-Za-z0-9_-]{6,})~i',
        '~youtube\.com/watch\?[^#]*v=([A-Za-z0-9_-]{6,})~i',
        '~youtube\.com/embed/([A-Za-z0-9_-]{6,})~i',
        '~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~i'
    ];

    foreach ($patrones as $patron) {
        if (preg_match($patron, $url, $coincidencia)) {
            return $coincidencia[1];
        }
    }

    return null;
}

function prepararImagenRecurso(string $imagen): string
{
    $imagen = trim($imagen);

    if ($imagen === "") {
        return "";
    }

    if (
        preg_match('~^https?://~i', $imagen) ||
        str_starts_with($imagen, "data:image/")
    ) {
        return $imagen;
    }

    $imagen = ltrim(str_replace("\\", "/", $imagen), "/");

    if (stripos($imagen, "icfes_platform/") === 0) {
        $imagen = substr($imagen, strlen("icfes_platform/"));
    }

    return urlAplicacion("/" . $imagen);
}

function portadaRecurso(array $recurso): array
{
    $tipo = strtolower(trim((string)($recurso["tipo"] ?? "")));
    $url = trim((string)($recurso["url"] ?? ""));
    $imagen = prepararImagenRecurso((string)($recurso["imagen"] ?? ""));

    if ($imagen !== "") {
        return [
            "modo" => "imagen",
            "url" => $imagen,
            "icono" => $GLOBALS["tipoIconos"][$tipo] ?? "bi-link-45deg",
            "nombre" => $GLOBALS["tipoNombres"][$tipo] ?? ucfirst($tipo)
        ];
    }

    if ($tipo === "video") {
        $youtubeId = extraerIdYoutube($url);

        if ($youtubeId !== null) {
            return [
                "modo" => "imagen",
                "url" => "https://img.youtube.com/vi/" . rawurlencode($youtubeId) . "/hqdefault.jpg",
                "icono" => "bi-play-btn-fill",
                "nombre" => "Video"
            ];
        }
    }

    return [
        "modo" => "portada",
        "url" => "",
        "icono" => $GLOBALS["tipoIconos"][$tipo] ?? "bi-link-45deg",
        "nombre" => $GLOBALS["tipoNombres"][$tipo] ?? ucfirst($tipo)
    ];
}

$urlDashboard = urlAplicacion("/estudiante/dashboard.php");
$gradoRetorno = $returnGrado !== ""
    ? $returnGrado
    : (string)($tema["grado"] ?? "");

$idMateriaRetorno = $returnMateria !== null
    ? $returnMateria
    : (int)($tema["id_materia"] ?? 0);

$idUnidadRetorno = $returnUnidad !== null
    ? $returnUnidad
    : (int)($tema["id_unidad"] ?? 0);

$urlGrado = urlAplicacion(
    "/estudiante/grado.php?grado=" .
    urlencode($gradoRetorno) .
    "&id_materia=" .
    $idMateriaRetorno .
    ($idUnidadRetorno > 0 ? "&id_unidad=" . $idUnidadRetorno : "")
);
$urlLogout = urlAplicacion("/cerrar_sesion.php");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0d6efd">

<title><?= e($tema["tema"] ?? "Tema") ?> | Studia360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
    --primary:#0d6efd;
    --primary-dark:#084298;
    --bg:#f4f7fb;
    --text:#172033;
    --muted:#667085;
    --border:#e4e9f1;
    --success:#198754;
}

*{box-sizing:border-box}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.navbar-studia{
    background:linear-gradient(110deg,#0b1f3a,#0d6efd);
    box-shadow:0 5px 22px rgba(13,110,253,.20);
}

.brand{
    font-weight:800;
    letter-spacing:-.02em;
}

.page{
    width:min(1250px,calc(100% - 32px));
    margin:0 auto;
    padding:30px 0 70px;
}

.breadcrumbs{
    color:var(--muted);
    font-size:.9rem;
    margin-bottom:15px;
}

.breadcrumbs a{
    color:var(--primary);
    text-decoration:none;
    font-weight:600;
}

.hero{
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,#0d6efd,#084298);
    color:#fff;
    border-radius:25px;
    padding:32px;
    box-shadow:0 18px 45px rgba(13,110,253,.22);
    margin-bottom:24px;
}

.hero:after{
    content:"";
    position:absolute;
    width:230px;
    height:230px;
    border-radius:50%;
    right:-80px;
    top:-100px;
    background:rgba(255,255,255,.10);
}

.hero-content{
    position:relative;
    z-index:1;
}

.subject-badge{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
    font-size:.82rem;
    font-weight:700;
}

.hero h1{
    font-weight:850;
    letter-spacing:-.035em;
    margin:15px 0 8px;
}

.hero-description{
    max-width:780px;
    opacity:.82;
    margin:0;
}

.progress-box{
    margin-top:24px;
    max-width:720px;
}

.progress-box .progress{
    height:10px;
    background:rgba(255,255,255,.18);
    border-radius:99px;
}

.progress-box .progress-bar{
    background:#fff;
}

.progress-label{
    display:flex;
    justify-content:space-between;
    font-size:.82rem;
    margin-bottom:7px;
    font-weight:700;
}

.layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 330px;
    gap:24px;
}

.card-studia{
    background:#fff;
    border:1px solid var(--border);
    border-radius:21px;
    box-shadow:0 10px 30px rgba(20,35,60,.07);
}

.content-card{
    overflow:hidden;
}

.content-header{
    padding:18px 22px;
    border-bottom:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
}

.content-header h2{
    font-size:1.05rem;
    font-weight:800;
    margin:0;
}

.content-body{
    padding:28px;
}

.lesson-content{
    font-size:1.04rem;
    line-height:1.75;
    overflow-wrap:anywhere;
}

.lesson-content img{
    max-width:100%;
    height:auto;
    border-radius:12px;
}

.lesson-content iframe{
    max-width:100%;
    width:100%;
    min-height:380px;
    border:0;
    border-radius:15px;
}

.lesson-content table{
    width:100%;
    margin:1rem 0;
}

.lesson-content a{
    color:var(--primary);
}

.empty-content{
    text-align:center;
    padding:55px 20px;
    color:var(--muted);
}

.empty-content i{
    font-size:3rem;
    opacity:.35;
}

.resources{
    margin-top:28px;
}

.resources-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:16px;
    margin-bottom:16px;
}

.resources-kicker{
    color:var(--primary);
    font-size:.74rem;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:4px;
}

.resources-title{
    font-size:1.2rem;
    font-weight:850;
    color:#172033;
    margin:0;
}

.resources-subtitle{
    color:var(--muted);
    font-size:.88rem;
    margin:4px 0 0;
}

.resources-count{
    flex:none;
}

.resources-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
}

.resource-card{
    position:relative;
    overflow:hidden;
    min-width:0;
    border:1px solid #e3eaf3;
    border-radius:20px;
    background:#fff;
    box-shadow:0 8px 24px rgba(20,35,60,.06);
    transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    display:flex;
    flex-direction:column;
}

.resource-card:hover{
    transform:translateY(-4px);
    border-color:#c8dafa;
    box-shadow:0 16px 35px rgba(20,35,60,.11);
}

.resource-visual{
    position:relative;
    height:175px;
    overflow:hidden;
    background:linear-gradient(135deg,#0d6efd,#084298);
}

.resource-thumb{
    width:100%;
    height:100%;
    display:block;
    object-fit:cover;
    background:#eaf2ff;
}

.resource-cover{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#0d6efd 0%,#084298 100%);
}

.resource-cover::before{
    content:"";
    position:absolute;
    width:190px;
    height:190px;
    border-radius:50%;
    right:-70px;
    top:-105px;
    background:rgba(255,255,255,.13);
}

.resource-cover::after{
    content:"";
    position:absolute;
    width:130px;
    height:130px;
    border-radius:50%;
    left:-65px;
    bottom:-80px;
    background:rgba(255,255,255,.08);
}

.resource-video{
    background:linear-gradient(135deg,#111827,#26364d);
}

.resource-cover-icon{
    position:relative;
    z-index:2;
    width:76px;
    height:76px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(255,255,255,.25);
    border-radius:22px;
    background:rgba(255,255,255,.14);
    backdrop-filter:blur(8px);
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,.12);
}

.resource-cover-icon i{
    display:block;
    font-size:2rem;
    line-height:1;
    margin-bottom:5px;
}

.resource-cover-icon span{
    display:block;
    font-size:.58rem;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.resource-type{
    position:absolute;
    z-index:3;
    top:12px;
    left:12px;
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:6px 10px;
    border-radius:999px;
    background:rgba(17,24,39,.76);
    color:#fff;
    font-size:.68rem;
    font-weight:800;
    backdrop-filter:blur(7px);
}

.resource-open-icon{
    position:absolute;
    z-index:3;
    top:12px;
    right:12px;
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    background:#fff;
    color:var(--primary);
    box-shadow:0 6px 16px rgba(0,0,0,.14);
}

.resource-info{
    padding:16px 17px 17px;
    display:flex;
    flex-direction:column;
    flex:1;
}

.resource-title{
    font-weight:850;
    font-size:1rem;
    color:#172033;
    margin-bottom:5px;
    line-height:1.3;
}

.resource-description{
    color:var(--muted);
    font-size:.86rem;
    line-height:1.55;
    margin-bottom:14px;
}

.resource-action{
    margin-top:auto;
}

.resource-action .btn{
    width:100%;
    border-radius:11px;
    font-weight:750;
    padding:9px 12px;
}

@media(max-width:700px){
    .resources-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:575px){
    .resources-header{
        align-items:flex-start;
    }

    .resource-visual{
        height:155px;
    }

    .resource-info{
        padding:14px;
    }
}

.resource-title{
    font-weight:800;
    margin-bottom:4px;
}

.resource-description{
    color:var(--muted);
    font-size:.88rem;
}

.sidebar{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.side-card{
    padding:20px;
}

.xp-card{
    background:linear-gradient(135deg,#172033,#263b5c);
    color:#fff;
    border:0;
}

.xp-number{
    font-size:2rem;
    font-weight:850;
    letter-spacing:-.04em;
}

.xp-label{
    opacity:.7;
    font-size:.8rem;
}

.level-progress{
    height:8px;
    background:rgba(255,255,255,.15);
}

.level-progress .progress-bar{
    background:#fff;
}

.stat-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 0;
    border-bottom:1px solid var(--border);
}

.stat-row:last-child{
    border-bottom:0;
}

.stat-label{
    color:var(--muted);
    font-size:.86rem;
}

.stat-value{
    font-weight:800;
}

.complete-box{
    border:1px solid #bfe3cf;
    background:#f1fbf5;
    border-radius:17px;
    padding:17px;
}

.complete-box.done{
    border-color:#b7d8ff;
    background:#f0f6ff;
}

.btn-complete{
    border-radius:12px;
    font-weight:750;
    padding:11px 15px;
}

.evaluation-card{
    border:1px solid var(--border);
    border-radius:16px;
    padding:16px;
    background:#fff;
}

.evaluation-title{
    font-weight:800;
}

@media(max-width:900px){
    .layout{grid-template-columns:1fr}
    .sidebar{order:-1}
}

@media(max-width:575px){
    .page{width:min(100% - 20px,1250px);padding-top:20px}
    .hero{padding:24px;border-radius:20px}
    .content-body{padding:19px}
    .resource-card{align-items:flex-start}
    .resource-thumb{width:100%;height:100%}
}
</style>
<style id="studia360-common-style">
:root{
  --sd-accent:#2563eb;
  --sd-accent-2:#4f46e5;
  --sd-accent-soft:#eff6ff;
  --sd-bg:#f7f9fc;
  --sd-card:#ffffff;
  --sd-text:#1f2a3d;
  --sd-muted:#7b8798;
  --sd-line:#e6ebf2;
  --sd-success:#10b981;
  --sd-warning:#f59e0b;
  --sd-danger:#ef4444;
  --sd-shadow:0 12px 34px rgba(31,55,86,.055);
  --sd-shadow-hover:0 18px 42px rgba(31,55,86,.09);
  --sd-radius:20px;
}
html{scroll-behavior:smooth}
body.sd-page{
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 8%,transparent),transparent 28rem),
    var(--sd-bg)!important;
  color:var(--sd-text);
  transition:background .25s,color .25s;
}
.sd-navbar{
  position:sticky;top:0;z-index:1030;
  min-height:68px;
  background:rgba(255,255,255,.9)!important;
  border-bottom:1px solid var(--sd-line)!important;
  box-shadow:0 5px 24px rgba(31,55,86,.035);
  backdrop-filter:blur(15px);
}
.sd-brand{
  display:flex;align-items:center;gap:9px;
  color:var(--sd-text)!important;font-weight:850!important;
  letter-spacing:-.035em;text-decoration:none;
}
.sd-logo{
  width:38px;height:38px;border-radius:12px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 8px 18px color-mix(in srgb,var(--sd-accent) 22%,transparent);
}
.sd-nav-actions{display:flex;align-items:center;gap:7px}
.sd-nav-btn{
  height:38px;border:1px solid var(--sd-line);background:#fff!important;
  color:#637086!important;border-radius:11px!important;
  font-size:.73rem!important;font-weight:750!important;
}
.sd-nav-btn:hover{background:var(--sd-accent-soft)!important;color:var(--sd-accent)!important}
.sd-avatar-mini{
  width:34px;height:34px;border-radius:50%;overflow:hidden;
  display:grid;place-items:center;
  background:var(--sd-accent-soft);color:var(--sd-accent);
  font-weight:850;border:2px solid #fff;
  box-shadow:0 2px 9px rgba(20,40,70,.08);
}
.sd-avatar-mini img{width:100%;height:100%;object-fit:cover}
.sd-shell{width:min(1240px,calc(100% - 30px));margin:auto;padding:27px 0 55px}
.sd-page-title{font-size:1.65rem;font-weight:850;letter-spacing:-.04em;margin:0}
.sd-page-sub{font-size:.78rem;color:var(--sd-muted);margin:.25rem 0 0}
.sd-card{
  background:var(--sd-card)!important;
  border:1px solid var(--sd-line)!important;
  border-radius:var(--sd-radius)!important;
  box-shadow:var(--sd-shadow)!important;
}
.sd-card:hover{box-shadow:var(--sd-shadow-hover)!important}
.sd-btn{
  border-radius:10px!important;font-size:.74rem!important;
  font-weight:750!important;padding:.52rem .76rem!important;
}
.sd-btn-primary{
  color:#fff!important;background:var(--sd-accent)!important;border-color:var(--sd-accent)!important;
  box-shadow:0 7px 16px color-mix(in srgb,var(--sd-accent) 14%,transparent);
}
.sd-btn-outline{
  color:var(--sd-accent)!important;background:var(--sd-card)!important;border:1px solid color-mix(in srgb,var(--sd-accent) 22%,var(--sd-line))!important;
}
.sd-btn-outline:hover{background:var(--sd-accent-soft)!important}
.sd-form .form-control,.sd-form .form-select{
  border:1px solid var(--sd-line)!important;border-radius:11px!important;
  min-height:40px;box-shadow:none!important;
}
.sd-form .form-control:focus,.sd-form .form-select:focus{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff)!important;
  box-shadow:0 0 0 4px color-mix(in srgb,var(--sd-accent) 9%,transparent)!important;
}
.sd-muted{color:var(--sd-muted)!important}
.sd-eyebrow{
  display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;
  color:var(--sd-accent);background:var(--sd-accent-soft);
  border:1px solid color-mix(in srgb,var(--sd-accent) 12%,#fff);
  font-size:.61rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em;
}
.sd-hero{
  position:relative;overflow:hidden;color:#fff;
  border-radius:25px;padding:27px 29px;
  background:
    radial-gradient(circle at 90% 12%,rgba(255,255,255,.15),transparent 17rem),
    linear-gradient(125deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 18px 44px color-mix(in srgb,var(--sd-accent) 15%,transparent);
}
.sd-hero:after{
  content:"";position:absolute;width:200px;height:200px;border-radius:50%;
  right:-75px;bottom:-110px;border:27px solid rgba(255,255,255,.055);
}
.sd-hero>*{position:relative;z-index:1}
.sd-fade{animation:sdFade .38s ease both}
@keyframes sdFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.sd-theme-toggle{
  position:fixed;right:20px;bottom:20px;z-index:1045;
  width:48px;height:48px;border:0;border-radius:16px;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(145deg,var(--sd-accent),var(--sd-accent-2));
  box-shadow:0 12px 28px color-mix(in srgb,var(--sd-accent) 25%,transparent);
  cursor:pointer;transition:transform .2s,box-shadow .2s;
}
.sd-theme-toggle:hover{transform:translateY(-3px) rotate(4deg)}
.sd-theme-panel{
  position:fixed;right:20px;bottom:78px;z-index:1046;width:285px;
  padding:15px;background:var(--sd-card);border:1px solid var(--sd-line);
  border-radius:18px;box-shadow:0 20px 50px rgba(20,35,60,.15);
  transform:translateY(8px) scale(.98);opacity:0;pointer-events:none;
  transition:.2s;
}
.sd-theme-panel.open{transform:none;opacity:1;pointer-events:auto}
.sd-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.sd-theme-sub{font-size:.65rem;color:var(--sd-muted);margin-bottom:12px}
.sd-theme-row{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.sd-theme-option{
  border:1px solid var(--sd-line);background:var(--sd-card);border-radius:12px;
  padding:9px;text-align:left;cursor:pointer;color:var(--sd-text);
}
.sd-theme-option:hover,.sd-theme-option.active{
  border-color:color-mix(in srgb,var(--sd-accent) 45%,#fff);
  background:var(--sd-accent-soft);
}
.sd-theme-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.sd-theme-option strong{display:block;font-size:.66rem}
.sd-theme-option small{font-size:.57rem;color:var(--sd-muted)}
.sd-theme-mode{
  width:100%;margin-top:8px;border:1px solid var(--sd-line);background:var(--sd-card);
  border-radius:12px;padding:8px;color:var(--sd-text);font-size:.68rem;font-weight:750;
  cursor:pointer;text-align:left;
}

/* Student badges/titles */
.sd-title-row{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
.sd-title-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 8px;border-radius:999px;
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.18);
  color:#fff;font-size:.62rem;font-weight:800;
}
.sd-title-badge.dark{
  color:#795f13;background:#fff8df;border-color:#efdfaa;
}
.sd-title-badge i{font-size:.65rem}

/* Dark mode */
body.sd-dark{
  --sd-bg:#111827;--sd-card:#182235;--sd-text:#edf2f7;--sd-muted:#9aa7ba;--sd-line:#2a3549;
  background:
    radial-gradient(circle at 90% 0%,color-mix(in srgb,var(--sd-accent) 13%,transparent),transparent 28rem),
    var(--sd-bg)!important;
}
body.sd-dark .sd-navbar{background:rgba(17,24,39,.88)!important}
body.sd-dark .sd-nav-btn{background:#182235!important;color:#aab5c5!important}
body.sd-dark .sd-card,
body.sd-dark .card,
body.sd-dark .cardx,
body.sd-dark .panel,
body.sd-dark .subject,
body.sd-dark .quick,
body.sd-dark .level-card,
body.sd-dark .explore,
body.sd-dark .resource-card,
body.sd-dark .evaluation-card,
body.sd-dark .stat,
body.sd-dark .avatar-option,
body.sd-dark .message-card{background:var(--sd-card)!important;color:var(--sd-text)!important}
body.sd-dark .form-control,body.sd-dark .form-select{
  background:#111827!important;color:var(--sd-text)!important;border-color:var(--sd-line)!important;
}
body.sd-dark .form-control::placeholder{color:#718096}
body.sd-dark .text-dark,body.sd-dark h1,body.sd-dark h2,body.sd-dark h3,body.sd-dark h4,body.sd-dark h5,
body.sd-dark .fw-bold,body.sd-dark .fw-semibold{color:var(--sd-text)!important}
body.sd-dark .text-muted,body.sd-dark .muted{color:var(--sd-muted)!important}
body.sd-dark .sd-theme-option,body.sd-dark .sd-theme-mode{background:var(--sd-card);color:var(--sd-text)}
body.sd-dark .table{--bs-table-bg:var(--sd-card);--bs-table-color:var(--sd-text)}
body.sd-dark .dropdown-menu{background:#182235;border-color:var(--sd-line)}
body.sd-dark .dropdown-item{color:#dce4ee}
body.sd-dark .dropdown-item:hover{background:#202d42}
body.sd-dark .bg-light{background:#202d42!important}
body.sd-dark .border{border-color:var(--sd-line)!important}

/* Accent themes */
body.sd-accent-orange{--sd-accent:#f97316;--sd-accent-2:#ea580c;--sd-accent-soft:#fff7ed}
body.sd-accent-purple{--sd-accent:#8b5cf6;--sd-accent-2:#7c3aed;--sd-accent-soft:#f5f3ff}
body.sd-accent-green{--sd-accent:#10b981;--sd-accent-2:#059669;--sd-accent-soft:#ecfdf5}

@media(max-width:767px){
  .sd-shell{width:min(100% - 20px,1240px);padding-top:19px}
  .sd-page-title{font-size:1.4rem}
  .sd-theme-toggle{right:14px;bottom:14px}
  .sd-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation:none!important;transition:none!important}
}
</style>
<link rel="stylesheet" href="studia360-estudiante.css">
</head>

<body class="sd-page">


<nav class="navbar sd-navbar">
  <div class="container-fluid px-3 px-lg-4">
    <a href="dashboard.php" class="sd-brand">
      <span class="sd-logo"><i class="bi bi-stars"></i></span>
      <span>Studia360</span>
    </a>
    <div class="sd-nav-actions">
      <div class="dropdown">
        <button class="btn sd-nav-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Explorar</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm p-2" style="border-radius:14px">
          <li><a class="dropdown-item rounded-2 small" href="dashboard.php"><i class="bi bi-house me-2"></i>Inicio</a></li>
          <li><a class="dropdown-item rounded-2 small" href="grado.php?grado=9"><i class="bi bi-book me-2"></i>Materias y temas</a></li>
          <li><a class="dropdown-item rounded-2 small" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
          <li><a class="dropdown-item rounded-2 small" href="sugerencias.php"><i class="bi bi-chat-left-heart me-2"></i>Quejas y recomendaciones</a></li>
          <li><a class="dropdown-item rounded-2 small" href="recuperacion.php"><i class="bi bi-key me-2"></i>Notificaciones</a></li>
        </ul>
      </div>
      <a href="perfil.php" class="sd-avatar-mini d-none d-sm-grid" title="Mi perfil">
        <?php if (!empty($foto)): ?><img src="<?=h($foto)?>" alt="Mi perfil"><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
      </a>
      <a href="perfil.php" class="btn sd-nav-btn d-none d-md-inline-flex"><i class="bi bi-person me-1"></i>Perfil</a>
      <a href="../cerrar_sesion.php" class="btn sd-nav-btn" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Salir</span></a>
    </div>
  </div>
</nav>


<main class="page">

<?php if (!empty($errores)): ?>
    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($mensaje !== ""): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?= e($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($tema): ?>

<div class="breadcrumbs">
    <a href="<?= e($urlDashboard) ?>">Inicio</a>
    <span class="mx-1">/</span>
    <a href="<?= e(urlAplicacion('/estudiante/grado.php?grado=' . urlencode((string)$tema['grado']) . '&id_materia=' . (int)$tema['id_materia'])) ?>">
        <?= e($tema["materia"]) ?>
    </a>
    <?php if (!empty($tema["unidad"])): ?>
        <span class="mx-1">/</span>
        <a href="<?= e($urlGrado) ?>"><?= e($tema["unidad"]) ?></a>
    <?php endif; ?>
    <span class="mx-1">/</span>
    <?= e($tema["tema"]) ?>
</div>

<section class="hero">
<div class="hero-content">

    <span class="subject-badge">
        <i class="bi bi-book-fill"></i>
        <?= e($tema["materia"]) ?>
        ·
        <?= e($tema["grado"]) ?>°
    </span>

    <h1><?= e($tema["tema"]) ?></h1>

    <?php if (!empty($tema["descripcion"])): ?>
        <p class="hero-description">
            <?= e($tema["descripcion"]) ?>
        </p>
    <?php endif; ?>

    <div class="progress-box">

        <div class="progress-label">
            <span>Tu progreso</span>
            <span><?= number_format($avance, 0) ?>%</span>
        </div>

        <div class="progress">
            <div
                class="progress-bar"
                style="width:<?= e($avance) ?>%"
            ></div>
        </div>

    </div>

</div>
</section>

<div class="layout">

<section>

    <article class="card-studia content-card">

        <div class="content-header">

            <h2>
                <i class="bi bi-journal-text text-primary me-2"></i>
                Contenido del tema
            </h2>

            <?php if ($completado): ?>
                <span class="badge text-bg-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Completado
                </span>
            <?php else: ?>
                <span class="badge text-bg-light border">
                    En progreso
                </span>
            <?php endif; ?>

        </div>

        <div class="content-body">

            <?php if ($contenido && trim($contenido["contenido"]) !== ""): ?>

                <div class="lesson-content">
                    <?= $contenido["contenido"] ?>
                </div>

            <?php else: ?>

                <div class="empty-content">
                    <i class="bi bi-journal-x"></i>

                    <h3 class="h5 mt-3">
                        Este tema todavía no tiene contenido publicado
                    </h3>

                    <p class="mb-0">
                        El contenido estará disponible cuando sea publicado por el administrador.
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </article>


    <?php if (!empty($recursos)): ?>

    <section class="resources">

        <div class="resources-header">

            <div>
                <div class="resources-kicker">
                    <i class="bi bi-collection-play me-1"></i>
                    Recursos
                </div>

                <h2 class="resources-title">
                    Material complementario
                </h2>

                <p class="resources-subtitle">
                    Explora materiales para reforzar y ampliar lo aprendido en este tema.
                </p>
            </div>

            <span class="badge rounded-pill text-bg-light border resources-count">
                <?= count($recursos) ?>
                <?= count($recursos) === 1 ? "recurso" : "recursos" ?>
            </span>

        </div>

        <div class="resources-grid">

        <?php foreach ($recursos as $recurso): ?>

            <?php
                $tipo = strtolower((string)$recurso["tipo"]);
                $icono = $tipoIconos[$tipo] ?? "bi-link-45deg";
                $tipoNombre = $tipoNombres[$tipo] ?? ucfirst($tipo);
                $portada = portadaRecurso($recurso);
            ?>

            <article class="resource-card">

                <div class="resource-visual">

                    <span class="resource-type">
                        <i class="bi <?= e($icono) ?>"></i>
                        <?= e($tipoNombre) ?>
                    </span>

                    <span class="resource-open-icon" aria-hidden="true">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </span>

                    <?php if ($portada["modo"] === "imagen"): ?>

                        <img
                            src="<?= e($portada["url"]) ?>"
                            alt="Portada de <?= e($recurso["titulo"]) ?>"
                            class="resource-thumb"
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        >

                        <div
                            class="resource-cover <?= $tipo === 'video' ? 'resource-video' : '' ?>"
                            style="display:none"
                            aria-hidden="true"
                        >
                            <div class="resource-cover-icon">
                                <i class="bi <?= e($portada["icono"]) ?>"></i>
                                <span><?= e($portada["nombre"]) ?></span>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="resource-cover <?= $tipo === 'video' ? 'resource-video' : '' ?>">
                            <div class="resource-cover-icon">
                                <i class="bi <?= e($portada["icono"]) ?>"></i>
                                <span><?= e($portada["nombre"]) ?></span>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="resource-info">

                    <div class="resource-title">
                        <?= e($recurso["titulo"]) ?>
                    </div>

                    <?php if (!empty($recurso["descripcion"])): ?>
                        <div class="resource-description">
                            <?= e($recurso["descripcion"]) ?>
                        </div>
                    <?php else: ?>
                        <div class="resource-description">
                            Material complementario para este tema.
                        </div>
                    <?php endif; ?>

                    <div class="resource-action">

                        <form
                            method="POST"
                            target="_blank"
                            class="resource-form"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="accion"
                                value="recurso"
                            >

                            <input
                                type="hidden"
                                name="id_recurso"
                                value="<?= (int)$recurso["id_recurso"] ?>"
                            >

                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-up-right-circle me-1"></i>
                                Abrir recurso
                            </button>

                        </form>

                    </div>

                </div>

            </article>

        <?php endforeach; ?>

        </div>

    </section>

    <?php endif; ?>


    <?php if (!empty($evaluaciones)): ?>

    <section class="resources">

        <div class="mb-3">

            <div class="text-uppercase text-primary fw-bold small">
                Evaluaciones
            </div>

            <h2 class="h5 fw-bold mb-0">
                Comprueba lo que aprendiste
            </h2>

        </div>

        <div class="d-grid gap-3">

        <?php foreach ($evaluaciones as $evaluacion): ?>

            <article class="evaluation-card">

                <div class="d-flex justify-content-between gap-3">

                    <div>

                        <div class="evaluation-title">
                            <?= e($evaluacion["titulo"]) ?>
                        </div>

                        <?php if (!empty($evaluacion["descripcion"])): ?>
                            <p class="text-secondary small mt-1 mb-0">
                                <?= e($evaluacion["descripcion"]) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                    <i class="bi bi-ui-checks-grid text-primary fs-4"></i>

                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">

                    <?php if ($evaluacion["puntaje_maximo"] !== null): ?>
                        <span class="badge text-bg-light border">
                            <i class="bi bi-star me-1"></i>
                            <?= e($evaluacion["puntaje_maximo"]) ?> puntos
                        </span>
                    <?php endif; ?>

                    <?php if (($evaluacion["tiempo_minutos"] ?? null) !== null): ?>
                        <span class="badge text-bg-light border">
                            <i class="bi bi-clock me-1"></i>
                            <?= e($evaluacion["tiempo_minutos"]) ?> min
                        </span>
                    <?php endif; ?>

                </div>

                <div class="mt-3">
                    <a
                        href="<?= e(
                            urlAplicacion(
                                "/estudiante/evaluacion.php?id=" .
                                (int)$evaluacion["id_evaluacion"]
                            )
                        ) ?>"
                        class="btn btn-outline-primary"
                    >
                        Realizar evaluación
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

            </article>

        <?php endforeach; ?>

        </div>

    </section>

    <?php endif; ?>

</section>


<aside class="sidebar">

    <div class="card-studia side-card xp-card">

        <div class="xp-label text-uppercase fw-bold">
            Tu experiencia
        </div>

        <div class="xp-number">
            <?= number_format((int)$gamificacion["puntos"]) ?>
            <span class="fs-6 fw-normal opacity-75">XP</span>
        </div>

        <div class="fw-bold mb-2">
            <?= e($gamificacion["nivel"]["nombre"]) ?>
        </div>

        <div class="progress level-progress">
            <div
                class="progress-bar"
                style="width:<?= e($gamificacion["progreso_nivel"]) ?>%"
            ></div>
        </div>

        <div class="d-flex justify-content-between small opacity-75 mt-2">
            <span>
                <?= number_format((int)$gamificacion["nivel"]["puntos_minimos"]) ?> XP
            </span>

            <?php if ($gamificacion["puntos_siguiente_nivel"] !== null): ?>
                <span>
                    <?= number_format((int)$gamificacion["puntos_siguiente_nivel"]) ?> XP
                </span>
            <?php else: ?>
                <span>Máximo nivel</span>
            <?php endif; ?>
        </div>

    </div>


    <div class="card-studia side-card">

        <h3 class="h6 fw-bold mb-3">
            <i class="bi bi-bar-chart-fill text-primary me-2"></i>
            Progreso del tema
        </h3>

        <div class="stat-row">
            <span class="stat-label">Avance</span>
            <span class="stat-value">
                <?= number_format($avance, 0) ?>%
            </span>
        </div>

        <div class="stat-row">
            <span class="stat-label">Recursos vistos</span>
            <span class="stat-value">
                <?= (int)$progreso["recursos_vistos"] ?>
            </span>
        </div>

    </div>


    <div class="card-studia side-card">

        <?php if ($completado): ?>

            <div class="complete-box done">

                <div class="fw-bold">
                    <i class="bi bi-patch-check-fill text-primary me-1"></i>
                    Tema completado
                </div>

                <div class="small text-secondary mt-1">
                    Este tema ya forma parte de tu progreso.
                </div>

            </div>

        <?php else: ?>

            <div class="complete-box">

                <div class="fw-bold">
                    <i class="bi bi-flag-fill text-success me-1"></i>
                    ¿Terminaste de estudiar?
                </div>

                <div class="small text-secondary mt-1 mb-3">
                    Cuando hayas revisado el contenido y los recursos,
                    marca el tema como completado.
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="completar"
                    >

                    <button
                        type="submit"
                        class="btn btn-success w-100 btn-complete"
                    >
                        <i class="bi bi-check2-circle me-1"></i>
                        Marcar como completado
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

</aside>

</div>

<?php endif; ?>

</main>

<script>
/*
 * Abrir recursos:
 * el formulario registra la interacción y luego el navegador
 * abre el recurso mediante la URL original.
 */
document.querySelectorAll('form[target="_blank"]').forEach(function(form){
    form.addEventListener("submit", function(event){
        event.preventDefault();

        const data = new FormData(this);

        fetch(window.location.href, {
            method: "POST",
            body: data,
            credentials: "same-origin",
            keepalive: true
        }).catch(function(){});

        const resourceButton = this.querySelector("button");

        if (resourceButton) {
            const card = this.closest(".resource-card");
            const resourceUrl = <?= json_encode(
                array_column($recursos, "url", "id_recurso"),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?>;

            const id = data.get("id_recurso");

            if (id && resourceUrl[id]) {
                window.open(resourceUrl[id], "_blank");
            }
        }
    });
});
</script>


<button id="sdThemeToggle" class="sd-theme-toggle" type="button" aria-label="Cambiar apariencia" title="Personalizar apariencia">
  <i class="bi bi-palette2"></i>
</button>
<div id="sdThemePanel" class="sd-theme-panel" aria-label="Personalizar apariencia">
  <div class="sd-theme-title">Personaliza Studia360</div>
  <div class="sd-theme-sub">Elige el color que te guste y decide si prefieres fondo claro u oscuro.</div>
  <div class="sd-theme-row">
    <button class="sd-theme-option" data-theme="blue" onclick="studiaTheme('blue')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
    <button class="sd-theme-option" data-theme="orange" onclick="studiaTheme('orange')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
    <button class="sd-theme-option" data-theme="purple" onclick="studiaTheme('purple')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Creativo</small></button>
    <button class="sd-theme-option" data-theme="green" onclick="studiaTheme('green')"><div class="sd-theme-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
  </div>
  <button id="sdThemeMode" class="sd-theme-mode" type="button" onclick="studiaThemeMode()"></button>
</div>

<script>
(function(){
  const body=document.body;
  const key="studia360_theme";
  const themes={
    blue:{label:"Azul",className:""},
    orange:{label:"Naranja",className:"sd-accent-orange"},
    purple:{label:"Violeta",className:"sd-accent-purple"},
    green:{label:"Verde",className:"sd-accent-green"}
  };
  function applyTheme(theme,mode){
    Object.values(themes).forEach(t=>{if(t.className) body.classList.remove(t.className)});
    if(themes[theme]?.className) body.classList.add(themes[theme].className);
    body.classList.toggle("sd-dark",mode==="dark");
    document.querySelectorAll(".sd-theme-option").forEach(el=>el.classList.toggle("active",el.dataset.theme===theme));
    const modeBtn=document.getElementById("sdThemeMode");
    if(modeBtn) modeBtn.innerHTML=(mode==="dark"?"<i class='bi bi-moon-stars me-2'></i>Modo oscuro":"<i class='bi bi-sun me-2'></i>Modo claro");
  }
  let saved={theme:"blue",mode:"light"};
  try{saved=Object.assign(saved,JSON.parse(localStorage.getItem(key)||"{}"));}catch(e){}
  applyTheme(saved.theme,saved.mode);
  window.studiaTheme=function(theme){
    saved.theme=theme;
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  window.studiaThemeMode=function(){
    saved.mode=saved.mode==="dark"?"light":"dark";
    try{localStorage.setItem(key,JSON.stringify(saved));}catch(e){}
    applyTheme(saved.theme,saved.mode);
  };
  const toggle=document.getElementById("sdThemeToggle");
  const panel=document.getElementById("sdThemePanel");
  toggle?.addEventListener("click",()=>panel?.classList.toggle("open"));
  document.addEventListener("click",e=>{
    if(panel && panel.classList.contains("open") && !panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove("open");
  });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

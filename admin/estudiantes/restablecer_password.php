<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


// =====================================================
// VALIDAR ID DEL ESTUDIANTE
// =====================================================

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit;
}

$id_usuario = (int) $_GET["id"];


// =====================================================
// BUSCAR ESTUDIANTE
// =====================================================

try {

    $sql = "SELECT
                id_usuario,
                nombres,
                apellidos,
                numero_documento,
                correo,
                estado
            FROM usuarios
            WHERE id_usuario = :id
            AND id_rol = 2
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ":id" => $id_usuario
    ]);

    $estudiante = $stmt->fetch();

    if (!$estudiante) {

        header("Location: index.php");
        exit;
    }

} catch (PDOException $e) {

    die("Error al consultar el estudiante.");
}


// =====================================================
// PROCESAR RESTABLECIMIENTO
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /*
        --------------------------------------------------
        La contraseña temporal será el número de documento
        --------------------------------------------------
        */

        $password_temporal = $estudiante["numero_documento"];

        /*
        --------------------------------------------------
        Convertimos la contraseña a HASH
        --------------------------------------------------
        */

        $password_hash = password_hash(
            $password_temporal,
            PASSWORD_DEFAULT
        );


        /*
        --------------------------------------------------
        Actualizar estudiante
        --------------------------------------------------
        */

        $sql = "UPDATE usuarios
                SET
                    password = :password,
                    primer_ingreso = 1,
                    fecha_cambio_password = NULL
                WHERE id_usuario = :id
                AND id_rol = 2";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ":password" => $password_hash,
            ":id" => $id_usuario
        ]);


        /*
        --------------------------------------------------
        Registrar la acción en logs_sistema
        --------------------------------------------------
        */

        $sql_log = "INSERT INTO logs_sistema
                    (
                        id_usuario,
                        accion,
                        direccion_ip
                    )
                    VALUES
                    (
                        :id_usuario,
                        :accion,
                        :ip
                    )";

        $stmt_log = $conexion->prepare($sql_log);

        $stmt_log->execute([
            ":id_usuario" => $_SESSION["id_usuario"],
            ":accion" => "Restableció la contraseña del estudiante ID " . $id_usuario,
            ":ip" => $_SERVER["REMOTE_ADDR"] ?? null
        ]);


        /*
        --------------------------------------------------
        Mensaje de éxito
        --------------------------------------------------
        */

        header(
            "Location: index.php?mensaje=password_restablecida"
        );

        exit;

    } catch (PDOException $e) {

        $error = "No fue posible restablecer la contraseña.";
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Restablecer contraseña | Studia360
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

<link rel="stylesheet" href="<?= htmlspecialchars(urlAplicacion('/admin/assets/studia-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="estudiantes.css">
</head>


<body class="s360-admin class="bg-light"">


<nav class="navbar navbar-studia">
    <div class="container-fluid px-3 px-lg-4">
        <a href="../dashboard.php" class="navbar-brand d-flex align-items-center">
            <span class="brand-mark"><i class="bi bi-stars"></i></span>
            Studia360
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-grid-3x3-gap me-1"></i> Gestión
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="border-radius:14px">
                    <li><a class="dropdown-item rounded-2 small" href="index.php"><i class="bi bi-people me-2"></i>Estudiantes</a></li>
                    <li><a class="dropdown-item rounded-2 small" href="crear.php"><i class="bi bi-person-plus me-2"></i>Nuevo estudiante</a></li>
                    <li><a class="dropdown-item rounded-2 small" href="importar.php"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Importar</a></li>
                    <li><a class="dropdown-item rounded-2 small" href="plantilla.php"><i class="bi bi-download me-2"></i>Plantilla</a></li>
                </ul>
            </div>
            <a href="../../cerrar_sesion.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Salir</span></a>
        </div>
    </div>
</nav>


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-12 col-md-7 col-lg-5">

            <div class="card shadow-sm">

                <div class="card-header bg-warning">

                    <h5 class="mb-0">

                        <i class="bi bi-key-fill"></i>

                        Restablecer contraseña

                    </h5>

                </div>


                <div class="card-body">


                    <?php if (isset($error)): ?>

                        <div class="alert alert-danger">

                            <i class="bi bi-exclamation-triangle"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <p>
                        Vas a restablecer la contraseña del siguiente estudiante:
                    </p>


                    <div class="alert alert-light border">

                        <strong>

                            <?= htmlspecialchars(
                                $estudiante["nombres"] . " " .
                                $estudiante["apellidos"]
                            ) ?>

                        </strong>

                        <br>

                        <small class="text-muted">

                            Documento:

                            <?= htmlspecialchars(
                                $estudiante["numero_documento"]
                            ) ?>

                        </small>

                        <br>

                        <small class="text-muted">

                            Correo:

                            <?= htmlspecialchars(
                                $estudiante["correo"]
                            ) ?>

                        </small>

                    </div>


                    <div class="alert alert-info">

                        <i class="bi bi-info-circle"></i>

                        La contraseña será restablecida al
                        <strong>número de documento del estudiante</strong>.

                        <br><br>

                        El estudiante deberá cambiarla
                        obligatoriamente en su próximo inicio de sesión.

                    </div>


                    <form method="POST">


                        <div class="d-grid gap-2">

                            <button
                                type="submit"
                                class="btn btn-warning"
                            >

                                <i class="bi bi-key-fill"></i>

                                Restablecer contraseña

                            </button>


                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >

                                Cancelar

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Studia360 Admin: personalizador global -->
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
</body>

</html>
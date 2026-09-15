<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$exito = false;


/*
|--------------------------------------------------------------------------
| Obtener ID
|--------------------------------------------------------------------------
*/

$id_usuario = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id_usuario) {

    header("Location: index.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| Obtener cursos
|--------------------------------------------------------------------------
*/

try {

    $sqlCursos = "
        SELECT
            id_curso,
            grado,
            grupo
        FROM cursos
        WHERE estado = 'Activo'
        ORDER BY grado ASC, grupo ASC
    ";

    $stmtCursos = $conexion->query($sqlCursos);

    $cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("No fue posible cargar los cursos.");

}


/*
|--------------------------------------------------------------------------
| Obtener estudiante
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            id_usuario,
            nombres,
            apellidos,
            numero_documento,
            correo,
            grado,
            id_curso,
            estado
        FROM usuarios
        WHERE id_usuario = ?
        AND id_rol = 2
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $id_usuario
    ]);

    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {

        header("Location: index.php");
        exit;

    }

} catch (PDOException $e) {

    die("No fue posible cargar el estudiante.");

}


/*
|--------------------------------------------------------------------------
| Procesar formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombres = trim($_POST["nombres"] ?? "");
    $apellidos = trim($_POST["apellidos"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $grado = trim($_POST["grado"] ?? "");
    $id_curso = (int)($_POST["id_curso"] ?? 0);
    $estado = trim($_POST["estado"] ?? "Activo");


    /*
    |--------------------------------------------------------------------------
    | Validaciones
    |--------------------------------------------------------------------------
    */

    if ($nombres === "") {
        $errores[] = "Los nombres son obligatorios.";
    }

    if ($apellidos === "") {
        $errores[] = "Los apellidos son obligatorios.";
    }

    if (!in_array($grado, ["9", "10", "11"], true)) {
        $errores[] = "El grado seleccionado no es válido.";
    }

    if ($id_curso <= 0) {
        $errores[] = "Debes seleccionar un curso.";
    }

    if (!in_array($estado, ["Activo", "Inactivo"], true)) {
        $errores[] = "El estado seleccionado no es válido.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validar correo
    |--------------------------------------------------------------------------
    */

    if (
        $correo !== "" &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        $errores[] = "El correo electrónico no es válido.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validar curso
    |--------------------------------------------------------------------------
    */

    if ($id_curso > 0 && $grado !== "") {

        $sqlCurso = "
            SELECT id_curso, grado
            FROM cursos
            WHERE id_curso = ?
            AND estado = 'Activo'
            LIMIT 1
        ";

        $stmtCurso = $conexion->prepare($sqlCurso);

        $stmtCurso->execute([
            $id_curso
        ]);

        $curso = $stmtCurso->fetch(PDO::FETCH_ASSOC);

        if (!$curso) {

            $errores[] = "El curso seleccionado no existe o está inactivo.";

        } elseif ($curso["grado"] !== $grado) {

            $errores[] = "El grado no corresponde con el curso seleccionado.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $sqlUpdate = "
                UPDATE usuarios
                SET
                    nombres = ?,
                    apellidos = ?,
                    correo = ?,
                    grado = ?,
                    id_curso = ?,
                    estado = ?
                WHERE id_usuario = ?
                AND id_rol = 2
            ";

            $stmtUpdate = $conexion->prepare($sqlUpdate);

            $stmtUpdate->execute([
                $nombres,
                $apellidos,
                $correo !== "" ? $correo : null,
                $grado,
                $id_curso,
                $estado,
                $id_usuario
            ]);

            $exito = true;


            /*
            |------------------------------------------------------------------
            | Actualizar datos mostrados
            |------------------------------------------------------------------
            */

            $estudiante["nombres"] = $nombres;
            $estudiante["apellidos"] = $apellidos;
            $estudiante["correo"] = $correo;
            $estudiante["grado"] = $grado;
            $estudiante["id_curso"] = $id_curso;
            $estudiante["estado"] = $estado;

        } catch (PDOException $e) {

            $errores[] = "No fue posible actualizar el estudiante.";

        }

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
        Editar estudiante | Studia360
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


<div class="container py-4">

    <div class="mb-4">

        <h2>

            <i class="bi bi-pencil-square"></i>

            Editar estudiante

        </h2>

        <p class="text-muted">

            Modifica la información académica y personal del estudiante.

        </p>

    </div>


    <?php if ($exito): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill"></i>

            Estudiante actualizado correctamente.

        </div>

    <?php endif; ?>


    <?php if (!empty($errores)): ?>

        <div class="alert alert-danger">

            <strong>No fue posible actualizar:</strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errores as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">


                <div class="mb-3">

                    <label class="form-label">
                        Nombres
                    </label>

                    <input
                        type="text"
                        name="nombres"
                        class="form-control"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars($estudiante["nombres"]) ?>"
                    >

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Apellidos
                    </label>

                    <input
                        type="text"
                        name="apellidos"
                        class="form-control"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars($estudiante["apellidos"]) ?>"
                    >

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Número de documento
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($estudiante["numero_documento"]) ?>"
                        disabled
                    >

                    <div class="form-text">
                        El número de documento no puede modificarse desde esta pantalla.
                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        name="correo"
                        class="form-control"
                        maxlength="120"
                        value="<?= htmlspecialchars($estudiante["correo"] ?? "") ?>"
                    >

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Grado
                    </label>

                    <select
                        id="grado"
                        name="grado"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Seleccionar grado
                        </option>

                        <?php foreach (["9", "10", "11"] as $grado): ?>

                            <option
                                value="<?= $grado ?>"
                                <?= $estudiante["grado"] === $grado ? "selected" : "" ?>
                            >

                                <?= $grado ?>°

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Curso
                    </label>

                    <select
                        id="id_curso"
                        name="id_curso"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Seleccionar curso
                        </option>

                        <?php foreach ($cursos as $curso): ?>

                            <option
                                value="<?= (int)$curso["id_curso"] ?>"
                                data-grado="<?= htmlspecialchars($curso["grado"]) ?>"
                                <?= (int)$estudiante["id_curso"] === (int)$curso["id_curso"] ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($curso["grado"]) ?>°
                                -
                                <?= htmlspecialchars($curso["grupo"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-4">

                    <label class="form-label">
                        Estado
                    </label>

                    <select
                        name="estado"
                        class="form-select"
                    >

                        <option
                            value="Activo"
                            <?= $estudiante["estado"] === "Activo" ? "selected" : "" ?>
                        >
                            Activo
                        </option>

                        <option
                            value="Inactivo"
                            <?= $estudiante["estado"] === "Inactivo" ? "selected" : "" ?>
                        >
                            Inactivo
                        </option>

                    </select>

                </div>


                <div class="alert alert-warning">

                    <i class="bi bi-shield-lock-fill"></i>

                    <strong>Información protegida:</strong>

                    La contraseña, puntos, nivel, avatar y rol no se modifican desde esta pantalla.

                    Para cambiar la contraseña utiliza la opción
                    <strong>Restablecer</strong>.

                </div>


                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-save-fill"></i>

                        Guardar cambios

                    </button>


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Volver

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

const grado = document.getElementById("grado");

const curso = document.getElementById("id_curso");


function filtrarCursos() {

    const gradoSeleccionado = grado.value;

    Array.from(curso.options).forEach(function(opcion) {

        if (opcion.value === "") {

            opcion.hidden = false;

            return;

        }

        const gradoCurso = opcion.dataset.grado;

        opcion.hidden =
            gradoSeleccionado !== "" &&
            gradoCurso !== gradoSeleccionado;

    });


    const seleccion =
        curso.options[curso.selectedIndex];

    if (
        seleccion &&
        seleccion.dataset.grado &&
        seleccion.dataset.grado !== gradoSeleccionado
    ) {

        curso.value = "";

    }

}


grado.addEventListener(
    "change",
    filtrarCursos
);

filtrarCursos();

</script>


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
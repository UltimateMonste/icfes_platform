<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();

$errores = [];
$exito = false;


/*
|--------------------------------------------------------------------------
| Obtener cursos activos
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

    $consultaCursos = $conexion->query($sqlCursos);

    $cursos = $consultaCursos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $cursos = [];

    $errores[] = "No fue posible cargar los cursos.";
}


/*
|--------------------------------------------------------------------------
| Procesar formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombres = trim($_POST["nombres"] ?? "");
    $apellidos = trim($_POST["apellidos"] ?? "");
    $documento = trim($_POST["numero_documento"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $grado = trim($_POST["grado"] ?? "");
    $id_curso = (int)($_POST["id_curso"] ?? 0);


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

    if ($documento === "") {
        $errores[] = "El número de documento es obligatorio.";
    }

    if ($grado === "") {
        $errores[] = "Debes seleccionar el grado.";
    }

    if ($id_curso <= 0) {
        $errores[] = "Debes seleccionar un curso.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validar grado
    |--------------------------------------------------------------------------
    */

    $gradosPermitidos = ["9", "10", "11"];

    if (
        $grado !== "" &&
        !in_array($grado, $gradosPermitidos, true)
    ) {

        $errores[] = "El grado seleccionado no es válido.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validar documento
    |--------------------------------------------------------------------------
    */

    if (
        $documento !== "" &&
        !preg_match('/^[0-9]+$/', $documento)
    ) {

        $errores[] = "El número de documento debe contener solamente números.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validar curso
    |--------------------------------------------------------------------------
    */

    if ($id_curso > 0) {

        try {

            $sqlCurso = "
                SELECT
                    id_curso,
                    grado
                FROM cursos
                WHERE id_curso = ?
                AND estado = 'Activo'
                LIMIT 1
            ";

            $stmtCurso = $conexion->prepare($sqlCurso);

            $stmtCurso->execute([$id_curso]);

            $cursoSeleccionado = $stmtCurso->fetch(PDO::FETCH_ASSOC);


            if (!$cursoSeleccionado) {

                $errores[] = "El curso seleccionado no existe o está inactivo.";

            } elseif ($cursoSeleccionado["grado"] !== $grado) {

                $errores[] = "El grado no corresponde con el curso seleccionado.";

            }

        } catch (PDOException $e) {

            $errores[] = "No fue posible validar el curso.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Verificar documento existente
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $sqlExiste = "
                SELECT id_usuario
                FROM usuarios
                WHERE numero_documento = ?
                LIMIT 1
            ";

            $stmtExiste = $conexion->prepare($sqlExiste);

            $stmtExiste->execute([$documento]);

            if ($stmtExiste->fetch()) {

                $errores[] = "Ya existe un usuario con ese número de documento.";

            }

        } catch (PDOException $e) {

            $errores[] = "No fue posible verificar el documento.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Registrar estudiante
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            /*
            |--------------------------------------------------------------
            | Contraseña inicial = número de documento
            |--------------------------------------------------------------
            */

            $passwordHash = password_hash(
                $documento,
                PASSWORD_DEFAULT
            );


            /*
            |--------------------------------------------------------------
            | Valores iniciales del estudiante
            |--------------------------------------------------------------
            */

            $idRol = 2;
            $idAvatar = 1;
            $puntos = 0;
            $nivel = 1;
            $primerIngreso = 1;
            $estado = "Activo";


            /*
            |--------------------------------------------------------------
            | Insertar usuario
            |--------------------------------------------------------------
            */

            $sql = "
                INSERT INTO usuarios (
                    nombres,
                    apellidos,
                    correo,
                    password,
                    grado,
                    id_curso,
                    avatar,
                    id_avatar,
                    puntos,
                    nivel,
                    numero_documento,
                    id_rol,
                    primer_ingreso,
                    estado
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                $nombres,
                $apellidos,
                $correo !== "" ? $correo : null,
                $passwordHash,
                $grado,
                $id_curso,
                "avatar1.png",
                $idAvatar,
                $puntos,
                $nivel,
                $documento,
                $idRol,
                $primerIngreso,
                $estado
            ]);


            $exito = true;


            /*
            |--------------------------------------------------------------
            | Limpiar formulario
            |--------------------------------------------------------------
            */

            $nombres = "";
            $apellidos = "";
            $documento = "";
            $correo = "";
            $grado = "";
            $id_curso = 0;


        } catch (PDOException $e) {

            $errores[] = "No fue posible registrar el estudiante.";

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
        Nuevo estudiante | Studia360
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


<body class="bg-light">


<!-- NAVBAR -->

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


    <!-- ENCABEZADO -->

    <div class="mb-4">

        <h2>

            <i class="bi bi-person-plus-fill"></i>

            Registrar estudiante

        </h2>

        <p class="text-muted">

            Agrega un estudiante individualmente a la plataforma.

        </p>

    </div>


    <!-- MENSAJE DE ÉXITO -->

    <?php if ($exito): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill"></i>

            <strong>Estudiante registrado correctamente.</strong>

            <br>

            La contraseña inicial corresponde al número de documento.

            El estudiante deberá cambiarla en su primer ingreso.

        </div>

    <?php endif; ?>


    <!-- ERRORES -->

    <?php if (!empty($errores)): ?>

        <div class="alert alert-danger">

            <strong>
                No fue posible completar el registro:
            </strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errores as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- FORMULARIO -->

    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">


                <!-- NOMBRES -->

                <div class="mb-3">

                    <label
                        for="nombres"
                        class="form-label"
                    >

                        Nombres

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="nombres"
                        name="nombres"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars($nombres ?? "") ?>"
                    >

                </div>


                <!-- APELLIDOS -->

                <div class="mb-3">

                    <label
                        for="apellidos"
                        class="form-label"
                    >

                        Apellidos

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="apellidos"
                        name="apellidos"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars($apellidos ?? "") ?>"
                    >

                </div>


                <!-- DOCUMENTO -->

                <div class="mb-3">

                    <label
                        for="numero_documento"
                        class="form-label"
                    >

                        Número de documento

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="numero_documento"
                        name="numero_documento"
                        maxlength="20"
                        inputmode="numeric"
                        required
                        value="<?= htmlspecialchars($documento ?? "") ?>"
                    >

                    <div class="form-text">

                        Este será el usuario y la contraseña inicial.

                    </div>

                </div>


                <!-- CORREO -->

                <div class="mb-3">

                    <label
                        for="correo"
                        class="form-label"
                    >

                        Correo electrónico

                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="correo"
                        name="correo"
                        maxlength="120"
                        value="<?= htmlspecialchars($correo ?? "") ?>"
                    >

                    <div class="form-text">

                        Campo opcional.

                    </div>

                </div>


                <!-- GRADO -->

                <div class="mb-3">

                    <label
                        for="grado"
                        class="form-label"
                    >

                        Grado

                    </label>

                    <select
                        class="form-select"
                        id="grado"
                        name="grado"
                        required
                    >

                        <option value="">
                            Seleccionar grado
                        </option>

                        <option
                            value="9"
                            <?= (($grado ?? "") === "9") ? "selected" : "" ?>
                        >
                            9°
                        </option>

                        <option
                            value="10"
                            <?= (($grado ?? "") === "10") ? "selected" : "" ?>
                        >
                            10°
                        </option>

                        <option
                            value="11"
                            <?= (($grado ?? "") === "11") ? "selected" : "" ?>
                        >
                            11°
                        </option>

                    </select>

                </div>


                <!-- CURSO -->

                <div class="mb-4">

                    <label
                        for="id_curso"
                        class="form-label"
                    >

                        Curso

                    </label>

                    <select
                        class="form-select"
                        id="id_curso"
                        name="id_curso"
                        required
                    >

                        <option value="">
                            Seleccionar curso
                        </option>

                        <?php foreach ($cursos as $curso): ?>

                            <option
                                value="<?= (int)$curso["id_curso"] ?>"
                                data-grado="<?= htmlspecialchars($curso["grado"]) ?>"
                                <?= ((int)($id_curso ?? 0) === (int)$curso["id_curso"]) ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($curso["grado"]) ?>°
                                -
                                <?= htmlspecialchars($curso["grupo"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- INFORMACIÓN INICIAL -->

                <div class="alert alert-info">

                    <i class="bi bi-info-circle-fill"></i>

                    <strong>Configuración inicial:</strong>

                    <ul class="mb-0 mt-2">

                        <li>
                            Rol: Estudiante
                        </li>

                        <li>
                            Avatar: Explorador
                        </li>

                        <li>
                            Puntos: 0
                        </li>

                        <li>
                            Nivel: 1
                        </li>

                        <li>
                            Cambio de contraseña obligatorio en el primer ingreso
                        </li>

                    </ul>

                </div>


                <!-- BOTONES -->

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-person-plus-fill"></i>

                        Registrar estudiante

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


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/*
|--------------------------------------------------------------------------
| Filtrar cursos según el grado seleccionado
|--------------------------------------------------------------------------
*/

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


        if (
            gradoSeleccionado === "" ||
            gradoCurso === gradoSeleccionado
        ) {

            opcion.hidden = false;

        } else {

            opcion.hidden = true;

        }

    });


    /*
    |----------------------------------------------------------------------
    | Si el curso seleccionado no corresponde al grado, limpiarlo
    |----------------------------------------------------------------------
    */

    const opcionSeleccionada =
        curso.options[curso.selectedIndex];


    if (
        opcionSeleccionada &&
        opcionSeleccionada.dataset.grado &&
        opcionSeleccionada.dataset.grado !== gradoSeleccionado
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


</body>

</html>
<?php

require_once __DIR__ . "/../../includes/seguridad.php";

exigirAdmin();


/*
|--------------------------------------------------------------------------
| PhpSpreadsheet
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../vendor/autoload.php";


use PhpOffice\PhpSpreadsheet\IOFactory;


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errores = [];
$exitos = [];

$importado = false;


/*
|--------------------------------------------------------------------------
| Procesar archivo
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | Verificar archivo
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_FILES["archivo"]) ||
        $_FILES["archivo"]["error"] !== UPLOAD_ERR_OK
    ) {

        $errores[] =
            "Debes seleccionar un archivo Excel válido.";

    } else {


        $archivo =
            $_FILES["archivo"];


        $extension =
            strtolower(
                pathinfo(
                    $archivo["name"],
                    PATHINFO_EXTENSION
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validar extensión
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $extension,
                ["xlsx", "xls", "csv"],
                true
            )
        ) {

            $errores[] =
                "El archivo debe ser XLSX, XLS o CSV.";

        }


        /*
        |--------------------------------------------------------------------------
        | Procesar
        |--------------------------------------------------------------------------
        */

        if (empty($errores)) {

            try {


                /*
                |--------------------------------------------------------------------------
                | Cargar archivo
                |--------------------------------------------------------------------------
                */

                $documento =
                    IOFactory::load(
                        $archivo["tmp_name"]
                    );


                $hoja =
                    $documento->getSheet(0);


                $filas =
                    $hoja->toArray(
                        null,
                        true,
                        true,
                        true
                    );


                /*
                |--------------------------------------------------------------------------
                | Verificar encabezados
                |--------------------------------------------------------------------------
                */

                $encabezadosEsperados = [
                    "nombres",
                    "apellidos",
                    "numero_documento",
                    "correo",
                    "grado",
                    "grupo"
                ];


                $encabezados =
                    $filas[1] ?? [];


                $encabezados =
                    array_map(
                        function ($valor) {

                            return strtolower(
                                trim(
                                    (string)$valor
                                )
                            );

                        },
                        $encabezados
                    );


                foreach (
                    $encabezadosEsperados
                    as $indice => $esperado
                ) {

                    $columna =
                        chr(
                            65 + $indice
                        );


                    if (
                        ($encabezados[$columna] ?? "")
                        !== $esperado
                    ) {

                        $errores[] =
                            "La columna " .
                            $columna .
                            " debe llamarse: " .
                            $esperado;

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Importar
                |--------------------------------------------------------------------------
                */

                if (empty($errores)) {


                    $conexion->beginTransaction();


                    $filaActual = 1;


                    foreach (
                        $filas
                        as $numeroFila => $fila
                    ) {


                        if ($numeroFila === 1) {
                            continue;
                        }


                        $filaActual =
                            $numeroFila;


                        /*
                        |--------------------------------------------------------------------------
                        | Datos
                        |--------------------------------------------------------------------------
                        */

                        $nombres =
                            trim(
                                (string)(
                                    $fila["A"] ?? ""
                                )
                            );


                        $apellidos =
                            trim(
                                (string)(
                                    $fila["B"] ?? ""
                                )
                            );


                        $documento =
                            trim(
                                (string)(
                                    $fila["C"] ?? ""
                                )
                            );


                        $correo =
                            trim(
                                (string)(
                                    $fila["D"] ?? ""
                                )
                            );


                        $grado =
                            trim(
                                (string)(
                                    $fila["E"] ?? ""
                                )
                            );


                        $grupo =
                            trim(
                                (string)(
                                    $fila["F"] ?? ""
                                )
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Saltar filas completamente vacías
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $nombres === "" &&
                            $apellidos === "" &&
                            $documento === "" &&
                            $correo === "" &&
                            $grado === "" &&
                            $grupo === ""
                        ) {

                            continue;

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Validaciones
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $nombres === "" ||
                            $apellidos === ""
                        ) {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": nombres y apellidos son obligatorios."
                            );

                        }


                        if ($documento === "") {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": el número de documento es obligatorio."
                            );

                        }


                        if (
                            !in_array(
                                $grado,
                                ["9", "10", "11"],
                                true
                            )
                        ) {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": el grado debe ser 9, 10 u 11."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Validar correo
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $correo !== "" &&
                            !filter_var(
                                $correo,
                                FILTER_VALIDATE_EMAIL
                            )
                        ) {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": el correo no es válido."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Buscar curso
                        |--------------------------------------------------------------------------
                        */

                        $sqlCurso = "
                            SELECT
                                id_curso,
                                grado
                            FROM cursos
                            WHERE grupo = ?
                            AND grado = ?
                            AND estado = 'Activo'
                            LIMIT 1
                        ";


                        $stmtCurso =
                            $conexion->prepare(
                                $sqlCurso
                            );


                        $stmtCurso->execute([
                            $grupo,
                            $grado
                        ]);


                        $curso =
                            $stmtCurso->fetch(
                                PDO::FETCH_ASSOC
                            );


                        if (!$curso) {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": no existe un curso activo con grado " .
                                $grado .
                                " y grupo " .
                                $grupo .
                                "."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Verificar documento existente
                        |--------------------------------------------------------------------------
                        */

                        $sqlExiste = "
                            SELECT id_usuario
                            FROM usuarios
                            WHERE numero_documento = ?
                            LIMIT 1
                        ";


                        $stmtExiste =
                            $conexion->prepare(
                                $sqlExiste
                            );


                        $stmtExiste->execute([
                            $documento
                        ]);


                        if (
                            $stmtExiste->fetch()
                        ) {

                            throw new Exception(
                                "Fila " .
                                $filaActual .
                                ": el documento " .
                                $documento .
                                " ya está registrado."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Crear contraseña inicial
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash =
                            password_hash(
                                $documento,
                                PASSWORD_DEFAULT
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Insertar estudiante
                        |--------------------------------------------------------------------------
                        */

                        $sqlInsert = "
                            INSERT INTO usuarios (
                                id_rol,
                                nombres,
                                apellidos,
                                numero_documento,
                                correo,
                                password,
                                grado,
                                id_curso,
                                puntos,
                                nivel,
                                primer_ingreso,
                                estado
                            )
                            VALUES (
                                2,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                0,
                                1,
                                1,
                                'Activo'
                            )
                        ";


                        $stmtInsert =
                            $conexion->prepare(
                                $sqlInsert
                            );


                        $stmtInsert->execute([
                            $nombres,
                            $apellidos,
                            $documento,
                            $correo !== ""
                                ? $correo
                                : null,
                            $passwordHash,
                            $grado,
                            $curso["id_curso"]
                        ]);


                        $exitos[] =
                            $nombres .
                            " " .
                            $apellidos;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Confirmar
                    |--------------------------------------------------------------------------
                    */

                    $conexion->commit();


                    $importado = true;


                }

            } catch (Throwable $e) {


                if (
                    $conexion->inTransaction()
                ) {

                    $conexion->rollBack();

                }


                $errores[] =
                    $e->getMessage();

            }

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
        Importar estudiantes | Studia360
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

            <i class="bi bi-file-earmark-excel-fill"></i>

            Importar estudiantes

        </h2>

        <p class="text-muted mb-0">

            Registra múltiples estudiantes utilizando un archivo Excel.

        </p>

    </div>



    <?php if ($importado): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill"></i>

            <strong>
                Importación completada.
            </strong>

            Se registraron
            <?= count($exitos) ?>
            estudiante(s).

        </div>

    <?php endif; ?>



    <?php if (!empty($errores)): ?>

        <div class="alert alert-danger">

            <strong>

                <i class="bi bi-exclamation-triangle-fill"></i>

                No fue posible completar la importación:

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



    <div class="card shadow-sm mb-3">

        <div class="card-body">

            <div
                class="d-flex justify-content-between align-items-center"
            >

                <div>

                    <h5 class="mb-1">

                        <i class="bi bi-download"></i>

                        ¿No tienes la plantilla?

                    </h5>

                    <small class="text-muted">

                        Descarga el archivo oficial antes de realizar la importación.

                    </small>

                </div>


                <a
                    href="plantilla.php"
                    class="btn btn-success"
                >

                    <i class="bi bi-file-earmark-arrow-down-fill"></i>

                    Descargar plantilla

                </a>

            </div>

        </div>

    </div>



    <div class="card shadow-sm">

        <div class="card-body">


            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <div class="mb-3">

                    <label
                        for="archivo"
                        class="form-label"
                    >

                        Archivo Excel

                    </label>


                    <input
                        type="file"
                        name="archivo"
                        id="archivo"
                        class="form-control"
                        accept=".xlsx,.xls,.csv"
                        required
                    >


                    <div class="form-text">

                        Formatos permitidos:
                        XLSX, XLS y CSV.

                    </div>

                </div>



                <div class="alert alert-info">

                    <i class="bi bi-info-circle-fill"></i>

                    <strong>Importante:</strong>

                    La contraseña inicial de cada estudiante será su número de documento.

                    Al ingresar por primera vez deberá cambiarla.

                </div>



                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-upload"></i>

                        Importar estudiantes

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
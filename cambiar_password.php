<?php

session_start();

require_once __DIR__ . "/config/conexion.php";

/*
|--------------------------------------------------------------------------
| Verificar que exista una sesión
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar que realmente sea primer ingreso
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["primer_ingreso"]) || (int)$_SESSION["primer_ingreso"] !== 1) {

    if ((int)$_SESSION["id_rol"] === 1) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: estudiante/dashboard.php");
    }

    exit;
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

    <title>Crear nueva contraseña | Studia360</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --studia-primary: #2467c5;
            --studia-dark: #173f80;
            --studia-light: #f4f7fb;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at 12% 15%,
                    rgba(74, 139, 235, 0.20),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 88% 82%,
                    rgba(23, 63, 128, 0.14),
                    transparent 30%
                ),
                var(--studia-light);
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .password-card {
            border: 0;
            border-radius: 28px;
            overflow: hidden;
            background: #ffffff;
            box-shadow:
                0 28px 75px rgba(23, 63, 128, 0.16);
        }

        .brand-panel {
            min-height: 100%;
            padding: 3.2rem;
            position: relative;
            overflow: hidden;
            color: #ffffff;
            background:
                radial-gradient(
                    circle at 85% 18%,
                    rgba(255,255,255,0.16),
                    transparent 26%
                ),
                linear-gradient(
                    135deg,
                    var(--studia-dark),
                    var(--studia-primary)
                );
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            right: -85px;
            bottom: -100px;
            border-radius: 50%;
            border: 40px solid rgba(255,255,255,0.06);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            width: 76px;
            height: 76px;
            border-radius: 23px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            background: rgba(255,255,255,0.13);
            font-size: 2rem;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.18);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-top: 1rem;
            color: rgba(255,255,255,.82);
            font-size: .93rem;
        }

        .feature-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(255,255,255,.12);
        }

        .form-panel {
            padding: 3.2rem;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .42rem .8rem;
            border-radius: 999px;
            background: #eef5ff;
            color: var(--studia-primary);
            font-size: .78rem;
            font-weight: 700;
        }

        .form-control {
            min-height: 52px;
            border-radius: 14px;
            border-color: #dbe4ef;
            padding-left: 1rem;
        }

        .input-group .form-control {
            border-left: 0;
            padding-left: .4rem;
        }

        .input-group-text {
            border-radius: 14px 0 0 14px;
            border-color: #dbe4ef;
            background: #ffffff;
            color: var(--studia-primary);
        }

        .input-group .form-control:focus {
            border-color: #7ca9e7;
            box-shadow: none;
        }

        .input-group:focus-within {
            border-radius: 14px;
            box-shadow: 0 0 0 .25rem rgba(36, 103, 197, .10);
        }

        .form-text {
            margin-top: .6rem;
        }

        .password-toggle {
            border-color: #dbe4ef;
            border-left: 0;
            border-radius: 0 14px 14px 0;
            background: #ffffff;
            color: #718096;
        }

        .password-toggle:hover {
            color: var(--studia-primary);
            background: #f8fbff;
        }

        .btn-save {
            min-height: 54px;
            border: 0;
            border-radius: 14px;
            font-weight: 700;
            background:
                linear-gradient(
                    120deg,
                    var(--studia-primary),
                    #3a7dd7
                );
            box-shadow:
                0 12px 24px rgba(36, 103, 197, .22);
            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow:
                0 16px 30px rgba(36, 103, 197, .28);
        }

        .security-note {
            padding: .9rem 1rem;
            border-radius: 14px;
            background: #f8fbff;
            border: 1px solid #e2ebf6;
            color: #62748a;
            font-size: .88rem;
        }

        .alert {
            border-radius: 15px;
        }

        .password-strength {
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: #e9eef5;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: inherit;
            transition: width .25s ease;
        }

        .match-message {
            font-size: .82rem;
            min-height: 1.25rem;
        }

        @media (max-width: 991.98px) {

            .brand-panel {
                padding: 2.5rem;
            }

            .form-panel {
                padding: 2.5rem;
            }

        }

        @media (max-width: 575.98px) {

            .brand-panel {
                padding: 2rem;
            }

            .form-panel {
                padding: 2rem 1.35rem;
            }

        }

    </style>

</head>

<body>

<div class="container py-4 py-lg-5">

    <div class="row justify-content-center align-items-center min-vh-100">

        <div class="col-12 col-lg-10 col-xl-9">

            <div class="password-card">

                <div class="row g-0">

                    <!-- Panel visual -->

                    <div class="col-lg-5">

                        <div
                            class="
                                brand-panel
                                h-100
                                d-flex
                                flex-column
                                justify-content-center
                            "
                        >

                            <div class="brand-content">

                                <div class="brand-logo">

                                    <i class="bi bi-mortarboard-fill"></i>

                                </div>


                                <div
                                    class="
                                        text-uppercase
                                        small
                                        fw-semibold
                                        opacity-75
                                        mb-2
                                    "
                                >
                                    Bienvenido a Studia360
                                </div>


                                <h1
                                    class="
                                        display-6
                                        fw-bold
                                        mb-3
                                    "
                                >
                                    Protege tu cuenta desde el primer día.
                                </h1>


                                <p
                                    class="
                                        text-white-50
                                        mb-4
                                    "
                                >
                                    Antes de continuar en la plataforma,
                                    debes establecer una contraseña
                                    personal y segura.
                                </p>


                                <div class="feature-item">

                                    <div class="feature-icon">

                                        <i class="bi bi-shield-check"></i>

                                    </div>

                                    <span>
                                        Mantén protegida tu información.
                                    </span>

                                </div>


                                <div class="feature-item">

                                    <div class="feature-icon">

                                        <i class="bi bi-person-lock"></i>

                                    </div>

                                    <span>
                                        Utiliza una contraseña que solo tú conozcas.
                                    </span>

                                </div>


                                <div class="feature-item">

                                    <div class="feature-icon">

                                        <i class="bi bi-stars"></i>

                                    </div>

                                    <span>
                                        Después podrás disfrutar de Studia360.
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Formulario -->

                    <div class="col-lg-7">

                        <div class="form-panel">

                            <div class="eyebrow mb-3">

                                <i class="bi bi-key-fill"></i>

                                PRIMER INGRESO

                            </div>


                            <h2
                                class="
                                    h2
                                    fw-bold
                                    mb-2
                                "
                            >
                                Crea tu nueva contraseña
                            </h2>


                            <p
                                class="
                                    text-muted
                                    mb-4
                                "
                            >
                                Este cambio es obligatorio para continuar
                                utilizando tu cuenta de Studia360.
                            </p>


                            <div
                                class="
                                    security-note
                                    d-flex
                                    gap-2
                                    align-items-start
                                    mb-4
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-info-circle-fill
                                        text-primary
                                        mt-1
                                    "
                                ></i>

                                <span>

                                    Por seguridad, evita utilizar datos
                                    personales fáciles de adivinar.

                                </span>

                            </div>


                            <?php if (isset($_GET["error"])): ?>

                                <div
                                    class="
                                        alert
                                        alert-danger
                                        d-flex
                                        align-items-center
                                        gap-2
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-exclamation-triangle-fill
                                        "
                                    ></i>

                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $_GET["error"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </span>

                                </div>

                            <?php endif; ?>


                            <form
                                action="guardar_password.php"
                                method="POST"
                                id="passwordForm"
                            >


                                <!-- Nueva contraseña -->

                                <div class="mb-4">

                                    <label
                                        for="nueva_password"
                                        class="
                                            form-label
                                            fw-semibold
                                        "
                                    >
                                        Nueva contraseña
                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">

                                            <i
                                                class="
                                                    bi
                                                    bi-lock-fill
                                                "
                                            ></i>

                                        </span>


                                        <input
                                            type="password"
                                            class="form-control"
                                            id="nueva_password"
                                            name="nueva_password"
                                            minlength="8"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Crea una contraseña segura"
                                        >


                                        <button
                                            class="
                                                btn
                                                password-toggle
                                            "
                                            type="button"
                                            data-target="nueva_password"
                                            aria-label="Mostrar contraseña"
                                        >

                                            <i class="bi bi-eye"></i>

                                        </button>

                                    </div>


                                    <div
                                        class="
                                            d-flex
                                            justify-content-between
                                            align-items-center
                                            mt-2
                                            mb-2
                                        "
                                    >

                                        <small class="text-muted">

                                            Mínimo 8 caracteres.

                                        </small>


                                        <small
                                            id="strengthText"
                                            class="fw-semibold text-muted"
                                        >
                                            Seguridad
                                        </small>

                                    </div>


                                    <div class="password-strength">

                                        <div
                                            class="password-strength-bar"
                                            id="strengthBar"
                                        ></div>

                                    </div>

                                </div>


                                <!-- Confirmar contraseña -->

                                <div class="mb-4">

                                    <label
                                        for="confirmar_password"
                                        class="
                                            form-label
                                            fw-semibold
                                        "
                                    >
                                        Confirmar contraseña
                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">

                                            <i
                                                class="
                                                    bi
                                                    bi-shield-lock-fill
                                                "
                                            ></i>

                                        </span>


                                        <input
                                            type="password"
                                            class="form-control"
                                            id="confirmar_password"
                                            name="confirmar_password"
                                            minlength="8"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Repite la nueva contraseña"
                                        >


                                        <button
                                            class="
                                                btn
                                                password-toggle
                                            "
                                            type="button"
                                            data-target="confirmar_password"
                                            aria-label="Mostrar contraseña"
                                        >

                                            <i class="bi bi-eye"></i>

                                        </button>

                                    </div>


                                    <div
                                        id="matchMessage"
                                        class="match-message mt-2"
                                    ></div>

                                </div>


                                <button
                                    type="submit"
                                    class="
                                        btn
                                        btn-primary
                                        btn-save
                                        w-100
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-check2-circle
                                            me-2
                                        "
                                    ></i>

                                    Guardar y continuar

                                </button>

                            </form>


                            <div
                                class="
                                    text-center
                                    mt-4
                                "
                            >

                                <small class="text-muted">

                                    <i
                                        class="
                                            bi
                                            bi-shield-check
                                            me-1
                                        "
                                    ></i>

                                    Tu contraseña será almacenada de forma segura.

                                </small>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

document.querySelectorAll(".password-toggle").forEach(function (button) {

    button.addEventListener("click", function () {

        const target = document.getElementById(
            this.dataset.target
        );

        const icon = this.querySelector("i");

        if (target.type === "password") {

            target.type = "text";

            icon.classList.remove("bi-eye");

            icon.classList.add("bi-eye-slash");

        } else {

            target.type = "password";

            icon.classList.remove("bi-eye-slash");

            icon.classList.add("bi-eye");

        }

    });

});


const passwordInput = document.getElementById(
    "nueva_password"
);

const confirmInput = document.getElementById(
    "confirmar_password"
);

const strengthBar = document.getElementById(
    "strengthBar"
);

const strengthText = document.getElementById(
    "strengthText"
);

const matchMessage = document.getElementById(
    "matchMessage"
);


function updateStrength() {

    const password = passwordInput.value;

    let score = 0;

    if (password.length >= 8) score++;

    if (/[A-Z]/.test(password)) score++;

    if (/[a-z]/.test(password)) score++;

    if (/[0-9]/.test(password)) score++;

    if (/[^A-Za-z0-9]/.test(password)) score++;


    const levels = [

        {
            width: "0%",
            label: "Seguridad",
            className: "bg-secondary"
        },

        {
            width: "25%",
            label: "Débil",
            className: "bg-danger"
        },

        {
            width: "50%",
            label: "Aceptable",
            className: "bg-warning"
        },

        {
            width: "75%",
            label: "Buena",
            className: "bg-info"
        },

        {
            width: "100%",
            label: "Excelente",
            className: "bg-success"
        }

    ];


    const level = levels[
        Math.min(score, 4)
    ];


    strengthBar.style.width = level.width;

    strengthBar.className =
        "password-strength-bar " +
        level.className;

    strengthText.textContent = level.label;

}


function checkMatch() {

    if (confirmInput.value === "") {

        matchMessage.textContent = "";

        return;

    }


    if (
        passwordInput.value ===
        confirmInput.value
    ) {

        matchMessage.innerHTML =
            '<span class="text-success">' +
            '<i class="bi bi-check-circle-fill me-1"></i>' +
            'Las contraseñas coinciden.' +
            '</span>';

    } else {

        matchMessage.innerHTML =
            '<span class="text-danger">' +
            '<i class="bi bi-x-circle-fill me-1"></i>' +
            'Las contraseñas no coinciden.' +
            '</span>';

    }

}


passwordInput.addEventListener(
    "input",
    function () {

        updateStrength();

        checkMatch();

    }
);


confirmInput.addEventListener(
    "input",
    checkMatch
);


document.getElementById("passwordForm").addEventListener(
    "submit",
    function (event) {

        if (
            passwordInput.value !==
            confirmInput.value
        ) {

            event.preventDefault();

            matchMessage.innerHTML =
                '<span class="text-danger">' +
                '<i class="bi bi-x-circle-fill me-1"></i>' +
                'Las contraseñas deben coincidir antes de continuar.' +
                '</span>';

            confirmInput.focus();

        }

    }
);

</script>

</body>

</html>

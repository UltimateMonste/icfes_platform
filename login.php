<?php
session_start();

if (isset($_SESSION['id_usuario'])) {
    if ((int)($_SESSION['id_rol'] ?? 0) === 1) {
        header("Location: admin/dashboard.php");
        exit;
    }

    header("Location: estudiante/dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1769ff">

    <title>Iniciar sesión | Studia360</title>

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
            --primary: #1769ff;
            --primary-dark: #0d47b5;
            --secondary: #6c63ff;
            --text: #14213d;
            --muted: #667085;
            --surface: #ffffff;
            --background: #f4f7fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 10%, rgba(23,105,255,.16), transparent 32%),
                radial-gradient(circle at 90% 90%, rgba(108,99,255,.14), transparent 30%),
                var(--background);
        }

        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 28px 16px;
        }

        .login-card {
            width: 100%;
            max-width: 1050px;
            margin: auto;
            overflow: hidden;
            border: 1px solid rgba(20, 33, 61, .08);
            border-radius: 28px;
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(20, 33, 61, .14);
        }

        .brand-panel {
            position: relative;
            min-height: 620px;
            padding: 48px;
            color: #fff;
            overflow: hidden;
            background: linear-gradient(145deg, #1769ff 0%, #1255d0 52%, #173f91 100%);
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.09);
        }

        .brand-panel::before {
            width: 330px;
            height: 330px;
            right: -130px;
            top: -100px;
        }

        .brand-panel::after {
            width: 260px;
            height: 260px;
            left: -120px;
            bottom: -120px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.2);
            font-size: 1.35rem;
        }

        .brand-title {
            margin-top: auto;
            margin-bottom: 24px;
            max-width: 460px;
        }

        .brand-title h1 {
            font-size: clamp(2.2rem, 4vw, 3.5rem);
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -.04em;
            margin-bottom: 20px;
        }

        .brand-title p {
            margin: 0;
            color: rgba(255,255,255,.86);
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .feature-list {
            display: grid;
            gap: 13px;
            margin-bottom: 5px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,.94);
        }

        .feature i {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(255,255,255,.13);
        }

        .form-panel {
            min-height: 620px;
            display: flex;
            align-items: center;
            padding: 52px clamp(28px, 5vw, 70px);
        }

        .form-content {
            width: 100%;
            max-width: 430px;
            margin: auto;
        }

        .welcome-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            margin-bottom: 22px;
            border-radius: 17px;
            color: var(--primary);
            background: #eaf1ff;
            font-size: 1.45rem;
        }

        .form-content h2 {
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -.035em;
        }

        .subtitle {
            margin-bottom: 30px;
            color: var(--muted);
            line-height: 1.6;
        }

        .form-label {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-group-custom > i {
            position: absolute;
            z-index: 3;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #98a2b3;
        }

        .input-group-custom .form-control {
            min-height: 52px;
            padding-left: 46px;
            padding-right: 48px;
            border-radius: 13px;
            border: 1px solid #d9e0ea;
            background: #fbfcfe;
        }

        .input-group-custom .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .22rem rgba(23,105,255,.12);
            background: #fff;
        }

        .password-toggle {
            position: absolute;
            z-index: 4;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: 0;
            background: transparent;
            color: #667085;
            border-radius: 10px;
        }

        .password-toggle:hover {
            background: #eef3fb;
            color: var(--primary);
        }

        .login-btn {
            min-height: 54px;
            border: 0;
            border-radius: 13px;
            font-weight: 700;
            font-size: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 10px 24px rgba(23,105,255,.24);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(23,105,255,.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .security-note {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-top: 24px;
            padding: 13px 15px;
            border-radius: 12px;
            color: #667085;
            background: #f7f9fc;
            font-size: .84rem;
            line-height: 1.5;
        }

        .security-note i {
            color: #12b76a;
            margin-top: 2px;
        }

        .error-alert {
            border: 0;
            border-left: 4px solid #dc3545;
            border-radius: 12px;
            background: #fff1f2;
            color: #842029;
        }

        .footer-text {
            margin-top: 28px;
            text-align: center;
            color: #98a2b3;
            font-size: .82rem;
        }

        @media (max-width: 991.98px) {
            .brand-panel,
            .form-panel {
                min-height: auto;
            }

            .brand-panel {
                padding: 36px;
            }

            .brand-title {
                margin-top: 70px;
            }

            .form-panel {
                padding: 42px 30px;
            }
        }

        @media (max-width: 575.98px) {
            .login-shell {
                padding: 12px;
            }

            .login-card {
                border-radius: 20px;
            }

            .brand-panel {
                padding: 28px 24px;
            }

            .brand-title {
                margin-top: 55px;
            }

            .brand-title h1 {
                font-size: 2.25rem;
            }

            .form-panel {
                padding: 34px 22px;
            }
        }
    </style>
</head>

<body>

<div class="login-shell">
    <main class="login-card">
        <div class="row g-0">

            <!-- Identidad de Studia360 -->
            <section class="col-lg-6 brand-panel">
                <div class="brand-content">

                    <div class="brand">
                        <span class="brand-icon">
                            <i class="bi bi-mortarboard-fill"></i>
                        </span>
                        <span>Studia360</span>
                    </div>

                    <div class="brand-title">
                        <h1>Tu preparación, a tu ritmo.</h1>
                        <p>
                            Un espacio de apoyo para organizar tu aprendizaje,
                            explorar contenidos y avanzar paso a paso hacia tus objetivos.
                        </p>
                    </div>

                    <div class="feature-list">
                        <div class="feature">
                            <i class="bi bi-book"></i>
                            <span>Explora contenidos por grado y materia.</span>
                        </div>

                        <div class="feature">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Consulta tu progreso y tus avances.</span>
                        </div>

                        <div class="feature">
                            <i class="bi bi-stars"></i>
                            <span>Avanza, consigue puntos y desbloquea recompensas.</span>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Formulario -->
            <section class="col-lg-6 form-panel">
                <div class="form-content">

                    <div class="welcome-icon">
                        <i class="bi bi-person-check-fill"></i>
                    </div>

                    <h2>Bienvenido a Studia360</h2>

                    <p class="subtitle">
                        Inicia sesión para continuar con tu preparación.
                    </p>

                    <?php if ($error !== ''): ?>
                        <div class="alert error-alert d-flex gap-2 align-items-start mb-4" role="alert">
                            <i class="bi bi-exclamation-circle-fill mt-1"></i>
                            <div>
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="autenticar.php" method="POST" id="loginForm">

                        <div class="mb-4">
                            <label for="documento" class="form-label">
                                Número de documento
                            </label>

                            <div class="input-group-custom">
                                <i class="bi bi-person-vcard"></i>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="documento"
                                    name="documento"
                                    placeholder="Ingresa tu documento"
                                    required
                                    autocomplete="username"
                                    inputmode="numeric"
                                >
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">
                                Contraseña
                            </label>

                            <div class="input-group-custom">
                                <i class="bi bi-lock"></i>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Ingresa tu contraseña"
                                    required
                                    autocomplete="current-password"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="togglePassword"
                                    aria-label="Mostrar contraseña"
                                    title="Mostrar contraseña"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary login-btn w-100" id="loginButton">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Iniciar sesión
                        </button>

                    </form>

                    <div class="security-note">
                        <i class="bi bi-shield-check"></i>
                        <span>
                            Tu acceso está protegido. No compartas tus credenciales con otras personas.
                        </span>
                    </div>

                    <div class="footer-text">
                        Studia360 · Plataforma de apoyo para tu preparación
                    </div>

                </div>
            </section>

        </div>
    </main>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');

    togglePassword.addEventListener('click', function () {
        const visible = passwordInput.type === 'text';

        passwordInput.type = visible ? 'password' : 'text';

        this.innerHTML = visible
            ? '<i class="bi bi-eye"></i>'
            : '<i class="bi bi-eye-slash"></i>';

        this.setAttribute(
            'aria-label',
            visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
        );
    });

    loginForm.addEventListener('submit', function () {
        loginButton.disabled = true;
        loginButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Ingresando...';
    });
</script>

</body>
</html>

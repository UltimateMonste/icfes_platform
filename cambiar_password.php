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
/* ===== Personalización Studia360 ===== */
:root{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff;--s360-bg:#f6f7fb;--s360-card:#fff;--s360-text:#182235;--s360-muted:#68758a;--s360-line:#dfe5ef}
body.s360-blue{--s360-primary:#2563eb;--s360-primary-2:#4f46e5;--s360-soft:#eff6ff}
body.s360-orange{--s360-primary:#f97316;--s360-primary-2:#ea580c;--s360-soft:#fff3e8}
body.s360-purple{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff}
body.s360-green{--s360-primary:#10b981;--s360-primary-2:#059669;--s360-soft:#ecfdf5}
body.s360-dark{--s360-bg:#0b1220;--s360-card:#172236;--s360-text:#eef2f7;--s360-muted:#9aa8bb;--s360-line:#2b3850}

.s360-login-bg{position:fixed;inset:0;z-index:-10;overflow:hidden;background:radial-gradient(circle at 10% 15%,color-mix(in srgb,var(--s360-primary) 17%,transparent),transparent 30rem),radial-gradient(circle at 90% 80%,color-mix(in srgb,var(--s360-primary-2) 15%,transparent),transparent 32rem),var(--s360-bg);transition:background .35s}
.s360-login-bg:before,.s360-login-bg:after{content:"";position:absolute;width:430px;height:430px;border-radius:50%;border:1px solid color-mix(in srgb,var(--s360-primary) 20%,transparent);animation:s360Float 14s ease-in-out infinite}
.s360-login-bg:before{left:-150px;top:-170px;box-shadow:0 0 0 42px color-mix(in srgb,var(--s360-primary) 4%,transparent),0 0 0 88px color-mix(in srgb,var(--s360-primary) 2%,transparent)}
.s360-login-bg:after{right:-170px;bottom:-190px;animation-delay:-7s;box-shadow:0 0 0 48px color-mix(in srgb,var(--s360-primary-2) 4%,transparent),0 0 0 96px color-mix(in srgb,var(--s360-primary-2) 2%,transparent)}
.s360-particle{position:absolute;width:7px;height:7px;border-radius:50%;background:var(--s360-primary);opacity:.35;box-shadow:0 0 20px color-mix(in srgb,var(--s360-primary) 60%,transparent);animation:s360Drift 11s ease-in-out infinite}
.s360-particle:nth-child(1){left:17%;top:25%;animation-duration:12s}.s360-particle:nth-child(2){left:73%;top:19%;animation-duration:15s;animation-delay:-4s}.s360-particle:nth-child(3){left:31%;top:78%;animation-duration:14s;animation-delay:-8s}.s360-particle:nth-child(4){left:83%;top:63%;animation-duration:10s;animation-delay:-2s}.s360-particle:nth-child(5){left:54%;top:10%;animation-duration:16s;animation-delay:-10s}
@keyframes s360Float{0%,100%{transform:translate(0,0) rotate(0)}50%{transform:translate(20px,18px) rotate(7deg)}}
@keyframes s360Drift{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(18px,-24px) scale(1.35)}}

.s360-theme-button{position:fixed;right:22px;bottom:22px;z-index:100;width:50px;height:50px;border:0;border-radius:16px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--s360-primary),var(--s360-primary-2));box-shadow:0 14px 30px color-mix(in srgb,var(--s360-primary) 28%,transparent);cursor:pointer;transition:.2s}
.s360-theme-button:hover{transform:translateY(-3px) rotate(4deg)}
.s360-theme-panel{position:fixed;right:22px;bottom:82px;z-index:101;width:292px;padding:16px;border:1px solid var(--s360-line);border-radius:20px;background:var(--s360-card);color:var(--s360-text);box-shadow:0 24px 60px rgba(0,0,0,.18);opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px) scale(.98);transition:.2s}
.s360-theme-panel.open{opacity:1;visibility:visible;pointer-events:auto;transform:none}
.s360-theme-title{font-size:.88rem;font-weight:800}.s360-theme-sub{font-size:.68rem;color:var(--s360-muted);margin:3px 0 12px}
.s360-theme-colors{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.s360-theme-option{border:1px solid var(--s360-line);border-radius:13px;padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left}
.s360-theme-option:hover,.s360-theme-option.active{border-color:var(--s360-primary);background:var(--s360-soft)}
.s360-swatch{height:24px;border-radius:8px;margin-bottom:6px}.s360-theme-option strong{display:block;font-size:.68rem}.s360-theme-option small{font-size:.58rem;color:var(--s360-muted)}
.s360-mode-button{width:100%;margin-top:8px;border:1px solid var(--s360-line);border-radius:13px;padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left;font-size:.7rem;font-weight:700}
.s360-mode-button:hover{background:var(--s360-soft);color:var(--s360-primary)}

body.s360-dark .cardx,body.s360-dark .password-card{background:var(--s360-card)!important;color:var(--s360-text)}
body.s360-dark .form-panel,body.s360-dark .main{background:var(--s360-card)!important;color:var(--s360-text)}
body.s360-dark .form-control,body.s360-dark .input-group-text,body.s360-dark .toggle,body.s360-dark .password-toggle{background:#111a2a!important;color:var(--s360-text)!important;border-color:var(--s360-line)!important}
body.s360-dark .form-control::placeholder{color:#748299}body.s360-dark .text-muted{color:var(--s360-muted)!important}
body.s360-dark .form-label,body.s360-dark h1,body.s360-dark h2,body.s360-dark h3{color:var(--s360-text)}
body.s360-dark .security-note{background:#111a2a!important;color:var(--s360-muted)!important;border-color:var(--s360-line)!important}
body.s360-dark .alert-danger{background:#3a202a!important;color:#ffd9df!important;border-color:#6b3545!important}
body.s360-dark .alert-warning{background:#3a301b!important;color:#ffe7a3!important;border-color:#69551f!important}
body.s360-dark .alert-success{background:#14372d!important;color:#b9f3dc!important;border-color:#23604d!important}
body.s360-dark .eyebrow{background:color-mix(in srgb,var(--s360-primary) 14%,#111a2a)!important;color:#c9b8ff!important}
@media(max-width:575px){.s360-theme-button{right:14px;bottom:14px}.s360-theme-panel{right:12px;bottom:72px;width:min(292px,calc(100vw - 24px))}}


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

    
/* ===== Studia360 · tema aplicado a toda la interfaz ===== */
:root{
    --s360-primary:#8b5cf6;
    --s360-primary-2:#7c3aed;
    --s360-soft:#f3efff;
    --s360-bg:#f6f7fb;
    --s360-card:#ffffff;
    --s360-input:#ffffff;
    --s360-text:#182235;
    --s360-muted:#68758a;
    --s360-line:#dfe5ef;
    --s360-panel-tint:#faf9ff;
    --s360-shadow:rgba(43,35,80,.15);
}
body.s360-blue{--s360-primary:#2563eb;--s360-primary-2:#4f46e5;--s360-soft:#eff6ff;--s360-panel-tint:#f8faff}
body.s360-orange{--s360-primary:#f97316;--s360-primary-2:#ea580c;--s360-soft:#fff3e8;--s360-panel-tint:#fffaf6}
body.s360-purple{--s360-primary:#8b5cf6;--s360-primary-2:#7c3aed;--s360-soft:#f3efff;--s360-panel-tint:#faf9ff}
body.s360-green{--s360-primary:#10b981;--s360-primary-2:#059669;--s360-soft:#ecfdf5;--s360-panel-tint:#f7fdfa}

body.s360-dark{
    --s360-bg:#0b1220;
    --s360-card:#172236;
    --s360-input:#111a2a;
    --s360-text:#eef2f7;
    --s360-muted:#9aa8bb;
    --s360-line:#2b3850;
    --s360-panel-tint:#1b2940;
    --s360-shadow:rgba(0,0,0,.36);
}

/* Fondo animado */
.s360-login-bg{
    position:fixed;inset:0;z-index:-10;overflow:hidden;
    background:
      radial-gradient(circle at 10% 15%,color-mix(in srgb,var(--s360-primary) 17%,transparent),transparent 30rem),
      radial-gradient(circle at 90% 80%,color-mix(in srgb,var(--s360-primary-2) 15%,transparent),transparent 32rem),
      var(--s360-bg);
    transition:background .35s ease;
}
.s360-login-bg:before,.s360-login-bg:after{
    content:"";position:absolute;width:430px;height:430px;border-radius:50%;
    border:1px solid color-mix(in srgb,var(--s360-primary) 20%,transparent);
    animation:s360Float 14s ease-in-out infinite;
}
.s360-login-bg:before{left:-150px;top:-170px;box-shadow:0 0 0 42px color-mix(in srgb,var(--s360-primary) 4%,transparent),0 0 0 88px color-mix(in srgb,var(--s360-primary) 2%,transparent)}
.s360-login-bg:after{right:-170px;bottom:-190px;animation-delay:-7s;box-shadow:0 0 0 48px color-mix(in srgb,var(--s360-primary-2) 4%,transparent),0 0 0 96px color-mix(in srgb,var(--s360-primary-2) 2%,transparent)}
.s360-particle{position:absolute;width:7px;height:7px;border-radius:50%;background:var(--s360-primary);opacity:.35;box-shadow:0 0 20px color-mix(in srgb,var(--s360-primary) 60%,transparent);animation:s360Drift 11s ease-in-out infinite}
.s360-particle:nth-child(1){left:17%;top:25%;animation-duration:12s}
.s360-particle:nth-child(2){left:73%;top:19%;animation-duration:15s;animation-delay:-4s}
.s360-particle:nth-child(3){left:31%;top:78%;animation-duration:14s;animation-delay:-8s}
.s360-particle:nth-child(4){left:83%;top:63%;animation-duration:10s;animation-delay:-2s}
.s360-particle:nth-child(5){left:54%;top:10%;animation-duration:16s;animation-delay:-10s}
@keyframes s360Float{0%,100%{transform:translate(0,0) rotate(0)}50%{transform:translate(20px,18px) rotate(7deg)}}
@keyframes s360Drift{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(18px,-24px) scale(1.35)}}

/* Personalización */
.s360-theme-button{
    position:fixed;right:22px;bottom:22px;z-index:100;width:50px;height:50px;
    border:0;border-radius:16px;display:grid;place-items:center;color:#fff;
    background:linear-gradient(135deg,var(--s360-primary),var(--s360-primary-2));
    box-shadow:0 14px 30px color-mix(in srgb,var(--s360-primary) 28%,transparent);
    cursor:pointer;transition:.2s;
}
.s360-theme-button:hover{transform:translateY(-3px) rotate(4deg)}
.s360-theme-panel{
    position:fixed;right:22px;bottom:82px;z-index:101;width:292px;padding:16px;
    border:1px solid var(--s360-line);border-radius:20px;background:var(--s360-card);
    color:var(--s360-text);box-shadow:0 24px 60px rgba(0,0,0,.18);
    opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px) scale(.98);transition:.2s;
}
.s360-theme-panel.open{opacity:1;visibility:visible;pointer-events:auto;transform:none}
.s360-theme-title{font-size:.88rem;font-weight:800}
.s360-theme-sub{font-size:.68rem;color:var(--s360-muted);margin:3px 0 12px}
.s360-theme-colors{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.s360-theme-option{
    border:1px solid var(--s360-line);border-radius:13px;padding:9px;
    background:var(--s360-card);color:var(--s360-text);cursor:pointer;text-align:left;
}
.s360-theme-option:hover,.s360-theme-option.active{border-color:var(--s360-primary);background:var(--s360-soft)}
.s360-swatch{height:24px;border-radius:8px;margin-bottom:6px}
.s360-theme-option strong{display:block;font-size:.68rem}
.s360-theme-option small{font-size:.58rem;color:var(--s360-muted)}
.s360-mode-button{
    width:100%;margin-top:8px;border:1px solid var(--s360-line);border-radius:13px;
    padding:9px;background:var(--s360-card);color:var(--s360-text);cursor:pointer;
    text-align:left;font-size:.7rem;font-weight:700;
}
.s360-mode-button:hover{background:var(--s360-soft);color:var(--s360-primary)}

/* ===== Aplicación del color a la ventana principal ===== */
.cardx,.password-card{
    background:var(--s360-card)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 18%,var(--s360-line))!important;
    box-shadow:0 28px 75px var(--s360-shadow)!important;
}
.form-panel,.main{
    background:var(--s360-panel-tint)!important;
    color:var(--s360-text)!important;
    transition:background .3s ease,color .3s ease;
}
.form-control,.input-group-text,.toggle,.password-toggle{
    background:var(--s360-input)!important;
    color:var(--s360-text)!important;
    border-color:var(--s360-line)!important;
}
.form-control::placeholder{color:var(--s360-muted)!important}
.form-label,h1,h2,h3{color:var(--s360-text)}
.text-muted{color:var(--s360-muted)!important}
.eyebrow,.badgex{
    background:var(--s360-soft)!important;
    color:var(--s360-primary)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 18%,transparent)!important;
}
.recover{color:var(--s360-primary)!important}
.btnlogin,.btn-save,.btnx{
    background:linear-gradient(120deg,var(--s360-primary),var(--s360-primary-2))!important;
    border-color:transparent!important;
    box-shadow:0 12px 24px color-mix(in srgb,var(--s360-primary) 23%,transparent)!important;
}
.input-group:focus-within{
    box-shadow:0 0 0 .25rem color-mix(in srgb,var(--s360-primary) 11%,transparent)!important;
}
.input-group-text{color:var(--s360-primary)!important}
.toggle:hover,.password-toggle:hover{color:var(--s360-primary)!important;background:var(--s360-soft)!important}
.security-note{
    background:var(--s360-soft)!important;
    border-color:color-mix(in srgb,var(--s360-primary) 13%,var(--s360-line))!important;
    color:var(--s360-muted)!important;
}
.password-strength{background:color-mix(in srgb,var(--s360-line) 65%,var(--s360-bg))!important}

/* El panel de marca también sigue el color seleccionado */
.panel,.brand-panel,.side{
    background:
      radial-gradient(circle at 90% 8%,rgba(255,255,255,.16),transparent 13rem),
      linear-gradient(135deg,var(--s360-primary-2),var(--s360-primary))!important;
}
body.s360-dark .form-panel,
body.s360-dark .main{
    background:var(--s360-panel-tint)!important;
}
body.s360-dark .cardx,
body.s360-dark .password-card{
    background:var(--s360-card)!important;
}
body.s360-dark .form-control,
body.s360-dark .input-group-text,
body.s360-dark .toggle,
body.s360-dark .password-toggle{
    background:var(--s360-input)!important;
}
body.s360-dark .security-note{
    background:color-mix(in srgb,var(--s360-primary) 8%,var(--s360-input))!important;
}
body.s360-dark .alert-danger{background:#3a202a!important;color:#ffd9df!important;border-color:#6b3545!important}
body.s360-dark .alert-warning{background:#3a301b!important;color:#ffe7a3!important;border-color:#69551f!important}
body.s360-dark .alert-success{background:#14372d!important;color:#b9f3dc!important;border-color:#23604d!important}

@media(max-width:575px){
    .s360-theme-button{right:14px;bottom:14px}
    .s360-theme-panel{right:12px;bottom:72px;width:min(292px,calc(100vw - 24px))}
}

</style>

</head>

<body>

<div class="s360-login-bg" aria-hidden="true"><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span><span class="s360-particle"></span></div>
<button type="button" class="s360-theme-button" id="s360ThemeButton" aria-label="Personalizar apariencia" title="Personalizar apariencia"><i class="bi bi-palette2"></i></button>
<div class="s360-theme-panel" id="s360ThemePanel">
  <div class="s360-theme-title">Personaliza Studia360</div>
  <div class="s360-theme-sub">Tu elección se mantiene en toda la plataforma.</div>
  <div class="s360-theme-colors">
    <button type="button" class="s360-theme-option" data-s360-theme="blue"><div class="s360-swatch" style="background:linear-gradient(135deg,#2563eb,#4f46e5)"></div><strong>Azul</strong><small>Clásico</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="orange"><div class="s360-swatch" style="background:linear-gradient(135deg,#f97316,#ea580c)"></div><strong>Naranja</strong><small>Enérgico</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="purple"><div class="s360-swatch" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"></div><strong>Violeta</strong><small>Creativo</small></button>
    <button type="button" class="s360-theme-option" data-s360-theme="green"><div class="s360-swatch" style="background:linear-gradient(135deg,#10b981,#059669)"></div><strong>Verde</strong><small>Calma</small></button>
  </div>
  <button type="button" class="s360-mode-button" id="s360ModeButton"></button>
</div>


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


<script>
(function(){
 const KEY="studia360_theme",body=document.body,panel=document.getElementById("s360ThemePanel"),toggle=document.getElementById("s360ThemeButton"),mode=document.getElementById("s360ModeButton");
 let state={theme:"purple",mode:"dark"};
 try{const raw=localStorage.getItem(KEY);if(raw){const x=JSON.parse(raw);if(typeof x==="string"){state.theme=["blue","orange","purple","green"].includes(x)?x:"purple"}else if(x){state.theme=["blue","orange","purple","green"].includes(x.theme)?x.theme:"purple";state.mode=x.mode==="light"?"light":"dark"}}}catch(e){}
 function apply(){body.classList.remove("s360-blue","s360-orange","s360-purple","s360-green","s360-dark");body.classList.add("s360-"+state.theme);if(state.mode==="dark")body.classList.add("s360-dark");document.querySelectorAll("[data-s360-theme]").forEach(b=>b.classList.toggle("active",b.dataset.s360Theme===state.theme));if(mode)mode.innerHTML=state.mode==="dark"?'<i class="bi bi-moon-stars me-2"></i>Modo oscuro':'<i class="bi bi-sun me-2"></i>Modo claro';try{localStorage.setItem(KEY,JSON.stringify(state))}catch(e){}}
 document.querySelectorAll("[data-s360-theme]").forEach(b=>b.addEventListener("click",()=>{state.theme=b.dataset.s360Theme;apply()}));
 mode?.addEventListener("click",()=>{state.mode=state.mode==="dark"?"light":"dark";apply()});
 toggle?.addEventListener("click",e=>{e.stopPropagation();panel?.classList.toggle("open")});
 document.addEventListener("click",e=>{if(panel&&!panel.contains(e.target)&&!toggle.contains(e.target))panel.classList.remove("open")});
 apply();
})();
</script>

</body>

</html>

<?php
session_start();

if (isset($_SESSION['id_usuario'])) {
    if ((int)($_SESSION['id_rol'] ?? 0) === 1) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: estudiante/dashboard.php');
    }
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión | Studia360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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

:root{
    --p:#2563eb;
    --p2:#4f46e5;
    --soft:#eff6ff;
    --bg:#f5f7fb;
    --card:#ffffff;
    --text:#172033;
    --muted:#667085;
    --line:#e3e8f0;
    --shadow:0 28px 80px rgba(31,55,86,.16);
}

*{box-sizing:border-box}

html,body{min-height:100%}

body{
    min-height:100vh;
    margin:0;
    color:var(--text);
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    background:var(--bg);
    overflow-x:hidden;
    transition:background .3s,color .3s;
}

/* Fondo animado */
.login-background{
    position:fixed;
    inset:0;
    z-index:-2;
    overflow:hidden;
    background:
        radial-gradient(circle at 12% 15%,color-mix(in srgb,var(--p) 18%,transparent),transparent 28rem),
        radial-gradient(circle at 88% 82%,color-mix(in srgb,var(--p2) 15%,transparent),transparent 30rem),
        var(--bg);
}

.login-background::before,
.login-background::after{
    content:"";
    position:absolute;
    width:420px;
    height:420px;
    border-radius:50%;
    border:1px solid color-mix(in srgb,var(--p) 16%,transparent);
    opacity:.65;
    animation:floatOrb 15s ease-in-out infinite;
}

.login-background::before{
    left:-130px;
    top:-150px;
    box-shadow:
        0 0 0 42px color-mix(in srgb,var(--p) 3%,transparent),
        0 0 0 86px color-mix(in srgb,var(--p) 2%,transparent);
}

.login-background::after{
    right:-160px;
    bottom:-180px;
    animation-delay:-7s;
    box-shadow:
        0 0 0 55px color-mix(in srgb,var(--p2) 3%,transparent),
        0 0 0 105px color-mix(in srgb,var(--p2) 2%,transparent);
}

.login-orb{
    position:absolute;
    width:9px;
    height:9px;
    border-radius:50%;
    background:var(--p);
    opacity:.35;
    box-shadow:0 0 22px color-mix(in srgb,var(--p) 55%,transparent);
    animation:drift 10s ease-in-out infinite;
}

.login-orb:nth-child(1){left:18%;top:22%;animation-duration:12s}
.login-orb:nth-child(2){left:72%;top:18%;animation-duration:15s;animation-delay:-4s}
.login-orb:nth-child(3){left:30%;top:78%;animation-duration:14s;animation-delay:-8s}
.login-orb:nth-child(4){left:82%;top:65%;animation-duration:11s;animation-delay:-2s}
.login-orb:nth-child(5){left:54%;top:8%;animation-duration:16s;animation-delay:-10s}

@keyframes floatOrb{
    0%,100%{transform:translate3d(0,0,0) rotate(0deg)}
    50%{transform:translate3d(22px,18px,0) rotate(7deg)}
}

@keyframes drift{
    0%,100%{transform:translate(0,0) scale(1)}
    50%{transform:translate(18px,-25px) scale(1.35)}
}

/* Tarjeta */
.cardx{
    border:1px solid var(--line);
    border-radius:30px;
    overflow:hidden;
    background:var(--card);
    box-shadow:var(--shadow);
    animation:loginIn .55s ease both;
}

@keyframes loginIn{
    from{opacity:0;transform:translateY(14px) scale(.985)}
    to{opacity:1;transform:none}
}

.panel{
    padding:3.3rem;
    color:#fff;
    background:
        radial-gradient(circle at 90% 8%,rgba(255,255,255,.15),transparent 13rem),
        linear-gradient(135deg,var(--p2),var(--p));
    position:relative;
    overflow:hidden;
}

.panel:before{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.13);
    right:-100px;
    top:-100px;
    box-shadow:
        0 0 0 35px rgba(255,255,255,.035),
        0 0 0 72px rgba(255,255,255,.025);
    animation:panelOrb 12s ease-in-out infinite;
}

.panel:after{
    content:"";
    position:absolute;
    width:170px;
    height:170px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.08);
    left:-90px;
    bottom:-80px;
}

@keyframes panelOrb{
    0%,100%{transform:translate(0,0)}
    50%{transform:translate(-15px,18px)}
}

.panel>*{position:relative;z-index:1}

.logo{
    width:78px;
    height:78px;
    border-radius:24px;
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.15);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:2rem;
    margin-bottom:2rem;
    box-shadow:0 12px 28px rgba(0,0,0,.12);
}

.form-panel{
    padding:3.3rem;
    background:var(--card);
}

.badgex{
    display:inline-flex;
    align-items:center;
    padding:.45rem .8rem;
    border-radius:999px;
    background:var(--soft);
    color:var(--p);
    border:1px solid color-mix(in srgb,var(--p) 12%,transparent);
    font-size:.78rem;
    font-weight:700;
}

.form-control{
    min-height:54px;
    border-radius:14px;
    border-color:var(--line);
    background:var(--card);
    color:var(--text);
}

.form-control::placeholder{color:#98a3b5}

.input-group-text{
    border-radius:14px 0 0 14px;
    background:var(--card);
    border-color:var(--line);
    color:var(--p);
}

.input-group .form-control{border-left:0}

.toggle{
    border:1px solid var(--line);
    border-left:0;
    border-radius:0 14px 14px 0;
    background:var(--card);
    color:var(--muted);
}

.toggle:hover{
    color:var(--p);
    background:var(--soft);
}

.input-group:focus-within{
    box-shadow:0 0 0 .25rem color-mix(in srgb,var(--p) 10%,transparent);
    border-radius:14px;
}

.form-control:focus{
    background:var(--card);
    color:var(--text);
    border-color:color-mix(in srgb,var(--p) 45%,#fff);
    box-shadow:none;
}

.btnlogin{
    min-height:54px;
    border:0;
    border-radius:14px;
    font-weight:700;
    color:#fff!important;
    background:linear-gradient(120deg,var(--p),var(--p2));
    box-shadow:0 12px 24px color-mix(in srgb,var(--p) 22%,transparent);
    transition:transform .2s,box-shadow .2s;
}

.btnlogin:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 30px color-mix(in srgb,var(--p) 27%,transparent);
}

.recover{
    color:var(--p);
    font-weight:600;
    text-decoration:none;
}

.recover:hover{text-decoration:underline}

.feature{
    display:flex;
    gap:.7rem;
    align-items:center;
    margin-top:1rem;
    color:rgba(255,255,255,.84);
}

.feature i{
    width:35px;
    height:35px;
    flex:none;
    border-radius:11px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.09);
    display:flex;
    align-items:center;
    justify-content:center;
}

/* Personalización */
.login-theme-toggle{
    position:fixed;
    right:22px;
    bottom:22px;
    z-index:20;
    width:48px;
    height:48px;
    border:0;
    border-radius:16px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(145deg,var(--p),var(--p2));
    box-shadow:0 12px 28px color-mix(in srgb,var(--p) 25%,transparent);
    cursor:pointer;
    transition:transform .2s,box-shadow .2s;
}

.login-theme-toggle:hover{
    transform:translateY(-3px) rotate(4deg);
}

.login-theme-panel{
    position:fixed;
    right:22px;
    bottom:80px;
    z-index:21;
    width:285px;
    padding:15px;
    background:var(--card);
    color:var(--text);
    border:1px solid var(--line);
    border-radius:18px;
    box-shadow:0 20px 50px rgba(20,35,60,.16);
    transform:translateY(8px) scale(.98);
    opacity:0;
    pointer-events:none;
    transition:.2s;
}

.login-theme-panel.open{
    transform:none;
    opacity:1;
    pointer-events:auto;
}

.login-theme-title{font-size:.82rem;font-weight:850;margin-bottom:3px}
.login-theme-sub{font-size:.65rem;color:var(--muted);margin-bottom:12px}

.login-theme-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7px;
}

.login-theme-option{
    border:1px solid var(--line);
    background:var(--card);
    color:var(--text);
    border-radius:12px;
    padding:9px;
    text-align:left;
    cursor:pointer;
}

.login-theme-option:hover,
.login-theme-option.active{
    border-color:color-mix(in srgb,var(--p) 45%,#fff);
    background:var(--soft);
}

.login-theme-swatch{
    height:24px;
    border-radius:8px;
    margin-bottom:6px;
}

.login-theme-option strong{display:block;font-size:.66rem}
.login-theme-option small{font-size:.57rem;color:var(--muted)}

.login-theme-mode{
    width:100%;
    margin-top:8px;
    border:1px solid var(--line);
    background:var(--card);
    color:var(--text);
    border-radius:12px;
    padding:8px;
    font-size:.68rem;
    font-weight:750;
    cursor:pointer;
    text-align:left;
}

/* Modo oscuro */
body.sd-dark{
    --bg:#0b1220;
    --card:#182235;
    --text:#edf2f7;
    --muted:#9aa7ba;
    --line:#2b374b;
}

body.sd-dark .login-background{
    background:
        radial-gradient(circle at 12% 15%,color-mix(in srgb,var(--p) 15%,transparent),transparent 28rem),
        radial-gradient(circle at 88% 82%,color-mix(in srgb,var(--p2) 12%,transparent),transparent 30rem),
        var(--bg);
}

body.sd-dark .cardx{
    background:var(--card);
    border-color:var(--line);
    box-shadow:0 28px 80px rgba(0,0,0,.34);
}

body.sd-dark .form-panel,
body.sd-dark .form-control,
body.sd-dark .input-group-text,
body.sd-dark .toggle{
    background:var(--card);
    color:var(--text);
}

body.sd-dark .form-control::placeholder{color:#718096}

body.sd-dark .form-label,
body.sd-dark .form-panel h2{
    color:var(--text)!important;
}

body.sd-dark .text-muted{
    color:var(--muted)!important;
}

body.sd-dark .alert-danger{
    background:#3a1f28!important;
    color:#ffd9df!important;
    border:1px solid #6d3544!important;
}

body.sd-dark .login-theme-panel,
body.sd-dark .login-theme-option,
body.sd-dark .login-theme-mode{
    background:var(--card);
    color:var(--text);
    border-color:var(--line);
}

/* Colores */
body.sd-orange{
    --p:#f97316;
    --p2:#ea580c;
    --soft:#fff7ed;
}

body.sd-purple{
    --p:#8b5cf6;
    --p2:#7c3aed;
    --soft:#f5f3ff;
}

body.sd-green{
    --p:#10b981;
    --p2:#059669;
    --soft:#ecfdf5;
}

@media(max-width:991px){
    .panel,.form-panel{padding:2.3rem}
}

@media(max-width:575px){
    .container{padding-left:12px!important;padding-right:12px!important}
    .cardx{border-radius:22px}
    .panel,.form-panel{padding:2rem 1.5rem}
    .logo{width:65px;height:65px;border-radius:20px;margin-bottom:1.4rem}
    .login-theme-toggle{right:14px;bottom:14px}
    .login-theme-panel{right:12px;bottom:70px;width:min(285px,calc(100vw - 24px))}
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

<div class="container py-4">
<div class="row min-vh-100 align-items-center justify-content-center">
<div class="col-12 col-lg-10 col-xl-9">
<div class="card cardx"><div class="row g-0">
<div class="col-lg-5"><div class="panel h-100 d-flex flex-column justify-content-center">
<div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
<div class="small text-uppercase fw-semibold opacity-75">Tu espacio de apoyo académico</div>
<h1 class="display-6 fw-bold mt-2">Bienvenido a Studia360.</h1>
<p class="text-white-50">Continúa tu proceso, consulta tus contenidos y sigue avanzando.</p>
<div class="feature"><i class="bi bi-book-half"></i><span>Contenidos y recursos organizados.</span></div>
<div class="feature"><i class="bi bi-trophy-fill"></i><span>Progreso, niveles y coleccionables.</span></div>
<div class="feature"><i class="bi bi-person-heart"></i><span>Personaliza tu experiencia.</span></div>
</div></div>
<div class="col-lg-7"><div class="form-panel">
<div class="badgex mb-3"><i class="bi bi-shield-lock-fill me-1"></i> ACCESO A STUDIA360</div>
<h2 class="h2 fw-bold mb-2">Inicia sesión</h2>
<p class="text-muted mb-4">Ingresa con tu número de documento y contraseña.</p>
<?php if($error!==''): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form method="POST" action="autenticar.php">
<div class="mb-3"><label class="form-label fw-semibold">Número de documento</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-person-vcard-fill"></i></span><input class="form-control" type="text" name="documento" required inputmode="numeric" autocomplete="username" placeholder="Ingresa tu documento"></div></div>
<div class="mb-2"><label class="form-label fw-semibold">Contraseña</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock-fill"></i></span><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"><button type="button" class="btn toggle" id="toggle"><i class="bi bi-eye"></i></button></div></div>
<div class="d-flex justify-content-end mb-4"><a class="recover small" href="recuperar_password.php"><i class="bi bi-key me-1"></i>¿Olvidaste tu contraseña?</a></div>
<button class="btn btn-primary btnlogin w-100" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión</button>
</form>
<div class="text-center mt-4"><small class="text-muted"><i class="bi bi-shield-check me-1"></i>Acceso protegido de Studia360.</small></div>
</div></div>
</div></div></div></div></div>
<script>
document.getElementById('toggle').addEventListener('click',function(){const p=document.getElementById('password'),i=this.querySelector('i');if(p.type==='password'){p.type='text';i.classList.replace('bi-eye','bi-eye-slash')}else{p.type='password';i.classList.replace('bi-eye-slash','bi-eye')}});
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
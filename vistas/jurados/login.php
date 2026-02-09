<?php
session_start();
if (!empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/jurados.php');
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login Jurados - Elecciones 2026</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{
      --brand:#0b2a4a;
      --brand-2:#08a750;
      --bg:#f5f6f8;
    }
    body {
      background: #f8f9fa;
    }

    .jurados-logo {
      max-width: 360px;   /* ajusta 320-420 según te guste */
      width: 100%;
      height: auto;
      display: inline-block;
    }

    .muted{ color:#6b7280; font-size:.875rem; }
    .brand-title{ font-weight: 800; color: var(--brand); }
  </style>
</head>
<body class="bg-light">
<div class="container" style="max-width: 520px; padding-top: 60px;">
  <div class="card shadow-sm">
    <div class="card-header brand-title">
        <div class="text-center p-3">
            <img src="/inscripciones/images/logoSloganColor.png" alt="COOPTRAISS" class="img-fluid jurados-logo">
        </div>
        <div class="brand-title">Ingreso Jurados</div>
        <div class="muted">Uso interno - Jurados Cooptraiss</div>
    <div class="card-body">
      <div class="form-group">
        <label>Usuario</label>
        <input id="usuario" class="form-control" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input id="pass" type="password" class="form-control" autocomplete="current-password">
      </div>
      <button id="btn" class="btn btn-primary btn-block">Ingresar</button>
    </div>
  </div>
</div>

<script>
(async () => {
  const usuario = document.getElementById('usuario');
  const pass = document.getElementById('pass');
  const btn = document.getElementById('btn');

  async function postForm(url, data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));

    const r = await fetch(url, { method: 'POST', body: fd });

    const text = await r.text(); // <- lee SIEMPRE como texto primero
    let j = null;

    try { j = JSON.parse(text); }
    catch (e) {
      // Si no es JSON, mostramos el texto crudo (útil para ver el error PHP)
      throw new Error(text ? text.substring(0, 300) : 'Respuesta vacía del servidor.');
    }

    if (!r.ok) throw new Error(j?.msg || 'Error');
    return j;
  }


  async function login() {
    btn.disabled = true;
    try {
      await postForm('/inscripciones/controladores/jurados_login_Controller.php', {
        usuario: usuario.value.trim(),
        pass: pass.value
      });
      window.location.href = '/inscripciones/vistas/jurados/jurados.php';
    } catch (e) {
      await Swal.fire({ icon: 'error', title: 'No fue posible ingresar', text: e.message });
    } finally {
      btn.disabled = false;
    }
  }

  btn.addEventListener('click', login);
  pass.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') login();
  });
})();
</script>
</body>
</html>

<?php
session_start();
if (empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/login.php');
  exit;
}

// === Timeout por INACTIVIDAD (15 min) ===
$now = time();
$ttl = 15 * 60;

if (!isset($_SESSION['JUR_LAST_ACTIVITY'])) {
  $_SESSION['JUR_LAST_ACTIVITY'] = $now;
}

if (($now - (int)$_SESSION['JUR_LAST_ACTIVITY']) > $ttl) {
  session_unset();
  session_destroy();
  header('Location: /inscripciones/vistas/jurados/login.php?timeout=1');
  exit;
}

// Renovar actividad
$_SESSION['JUR_LAST_ACTIVITY'] = $now;

$jurado = $_SESSION['JUR_USER'];
$jurado_nombre = $_SESSION['JUR_NOMBRE'] ?? $jurado;
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro de votantes - Jurado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../images/logoIColor.png">
  <!-- Bootstrap CSS  -->
    
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

  <!-- SweetAlert2  -->
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
      max-width: 340px;   
      width: 100%;
      height: auto;
      display: inline-block;
    }

    .muted{ color:#6b7280; font-size:.875rem; }
    .brand-title{ font-weight: 800; color: var(--brand); }
  </style> 
</head>
<body class="bg-light">
<div class="container" style="max-width: 720px; padding-top: 30px;">
  <div class="card shadow-sm">
    <div class="card-header">
      <div class="text-center py-2">
        <img src="../../images/logoSloganColor.png"
            alt="COOPTRAISS"
            class="img-fluid jurados-logo">
      </div>

      <div>
        <b>Registro de Votantes (JURADO)</b>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-2">
        <span class="text-muted">
          Bienvenido, <b><?= htmlspecialchars($jurado_nombre) ?></b>
        </span>

        <a class="btn btn-sm btn-outline-danger"
          href="../../controladores/jurados_logout.php"
          onclick="return confirm('¿Desea cerrar sesión?');">
          Salir
        </a>
      </div>
    </div>

    <div class="card-body">

      <div class="form-group">
        <label for="aso_Id"><b>Cédula del votante:</b></label>
        <input type="text" class="form-control form-control-lg" id="aso_Id" autocomplete="off" placeholder="Digite cédula y Enter">
      </div>

      <div class="row">
        
        <div class="col-6 col-md-4 mb-2">
          <button class="btn btn-primary btn-lg btn-block" id="btnConsultar">Consultar</button>
        </div>

        <div class="col-6 col-md-3 mb-2">
          <button class="btn btn-outline-secondary btn-lg btn-block" id="btnLimpiar">Limpiar</button>
        </div>

        <div class="col-12 col-md-5 mb-2">
          <button class="btn btn-success btn-lg btn-block" id="btnConfirmar" disabled>Registrar voto</button>
        </div>
      </div>


      <hr>

      <div id="resultado" class="alert alert-secondary" role="alert">
        Sin consulta.
      </div>

      <div class="small text-muted">
        Nota: Si el votante está <b>inhábil</b> o <b>ya votó</b>, el sistema no permitirá registrar voto.
      </div>
      <div id="progreso-urna" class="alert alert-info mt-3 mb-0" style="display:none;">
        <b>Progreso:</b>
        Inscritos <span id="prog-inscritos">0</span> de <span id="prog-total">0</span> — Faltan <span id="prog-faltan">0</span>
      </div>


    </div>
  </div>
</div>

<script>
(() => {
  const $$ = (id) => document.getElementById(id);

  const input = $$('aso_Id');
  const btnConsultar = $$('btnConsultar');
  const btnConfirmar = $$('btnConfirmar');
  const btnLimpiar = $$('btnLimpiar');
  const resultado = $$('resultado');

  let estadoActual = null; // HABIL | INHABIL | NO_EXISTE | YA_VOTO
  let asoActual = null;    // {id,nombre,correo}

  function blurActivo() {
    try {
      if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
      }
    } catch (e) {}
  }


  function setAlert(type, html) {
    resultado.className = 'alert alert-' + type;
    resultado.innerHTML = html;
  }

  function limpiar() {
    input.value = '';
    estadoActual = null;
    asoActual = null;
    btnConfirmar.disabled = true;
    setAlert('secondary', 'Sin consulta.');
    input.focus();
  }

  async function postForm(url, data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));

    const r = await fetch(url, { method: 'POST', body: fd });

    const text = await r.text();   // leer texto primero
    let j = null;

    try { j = JSON.parse(text); }
    catch (e) {
      throw new Error(text ? text.substring(0, 300) : 'Respuesta vacía del servidor.');
    }

    if (!r.ok) {
      // Si es 401, redirigir siempre (timeout o no autorizado)
      if (r.status === 401) {
        window.location.href = '/inscripciones/vistas/jurados/login.php?timeout=1';
        return; // corta el flujo
      }

      // Si el backend manda redirect explícito, úsalo
      if (j?.redirect) {
        window.location.href = j.redirect;
        return;
      }

      throw new Error(j?.msg || 'Error');
    }

    return j;

  }


  async function consultar() {
    const aso_Id = (input.value || '').trim();
    if (!aso_Id) {
      setAlert('warning', 'Digite la cédula.');
      input.focus();
      return;
    }

    btnConfirmar.disabled = true;
    setAlert('info', 'Consultando...');

    try {
      const j = await postForm('../../controladores/jurados_Controller.php', { accion: 1, aso_Id });
      estadoActual = j.estado || null;
      asoActual = j.aso || null;

      if (estadoActual === 'NO_EXISTE') {
        setAlert('danger', '❌ ' + j.msg);
        btnConfirmar.disabled = true;
        return;
      }

      if (estadoActual === 'INHABIL') {
        setAlert('danger', `⛔ ${j.msg}<br><b>${asoActual?.nombre || ''}</b>`);
        btnConfirmar.disabled = true;
        return;
      }

      if (estadoActual === 'YA_VOTO') {
        setAlert('warning', `⚠️ ${j.msg}<br><b>${asoActual?.nombre || ''}</b><br>Fecha: ${j.voto?.fecha || ''}`);
        btnConfirmar.disabled = true;
        return;
      }

      if (estadoActual === 'HABIL') {
        const punto = asoActual?.punto ? `<br>${asoActual.punto}` : '';
        setAlert('success', `✅ ${j.msg}${punto}<br><b>${asoActual?.nombre || ''}</b><br>`);
        btnConfirmar.disabled = false;
        return;
      }


      if (estadoActual === 'FUERA_URNA') {
        blurActivo();
        await Swal.fire({ icon: 'warning', title: 'Fuera de su urna', text: j.msg });
        btnConfirmar.disabled = true;
        setAlert('warning', '⚠️ ' + j.msg);
        return;
      }


      setAlert('secondary', j.msg || 'Sin estado.');
    } catch (e) {
      setAlert('danger', 'Error: ' + e.message);
    }
  }

  async function cargarProgresoUrna() {
    try {
      const r = await fetch('../../controladores/jurados_Controller.php?accion=3', { method: 'GET' });
      const text = await r.text();
      let j = null;
      try { j = JSON.parse(text); } catch (e) { return; }
      if (!j || !j.ok) return;

      document.getElementById('prog-inscritos').textContent = j.inscritos;
      document.getElementById('prog-total').textContent = j.total;
      document.getElementById('prog-faltan').textContent = j.faltan;
      document.getElementById('progreso-urna').style.display = '';
    } catch (e) {
      // si falla, no mostramos el div
    }
  }



  async function registrarVoto() {
    if (estadoActual !== 'HABIL' || !asoActual?.id) {
      return;
    }
    blurActivo();
    const confirm = await Swal.fire({
      icon: 'question',
      title: '¿Registrar voto?',
      html: `<b>${asoActual.nombre}</b><br>${asoActual.id}<br><small></small>`,
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });

    if (!confirm.isConfirmed) {
      // Cancelación registrada
      try {
        await postForm('/../../controladores/jurados_Controller.php', { accion: 2, aso_Id: asoActual.id, decision: 'CANCELAR' });
      } catch (e) {
        // no bloquea el flujo
      }
      limpiar();
      return;
    }

    // Confirmado
    try {
      const j = await postForm('../../controladores/jurados_Controller.php', { accion: 2, aso_Id: asoActual.id, decision: 'CONFIRMAR' });
      blurActivo();
      await Swal.fire({
        icon: j.email_enviado ? 'success' : 'warning',
        title: 'Voto registrado',
        text: j.msg
      });
      cargarProgresoUrna();
      limpiar();
    } catch (e) {
      blurActivo();
      await Swal.fire({ icon: 'error', title: 'Error', text: e.message });
    }
  }

  btnConsultar.addEventListener('click', consultar);
  btnConfirmar.addEventListener('click', registrarVoto);
  btnLimpiar.addEventListener('click', limpiar);

  input.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      consultar();
    }
  });

  limpiar();
  cargarProgresoUrna();
})();
</script>
</body>
</html>
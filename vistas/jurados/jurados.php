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
          Jurado: <b><?= htmlspecialchars($jurado_nombre) ?></b><br>
          <small id="jurado-urnas" class="text-muted"></small>
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
        <div class="col-12 mb-2">
          <button class="btn btn-danger btn-lg btn-block" id="btnAnular" disabled>Anular voto</button>
        </div>

      </div>


      <hr>

      <div id="resultado" class="alert alert-secondary" role="alert">
        Sin consulta.
      </div>

      <div class="small text-muted">
        Nota: Si el votante está <b>inhábil</b> o <b>ya votó</b>, el sistema no permitirá registrar voto.
      </div>
      <div id="progreso-urna" class="alert alert-info mt-3 mb-0" style="display: none;">
        <b>Progreso urna <span id="reg-urna"></span>:</b>
        <span id="prog-inscritos">0</span> de <span id="prog-total">0</span> — Faltan <span id="prog-faltan">0</span>
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
  const btnAnular = $$('btnAnular');


  let estadoActual = null; // HABIL | INHABIL | NO_EXISTE | YA_VOTO
  let asoActual = null;    // {id,nombre,correo}
  let cacheUrnasJurado = null; 
  let cacheUrnasTs = 0;

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
    btnAnular.disabled = true;
    setAlert('secondary', 'Sin consulta.');
    input.focus();
  }



async function cargarUrnasJurado({ targetId, mode = 'lista', force = false }) {
  try {
    // 1) Obtener datos (cache 60s)
    let j = null;
    const cacheOk = cacheUrnasJurado && (Date.now() - cacheUrnasTs) < 60_000;

    if (!force && cacheOk) {
      j = cacheUrnasJurado;
    } else {
      const r = await fetch('../../controladores/jurados_Controller.php?accion=4', { method: 'GET' });
      const text = await r.text();
      try { j = JSON.parse(text); } catch (e) { return; }
      if (!j || !j.ok) return;

      cacheUrnasJurado = j;
      cacheUrnasTs = Date.now();
    }

    // 2) Pintar
    const el = document.getElementById(targetId);
    if (!el) return;

    const urnas = Array.isArray(j.urnas) ? j.urnas : [];
    if (urnas.length === 0) {
      el.textContent = (mode === 'principal_id') ? '' : 'Urnas: No asignadas';
      return;
    }

    const principal = urnas.find(u => !u.es_otras) || urnas[0];

    if (mode === 'principal_id') {
      el.textContent = String(principal.id);
      return;
    }

    if (mode === 'principal_txt') {
      el.textContent = `Urna principal: ${principal.id} (${principal.nombre})`;
      return;
    }

    // mode === 'lista'
    el.textContent = 'Urnas asignadas: ' + urnas.map(u => `${u.id} (${u.nombre})`).join(' - ');

  } catch (e) { /* silencioso */ }
}


async function postForm(url, data) {
  const fd = new FormData();
  Object.keys(data).forEach(k => fd.append(k, data[k]));

  const r = await fetch(url, {
    method: 'POST',
    body: fd,
    credentials: 'same-origin' //envía cookie de sesión PHP
  });

  const text = await r.text();
  let j = null;

  try { j = text ? JSON.parse(text) : null; }
  catch (e) { j = null; }

  // 401: timeout o no autorizado -> redirigir
  if (r.status === 401) {
    const redir = (j && j.redirect) ? j.redirect : '/inscripciones/vistas/jurados/login.php?timeout=1';
    window.location.href = redir;
    return null;
  }

  // Otros errores
  if (!r.ok) {
    if (j?.redirect) {
      window.location.href = j.redirect;
      return null;
    }

    const msg = j?.msg || (text ? text.substring(0, 300) : 'Respuesta vacía del servidor.');
    throw new Error(msg);
  }

  if (!j) {
    throw new Error(text ? text.substring(0, 300) : 'Respuesta vacía del servidor.');
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
    btnAnular.disabled = true;
    setAlert('info', 'Consultando...');

    try {
      const j = await postForm('../../controladores/jurados_Controller.php', { accion: 1, aso_Id });
      if (!j) return;
      estadoActual = j.estado || null;
      asoActual = j.aso || null;
      if (asoActual) {
        asoActual.urna = j.urna || null;
      }
      const urnaTxt = asoActual?.urna
        ? `<br><span class="badge badge-${asoActual.urna.es_otras ? 'warning' : 'info'}">
            URNA ${asoActual.urna.id} — ${asoActual.urna.nombre}${asoActual.urna.es_otras ? ' (OTRAS AGENCIAS)' : ''}
          </span>`
        : '';

      const puntoTxt = asoActual?.punto ? `<br>${asoActual.punto}` : '';

      if (estadoActual === 'NO_EXISTE') {
        setAlert('danger', '❌ ' + j.msg);
        btnConfirmar.disabled = true;
        btnAnular.disabled = true;
        return;
      }

      if (estadoActual === 'INHABIL') {
        setAlert('danger', `⛔ ${j.msg}<br><b>${asoActual?.nombre || ''}</b>`);
        btnConfirmar.disabled = true;
        return;
      }

      if (estadoActual === 'YA_VOTO') {
        setAlert('warning', `⚠️ ${j.msg}<b><br>${asoActual?.nombre || ''}</b><br>${urnaTxt}<br>${puntoTxt}<br>Fecha: ${j.voto?.fecha || ''}`);
        btnConfirmar.disabled = true;
        btnAnular.disabled = false; 
        return;
      }

      if (estadoActual === 'HABIL') {

        setAlert('success', `✅ ${j.msg}${urnaTxt}<br><b>${asoActual?.nombre || ''}</b>${puntoTxt}`);
        btnConfirmar.disabled = false;
        return;
      }

      if (estadoActual === 'FUERA_URNA') {
        blurActivo();
        await Swal.fire({ icon: 'warning', title: 'Fuera de su urna', text: j.msg });
        btnConfirmar.disabled = true;
        btnAnular.disabled = true;
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
      setAlert('danger', 'Error: ' + e.message);
    }
  }



  async function registrarVoto() {
    if (estadoActual !== 'HABIL' || !asoActual?.id) {
      return;
    }
    blurActivo();

    const urnaLine = asoActual?.urna
      ? `<div style="margin-top:6px;">
          <small><b>Urna:</b> ${asoActual.urna.id} — ${asoActual.urna.nombre}
          ${asoActual.urna.es_otras ? ' <br><span class="badge badge-warning">OTRAS AGENCIAS</span>' : ''}</small>
        </div>`
      : '';

    const puntoLine = asoActual?.punto ? `<div><small>${asoActual.punto}</small></div>` : '';

    const confirm = await Swal.fire({
      icon: 'question',
      title: '¿Registrar voto?',
      html: `<b>${asoActual.nombre}</b><br>${asoActual.id}${puntoLine}${urnaLine}`,
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });


    if (!confirm.isConfirmed) {
      limpiar();
      return;
    }

    // Confirmado
    try {
      const j = await postForm('../../controladores/jurados_Controller.php', { accion: 2, aso_Id: asoActual.id, decision: 'CONFIRMAR' });
      if (!j) return;

      const urnaResp = j.urna || asoActual?.urna || null;

      const urnaOkLine = urnaResp
        ? `Urna: ${urnaResp.id} — ${urnaResp.nombre}${urnaResp.es_otras ? ' (OTRAS AGENCIAS)' : ''}`
        : null;


      blurActivo();
      await Swal.fire({
        icon: j.email_enviado ? 'success' : 'warning',
        title: 'Voto registrado',
        html: `${j.msg || 'Voto registrado.'}${urnaOkLine ? `<br><small><b>${urnaOkLine}</b></small>` : ''}`
      });
      cargarProgresoUrna();
      limpiar();
    } catch (e) {
      blurActivo();
      await Swal.fire({ icon: 'error', title: 'Error', text: e.message });
    }
  }

  async function anularVoto() {
    if (estadoActual !== 'YA_VOTO' || !asoActual?.id) return;
    
    blurActivo();
    const confirm = await Swal.fire({
      icon: 'warning',
      title: '¿Anular voto?',
      html: `<b>${asoActual.nombre}</b><br>${asoActual.id}`,
      showCancelButton: true,
      confirmButtonText: 'Sí, anular',
      cancelButtonText: 'No',
      reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    try {
      const j = await postForm('../../controladores/jurados_Controller.php', {
        accion: 2,
        aso_Id: asoActual.id,
        decision: 'ANULAR'
      });
      if (!j) return;

      await Swal.fire({ icon: 'success', title: 'Listo', text: j.msg });
      cargarProgresoUrna();
      limpiar();
    } catch (e) {
      await Swal.fire({ icon: 'error', title: 'Error', text: e.message });
    }
}


  btnConsultar.addEventListener('click', consultar);
  btnConfirmar.addEventListener('click', registrarVoto);
  btnLimpiar.addEventListener('click', limpiar);
  btnAnular.addEventListener('click', anularVoto);
  input.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      consultar();
    }
  });

  limpiar();
  cargarUrnasJurado({ targetId: 'jurado-urnas', mode: 'lista' });
  cargarUrnasJurado({ targetId: 'reg-urna', mode: 'principal_id' });
  cargarProgresoUrna();
})();
</script>
</body>
</html>
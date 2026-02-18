<?php
session_start();
if (empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/login_correo.php');
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
  header('Location: /inscripciones/vistas/jurados/login_correo.php?timeout=1');
  exit;
}

$_SESSION['JUR_LAST_ACTIVITY'] = $now;

$rol = strtolower(trim((string)($_SESSION['JUR_ROL'] ?? '')));
if ($rol !== 'jurcorreo' && $rol !== 'superadmin') {
  header('Location: /inscripciones/vistas/jurados/login_correo.php?timeout=1');
  exit;
}

$usuario = $_SESSION['JUR_USER'];
$nombre  = $_SESSION['JUR_NOMBRE'] ?? $usuario;
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro por correo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../images/logoIColor.png">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{ --brand:#0b2a4a; --brand2:#08a750; }
    body { background:#f8f9fa; }
    .jurados-logo { max-width: 340px; width:100%; height:auto; display:inline-block; }
    .muted{ color:#6b7280; font-size:.875rem; }
    .brand-title{ font-weight: 800; color: var(--brand); }
    .radio-card{
      border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; cursor:pointer;
    }
    .radio-card.active{ border-color: var(--brand2); box-shadow:0 0 0 3px rgba(8,167,80,.15); }
  </style>
</head>
<body class="bg-light">
<div class="container" style="max-width: 720px; padding-top: 30px;">
  <div class="card shadow-sm">
    <div class="card-header">
      <div class="text-center py-2">
        <img src="../../images/logoSloganColor.png" alt="COOPTRAISS" class="img-fluid jurados-logo">
      </div>

      <div><b>Registro de Votos (CORREO)</b> <span class="muted">Urna 70</span></div>

      <div class="d-flex justify-content-between align-items-center mt-2">
        <span class="text-muted">Bienvenido, <b><?= htmlspecialchars($nombre) ?></b></span>
        <a class="btn btn-sm btn-outline-danger" href="../../controladores/jurados_logout.php" onclick="return confirm('¿Desea cerrar sesión?');">Salir</a>
      </div>
    </div>

    <div class="card-body">

      <div class="form-group">
        <label for="aso_Id"><b>Cédula del asociado:</b></label>
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

      <div class="mt-3" id="bloque-medio" style="display:none;">
        <div class="mb-2"><b>Seleccione el medio:</b></div>
        <div class="row">
          <div class="col-12 col-md-6 mb-2">
            <div class="radio-card" id="card-fisico">
              <div class="d-flex align-items-center">
                <input type="radio" name="medio" id="medio_fisico" value="CORREO_FISICO" class="mr-2">
                <label for="medio_fisico" class="mb-0"><b>Correo físico</b></label>
              </div>
              <div class="muted mt-1">El voto fue recibido en físico.</div>
            </div>
          </div>
          <div class="col-12 col-md-6 mb-2">
            <div class="radio-card" id="card-email">
              <div class="d-flex align-items-center">
                <input type="radio" name="medio" id="medio_email" value="CORREO_EMAIL" class="mr-2">
                <label for="medio_email" class="mb-0"><b>Correo electrónico</b></label>
              </div>
              <div class="muted mt-1">El voto fue recibido por email.</div>
            </div>
          </div>
        </div>
      </div>

      <hr>

      <div id="resultado" class="alert alert-secondary" role="alert">Digite una cédula y presione Consultar.</div>

    </div>
  </div>
</div>

<script>
(() => {
  const input = document.getElementById('aso_Id');
  const btnConsultar = document.getElementById('btnConsultar');
  const btnLimpiar = document.getElementById('btnLimpiar');
  const btnConfirmar = document.getElementById('btnConfirmar');
  const resultado = document.getElementById('resultado');
  const bloqueMedio = document.getElementById('bloque-medio');

  const cardFisico = document.getElementById('card-fisico');
  const cardEmail  = document.getElementById('card-email');
  const rbFisico   = document.getElementById('medio_fisico');
  const rbEmail    = document.getElementById('medio_email');

  let estadoActual = null;
  let asoActual = null;

  function setAlert(type, msg) {
    resultado.className = 'alert alert-' + type;
    resultado.innerHTML = msg;
  }

  function limpiarSeleccionMedio(){
    rbFisico.checked = false;
    rbEmail.checked = false;
    cardFisico.classList.remove('active');
    cardEmail.classList.remove('active');
  }

  function medioSeleccionado(){
    if (rbFisico.checked) return rbFisico.value;
    if (rbEmail.checked) return rbEmail.value;
    return '';
  }

  cardFisico.addEventListener('click', () => { rbFisico.checked = true; cardFisico.classList.add('active'); cardEmail.classList.remove('active'); });
  cardEmail.addEventListener('click',  () => { rbEmail.checked = true;  cardEmail.classList.add('active');  cardFisico.classList.remove('active'); });

  async function postForm(url, data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));

    const r = await fetch(url, { method: 'POST', body: fd });
    const text = await r.text();

    let j = null;
    try { j = JSON.parse(text); }
    catch (e) {
      throw new Error(text ? text.substring(0, 300) : 'Respuesta vacía del servidor.');
    }

    if (!r.ok) {
      if (r.status === 401) {
        window.location.href = '/inscripciones/vistas/jurados/login_correo.php?timeout=1';
        return;
      }
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
    bloqueMedio.style.display = 'none';
    limpiarSeleccionMedio();

    const j = await postForm('../../controladores/jurados_Controller.php', { accion: 1, aso_Id });
    estadoActual = j.estado || null;
    asoActual = j.aso || null;

    if (estadoActual === 'NO_EXISTE') {
      setAlert('warning', 'El asociado no está registrado.');
      return;
    }

    if (estadoActual === 'INHABIL') {
      setAlert('danger', `Asociado INHÁBIL. No puede votar.<br><b>${asoActual?.nombre || ''}</b>`);
      return;
    }

    if (estadoActual === 'FUERA_URNA') {
      setAlert('warning', `${j.msg || 'Fuera de urna.'}<br><b>${asoActual?.nombre || ''}</b>`);
      return;
    }

    if (estadoActual === 'YA_VOTO') {
      setAlert('info', `El asociado ya registra voto confirmado.<br><b>${asoActual?.nombre || ''}</b>`);
      return;
    }

    if (estadoActual === 'HABIL') {
      setAlert('success', `✅ Asociado HÁBIL. Puede registrar voto por correo.<br><b>${asoActual?.nombre || ''}</b><br><span class="muted">${asoActual?.punto || ''}</span>`);
      bloqueMedio.style.display = '';
      btnConfirmar.disabled = false;
      return;
    }

    setAlert('secondary', 'Estado no esperado.');
  }

  async function confirmar() {
    if (!asoActual?.id) return;

    const medio = medioSeleccionado();
    if (!medio) {
      await Swal.fire({ icon:'warning', title:'Seleccione el medio', text:'Debe escoger correo físico o correo electrónico.' });
      return;
    }

    const r = await Swal.fire({
      icon: 'question',
      title: '¿Registrar voto por correo?',
      html: `<b>${asoActual.nombre}</b><br>${asoActual.id}<br><span class="muted">${medio === 'CORREO_FISICO' ? 'Correo físico' : 'Correo electrónico'}</span>`,
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar'
    });

    if (!r.isConfirmed) return;

    const j = await postForm('../../controladores/jurados_Controller.php', {
      accion: 2,
      aso_Id: asoActual.id,
      decision: 'CONFIRMAR',
      medio_voto: medio
    });

    await Swal.fire({ icon:'success', title:'Voto registrado', text: j.msg || 'Voto registrado.' });

    // Reset
    input.value = '';
    input.focus();
    btnConfirmar.disabled = true;
    bloqueMedio.style.display = 'none';
    limpiarSeleccionMedio();
    setAlert('secondary', 'Digite una cédula y presione Consultar.');
  }

  function limpiar(){
    input.value = '';
    input.focus();
    estadoActual = null;
    asoActual = null;
    btnConfirmar.disabled = true;
    bloqueMedio.style.display = 'none';
    limpiarSeleccionMedio();
    setAlert('secondary', 'Digite una cédula y presione Consultar.');
  }

  btnConsultar.addEventListener('click', () => consultar().catch(e => setAlert('danger','Error: '+e.message)));
  btnConfirmar.addEventListener('click', () => confirmar().catch(e => setAlert('danger','Error: '+e.message)));
  btnLimpiar.addEventListener('click', limpiar);

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      consultar().catch(err => setAlert('danger','Error: '+err.message));
    }
  });

  input.focus();
})();
</script>

</body>
</html>

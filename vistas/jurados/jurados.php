<?php
session_start();
if (empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/login.php');
  exit;
}
$jurado = $_SESSION['JUR_USER'];
$jurado_nombre = $_SESSION['JUR_NOMBRE'] ?? $jurado;
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro de votantes - Jurado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS  -->
    
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

  <!-- SweetAlert2  -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container" style="max-width: 720px; padding-top: 30px;">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <b>Registro de Votantes (JURADO)</b>
            <span class="float-right text-muted"><?php echo htmlspecialchars($jurado_nombre); ?></span>
        </div>
        <a class="btn btn-sm btn-outline-danger"
            href="/inscripciones/controladores/jurados_logout.php"
            onclick="return confirm('¿Desea cerrar sesión?');">
            Salir
        </a>
    </div>
    <div class="card-body">

      <div class="form-group">
        <label for="aso_Id"><b>Cédula del asociado</b></label>
        <input type="text" class="form-control form-control-lg" id="aso_Id" autocomplete="off" placeholder="Digite cédula y Enter">
      </div>

      <button class="btn btn-primary btn-lg" id="btnConsultar">Consultar</button>
      <button class="btn btn-success btn-lg" id="btnConfirmar" disabled>Registrar voto</button>
      <button class="btn btn-outline-secondary btn-lg" id="btnLimpiar">Limpiar</button>

      <hr>

      <div id="resultado" class="alert alert-secondary" role="alert">
        Sin consulta.
      </div>

      <div class="small text-muted">
        Nota: Si el asociado está <b>inhábil</b> o <b>ya votó</b>, el sistema no permitirá registrar voto.
      </div>

    </div>
  </div>
</div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);

  const input = $('aso_Id');
  const btnConsultar = $('btnConsultar');
  const btnConfirmar = $('btnConfirmar');
  const btnLimpiar = $('btnLimpiar');
  const resultado = $('resultado');

  let estadoActual = null; // HABIL | INHABIL | NO_EXISTE | YA_VOTO
  let asoActual = null;    // {id,nombre,correo}

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

    if (!r.ok) throw new Error(j?.msg || 'Error');
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
      const j = await postForm('/inscripciones/controladores/jurados_Controller.php', { accion: 1, aso_Id });
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
        setAlert('success', `✅ ${j.msg}<br><b>${asoActual?.nombre || ''}</b><br>${asoActual?.correo || ''}`);
        btnConfirmar.disabled = false;
        return;
      }

      setAlert('secondary', j.msg || 'Sin estado.');
    } catch (e) {
      setAlert('danger', 'Error: ' + e.message);
    }
  }

  async function registrarVoto() {
    if (estadoActual !== 'HABIL' || !asoActual?.id) {
      return;
    }

    const confirm = await Swal.fire({
      icon: 'question',
      title: '¿Registrar voto?',
      html: `<b>${asoActual.nombre}</b><br>${asoActual.id}<br><small>${asoActual.correo || ''}</small>`,
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });

    if (!confirm.isConfirmed) {
      // Cancelación registrada
      try {
        await postForm('/inscripciones/controladores/jurados_Controller.php', { accion: 2, aso_Id: asoActual.id, decision: 'CANCELAR' });
      } catch (e) {
        // no bloquea el flujo
      }
      limpiar();
      return;
    }

    // Confirmado
    try {
      const j = await postForm('/inscripciones/controladores/jurados_Controller.php', { accion: 2, aso_Id: asoActual.id, decision: 'CONFIRMAR' });
      await Swal.fire({
        icon: j.email_enviado ? 'success' : 'warning',
        title: 'Listo',
        text: j.msg
      });
      limpiar();
    } catch (e) {
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
})();
</script>
</body>
</html>

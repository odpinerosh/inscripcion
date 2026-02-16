<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/login.php?timeout=1');
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
$_SESSION['JUR_LAST_ACTIVITY'] = $now;

// === Permisos reportes ===
$rol  = strtolower(trim((string)($_SESSION['JUR_ROL'] ?? '')));
$user = strtolower(trim((string)($_SESSION['JUR_USER'] ?? '')));

$permitidos_reportes = ['admin','39581549','51589322','11798151'];

// A reportes entra: superadmin o whitelist
if ($rol !== 'superadmin' && !in_array($user, $permitidos_reportes, true)) {
  header('Location: /inscripciones/vistas/jurados/login.php?timeout=1');
  exit;
}

$nombre = $_SESSION['JUR_NOMBRE'] ?? $user;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../images/logoIColor.png">
  <title>Reportes - COOPTRAISS</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

  <style>
    .wrap {
      max-width: 1100px;
      margin: 18px auto;
      padding: 0 12px;
    }

    .jurados-logo { max-height: 54px; }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .subline {
      color: #6c757d;
      font-size: 0.95rem;
    }

    .pill {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 999px;
      font-size: 0.85rem;
      border: 1px solid rgba(0,0,0,.1);
      background: #f8f9fa;
      margin-left: 6px;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .table thead th {
      background: #f8f9fa;
      border-bottom: 1px solid #dee2e6;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    .table-wrap {
      max-height: 62vh;
      overflow: auto;
      border: 1px solid #dee2e6;
      border-radius: .5rem;
    }

    .muted-note {
      color: #6c757d;
      font-size: .9rem;
    }
  </style>
</head>

<body>
  <div class="wrap">

    <div class="text-center py-2">
      <img src="../../images/logoSloganColor.png"
           alt="COOPTRAISS"
           class="img-fluid jurados-logo">
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="topbar">
          <div>
            <div class="h5 mb-1">Reportes</div>
            <div class="subline">
              ¡Hola, <b><?= htmlspecialchars($nombre) ?>!</b>
              <span id="jurado-urnas" class="pill" style="display:none;"></span>
            </div>
          </div>

          <a class="btn btn-sm btn-outline-danger"
             href="/inscripciones/controladores/jurados_logout.php"
             onclick="return confirm('¿Desea cerrar sesión?');">
            Salir
          </a>
        </div>

        <hr class="my-3">
        <div class="row mt-2" id="kpi-row" style="display:none;">
        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Total votos confirmados</div>
                <div class="h3 mb-0" id="kpi-confirmados">0</div>
            </div>
            </div>
        </div>

        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Total votos cancelados</div>
                <div class="h3 mb-0" id="kpi-cancelados">0</div>
            </div>
            </div>
        </div>

        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Total votos (confirmado + cancelado)</div>
                <div class="h3 mb-0" id="kpi-total">0</div>
            </div>
            </div>
        </div>
        </div>

        <div class="actions">
          <button id="btn-punto" class="btn btn-primary btn-sm">Votos por punto</button>
          <button id="btn-urna" class="btn btn-secondary btn-sm">Votos por urna</button>
          <button id="btn-jurado" class="btn btn-info btn-sm">Votos por jurado</button>
        </div>

        <div id="msg" class="alert alert-danger mt-3 py-2" style="display:none;"></div>

        <div class="d-flex justify-content-between align-items-end mt-3">
          <div>
            <div id="titulo" class="h6 mb-1"></div>
            <div id="subtitle" class="muted-note"></div>
          </div>
          <button id="btn-refresh" class="btn btn-outline-dark btn-sm" type="button" style="display:none;">
            Actualizar
          </button>
        </div>

        <div class="table-wrap mt-2">
          <table class="table table-sm mb-0">
            <thead id="thead"></thead>
            <tbody id="tbody"></tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  
  <script>
    // Evita volver con "Atrás" a una página congelada del historial
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) window.location.reload();
    });

    async function getJSON(url) {
      const r = await fetch(url, { method: 'GET' });
      const text = await r.text();
      let j = null;
      try { j = JSON.parse(text); }
      catch (e) { throw new Error(text ? text.substring(0, 200) : 'Respuesta inválida'); }

      if (!r.ok || !j?.ok) throw new Error(j?.msg || 'Error');
      return j;
    }

    function showMsg(s) {
      const el = document.getElementById('msg');
      if (!s) { el.style.display = 'none'; el.textContent = ''; return; }
      el.textContent = s;
      el.style.display = '';
    }

    function setKpis({ confirmados = 0, cancelados = 0 }) {
        const total = (Number(confirmados) || 0) + (Number(cancelados) || 0);

        document.getElementById('kpi-confirmados').textContent = confirmados;
        document.getElementById('kpi-cancelados').textContent = cancelados;
        document.getElementById('kpi-total').textContent = total;

        const row = document.getElementById('kpi-row');
        if (row) row.style.display = '';
    }

    function kpisFromRows(rows, keyConfirmados = 'confirmados', keyCancelados = 'cancelados') {
        let c = 0, x = 0;
        (rows || []).forEach(r => {
            c += Number(r?.[keyConfirmados] ?? 0) || 0;
            x += Number(r?.[keyCancelados] ?? 0) || 0;
        });
        return { confirmados: c, cancelados: x };
    }

    function toPct(n, total) {
        const x = Number(n) || 0;
        const t = Number(total) || 0;
        if (t <= 0) return '0.0%';
        return ((x * 100) / t).toFixed(1) + '%';
    }

    function addPctAndSort(rows, keyCount = 'confirmados') {
        const list = Array.isArray(rows) ? rows.slice() : [];
        const total = list.reduce((acc, r) => acc + (Number(r?.[keyCount] ?? 0) || 0), 0);

        list.forEach(r => { r.pct = toPct(r?.[keyCount], total); });

        list.sort((a, b) => (Number(b?.[keyCount] ?? 0) || 0) - (Number(a?.[keyCount] ?? 0) || 0));

        return { total, rows: list };
    }


    function renderTable(cols, rows) {
      const thead = document.getElementById('thead');
      const tbody = document.getElementById('tbody');

      thead.innerHTML = '<tr>' + cols.map(c => `<th class="text-nowrap">${c.label}</th>`).join('') + '</tr>';

      if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${cols.length}" class="text-center text-muted py-3">Sin datos</td></tr>`;
        return;
      }

      tbody.innerHTML = rows.map(r =>
        '<tr>' + cols.map(c => `<td class="text-nowrap">${(r[c.key] ?? '')}</td>`).join('') + '</tr>'
      ).join('');
    }

    // Mostrar urnas 
    async function cargarUrnasCabecera() {
      try {
        const j = await getJSON('../../controladores/jurados_Controller.php?accion=4');
        const pill = document.getElementById('jurado-urnas');
        const urnas = Array.isArray(j.urnas) ? j.urnas : [];
        if (!pill) return;

        if (urnas.length) {
          pill.textContent = 'Urnas: ' + urnas.map(u => u.id).join(', ');
          pill.style.display = '';
        } else {
          pill.style.display = 'none';
        }
      } catch (e) { /* silencioso */ }
    }

    let lastAction = null;

    async function runReport(action) {
      lastAction = action;
      showMsg('');

      const titulo = document.getElementById('titulo');
      const subtitle = document.getElementById('subtitle');
      const btnRefresh = document.getElementById('btn-refresh');

      btnRefresh.style.display = '';

      try {
        if (action === 30) {
            titulo.textContent = 'Votos confirmados por punto de atención';
            subtitle.textContent = 'Ranking de puntos (CONFIRMADOS) + participación sobre el total.';
            const j = await getJSON('../../controladores/jurados_Controller.php?accion=30');

            const pack = addPctAndSort(j.data || [], 'confirmados');

            // KPI: total confirmados 
            setKpis({ confirmados: pack.total, cancelados: 0 });

            renderTable(
                [
                { key: 'punto_id', label: 'Punto ID' },
                { key: 'punto', label: 'Punto' },
                { key: 'confirmados', label: 'Confirmados' },
                { key: 'pct', label: '% del total' }
                ],
                pack.rows
            );
        }

        if (action === 31) {
            titulo.textContent = 'Votos confirmados por urna';
            subtitle.textContent = 'Ranking de urnas (CONFIRMADOS) + participación sobre el total.';
            const j = await getJSON('../../controladores/jurados_Controller.php?accion=31');

            const pack = addPctAndSort(j.data || [], 'confirmados');

            setKpis({ confirmados: pack.total, cancelados: 0 });

            renderTable(
                [
                { key: 'punto', label: 'Punto' },
                { key: 'urna_id', label: 'Urna ID' },
                { key: 'urna', label: 'Urna' },
                { key: 'confirmados', label: 'Confirmados' },
                { key: 'pct', label: '% del total' }
                ],
                pack.rows
            );
        }

        if (action === 32) {
            titulo.textContent = 'Votos por jurado';
            subtitle.textContent = 'Conteo por jurado: CONFIRMADO y CANCELADO.';
            const j = await getJSON('../../controladores/jurados_Controller.php?accion=32');
            const k = kpisFromRows(j.data || [], 'confirmados', 'cancelados');
            setKpis(k);

            renderTable(
            [
                { key: 'jurado', label: 'Jurado' },
                { key: 'confirmados', label: 'Confirmados' },
                { key: 'cancelados', label: 'Cancelados' }
            ],
            j.data || []
            );
        }
      } catch (e) {
        showMsg(e.message);
      }
    }

    document.getElementById('btn-punto').addEventListener('click', () => runReport(30));
    document.getElementById('btn-urna').addEventListener('click', () => runReport(31));
    document.getElementById('btn-jurado').addEventListener('click', () => runReport(32));

    document.getElementById('btn-refresh').addEventListener('click', () => {
      if (lastAction) runReport(lastAction);
    });

    cargarUrnasCabecera();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

</body>
</html>

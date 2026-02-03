<?php

require_once __DIR__ . "/../../config/session_funcionarios.php";

$evento = trim($_GET['evento'] ?? '');

$titulo = "Inscribir (Interno)";

$BASE = "/inscripciones"; 

$contenido = '
  <div style="text-align: center">
      <h3>INSCRIPCION DE DELEGADOS<br>2026</h3>
  </div>
  <div class="card warn">
    <b>Documentos físicos (NO se cargan al sistema)</b>
    <ul style="margin:8px 0 0 -18px;">
      <li>Solicitar <b>la cédula fìsica o digital (no copia)</b>.</li>
      <li>Si el asociado <b>tiene menos horas</b>, solicitar el <b>certificado</b>.</li>
      <li>Verificar físicamente antes de finalizar.</li>
    </ul>
  </div>

  <div class="card">
    <form id="inscripcion" enctype="multipart/form-data">
      
      <div style="margin-bottom:10px;">
        <label><b>Cédula</b></label><br>
        <input type="text" id="documento" name="documento" onkeydown="numeros(event)" placeholder="Número de identificación" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;">
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="#" onclick="validar_Documento(); return false;">Consultar</a>
        <button class="btn" id="enviar" type="button" onclick="validar_Formulario();" disabled>Confirmar inscripción</button>
        <a class="btn" href="/inscripciones/vistas/funcionarios/index.php" style="background:#6b7280; color:#fff;">Volver</a>
      </div>

      <div id="div_Alertas" style="margin-top:12px;"></div>
      <div id="div_Datos" style="margin-top:12px;"></div>

      <div id="div_Loading" class="card" style="display:none; margin-top:12px;">
        Procesando...
      </div>

      <div id="div_Validar" style="display:none;"></div>
      <div id="div_Enviar" style="display:none;"></div>
    </form>
  </div>

';

$contenido .= "<script>window.INS_BASE = " . json_encode($BASE) . ";</script>";

$contenido .= '
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/inscripciones/js/auditoria_funcionarios.js?v=20260130a"></script>
';

require __DIR__ . "/plantilla.php";

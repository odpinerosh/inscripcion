<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";

$titulo = "Inscripción Delegados (Interno)";

$contenido = '
  <div class="card warn">
    <b>Documentos físicos (NO se cargan al sistema)</b>
    <ul style="margin:8px 0 0 18px;">
      <li>Solicitar <b>copia de la cédula</b>.</li>
      <li>Si el asociado <b>tiene menos horas</b>, solicitar el <b>certificado</b> correspondiente.</li>
      <li>Verificar físicamente antes de finalizar el registro.</li>
    </ul>
  </div>

  <div class="card">
    <div style="font-weight:700; margin-bottom:6px;">Ingreso por funcionarios</div>
    <div class="muted">Este módulo no usa OTP y no solicita adjuntos.</div>
  </div>
  <div class="card">
    <a class="btn" href="/inscripciones/vistas/funcionarios/inscribir.php">Registrar inscripción</a>
  </div>
';

if (!empty($_SESSION["FUNC_USER"]["usuario"]) && $_SESSION["FUNC_USER"]["usuario"] === "admin") {
  $contenido .= '
    <div class="card">
      <a class="btn" href="/inscripciones/vistas/funcionarios/crear_usuario.php">Crear usuarios</a>
    </div>
  ';
}

require __DIR__ . "/plantilla.php";

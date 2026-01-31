<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";

$titulo = "Inscripción Delegados (Interno)";

$contenido = '
  <div class="card warn mb-3">
    <div class="card-body">
      <div class="fw-bold mb-2">Documentos físicos (NO se cargan al sistema)</div>
      <ul class="mb-0 ps-3">
        <li>Solicitar <b>copia de la cédula</b>.</li>
        <li>Si el asociado <b>tiene menos horas</b>, solicitar el <b>certificado</b> correspondiente.</li>
        <li>Verificar físicamente antes de finalizar el registro.</li>
      </ul>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="fw-bold mb-1">Ingreso por funcionarios</div>
      <div class="muted">Este módulo no usa OTP y no solicita adjuntos.</div>
    </div>
  </div>

  <div class="d-grid gap-2 d-sm-flex mb-3">
    <a class="btn btn-brand" href="/inscripciones/vistas/funcionarios/inscribir.php">Registrar inscripción</a>
  </div>
';

if (!empty($_SESSION["FUNC_USER"]["usuario"]) && $_SESSION["FUNC_USER"]["usuario"] === "admin") {
  $contenido .= '
    <div class="d-grid gap-2 d-sm-flex">
      <a class="btn btn-outline-secondary" href="/inscripciones/vistas/funcionarios/crear_usuario.php">Crear usuarios</a>
    </div>
  ';
}

require __DIR__ . "/plantilla.php";

<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";

$titulo = "Inscripción Delegados (Interno)";

$contenido = '
  <div class="card warn mb-3">
    <div class="card-body">
      <div class="fw-bold mb-2">Documentos físicos (NO se cargan al sistema)</div>
      <ul class="mb-0 ps-3">
        <li>Solicitar <b>la cédula física o digital (no foto)</b>.</li>
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
';

$gerente = $_SESSION["FUNC_USER"]["usuario"] ?? "";

if ($gerente == "11798151") {
  $contenido .= '
    <div class="d-grid gap-2 d-sm-flex mb-3">
        <button class="btn btn-brand" disabled title="No autorizado">Registrar inscripción</button>
    </div>
  ';
} else {
    $contenido .= '
      <div class="d-grid gap-2 d-sm-flex mb-3">
        <a class="btn btn-brand" href="/inscripciones/vistas/funcionarios/inscribir.php">Registrar inscripción</a>
      </div>
    ';    
}


$permitidos_reporte = ["39581549", "51589322", "11798151", "admin"];

$func_user = $_SESSION["FUNC_USER"]["usuario"] ?? ""; // 

if ($func_user !== "" && in_array((string)$func_user, $permitidos_reporte, true)) {
  $contenido .= '
    <div class="d-grid gap-2 d-sm-flex mb-3">
        <a class="btn btn-outline-primary" href="/inscripciones/vistas/funcionarios/reporte_puntos.php">
            Reporte por punto de atención</a>
    </div>
    <div class="d-grid gap-2 d-sm-flex mb-3">
      <a class="btn btn-outline-primary" href="/inscripciones/vistas/funcionarios/reporte_insc_por_punto.php">
            Detalle por punto de atención</a>
    </div>
  ';
}

if (!empty($_SESSION["FUNC_USER"]["usuario"]) && ($_SESSION["FUNC_USER"]["usuario"] === "admin" || $_SESSION["FUNC_USER"]["rol"] === "gestor" )) {
  $contenido .= '
    <div class="d-grid gap-2 d-sm-flex">
      <a class="btn btn-outline-secondary" href="/inscripciones/vistas/funcionarios/crear_usuario.php">Administrar usuarios</a>
    </div>
  ';
}

require __DIR__ . "/plantilla.php";
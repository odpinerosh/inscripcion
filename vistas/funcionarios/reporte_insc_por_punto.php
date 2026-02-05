<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";
require_once __DIR__ . "/../../config/conecta.php";

$permitidos = ["39581549", "51589322", "11798151", "admin"];
$func_user = $_SESSION["FUNC_USER"]["usuario"] ?? "";

if ($func_user === "" || !in_array((string)$func_user, $permitidos, true)) {
  http_response_code(403);
  exit("No autorizado.");
}

$con = Conectar::conexion();
$titulo = "Reporte - Inscritos por punto (con delegado)";

// =========================
// 1) Cargar lista de puntos (solo los que tienen inscripciones)
// =========================
$sql_agencias = "
SELECT DISTINCT
  a.aso_Agen_Id  AS id_agencia,
  a.aso_NAgencia AS punto_atencion
FROM inscripcion i
JOIN asociados a ON a.aso_Id = i.ins_Part_Id
ORDER BY a.aso_NAgencia ASC
";
$res_ag = $con->query($sql_agencias);
if (!$res_ag) {
  http_response_code(500);
  exit("Error en consulta agencias: " . $con->error);
}

$agencias = [];
while ($r = $res_ag->fetch_assoc()) $agencias[] = $r;

// =========================
// 2) Leer filtro (GET)
// =========================
$filtro_agencia = $_GET["agencia"] ?? "ALL";
$filtro_agencia = trim((string)$filtro_agencia);

// Validar que si NO es ALL, sea numérico (por seguridad)
if ($filtro_agencia !== "ALL" && !ctype_digit($filtro_agencia)) {
  $filtro_agencia = "ALL";
}

// =========================
// 3) Consultar inscritos (con filtro opcional)
// =========================
$sql = "
SELECT
  a.aso_Agen_Id  AS id_agencia,
  a.aso_NAgencia AS punto_atencion,
  a.aso_Id       AS cedula,
  a.aso_Nombre   AS nombre,
  IFNULL(a.aso_Delegado,0) AS delegado
FROM inscripcion i
JOIN asociados a ON a.aso_Id = i.ins_Part_Id
";

if ($filtro_agencia !== "ALL") {
  $sql .= " WHERE a.aso_Agen_Id = ? ";
}

$sql .= " ORDER BY a.aso_NAgencia ASC, a.aso_Nombre ASC ";

$stmt = $con->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  exit("Error prepare: " . $con->error);
}

if ($filtro_agencia !== "ALL") {
  $idAg = (int)$filtro_agencia;
  $stmt->bind_param("i", $idAg);
}

if (!$stmt->execute()) {
  http_response_code(500);
  exit("Error execute: " . $stmt->error);
}


$stmt->bind_result($id_agencia, $punto_atencion, $cedula, $nombre, $delegado);

$data = [];
while ($stmt->fetch()) {
  $key = ((string)$id_agencia) . "||" . ((string)$punto_atencion);
  if (!isset($data[$key])) $data[$key] = [];

  $data[$key][] = [
    "id_agencia"     => $id_agencia,
    "punto_atencion" => $punto_atencion,
    "cedula"         => $cedula,
    "nombre"         => $nombre,
    "delegado"       => $delegado
  ];
}

$stmt->close();

// =========================
// 4) Render
// =========================
$contenido = '
  <div class="d-grid gap-2 d-sm-flex mb-3">
    <a class="btn btn-outline-secondary" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
    <a class="btn btn-outline-primary" href="/inscripciones/vistas/funcionarios/reporte_inscritos_por_agencia.php?agencia=' . urlencode($filtro_agencia) . '">Refrescar</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="fw-bold mb-2">Filtro</div>

      <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
          <label class="form-label mb-1">Punto de Atención</label>
          <select name="agencia" class="form-select" onchange="this.form.submit()">
            <option value="ALL"' . ($filtro_agencia === "ALL" ? " selected" : "") . '>TODOS</option>
';

foreach ($agencias as $ag) {
  $id = (string)($ag["id_agencia"] ?? "");
  $nom = (string)($ag["punto_atencion"] ?? "");
  $sel = ($filtro_agencia === $id) ? " selected" : "";
  $contenido .= '<option value="' . htmlspecialchars($id) . '"' . $sel . '>' . htmlspecialchars($nom) . '</option>';
}

$contenido .= '
          </select>
        </div>

        <div class="col-12 col-md-auto">
          <button type="submit" class="btn btn-outline-secondary">Aplicar</button>
        </div>
      </form>
    </div>
  </div>
';

if (empty($data)) {
  $contenido .= '
    <div class="alert alert-warning">
      No hay inscripciones para el filtro seleccionado.
    </div>
  ';
  require __DIR__ . "/plantilla.php";
  exit;
}

// Mostrar por agencia (bloques)
foreach ($data as $key => $rows) {
  [$idAg, $nomAg] = explode("||", $key);

  $contenido .= '
    <div class="card mb-3">
      <div class="card-body">
        <div class="fw-bold">' . htmlspecialchars($nomAg) . ' <span class="muted">(' . htmlspecialchars($idAg) . ')</span></div>
        <div class="muted"><strong>Inscritos: ' . number_format(count($rows), 0, ",", ".") . '</strong></div>

        <div class="table-responsive mt-3">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th style="width:180px;">Cédula</th>
                <th>Nombre</th>
                <th style="width:160px;">Delegado actual</th>
              </tr>
            </thead>
            <tbody>
  ';

  foreach ($rows as $r) {
    $esDelegado = ((int)($r["delegado"] ?? 0) === 1) ? "SI" : "NO";
    $contenido .= '
      <tr>
        <td>' . htmlspecialchars((string)($r["cedula"] ?? "")) . '</td>
        <td>' . htmlspecialchars((string)($r["nombre"] ?? "")) . '</td>
        <td>' . $esDelegado . '</td>
      </tr>
    ';
  }

  $contenido .= '
            </tbody>
          </table>
        </div>
      </div>
    </div>
  ';
}

require __DIR__ . "/plantilla.php";
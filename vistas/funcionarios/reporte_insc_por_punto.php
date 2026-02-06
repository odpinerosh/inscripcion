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


// 1. EXPORT CSV (Excel) - PLANO

$filtro_agencia = $_GET["agencia"] ?? "ALL";
$filtro_agencia = trim((string)$filtro_agencia);
if ($filtro_agencia !== "ALL" && !ctype_digit($filtro_agencia)) {
  $filtro_agencia = "ALL";
}


if (isset($_GET["export"]) && $_GET["export"] == "1") {

  // Conexión para export a Excel
  $conx = Conectar::conexion();

  $sql_export = "
    SELECT
        a.aso_Id       AS aso_Id,
        a.aso_Nombre   AS aso_Nombre,
        a.aso_Agen_Id  AS aso_Agen_Id,
        a.aso_NAgencia AS aso_NAgencia,
        IFNULL(a.aso_Delegado,0) AS aso_Delegado,
        i.ins_Fecha AS ins_Fecha,
        CASE
          WHEN aud.aud_documento IS NOT NULL THEN 'PRESENCIAL'
          ELSE 'VIRTUAL'
        END AS ins_Tipo,
        IFNULL(uf.nombre,'') AS func_nombre
      FROM inscripcion i
      JOIN asociados a ON a.aso_Id = i.ins_Part_Id
      LEFT JOIN (
        SELECT
          t.aud_documento,
          SUBSTRING_INDEX(GROUP_CONCAT(t.aud_usuario ORDER BY t.aud_fecha DESC), ',', 1) AS aud_usuario
        FROM ins_auditoria t
        GROUP BY t.aud_documento
      ) aud
        ON aud.aud_documento = i.ins_Part_Id
      LEFT JOIN usuarios_funcionarios uf
        ON uf.usuario = aud.aud_usuario
  ";

  if ($filtro_agencia !== "ALL") {
    $sql_export .= " WHERE a.aso_Agen_Id = ? ";
  }

  $sql_export .= " ORDER BY a.aso_Agen_Id ASC, a.aso_Id ASC ";

  // Preparar y ejecutar consulta export
  $stmtx = $conx->prepare($sql_export);
  if (!$stmtx) {
    http_response_code(500);
    exit("Error prepare export: " . $conx->error);
  }

  // Vincular parámetro si NO es ALL
  if ($filtro_agencia !== "ALL") {
    $idAg = (int)$filtro_agencia;
    $stmtx->bind_param("i", $idAg);
  }

  // Ejecutar consulta export
  if (!$stmtx->execute()) {
    http_response_code(500);
    exit("Error execute export: " . $stmtx->error);
  }

  // Función para generar una abreviatura del nombre de la agencia (para el filename)
  function slug_agencia($s, $len = 5) {
    $s = trim((string)$s);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('/[^A-Z0-9]/', '', $s);
    return substr($s, 0, $len);
  }


  // Configurar headers para descarga CSV
  if ($filtro_agencia === "ALL") {
    $suf = "TODOS";
  } else {
      $idAg = (int)$filtro_agencia;

      $stmtNom = $con->prepare("SELECT DISTINCT aso_NAgencia FROM asociados WHERE aso_Agen_Id = ? LIMIT 1");
      if (!$stmtNom) { http_response_code(500); exit("Error prepare nom agencia: " . $con->error); }

      $stmtNom->bind_param("i", $idAg);
      $stmtNom->execute();
      $stmtNom->bind_result($nomAg);
      $stmtNom->fetch();
      $stmtNom->close();

      $sigla = slug_agencia($nomAg ?? "", 5);   // 5 letras: ATLAN
      if ($sigla === "") $sigla = "AGENC";
      $suf = $idAg . $sigla;                    // 112ATLAN
  }

  $filename = "reporte_inscritos_" . $suf . "_" . date("Ymd_His") . ".csv";

  header("Content-Type: text/csv; charset=UTF-8");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  echo "\xEF\xBB\xBF"; // BOM para Excel

  // Escribir datos al CSV
  $out = fopen("php://output", "w");
  fputcsv($out, ["CEDULA","NOMBRE","COD_PUNTO","NOMBRE_PUNTO","DELEGADO ACTUAL", "FECHA INSCRIP.", "TIPO INSCRIP.", "FUNCIONARIO"], ";");

  $stmtx->bind_result($aso_Id, $aso_Nombre, $aso_Agen_Id, $aso_NAgencia, $aso_Delegado, $ins_Fecha, $ins_Tipo, $func_nombre);

  // Recorrer resultados y escribir al CSV
  while ($stmtx->fetch()) {
    $delegado_txt = ((int)$aso_Delegado === 1) ? "SI" : "NO";
    fputcsv($out, [$aso_Id, $aso_Nombre, $aso_Agen_Id, $aso_NAgencia, $delegado_txt, $ins_Fecha, $ins_Tipo, $func_nombre], ";");
  }

  fclose($out);
  $stmtx->close();
  $conx->close();
  exit;
}

// 2. Cargar lista de puntos que tienen inscripciones
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
$res_ag->free(); // liberar memoria


// Si no es ALL, validar que el ID de agencia existe en la lista (evitar inyección y errores)
if ($filtro_agencia !== "ALL" && !ctype_digit($filtro_agencia)) {
  $filtro_agencia = "ALL";
}

// 3. Consultar inscritos 
$sql = "
SELECT
  a.aso_Agen_Id  AS id_agencia,
  a.aso_NAgencia AS punto_atencion,
  a.aso_Id       AS cedula,
  a.aso_Nombre   AS nombre,
  IFNULL(a.aso_Delegado,0) AS delegado,
  i.ins_Ruta_Adj AS ruta_adj
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

// === Consumir resultado del stmt (SIN get_result) y armar $data por agencia ===
$stmt->bind_result($id_agencia, $punto_atencion, $cedula, $nombre, $delegado, $ruta_adj);

$data = [];
while ($stmt->fetch()) {
  $key = ((string)$id_agencia) . "||" . ((string)$punto_atencion);
  if (!isset($data[$key])) $data[$key] = [];
  $data[$key][] = [
    "id_agencia"     => $id_agencia,
    "punto_atencion" => $punto_atencion,
    "cedula"         => $cedula,
    "nombre"         => $nombre,
    "delegado"       => $delegado,
    "ruta_adj"       => $ruta_adj
  ];
}
$stmt->close();

function url_soporte($path) {
  $path = trim((string)$path);
  if ($path === "" || $path === "null") return null;

  $path = ltrim($path, "/");

  if (strpos($path, "inscripciones/soportes/") === 0) {
    return "/" . $path; 
  }
  if (strpos($path, "soportes/inscripciones/") === 0) {
    return "/inscripciones/" . $path;
  }

  return "/inscripciones/" . $path;
}


// 4. Render
$contenido = '
  <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
    <a class="btn btn-outline-primary" href="/inscripciones/vistas/funcionarios/reporte_insc_por_punto.php?agencia=' . urlencode($filtro_agencia) . '">Refrescar</a>
    <a class="btn btn-outline-success" href="/inscripciones/vistas/funcionarios/reporte_insc_por_punto.php?agencia=' . urlencode($filtro_agencia) . '&export=1">Exportar a Excel</a>
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

// 5. Mostrar por agencia (bloques)
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
                <th style="width:160px;">Desc. Cédula</th>
                <th style="width:160px;">Desc. Certificado</th>
              </tr>
            </thead>
            <tbody>
  ';

  foreach ($rows as $r) {
    $esDelegado = ((int)($r["delegado"] ?? 0) === 1) ? "SI" : "NO";
    $adj = [];
    if (!empty($r["ruta_adj"])) {
      $tmp = json_decode($r["ruta_adj"], true);
      if (is_array($tmp)) $adj = $tmp;
    }

    $uCedula = isset($adj["cedula"]) ? url_soporte($adj["cedula"]) : null;
    $uCert   = isset($adj["certificado"]) ? url_soporte($adj["certificado"]) : null;

    $linkCedula = ($uCedula)
      ? '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars($uCedula) . '" target="_blank">Cédula</a>'
      : '<span class="muted">No</span>';

    $linkCert = ($uCert)
      ? '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars($uCert) . '" target="_blank">Certificado</a>'
      : '<span class="muted">No</span>';


    $contenido .= '
      <tr>
        <td>' . htmlspecialchars((string)($r["cedula"] ?? "")) . '</td>
        <td>' . htmlspecialchars((string)($r["nombre"] ?? "")) . '</td>
        <td>' . $esDelegado . '</td>
        <td>' . $linkCedula . '</td>
        <td>' . $linkCert . '</td>

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
<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";
require_once __DIR__ . "/../../config/conecta.php"; 

$permitidos_reporte = ["39581549", "51589322", "11798151", "admin", "79635523"];
$func_user = $_SESSION["FUNC_USER"]["usuario"] ?? "";

if ($func_user === "" || !in_array((string)$func_user, $permitidos_reporte, true)) {
  http_response_code(403);
  exit("No autorizado.");
}

$con = Conectar::conexion();

$titulo = "Reporte - Inscripciones por punto de atención";

// Consulta (totales + estado)
$sql = "
SELECT
  a.aso_Agen_Id AS id_agencia,
  a.aso_NAgencia AS punto_atencion,
  COUNT(*) AS total,
  SUM(i.ins_Notificado IS NULL) AS sin_estado,
  SUM(i.ins_Notificado = 1) AS pendientes,
  SUM(i.ins_Notificado = 2) AS notificados,
  SUM(i.ins_Notificado = 3) AS en_proceso
FROM inscripcion i
JOIN asociados a ON a.aso_Id = i.ins_Part_Id
GROUP BY a.aso_Agen_Id, a.aso_NAgencia
ORDER BY total DESC, punto_atencion ASC
";

$res = $con->query($sql);
if (!$res) {
  http_response_code(500);
  exit("Error en consulta: " . $con->error);
}

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

// Construcción HTML
$contenido = '
  <div class="d-grid gap-2 d-sm-flex mb-3">
    <a class="btn btn-outline-secondary" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
    <a class="btn btn-outline-primary" href="/inscripciones/vistas/funcionarios/reporte_puntos.php">Refrescar</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="fw-bold">Inscripciones por punto de atención</div>
      <div class="muted">Fuente: inscripcion + asociados</div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead>
        <tr>
          <th>Cod. </th>
          <th>Punto atención</th>
          <th class="text-end">Total</th>
          <th class="text-end">Pendientes</th>
          <th class="text-end">Notificados</th>
        </tr>
      </thead>
      <tbody>
';

$sum_total = $sum_sin = $sum_pend = $sum_not = $sum_proc = 0;

foreach ($rows as $r) {
  $total = (int)$r["total"];
  $pend  = (int)$r["pendientes"];
  $not   = (int)$r["notificados"];

  $sum_total += $total;
  $sum_sin   += $sin;
  $sum_pend  += $pend;
  $sum_not   += $not;
  $sum_proc  += $proc;

  $contenido .= '
    <tr>
      <td>' . htmlspecialchars($r["id_agencia"] ?? "") . '</td>
      <td>' . htmlspecialchars($r["punto_atencion"] ?? "") . '</td>
      <td class="text-end">' . number_format($total, 0, ",", ".") . '</td>
      <td class="text-end">' . number_format($pend, 0, ",", ".") . '</td>
      <td class="text-end">' . number_format($not, 0, ",", ".") . '</td>
    </tr>
  ';
}

$contenido .= '
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="2" class="text-end">Totales</th>
          <th class="text-end">' . number_format($sum_total, 0, ",", ".") . '</th>
          <th class="text-end">' . number_format($sum_pend, 0, ",", ".") . '</th>
          <th class="text-end">' . number_format($sum_not, 0, ",", ".") . '</th>
        </tr>
      </tfoot>
    </table>
  </div>
';

require __DIR__ . "/plantilla.php";

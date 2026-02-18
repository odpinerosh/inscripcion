<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['JUR_USER'])) {
  http_response_code(401);
  echo json_encode([
      'ok' => false,
      'code' => 'SESSION_TIMEOUT',
      'msg' => 'Sesión expirada por inactividad (15 minutos). Inicie sesión nuevamente.',
      'redirect' => '/inscripciones/vistas/jurados/login.php?timeout=1'
  ]);
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

// Renovar actividad
$_SESSION['JUR_LAST_ACTIVITY'] = $now;

$jurado = $_SESSION['JUR_USER'];
$rol = strtolower(trim((string)($_SESSION['JUR_ROL'] ?? 'jurado')));


require_once __DIR__ . '/../config/conecta.php';
$conn = Conectar::conexion();

function respond($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function clean_id($v) {
  $v = trim((string)$v);
  // Permite cédulas alfanuméricas (ej. 05944333Y). Quita separadores comunes.
  $v = preg_replace('/[^0-9A-Za-z]+/', '', $v);
  return strtoupper($v);
}

function jurado_puede_ver_asociado($conn, $jurado_user, $aso_Id) {
  
  // Valida que el asociado esté en alguna urna asignada al jurado
  $sql = "SELECT 1
          FROM jurados_urnas ju
          JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
          WHERE ju.jurado_usuario = ?
            AND ua.aso_Id = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return false;
  $st->bind_param("ss", $jurado_user, $aso_Id);
  $st->execute();
  $st->store_result();
  return ($st->num_rows > 0);
}


$accion = isset($_REQUEST['accion']) ? (int)$_REQUEST['accion'] : 0;

// =======================
// ACCION 1: CONSULTAR
// =======================
if ($accion === 1) {
  $aso_Id = clean_id($_POST['aso_Id'] ?? $_GET['aso_Id'] ?? '');
  if ($aso_Id === '') {
    respond(['ok' => false, 'msg' => 'Digite la cédula.'], 400);
  }

  // Buscar asociado (SIN get_result)
  $st = $conn->prepare("SELECT aso_Id, aso_Nombre, aso_Correo, aso_Inhabil, a.aso_Agen_Id, p.punto_Nombre AS punto_nombre 
                          FROM asociados a LEFT JOIN ptos_atencion p ON p.punto_Id = a.aso_Agen_Id
                         WHERE a.aso_Id = ? LIMIT 1");
  if (!$st) respond(['ok' => false, 'msg' => 'Error preparando consulta.'], 500);

  $st->bind_param("s", $aso_Id);
  $st->execute();
  $st->store_result();

  if ($st->num_rows === 0) {
    $motivo = "No existe en tabla asociados";
    $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'NO_EXISTE', ?)");
    $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
    $st2->execute();

    respond(['ok' => true, 'estado' => 'NO_EXISTE', 'msg' => 'El asociado no está registrado.']);
  }

  $st->bind_result($db_id, $db_nombre, $db_correo, $db_inhabil, $db_agen_id, $db_punto_nombre);
  $st->fetch();

  $aso = [
    'id' => $db_id,
    'nombre' => $db_nombre,
    'correo' => $db_correo,
    'punto' => $db_punto_nombre ?: ('Punto ' . $db_agen_id)
  ];

  if ((int)$db_inhabil === 1) {
    $motivo = "aso_Inhabil=1";
    $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'INHABIL', ?)");
    $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
    $st2->execute();

    respond(['ok' => true, 'estado' => 'INHABIL', 'msg' => 'Asociado INHÁBIL. No puede votar.', 'aso' => $aso]);
  }

  // === VALIDACION DE URNA DEL JURADO ===
  if (!jurado_puede_ver_asociado($conn, $jurado, $aso_Id)) {

    // Registrar consulta FUERA_URNA en auditoría 
    $motivo = "FUERA_URNA: no pertenece a urnas asignadas al jurado";
    $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'FUERA_URNA', ?)");
    if ($st2) {
      $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
      $st2->execute();
    }

    respond([
      'ok' => true,
      'estado' => 'FUERA_URNA',
      'msg' => 'Este asociado NO está asignado a su mesa/urna.',
      'aso' => $aso
    ]);
  }


  // Validar si ya votó 
  $st3 = $conn->prepare("SELECT id, creado_en FROM elecciones_votos WHERE aso_Id = ? AND accion='CONFIRMADO' LIMIT 1");
  $st3->bind_param("s", $aso_Id);
  $st3->execute();
  $st3->store_result();

  $yaVoto = ($st3->num_rows > 0);

  $motivo = $yaVoto ? "Hábil, pero ya registra voto confirmado" : "Hábil";
  $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'HABIL', ?)");
  $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
  $st2->execute();

  if ($yaVoto) {
    $st3->bind_result($v_id, $v_fecha);
    $st3->fetch();

    respond([
      'ok' => true,
      'estado' => 'YA_VOTO',
      'msg' => 'El asociado ya registra voto.',
      'voto' => ['fecha' => $v_fecha],
      'aso' => $aso
    ]);
  }

  respond(['ok' => true, 'estado' => 'HABIL', 'msg' => 'Asociado HÁBIL. Puede votar.', 'aso' => $aso]);
}

// =======================
// ACCION 2: REGISTRAR
// =======================
if ($accion === 2) {
  $aso_Id = clean_id($_POST['aso_Id'] ?? '');
  $decision = strtoupper(trim($_POST['decision'] ?? '')); // CONFIRMAR | CANCELAR

  if ($aso_Id === '' || !in_array($decision, ['CONFIRMAR', 'CANCELAR'], true)) {
    respond(['ok' => false, 'msg' => 'Solicitud inválida.'], 400);
  }

  // Revalida asociado
  $st = $conn->prepare("SELECT aso_Id, aso_Nombre, aso_Correo, aso_Inhabil FROM asociados WHERE aso_Id = ? LIMIT 1");
  $st->bind_param("s", $aso_Id);
  $st->execute();
  $st->store_result();

  if ($st->num_rows === 0) {
    respond(['ok' => false, 'msg' => 'El asociado no está registrado.'], 404);
  }

  $st->bind_result($db_id, $db_nombre, $db_correo, $db_inhabil);
  $st->fetch();

  if ((int)$db_inhabil === 1) {
    respond(['ok' => false, 'msg' => 'Asociado INHÁBIL. No se puede registrar voto.'], 409);
  }

  if ($decision === 'CONFIRMAR') {
    $st3 = $conn->prepare("SELECT id FROM elecciones_votos WHERE aso_Id = ? AND accion='CONFIRMADO' LIMIT 1");
    $st3->bind_param("s", $aso_Id);
    $st3->execute();
    $st3->store_result();

    if ($st3->num_rows > 0) {
      respond(['ok' => false, 'msg' => 'El asociado ya registra voto confirmado.'], 409);
    }
  }

  $accionVoto = ($decision === 'CONFIRMAR') ? 'CONFIRMADO' : 'CANCELADO';

  if (!jurado_puede_ver_asociado($conn, $jurado, $aso_Id)) {

    // (Opcional) Auditoría del intento de registrar fuera de urna
    $motivo = "INTENTO_REGISTRO_FUERA_URNA: jurado=" . $jurado;
    $stA = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'REGISTRO_DENEGADO', ?)");
    if ($stA) {
      $stA->bind_param("sss", $aso_Id, $jurado, $motivo);
      $stA->execute();
    }

    respond(['ok' => false, 'msg' => 'No autorizado: asociado fuera de su urna/mesa.'], 403);
  }


  // Medio de voto (solo requerido para rol jurcorreo)
  $medio_voto = strtoupper(trim($_POST['medio_voto'] ?? ''));
  if ($rol === 'jurcorreo') {
    if (!in_array($medio_voto, ['CORREO_FISICO', 'CORREO_EMAIL'], true)) {
      respond(['ok' => false, 'msg' => 'Seleccione el medio de voto (correo físico o correo electrónico).'], 400);
    }
  } else {
    // Para jurados normales, no aplica
    $medio_voto = null;
  }

  // Determinar urna de registro
  $urna_reg = null;
  if ($rol === 'jurcorreo') {
    $urna_reg = 70;
  } else {
    // Tomar una urna del jurado donde esté asignado el asociado
    $stU = $conn->prepare("SELECT ju.urna_Id FROM jurados_urnas ju JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id WHERE ju.jurado_usuario = ? AND ua.aso_Id = ? LIMIT 1");
    if ($stU) {
      $stU->bind_param("ss", $jurado, $aso_Id);
      $stU->execute();
      $stU->bind_result($urna_reg);
      $stU->fetch();
      $stU->close();
    }
  }
  if (!$urna_reg) {
    respond(['ok' => false, 'msg' => 'No autorizado: asociado fuera de su urna/mesa.'], 403);
  }

  $st4 = $conn->prepare("INSERT INTO elecciones_votos (aso_Id, jurado_usuario, accion, urna_Id, medio_voto) VALUES (?, ?, ?, ?, ?)");
  $st4->bind_param("sssis", $aso_Id, $jurado, $accionVoto, $urna_reg, $medio_voto);
  if (!$st4->execute()) {
    // Detectar error de duplicado (voto confirmado existente)
    // Esto puede ocurrir si dos jurados intentan confirmar el voto del mismo asociado al mismo tiempo
    // El código de error 1062 corresponde a "Duplicate entry" en MySQL
    if ((int)$conn->errno === 1062 && $accionVoto === 'CONFIRMADO') {
      respond(['ok' => false, 'msg' => 'El asociado ya registra voto confirmado.'], 409);
    }
  respond(['ok' => false, 'msg' => 'No fue posible registrar el voto.'], 500);
  }
  $idVoto = $conn->insert_id;

  if ($decision === 'CANCELAR') {
    respond(['ok' => true, 'msg' => 'Operación cancelada.']);
  }

  // Envío de correo
  $emailOk = 0;
  $emailErr = null;

  /*try {
    require_once __DIR__ . '/../config/cooptraiss_mail.php';

    $to = $db_correo;
    //$cc = ['elecciones2026@cooptraiss.com', 'notificaciones@solucionescooptraiss.com'];
    $cc = ['notificaciones@solucionescooptraiss.com'];

    $subject = "ESTO ES UNA PRUEBA - Registro de voto - Elecciones Cooptraiss 2026 - ESTO ES UNA PRUEBA";
    $body = "
			<div style='font-family: Arial, Helvetica, sans-serif; background:#f5f7f9; padding:24px;'>
				<div style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9ee;'>
					<div style='background:#08a750; color:#fff; padding:18px 22px;'>
					  <h2 style='margin:0; font-size:18px;'>ESTO ES UNA PRUEBA - Voto registrado - ESTO ES UNA PRUEBA</h2>
					  <div style='opacity:.95; font-size:13px; margin-top:4px;'>Elección de Delegados<br>ESTO ES UNA PRUEBA - COOPTRAISS 2026-2030 - ESTO ES UNA PRUEBA</div>
					</div>

					<div style='padding:20px 22px; color:#1f2a37;'>
					  <div style='margin:0 0 12px 0; font-size:14px;'>
    				  <p>Apreciado(a) asociado(a),</p><p><b>" . htmlspecialchars($db_nombre) . "</b></p>
    					<!--p>Su voto ha sido <b>registrado exitosamente</b>.</p -->
						</div>

            <table style='width:100%; border-collapse:collapse; font-size:14px; margin:12px 0 18px 0;'>
              <tr>
                <td style='padding:8px 10px; background:#f3f4f6; width:38%; border:1px solid #e5e7eb;'><b>Documento</b></td>
                <td style='padding:8px 10px; border:1px solid #e5e7eb;'>". $aso_Id ."</td>
              </tr>
              <tr>
                <td style='padding:8px 10px; background:#f3f4f6; border:1px solid #e5e7eb;'><b>Fecha y hora</b></td>
                <td style='padding:8px 10px; border:1px solid #e5e7eb;'>". date('Y-m-d H:i:s') ."</td>
              </tr>
            </table>

            <p style='margin:16px 0 0 0; font-size:13px; color:#374151;'>
              Si requiere soporte escriba al correo <b>elecciones2026@cooptraiss.com</b>.
            </p>

            <p style='margin:18px 0 0 0; font-size:13px; color:#6b7280;'>
              Cordialmente,<br>
              <b>COOPTRAISS</b>
            </p>
					</div>

					<div style='padding:12px 22px; background:#f9fafb; color:#6b7280; font-size:12px; border-top:1px solid #e6e9ee;'>
						Por favor no responda este mensaje;--ESTO ES UNA PRUEBA-- se envía desde una cuenta de notificaciones automáticas.
					</div>
				</div>
			</div>
    ";

    send_mail($to, $subject, $body, $cc);
    $emailOk = 1;
  } catch (Throwable $e) {
    $emailOk = 0;
    $emailErr = substr($e->getMessage(), 0, 255);
  }*/

  $emailOk = 0;
  $emailErr = 'No se están enviando correos por decisión gerencial';
  $st5 = $conn->prepare("UPDATE elecciones_votos SET email_enviado = ?, email_error = ? WHERE id = ?");
  $st5->bind_param("isi", $emailOk, $emailErr, $idVoto);
  $st5->execute();

  respond([
    'ok' => true,
    'msg' => $emailOk ? 'Voto registrado.' : 'Voto registrado. ',
    'email_enviado' => $emailOk
  ]);
}

// ===========================================
// ACCION 3: PROGRESO DE INSCRIPCIÓN DE JURADO
// ===========================================

if ($accion === 3) {
  // Progreso de inscripción del jurado: inscritos vs total de su(s) urna(s)

  while ($conn->more_results() && $conn->next_result()) {
    if ($res = $conn->use_result()) { $res->free(); }
  }

  // Total hábiles en urnas asignadas al jurado
  $sqlTotal = "
    SELECT COUNT(DISTINCT ua.aso_Id) AS total_urna
    FROM jurados_urnas ju
    JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
    JOIN asociados a ON a.aso_Id = ua.aso_Id
    WHERE ju.jurado_usuario = ?
      AND a.aso_Inhabil = 0
  ";
  $total = 0;
  $stT = $conn->prepare($sqlTotal);
  if (!$stT) respond(['ok' => false, 'msg' => 'Error preparando total.'], 500);

  $stT->bind_param("s", $jurado);
  $stT->execute();
  $stT->bind_result($total);
  $stT->fetch();
  $stT->close();

  // Inscritos por ese jurado
  $sqlIns = "SELECT COUNT(*) AS inscritos FROM elecciones_votos WHERE jurado_usuario = ?";
  $inscritos = 0;
  $stI = $conn->prepare($sqlIns);
  if (!$stI) respond(['ok' => false, 'msg' => 'Error preparando inscritos.'], 500);

  $stI->bind_param("s", $jurado);
  $stI->execute();
  $stI->bind_result($inscritos);
  $stI->fetch();
  $stI->close();

  $faltan = max(0, (int)$total - (int)$inscritos);

  respond([
    'ok' => true,
    'total' => (int)$total,
    'inscritos' => (int)$inscritos,
    'faltan' => (int)$faltan
  ]);
}




respond(['ok' => false, 'msg' => 'Acción no válida.'], 400);
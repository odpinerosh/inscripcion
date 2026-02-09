<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['JUR_USER'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
  exit;
}
$jurado = $_SESSION['JUR_USER'];

require_once __DIR__ . '/../config/conecta.php';
$conn = Conectar::conexion();

function respond($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function clean_id($v) {
  $v = trim((string)$v);
  return preg_replace('/\D+/', '', $v);
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
  $st = $conn->prepare("SELECT aso_Id, aso_Nombre, aso_Correo, aso_Inhabil FROM asociados WHERE aso_Id = ? LIMIT 1");
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

  $st->bind_result($db_id, $db_nombre, $db_correo, $db_inhabil);
  $st->fetch();

  $aso = [
    'id' => $db_id,
    'nombre' => $db_nombre,
    'correo' => $db_correo
  ];

  if ((int)$db_inhabil === 1) {
    $motivo = "aso_Inhabil=1";
    $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo) VALUES (?,?, 'INHABIL', ?)");
    $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
    $st2->execute();

    respond(['ok' => true, 'estado' => 'INHABIL', 'msg' => 'Asociado INHÁBIL. No puede votar.', 'aso' => $aso]);
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

  $st4 = $conn->prepare("INSERT INTO elecciones_votos (aso_Id, jurado_usuario, accion) VALUES (?,?, ?)");
  $st4->bind_param("sss", $aso_Id, $jurado, $accionVoto);
  $st4->execute();
  $idVoto = $conn->insert_id;

  if ($decision === 'CANCELAR') {
    respond(['ok' => true, 'msg' => 'Operación cancelada.']);
  }

  // Envío de correo
  $emailOk = 0;
  $emailErr = null;

  try {
    require_once __DIR__ . '/../config/cooptraiss_mail.php';

    $to = $db_correo;
    //$cc = ['elecciones2026@cooptraiss.com', 'notificaciones@solucionescooptraiss.com'];
    $cc = ['notificaciones@solucionescooptraiss.com'];

    $subject = "Registro de voto - Elecciones Cooptraiss 2026";
    $body = "
			<div style='font-family: Arial, Helvetica, sans-serif; background:#f5f7f9; padding:24px;'>
				<div style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9ee;'>
					<div style='background:#08a750; color:#fff; padding:18px 22px;'>
					  <h2 style='margin:0; font-size:18px;'>Voto registrado</h2>
					  <div style='opacity:.95; font-size:13px; margin-top:4px;'>Elección de Delegados<br>COOPTRAISS 2026-2030</div>
					</div>

					<div style='padding:20px 22px; color:#1f2a37;'>
					  <div style='margin:0 0 12px 0; font-size:14px;'>
    				  <p>Apreciado(a) asociado(a),</p><p><b>" . htmlspecialchars($db_nombre) . "</b></p>
    					<p>Su voto ha sido <b>registrado exitosamente</b>.</p>
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
						Por favor no responda este mensaje; se envía desde una cuenta de notificaciones automáticas.
					</div>
				</div>
			</div>
    ";

    send_mail($to, $subject, $body, $cc);
    $emailOk = 1;
  } catch (Throwable $e) {
    $emailOk = 0;
    $emailErr = substr($e->getMessage(), 0, 255);
  }

  $st5 = $conn->prepare("UPDATE elecciones_votos SET email_enviado = ?, email_error = ? WHERE id = ?");
  $st5->bind_param("isi", $emailOk, $emailErr, $idVoto);
  $st5->execute();

  respond([
    'ok' => true,
    'msg' => $emailOk ? 'Voto registrado y notificación enviada.' : 'Voto registrado. Error enviando correo.',
    'email_enviado' => $emailOk
  ]);
}

respond(['ok' => false, 'msg' => 'Acción no válida.'], 400);
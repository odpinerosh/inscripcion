<?php 
	require_once("../config/session.php");

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	//conexión a base de datos
	require_once("../config/conecta.php");
	
	//Llamada al modelo
	require_once("../modelos/asociados_Model.php");
	require_once("../modelos/eventos_Model.php");

	// Función para validar sesión OTP
	function requireOtpSession($aso, $even) {

		// Funcionarios internos: NO requiere OTP
		if (!empty($_SESSION['FUNC_USER'])) {
			return [true, ""];
		}

		if (empty($_SESSION['otp_ok']) || empty($_SESSION['otp_expires'])) {
			return [false, "Debes validar tu acceso con el código enviado a tu correo antes de continuar."];
		}

		if (time() > (int)$_SESSION['otp_expires']) {
			// expirada: limpiar sesión OTP (sin tumbar otras)
			unset($_SESSION['otp_ok'], $_SESSION['otp_aso'], $_SESSION['otp_even'], $_SESSION['otp_expires']);
			return [false, "Tu sesión venció. Solicita un nuevo código e inténtalo de nuevo."];
		}

		if ((string)($_SESSION['otp_aso'] ?? '') !== (string)$aso || (string)($_SESSION['otp_even'] ?? '') !== (string)$even) {
			return [false, "Sesión inválida para este documento/evento."];
		}

		return [true, ""];
	}


	$accion = $_GET['accion'];

	switch ($accion) {
		case '1'://Consultar asociado
			$id_Asociado = $_GET['id_Asociado'] ?? '';
			$id_Evento   = $_GET['id_Evento'] ?? 'INSCRIPCIÓN DELEGADOS 2026';

			$btnSalir = "<button type='button' class='btn btn-secondary' onclick='reiniciar_Inscripcion()'>Salir</button>";

			if (!empty($_SESSION['FUNC_USER'])) {
				$btnSalir = "<a class='btn btn-secondary' href='/inscripciones/vistas/funcionarios/index.php'>Salir</a>";
			}


			// Validar sesión OTP
			list($okSess, $msgSess) = requireOtpSession($id_Asociado, $id_Evento);
			if (!$okSess) {
			echo "<div class='alert alert-danger' role='alert'>{$msgSess}</div>";
			exit;
			}

			//consultar asociado
			$asociados = New Asociados();
			$consultar_Asociado = $asociados->consultar_Asociado($id_Asociado, $id_Evento);
			
			/* TEMPORAL - Desactivar consulta*/
			/*error_log("KEYS consultar_Asociado: ".implode(',', array_keys($consultar_Asociado)));
			error_log("VAL horas candidates: ".
			json_encode([
				'aso_Horas'  => $consultar_Asociado['aso_Horas']  ?? null,
				'aso_horas'  => $consultar_Asociado['aso_horas']  ?? null,
				'ASO_HORAS'  => $consultar_Asociado['ASO_HORAS']  ?? null,
				'horas'      => $consultar_Asociado['horas']      ?? null,
			])
			);*/
			/* FIN TEMPORAL */

			if (!$consultar_Asociado) {
				//mostar div con error
				echo "
					<div class='alert alert-danger' role='alert'>
	                    El número de cédula ingresado no se encuentra en nuestros registros, por favor verifiquelo. Si el inconveniente persiste reporte la incidencia  al correo encuentroseducativos@cooptraiss.com
	                </div>
				";				
			}else{
				//validar si ya se encuentra inscrito
				$eventos = new Eventos();
				$validar_Inscripcion = $eventos->validar_Inscripcion($id_Asociado, $id_Evento);
				if (!$validar_Inscripcion ) {
					$esDelegado = (int)($consultar_Asociado['aso_Delegado'] ?? 0);
					$antiguedad = (float)($consultar_Asociado['aso_Antiguedad'] ?? 0);
					$inhabil   = (int)($consultar_Asociado['aso_Inhabil'] ?? 0);
					$empleado  = (int)($consultar_Asociado['aso_Empleado'] ?? 0);
					$horas     = (float)($consultar_Asociado['aso_Horas'] ?? 0);
					$requiereCert = ($horas < 80);


					//mostrar formulario para inscripción
					$html = "
					<input type='hidden' id='es_delegado' name='es_delegado' value='".$esDelegado."'>
					<input type='hidden' id='antiguedad' name='antiguedad' value='".$antiguedad."'>
					<input type='hidden' id='requiere_cert' name='requiere_cert' value='".($requiereCert ? "1":"0")."'>


					<div id='div_Nombre' class='form-group'>
						<h5>Nombre</h5>
						<span class='input-group-prepend'>
						<input type='text' name='nombre' id='nombre' class='form-control' readonly value='".$consultar_Asociado['aso_Nombre']."'>
						<span class='input-group-text' style='background-color:#08a750;color:#fff101'><i class='fas fa-user'></i></span>
						</span>
					</div>

					<div id='div_identificacion' class='form-group'>
						<h5>Identificación</h5>
						<span class='input-group-prepend'>
						<input type='text' name='identificacion' class='form-control' value='".$id_Asociado."' id='identificacion' readonly>
						<span class='input-group-text' style='background-color:#08a750;color:#fff101'><i class='fas fa-id-card'></i></span>
						</span>
					</div>

					<div id='div_Correo' class='form-group'>
						<h5>Correo electrónico (verificado)</h5>
						<span class='input-group-prepend'>
						<input type='text' name='correo' id='correo' class='form-control' value='".$consultar_Asociado['aso_Correo']."' readonly>
						<span class='input-group-text' style='background-color:#08a750;color:#fff101'><i class='fas fa-at'></i></span>
						</span>
					</div>

					<div id='div_Celular' class='form-group' style='display:none'>
						<h5>Número de celular</h5>
						<span class='input-group-prepend'>
						<input type='text' name='celular' id='celular' class='form-control' maxlength='10'
								value='".$consultar_Asociado['aso_Celular']."' readonly>
						<span class='input-group-text' style='background-color:#08a750;color:#fff101'><i class='fas fa-phone'></i></span>
						</span>
					</div>
					<br>
					";

					if ($esDelegado === 1) {
						$html .= "
							<div class='alert alert-info' role='alert'>
								Ya eres delegado actualmente. Puedes confirmar tu inscripción.
							</div>
						";
					} else {

						// Bloqueos duros 
						if ($inhabil === 1) {
							$html .= "
							<div class='alert alert-danger' role='alert'>
								<b>⛔ No habilitado para inscripción</b>
								<p>Motivo: <b>Asociado inhábil por mora</b>.</p>
								
							</div><br>
							<div class='form-group' style='text-align:center; margin-top:20px'>
								{$btnSalir}
							</div>
							";
							echo $html; exit;
						}

						if ($empleado === 1) {
							$html .= "
							<div class='alert alert-danger' role='alert'>
								<b>⛔ No habilitado para inscripción</b>
								<p>Motivo: <b>Fue empleado de confianza</b> en los últimos tres años.</p>							
							</div><br>
							<div class='form-group' style='text-align:center; margin-top:20px'>
								{$btnSalir}
							</div>
							";
							echo $html; exit;
						}

						if ($antiguedad < 5.0) {
							$antiguedadTxt = number_format($antiguedad, 1, '.', '');
							$html .= "
							<div class='alert alert-danger' role='alert'>
								<b>⛔ No habilitado para inscripción</b>
								<p>Motivo: No cumple la antigüedad mínima de <b>5 años</b>. (Antigüedad actual: {$antiguedadTxt} años)</p>
							</div><br>
							<div class='form-group' style='text-align:center; margin-top:20px'>
								{$btnSalir}
							</div>
							";
							echo $html; exit;
						}
					}
					// Pasa reglas → en asociados pide PDF, en funcionarios es físico
					$html .= "<hr>";

					if (!empty($_SESSION['FUNC_USER'])) {

						// === FUNCIONARIOS: DOCUMENTOS FÍSICOS (NO PDF) ===
						$html .= "
						<div class='alert alert-success' role='alert'>
							<b>Cumple con los requisitos para inscribirse.</b><br>
							Documentos físicos a solicitar:
							<ul style='margin: 8px 0 0 -18px;'>
							<li><b>Fotocopia de la cédula</b> (físico)</li>"
							. ($requiereCert
								? "<li><b>Certificado de cooperativismo</b> (físico) — registra ".number_format($horas,0,'.','')." horas y se requieren 80</li>"
								: ""
								)
							. "</ul>
						</div>
						<div class='alert alert-info' role='alert'>
							Nota: En este módulo interno <b>no se cargan adjuntos</b>.
						</div>
						";

					} else {

						// === ASOCIADOS:  ===
						if ($requiereCert) {
							$horasTxt = number_format($horas, 0, '.', '');
							$html .= "
							<div class='alert alert-success' role='alert'>
								<b>Cumples con los requisitos para inscribirte</b><br>
								Para continuar, adjunta en PDF:
								<ul style='margin: 8px 0 0 -18px;'>
								<li><b>Fotocopia de tu cédula</b></li>
								<li><b>Certificado de cooperativismo</b> (registras {$horasTxt} horas y se requieren 80)</li>
								</ul>
							</div>
							<div class='form-group'>
								<label><b>Fotocopia de la cédula (PDF)</b></label>
								<input type='file' id='pdf_cedula' name='pdf_cedula' class='form-control' accept='application/pdf'>
								<small class='text-muted'>Solo PDF.</small>
							</div>
							<div class='form-group'>
								<label><b>Certificado curso Cooperativismo (PDF)</b></label>
								<input type='file' id='pdf_certificado' name='pdf_certificado' class='form-control' accept='application/pdf'>
								<small class='text-muted'>Solo PDF.</small>
							</div>
							";
						} else {
							$html .= "
							<div class='alert alert-success' role='alert'>
								<b>Cumples con los requisitos para inscribirte</b><br>
								Para continuar, adjunta en PDF:
								<ul style='margin: 8px 0 0 -18px;'>
								<li><b>Fotocopia de tu cédula</b></li>
								</ul>
							</div>
							<div class='form-group'>
								<label><b>Fotocopia de la cédula (PDF)</b></label>
								<input type='file' id='pdf_cedula' name='pdf_cedula' class='form-control' accept='application/pdf'>
								<small class='text-muted'>Solo PDF.</small>
							</div>
							";
						}
					}


					$disabled = ($esDelegado === 1) ? "" : "disabled";

					$html .= "
					<div class='alert alert-danger' role='alert' id='errores' style='display:none'>
						<h5>Debes corregir:</h5>
					</div>

					<div id='div_Enviar' class='form-group' style='text-align:center; margin-top:20px'>
						<input class='btn btn-success' type='button' name='enviar' id='enviar'
							value='Confirmar inscripción'
							style='font-size: large; font-weight:bold'
							onclick='validar_Formulario()' ".$disabled.">
					</div>
					<div class='form-group' style='text-align:center; margin-top:10px'>
						{$btnSalir}
					</div>
					<div id='div_Loading' class='form-group' style='text-align:center; display:none; margin-top:10px'>
						<label>Se está procesando la solicitud, por favor espere.</label>
						<img src='images/loading.gif' style='max-width: 240px;'>
					</div>
					";

					echo $html;

				}else{
					//mensaje informando que ya está inscrito
					$inscripcion = $validar_Inscripcion['ins_Fecha'] ?? '';
					$insTxt = htmlspecialchars((string)$inscripcion, ENT_QUOTES, 'UTF-8');
					$idTxt  = htmlspecialchars((string)$id_Asociado, ENT_QUOTES, 'UTF-8');

					echo "
					<div class='alert alert-primary' role='alert'>
						El documento <b>{$idTxt}</b> ya se encuentra inscrito.<p>
						".($insTxt !== '' ? " Registrado en <b>{$insTxt}</b>.</p>" : "")."
					</div>

					<div class='form-group' style='text-align:center; margin-top:20px'>
						{$btnSalir}
					</div>
					";

				}
			}
				
		break;

		case '2'://Consultar agencias
			$asociados = New Asociados();
			$consultar_Agencias = $asociados->consultar_Agencias();
			if (!$consultar_Agencias) {
				echo "<h4>No es posible cargar las agencias</h4>";
			}else{
				echo "
					<h5>Agencia</h5>
					<div class='input-group'>
						<select id='agencias' class='form-control' style='cursor:pointer'>";
						echo "<option value='0'>Seleccione</option>";
						while ($columna = mysqli_fetch_array($consultar_Agencias)) {
							echo "<option value='".$columna['agen_Id']."'>".$columna['agen_Nombre']."</option>";
						}
				echo "</select><span class='input-group-prepend'><span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-map-marker-alt'></i></span></span>
				</div>";
			}
		break;

		
		case '8': // validar cédula y devolver pista de correo (JSON)
			header('Content-Type: application/json; charset=utf-8');

			$id_Asociado = $_GET['id_Asociado'] ?? '';
			$id_Evento   = $_GET['id_Evento'] ?? '';

			if ($id_Asociado === '' || $id_Evento === '') {
				http_response_code(400);
				echo json_encode(["ok"=>false,"msg"=>"Parámetros incompletos."]);
				exit;
			}

			$asociados = new Asociados();
			$info = $asociados->consultar_Asociado($id_Asociado, $id_Evento);

			if (!$info) {
				http_response_code(404);
				echo json_encode(["ok"=>false,"msg"=>"Documento no encontrado."]);
				exit;
			}

			$correo = trim((string)($info['aso_Correo'] ?? ''));
			$pista  = $asociados->mascarar_Correo($correo);

			echo json_encode(["ok"=>true,"correo_hint"=>$pista]);
			exit;


		case '9': // validar correo y enviar OTP (JSON)
			$OTP_TTL_MIN = 10; // duración del OTP en minutos
			$COOLDOWN_SEC = 60; // segundos para reenviar OTP
			
			header('Content-Type: application/json; charset=utf-8');

			$id_Asociado = $_POST['id_Asociado'] ?? '';
			$id_Evento   = $_POST['id_Evento'] ?? '';
			$correoIn    = trim((string)($_POST['correo'] ?? ''));

			if ($id_Asociado === '' || $id_Evento === '' || $correoIn === '') {
				http_response_code(400);
				echo json_encode(["ok"=>false,"msg"=>"Parámetros incompletos."]);
				exit;
			}

			$asociados = new Asociados();
			$info = $asociados->consultar_Asociado($id_Asociado, $id_Evento);

			if (!$info) {
				http_response_code(404);
				echo json_encode(["ok"=>false,"msg"=>"Documento no encontrado."]);
				exit;
			}

			$correoBD = strtolower(trim((string)($info['aso_Correo'] ?? '')));
			if ($correoBD === '' || strtolower($correoIn) !== $correoBD) {
				http_response_code(401);
				echo json_encode(["ok"=>false,"msg"=>"El correo no coincide con el registrado."]);
				exit;
			}

			$cool = $asociados->validarCooldownOtp($id_Asociado, $id_Evento, $COOLDOWN_SEC);
			if (!$cool["ok"]) {
				http_response_code(429);
				echo json_encode([
					"ok" => false,
					"msg" => "Espera {$cool['wait']} segundos para reenviar el código.",
					"waitSec" => (int)$cool["wait"]
				]);
				exit;
			}
			
			// invalidar OTPs previos
			$asociados->invalidarOtpsPrevios($id_Asociado, $id_Evento);

			// generar OTP
			$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$ok = $asociados->crear_OTP($id_Asociado, $id_Evento, $otp, $OTP_TTL_MIN);

			if (!$ok) {
				http_response_code(500);
				echo json_encode(["ok"=>false,"msg"=>"No fue posible generar el código."]);
				exit;
			}

			// Envío de correo (simple). 
			//$asunto = "Código de acceso al evento";
			//$msg    = "Tu código de acceso es: $otp\n\nVence en $OTP_TTL_MIN minutos.";
			//@mail($correoBD, $asunto, $msg);


			$sent = $asociados->mail_Otp($correoBD, $otp, $OTP_TTL_MIN);

			if (!$sent) {
				http_response_code(500);
				echo json_encode([
				"ok"=>false,
				"msg"=>"Se generó el código, pero no fue posible enviar el correo. Intenta reenviar."
				]);
				exit;
			}

			echo json_encode([
			"ok" => true,
			"msg" => "Te enviamos un código de 6 dígitos a tu correo.",
			"message" => "Te enviamos un código de 6 dígitos a tu correo.",
			"ttlMin" => $OTP_TTL_MIN
			]);
			exit;


		case '10': // verificar OTP y abrir sesión (JSON)
			header('Content-Type: application/json; charset=utf-8');

			$id_Asociado = $_POST['id_Asociado'] ?? '';
			$id_Evento   = $_POST['id_Evento'] ?? '';
			$codigo      = preg_replace('/\D+/', '', (string)($_POST['codigo'] ?? ''));

			if ($id_Asociado === '' || $id_Evento === '' || strlen($codigo) !== 6) {
				http_response_code(400);
				echo json_encode(["ok"=>false,"msg"=>"Código inválido."]);
				exit;
			}

			$asociados = new Asociados();
			$resp = $asociados->verificar_OTP($id_Asociado, $id_Evento, $codigo);

			if (!$resp['ok']) {
				http_response_code(401);
				echo json_encode($resp);
				exit;
			}


			// sesión aprobada (con expiración)
			$OTP_SESSION_TTL_MIN = 20; // configurable: vigencia de la sesión (ej. 20 min)

			session_regenerate_id(true); // evita session fixation

			$_SESSION['otp_ok']      = true;
			$_SESSION['otp_aso']     = (string)$id_Asociado;
			$_SESSION['otp_even']    = (string)$id_Evento;
			$_SESSION['otp_time']    = time();
			$_SESSION['otp_expires'] = time() + ($OTP_SESSION_TTL_MIN * 60);

			// (Opcional) amarrar a user-agent (útil, pero puede molestar si cambian de navegador)
			// $_SESSION['otp_ua'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);

			echo json_encode([
			"ok" => true,
			"msg" => "Acceso confirmado.",
			"sessionTtlMin" => $OTP_SESSION_TTL_MIN
			]);
			exit;



		default:
			echo "Problemas de comunicación, intente nuevamente";
		break;
	}
?>
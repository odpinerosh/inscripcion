<?php 
	session_start();
	//conexión a base de datos
	require_once("../config/conecta.php");
	//Llamada al modelo
	require_once("../modelos/asociados_Model.php");
	require_once("../modelos/eventos_Model.php");

	// Función para validar sesión OTP
	function requireOtpSession($aso, $even) {
		if (empty($_SESSION['otp_ok']) || empty($_SESSION['otp_expires'])) {
			return [false, "Debes validar tu acceso con el código enviado a tu correo antes de continuar."];
		}
		if (time() > (int)$_SESSION['otp_expires']) {
			// expirada: limpiar
			$_SESSION = [];
			if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
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
			$id_Asociado = $_GET['id_Asociado'];
			$id_Evento = $_GET['id_Evento'];

			// Validar sesión OTP
			list($okSess, $msgSess) = requireOtpSession($id_Asociado, $id_Evento);
			if (!$okSess) {
			echo "<div class='alert alert-danger' role='alert'>{$msgSess}</div>";
			exit;
			}

			//consultar asociado
			$asociados = New Asociados();
			$consultar_Asociado = $asociados->consultar_Asociado($id_Asociado, $id_Evento);
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
								No puedes inscribirte: actualmente estás <b>inhábil por mora</b>.
							</div>
							<div class='form-group' style='text-align:center'>
								<button type='button' class='btn btn-secondary' onclick='reiniciar_Inscripcion()'>Salir</button>
							</div>";
							echo $html; exit;
						}

						if ($empleado === 1) {
							$html .= "
							<div class='alert alert-danger' role='alert'>
								No puedes inscribirte: fuiste <b>empleado de confianza</b> en los últimos tres (3) años.
							</div>
							<div class='form-group' style='text-align:center'>
								<button type='button' class='btn btn-secondary' onclick='reiniciar_Inscripcion()'>Salir</button>
							</div>";
							echo $html; exit;
						}

						if ($antiguedad < 5.0) {
							$antiguedadTxt = number_format($antiguedad, 1, '.', '');
							$html .= "
							<div class='alert alert-danger' role='alert'>
								No cumples la antigüedad mínima de <b>5 años</b>. (Antigüedad actual: {$antiguedadTxt} años)
							</div>
							<div class='form-group' style='text-align:center'>
								<button type='button' class='btn btn-secondary' onclick='reiniciar_Inscripcion()'>Salir</button>
							</div>";
							echo $html; exit;
						}

						// Pasa reglas → pide PDFs
						$html .= "
						<hr>
						<div class='alert alert-warning' role='alert'>
							Para confirmar tu inscripción debes adjuntar los soportes en PDF.
						</div>

						<div class='form-group'>
							<label><b>Fotocopia de la cédula (PDF)</b></label>
							<input type='file' id='pdf_cedula' name='pdf_cedula' class='form-control' accept='application/pdf'>
							<small class='text-muted'>Solo PDF.</small>
						</div>
						";

						if ($requiereCert) {
							$horasTxt = number_format($horas, 0, '.', '');
							$html .= "
							<div class='alert alert-info' role='alert'>
								Registras <b>{$horasTxt} horas</b>. Como son menos de <b>80</b>, debes adjuntar el <b>certificado de cooperativismo</b>.
							</div>

							<div class='form-group'>
								<label><b>Certificado curso Cooperativismo (PDF)</b></label>
								<input type='file' id='pdf_certificado' name='pdf_certificado' class='form-control' accept='application/pdf'>
								<small class='text-muted'>Solo PDF.</small>
							</div>
							";
						}

						$html .= "
						<div class='form-group' style='text-align:center'>
							<button type='button' class='btn btn-secondary' onclick='cancelar_Subida()'>Cancelar</button>
						</div>
						";
					}


					$disabled = ($esDelegado === 1) ? "" : "disabled";

					$html .= "
					<div class='alert alert-danger' role='alert' id='errores' style='display:none'>
						<h5>Debes corregir:</h5>
					</div>

					<div id='div_Enviar' class='form-group' style='text-align:center'>
						<input class='btn btn-success' type='button' name='enviar' id='enviar'
							value='Confirmar inscripción'
							style='font-size: large; font-weight:bold'
							onclick='validar_Formulario()' ".$disabled.">
					</div>

					<div id='div_Loading' class='form-group' style='text-align:center; display:none'>
						<label>Se está procesando la solicitud, por favor espere.</label>
						<img src='images/loading.gif' style='max-width: 240px;'>
					</div>
					";

					echo $html;

				}else{
					//mensaje informando que ya está inscrito
					$inscripcion = $validar_Inscripcion ['ins_Fecha'];
					echo"
					<div class='alert alert-primary' role='alert'>
	                    El documento ".$id_Asociado." ya se encuentra inscrito al evento. Registrado en ".$inscripcion."
	                </div>";
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
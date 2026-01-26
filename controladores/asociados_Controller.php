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

					//mostrar formulario para inscripción
					echo "
						<div id='div_Nombre' class='form-group'>
	                        <h5>Nombre</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='nombre' id='nombre'  class='form-control' readonly value='".$consultar_Asociado['aso_Nombre']."'></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-user'></i></span>
	                        </span>
	                    </div>
	                    <div id='div_identificacion' class='form-group'>
	                        <h5>identificación</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='identificacion' class='form-control' placeholder='Ingrese el número' value='".$id_Asociado."' id='identificacion' readonly></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-id-card'></i></span>
	                        </span>
	                    </div>
	                    <div id='div_Correo' class='form-group'>
	                        <h5>Correo electrónico</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='correo' id='correo' class='form-control' placeholder='Ingrese el correo' value='".$consultar_Asociado['aso_Correo']."'></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-at'></i></span>
	                        </span>
	                    </div>
	                    <div id='div_Correo_Confirma' class='form-group'>
	                        <h5>Confirmar Correo</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='correo_Confirma' id='correo_Confirma' class='form-control' placeholder='Confirme el correo'></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-at'></i></span>
	                        </span>
	                    </div>
	                    <div id='div_Celular' class='form-group' style='display: none'>
	                        <h5>Número de celular</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='celular' id='celular' class='form-control' placeholder='Ingrese el número' onKeyDown='numeros(event)' maxlength='10' value='".$consultar_Asociado['aso_Celular']."' readonly></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-phone'></i></span>
	                        </span>
	                    </div><br>
	                    <div id='div_Participante' class='form-group' style='display: none'>
	                        <h5>Participante Id</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='participante' id='participante' class='form-control' placeholder='Ingrese el número' onKeyDown='numeros(event)' maxlength='10' value='".$consultar_Asociado['part_Id']."' readonly></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-phone'></i></span>
	                        </span>
	                    </div>
	                    <div class='alert alert-danger' role='alert' id='errores' style='display:none'>
		                    <h5>Debes corregir:</h5>
		                </div>
	                    <div id='div_Enviar' class='form-group' style='text-align: center'>
	                        <input class='btn btn-success' type='button' name='enviar' id='enviar' value='Confirmar inscripción' class='form-control' style='font-size: large; font-weight:bold' onclick='validar_Formulario()'>
	                    </div>
	                    <div id='div_Loading' class='form-group' style='text-align: center; display:none'>
	                    	<label>Se está procesando la solicitud, por favor espere.</label>
	                        <img src='images/loading.gif' style='max-width: 240px;'>
	                    </div>
					";
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
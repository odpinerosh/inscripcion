<?php 
date_default_timezone_set("America/Bogota");

	class Asociados{

	    private $conecta;

	    public function __construct(){
	    	$this->conecta=Conectar::conexion();
	    }

		public function consultar_Asociado($identificacion, $evento){
			$query_bs_acces = "SELECT * FROM participantes_view WHERE part_Aso_Id = '$identificacion' AND even_Id = '$evento'";
			//echo $query_bs_acces;
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$acceso = mysqli_fetch_array($bs_acces);
		    	}else{
		        	$acceso = false;
		    	} 
			}else{
				$acceso = false;
			}
			//var_dump($query_bs_acces);
			return $acceso;
		}

		public function consultar_Asociados(){
			$query_bs_acces = "SELECT * FROM asociados";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = mysqli_fetch_array($bs_acces);
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function consultar_Agencias(){
			$query_bs_acces = "SELECT * FROM agencias";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = $bs_acces;
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function consultar_Agencia($id_Asociado){
			$query_bs_acces = "SELECT * FROM asociados_Agencia WHERE aso_Id = '$id_Asociado'";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = $bs_acces;
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function mascarar_Correo($correo){
			$correo = trim((string)$correo);
			if ($correo === '' || strpos($correo,'@') === false) return "correo registrado";
			[$u,$d] = explode('@', $correo, 2);
			$uMask = substr($u,0,1) . str_repeat('*', max(1, strlen($u)-1));
			return $uMask . '@' . $d;
		}

		public function crear_OTP($asoId, $evenId, $otpPlain, $ttlMin){
			$asoId = mysqli_real_escape_string($this->conecta, $asoId);
			$evenId = mysqli_real_escape_string($this->conecta, $evenId);

			$hash = password_hash($otpPlain, PASSWORD_DEFAULT);
			$hash = mysqli_real_escape_string($this->conecta, $hash);

			$ttlMin = (int)$ttlMin;
			if ($ttlMin < 1) $ttlMin = 10;

			$ip = mysqli_real_escape_string($this->conecta, $_SERVER['REMOTE_ADDR'] ?? '');
			$ua = mysqli_real_escape_string($this->conecta, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250));

			$sql = "
			INSERT INTO acceso_otp (aso_Id, even_Id, code_hash, expires_at, ip, user_agent)
			VALUES ('$asoId', '$evenId', '$hash', DATE_ADD(NOW(), INTERVAL $ttlMin MINUTE), '$ip', '$ua')
			";
			return (bool)mysqli_query($this->conecta, $sql);
		}

		// Valida si aplica cooldown para nuevo OTP
		public function validarCooldownOtp($asoId, $evenId, $cooldownSec = 60){
			$asoId = mysqli_real_escape_string($this->conecta, (string)$asoId);
			$evenId = mysqli_real_escape_string($this->conecta, (string)$evenId);
			$cooldownSec = (int)$cooldownSec;

			// Busca el OTP más reciente que aún sea "relevante"
			$sql = "
			SELECT created_at, expires_at, used_at
			FROM acceso_otp
			WHERE aso_Id = '$asoId'
				AND even_Id  = '$evenId'
			ORDER BY created_at DESC
			LIMIT 1
			";

			$rs = mysqli_query($this->conecta, $sql);
			if (!$rs || mysqli_num_rows($rs) === 0) {
				return ["ok" => true, "wait" => 0];
			}

			$row = mysqli_fetch_assoc($rs);

			// Si ya fue usado, no aplica cooldown
			if (!empty($row['used_at'])) {
				return ["ok" => true, "wait" => 0];
			}

			// Si ya expiró, no aplica cooldown
			if (!empty($row['expires_at']) && strtotime($row['expires_at']) <= time()) {
				return ["ok" => true, "wait" => 0];
			}

			// Cooldown por tiempo desde created_at
			$created = !empty($row['created_at']) ? strtotime($row['created_at']) : 0;
			if ($created > 0) {
				$diff = time() - $created;
				if ($diff < $cooldownSec) {
					return ["ok" => false, "wait" => ($cooldownSec - $diff)];
				}
			}

			return ["ok" => true, "wait" => 0];
		}

		// Invalida OTPs previos (marcar como usados)
		public function invalidarOtpsPrevios($asoId, $evenId){
			$asoId = mysqli_real_escape_string($this->conecta, (string)$asoId);
			$evenId = mysqli_real_escape_string($this->conecta, (string)$evenId);

			$sql = "
			UPDATE acceso_otp
			SET used_at = NOW()
			WHERE aso_Id = '$asoId'
				AND even_Id   = '$evenId'
				AND used_at IS NULL
				AND expires_at > NOW()
			";
			return mysqli_query($this->conecta, $sql);
		}

		// Enviar correo con OTP
		public function mail_Otp($correo, $otp, $ttlMin){
			// PHPMailer local
			require_once __DIR__ . '/../clases/PHPMailer/PHPMailerAutoload.php';
			// Config SMTP
			require_once __DIR__ . '/../config/mail.php';
			// Logo en el correo
			$logoPath = __DIR__ . '/../images/logo.jpg';  
			$logoCid  = 'logo_cooptraiss';
			// Contenido
			$asunto = 'Código de acceso al evento';
			$fecha  = date("Y-m-d H:i:s");

			$mensaje = "
				<div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; line-height: 1.4;'>
					<div style='padding:18px; border:1px solid #e5e5e5; border-radius:12px;'>
						<div style='text-align:center; margin-bottom:20px;'>
							<img src='cid:{$logoCid}' alt='COOPTRAISS' style='max-width:200px;'>
						</div>
					
					<p style='margin:0 0 14px; font-size:14px; color:#333;'>
						A continuación encuentras tu <b>código de acceso</b>:
					</p>

					<div style='font-size:30px; letter-spacing:6px; font-weight:700; padding:14px 16px;
								border:1px dashed #08a750; border-radius:12px; display:inline-block; color:#111;'>
						{$otp}
					</div>

					<p style='margin:14px 0 0; font-size:14px; color:#333;'>
						<b>Vigencia:</b> {$ttlMin} minutos.
					</p>

					<p style='margin:14px 0 0; font-size:12px; color:#666;'>
						Fecha/hora: {$fecha}<br>
						Si no solicitaste este código, puedes ignorar este mensaje.
					</p>
					</div>

					<p style='margin:12px 0 0; font-size:11px; color:#777; font-style:italic;'>
						Por favor no respondas este mensaje; se envía desde una cuenta de notificaciones automáticas.
					</p>
				</div>
			";

			$mail = new PHPMailer(true);

			// (Opcional) Debug: SOLO para pruebas controladas
			if (defined('MAIL_DEBUG') && MAIL_DEBUG === true) {
				$mail->SMTPDebug = 2; // muestra detalle SMTP
				$mail->Debugoutput = 'error_log';
			}

			$mail->isSMTP();
			$mail->Host       = MAIL_SMTP_HOST;
			$mail->Port       = MAIL_SMTP_PORT;
			$mail->SMTPAuth   = (bool)MAIL_SMTP_AUTH;
			$mail->SMTPSecure = MAIL_SMTP_SECURE;

			$mail->Username   = MAIL_SMTP_USER;
			$mail->Password   = MAIL_SMTP_PASS;

			
			$fromEmail = MAIL_FROM_EMAIL ?: MAIL_SMTP_USER;
			$fromName  = MAIL_FROM_NAME  ?: 'COOPTRAISS';

			$mail->setFrom($fromEmail, $fromName);
			$mail->addAddress($correo);

			if (defined('MAIL_BCC') && MAIL_BCC !== '') {
				$mail->addBCC(MAIL_BCC);
			}

			$mail->Subject = utf8_decode($asunto);
			if (file_exists($logoPath)) {
			    $mail->addEmbeddedImage($logoPath, $logoCid, 'logo.jpg');
			}

			$mail->Body    = utf8_decode($mensaje);
			$mail->AltBody = "Tu código de acceso es: {$otp}. Vigencia: {$ttlMin} minutos.";
			$mail->isHTML(true);

			// Importante para caracteres
			$mail->CharSet = 'UTF-8';

			try {
				return $mail->send();
			} catch (Exception $e) {
				// Log mínimo 
				error_log("PHPMailer OTP error: " . $mail->ErrorInfo);
				return false;
			}
		}



		public function verificar_OTP($asoId, $evenId, $otpPlain){
			$asoId = mysqli_real_escape_string($this->conecta, $asoId);
			$evenId = mysqli_real_escape_string($this->conecta, $evenId);

			$sql = "
			SELECT id, code_hash, expires_at, attempts, used_at
			FROM acceso_otp
			WHERE aso_Id='$asoId' AND even_Id='$evenId'
				AND used_at IS NULL
			ORDER BY id DESC
			LIMIT 1
			";
			$rs = mysqli_query($this->conecta, $sql);
			if (!$rs || mysqli_num_rows($rs) === 0){
				return ["ok"=>false,"msg"=>"No hay código activo. Solicita uno nuevo."];
			}

			$row = mysqli_fetch_assoc($rs);

			// vencido
			if (strtotime($row['expires_at']) < time()){
				return ["ok"=>false,"msg"=>"El código venció. Solicita uno nuevo."];
			}

			// límite de intentos (ej. 5)
			if ((int)$row['attempts'] >= 5){
				return ["ok"=>false,"msg"=>"Demasiados intentos. Solicita un nuevo código."];
			}

			$ok = password_verify($otpPlain, $row['code_hash']);

			// siempre incrementa attempts si falla
			if (!$ok){
				$id = (int)$row['id'];
				mysqli_query($this->conecta, "UPDATE acceso_otp SET attempts = attempts + 1 WHERE id = $id");
				return ["ok"=>false,"msg"=>"Código incorrecto."];
			}

			// marcar usado
			$id = (int)$row['id'];
			mysqli_query($this->conecta, "UPDATE acceso_otp SET used_at = NOW() WHERE id = $id");

			return ["ok"=>true];
		}

   }

?>
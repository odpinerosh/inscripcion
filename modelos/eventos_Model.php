<?php 
date_default_timezone_set("America/Bogota");

	class Eventos{

	    private $conecta;

	    public function __construct(){
	    	$this->conecta=Conectar::conexion();
	    }
	    //Consultar Inscripciones a eventos
		public function consultar_Inscripciones(){
			$query_bs_acces = "SELECT * FROM inscripciones_view";
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

		//Consultar Inscripción individual
		public function validar_Inscripcion($id_Asociado, $id_Evento){
		$id = mysqli_real_escape_string($this->conecta, (string)$id_Asociado);

		$sql = "SELECT * FROM inscripcion WHERE ins_Part_Id = '$id' LIMIT 1";
		$rs  = mysqli_query($this->conecta, $sql);

		if ($rs && mysqli_num_rows($rs) > 0) {
			return mysqli_fetch_assoc($rs);
		}
		return false;
		}


		//Crear registro de inscripción a evento
		public function realizar_Inscripcion($id_Participante,$fecha_Incrib, $correo, $celular){

			$query_bs_acces = "INSERT INTO inscripcion(
				ins_Id, 
				ins_Part_Id, 
				ins_Fecha, 
				ins_Correo, 
				ins_Celular,
				ins_Notificado) VALUES (
				NULL,
				'$id_Participante',  
				'$fecha_Incrib', 
				'$correo', 
				'$celular',
				'2')";
			//var_dump($query_bs_acces);
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			$id = mysqli_insert_id($this->conecta);
			return $id;
		}

		//Crear registro de inscripción a evento con adjunto
		public function crear_Inscripcion($id_Asociado, $fecha, $correo, $celular, $rutaAdj){
		$id  = mysqli_real_escape_string($this->conecta, (string)$id_Asociado);
		$f   = mysqli_real_escape_string($this->conecta, (string)$fecha);
		$co  = mysqli_real_escape_string($this->conecta, (string)$correo);
		$ce  = mysqli_real_escape_string($this->conecta, (string)$celular);
		$ra  = is_null($rutaAdj) ? null : mysqli_real_escape_string($this->conecta, (string)$rutaAdj);

		$rutaSql = is_null($ra) ? "NULL" : "'$ra'";

		$sql = "INSERT INTO inscripcion(
					ins_Id,
					ins_Part_Id,
					ins_Fecha,
					ins_Correo,
					ins_Celular,
					ins_Notificado,
					ins_Ruta_Adj
				) VALUES (
					NULL,
					'$id',
					'$f',
					'$co',
					'$ce',
					2,
					$rutaSql
				)";

		$ok = mysqli_query($this->conecta, $sql);
		return $ok ? true : false;
		}


		public function mail_ConfirmacionInscripcion($correo, $documento, $fecha, $esDelegado = 0, $nAgencia = ''){

			require_once '../clases/PHPMailer/PHPMailerAutoload.php';

			// Cargar config sensible (NO versionar este archivo)
			// Contiene la información del servidor de correo saliente
			// Estructura esperada: return ['smtp_host'=>..., 'smtp_port'=>..., 'smtp_user'=>..., 'smtp_pass'=>...];
			$cfgPath = '/home/solucio1/cooptraiss_mail.php';

			/*if (!file_exists($cfgPath)) {
				// Si falta config, no intentamos enviar
				return false;
			}*/

			if (!is_readable($cfgPath)) {
			error_log("MAIL_CFG no readable: $cfgPath");
			return false;
			}


			$cfg = require $cfgPath;

			$remitente = $cfg['smtp_user'] ?? 'notificaciones@solucionescooptraiss.com';
			$empresa   = 'COOPTRAISS';
			$asunto    = 'Confirmación de inscripción / Delegados COOPTRAISS 2026-2030';

			$doc = htmlspecialchars((string)$documento, ENT_QUOTES, 'UTF-8');
			$fh  = htmlspecialchars((string)$fecha, ENT_QUOTES, 'UTF-8');

			$esDel = ((int)$esDelegado === 1);

			$ag = trim((string)$nAgencia);
			$agSafe = ($ag !== '')
				? htmlspecialchars($ag, ENT_QUOTES, 'UTF-8')
				: 'No disponible';

			$estadoHtml = $esDel
				? "<b>Registrado</b>"
				: "<b>Registrado</b> (pendiente de verificación)";

			$importanteHtml = $esDel
				? "<div style='padding:12px 14px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px;'>
					<b>Nota:</b> Este registro corresponde a un <b>delegado actual</b>.
				</div>"
				: "<div style='padding:12px 14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px;'>
					<b>Importante:</b> Tu registro será verificado por la <b>Comisión Central Electoral de Escrutinios</b>.
				</div>";



			$mensaje = "
			<div style='font-family: Arial, Helvetica, sans-serif; background:#f5f7f9; padding:24px;'>
				<div style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9ee;'>
					<div style='background:#08a750; color:#fff; padding:18px 22px;'>
					<h2 style='margin:0; font-size:18px;'>Inscripción confirmada</h2>
					<div style='opacity:.95; font-size:13px; margin-top:4px;'>Delegados COOPTRAISS 2026-2030</div>
					</div>

					<div style='padding:20px 22px; color:#1f2a37;'>
					<p style='margin:0 0 12px 0; font-size:14px;'>
						Apreciado(a) asociado(a),<br>
						Tu inscripción ha sido <b>registrada correctamente</b>.
					</p>

					<table style='width:100%; border-collapse:collapse; font-size:14px; margin:12px 0 18px 0;'>
						<tr>
							<td style='padding:8px 10px; background:#f3f4f6; width:38%; border:1px solid #e5e7eb;'><b>Documento</b></td>
							<td style='padding:8px 10px; border:1px solid #e5e7eb;'>$doc</td>
						</tr>
						<tr>
							<td style='padding:8px 10px; background:#f3f4f6; border:1px solid #e5e7eb;'><b>Punto de atención</b></td>
							<td style='padding:8px 10px; border:1px solid #e5e7eb;'>$agSafe</td>
						</tr>

						<tr>
							<td style='padding:8px 10px; background:#f3f4f6; border:1px solid #e5e7eb;'><b>Fecha y hora</b></td>
							<td style='padding:8px 10px; border:1px solid #e5e7eb;'>$fh</td>
						</tr>
						<tr>
							<td style='padding:8px 10px; background:#f3f4f6; border:1px solid #e5e7eb;'><b>Estado</b></td>
							<td style='padding:8px 10px; border:1px solid #e5e7eb;'>$estadoHtml</td>
						</tr>
					</table>

					<div style='padding:12px 14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px;'>
						<b>Importante:</b> Tu registro será verificado por la <b>Comisión Central Electoral de Escrutinios</b>.
						<p><b>Las listas de los delegados inscritos se publicarán el 9 de febrero de 2026</b>.</p>
					</div>

					<p style='margin:16px 0 0 0; font-size:13px; color:#374151;'>
						Si requieres soporte comunícate al correo <b>eventos@cooptraiss.com</b>.
					</p>

					<p style='margin:18px 0 0 0; font-size:13px; color:#6b7280;'>
						Cordialmente,<br>
						<b>COOPTRAISS</b>
					</p>
					</div>

					<div style='padding:12px 22px; background:#f9fafb; color:#6b7280; font-size:12px; border-top:1px solid #e6e9ee;'>
						Por favor no respondas este mensaje; se envía desde una cuenta de notificaciones automáticas.
					</div>
				</div>
			</div>
			";

			$altText = "Inscripción confirmada.\nDocumento: $documento\nFecha y hora: $fecha\n\nImportante: Su registro será verificado por la Comisión Central Electoral de Escrutinios.\n\nSoporte: eventos@cooptraiss.com\nCOOPTRAISS";

			try {
				$mail = new PHPMailer(true);
				$mail->CharSet  = 'UTF-8';
				$mail->isSMTP();
				$mail->SMTPAuth = true;
				$mail->SMTPSecure = 'tls';

				$mail->Host     = $cfg['smtp_host'] ?? 'mail.solucionescooptraiss.com';
				$mail->Port     = (int)($cfg['smtp_port'] ?? 587);
				$mail->Username = $cfg['smtp_user'] ?? $remitente;
				$mail->Password = $cfg['smtp_pass'] ?? '';

				if (trim($mail->Password) === '') {
					return false; // no enviamos si no hay pass
				}

				$mail->setFrom($mail->Username, $empresa);

				$mail->addAddress($correo);

				$mail->Subject = $asunto;
				$mail->isHTML(true);
				$mail->Body    = $mensaje;
				$mail->AltBody = $altText;

				// Producción: debug apagado
				$mail->SMTPDebug  = 0;
				$mail->Debugoutput = 'error_log';

				$sent = $mail->send();
				if (!$sent) {
				error_log("MAIL_SEND_FAIL to=$correo err=" . $mail->ErrorInfo);
				}
				return $sent ? true : false;

				//return $mail->send() ? true : false;

			} catch (Exception $e) {
				error_log("MAIL_EXCEPTION to=$correo ex=" . $e->getMessage());
				return false;
			}
		}
	}
	

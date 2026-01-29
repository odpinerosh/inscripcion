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


		public function mail_ConfirmacionInscripcion($correo, $documento, $fecha){
			require_once '../clases/PHPMailer/PHPMailerAutoload.php';

			$remitente = 'notificaciones@solucionescooptraiss.com';
			$empresa   = 'COOPTRAISS';
			$asunto    = 'Confirmación de inscripción/Delegados Cooptraiss 2026-2030';

			$mensaje = "
				<div style='font-family: Arial, sans-serif; font-size: 14px; color:#111'>
				<p>Apreciado(a) asociado(a),</p>
				<p>Su inscripción ha sido registrada correctamente.</p>
				<p>
					<b>Documento:</b> ".htmlspecialchars((string)$documento)."<br>
					<b>Fecha:</b> ".htmlspecialchars((string)$fecha)."
				</p>
				<p style='margin-top:18px'>
					Por favor no responda este mensaje. Si requiere soporte, comuníquese al correo
					<b>eventos@cooptraiss.com</b>.
				</p>
				</div>
			";

			try {
				$mail = new PHPMailer(true);
				$mail->isSMTP();
				$mail->SMTPAuth   = true;
				$mail->SMTPSecure = 'tls';
				$mail->Host       = 'mail.solucionescooptraiss.com';
				$mail->Port       = 587;
				$mail->Username   = "notificaciones@solucionescooptraiss.com";
				$mail->Password   = "C00p_2021*";

				$mail->setFrom($remitente, $empresa);
				$mail->addAddress($correo);

				$mail->Subject = utf8_decode($asunto);
				$mail->Body    = utf8_decode($mensaje);
				$mail->IsHTML(true);
				// Log configuration details
				error_log("MAIL_CFG host={$mail->Host} port={$mail->Port} user={$mail->Username} from={$remitente} to={$correo}");
				$mail->SMTPDebug = 2;
				$mail->Debugoutput = 'error_log';

				return $mail->send() ? true : false;
			} catch (Exception $e) {
				return false;
			}
		}


	}

?>
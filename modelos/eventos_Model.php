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
			$query_bs_acces = "SELECT * FROM inscripciones_view WHERE aso_Id = '$id_Asociado' AND even_Id = '$id_Evento'";	
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

		public function mail_Inscripcion($nombre_C, $documento_C, $correo_C, $telefono_C){
	    //EMAIL INFORMANDO AL PROFESOR SU REGISTRO EN EL SISTEMA
			require_once '../clases/PHPMailer/PHPMailerAutoload.php';
			//$ruta = '../manual/manual.pdf';
			
			$remitente = 'notificaciones@solucionescooptraiss.com';
			$empresa = 'COOPTRAISS';
			$asunto = 'Inscripción exitosa';
			$mensaje = '<b>Felicitaciones</b> tu inscripción al evento <b>ENCUENTRO EDUCATIVO VIRTUAL 1</b> ha sido exitosa.</br>
			El viernes 18 de junio/2021, recibirás en el correo registrado el Link de ingreso. <b>El Link es personal</b> ya que contiene un código de registro.</br>
			Te esperamos  el 19 de junio/2021 a las 8.00 am, contamos con tu puntual asistencia</br>
			Recuerda descargar el material adjunto, en caso de no visualizarlo al instante, búscalo en la carpeta de Descargas.</br> 
				<ul>
                  <li style="font-weight: bold">
                    <a href="https://solucionescooptraiss.com/inscripciones/material/01_AYUDAS ENCUENTRO VIRTUAL 1  COOPTRAISS.doc" target="_blank"><b>DOCUMENTO 01<b></a>
                  </li><br>
                  <li style="font-weight: bold">
                    <a href="https://solucionescooptraiss.com/inscripciones/material/02_PROTOCOLO ENCUENTRO VIRTUAL 1 COOPTRAISS.docx" target="_blank"><b>DOCUMENTO 02<b></a>
                  </li><br>
                </ul>
			';
			/*
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = 'smtp.office365.com';
			$mail->Port = 587;
			$mail->SMTPSecure = 'tls';
			$mail->SMTPAuth = true;
			$mail->Username = "soporteweb@cooptraiss.com"; 
			$mail->Password = "coop123*";
			*/
			
			
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = 'tls'; //Modificar
			$mail->Host = 'mail.solucionescooptraiss.com'; //Modificar
			$mail->Port = 587; //Modificar
			$mail->Username = "notificaciones@solucionescooptraiss.com"; 
			$mail->Password = "C00p_2021*";
			
			
			//$mail->addAttachment($ruta);//adjunto
			
			$mail->setFrom($remitente, $empresa); //Modificar
			$mail->addAddress($correo_C);
			
			$mail->Subject = utf8_decode($asunto);
			$mail->Body    = utf8_decode($mensaje);
			$mail->IsHTML(true);
			if($mail->send()){
				//$this->mail_Copia_Administrador($mensaje, $nombre_C, $documento_C, $correo_C, $asunto, $telefono_C);
				//return true;
			}else{
				//return false;
			}
	    }

	    public function mail_Copia_Administrador($mensaje_Copia, $nombre_C, $documento_C, $correo_Usuario, $asunto_Copia, $telefono_C){
	    //COPIA DE CORREOS ENVIADA AL ADMINISTRADOR DEL SISTEMA
	    	require_once '../clases/PHPMailer/PHPMailerAutoload.php';
	    	$remitente = 'notificaciones@solucionescooptraiss.com';
			$empresa = 'COOPTRAISS';
			$asunto = 'Copia - '.$asunto_Copia.' - '.$documento_C;
			$mensaje = 'Cordial saludo: <br /><br />solucionescooptraiss.com informa que se ha enviado un correo al asociado: "'.$documento_C.'", nombre: "'.$nombre_C.'", celular: "'.$telefono_C.'", correo:"'.$correo_Usuario.'", asunto: "'.$asunto_Copia.'", con el siguiente mensaje:<br /><br />'.$mensaje_Copia;
			$administrador = 'yerson.paez@cooptraiss.com';


			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = 'tls'; //Modificar
			$mail->Host = 'mail.solucionescooptraiss.com'; //Modificar
			$mail->Port = 587; //Modificar
			$mail->Username = "notificaciones@solucionescooptraiss.com"; 
			$mail->Password = "C00p_2021*";
			
			$mail->setFrom($remitente, $empresa); //Modificar
			$mail->addAddress($administrador);
			//$mail->addCC('solidaridad@cooptraiss.com');
			
			$mail->Subject = utf8_decode($asunto);
			$mail->Body    = utf8_decode($mensaje);
			$mail->IsHTML(true);
			if($mail->send()){
				//echo "copia enviada";
			}else{
				//echo "error de copia enviada";
			}
	    }

	}

?>
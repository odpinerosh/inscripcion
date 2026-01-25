<?php 
	require_once("../config/conecta.php");
	require_once("../config/session.php");
	require_once("../modelos/eventos_Model.php");
	require_once("../modelos/asociados_Model.php");
	date_default_timezone_set("America/Bogota");
	//Llamada al modelo
	//require_once("../modelos/citas_Model.php");

	$accion = $_GET['accion'];

	switch ($accion) {

		case '1'://Realizar inscripción
			$id_Evento = $_GET['id_Evento'];
			$id_Asociado  = strip_tags($_POST['identificacion']);
			$correo = strip_tags($_POST['correo']);
			$celular = strip_tags($_POST['celular']);
			$nombre = strip_tags($_POST['nombre']);
			$participante = strip_tags($_POST['participante']);

			$fecha = date("Y-m-d H:i:s");
			/*
			$prueba = array($id_Asociado, $id_Agencia, $correo,	$celular, $fecha, $id_Evento);
			echo "<pre>";
				var_dump($prueba);
			echo "<pre>";
			*/
			
			$eventos = new Eventos();
			$validar_Inscripcion = $eventos->validar_Inscripcion($id_Asociado, $id_Evento);
			if (!$validar_Inscripcion ) {

				$realizar_Inscripcion = $eventos->realizar_Inscripcion($participante, $fecha, $correo, $celular);
				//Llamada a la vista
				if ($realizar_Inscripcion) {
					//generar envío de correos
					$eventos->mail_Inscripcion($nombre, $id_Asociado, $correo, $celular);
					//mostrar mensaje de confirmación en pantalla
					echo"
						<div class='alert alert-primary' role='alert'>
		                    Su inscripción ha sido exitosa. En el transcurso del día recibirá un correo electrónico en cual se le indicará cómo ingresar al evento.
		                </div>";
				}else{
					echo"
					<div class='alert alert-warning' role='alert'>
	                    No fué posible realizar la inscripción, por favor intente nuevamente.
	                </div>";
				}

			}else{
				//mensaje informando que ya está inscrito
				$inscripcion = $validar_Inscripcion ['ins_Fecha'];
				echo"
				<div class='alert alert-primary' role='alert'>
                    El documento ".$id_Asociado." ya se encuentra inscrito al evento. Registrado en ".$inscripcion."
                </div>";
			}
		break;

		case '3'://Generar Reporte
			$usuario = $_GET['usuario'];
			$password = $_GET['password'];
			//echo "la fecha inicial es ".$fecha_Inicial." y la fecha final es ".$fecha_Final;
			if ($usuario==$admin && $password==$pass) {
				$eventos = New Eventos();
				$inscritos = $eventos->consultar_Inscripciones();

				$delimiter = "|";
			    $filename = "inscripciones" . date('Y-m-d') . ".csv";
			    
			    //create a file pointer
			    $f = fopen('php://memory', 'w');
			    
			    //set column headers
			    $fields = array('id_Inscrip', 'Evento', 'Fecha y Hora', 'id_Asociado', 'nombre', 'correo', 'celular', 'agencia');
			    fputcsv($f, $fields, $delimiter);

				if (!$inscritos) {
					$lineData = array('', '', '', '', '', '', '');
				        fputcsv($f, $lineData, $delimiter);
				}else{
					//var_dump($inscritos);
				    
				    //output each row of the data, format line as csv and write to file pointer
				    while($row = $inscritos->fetch_assoc()){
				        //$status = ($row['status'] == '1')?'Active':'Inactive';
				        $lineData = array($row['ins_Id'], $row['even_Nombre'], $row['ins_Fecha'], $row['aso_Id'], $row['aso_Nombre'], $row['ins_Correo'], $row['ins_Celular'], $row['agen_Nombre']);
				        fputcsv($f, $lineData, $delimiter);
				    }		
				}

				//move back to beginning of file
			    fseek($f, 0);
			    
			    //set headers to download file rather than displayed
			    header('Content-Type: text/csv');
			    header('Content-Disposition: attachment; filename="' . $filename . '";');
			    
			    //output all remaining data on a file pointer
			    fpassthru($f);
			}else{
				
			    echo"
	                <script language='JavaScript'>
	                	alert('Acceso no autorizado. Verifique los datos ingresados.')
					    history.back();
					 </script>
	                ";
			}
		break;

		default:
		break;
	}

?>
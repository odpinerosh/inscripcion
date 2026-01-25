<?php 
	//conexión a base de datos
	require_once("../config/conecta.php");
	//Llamada al modelo
	require_once("../modelos/asociados_Model.php");
	require_once("../modelos/eventos_Model.php");

	$accion = $_GET['accion'];

	switch ($accion) {
		case '1'://Consultar asociado
			$id_Asociado = $_GET['id_Asociado'];
			$id_Evento = $_GET['id_Evento'];
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
	                            <input type='text' name='celular' id='celular' class='form-control' placeholder='Ingrese el número' onKeyDown='numeros()' maxlength='10' value='".$consultar_Asociado['aso_Celular']."' readonly></input>
	                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-phone'></i></span>
	                        </span>
	                    </div><br>
	                    <div id='div_Participante' class='form-group' style='display: none'>
	                        <h5>Participante Id</h5>
	                        <span class='input-group-prepend'>
	                            <input type='text' name='participante' id='participante' class='form-control' placeholder='Ingrese el número' onKeyDown='numeros()' maxlength='10' value='".$consultar_Asociado['part_Id']."' readonly></input>
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

		default:
			echo "Problemas de comunicación, intente nuevamente";
		break;
	}
?>
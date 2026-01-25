<?php 

    class Conectar{
	    public static function conexion(){
		    $servername = "localhost";
		    $database = "solucio1_inscripciones";
		    $username = "solucio1_eventos";
		    $password = "Event_2021*";
			$conecta = new mysqli($servername, $username, $password, $database);
			
			if($conecta) {
				//echo "CORRECTO.<br />";
				$conecta->set_charset("utf8");
			}else{
			     echo "Conexión no se pudo establecer.<br />";
			     die( print_r( sqlsrv_errors(), true));
			}
	        return $conecta;
	    }
	}
	$admin = 'admin_Encuentro';
	$pass = 'Enc_2021*';
?>
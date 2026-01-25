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

   }

?>
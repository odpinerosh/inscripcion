

function mostrar_Agencias(){

	var urldat="controladores/asociados_Controller.php?accion=2";
    var page = $(this).attr('data');        
    var dataString = 'page='+page;

    $.ajax({
        type: "GET",
        url:  urldat,
        data: dataString,
        success: function(data) {
            $('#div_Agencias').html(data);
        }
    }); 
}

function enviar_Documento(){
    var evento = $('#evento').val()
    var documento = $('#documento').val()
    var urldat="controladores/asociados_Controller.php?accion=1&id_Asociado="+documento+"&id_Evento="+evento;
    var page = $(this).attr('data');        
    var dataString = 'page='+page;

    //alert(documento);
    
    $.ajax({
        type: "GET",
        url:  urldat,
        data: dataString,
        success: function(data) {
            $('#div_Agencias').show();
            $('#div_Datos').empty();
            $('#div_Alertas').empty();
            $('#div_Datos').html(data);
        }
    }); 

}

function validar_Acceso(){
    //alert('prueba');
    
    var usuario = $('#usuario').val();
    var password = $('#password').val();
    var urldat="../controladores/eventos_Controller.php?accion=3&usuario="+usuario+"&password="+password;

    $(location).attr('href',urldat);
}

function validar_Formulario(){
    var nombre = $('#nombre').val();
    var correo = $('#correo').val();
    var correo_Confirma = $('#correo_Confirma').val();
    var identificacion = $('#identificacion').val();
    var agencia = $('#agencias').val();
    var celular = $('#celular').val();
    var val_Correo = validar_Correo(correo);
    var val_Celular = validar_Celular(celular);

    var errores = [];


    if (nombre =='') {
        errores.push("El campo nombre está vacío");
    }
    if (identificacion =='') {
        errores.push("El campo identificación está vacío");
    }
    if (agencia =='0') {
        errores.push(" Seleccione una agencia");
    }
    if (!val_Correo) {
        errores.push(" Ingrese un correo válido");
    }
    if (correo_Confirma!=correo) {
        errores.push("La confirmación de correo no coincide");
    }
    /*
    if (!val_Celular) {
        errores.push(" El celular debe tener 10 números.");
    }
    */
    
    
    if (errores.length>0) {
        $('#errores').empty();
        $('#errores').show();
        $('#errores').html('<h5>Debes corregir:</h5>'+errores);
    }else{
        $('#errores').empty();
        $('#errores').hide();
        realizar_Inscripcion();
    }
    
}

function realizar_Inscripcion(){
    
    var evento = $('#evento').val()
    $('#div_Loading').show(1000);
    $('html, body').animate({
        scrollTop: $("#div_Loading").offset().top
    }, 1000);
    $('#div_Validar').hide();
    $('#div_Enviar').hide();
    var urldat="controladores/eventos_Controller.php?accion=1&id_Evento="+evento;
    var page = $(this).attr('data');        
    var dataString = 'page='+page;

    $.ajax({
        type: "POST",
        url:  urldat,
        data: $("#inscripcion").serialize(),
        success: function(data) {
            $('#div_Loading').hide();
            $('#div_Validar').show();
            $('#div_Enviar').show();
            $('#div_Alertas').html(data);
        }
    }); 
}


function numeros(){
    if(event.shiftKey) {
        event.preventDefault();
    }

    if (event.keyCode == 46 || event.keyCode == 8) {
    }else {
        if (event.keyCode < 95) {
          if (event.keyCode < 48 || event.keyCode > 57) {
                event.preventDefault();
            }
        }else {
            if (event.keyCode < 96 || event.keyCode > 105) {
                event.preventDefault();
            }
        }
    }      
}

function validar_Documento(){
    var documento = $('#documento').val()
    if (documento == '') {
        alert('El número de identificación no puede estar vacío.')
    }else{
        enviar_Documento();
    }
    
}

function validar_Correo(correo){

    var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
    if (!filter.test(correo)) {
        return false;
    } else {
       return true;
    }

}
function validar_Celular(celular){

    if (celular.length!=10 || isNaN(celular) || celular=='') {
        return false;
    }else {
       return true;
    }

}

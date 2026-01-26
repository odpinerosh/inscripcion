

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


function numeros(e){
  // Teclas permitidas
  const allow = [
    "Enter","Tab","Backspace","Delete",
    "ArrowLeft","ArrowRight","ArrowUp","ArrowDown",
    "Home","End"
  ];
  if (allow.includes(e.key)) return;

  // Permitir Ctrl/Cmd + (C,V,X,A)
  if (e.ctrlKey || e.metaKey) return;

  // Solo dígitos
  if (!/^\d$/.test(e.key)) {
    e.preventDefault();
  }
}



function validar_Documento(){
    // Si el login OTP está presente, este botón debe disparar el paso 1 del OTP
    if ($('#correo_login').length && $('#codigo_login').length && $('#div_CorreoLogin').length){
        paso_Documento_Otp();
        return;
    }

    // Fallback (flujo antiguo): entra directo a la inscripción
    var documento = $('#documento').val();
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


/* =======================================================================
   LOGIN POR CÓDIGO (OTP) - Cédula -> Correo -> Código 6 dígitos (TTL)
   Requiere que en el HTML existan:
   #evento (hidden o select), #documento, #correo_login, #codigo_login,
   #div_CorreoLogin, #div_CodigoLogin, #div_LoginAlert, #div_LoginOtp
======================================================================= */

function init_LoginOtp(){
    // Enter en cédula => solicitar correo
    $('#documento').on('keypress', function(e){
        if (e.which === 13) { e.preventDefault(); paso_Documento_Otp(); }
    });

    // Enter en correo => solicitar código
    $('#correo_login').on('keypress', function(e){
        if (e.which === 13) { e.preventDefault(); solicitar_Codigo_Otp(); }
    });

    // Enter en código => verificar
    $('#codigo_login').on('keypress', function(e){
        if (e.which === 13) { e.preventDefault(); verificar_Codigo_Otp(); }
    });
}

function ui_LoginAlert(tipo, mensaje){
    var cls = (tipo === 'ok') ? 'alert alert-success' : 'alert alert-danger';
    $('#div_LoginAlert').html("<div class='"+cls+"' role='alert'>"+mensaje+"</div>");
}

function paso_Documento_Otp(){
    var evento = $('#evento').val();
    var documento = ($('#documento').val() || '').trim();

    if (documento === '') {
        ui_LoginAlert('err', 'La cédula no puede estar vacía.');
        return;
    }

    // Valida que exista para este evento y devuelve un "hint" del correo
    $.ajax({
        type: "GET",
        url: "controladores/asociados_Controller.php",
        data: { accion: 8, id_Asociado: documento, id_Evento: evento },
        dataType: "json",
        success: function(resp){
            if (!resp || !resp.ok){
                ui_LoginAlert('err', (resp && resp.message) ? resp.message : 'No fue posible validar la cédula.');
                return;
            }
            $('#div_CorreoLogin').show();
            $('#correo_login').focus();
            if (resp.correoHint){
                ui_LoginAlert('ok', "Ahora escribe tu correo (pista: "+resp.correoHint+").");
            }else{
                ui_LoginAlert('ok', "Ahora escribe tu correo electrónico.");
            }
        },
        error: function(){
            ui_LoginAlert('err', 'Error de comunicación. Intenta nuevamente.');
        }
    });
}

function solicitar_Codigo_Otp(){
    var evento = $('#evento').val();
    var documento = ($('#documento').val() || '').trim();
    var correo = ($('#correo_login').val() || '').trim();

    if (documento === '') { ui_LoginAlert('err', 'La cédula no puede estar vacía.'); return; }
    if (!validar_Correo(correo)) { ui_LoginAlert('err', 'Ingresa un correo válido.'); return; }

    $('#div_CodigoLogin').hide();
    ui_LoginAlert('ok', 'Validando correo y enviando código...');

    $.ajax({
        type: "POST",
        url: "controladores/asociados_Controller.php?accion=9",
        data: { id_Asociado: documento, id_Evento: evento, correo: correo },
        dataType: "json",
        success: function(resp){
            if (!resp || !resp.ok){
                ui_LoginAlert('err', (resp && resp.message) ? resp.message : 'No fue posible enviar el código.');
                return;
            }
            $('#div_CodigoLogin').show();
            $('#codigo_login').val('').focus();
            ui_LoginAlert('ok', "Listo. Te enviamos un código de 6 dígitos. Vigencia: "+resp.ttlMin+" minutos.");
        },
        error: function(){
            ui_LoginAlert('err', 'Error de comunicación. Intenta nuevamente.');
        }
    });
}

function verificar_Codigo_Otp(){
    var evento = $('#evento').val();
    var documento = ($('#documento').val() || '').trim();
    var codigo = ($('#codigo_login').val() || '').trim();

    if (!/^\d{6}$/.test(codigo)){
        ui_LoginAlert('err', 'El código debe tener 6 dígitos.');
        return;
    }

    $.ajax({
        type: "POST",
        url: "controladores/asociados_Controller.php?accion=10",
        data: { id_Asociado: documento, id_Evento: evento, codigo: codigo },
        dataType: "json",
        success: function(resp){
            if (!resp || !resp.ok){
                ui_LoginAlert('err', (resp && resp.message) ? resp.message : 'Código inválido.');
                return;
            }

            // Éxito: oculta el login y carga el flujo actual (inscripción)
            ui_LoginAlert('ok', 'Acceso exitoso. Cargando...');
            if ($('#div_LoginOtp').length){ $('#div_LoginOtp').hide(); }

            // Reutiliza el flujo existente: trae datos y formulario
            enviar_Documento();
        },
        error: function(){
            ui_LoginAlert('err', 'Error de comunicación. Intenta nuevamente.');
        }
    });
}

// Inicializa automáticamente si el HTML tiene los campos del login
$(document).ready(function(){
    if ($('#documento').length && $('#correo_login').length && $('#codigo_login').length){
        init_LoginOtp();
    }
});

$(document).on('click', '#btnContinuarOtp', function(e){
  e.preventDefault();
  paso_Documento_Otp(); // inicia OTP
});


// ===== Parche: handlers delegados para evitar que "Enter" se pierda =====
$(document).on("keydown", "#correo_login", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    solicitar_Codigo_Otp(); 
  }
});

$(document).on("keydown", "#codigo_login", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    verificar_Codigo_Otp(); 
  }
});

// (Opcional) click explícito para enviar OTP (paso 2)
$(document).on("click", "#btnEnviarOtp", function(e){
  e.preventDefault();
  solicitar_Codigo_Otp();
});

// Click para enviar OTP (paso 2)
$(document).on("click", "#btnEnviarOtp", function(e){
  e.preventDefault();
  solicitar_Codigo_Otp();  
});

// Click para verificar OTP (paso 3)
$(document).on("click", "#btnValidarOtp", function(e){
  e.preventDefault();
  verificar_Codigo_Otp();   
});


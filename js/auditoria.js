$(document).on('submit', '#inscripcion', function(e){
  e.preventDefault();
  return false;
});


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
            $('#div_Datos').empty();
            $('#div_Alertas').empty();
            $('#div_Datos').html(data);
            toggleBtnInscribir();
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

async function validar_Formulario(){
  var esDelegado = ($('#es_delegado').val() || '0') === '1';
  var requiereCert = ($('#requiere_cert').val() || '0') === '1';

  var errores = [];

  if (!esDelegado){
    if (!$('#pdf_cedula').length || $('#pdf_cedula')[0].files.length === 0){
      errores.push("Debes adjuntar la fotocopia de la cédula (PDF).");
    }
    if (requiereCert){
      if (!$('#pdf_certificado').length || $('#pdf_certificado')[0].files.length === 0){
        errores.push("Debes adjuntar el certificado de cooperativismo (PDF).");
      }
    }
  }

  if (errores.length){
    await Swal.fire({
      icon: 'error',
      title: 'Debes corregir',
      html: errores.map(e => `• ${e}`).join('<br>')
    });
    return;
  }

  const r = await Swal.fire({
    icon: 'question',
    title: 'Confirmar inscripción',
    text: '¿Está seguro de confirmar su inscripción?',
    showCancelButton: true,
    confirmButtonText: 'Sí, confirmar',
    cancelButtonText: 'No, cancelar'
  });

  if (!r.isConfirmed) return;

  // bloquear botón para evitar doble envío
  $('#enviar').prop('disabled', true);

  realizar_Inscripcion();
}




function cancelar_Subida(){
  if ($('#pdf_cedula').length) $('#pdf_cedula').val('');
  if ($('#pdf_certificado').length) $('#pdf_certificado').val('');

  // deshabilita si no delegado
  var esDelegado = parseInt($('#es_delegado').val() || '0', 10) === 1;
  if (!esDelegado) $('#enviar').prop('disabled', true);

  // opcional: ocultar mensajes de error 
  $('#errores').hide().html('<h5>Debes corregir:</h5>');
}


function realizar_Inscripcion(){
  var evento = $('#evento').val();
  $('#div_Loading').show(300);
  $('html, body').animate({ scrollTop: $("#div_Loading").offset().top }, 500);
  $('#div_Validar').hide();
  $('#div_Enviar').hide();

  
  var urldat = "controladores/eventos_Controller.php?accion=1&id_Evento=" + encodeURIComponent(evento);

  var fd = new FormData(document.getElementById('inscripcion'));

  $.ajax({
    type: "POST",
    url: urldat,
    data: fd,
    processData: false,
    contentType: false,
    dataType: "json",   //ahora esperamos JSON
    success: async function(resp){

      $('#div_Loading').hide();
      $('#div_Validar').show();
      $('#div_Enviar').show();

      // resp = {ok, msg, code?}
      if (resp && resp.ok){
        await Swal.fire({
          icon: 'success',
          title: 'Inscripción realizada',
          text: resp.msg || 'Su inscripción fue realizada con éxito',
          confirmButtonText: 'Aceptar'
        });
        window.location.reload();
        return;
      }

      // YA_INSCRITO => info
      if (resp && resp.code === 'YA_INSCRITO'){
        await Swal.fire({
          icon: 'info',
          title: 'Ya inscrito',
          text: resp.msg || 'Ya estás inscrito en este evento.',
          confirmButtonText: 'Aceptar'
        });
        window.location.reload();
        return;
      }

      // error normal
      await Swal.fire({
        icon: 'error',
        title: 'No se pudo completar',
        text: (resp && resp.msg) ? resp.msg : 'Error al procesar la inscripción'
      });
      //Habilitar botón
      $('#enviar').prop('disabled', false);
    },
    error: async function(xhr){
      $('#div_Loading').hide();
      $('#div_Validar').show();
      $('#div_Enviar').show();

      await Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error procesando la inscripción. Intenta nuevamente.'
      });
      //Habilitar botón
      $('#enviar').prop('disabled', false);
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
// ===== Cancelar OTP (aborta requests y resetea UI) =====
var xhrOtpPasoDoc = null;
var xhrOtpEnviar = null;
var xhrOtpVerificar = null;

function cancelar_Otp(){
  // abortar si hay requests en curso
  try { if (xhrOtpPasoDoc) xhrOtpPasoDoc.abort(); } catch(e){}
  try { if (xhrOtpEnviar) xhrOtpEnviar.abort(); } catch(e){}
  try { if (xhrOtpVerificar) xhrOtpVerificar.abort(); } catch(e){}
  xhrOtpPasoDoc = xhrOtpEnviar = xhrOtpVerificar = null;

  // limpiar también documento
  $('#documento').val('');
  $('#id_Asociado').val('');  
  $('#id_Evento').val('');    

  // reset UI
  $('#correo_login').val('');
  $('#codigo_login').val('');
  $('#div_CorreoLogin').hide();
  $('#div_CodigoLogin').hide();
  $('#btnEnviarOtp').prop('disabled', false);
  $('#btnReenviarOtp').prop('disabled', false);
  $('#otpCooldownMsg').text('');

  $('#div_LoginAlert').hide();
  $('#div_LoginAlert').removeClass('alert-success alert-danger alert-warning alert-info');
  $('#div_LoginAlert').html('');

  //ui_LoginAlert('ok', 'Proceso cancelado.');
  $('#documento').focus();
}


// clicks cancelar
$(document).on('click', '#btnCancelarOtp, #btnCancelarOtp2', function(e){
  e.preventDefault();
  cancelar_Otp();
});


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

  xhrOtpPasoDoc = $.ajax({
    type: "GET",
    url: "controladores/asociados_Controller.php",
    data: { accion: 8, id_Asociado: documento, id_Evento: evento },
    dataType: "json",
    success: function(resp){
      if (!resp || !resp.ok){
        ui_LoginAlert('err', (resp && (resp.message || resp.msg)) ? (resp.message || resp.msg) : 'No fue posible validar la cédula.');
        return;
      }

      // Asegura que estás en Paso 2 (correo)
      $('#div_CodigoLogin').hide();          // si ya estaba visible por un intento previo
      $('#codigo_login').val('');              // limpia código previo
      $('#div_CorreoLogin').show();

      // Habilita el botón Enviar código al entrar al Paso 2
      $('#btnEnviarOtp').prop('disabled', false);

      $('#correo_login').focus();

      if (resp.correoHint){
        ui_LoginAlert('ok', "Ahora escribe tu correo (pista: "+resp.correoHint+").");
      }else{
        ui_LoginAlert('ok', "Ahora escribe tu correo electrónico.");
      }
    },
    error: function(xhr){
      mostrarErrorAjax(xhr, 'Error de comunicación. Intenta nuevamente.');
    }
  });
}


function solicitar_Codigo_Otp(){
  var evento = ($('#evento').val() || '').trim();
  var documento = ($('#documento').val() || '').trim();
  var correo = ($('#correo_login').val() || '').trim();

  if (documento === '') { ui_LoginAlert('err', 'La cédula no puede estar vacía.'); return; }
  if (evento === '') { ui_LoginAlert('err', 'No se encontró el evento.'); return; }
  if (!validar_Correo(correo)) { ui_LoginAlert('err', 'Ingresa un correo válido.'); return; }

  $('#div_CodigoLogin').hide();
  ui_LoginAlert('ok', 'Validando correo y enviando código...');

  // Evita doble clic antes de que el servidor responda
  $('#btnEnviarOtp').prop('disabled', true);

  xhrOtpEnviar = $.ajax({
    type: "POST",
    url: "controladores/asociados_Controller.php?accion=9",
    dataType: "json",
    data: { id_Asociado: documento, id_Evento: evento, correo: correo },
    success: function(resp){
      if(resp && resp.ok){
        $('#div_CorreoLogin').hide(); 
        $('#div_CodigoLogin').show();
        $('#codigo_login').val('').focus();

        var ttl = (resp.ttlMin != null) ? resp.ttlMin : 10;
        ui_LoginAlert('ok', (resp.message || resp.msg || 'Te enviamos un código de 6 dígitos.') + ' Vigencia: ' + ttl + ' minutos.');

        iniciarCooldownOtp(60); // deshabilita Enviar + Reenviar por 60s
      } else {
        // Falló validación (correo no coincide, etc.)
        $('#btnEnviarOtp').prop('disabled', false);
        ui_LoginAlert('err', (resp && (resp.message || resp.msg)) ? (resp.message || resp.msg) : "No fue posible enviar el código.");
      }
    },
    error: function(xhr){
      $('#btnEnviarOtp').prop('disabled', false);
      mostrarErrorAjax(xhr, "Error enviando el código. Intenta nuevamente.");
    }
  });
}


function verificar_Codigo_Otp(){
  var evento = ($('#evento').val() || '').trim();
  var documento = ($('#documento').val() || '').trim();
  var codigo = ($('#codigo_login').val() || '').trim();

  if (!/^\d{6}$/.test(codigo)){
    ui_LoginAlert('err', 'El código debe tener 6 dígitos.');
    return;
  }

  $.ajax({
    type: "POST",
    url: "controladores/asociados_Controller.php?accion=10",
    dataType: "json",
    data: { id_Asociado: documento, id_Evento: evento, codigo: codigo },
    success: function(resp){
      if (!resp || !resp.ok){
        ui_LoginAlert('err', (resp && (resp.message || resp.msg)) ? (resp.message || resp.msg) : 'Código inválido.');
        return;
      }

      ui_LoginAlert('ok', 'Acceso exitoso. Cargando...');
      if ($('#div_LoginOtp').length){ $('#div_LoginOtp').hide(); }
      enviar_Documento();
    },
    error: function(xhr){
      if (!(xhr && xhr.status === 429)) {
        $('#btnEnviarOtp').prop('disabled', false);
      }
      mostrarErrorAjax(xhr, "Error enviando el código. Intenta nuevamente.");
    }

  });
}

// ===== Cooldown OTP (deshabilita Enviar y Reenviar) =====
var otpCooldownUntil = 0;

function iniciarCooldownOtp(segundos){
  otpCooldownUntil = Date.now() + (segundos * 1000);

  // Deshabilita ambos
  $("#btnEnviarOtp").prop("disabled", true);
  $("#btnReenviarOtp").prop("disabled", true);

  tickCooldownOtp();
}

function tickCooldownOtp(){
  var leftMs = otpCooldownUntil - Date.now();
  if (leftMs <= 0){
    $("#btnEnviarOtp").prop("disabled", false);
    $("#btnReenviarOtp").prop("disabled", false);
    $("#otpCooldownMsg").text("");
    return;
  }
  var left = Math.ceil(leftMs / 1000);
  $("#otpCooldownMsg").text("Puedes reenviar en " + left + "s.");
  setTimeout(tickCooldownOtp, 400);
}

// Click: reenviar = reutiliza el envío normal
$(document).on("click", "#btnReenviarOtp", function(e){
  e.preventDefault();
  solicitar_Codigo_Otp();
});

// ===== Manejo de errores AJAX =====

function mostrarErrorAjax(xhr, fallback) {
  // 0) Cooldown OTP (429)
  if (xhr && xhr.status === 429) {
    let wait = null;
    let m = null;

    if (xhr.responseJSON) {
      m = xhr.responseJSON.message || xhr.responseJSON.msg;
      wait = xhr.responseJSON.wait;
    } else {
      try {
        const obj = JSON.parse(xhr.responseText || "{}");
        m = obj.message || obj.msg;
        wait = obj.wait;
      } catch (e) {}
    }

    if (m) ui_LoginAlert('err', m);
    else ui_LoginAlert('err', "Espera unos segundos para reenviar el código.");

    // si el backend manda "wait", úsalo; si no, asume 60
    if (typeof iniciarCooldownOtp === 'function') {
      const sec = (wait != null) ? parseInt(wait, 10) : 60;
      if (!isNaN(sec) && sec > 0) iniciarCooldownOtp(sec);
    }

    return;
  }

  // 1) Si jQuery ya parseó JSON
  if (xhr && xhr.responseJSON) {
    const m = xhr.responseJSON.message || xhr.responseJSON.msg;
    if (m) return ui_LoginAlert('err', m);
  }

  // 2) Intentar parsear el texto como JSON
  try {
    const obj = JSON.parse(xhr.responseText || "{}");
    const m = obj.message || obj.msg;
    if (m) return ui_LoginAlert('err', m);
  } catch (e) {}

  // 3) Fallback
  ui_LoginAlert('err', fallback || "Error de comunicación. Intenta nuevamente.");
}



// Inicializa automáticamente si el HTML tiene los campos del login
$(document).ready(function(){
    if ($('#documento').length && $('#correo_login').length && $('#codigo_login').length){
        init_LoginOtp();
    }
    limpiarLoginOtp();
});

$(document).on('click', '#btnContinuarOtp', function(e){
  e.preventDefault();
  paso_Documento_Otp(); // inicia OTP
});

function limpiarLoginOtp(){
  $('#documento').val('');
  $('#correo_login').val('');
  $('#codigo_login').val('');
}

window.addEventListener('pageshow', function () {
  // pageshow se dispara también cuando vuelves con el botón "atrás"
  limpiarLoginOtp();
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

function reiniciar_Inscripcion(){
  window.location.reload();
}

function toggleBtnInscribir(){
  var esDelegado = ($('#es_delegado').val() || '0') === '1';
  if (esDelegado){
    $('#enviar').prop('disabled', false);
    return;
  }

  var requiereCert = ($('#requiere_cert').val() || '0') === '1';

  var okCed = ($('#pdf_cedula').length && $('#pdf_cedula')[0].files.length > 0);
  var okCert = (!requiereCert) || ($('#pdf_certificado').length && $('#pdf_certificado')[0].files.length > 0);

  $('#enviar').prop('disabled', !(okCed && okCert));
}



// Re-evaluar cada vez que cambie un requisito
$(document).on('change', '#pdf_cedula, #pdf_certificado', function () {
  toggleBtnInscribir();
});


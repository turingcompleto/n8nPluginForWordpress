jQuery(function($) {
  const $chat = $('#cbn8n-chat');
  const $messages = $chat.find('.messages');
  const $input = $('#cbn8n-input');
  const $sendBtn = $('#cbn8n-send');

  /**
   * Envía el mensaje del usuario y gestiona la respuesta
   */
  function sendMessage() {
    const msg = $input.val().trim();
    if (!msg) {
      $messages.append(`<div class="msg error">Por favor, escribe un mensaje.</div>`);
      scrollToBottom();
      return;
    }

    // 1. Mostrar mensaje del usuario
    const userMsg = `<div class="msg user">${msg}</div>`;
    $messages.append(userMsg);
    $input.val('');
    scrollToBottom();

    // Deshabilitar botón y mostrar estado de carga
    $sendBtn.prop('disabled', true).html('Enviando...');

    // 2. Llamada AJAX a WordPress
    // Verificar si hay una URL de webhook configurada
    if (!CBN8N.webhook_url) {
      $messages.append(`<div class="msg error">No se ha configurado la URL del webhook. Por favor, configúrala en Ajustes > Chatbot n8n.</div>`);
      $sendBtn.prop('disabled', true);
      scrollToBottom();
      return;
    }

    $.ajax({
      url: CBN8N.ajax_url,
      type: 'POST',
      data: {
        action: 'cbn8n_chat',
        nonce: CBN8N.nonce,
        mensaje: msg,
        webhook_url: CBN8N.webhook_url
      },
      timeout: 30000, // 30 segundos
      success: function(res) {
        if (res.success) {
          $messages.append(`<div class="msg bot">${res.data}</div>`);
        } else {
          $messages.append(`<div class="msg error">Error: ${res.data.details || res.data.message}</div>`);
        }
      },
      error: function(xhr, status, error) {
        let errorMessage = 'Error de red. Por favor, intenta nuevamente.';
        if (xhr.status === 0) {
          errorMessage = 'No se puede conectar al servidor. Verifica tu conexión a internet.';
        } else if (xhr.status === 403) {
          errorMessage = 'Error de autenticación. Por favor, recarga la página.';
        } else if (xhr.status === 404) {
          errorMessage = 'Servicio no encontrado. Contacta al administrador.';
        }
        $messages.append(`<div class="msg error">${errorMessage}</div>`);
      },
      complete: function() {
        // Restaurar botón y estado normal
        $sendBtn.prop('disabled', false).html('Enviar');
        scrollToBottom();
      }
    });
  }

  /**
   * Desplaza el contenedor de mensajes al fondo
   */
  function scrollToBottom() {
    $messages.scrollTop($messages[0].scrollHeight);
  }

  // Envío al hacer clic en el botón
  $sendBtn.on('click', sendMessage);

  // Envío al presionar Enter en el input
  $input.on('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      sendMessage();
    }
  });
});

jQuery(function($) {
  // 1. Crea el botón de cerrar y añádelo al chat
  const $chat = $('#cbn8n-chat');
  const $closeBtn = $('<button id="cbn8n-close" aria-label="Cerrar chat">×</button>')
    .css({
      position: 'absolute',
      top: '-81px',
      right: '12px',
      background: 'transparent',
      border: 'none',
      'font-size': '1.5rem',
      cursor: 'pointer',
      color: '#fff',
      'z-index': 10
    })
    .appendTo($chat);

  // 2. Al hacer clic, oculta todo el contenedor fijo
  $closeBtn.on('click', function() {
    $('.cbn8n-fixed-container').hide();
  });

  // (Opcional) si quieres reabrirlo desde un botón externo:
   jQuery('#mi-boton-abrir-chat').on('click', () => {
     $('.cbn8n-fixed-container').show();
   });
});

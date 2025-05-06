<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 6.1 Añade menú
add_action( 'admin_menu', 'cbn8n_add_settings_page' );
function cbn8n_add_settings_page() {
  add_options_page(
    'Chatbot n8n',
    'Chatbot n8n',
    'manage_options',
    'cbn8n-settings',
    'cbn8n_settings_page'
  );
}

// 6.2 Registra la opción
add_action( 'admin_init', 'cbn8n_register_settings' );
function cbn8n_register_settings() {
  register_setting(
    'cbn8n_settings_group',
    'cbn8n_webhook_url',
    [
      'type'              => 'string',
      'sanitize_callback' => 'esc_url_raw',
      'default'           => ''
    ]
  );
}

// 6.3 Renderiza la página
function cbn8n_settings_page() { ?>
  <div class="wrap">
    <h1>Chatbot n8n Settings</h1>
    <form method="post" action="options.php">
      <?php settings_fields( 'cbn8n_settings_group' ); ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">
            <label for="cbn8n_webhook_url">Webhook URL de n8n</label>
          </th>
          <td>
            <input
              type="url"
              id="cbn8n_webhook_url"
              name="cbn8n_webhook_url"
              value="<?php echo esc_attr( get_option('cbn8n_webhook_url') ); ?>"
              style="width: 100%; max-width: 400px;"
            />
            <p class="description">
              Pega aquí la URL de tu Webhook de n8n.
            </p>
          </td>
        </tr>
      </table>
      <?php submit_button( 'Guardar Cambios' ); ?>
    </form>
  </div>
<?php }


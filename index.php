<?php
/**
 * Plugin Name:       Aurora Chat
 * Plugin URI:        https://agentesaurora.com.br/
 * Description:       Plataforma de agentes conversacionais com templates visuais personalizáveis.
 * Version:           1.0.49
 * Author:            Aurora Labs
 * Author URI:        https://agentesaurora.com.br/
 * Text Domain:       aurora-chat
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AURORA_CHAT_VERSION', '1.0.49' );
define( 'AURORA_CHAT_FILE', __FILE__ );
define( 'AURORA_CHAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AURORA_CHAT_URL', plugin_dir_url( __FILE__ ) );

require_once AURORA_CHAT_DIR . 'includes/class-aurora-chat-plugin.php';

Aurora_Chat_Plugin::instance();

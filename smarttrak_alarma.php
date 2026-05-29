<?php
/**
 * Plugin Name: Smarttrak Alarma
 * Description: Заявки на зворотний дзвінок і Telegram-аларми для Smarttrak.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Codex
 * License: GPL-2.0-or-later
 * Text Domain: smarttrak-alarma
 */

defined( 'ABSPATH' ) || exit;

define( 'SMARTTRAK_ALARMA_VERSION', '1.0.0' );
define( 'SMARTTRAK_ALARMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMARTTRAK_ALARMA_URL', plugin_dir_url( __FILE__ ) );

require_once SMARTTRAK_ALARMA_PATH . 'includes/class-smarttrak-alarma.php';

register_activation_hook( __FILE__, [ 'Smarttrak_Alarma', 'activate' ] );

Smarttrak_Alarma::instance();

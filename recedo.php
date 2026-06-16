<?php
/**
 * Plugin Name:       Recedo — Pulsante di Recesso 54-bis
 * Plugin URI:        https://github.com/Giovanni-Bernardi/recedo
 * Description:       Aggiunge il pulsante di recesso obbligatorio ex art. 54-bis D.Lgs. 209/2025 agli ordini WooCommerce. Plug & play, zero configurazione richiesta.
 * Version:           1.0.0
 * Author:            Giovanni Bernardi
 * Author URI:        https://github.com/Giovanni-Bernardi
 * License:           GPL-2.0-or-later
 * Text Domain:       recedo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WCR54_VERSION', '1.0.0' );
define( 'WCR54_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCR54_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ─── HPOS compatibility ───────────────────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

// ─── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Recedo</strong>: WooCommerce non è attivo.</p></div>';
        } );
        return;
    }
    require_once WCR54_PLUGIN_DIR . 'includes/class-wcr54-order-status.php';
    require_once WCR54_PLUGIN_DIR . 'includes/class-wcr54-frontend.php';
    require_once WCR54_PLUGIN_DIR . 'includes/class-wcr54-handler.php';
    require_once WCR54_PLUGIN_DIR . 'includes/class-wcr54-email.php';

    load_plugin_textdomain( 'recedo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    WCR54_Order_Status::init();
    WCR54_Frontend::init();
    WCR54_Handler::init();
    WCR54_Email::init();
} );

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    // Crea la tabella log recessi
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'wcr54_log';

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id    BIGINT UNSIGNED NOT NULL,
        user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_email  VARCHAR(200)    NOT NULL DEFAULT '',
        ip_address  VARCHAR(45)     NOT NULL DEFAULT '',
        user_agent  VARCHAR(500)    NOT NULL DEFAULT '',
        reason      TEXT,
        recesso_at  DATETIME        NOT NULL,
        ricevuta_sent TINYINT(1)    NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'wcr54_db_version', WCR54_VERSION );
} );

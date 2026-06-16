<?php
defined( 'ABSPATH' ) || exit;

class WCR54_Handler {

    public static function init(): void {
        add_action( 'wp_ajax_wcr54_submit_recesso', [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_wcr54_submit_recesso', [ __CLASS__, 'handle' ] );
    }

    /**
     * Handler unico: distingue utente loggato (ownership via customer_id)
     * da guest (ownership via order_key).
     */
    public static function handle(): void {
        if ( ! check_ajax_referer( 'wcr54_recesso', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Richiesta non valida. Ricarica la pagina e riprova.', 'recedo' ) ], 403 );
        }

        $order_id  = absint( $_POST['order_id'] ?? 0 );
        $order_key = sanitize_text_field( $_POST['order_key'] ?? '' );
        $reason    = sanitize_textarea_field( $_POST['reason'] ?? '' );

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'ID ordine mancante.', 'recedo' ) ], 400 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Ordine non trovato.', 'recedo' ) ], 404 );
        }

        // ── Verifica proprietà ────────────────────────────────────────────────
        if ( is_user_logged_in() ) {
            // Utente loggato: l'ordine deve appartenergli
            if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
                wp_send_json_error( [ 'message' => __( 'Non hai i permessi per questo ordine.', 'recedo' ) ], 403 );
            }
        } else {
            // Guest: deve fornire la order_key corretta
            if ( ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
                wp_send_json_error( [ 'message' => __( 'Chiave ordine non valida.', 'recedo' ) ], 403 );
            }
        }

        // ── Eleggibilità ──────────────────────────────────────────────────────
        if ( $order->get_status() === 'recesso' ) {
            wp_send_json_error( [ 'message' => __( 'Il recesso è già stato richiesto per questo ordine.', 'recedo' ) ], 409 );
        }
        if ( ! WCR54_Frontend::can_recede( $order ) ) {
            wp_send_json_error( [ 'message' => __( 'Il recesso non è disponibile per questo ordine (finestra scaduta o stato non compatibile).', 'recedo' ) ], 409 );
        }

        // ── Processa ──────────────────────────────────────────────────────────
        $recesso_at = self::process( $order, $reason );

        wp_send_json_success( [
            'message' => sprintf(
                __( 'Recesso per l\'ordine #%d registrato il %s. Ti invieremo una conferma via email.', 'recedo' ),
                $order_id,
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $recesso_at ) )
            ),
            'ricevuta_at' => $recesso_at,
        ] );
    }

    /**
     * Logica di business condivisa: log probatorio, cambio stato, email.
     * @return string Timestamp mysql del recesso.
     */
    private static function process( WC_Order $order, string $reason ): string {
        $order_id   = $order->get_id();
        $recesso_at = current_time( 'mysql' );
        $ip         = self::get_ip();
        $ua         = substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 500 );

        // 1. Log probatorio
        self::log( [
            'order_id'   => $order_id,
            'user_id'    => (int) $order->get_customer_id(),
            'user_email' => $order->get_billing_email(),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'reason'     => $reason,
            'recesso_at' => $recesso_at,
        ] );

        // 2. Cambio stato
        $order->update_status( 'recesso', __( 'Recesso esercitato dal cliente tramite portale.', 'recedo' ) );

        // 3. Metadati probatori sull'ordine
        $order->update_meta_data( '_wcr54_recesso_at', $recesso_at );
        $order->update_meta_data( '_wcr54_recesso_ip', $ip );
        $order->update_meta_data( '_wcr54_recesso_ua', $ua );
        $order->update_meta_data( '_wcr54_recesso_reason', $reason );
        $order->save();

        // 4. Ricevuta
        do_action( 'wcr54_send_ricevuta', $order, $recesso_at );

        return $recesso_at;
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private static function log( array $data ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wcr54_log',
            array_merge( $data, [ 'ricevuta_sent' => 0 ] ),
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' ]
        );
    }

    private static function get_ip(): string {
        $keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $keys as $key ) {
            $ip = $_SERVER[ $key ] ?? '';
            if ( $ip ) {
                return sanitize_text_field( trim( explode( ',', $ip )[0] ) );
            }
        }
        return 'unknown';
    }
}

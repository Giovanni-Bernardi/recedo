<?php
defined( 'ABSPATH' ) || exit;

class WCR54_Frontend {

    // Finestra di recesso in secondi (14 giorni)
    const RECESSO_WINDOW = 14 * DAY_IN_SECONDS;

    // Stati ordine su cui il recesso è ammesso
    const ELIGIBLE_STATUSES = [ 'processing', 'completed', 'on-hold' ];

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Colonna extra nella tabella ordini del "Mio Account"
        add_filter( 'woocommerce_my_account_my_orders_columns', [ __CLASS__, 'add_column' ] );
        add_action( 'woocommerce_my_account_my_orders_column_recesso', [ __CLASS__, 'render_column' ] );

        // Bottone anche nella singola pagina ordine
        add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'render_order_page_button' ] );

        // Modale / form di conferma (iniettato nel footer)
        add_action( 'wp_footer', [ __CLASS__, 'render_modal' ] );
    }

    // ── Assets ─────────────────────────────────────────────────────────────────
    public static function enqueue_assets(): void {
        if ( ! is_account_page() && ! is_wc_endpoint_url( 'view-order' ) ) {
            return;
        }
        wp_enqueue_style(
            'wcr54-style',
            WCR54_PLUGIN_URL . 'assets/recesso.css',
            [],
            WCR54_VERSION
        );
        wp_enqueue_script(
            'wcr54-script',
            WCR54_PLUGIN_URL . 'assets/recesso.js',
            [ 'jquery' ],
            WCR54_VERSION,
            true
        );
        wp_localize_script( 'wcr54-script', 'wcr54', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wcr54_recesso' ),
            'i18n'     => [
                'confirm_label'  => __( 'Conferma recesso', 'recedo' ),
                'sending'        => __( 'Invio in corso…', 'recedo' ),
                'success'        => __( 'Recesso registrato. Riceverai una email di conferma.', 'recedo' ),
                'error_generic'  => __( 'Si è verificato un errore. Riprova.', 'recedo' ),
            ],
        ] );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Determina se un ordine è ancora in finestra di recesso (14 giorni).
     * La data di riferimento (legalmente: ricezione merce) è filtrabile via 'wcr54_reference_date'.
     */
    public static function is_within_window( WC_Order $order ): bool {
        $ref_date = self::get_reference_date( $order );
        if ( ! $ref_date ) {
            return false;
        }
        return ( time() - $ref_date->getTimestamp() ) <= self::RECESSO_WINDOW;
    }

    /**
     * Restituisce la data da cui calcolare la finestra di recesso.
     * Default: data completamento (o creazione). Hook per agganciare la data di consegna reale.
     */
    public static function get_reference_date( WC_Order $order ): ?WC_DateTime {
        $ref_date = $order->get_date_completed() ?? $order->get_date_created();
        return apply_filters( 'wcr54_reference_date', $ref_date, $order );
    }

    /**
     * Determina se il recesso è disponibile per questo ordine.
     */
    public static function can_recede( WC_Order $order ): bool {
        if ( ! in_array( $order->get_status(), self::ELIGIBLE_STATUSES, true ) ) {
            return false;
        }
        if ( ! self::is_within_window( $order ) ) {
            return false;
        }
        return true;
    }

    // ── Colonna nella tabella ordini ───────────────────────────────────────────

    public static function add_column( array $columns ): array {
        $columns['recesso'] = __( 'Recesso', 'recedo' );
        return $columns;
    }

    public static function render_column( $order ): void {
        if ( ! ( $order instanceof WC_Order ) ) {
            $order = wc_get_order( is_object( $order ) ? $order->get_id() : $order );
        }
        if ( ! $order ) {
            return;
        }
        self::render_button( $order );
    }

    // ── Bottone nella singola pagina ordine ────────────────────────────────────

    public static function render_order_page_button( WC_Order $order ): void {
        if ( ! self::can_recede( $order ) ) {
            return;
        }
        echo '<div class="wcr54-order-page-cta">';
        self::render_button( $order );
        echo '</div>';
    }

    // ── Render del pulsante ────────────────────────────────────────────────────

    public static function render_button( WC_Order $order ): void {
        if ( ! self::can_recede( $order ) ) {
            echo '<span class="wcr54-na">—</span>';
            return;
        }

        $ref_date    = self::get_reference_date( $order );
        $scadenza    = $ref_date ? ( $ref_date->getTimestamp() + self::RECESSO_WINDOW ) : 0;
        $giorni_left = $scadenza ? max( 0, (int) ceil( ( $scadenza - time() ) / DAY_IN_SECONDS ) ) : 0;

        // order_key necessario per il flusso guest (utenti non loggati)
        $order_key = is_user_logged_in() ? '' : $order->get_order_key();

        printf(
            '<button
                type="button"
                class="button wcr54-btn-recesso"
                data-order-id="%d"
                data-order-key="%s"
                data-scadenza="%s"
                aria-label="%s"
            >%s</button>
            <span class="wcr54-giorni-left">%s</span>',
            esc_attr( $order->get_id() ),
            esc_attr( $order_key ),
            esc_attr( date_i18n( 'd/m/Y', $scadenza ) ),
            esc_attr( sprintf( __( 'Esercita il diritto di recesso per l\'ordine #%d', 'recedo' ), $order->get_id() ) ),
            esc_html__( 'Recedere', 'recedo' ),
            esc_html( sprintf( _n( 'Scade tra %d giorno', 'Scade tra %d giorni', $giorni_left, 'recedo' ), $giorni_left ) )
        );
    }

    // ── Modale di doppia conferma ──────────────────────────────────────────────

    public static function render_modal(): void {
        if ( ! is_account_page() && ! is_wc_endpoint_url( 'view-order' ) ) {
            return;
        }
        ?>
        <div id="wcr54-modal" class="wcr54-modal" role="dialog" aria-modal="true" aria-labelledby="wcr54-modal-title" hidden>
            <div class="wcr54-modal-overlay" id="wcr54-overlay"></div>
            <div class="wcr54-modal-box">
                <button class="wcr54-modal-close" aria-label="<?php esc_attr_e( 'Chiudi', 'recedo' ); ?>">&#x2715;</button>

                <!-- Step 1: raccolta dati -->
                <div id="wcr54-step-1">
                    <h2 id="wcr54-modal-title"><?php esc_html_e( 'Diritto di recesso', 'recedo' ); ?></h2>
                    <p class="wcr54-subtitle">
                        <?php esc_html_e( 'Stai per esercitare il diritto di recesso per l\'ordine', 'recedo' ); ?>
                        <strong id="wcr54-order-label"></strong>.
                        <?php esc_html_e( 'Il recesso deve essere esercitato entro 14 giorni dalla ricezione della merce.', 'recedo' ); ?>
                    </p>

                    <label for="wcr54-reason"><?php esc_html_e( 'Motivo (facoltativo)', 'recedo' ); ?></label>
                    <textarea id="wcr54-reason" name="reason" rows="3" maxlength="500"
                        placeholder="<?php esc_attr_e( 'Es: prodotto non conforme, cambio idea…', 'recedo' ); ?>"></textarea>

                    <div class="wcr54-info-box">
                        <p><?php printf(
                            esc_html__( 'I tuoi dati: %s — Ordine: #%s', 'recedo' ),
                            '<span id="wcr54-user-email"></span>',
                            '<span id="wcr54-order-id-display"></span>'
                        ); ?></p>
                        <p><?php esc_html_e( 'Scadenza finestra recesso:', 'recedo' ); ?> <strong id="wcr54-scadenza"></strong></p>
                    </div>

                    <button type="button" class="button button-primary wcr54-btn-step2">
                        <?php esc_html_e( 'Procedi →', 'recedo' ); ?>
                    </button>
                </div>

                <!-- Step 2: doppia conferma (art. 54-bis c.3) -->
                <div id="wcr54-step-2" hidden>
                    <h2><?php esc_html_e( 'Conferma definitiva', 'recedo' ); ?></h2>
                    <p class="wcr54-warning">
                        <?php esc_html_e( 'Stai per confermare il recesso. Questa azione è definitiva e genererà una ricevuta legale inviata via email.', 'recedo' ); ?>
                    </p>
                    <div class="wcr54-btn-group">
                        <button type="button" class="button wcr54-btn-back">← <?php esc_html_e( 'Indietro', 'recedo' ); ?></button>
                        <button type="button" class="button button-primary wcr54-btn-confirm" id="wcr54-btn-confirm-final">
                            <?php esc_html_e( 'Conferma recesso', 'recedo' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 3: esito -->
                <div id="wcr54-step-3" hidden>
                    <div class="wcr54-success-icon">✓</div>
                    <h2><?php esc_html_e( 'Recesso registrato', 'recedo' ); ?></h2>
                    <p id="wcr54-success-msg"></p>
                </div>

                <div id="wcr54-error-msg" class="wcr54-error" hidden></div>
            </div>
        </div>
        <?php
    }
}

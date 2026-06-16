<?php
defined( 'ABSPATH' ) || exit;

class WCR54_Email {

    public static function init(): void {
        add_action( 'wcr54_send_ricevuta', [ __CLASS__, 'send' ], 10, 2 );
    }

    public static function send( WC_Order $order, string $recesso_at ): void {
        $order_id    = $order->get_id();
        $user_email  = $order->get_billing_email();
        $first_name  = $order->get_billing_first_name();
        $last_name   = $order->get_billing_last_name();
        $reason      = $order->get_meta( '_wcr54_recesso_reason' ) ?: '—';
        $blog_name   = get_bloginfo( 'name' );
        $date_fmt    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        $recesso_fmt = date_i18n( $date_fmt, strtotime( $recesso_at ) );

        // ── Email al cliente (ricevuta legale su supporto durevole) ────────────
        $subject_cliente = sprintf(
            __( '[%s] Ricevuta di recesso — Ordine #%d', 'recedo' ),
            $blog_name,
            $order_id
        );

        $body_cliente = self::build_email_body_cliente(
            $first_name,
            $last_name,
            $order_id,
            $recesso_fmt,
            $reason,
            $blog_name
        );

        self::send_email( $user_email, $subject_cliente, $body_cliente );

        // ── Email al merchant ──────────────────────────────────────────────────
        $admin_email      = get_option( 'admin_email' );
        $subject_merchant = sprintf(
            __( '[%s] Nuovo recesso ricevuto — Ordine #%d', 'recedo' ),
            $blog_name,
            $order_id
        );

        $body_merchant = self::build_email_body_merchant(
            $first_name . ' ' . $last_name,
            $user_email,
            $order_id,
            $recesso_fmt,
            $reason,
            $order->get_order_number()
        );

        self::send_email( $admin_email, $subject_merchant, $body_merchant );

        // ── Aggiorna log: ricevuta inviata ─────────────────────────────────────
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wcr54_log',
            [ 'ricevuta_sent' => 1 ],
            [ 'order_id' => $order_id ],
            [ '%d' ],
            [ '%d' ]
        );
    }

    // ── Template email cliente ─────────────────────────────────────────────────

    private static function build_email_body_cliente(
        string $first_name,
        string $last_name,
        int    $order_id,
        string $recesso_fmt,
        string $reason,
        string $blog_name
    ): string {
        ob_start();
        ?>
Gentile <?php echo esc_html( $first_name . ' ' . $last_name ); ?>,

questa email costituisce la RICEVUTA LEGALE di recesso ai sensi dell'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025).

──────────────────────────────────────────────
DATI RECESSO
──────────────────────────────────────────────
Ordine:        #<?php echo esc_html( $order_id ); ?>

Data e ora:    <?php echo esc_html( $recesso_fmt ); ?> (ora sito)
Motivo:        <?php echo esc_html( $reason ); ?>

──────────────────────────────────────────────

Il suo diritto di recesso è stato correttamente registrato.

Ai sensi degli artt. 54-ter e ss. del Codice del Consumo Le ricordiamo che:
• Dovrà restituire i beni entro 14 giorni dalla presente comunicazione.
• Il rimborso sarà effettuato entro 14 giorni dal ricevimento dei beni o della prova di spedizione.
• Il rimborso avverrà tramite lo stesso mezzo di pagamento utilizzato per l'acquisto, salvo diverso accordo.

Per informazioni sul reso fisico della merce, risponda a questa email o contatti il nostro servizio clienti.

Cordiali saluti,
<?php echo esc_html( $blog_name ); ?>

──────────────────────────────────────────────
Questa email è generata automaticamente ai sensi dell'art. 54-bis c. 4 D.Lgs. 209/2025.
Conservarla come prova del recesso esercitato.
        <?php
        return ob_get_clean();
    }

    // ── Template email merchant ────────────────────────────────────────────────

    private static function build_email_body_merchant(
        string $customer_name,
        string $customer_email,
        int    $order_id,
        string $recesso_fmt,
        string $reason,
        string $order_number
    ): string {
        ob_start();
        ?>
Nuovo recesso ricevuto tramite portale.

──────────────────────────────────────────────
DETTAGLI
──────────────────────────────────────────────
Ordine:        #<?php echo esc_html( $order_number ); ?>

Cliente:       <?php echo esc_html( $customer_name ); ?> <<?php echo esc_html( $customer_email ); ?>>
Data e ora:    <?php echo esc_html( $recesso_fmt ); ?>

Motivo:        <?php echo esc_html( $reason ); ?>

──────────────────────────────────────────────

L'ordine è stato aggiornato allo stato "Recesso richiesto".
La ricevuta legale è stata inviata al cliente.

Accedi al pannello per gestire il reso:
<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>

        <?php
        return ob_get_clean();
    }

    // ── Invio ─────────────────────────────────────────────────────────────────

    private static function send_email( string $to, string $subject, string $body ): void {
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        wp_mail( $to, $subject, $body, $headers );
    }
}

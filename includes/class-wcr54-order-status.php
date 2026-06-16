<?php
defined( 'ABSPATH' ) || exit;

class WCR54_Order_Status {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_status' ] );
        add_filter( 'wc_order_statuses', [ __CLASS__, 'add_to_list' ] );
    }

    public static function register_status(): void {
        register_post_status( 'wc-recesso', [
            'label'                     => _x( 'Recesso richiesto', 'Order status', 'recedo' ),
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s = count */
            'label_count'               => _n_noop( 'Recesso richiesto <span class="count">(%s)</span>', 'Recesso richiesto <span class="count">(%s)</span>', 'recedo' ),
        ] );
    }

    public static function add_to_list( array $statuses ): array {
        $statuses['wc-recesso'] = _x( 'Recesso richiesto', 'Order status', 'recedo' );
        return $statuses;
    }
}

<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Guest Orders', 'space-core' ); ?></h1>

    <form method="get" action="<?php echo esc_url( $page_url ); ?>"
          style="background:#fff;padding:16px 20px;border:1px solid #ddd;border-radius:6px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="page" value="sc-guest-orders">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Phone', 'space-core' ); ?></label>
            <input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+96512345678">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'From', 'space-core' ); ?></label>
            <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'To', 'space-core' ); ?></label>
            <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
        </div>
        <div>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'space-core' ); ?></button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button" style="margin-left:4px;"><?php esc_html_e( 'Reset', 'space-core' ); ?></a>
            <a href="<?php echo esc_url( add_query_arg( [
                'page'   => 'sc-guest-orders',
                'action' => 'sc_guest_orders_export',
                'nonce'  => $nonce,
                'phone'  => $phone,
                'from'   => $from,
                'to'     => $to,
            ], admin_url( 'admin-ajax.php' ) ) ); ?>" class="button" style="margin-left:4px;"><?php esc_html_e( 'Export CSV', 'space-core' ); ?></a>
        </div>
    </form>

    <?php if ( empty( $rows ) ) : ?>
        <p><?php esc_html_e( 'No guest orders found.', 'space-core' ); ?></p>
    <?php else : ?>
        <p style="color:#666;font-size:.875rem;">
            <?php
            printf(
                esc_html__( 'Showing %d–%d of %d customers', 'space-core' ),
                ( $paged - 1 ) * $per_page + 1,
                min( $paged * $per_page, $total ),
                $total
            );
            ?>
        </p>
        <table class="widefat striped" style="margin-bottom:16px;">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Email', 'space-core' ); ?></th>
                <th style="text-align:center;"><?php esc_html_e( '# Orders', 'space-core' ); ?></th>
                <th style="text-align:right;"><?php esc_html_e( 'Total Spent', 'space-core' ); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row['name'] ); ?></td>
                    <td><?php echo esc_html( $row['phone'] ); ?></td>
                    <td><?php echo esc_html( $row['email'] ); ?></td>
                    <td style="text-align:center;"><strong><?php echo esc_html( (string) $row['order_count'] ); ?></strong></td>
                    <td style="text-align:right;"><?php echo esc_html( $currency . number_format( $row['total_spent'], 2 ) ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $row['orders_url'] ); ?>" class="button button-small" target="_blank">
                            <?php esc_html_e( 'View Orders', 'space-core' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div style="display:flex;gap:4px;align-items:center;">
                <?php for ( $p = 1; $p <= $total_pages; $p ++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [
                        'page'  => 'sc-guest-orders',
                        'paged' => $p,
                        'phone' => $phone,
                        'from'  => $from,
                        'to'    => $to,
                    ], $page_url ) ); ?>"
                       class="button<?php echo $p === $paged ? ' button-primary' : ''; ?>"
                       style="min-width:32px;text-align:center;">
                        <?php echo esc_html( (string) $p ); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

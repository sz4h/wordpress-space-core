<?php

/**
 * Debug helpers — dd() and dump().
 *
 * Available in all contexts (admin, frontend, CLI, cron).
 * Guarded with function_exists() so they never clash with other plugins.
 */

if ( ! function_exists( 'dump' ) ) {
    /**
     * Pretty-print one or more values.
     */
    function dump( mixed ...$values ): void {
        foreach ( $values as $value ) {
            echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px 16px;margin:8px 0;border-radius:4px;font-size:13px;overflow:auto;text-align:left;">';
            var_dump( $value );
            echo '</pre>';
        }
    }
}

if ( ! function_exists( 'dd' ) ) {
    /**
     * Dump one or more values then die.
     */
    function dd( mixed ...$values ): never {
        dump( ...$values );
        die();
    }
}

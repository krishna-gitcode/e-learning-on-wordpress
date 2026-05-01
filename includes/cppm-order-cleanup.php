<?php
/**
 * Core: Automated Database Cleanup (Cron Jobs)
 * Architecture: Pure Logic (No Assets)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. SCHEDULE THE DAILY CLEANUP TASK
// ==========================================
// Hooking to 'init' is much safer and faster than 'wp'
add_action( 'init', 'cppm_schedule_daily_order_cleanup' );
function cppm_schedule_daily_order_cleanup() {
    if ( ! wp_next_scheduled( 'cppm_daily_abandoned_order_sweep' ) ) {
        wp_schedule_event( time(), 'daily', 'cppm_daily_abandoned_order_sweep' );
    }
}

// ==========================================
// 2. THE CLEANUP SCRIPT (PERMANENT DELETE)
// ==========================================
add_action( 'cppm_daily_abandoned_order_sweep', 'cppm_execute_order_cleanup' );
function cppm_execute_order_cleanup() {
    
    // Only target orders that are Pending, Failed, or Cancelled
    // DO NOT target 'on-hold' because those are waiting for your manual UPI verification!
    $statuses_to_clean = array( 'wc-pending', 'wc-failed', 'wc-cancelled' );
    
    // Target orders older than 48 hours (2 days)
    $time_threshold = strtotime( '-2 days' );

    // Fetch the ghost orders
    $args = array(
        'status'       => $statuses_to_clean,
        'date_created' => '<' . $time_threshold,
        'limit'        => 100, // Do this in batches of 100 to prevent server overload
        'return'       => 'ids',
    );

    $ghost_orders = wc_get_orders( $args );

    if ( ! empty( $ghost_orders ) ) {
        foreach ( $ghost_orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                // The 'true' parameter forces permanent deletion (bypasses the trash)
                $order->delete( true ); 
            }
        }
    }
}

// ==========================================
// 3. CLEAN UP CRON ON PLUGIN DEACTIVATION
// ==========================================
// Safely remove the background job if you ever turn the plugin off
// (Assuming your main plugin file is 'custom-presto-playlist.php' in the root directory)
register_deactivation_hook( dirname( dirname( __FILE__ ) ) . '/custom-presto-playlist.php', 'cppm_remove_cron_job' );
function cppm_remove_cron_job() {
    $timestamp = wp_next_scheduled( 'cppm_daily_abandoned_order_sweep' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'cppm_daily_abandoned_order_sweep' );
    }
}
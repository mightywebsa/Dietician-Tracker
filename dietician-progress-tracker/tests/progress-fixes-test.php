<?php
/**
 * Checks for legacy meal-plan reads and calculated BMI storage.
 * Run: php dietician-progress-tracker/tests/progress-fixes-test.php
 */

$GLOBALS['dpt_meta'] = array();
$GLOBALS['dpt_posts'] = array();
$GLOBALS['dpt_inserts'] = array();
$GLOBALS['dpt_current_user'] = 7;

function absint( $maybeint ) {
    return abs( (int) $maybeint );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}
function register_activation_hook( $file, $callback ) {}

function wp_unslash( $value ) {
    return $value;
}

function is_user_logged_in() {
    return true;
}

function get_current_user_id() {
    return (int) $GLOBALS['dpt_current_user'];
}

function get_user_meta( $user_id, $key, $single = false ) {
    $user_id = (int) $user_id;
    if ( ! isset( $GLOBALS['dpt_meta'][ $user_id ][ $key ] ) ) {
        return '';
    }
    return $GLOBALS['dpt_meta'][ $user_id ][ $key ];
}

function update_user_meta( $user_id, $key, $value ) {
    $user_id = (int) $user_id;
    $GLOBALS['dpt_meta'][ $user_id ][ $key ] = $value;
    return true;
}

function get_post( $post_id ) {
    $post_id = (int) $post_id;
    return $GLOBALS['dpt_posts'][ $post_id ] ?? null;
}

function wp_verify_nonce( $nonce, $action = -1 ) {
    return $nonce === 'test-nonce' && $action === 'dpt_save_progress';
}

function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $display = true ) {
    return '<input type="hidden" name="' . $name . '" value="nonce">';
}

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wpautop( $text ) {
    return '<p>' . $text . '</p>';
}

function wp_kses_post( $data ) {
    return $data;
}

function sanitize_textarea_field( $text ) {
    return is_string( $text ) ? $text : '';
}

function current_time( $type ) {
    return '2026-10-14 12:00:00';
}

class DptFakeWpdb {
    public $prefix = 'wp_';

    public function insert( $table, $data ) {
        $GLOBALS['dpt_inserts'][] = $data;
        return 1;
    }
}

$wpdb = new DptFakeWpdb();

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

require dirname( __DIR__ ) . '/dietician-progress-tracker.php';

$failures = 0;

function dpt_assert( $condition, $message ) {
    global $failures;
    if ( $condition ) {
        echo "PASS: $message\n";
        return;
    }
    $failures++;
    echo "FAIL: $message\n";
}

function dpt_assert_near( $actual, $expected, $message ) {
    dpt_assert( abs( (float) $actual - (float) $expected ) < 0.001, $message . " (got $actual, expected $expected)" );
}

function dpt_post( $id, $type, $status, $title ) {
    $GLOBALS['dpt_posts'][ $id ] = (object) array(
        'ID'           => $id,
        'post_type'    => $type,
        'post_status'  => $status,
        'post_title'   => $title,
        'post_content' => 'Plan body',
    );
}

echo "BMI persistence\n";
dpt_assert_near( dpt_resolve_logged_bmi( '', 80, 180 ), 80 / ( 1.8 * 1.8 ), 'blank BMI is calculated from weight and height' );
dpt_assert_near( dpt_resolve_logged_bmi( '0', 80, 180 ), 80 / ( 1.8 * 1.8 ), 'zero BMI is treated as missing and calculated' );
dpt_assert_near( dpt_resolve_logged_bmi( '22.5', 80, 180 ), 22.5, 'an explicit BMI is kept' );
dpt_assert_near( dpt_resolve_logged_bmi( '', 80, 0 ), 0, 'BMI stays empty when height is missing' );

$GLOBALS['dpt_meta'] = array();
$GLOBALS['dpt_inserts'] = array();
$GLOBALS['dpt_current_user'] = 4;
$_POST = array(
    'dpt_submit'         => 'Save Progress',
    'dpt_progress_nonce' => 'test-nonce',
    'weight'             => '80',
    'height_cm'          => '180',
    'bmi'                => '',
    'waist'              => '70',
    'notes'              => 'check-in',
);
ob_start();
dpt_progress_form();
ob_end_clean();
dpt_assert( count( $GLOBALS['dpt_inserts'] ) === 1, 'progress form inserts one row' );
dpt_assert_near( $GLOBALS['dpt_inserts'][0]['bmi'], 80 / ( 1.8 * 1.8 ), 'progress form stores the calculated BMI instead of 0' );

echo "\nLegacy meal plan reads\n";
$GLOBALS['dpt_meta'] = array();
$GLOBALS['dpt_posts'] = array();
dpt_post( 20, 'meal_plan', 'publish', 'Maintenance' );
dpt_post( 21, 'meal_plan', 'publish', 'New Plan' );
$GLOBALS['dpt_meta'][7] = array(
    'meal_plan'    => '',
    'meal_plan_id' => '20',
);
$GLOBALS['dpt_current_user'] = 7;
$html = get_current_mealplan();
dpt_assert( strpos( $html, 'Maintenance' ) !== false, 'shortcode shows a plan stored only under the legacy meta key' );
dpt_assert( ( $GLOBALS['dpt_meta'][7]['meal_plan'] ?? null ) == 20, 'legacy plan id is copied onto the canonical meta key' );

$GLOBALS['dpt_meta'][7] = array(
    'meal_plan'    => '21',
    'meal_plan_id' => '20',
);
$html = get_current_mealplan();
dpt_assert( strpos( $html, 'New Plan' ) !== false, 'canonical meal_plan wins over the legacy key' );
dpt_assert( ( $GLOBALS['dpt_meta'][7]['meal_plan'] ?? null ) == 21, 'a current assignment is not overwritten by the legacy key' );

$GLOBALS['dpt_meta'] = array(
    7 => array( 'meal_plan_id' => '30' ),
);
dpt_post( 30, 'post', 'publish', 'Blog post' );
$html = get_current_mealplan();
dpt_assert( strpos( $html, 'No meal plan assigned yet.' ) !== false, 'legacy meta pointing at a non-meal-plan post is ignored' );
dpt_assert( ! isset( $GLOBALS['dpt_meta'][7]['meal_plan'] ), 'non-meal-plan legacy ids are not migrated' );

if ( $failures > 0 ) {
    echo "\n$failures assertion(s) failed.\n";
    exit( 1 );
}

echo "\nAll assertions passed.\n";
exit( 0 );

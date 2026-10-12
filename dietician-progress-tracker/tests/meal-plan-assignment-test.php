<?php
/**
 * Behavioral checks for meal-plan assignment and BMI persistence.
 * Run: php dietician-progress-tracker/tests/meal-plan-assignment-test.php
 */

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$GLOBALS['dpt_meta'] = array();
$GLOBALS['dpt_meta_writes'] = array();
$GLOBALS['dpt_posts'] = array();
$GLOBALS['dpt_users'] = array();
$GLOBALS['dpt_inserts'] = array();
$GLOBALS['dpt_current_user'] = 7;

function absint($maybeint) {
    return abs((int) $maybeint);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_activation_hook($file, $callback) {}

function wp_unslash($value) {
    return $value;
}

function is_user_logged_in() {
    return true;
}

function get_current_user_id() {
    return (int) $GLOBALS['dpt_current_user'];
}

function get_user_meta($user_id, $key, $single = false) {
    $user_id = (int) $user_id;
    if (!isset($GLOBALS['dpt_meta'][$user_id][$key])) {
        return '';
    }
    return $GLOBALS['dpt_meta'][$user_id][$key];
}

function update_user_meta($user_id, $key, $value) {
    $user_id = (int) $user_id;
    $GLOBALS['dpt_meta'][$user_id][$key] = $value;
    $GLOBALS['dpt_meta_writes'][] = array($user_id, $key, $value);
    return true;
}

function get_post($post_id) {
    $post_id = (int) $post_id;
    return $GLOBALS['dpt_posts'][$post_id] ?? null;
}

function get_userdata($user_id) {
    $user_id = (int) $user_id;
    return $GLOBALS['dpt_users'][$user_id] ?? false;
}

function get_users($args = array()) {
    return array_values($GLOBALS['dpt_users']);
}

function get_posts($args = array()) {
    $posts = array();
    foreach ($GLOBALS['dpt_posts'] as $post) {
        if (($args['post_type'] ?? 'post') !== $post->post_type) {
            continue;
        }
        $status = $args['post_status'] ?? 'publish';
        if ($status !== 'any' && $post->post_status !== $status) {
            continue;
        }
        $posts[] = $post;
    }
    return $posts;
}

function current_user_can($capability) {
    return !empty($GLOBALS['dpt_can_manage']);
}

function check_admin_referer($action, $query_arg = '_wpnonce') {
    return true;
}

function wp_die($message = '', $title = '', $args = array()) {
    throw new RuntimeException(is_string($message) ? $message : 'wp_die');
}

function wp_nonce_field($action, $name) {
    echo '<input type="hidden" name="' . $name . '" value="nonce">';
}

function wp_safe_redirect($location) {
    $GLOBALS['dpt_redirect'] = $location;
    return true;
}

function admin_url($path = '') {
    return 'https://example.test/wp-admin/' . $path;
}

function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_textarea($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wpautop($text) {
    return '<p>' . $text . '</p>';
}

function sanitize_textarea_field($text) {
    return is_string($text) ? $text : '';
}

function current_time($type) {
    return '2026-10-12 12:00:00';
}

function selected($selected, $current = true, $display = true) {
    return '';
}

class DptFakeWpdb {
    public $prefix = 'wp_';

    public function insert($table, $data) {
        $GLOBALS['dpt_inserts'][] = $data;
        return 1;
    }

    public function prepare($query, ...$args) {
        return $query;
    }

    public function get_var($query) {
        return null;
    }

    public function get_row($query) {
        return null;
    }

    public function get_results($query) {
        return array();
    }

    public function update($table, $data, $where) {
        return 1;
    }

    public function delete($table, $where) {
        return 1;
    }
}

$wpdb = new DptFakeWpdb();

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

require dirname(__DIR__) . '/dietician-progress-tracker.php';

$failures = 0;

function dpt_assert($condition, $message) {
    global $failures;
    if ($condition) {
        echo "PASS: $message\n";
        return;
    }
    $failures++;
    echo "FAIL: $message\n";
}

function dpt_assert_near($actual, $expected, $message) {
    dpt_assert(abs((float) $actual - (float) $expected) < 0.001, $message . " (got $actual, expected $expected)");
}

function dpt_reset_state() {
    $GLOBALS['dpt_meta'] = array();
    $GLOBALS['dpt_meta_writes'] = array();
    $GLOBALS['dpt_posts'] = array();
    $GLOBALS['dpt_users'] = array(
        4 => (object) array('ID' => 4, 'display_name' => 'Patient Four'),
        5 => (object) array('ID' => 5, 'display_name' => 'Patient Five'),
    );
    $GLOBALS['dpt_inserts'] = array();
    $GLOBALS['dpt_can_manage'] = true;
    $GLOBALS['dpt_current_user'] = 7;
    $_POST = array();
    $_GET = array();
    $_REQUEST = array();
}

function dpt_post($id, $type, $status, $title) {
    $GLOBALS['dpt_posts'][$id] = (object) array(
        'ID' => $id,
        'post_type' => $type,
        'post_status' => $status,
        'post_title' => $title,
        'post_content' => 'Plan body',
    );
}

echo "BMI persistence\n";
dpt_assert_near(dpt_resolve_logged_bmi('', 80, 180), 80 / (1.8 * 1.8), 'blank BMI is calculated from weight and height');
dpt_assert_near(dpt_resolve_logged_bmi('0', 80, 180), 80 / (1.8 * 1.8), 'zero BMI is treated as missing and calculated');
dpt_assert_near(dpt_resolve_logged_bmi('22.5', 80, 180), 22.5, 'an explicit BMI is kept');
dpt_assert_near(dpt_resolve_logged_bmi('', 80, 0), 0, 'BMI stays empty when height is missing');

dpt_reset_state();
$_POST = array(
    'dpt_submit' => 'Save Progress',
    'weight' => '80',
    'height_cm' => '180',
    'bmi' => '',
    'waist' => '70',
    'hip_cm' => '',
    'chest_cm' => '',
    'left_bicep_cm' => '',
    'right_bicep_cm' => '',
    'left_thigh_cm' => '',
    'right_thigh_cm' => '',
    'body_fat' => '',
    'notes' => 'check-in',
);
$GLOBALS['dpt_current_user'] = 4;
ob_start();
dpt_progress_form();
ob_end_clean();
dpt_assert(count($GLOBALS['dpt_inserts']) === 1, 'progress form inserts one row');
dpt_assert_near($GLOBALS['dpt_inserts'][0]['bmi'], 80 / (1.8 * 1.8), 'progress form stores the calculated BMI instead of 0');

echo "\nLegacy meal plan reads\n";
dpt_reset_state();
dpt_post(20, 'meal_plan', 'publish', 'Maintenance');
dpt_post(21, 'meal_plan', 'publish', 'New Plan');
$GLOBALS['dpt_meta'][7] = array('meal_plan' => '', 'meal_plan_id' => '20');
$GLOBALS['dpt_current_user'] = 7;
$html = get_current_mealplan();
dpt_assert(strpos($html, 'Maintenance') !== false, 'shortcode shows a plan stored only under the legacy meta key');
dpt_assert(($GLOBALS['dpt_meta'][7]['meal_plan'] ?? null) == 20, 'legacy plan id is copied onto the canonical meta key');

dpt_reset_state();
dpt_post(20, 'meal_plan', 'publish', 'Maintenance');
dpt_post(21, 'meal_plan', 'publish', 'New Plan');
$GLOBALS['dpt_meta'][7] = array('meal_plan' => '21', 'meal_plan_id' => '20');
$GLOBALS['dpt_meta_writes'] = array();
$html = get_current_mealplan();
dpt_assert(strpos($html, 'New Plan') !== false, 'canonical meal_plan wins over the legacy key');
dpt_assert($GLOBALS['dpt_meta_writes'] === array(), 'a current assignment is not overwritten by the legacy key');

dpt_reset_state();
dpt_post(30, 'post', 'publish', 'Blog post');
$GLOBALS['dpt_meta'][7] = array('meal_plan_id' => '30');
$html = get_current_mealplan();
dpt_assert(strpos($html, 'No meal plan assigned yet.') !== false, 'legacy meta pointing at a non-meal-plan post is ignored');
dpt_assert(!isset($GLOBALS['dpt_meta'][7]['meal_plan']), 'non-meal-plan legacy ids are not migrated');

echo "\nBulk assign cannot wipe plans\n";
dpt_reset_state();
$GLOBALS['dpt_meta'][4] = array('meal_plan' => '21');
$GLOBALS['dpt_meta'][5] = array('meal_plan' => '21');
dpt_post(21, 'meal_plan', 'publish', 'New Plan');
$_POST = array(
    'dpt_bulk_assign_meal_plan' => 'Assign to Selected Users',
    'bulk_meal_plan' => '',
    'selected_users' => array('4', '5'),
);
$_REQUEST = $_POST;
$GLOBALS['dpt_meta_writes'] = array();
ob_start();
dpt_admin_progress_all();
$bulk_html = ob_get_clean();
dpt_assert(strpos($bulk_html, 'No assignments were changed.') !== false, 'blank bulk selection explains that nothing changed');
dpt_assert($GLOBALS['dpt_meta_writes'] === array(), 'blank bulk selection does not write meal_plan meta');
dpt_assert(($GLOBALS['dpt_meta'][4]['meal_plan'] ?? null) == 21, 'patient 4 keeps the existing meal plan');
dpt_assert(($GLOBALS['dpt_meta'][5]['meal_plan'] ?? null) == 21, 'patient 5 keeps the existing meal plan');

dpt_reset_state();
dpt_post(9, 'meal_plan', 'draft', 'Draft Plan');
$_POST = array(
    'dpt_bulk_assign_meal_plan' => 'Assign to Selected Users',
    'bulk_meal_plan' => '9',
    'selected_users' => array('4'),
);
$_REQUEST = $_POST;
$GLOBALS['dpt_meta_writes'] = array();
ob_start();
dpt_admin_progress_all();
ob_end_clean();
dpt_assert($GLOBALS['dpt_meta_writes'] === array(), 'a draft meal plan is not assigned in bulk');

dpt_reset_state();
dpt_post(9, 'meal_plan', 'publish', 'Keto');
$_POST = array(
    'dpt_bulk_assign_meal_plan' => 'Assign to Selected Users',
    'bulk_meal_plan' => '9',
    'selected_users' => array('4', '5'),
);
$_REQUEST = $_POST;
$GLOBALS['dpt_meta_writes'] = array();
ob_start();
dpt_admin_progress_all();
ob_end_clean();
$assigned = array();
foreach ($GLOBALS['dpt_meta_writes'] as $write) {
    if ($write[1] === 'meal_plan') {
        $assigned[$write[0]] = $write[2];
    }
}
dpt_assert($assigned === array(4 => 9, 5 => 9), 'a published meal plan is assigned to each selected user');

echo "\nSingle assign page rejects an empty plan\n";
dpt_reset_state();
$GLOBALS['dpt_meta'][4] = array('meal_plan' => '21');
dpt_post(21, 'meal_plan', 'publish', 'New Plan');
$_POST = array(
    'assign_mealplan' => 'Assign Meal Plan',
    'user_id' => '4',
    'meal_plan_id' => '0',
);
$_REQUEST = $_POST;
$GLOBALS['dpt_meta_writes'] = array();
ob_start();
dpt_admin_assign_mealplans();
$assign_html = ob_get_clean();
dpt_assert(strpos($assign_html, 'No assignment was changed.') !== false, 'assign page rejects a missing meal plan');
dpt_assert($GLOBALS['dpt_meta_writes'] === array(), 'assign page does not wipe meal_plan when the plan id is empty');
dpt_assert(($GLOBALS['dpt_meta'][4]['meal_plan'] ?? null) == 21, 'existing assignment survives an empty assign submission');

if ($failures > 0) {
    echo "\n$failures assertion(s) failed.\n";
    exit(1);
}

echo "\nAll assertions passed.\n";
exit(0);

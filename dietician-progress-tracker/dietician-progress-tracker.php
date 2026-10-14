<?php
/*
Plugin Name: Dietician Progress Tracker
Description: Allows users to log and track their weight loss progress with multiple metrics. Includes form + multi-line chart display.
Author: Mightyweb Pty Ltd
Version: 1.3.4
License: GPL-3.0+
Text Domain: dietician-progress-tracker
*/

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//  Create DB Table on Activation
register_activation_hook( __FILE__, 'dpt_create_table' );
function dpt_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_progress';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        date_logged datetime NOT NULL,
        weight float DEFAULT NULL,
        waist float DEFAULT NULL,
        bmi float DEFAULT NULL,
        body_fat float DEFAULT NULL,
        calories int DEFAULT NULL,
        hip_cm float DEFAULT NULL,
        chest_cm float DEFAULT NULL,
        left_bicep_cm float DEFAULT NULL,
        right_bicep_cm float DEFAULT NULL,
        left_thigh_cm float DEFAULT NULL,
        right_thigh_cm float DEFAULT NULL,
        notes text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}



// Enqueue BMI auto-calc in both front-end and admin
add_action('admin_enqueue_scripts', 'dpt_enqueue_bmi_admin_script');
function dpt_enqueue_bmi_admin_script($hook) {
    // Only load on your plugin's admin page (optional optimization)
    if (strpos($hook, 'dpt-user-progress') === false && strpos($hook, 'dpt-admin-progress') === false) {
        return;
    }

    wp_enqueue_script('dpt-bmi-auto', plugin_dir_url(__FILE__) . 'js/dpt-bmi-auto.js', ['jquery'], '1.0', true);
}



// Enqueue BMI auto-calc script
add_action('wp_enqueue_scripts', 'dpt_enqueue_bmi_script');
function dpt_enqueue_bmi_script() {
    wp_enqueue_script('dpt-bmi-auto', plugin_dir_url(__FILE__) . 'js/dpt-bmi-auto.js', ['jquery'], '1.0', true);
}

//  Shortcode: User Progress Form
add_shortcode( 'user_progress_form', 'dpt_progress_form' );
function dpt_progress_form() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to log your progress.</p>';
    }

    $output = '';
    if ( isset($_POST['dpt_submit']) ) {
        $progress_nonce = $_POST['dpt_progress_nonce'] ?? '';
        if ( ! is_string( $progress_nonce ) || ! wp_verify_nonce( wp_unslash( $progress_nonce ), 'dpt_save_progress' ) ) {
            $output .= '<p style="color:red;">Your session expired. Please reload the page and try again.</p>';
        } else {
            global $wpdb;
            $table = $wpdb->prefix . 'user_progress';

            $wpdb->insert( $table, [
                'user_id'       => get_current_user_id(),
                'date_logged'   => current_time('mysql'),
                'weight'        => dpt_posted_float( 'weight' ),
                'waist'         => dpt_posted_float( 'waist' ),
                'hip_cm'        => dpt_posted_float( 'hip_cm' ),
                'chest_cm'      => dpt_posted_float( 'chest_cm' ),
                'left_bicep_cm' => dpt_posted_float( 'left_bicep_cm' ),
                'right_bicep_cm'=> dpt_posted_float( 'right_bicep_cm' ),
                'left_thigh_cm' => dpt_posted_float( 'left_thigh_cm' ),
                'right_thigh_cm'=> dpt_posted_float( 'right_thigh_cm' ),
                'body_fat'      => dpt_posted_float( 'body_fat' ),
                'bmi'           => dpt_posted_float( 'bmi' ),
                'notes'         => dpt_posted_textarea( 'notes' ),
            ] );
            $output .= '<p style="color:green;">Progress saved!</p>';
        }
    }
    
    $user_id = get_current_user_id();
    $height_cm = get_user_meta($user_id, 'height', true);
    if ( ! is_scalar( $height_cm ) ) {
        $height_cm = '';
    }
    
    $output .= '

    <form method="post">
        ' . wp_nonce_field( 'dpt_save_progress', 'dpt_progress_nonce', true, false ) . '
        <label>Weight (kg):</label><br>
        <input type="number" step="0.01" name="weight" id="weight" required><br><br>
        <input type="number" id="height_cm" name="height_cm" value="' . esc_attr( $height_cm ) . '" readonly hidden>
    
        <label>Waist (cm):</label><br>
        <input type="number" step="0.01" name="waist"><br><br>
    
        <label>Hip (cm):</label><br>
        <input type="number" step="0.01" name="hip_cm"><br><br>
    
        <label>Chest (cm):</label><br>
        <input type="number" step="0.01" name="chest_cm"><br><br>
    
        <label>Left Bicep (cm):</label><br>
        <input type="number" step="0.01" name="left_bicep_cm"><br><br>
    
        <label>Right Bicep (cm):</label><br>
        <input type="number" step="0.01" name="right_bicep_cm"><br><br>
    
        <label>Left Thigh (cm):</label><br>
        <input type="number" step="0.01" name="left_thigh_cm"><br><br>
    
        <label>Right Thigh (cm):</label><br>
        <input type="number" step="0.01" name="right_thigh_cm"><br><br>
    
        <label>Body Fat (%):</label><br>
        <input type="number" step="0.01" name="body_fat"><br><br>
        
        <label>BMI (eg 26.1):</label><br>
        <input type="number" step="0.01" name="bmi" id="bmi"><br><br>
        
        <label>Notes:</label><br>
        <textarea name="notes"></textarea><br><br>
    
        <input type="submit" name="dpt_submit" value="Save Progress">
    </form>';
    

    return $output;
}

// Shortcode: Display current meal plan
add_shortcode('current_meal_plan', 'get_current_mealplan');
function get_current_mealplan() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to view your meal plan.</p>';
    }

    $user_id = get_current_user_id();
    $plan_id = get_user_meta($user_id, 'meal_plan', true);

    if ( ! $plan_id ) {
        return '<p>No meal plan assigned yet.</p>';
    }

    $plan = get_post($plan_id);
    if ( ! $plan || $plan->post_type !== 'meal_plan' || $plan->post_status !== 'publish' ) {
        return '<p>Meal plan not found.</p>';
    }

    return '<div class="meal-plan"><h3>' . esc_html($plan->post_title) . '</h3><div>' . wp_kses_post( wpautop( $plan->post_content ) ) . '</div></div>';
}



//Shortcode: Display Latest Progress Entry (Current Data)
add_shortcode( 'user_progress_current', 'dpt_progress_current' );
function dpt_progress_current() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to view your progress.</p>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'user_progress';
    $user_id = get_current_user_id();

    // Get most recent entry
    $result = $wpdb->get_row( $wpdb->prepare(
        "SELECT date_logged, weight, waist, bmi, hip_cm, chest_cm, left_bicep_cm, right_bicep_cm, left_thigh_cm, right_thigh_cm, body_fat, notes 
         FROM $table 
         WHERE user_id = %d ORDER BY date_logged DESC LIMIT 1",
        $user_id
    ) );

    if ( ! $result ) {
        return '<p>No progress logged yet.</p>';
    }

    ob_start();
    ?>
    <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="8">
        <tr><th>Date</th><td><?php echo esc_html( date("M d, Y H:i", strtotime($result->date_logged)) ); ?></td></tr>
        <tr><th>Weight</th><td><?php echo esc_html($result->weight); ?> kg</td></tr>
        <tr><th>Waist</th><td><?php echo esc_html($result->waist); ?> cm</td></tr>
        <tr><th>Hip</th><td><?php echo esc_html($result->hip_cm); ?> cm</td></tr>
        <tr><th>Chest</th><td><?php echo esc_html($result->chest_cm); ?> cm</td></tr>
        <tr><th>Left Bicep</th><td><?php echo esc_html($result->left_bicep_cm); ?> cm</td></tr>
        <tr><th>Right Bicep</th><td><?php echo esc_html($result->right_bicep_cm); ?> cm</td></tr>
        <tr><th>Left Thigh</th><td><?php echo esc_html($result->left_thigh_cm); ?> cm</td></tr>
        <tr><th>Right Thigh</th><td><?php echo esc_html($result->right_thigh_cm); ?> cm</td></tr>
        <tr><th>Body Fat</th><td><?php echo esc_html($result->body_fat); ?> %</td></tr>
        <tr><th>BMI</th><td><?php echo esc_html($result->bmi); ?> </td></tr>        
        <tr><th>Notes</th><td><?php echo nl2br(esc_html($result->notes)); ?></td></tr>
    </table>
    <?php
    return ob_get_clean();
}


// Shortcode: Display Progress Charts (one per metric)
add_shortcode( 'user_progress_chart', 'dpt_progress_chart' );
function dpt_progress_chart() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to view your progress.</p>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'user_progress';
    $user_id = get_current_user_id();

    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT date_logged, weight, waist, body_fat, bmi 
         FROM $table 
         WHERE user_id = %d ORDER BY date_logged ASC",
        $user_id
    ) );

    if ( ! $results ) {
        return '<p>No progress logged yet.</p>';
    }

    $dates     = [];
    $weights   = [];
    $waists    = [];
    $bodyfats  = [];
    $bmi       = [];

    foreach ( $results as $row ) {
        $dates[]    = date("M d", strtotime($row->date_logged));
        $weights[]  = $row->weight;
        $waists[]   = $row->waist;
        $bodyfats[] = $row->body_fat;
        $bmi[]      = $row->bmi;
        
    }

    // Unique IDs
    $uid = get_current_user_id();
    $charts = [
        'weight'   => ['label' => 'Weight (kg)',   'data' => $weights,  'color' => 'blue'],
        'waist'    => ['label' => 'Waist (cm)',    'data' => $waists,   'color' => 'green'],
        'bodyfat'  => ['label' => 'Body Fat (%)',  'data' => $bodyfats, 'color' => 'red'],
        'bmi'      => ['label' => 'BMI',          'data' => $bmi,      'color' => 'orange'],
    ];

    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" integrity="sha384-Sse/HDqcypGpyTDpvZOJNnG0TT3feGQUkF9H+mnRvic+LjR+K1NhTt8f51KIQ3v3" crossorigin="anonymous"></script>
    <?php foreach ( $charts as $key => $chart ) : 
        $chart_id = "dptChart_{$key}_{$uid}";
    ?>
        <h4><?php echo esc_html($chart['label']); ?></h4>
        <canvas id="<?php echo esc_attr($chart_id); ?>" height='120' ></canvas>
        <script>
        new Chart(document.getElementById('<?php echo $chart_id; ?>').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: '<?php echo esc_html($chart['label']); ?>',
                    data: <?php echo json_encode($chart['data']); ?>,
                    borderColor: '<?php echo $chart['color']; ?>',
                    backgroundColor: 'rgba(0,0,0,0)',
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: false }
                }
            }
        });
        </script>
    <?php endforeach; ?>
    <?php
    return ob_get_clean();
}

//  Admin Menu for Dietician
add_action('admin_menu', 'dpt_admin_menu');
function dpt_admin_menu() {
    add_menu_page(
        'Dietician Tracker',
        'Dietician Tracker',
        'manage_options',
        'dietician-tracker',
        'dpt_admin_dashboard',
        'dashicons-heart',
        6
    );

    add_submenu_page(
        'dietician-tracker',
        'All Progress Entries',
        'All Progress',
        'manage_options',
        'dietician-tracker-progress',
        'dpt_admin_progress_all'
    );
    add_submenu_page(
        null, // hidden from menu
        'User Progress',
        'User Progress',
        'manage_options',
        'dpt-user-progress',
        'dpt_admin_progress_single'
    );

    add_submenu_page(
        'dietician-tracker',
        'Assign Meal Plan',
        'Assign Meal Plan',
        'manage_options',
        'dietician-tracker-assign-mealplans',
        'dpt_admin_assign_mealplans'
);

}


/**
 * Published meal plan ID, or 0 when the value cannot be assigned.
 */
function dpt_get_valid_meal_plan_id( $plan_id ) {
    if ( is_array( $plan_id ) ) {
        return 0;
    }

    $plan_id = absint( $plan_id );
    if ( ! $plan_id ) {
        return 0;
    }

    $plan = get_post( $plan_id );
    if ( ! $plan || $plan->post_type !== 'meal_plan' || $plan->post_status !== 'publish' ) {
        return 0;
    }

    return $plan_id;
}

/**
 * Numeric value from the current request, or 0 when the field is missing or not scalar.
 */
function dpt_posted_float( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
        return 0.0;
    }

    return (float) wp_unslash( $_POST[ $key ] );
}

/**
 * Textarea value from the current request.
 */
function dpt_posted_textarea( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
        return '';
    }

    return sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
}

function dpt_admin_dashboard() {
    echo '<div class="wrap"><h1>Dietician Progress Tracker</h1>';
    echo '<p>Welcome! Use the menu to view user progress or manage meal plans.</p>';
    echo '</div>';
}

function dpt_admin_progress_all() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to view user progress.' );
    }

    echo '<div class="wrap"><h1>All User Progress</h1>';

    global $wpdb;
    $progress_table = $wpdb->prefix . 'user_progress';
    $users = get_users();

    if ( isset( $_POST['dpt_bulk_assign'] ) ) {
        check_admin_referer( 'dpt_bulk_assign_meal_plan', 'dpt_bulk_nonce' );

        $new_meal_plan = dpt_get_valid_meal_plan_id( wp_unslash( $_POST['bulk_meal_plan'] ?? 0 ) );
        $selected_users = $_POST['selected_users'] ?? [];

        if ( ! $new_meal_plan ) {
            echo '<div class="error"><p>Please select a published meal plan.</p></div>';
        } elseif ( ! is_array( $selected_users ) || empty( $selected_users ) ) {
            echo '<div class="error"><p>Please select at least one user.</p></div>';
        } else {
            foreach ( $selected_users as $selected_user_id ) {
                $selected_user_id = absint( $selected_user_id );
                if ( $selected_user_id && get_userdata( $selected_user_id ) ) {
                    update_user_meta( $selected_user_id, 'meal_plan', $new_meal_plan );
                }
            }
            echo '<div class="updated"><p>Meal plan assigned to selected users.</p></div>';
        }
    }

    // ðŸ”¹ Get available meal plans
    $plans = get_posts([
        'post_type'   => 'meal_plan',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC'
    ]);

    // Bulk action form
    echo '<form method="post">';
    wp_nonce_field( 'dpt_bulk_assign_meal_plan', 'dpt_bulk_nonce' );
    echo '<div style="margin-bottom:10px;">';
    echo '<select name="bulk_meal_plan">';
    echo '<option value="">-- Select Meal Plan --</option>';
    foreach ($plans as $plan) {
        echo '<option value="' . esc_attr($plan->ID) . '">' . esc_html($plan->post_title) . '</option>';
    }
    echo '</select> ';
    echo '<input type="submit" name="dpt_bulk_assign" class="button button-primary" value="Assign to Selected Users">';
    echo '</div>';

    // Table
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
            <th><input type="checkbox" id="select_all_users"></th>
            <th>User</th>
            <th>Current Meal Plan</th>
            <th>Last Progress Entry</th>
          </tr></thead><tbody>';

    foreach ($users as $user) {
        $user_id = $user->ID;

        // Current meal plan
        $meal_plan_id = get_user_meta($user_id, 'meal_plan', true);
        $meal_plan_title = '';
        if ($meal_plan_id) {
            $plan_post = get_post($meal_plan_id);
            $meal_plan_title = $plan_post ? esc_html($plan_post->post_title) : '<em>Not found</em>';
        } else {
            $meal_plan_title = '<em>None assigned</em>';
        }

        // Last progress entry
        $last_progress = $wpdb->get_var(
            $wpdb->prepare("SELECT MAX(date_logged) FROM $progress_table WHERE user_id = %d", $user_id)
        );
        $last_progress_display = $last_progress ? date("M d, Y H:i", strtotime($last_progress)) : '<em>No entries</em>';

        echo '<tr>
                <td><input type="checkbox" name="selected_users[]" value="' . esc_attr($user_id) . '"></td>
                <td><a href="' . admin_url('admin.php?page=dpt-user-progress&user_id=' . $user_id) . '">' . esc_html($user->display_name) . '</a></td>
                <td>' . $meal_plan_title . '</td>
                <td>' . $last_progress_display . '</td>
              </tr>';
    }

    echo '</tbody></table>';
    echo '</form>';

    // JS: Select all
    echo '<script>
        document.getElementById("select_all_users").addEventListener("change", function(e) {
            var checkboxes = document.querySelectorAll("input[name=\'selected_users[]\']");
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = e.target.checked;
            }
        });
    </script>';

    echo '</div>';
}



function dpt_admin_progress_single() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to view user progress.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'user_progress';

    $user_id = ( isset( $_GET['user_id'] ) && is_scalar( $_GET['user_id'] ) ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
    $user = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user ) {
        echo '<p>No user selected.</p>';
        return;
    }

    if ( isset( $_POST['update_meal_plan'] ) ) {
        check_admin_referer( 'dpt_update_meal_plan_' . $user_id, 'dpt_meal_plan_nonce' );

        $new_meal_plan = dpt_get_valid_meal_plan_id( wp_unslash( $_POST['new_meal_plan'] ?? 0 ) );
        if ( ! $new_meal_plan ) {
            wp_die( 'Please select a published meal plan.' );
        }

        update_user_meta( $user_id, 'meal_plan', $new_meal_plan );

        wp_safe_redirect( add_query_arg(
            [
                'page' => 'dpt-user-progress',
                'user_id' => $user_id,
                'meal_plan_updated' => '1',
            ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    echo '<div class="wrap"><h1>Progress for ' . esc_html( $user->display_name ) . '</h1>';

    if ( isset( $_POST['delete_entry'] ) ) {
        $entry_id = is_scalar( $_POST['delete_entry'] ) ? absint( wp_unslash( $_POST['delete_entry'] ) ) : 0;
        check_admin_referer( 'dpt_delete_progress_' . $entry_id, 'dpt_delete_nonce' );
        if ( $entry_id ) {
            $wpdb->delete( $table, [ 'id' => $entry_id, 'user_id' => $user_id ] );
            echo '<div class="updated"><p>Entry deleted.</p></div>';
        }
    }

    if ( isset( $_POST['update_progress'] ) ) {
        $entry_id = ( isset( $_POST['entry_id'] ) && is_scalar( $_POST['entry_id'] ) ) ? absint( wp_unslash( $_POST['entry_id'] ) ) : 0;
        check_admin_referer( 'dpt_update_progress_' . $entry_id, 'dpt_update_nonce' );
        $wpdb->update( $table, [
            'weight'        => dpt_posted_float( 'weight' ),
            'waist'         => dpt_posted_float( 'waist' ),
            'hip_cm'        => dpt_posted_float( 'hip_cm' ),
            'chest_cm'      => dpt_posted_float( 'chest_cm' ),
            'left_bicep_cm' => dpt_posted_float( 'left_bicep_cm' ),
            'right_bicep_cm'=> dpt_posted_float( 'right_bicep_cm' ),
            'left_thigh_cm' => dpt_posted_float( 'left_thigh_cm' ),
            'right_thigh_cm'=> dpt_posted_float( 'right_thigh_cm' ),
            'body_fat'      => dpt_posted_float( 'body_fat' ),
            'bmi'           => dpt_posted_float( 'bmi' ),
            'notes'         => dpt_posted_textarea( 'notes' ),
        ], [ 'id' => $entry_id, 'user_id' => $user_id ] );
        echo '<div class="updated"><p>Progress entry updated.</p></div>';
    }

    if ( isset( $_POST['add_progress'] ) ) {
        check_admin_referer( 'dpt_add_progress_' . $user_id, 'dpt_add_nonce' );

        $logged_date = '';
        if ( isset( $_POST['date_logged'] ) && is_string( $_POST['date_logged'] ) ) {
            $logged_date = sanitize_text_field( wp_unslash( $_POST['date_logged'] ) );
        }
        $date_logged = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $logged_date )
            ? $logged_date . ' ' . date( 'H:i:s' )
            : current_time( 'mysql' );

        $wpdb->insert( $table, [
            'user_id'       => $user_id,
            'date_logged'   => $date_logged,
            'weight'        => dpt_posted_float( 'weight' ),
            'waist'         => dpt_posted_float( 'waist' ),
            'hip_cm'        => dpt_posted_float( 'hip_cm' ),
            'chest_cm'      => dpt_posted_float( 'chest_cm' ),
            'left_bicep_cm' => dpt_posted_float( 'left_bicep_cm' ),
            'right_bicep_cm'=> dpt_posted_float( 'right_bicep_cm' ),
            'left_thigh_cm' => dpt_posted_float( 'left_thigh_cm' ),
            'right_thigh_cm'=> dpt_posted_float( 'right_thigh_cm' ),
            'body_fat'      => dpt_posted_float( 'body_fat' ),
            'bmi'           => dpt_posted_float( 'bmi' ),
            'notes'         => dpt_posted_textarea( 'notes' ),
        ] );
        echo '<div class="updated"><p>New progress entry added.</p></div>';
    }

    // âœ… Get entries
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY date_logged DESC",
        $user_id
    ));

    // âœ… Show progress history
    echo '<h2>Progress History</h2>';
    if ($results) {
        echo '<table class="widefat striped"><thead><tr>
            <th>Date</th><th>Weight</th><th>Waist</th><th>Hip</th><th>Chest</th>
            <th>Left Bicep</th><th>Right Bicep</th><th>Left Thigh</th><th>Right Thigh</th>
            <th>Body Fat</th><th>BMI</th><th>Notes</th><th>Actions</th>
        </tr></thead><tbody>';

        foreach ($results as $row) {
            // Normal view row
            echo '<tr id="row-view-' . $row->id . '">
                <td>' . esc_html($row->date_logged) . '</td>
                <td>' . esc_html($row->weight) . '</td>
                <td>' . esc_html($row->waist) . '</td>
                <td>' . esc_html($row->hip_cm) . '</td>
                <td>' . esc_html($row->chest_cm) . '</td>
                <td>' . esc_html($row->left_bicep_cm) . '</td>
                <td>' . esc_html($row->right_bicep_cm) . '</td>
                <td>' . esc_html($row->left_thigh_cm) . '</td>
                <td>' . esc_html($row->right_thigh_cm) . '</td>
                <td>' . esc_html($row->body_fat) . '</td>
                <td>' . esc_html($row->bmi) . '</td>                
                <td>' . esc_html($row->notes) . '</td>
                <td>
                    <a href="javascript:void(0);" class="button" onclick="toggleEditRow(' . intval( $row->id ) . ')">Edit</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this entry?\');">
                        ' . wp_nonce_field( 'dpt_delete_progress_' . intval( $row->id ), 'dpt_delete_nonce', true, false ) . '
                        <input type="hidden" name="delete_entry" value="' . intval( $row->id ) . '">
                        <button type="submit" class="button button-secondary">Delete</button>
                    </form>
                </td>
            </tr>';

            // Edit fields live outside the form element because a form cannot wrap table cells.
            // The form attribute keeps Save, the nonce, and the inputs in one submission.
            $edit_form_id = 'dpt-edit-' . intval( $row->id );
            echo '<tr id="row-edit-' . intval( $row->id ) . '" style="display:none;background:#f9f9f9;">
                <td>' . esc_html( $row->date_logged ) . '</td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="weight" value="' . esc_attr( $row->weight ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="waist" value="' . esc_attr( $row->waist ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="hip_cm" value="' . esc_attr( $row->hip_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="chest_cm" value="' . esc_attr( $row->chest_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="left_bicep_cm" value="' . esc_attr( $row->left_bicep_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="right_bicep_cm" value="' . esc_attr( $row->right_bicep_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="left_thigh_cm" value="' . esc_attr( $row->left_thigh_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="right_thigh_cm" value="' . esc_attr( $row->right_thigh_cm ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="body_fat" value="' . esc_attr( $row->body_fat ) . '" /></td>
                <td><input form="' . esc_attr( $edit_form_id ) . '" type="number" step="0.01" name="bmi" value="' . esc_attr( $row->bmi ) . '" /></td>
                <td><textarea form="' . esc_attr( $edit_form_id ) . '" name="notes">' . esc_textarea( $row->notes ) . '</textarea></td>
                <td>
                    <form id="' . esc_attr( $edit_form_id ) . '" method="post">
                    ' . wp_nonce_field( 'dpt_update_progress_' . intval( $row->id ), 'dpt_update_nonce', true, false ) . '
                    <input type="hidden" name="entry_id" value="' . intval( $row->id ) . '">
                    <input type="submit" name="update_progress" class="button button-primary" value="Save">
                    </form>
                    <a href="javascript:void(0);" class="button" onclick="toggleEditRow(' . intval( $row->id ) . ')">Cancel</a>
                </td>
            </tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No progress logged yet.</p>';
    }

?>


<?php
//  Current Meal Plan + Change Option
$current_meal_plan = get_user_meta($user_id, 'meal_plan', true);
?>
<h2>Meal Plan</h2>
<?php
$current_meal_plan = get_user_meta($user_id, 'meal_plan', true);

// Get published meal plans
$plans = get_posts([
    'post_type'   => 'meal_plan',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby'     => 'title',
    'order'       => 'ASC'
]);
?>
<p><strong>Current Meal Plan:</strong> 
    <?php
    if ($current_meal_plan) {
        $plan_post = get_post($current_meal_plan);
        echo $plan_post ? esc_html($plan_post->post_title) : '<em>Plan not found</em>';
    } else {
        echo '<em>No meal plan assigned yet.</em>';
    }
    ?>
</p>

<form method="post">
    <?php wp_nonce_field('dpt_update_meal_plan_' . $user_id, 'dpt_meal_plan_nonce'); ?>
    <select name="new_meal_plan" style="width:300px;">
        <option value="">-- Select Meal Plan --</option>
        <?php foreach ($plans as $plan) : ?>
            <option value="<?php echo esc_attr($plan->ID); ?>" <?php selected($current_meal_plan, $plan->ID); ?>>
                <?php echo esc_html($plan->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p><input type="submit" name="update_meal_plan" class="button button-secondary" value="Update Meal Plan"></p>
</form>


<?php
if (isset($_GET['meal_plan_updated'])
    && '1' === sanitize_text_field(wp_unslash($_GET['meal_plan_updated']))) {
    echo '<div class="updated"><p>Meal plan updated successfully.</p></div>';
}
?>

<?php     //Add new entry form below 

$height = get_user_meta( $user_id, 'height', true );
if ( ! is_scalar( $height ) ) {
    $height = '';
}

?>

<h2>Add New Progress Entry</h2>
<form method="post">
    <?php wp_nonce_field( 'dpt_add_progress_' . $user_id, 'dpt_add_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th>Date</th>
            <td>
                <input type="date" name="date_logged" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                <p class="description">Select the date for this entry (defaults to today).</p>
            </td>
        </tr>
        <tr><th>Height (cm)</th><td><input type="number" id="height_cm" name="height_cm" value="<?php echo esc_attr( $height ); ?>" readonly></td></tr>
        <tr><th>Weight (kg)</th><td><input type="number" step="0.1" name="weight" id="weight"></td></tr>
        <tr><th>Waist (cm)</th><td><input type="number" step="0.1" name="waist"></td></tr>
        <tr><th>Hip (cm)</th><td><input type="number" step="0.1" name="hip_cm"></td></tr>
        <tr><th>Chest (cm)</th><td><input type="number" step="0.1" name="chest_cm"></td></tr>
        <tr><th>Left Bicep (cm)</th><td><input type="number" step="0.1" name="left_bicep_cm"></td></tr>
        <tr><th>Right Bicep (cm)</th><td><input type="number" step="0.1" name="right_bicep_cm"></td></tr>
        <tr><th>Left Thigh (cm)</th><td><input type="number" step="0.1" name="left_thigh_cm"></td></tr>
        <tr><th>Right Thigh (cm)</th><td><input type="number" step="0.1" name="right_thigh_cm"></td></tr>
        <tr><th>Body Fat (%)</th><td><input type="number" step="0.1" name="body_fat"></td></tr>
        <tr><th>BMI</th><td><input type="number" step="0.1" name="bmi" id="bmi"></td></tr>        
        <tr><th>Recent Notes</th><td><textarea name="notes"></textarea></td></tr>
    </table>
    <p><input type="submit" name="add_progress" class="button button-primary" value="Add Progress"></p>
</form>



    <script>
    function toggleEditRow(id) {
        var viewRow = document.getElementById('row-view-' + id);
        var editRow = document.getElementById('row-edit-' + id);
        if (viewRow.style.display === 'none') {
            viewRow.style.display = '';
            editRow.style.display = 'none';
        } else {
            viewRow.style.display = 'none';
            editRow.style.display = '';
        }
    }
    </script>
    <?php

    echo '</div>';
}




// ðŸ”¹ Register Meal Plan Post Type
add_action('init', 'dpt_register_mealplan_cpt');
function dpt_register_mealplan_cpt() {
    $labels = [
        'name'               => 'Meal Plans',
        'singular_name'      => 'Meal Plan',
        'add_new'            => 'Add New Meal Plan',
        'add_new_item'       => 'Add New Meal Plan',
        'edit_item'          => 'Edit Meal Plan',
        'new_item'           => 'New Meal Plan',
        'all_items'          => 'All Meal Plans',
        'view_item'          => 'View Meal Plan',
        'search_items'       => 'Search Meal Plans',
        'not_found'          => 'No meal plans found',
        'not_found_in_trash' => 'No meal plans found in Trash',
        'menu_name'          => 'Meal Plans'
    ];

    $args = [
        'labels'        => $labels,
        'public'        => false,   // not visible on frontend
        'show_ui'       => true,    // show in admin menu
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-carrot',
        'supports'      => ['title', 'editor'],
        'capability_type'=> 'post'
    ];

    register_post_type('meal_plan', $args);
}


function dpt_admin_assign_mealplans() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to assign meal plans.' );
    }

    if ( isset( $_POST['assign_mealplan'] ) ) {
        check_admin_referer( 'dpt_assign_mealplan', 'dpt_assign_nonce' );

        $assign_user_id = ( isset( $_POST['user_id'] ) && is_scalar( $_POST['user_id'] ) ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
        $plan_id = dpt_get_valid_meal_plan_id( wp_unslash( $_POST['meal_plan_id'] ?? 0 ) );

        if ( ! $assign_user_id || ! get_userdata( $assign_user_id ) || ! $plan_id ) {
            echo '<div class="error"><p>Please select a valid user and a published meal plan.</p></div>';
        } else {
            update_user_meta( $assign_user_id, 'meal_plan', $plan_id );
            echo '<div class="updated"><p>Meal plan assigned!</p></div>';
        }
    }

    $users = get_users(['role__in' => ['subscriber', 'pmpro_member', 'administrator']]);
    $mealplans = get_posts([
        'post_type'   => 'meal_plan',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ]);

    echo '<div class="wrap"><h1>Assign Meal Plans</h1>';
    echo '<form method="post">';
    wp_nonce_field( 'dpt_assign_mealplan', 'dpt_assign_nonce' );
    
    echo '<label>User:</label><br>';
    echo '<select name="user_id">';
    foreach ( $users as $user ) {
        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
    }
    echo '</select><br><br>';

    echo '<label>Meal Plan:</label><br>';
    echo '<select name="meal_plan_id">';
    foreach ( $mealplans as $plan ) {
        echo '<option value="' . esc_attr($plan->ID) . '">' . esc_html($plan->post_title) . '</option>';
    }
    echo '</select><br><br>';

    echo '<input type="submit" class="button-primary" name="assign_mealplan" value="Assign Meal Plan">';
    echo '</form></div>';
}

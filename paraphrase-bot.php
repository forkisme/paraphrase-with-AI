<?php
/*
Plugin Name:  Paraphrase with AI
Plugin URI: paraphrase-bot.com
Description: Paraphrase your text and get multiple paraphrased sentences per sentence using our API.
Version: 0.1.4
Author: Michael Mwanzia
License: GPLv2 or later
Text Domain: paraphrase-bot
*/

function paraphrase_bot_enqueue_modal_script() {
    // Only enqueue the script on Gutenberg editor pages
    $screen = get_current_screen();
    if ($screen && $screen->is_block_editor) {
        wp_enqueue_script('paraphrase-bot-modal', plugin_dir_url(__FILE__) . 'modal-notice.js', array('wp-element'), '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'paraphrase_bot_enqueue_modal_script');

function paraphrase_bot_enqueue_scripts() {
    wp_enqueue_script('paraphrase-bot-script', plugin_dir_url(__FILE__) . 'js/paraphrase-bot.js', array('jquery'), '0.1.4', true);
    wp_localize_script('paraphrase-bot-script', 'paraphraseBot', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('paraphrase_bot_nonce_action')
    ));
}
add_action('admin_enqueue_scripts', 'paraphrase_bot_enqueue_scripts');

function paraphrase_bot_add_editor_button() {
    echo '<a href="#" id="paraphrase-bot-button" class="button">Paraphrase Selected Text</a>';
}
add_action('media_buttons', 'paraphrase_bot_add_editor_button');

function paraphrase_bot_ajax_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'paraphrase_bot_nonce_action')) {
        wp_send_json_error('Security check failed.');
        return;
    }

    if (isset($_POST['text'])) {
        $text = sanitize_text_field($_POST['text']);
        $word_count = str_word_count($text);
        $user_id = get_current_user_id();

        if (!paraphrase_bot_can_paraphrase($user_id, $word_count)) {
            wp_send_json_error('Word limit exceeded for the day. Upgrade to premium for unlimited paraphrasing.');
            return;
        }

        $paraphrased_text = paraphrase_text($text);

        if ($paraphrased_text) {
            // Update the word count for the user
            paraphrase_bot_update_word_count($user_id, $word_count);
            wp_send_json_success($paraphrased_text);
        } else {
            wp_send_json_error('Failed to paraphrase text.');
        }
    }
}
add_action('wp_ajax_paraphrase_text', 'paraphrase_bot_ajax_handler');

function paraphrase_text($text) {
    $proxy_url = 'https://paraphrase-ai.mick.co.ke/webhook/proxy.php'; 

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $proxy_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'text' => $text,
            'result_type' => 'single'
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err;
    } else {
        return $response;
    }
}

// Check if the user can paraphrase based on their plan and word count
function paraphrase_bot_can_paraphrase($user_id, $word_count) {
    $is_premium = get_user_meta($user_id, 'paraphrase_bot_premium', true);
    
    if ($is_premium) {
        return true; // Premium users have unlimited paraphrasing
    }
    
    $current_word_count = get_user_meta($user_id, 'paraphrase_bot_daily_word_count', true);
    if (!$current_word_count) {
        $current_word_count = 0;
    }

    return ($current_word_count + $word_count) <= 1000; // Free users can paraphrase up to 1000 words a day
}

// Update the word count for the user
function paraphrase_bot_update_word_count($user_id, $word_count) {
    $current_word_count = get_user_meta($user_id, 'paraphrase_bot_daily_word_count', true);
    
    if (!$current_word_count) {
        $current_word_count = 0;
    }

    update_user_meta($user_id, 'paraphrase_bot_daily_word_count', $current_word_count + $word_count);
}

// Cron job function to reset daily word counts
function paraphrase_bot_reset_daily_word_count() {
    $users = get_users(array(
        'meta_key' => 'paraphrase_bot_daily_word_count',
        'meta_compare' => 'EXISTS'
    ));

    foreach ($users as $user) {
        update_user_meta($user->ID, 'paraphrase_bot_daily_word_count', 0);
    }
}

// Schedule the cron job to reset daily word counts
if (!wp_next_scheduled('paraphrase_bot_reset_daily_word_count_event')) {
    wp_schedule_event(time(), 'daily', 'paraphrase_bot_reset_daily_word_count_event');
}

add_action('paraphrase_bot_reset_daily_word_count_event', 'paraphrase_bot_reset_daily_word_count');

function paraphrase_bot_menu() {
    add_menu_page(
        'Paraphrase Bot Settings', 
        'Paraphrase Bot', 
        'manage_options', 
        'paraphrase-bot', 
        'paraphrase_bot_settings_page', 
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'paraphrase_bot_menu');

function paraphrase_bot_settings_page() {
    // Check if the user has upgraded to premium
    $user_email = get_user_meta(get_current_user_id(), 'paraphrase_bot_premium', true);
    $upgrade_confirmed = false;

    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify the nonce
        if (isset($_POST['paraphrase_bot_confirm_email_nonce']) && wp_verify_nonce($_POST['paraphrase_bot_confirm_email_nonce'], 'paraphrase_bot_confirm_email_action')) {
            $submitted_email = sanitize_email($_POST['confirm_email']);

            // Check if this email exists in our webhook logs
            if ($submitted_email && paraphrase_bot_check_email_in_logs($submitted_email)) {
                // Update user meta to mark as premium
                update_user_meta(get_current_user_id(), 'paraphrase_bot_premium', $submitted_email);
                $user_email = $submitted_email;
                $upgrade_confirmed = true;
            } else {
                $upgrade_confirmed = false;
            }
        } else {
            // Nonce verification failed
            wp_die('Security check failed. Please try again.');
        }
    }

    ?>
    <div class="wrap" style="max-width: 800px; margin: 50px auto; background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
        <h1 style="font-size: 2.5em; color: #0073aa; margin-bottom: 1.5em; text-align: center;">Paraphrase Bot Settings</h1>
        
        <h2 class="nav-tab-wrapper" style="text-align: center;">
            <a href="#tab-plans" class="nav-tab nav-tab-active" style="font-size: 1.2em; padding: 10px 20px;">💳 Plans and Payments</a>
            <a href="#tab-how-to-use" class="nav-tab" style="font-size: 1.2em; padding: 10px 20px;">📘 How to Use</a>
        </h2>

        <div id="tab-plans" class="tab-content">
            <h2 style="font-size: 1.5em; color: #0073aa; margin-top: 1.5em;">Plans</h2>
            <p style="font-size: 1.1em; line-height: 1.6;">By default, users are on the free version, which allows users to paraphrase up to 1000 words a day. This resets each day.</p>
            <p style="font-size: 1.1em; line-height: 1.6;">The premium plan allows users to use the plugin with unlimited usage and costs $25 per month.</p>
            
            <?php if ($user_email) : ?>
                <p style="font-size: 1.1em; line-height: 1.6; margin-top: 2em;"><strong>You are already on premium mode. Your email: <?php echo esc_html($user_email); ?></strong></p>
                <p style="font-size: 1em; line-height: 1.6; color: #0073aa; margin-top: 10px;">Kindly note: You can manage your subscription status from your Gumroad official receipt of this subscription. Go to emails, check Gumroad receipt email, and click manage subscriptions.</p>
            <?php else : ?>
                <p style="font-size: 1.1em; line-height: 1.6; margin-top: 2em;"><strong>Upgrade to Premium</strong></p>
                <a href="https://michaelmick.gumroad.com/l/nkzdfw" class="button button-primary">Upgrade to Premium</a>
                <p style="font-size: 0.9em; line-height: 1.6; color: #666; margin-top: 10px;">Get unlimited paraphrasing and more advanced features with our premium plan.</p>

                <hr>

                <form method="post">
                    <?php wp_nonce_field('paraphrase_bot_confirm_email_action', 'paraphrase_bot_confirm_email_nonce'); ?>
                    <p style="font-size: 1.1em; line-height: 1.6; margin-top: 2em;"><strong>Confirm your email address if you've already paid:</strong></p>
                    <input type="email" name="confirm_email" required placeholder="Enter your email address" style="padding: 8px; width: 100%; max-width: 300px;">
                    <button type="submit" class="button button-secondary" style="margin-top: 10px;">Confirm Email</button>
                </form>

                <?php if ($upgrade_confirmed) : ?>
                    <p style="font-size: 1.1em; line-height: 1.6; color: green; margin-top: 10px;">Thank you for upgrading to premium! Your email: <?php echo esc_html($user_email); ?></p>
                    <p style="font-size: 1em; line-height: 1.6; color: #0073aa; margin-top: 10px;">Kindly note: You can manage your subscription status from your Gumroad official receipt of this subscription. Go to emails, check Gumroad receipt email, and click manage subscriptions.</p>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST') : ?>
                    <p style="font-size: 1.1em; line-height: 1.6; color: red; margin-top: 10px;">Email not found. Please make sure you used the correct email or contact support.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div id="tab-how-to-use" class="tab-content" style="display: none;">
            <h2 style="font-size: 1.5em; color: #0073aa; margin-top: 1.5em;">How to Use the Paraphrase Bot Plugin</h2>
            <p style="font-size: 1.1em; line-height: 1.6;">Using the Paraphrase Bot plugin is simple and straightforward. Follow the steps below to get started:</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">1. Install and Activate the Plugin</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">First, install the Paraphrase Bot plugin from the WordPress plugin directory or upload the plugin manually. Once installed, activate the plugin from the Plugins page.</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">2. Access the Paraphrase Bot</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">You can access the Paraphrase Bot functionality directly from the WordPress post editor. Look for the "Paraphrase Selected Text" button in the editor's toolbar.</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">3. Paraphrase Text</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">Highlight the text you want to paraphrase and click the "Paraphrase Selected Text" button. The plugin will automatically send the text to our paraphrasing API and return the paraphrased version, which will replace the highlighted text.</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">4. Manage Your Plan</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">To upgrade to the premium version for unlimited paraphrasing, go to the "Plans and Payments" tab in the Paraphrase Bot settings. Follow the instructions to upgrade and confirm your email after payment.</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">5. Confirming Premium Status</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">After upgrading, confirm your email address in the "Plans and Payments" tab to activate your premium status. Once confirmed, you will have unlimited access to the paraphrasing feature.</p>

            <h3 style="font-size: 1.2em; color: #0073aa; margin-top: 1em;">6. Checking Subscription Status</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">Your subscription status is managed automatically. If your subscription expires, you will be downgraded to the free version. You can check your current status in the Paraphrase Bot settings page.</p>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-tab').forEach(function(tab) {
                tab.addEventListener('click', function(event) {
                    event.preventDefault();
                    document.querySelectorAll('.nav-tab').forEach(function(t) { t.classList.remove('nav-tab-active'); });
                    document.querySelectorAll('.tab-content').forEach(function(content) { content.style.display = 'none'; });
                    tab.classList.add('nav-tab-active');
                    document.querySelector(tab.getAttribute('href')).style.display = 'block';
                });
            });
        });
    </script>
    <?php
}

// Function to check if the email exists in the webhook logs
function paraphrase_bot_check_email_in_logs($email) {
    // URL to your webhook log file
    $webhook_log_url = 'https://paraphrase-ai.mick.co.ke/webhook/webhook_data.log';

    // Fetch the contents of the log file from the URL
    $log_contents = file_get_contents($webhook_log_url);

    if ($log_contents !== false) {
        // Split the log file into individual entries
        $log_entries = explode("-----------------------", $log_contents);

        // Iterate through each entry to search for the email
        foreach ($log_entries as $entry) {
            if (strpos($entry, "Email: " . $email) !== false) {
                return true;
            }
        }
    }
    return false;
}

// Handle webhook for Gumroad payments
if (isset($_GET['gumroad_webhook'])) {
    paraphrase_bot_handle_gumroad_webhook();
}

function paraphrase_bot_handle_gumroad_webhook() {
    // Retrieve raw POST data
    $raw_post_data = file_get_contents('php://input');

    // Parse the received data
    parse_str($raw_post_data, $data);

    // Extract the email and other relevant details
    $email = $data['email'] ?? '';
    $recurrence = $data['recurrence'] ?? '';
    $sale_timestamp = $data['sale_timestamp'] ?? '';

    if ($email && $recurrence && $sale_timestamp) {
        // Calculate the expiration date based on the recurrence
        $expiration_date = paraphrase_bot_calculate_expiration($recurrence, $sale_timestamp);

        // Find the user by email
        $user = get_user_by('email', $email);
        if ($user) {
            // Update user meta to mark as premium and store the expiration date
            update_user_meta($user->ID, 'paraphrase_bot_premium', $email);
            update_user_meta($user->ID, 'paraphrase_bot_expiration', $expiration_date);
            error_log("User upgraded to premium: " . $email . " until " . $expiration_date); // Debugging line
        }
    }
}

function paraphrase_bot_calculate_expiration($recurrence, $sale_timestamp) {
    $date = new DateTime($sale_timestamp);

    switch ($recurrence) {
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'yearly':
            $date->modify('+1 year');
            break;
        // Add other cases if needed
    }

    return $date->format('Y-m-d H:i:s');
}

// Cron job function to check expiration dates
function paraphrase_bot_check_expiration() {
    $users = get_users(array(
        'meta_key' => 'paraphrase_bot_premium',
        'meta_compare' => 'EXISTS'
    ));

    $current_date = current_time('Y-m-d H:i:s');

    foreach ($users as $user) {
        $expiration_date = get_user_meta($user->ID, 'paraphrase_bot_expiration', true);

        if ($expiration_date && $current_date > $expiration_date) {
            // Downgrade user to free mode
            delete_user_meta($user->ID, 'paraphrase_bot_premium');
            delete_user_meta($user->ID, 'paraphrase_bot_expiration');
            error_log("User downgraded to free: " . $user->user_email);
        }
    }
}

// Schedule the cron job
if (!wp_next_scheduled('paraphrase_bot_check_expiration_event')) {
    wp_schedule_event(time(), 'daily', 'paraphrase_bot_check_expiration_event');
}

add_action('paraphrase_bot_check_expiration_event', 'paraphrase_bot_check_expiration');
?>
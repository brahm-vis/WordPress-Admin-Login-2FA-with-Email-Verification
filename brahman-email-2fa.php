<?php
/*
 * Plugin Name: Custom Email 2FA for WordPress by Brahman WebTech.
 * Plugin URI: https://bmwtech.in
 * Description: Custom Email 2FA for WordPress by Brahman WebTech.
 * Version: 1.0.14
 * Requires at least: 5.5
 * Requires PHP: 8.0
 * Author: Brahman WebTech
 * Author URI: https://bmwtech.in
 */
 

function brahman_2fa_enqueue_inline_script() {
    add_action('login_form', function() {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const storedCredentials = window.electron.getCredentials();
                if (storedCredentials.username && storedCredentials.password) {
                    document.getElementById('user_login').value = storedCredentials.username;
                    document.getElementById('user_pass').value = storedCredentials.password;
                }

                document.getElementById('loginform').addEventListener('submit', function() {
                    const username = document.getElementById('user_login').value;
                    const password = document.getElementById('user_pass').value;
                    window.electron.saveCredentials(username, password);
                });
            });
        </script>";
    });
}
add_action('init', 'brahman_2fa_enqueue_inline_script');

function brahman_2fa_send_otp_after_authentication($user, $username, $password) {
    if (is_wp_error($user)) {
        return $user;
    }

    if (isset($_POST['otp'])) {
        return $user;
    }

    $otp = rand(100000, 999999); // Generate a 6-digit OTP
    update_user_meta($user->ID, 'user_otp', $otp);

    // Send OTP to the user's email
    $to = 'your-email@provider.xxx';  //$user->email
    $subject = 'OTP for WordPress Login Authorisation';
    $message = "Below we attached your WordPress account login verification code. Copy the code and paste on site to login your account. <br> ".$otp."";
    
    
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail($to, $subject, $message, $headers);

    // Redirect to login page with OTP flag
    wp_redirect(add_query_arg('otp_sent', 'true', wp_login_url()));
    exit();
}
add_filter('authenticate', 'brahman_2fa_send_otp_after_authentication', 30, 3);

function brahman_2fa_login_form() {
    if (isset($_GET['otp_sent'])) {
        echo '
        <form name="otpform" id="otpform" action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post">
            <p>
                <label for="otp">One Time Password<br>
                <input type="text" name="otp" id="otp" class="input" value="" size="20"></label>
            </p>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Verify OTP">
            </p>
        </form>';
        

        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const storedCredentials = window.electron.getCredentials();
            if (storedCredentials.username && storedCredentials.password) {
                document.getElementById('user_login').value = storedCredentials.username;
                document.getElementById('user_pass').value = storedCredentials.password;
            }

            document.getElementById('loginform').addEventListener('submit', function() {
                const username = document.getElementById('user_login').value;
                const password = document.getElementById('user_pass').value;
                window.electron.saveCredentials(username, password);
            });
        });
    </script>";
    
        exit();
    }
}
add_action('login_form', 'brahman_2fa_login_form');

function brahman_2fa_validate_otp($user, $username, $password) {
    if (isset($_POST['otp'])) {
        $otp = $_POST['otp'];
        $saved_otp = get_user_meta($user->ID, 'user_otp', true);

        if ($otp !== $saved_otp) {
            return new WP_Error('incorrect_otp', ('<strong>ERROR</strong>: Incorrect OTP.'));
        }
        
        // If OTP is correct, remove the OTP from user meta
        delete_user_meta($user->ID, 'user_otp');
    } else {
        return new WP_Error('empty_otp', ('Please enter the OTP.'));
    }

    return $user;
}
add_filter('authenticate', 'brahman_2fa_validate_otp', 40, 3);

?>

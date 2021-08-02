<?php

require_once 'settings-page.php';

class WPPR_Protector {

    private $options;

    private $text_domain;

    private $lockdown_key;

    public function __construct($text_domain) {
        $this->text_domain = $text_domain;
        $this->options = get_option($text_domain.'-options', WordpressProtectorSettingsPage::GetDefaultOptions());
        $this->lockdown_key = $this->text_domain.'-'.$_SERVER['REMOTE_ADDR'];
    }

    public function Protect() {
        if( true == $this->options['hide-login-errors'] ) {
            $this->HideLoginErrors();
        }

        if( true == $this->options['hide-wp-version'] ) {
            $this->HideWpVersion();
        }

        if( true == $this->options['lockdown-login-enable'] ) {
            $this->LockdownLogin();
        }

        if( true == $this->options['recaptcha-enable'] ) {
            $this->reCAPTCHA();
        }
    }

    #region reCAPTCHA
    private function reCAPTCHA() {

        $text_domain = $this->text_domain;
        $ref = $this;

        add_action('login_enqueue_scripts', function() use($text_domain, $ref) {

            if( !$this->ValidateAuthCookie('reCAPTCHA') ) {

                echo '
                <style type="text/css">
                #login {
                    width: 352px !important;
                }
                </style>';

                $locale = get_locale();
                wp_enqueue_script($text_domain.'-google-recaptcha', 'https://www.google.com/recaptcha/enterprise.js?hl='.$locale, array());

                $site_key = $ref->options['recaptcha-site-key'];
                add_action('login_form', function() use($site_key) {
                    echo '<div class="g-recaptcha" style="width:304px;margin-bottom:20px" data-sitekey="'.$site_key.'"></div>';
                }, 10);
            }
        });

        add_filter('wp_authenticate_user', array($this, 'reCAPTCHAauth'), 100, 2);

        add_action('wp_login_failed', function($username, $error) use($ref) {
            $ref->DeleteAuthCookie('reCAPTCHA');
        }, 10, 2);
    }
    #endregion

    private function LockdownLogin() {
        add_action('wp_login_failed', array($this, 'CountFailedLogin'), 10, 2);
        add_filter('login_message', array($this, 'DisplayLoginInfo'), 10, 1);
        add_filter('authenticate', array($this, 'LockdownAuth'), 100, 3);

        $ref = $this;
        add_action('wp_login', function() use($ref) {
            $ref->DeleteFailedLoginData();
        }, 10); 
    }

    private function HideWpVersion() {
        add_filter('the_generator', '__return_null');
    }

    private function HideLoginErrors() {
        $text_domain = $this->text_domain;
        add_filter('login_errors', function($message) use($text_domain) {
            $message = '<strong>';
            $message .= __("Login failed", $text_domain);
            $message .= '</strong>';

            return $message;
        });
    }

    public function reCAPTCHAauth($user, $password) {

        $token = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

        $url = "https://recaptchaenterprise.googleapis.com/v1beta1/projects/".$this->options['recaptcha-project-id']."/assessments?key=".$this->options['recaptcha-api-key'];

        $body = array(
            'event' => array(
                'token' => $token,
                'siteKey' => $this->options['recaptcha-site-key'],
                'expectedAction' => 'LOGIN'
            )
        );

        $response = wp_remote_post($url, array(
            'headers'=> array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode($body),
            'data_format' => 'body'
        ));

        if(200 != $response['response']['code']) {

            $error_message = '<strong>';
            $error_message .= $response['response']['message'];
            $error_message .= '</strong>';

            return new WP_Error($this->text_domain.'-recaptcha-response', $error_message, $response);
        } 
        else {
            
            $body = json_decode($response['body']);
            
            if( $body->score < ( (float)$this->options['recaptcha-score'] || false === $body->tokenProperties->valid ) ) {

                $error_message = '<strong>';
                $error_message .= __('Recaptcha verification failed', $this->text_domain);
                $error_message .= '</strong>';

                return new WP_Error($this->text_domain.'-recaptcha', $error_message, $body);
            }
        }
        
        $this->SetAuthCookie($user->ID, 'reCAPTCHA', isset($_POST['rememberme']) );

        return $user;

    }

    private function DeleteAuthCookie($name) {
            $cookie_name = $this->text_domain . '-' .$name . '_' . COOKIEHASH;
            if( isset($_COOKIE[$cookie_name]) ) {
                unset($_COOKIE[$cookie_name]);
                
                $secure = is_ssl();
                
                $s = setcookie($cookie_name, null, -1, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true); 
            }

    }

    private function ValidateAuthCookie($name) {

            $cookie_name = $this->text_domain . '-' .$name . '_' . COOKIEHASH;
            
            if( !isset($_COOKIE[$cookie_name]) ) return false;

            $cookie = $_COOKIE[$cookie_name];

            if( !wp_validate_auth_cookie($cookie, 'auth') ) return false;

            return true;
    }

    private function SetAuthCookie($user_id ,$name, $remember = false) {

        $cookie_name = $this->text_domain . '-' .$name . '_' . COOKIEHASH;

        if ( $remember ) {

			$expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );
			$expire = $expiration + ( 12 * HOUR_IN_SECONDS );

		} else {
			$expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
			$expire     = 0;
		}

        $secure = is_ssl();
        $secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );

        $cookie = wp_generate_auth_cookie($user_id, $expiration, 'auth','');

        do_action( 'set_auth_cookie', $cookie, $expire, $expiration, $user_id, 'auth', '' );

        return setcookie($cookie_name, $cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true);
    }

    #region Lockdown Functions
    #region DisplayLoginInfo
    public function DisplayLoginInfo($message) {
        
        $message = '';

        if( ($value = $this->GetFailedLoginData()) != null ) {

            if($value['count'] >= $this->options['failed-login-count']) {

                $curr_date = new DateTime(gmdate("c"));
                $last_attempt_login_date = new DateTime($value['time']);
                $last_attempt_login_date->add(DateInterval::createFromDateString($this->options['lockdown-time'].' minutes'));
                $date_diff = $last_attempt_login_date->diff($curr_date);
                $diff_total_days = $date_diff->format("%a");

                $message .= '<div id="'.$this->text_domain.'-lockdown-container" class="login message" style="border-left-color:#d63638;" >';
                $message .= '<strong>';
                $message .= __("Login has been blocked on ", $this->text_domain);
                
                if( 0 < $diff_total_days ) {

                    $message .= '<span id="'.$this->text_domain.'-countdown-days">';
                    $message .= $diff_total_days;
                    $message .= '</span>';
                    $message .= " ";

                    if( 1 == $diff_total_days) $message .= __("day", $this->text_domain);
                    else $message .= __("days", $this->text_domain);

                    $message .= " ";
                } 

                if( 0 < $date_diff->h) {
                    $message .= '<span id="'.$this->text_domain.'-countdown-hours">';
                    $message .=  $date_diff->format('%H');
                    $message .= '</span>:';
                } 

                $message .= '<span id="'.$this->text_domain.'-countdown-minutes">';
                $message .=  $date_diff->format('%I');
                $message .= '</span>:';
                $message .= '<span id="'.$this->text_domain.'-countdown-seconds">';
                $message .=  $date_diff->format('%S');
                $message .= '</span>';

                $message .= '</strong>';
                $message .= '</div>';

                // add js script
                $timeout_message = __("You can try to login", $this->text_domain);
                $message .= '<script type="text/javascript">';
                
$message .= <<<SCRIPT

(function(){
    const secInMs = 1000;
    const minInMs = 60 * secInMs;
    const hrInMs = 60 * minInMs;
    const daysInMs = 24 * hrInMs;

    var days = document.getElementById("$this->text_domain-countdown-days");
    var hours = document.getElementById("$this->text_domain-countdown-hours");
    var minutes = document.getElementById("$this->text_domain-countdown-minutes");
    var seconds = document.getElementById("$this->text_domain-countdown-seconds");

    var d = days == null ? 0 : parseInt(days.innerText);
    var h = hours == null ? 0 : parseInt(hours.innerText);
    var m = parseInt(minutes.innerText);
    var s = parseInt(seconds.innerText);

    var time = d * daysInMs + h * hrInMs + m * minInMs + s * secInMs;
    console.log(time);

    const intervalID = window.setInterval( function() {
        var tempTime = time - secInMs;
        time = tempTime;

        if( secInMs > time) {
            window.clearInterval(intervalID);
            document.getElementById("loginform").style.display = "block";
            var container = document.getElementById("$this->text_domain-lockdown-container");
            container.insertAdjacentHTML('beforebegin', '<div class="login message" style="border-left-color:#00a32a;"><strong>$timeout_message</strong></div>');
            container.remove();
        }

        if(days != null) {
            d = Math.floor(tempTime / daysInMs);
            tempTime -= d * daysInMs;
            days.innerText = d;
        }
        if(hours != null) {
            h = Math.floor(tempTime / hrInMs);
            tempTime -= h * hrInMs;
            hours.innerText = h.toString().padStart(2, '0');
        }

        m = Math.floor(tempTime / minInMs);
        tempTime -= m * minInMs;
        minutes.innerText = m.toString().padStart(2, '0');

        s = Math.floor(tempTime / secInMs);
        tempTime -= s * secInMs;
        seconds.innerText = s.toString().padStart(2, '0');

        console.log(time);
        console.log(d,h,m,s);
        
    }, secInMs);

    window.addEventListener('load', (event) => {
        document.getElementById("loginform").style.display = "none";
    });
})();

SCRIPT;
                $message .= '</script>';
                
            } 
            else {
                
                $diff_attemps = (int)($this->options['failed-login-count']) - $value['count'];

                $message .= '<div class="login message" style="border-left-color:#dba617;" >';
                $message .= '<strong>';
                $message .= __("Left", $this->text_domain);
                $message .=  " ".$diff_attemps." ";

                if($diff_attemps === 1) {
                    $message .= __("attempt", $this->text_domain);
                } else {
                    $message .= __("attempts", $this->text_domain);
                }

                $message .= '</strong>';
                $message .= '</div>';
            }
        }
        return $message;
    }
    #endregion

    public function CountFailedLogin($username, $error) {

        $value = array(
            'time' => null,
            'count' => 0
        );
        
        // providers to stored data
        // APCU
        if(function_exists('apcu_enabled') && apcu_enabled()) {
            // if exists update count and time
            if(apcu_exists($this->lockdown_key)) {
                $value = apcu_fetch($this->lockdown_key);
            } 
            // if failed count is lower that max attempts login then update value
            if ($value['count'] < $this->options['failed-login-count']) {
                $value['count']++;
                $value['time'] = gmdate("c"); 
                apcu_store($this->lockdown_key, $value, ((int)$this->options['lockdown-time']) * 60);
            }
        } 

        // you can added other providers if your server dosn't support apcu
    }

    private function GetFailedLoginData() {
        // providers to get stored data
        // APCU
        if(function_exists('apcu_enabled') && apcu_enabled()) {
            // if exists update count and time
            if(apcu_exists($this->lockdown_key)) {
                return apcu_fetch($this->lockdown_key);
            }
        }

        // You can add other providers to get data
        
        return null;
    }

    private function DeleteFailedLoginData() {
        // providers to delate stored data
        $data = $this->GetFailedLoginData();
        
        if( null != $data ) {
            // APCU
            if(function_exists('apcu_enabled') && apcu_enabled()) {
                return apcu_delete($this->lockdown_key);
            }

        }
            
        // You can add other providers to get data

        return null;
    }

    public function LockdownAuth($user, $username, $password) {
        if(is_wp_error($user)) {
            return $user;
        } 

         if( ($value = $this->GetFailedLoginData()) != null ) {
             
            if($value['count'] >= $this->options['failed-login-count']) {

                $error_message = '<strong>';
                $error_message .= __("Too many login attempts", $this->text_domain);
                $error_message .= '</strong>';

                return new WP_Error($this->text_domain.'-lockdown', $error_message, $value);
            }
        }

        return $user;
    }
    #endregion
}
<?php

class WordpressProtectorSettingsPage {

    private static $radio_count = 0;
    /**
     * Page name
     *
     * @var string
     */
    private $page_name;

    /**
     * menu slug
     *
     * @var string
     */
    private $menu_slug;

    /**
     * Text domain
     *
     * @var string
     */
    private $text_domain;

    /**
     * options
     *
     * @var array[string]
     */
    private $options;

    /**
     * option name
     *
     * @var string
     */
    private $option_name;

    /**
     * Constructor
     * set varibles
     *
     * @param string $text_domain
     * @param string $page_name
     */
    public function __construct($text_domain, $page_name = "WordPress Protector") {
        $this->text_domain = $text_domain;
        $this->page_name = $page_name;
        $this->menu_slug = str_replace(" ","-", $page_name);
        $this->option_name = $text_domain.'-options';

        $this->UpdateOptions();
    }

    /**
     * Initialization options page and saved changes
     *
     * @return void
     */
    public function Init() {

        // update options after click save changes button
        if( isset($_POST['action'])
         && isset($_POST['_wpnonce'])
         && 'update' === $_POST['action']
         && wp_verify_nonce($_POST['_wpnonce'], $this->text_domain."-update-option") ) {

            $options = $_POST;
            unset($options['action']);
            unset($options['_wpnonce']);
            unset($options['submit']);

            $this->UpdateOptions($options);

            echo '<div class="notice notice-success"><p>';
			_e('Changes saved', $this-> text_domain);
            echo '</p></div>';
        }


        $ref= $this;
        add_options_page(
            $this->page_name, 
            $this->page_name, 
            "manage_options",
            $this->menu_slug, 
            array($this,'OptionsPage')
        ) ;
    }

    /**
     * Function print Option page and add sections
     *
     * @return void
     */
    public function OptionsPage() {

        $wp_nonce = wp_create_nonce($this->text_domain."-update-option");

        echo '<div class="wrap">
                <h1>';
        _e("Settings for", "wordpress-protector");
        echo ' WordPress Protector
                </h1>
            </div>
            <form method="POST" action="'.$this->GetSettingsUrl().'">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="_wpnonce" value="'.$wp_nonce.'">';
        
        $this->AddSection('hide-login-errors', __("Hide login errors", $this->text_domain), 'HideLoginErrors');
        $this->AddSection('hide-wp-version-section', __("Hide WordPress version", $this->text_domain), 'HideWpVersionSection');
        $this->AddSection('login-lockdown', __("Login lockdown", $this->text_domain), 'LoginLockdown');
        $this->AddSection('reCAPTCHA', "Google reCAPTCHA", 'reCAPTCHA');
        // $this->AddSection('hCAPTCHA', "hCAPTCHA", 'hCAPTCHA');
        

        do_settings_sections($this->menu_slug);

        echo '      <p class="submit">
			            <input type="submit" name="submit" id="submit" class="button button-primary" value="'.__("Save changes", $this->text_domain).'">
                    </p>
			    </form>
			</div>';
    }

    /**
     * Add hCAPTCHA section with options fields
     *
     * @param string $section_id
     * @return void
     */
    public function hCAPTCHA($section_id) {

        $this->AddField(
            'hcaptcha-enable',
            __("Enable?", $this->text_domain),
            $section_id,
            'checkbox'
        );

        $this->AddField(
            'hcaptcha-site-key',
            __("Site key (public)", $this->text_domain),
            $section_id,
            'text',
            'large-text'
        );

        $this->AddField(
            'hcaptcha-private-key',
            __("Private key (private)", $this->text_domain),
            $section_id,
            'text',
            'large-text'
        );
    }

    /**
     * Add section reCAPTCHA section with options fields
     *
     * @param string $section_id
     * @return void
     */
    public function reCAPTCHA($section_id) {

        $this->AddField(
            'recaptcha-enable',
            __("Enable?", $this->text_domain),
            $section_id,
            'checkbox'
        );

        $this->AddField(
            'recaptcha-site-key',
            __("Site key (public)", $this->text_domain),
            $section_id,
            'text',
            'large-text'
        );

        $this->AddField(
            'recaptcha-api-key',
            __("API key (private)", $this->text_domain),
            $section_id,
            'text',
            'large-text'
        );
        $this->AddField(
            'recaptcha-project-id',
            __("Project's name", $this->text_domain),
            $section_id,
            'text',
            'large-text'
        );


        $this->AddField(
            'recaptcha-score',
            __("Score", $this->text_domain),
            $section_id,
            'radio',
            '',
            0.1,
            '0.1 ('.__('the weakest', $this->text_domain).')'
        );
        $this->AddField(
            'recaptcha-score',
            "",
            $section_id,
            'radio',
            '',
            0.3,
            '0.3'
        );
        $this->AddField(
            'recaptcha-score',
            "",
            $section_id,
            'radio',
            '',
            0.7,
            '0.7'
        );
        $this->AddField(
            'recaptcha-score',
            "",
            $section_id,
            'radio',
            '',
            0.9,
            '0.9 ('.__('the strongest',$this->text_domain).')'
        );
    }

    /**
     * Add Login Lockdown section with options fields
     *
     * @param string $section_id
     * @return void
     */
    public function LoginLockdown($section_id) {

         $this->AddField(
            'lockdown-login-enable',
            __("Enable?", $this->text_domain),
            $section_id,
            'checkbox'
        );

        $this->AddField(
            'failed-login-count',
            __("Failed login count", $this->text_domain),
            $section_id,
            'number'
        );

        $this->AddField(
            'lockdown-time',
            __("Lockdown time in minutes", $this->text_domain),
            $section_id,
            'number'
        );
    }

    /**
     * Add hide wp version section with option field
     *
     * @param string $section_id
     * @return void
     */
    public function HideWpVersionSection($section_id) {
        
        $this->AddField(
            'hide-wp-version',
            __("Hide version of WordPress instance", $this->text_domain),
            $section_id,
            'checkbox'
        );
    }

    public function HideLoginErrors($section_id) {

        $this->AddField(
            'hide-login-errors',
            __("Hide all login errors", $this->text_domain),
            $section_id,
            'checkbox'
        );
    }

    /**
     * Update options in this object. 
     * IF param options is different from FALSE 
     * THEN function update option in database
     * OTHER CASE function get options from database.
     * On the end function parse options with default options
     *
     * @param array $options
     * @return void
     */
    private function UpdateOptions($options = false) {

        $actually_options = $options === false ? get_option($this->option_name, array()) : $options;
        $this->options = wp_parse_args($actually_options, self::GetDefaultOptions());

        if( is_array($options)) {
            update_option($this->option_name, $this->options, false);
        }
    }

    /**
     * Function wraped default add_settings_field function.
     * Also set value to input.
     * Function have a few instruction for different input type.
     *
     * @param string $option_id
     * @param string $title
     * @param string $section_id
     * @param string $input_type
     * @param string $input_class
     * @param string $radio_value
     * @param string $radio_label
     * @return void
     */
    private function AddField($option_id, $title, $section_id, $input_type="text", $input_class="", $radio_value='', $radio_label='') {
        
        $option_slug = $this->text_domain.'-'.$option_id;
        if('radio' == $input_type) {
            $option_slug .= '-radio-'.self::$radio_count;
            self::$radio_count++;
        }
        
        $option = $this->options[$option_id];

        add_settings_field(
            $option_slug,
            $title,
            function() use($option_id, $input_class, $input_type, $option, $radio_value, $radio_label) {
                if('checkbox' === $input_type) {
                    $option = $option == 1 ? 'checked="checked"' : '';
                    echo '<input type="'.$input_type.'" name="'.$option_id.'" class="'.$input_class.'" '.$option.' value="1" >';
                }
                elseif('number' === $input_type) {
                    echo '<input type="'.$input_type.'" name="'.$option_id.'" class="'.$input_class.'" value="'.esc_attr($option).'" min="1" max="2102400" >';
                }  
                elseif( 'radio' === $input_type ) {
                    $option = $option == $radio_value ? 'checked="checked"' : '';
                    
                    echo '<label>';
                    echo '<input type="'.$input_type.'" name="'.$option_id.'" class="'.$input_class.'" '.$option.' value="'.$radio_value.'" >';
                    echo $radio_label;
                    echo '</label>';
                    echo '<br>';
                }
                else {
                    echo '<input type="'.$input_type.'" name="'.$option_id.'" class="'.$input_class.'" value="'.esc_attr($option).'" >';
                }
            },
            $this->menu_slug,
            $section_id
        );
    }

    /**
     * Function wraped add_settings_section and provide $section_id to other functions
     *
     * @param string $section_id
     * @param string $section_name
     * @param string $callback
     * @return void
     */
    private function AddSection($section_id, $section_name, $callback) {
        $section_id = $this->text_domain.'-'.$section_id;
        $ref = $this;

        add_settings_section(
            $section_id, 
            $section_name,
            function() use($ref, $section_id, $callback) {
                call_user_func(array($ref, $callback), $section_id);
            },
            $this->menu_slug
        );
    }

    /**
     * Helpers function create url for settings page
     *
     * @return string
     */
    private function GetSettingsUrl() {
        return admin_url("options-general.php?page=".$this->menu_slug, "https");
    }

    /**
     * Helpers function. Provide Default array options
     * 
     * @return array
     */
    public static function GetDefaultOptions() {
        return array(
            'hide-login-errors' => 0,

            'hide-wp-version' => 0,

            'lockdown-login-enable' => 0,
            'failed-login-count' => 5,
            'lockdown-time' => 30,

            'recaptcha-enable' => 0,
            'recaptcha-site-key' => '',
            'recaptcha-api-key' => '',
            'recaptcha-project-id' => '',
            'recaptcha-score' => 0.7

            // 'hcaptcha-enable' => 0,
            // 'hcaptcha-site-key' => '',
            // 'hcaptcha-private-key' => ''
        );
    }

}
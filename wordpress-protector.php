<?php
/*
* Plugin Name: Wordpress Protector
* Author: Patryk Dumin
* Description: This plugin protected login service, added reCAPTCHA, Lockdown after wrong times login, also can hide wordpress versions and login notice.
* Version: 0.59.0
* Text Domain: wordpress-protector
* Domain Path: /lang
*/

if ( !defined( 'WPINC' ) ) {
	die;
}

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$wppr_plugin_is_active = is_plugin_active('wordpress-protector/wordpress-protector.php');
$wppr_text_domain = "wordpress-protector";

if( $wppr_plugin_is_active ){

    require_once 'settings-page.php';
    require_once 'protector.php';

    $wppr_protector = new WPPR_Protector($wppr_text_domain);
    $wppr_setting_page = new WordpressProtectorSettingsPage($wppr_text_domain);
    
    add_action('plugins_loaded', array($wppr_protector, 'Protect'), 10);

    add_action('plugins_loaded', function() use($wppr_text_domain) { 
        wordpreess_protector_onload($wppr_text_domain); 
    }, 10);
    
    add_action('admin_menu', array($wppr_setting_page, 'Init'), 10);
}

register_activation_hook( __FILE__, function() use($wppr_text_domain) {
    wordpreess_protector_activate($wppr_text_domain);
} );

function wordpreess_protector_onload($text_domain) {
    load_plugin_textdomain($text_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/' );
}

function wordpreess_protector_activate($text_domain)  {
    require_once 'settings-page.php';

    $options = WordpressProtectorSettingsPage::GetDefaultOptions();
    add_option($text_domain.'-options', $options, false);
}
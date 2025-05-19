<?php

class SFP_Public {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('wp_ajax_get_form_template', [$this, 'get_form_template']);
        add_action('wp_ajax_nopriv_get_form_template', [$this, 'get_form_template']);
        add_shortcode('sinergia_form', [$this, 'render_sinergia_form']);
    }

    public function enqueue_public_assets() {
        wp_enqueue_style('sfp-style', SFP_URL . 'assets/css/style.css');
        wp_enqueue_script('public-script', SFP_URL . 'assets/js/public-script.js', ['jquery'], time(), true);

        wp_localize_script('public-script', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sinergiacrm_nonce'),
            'is_logged_in' => isset($_SESSION['sinergia_session_id']) ? 'yes' : 'no'
        ]);
    }

    public function render_sinergia_form($atts) {
        $atts = shortcode_atts([
            'event_id' => ''
        ], $atts, 'sinergia_form');

        ob_start();
        include plugin_dir_path(__FILE__) . '../assets/html/form-template.php';
        return ob_get_clean();
    }
}
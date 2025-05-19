<?php
/**
 * Plugin Name: SinergiaCRM Form Plugin
 * Description: Plugin per connectar amb SinergiaCRM i generar formularis d'inscripció a esdeveniments.
 * Version: 1.0
 * Author: Evelia Molina
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('SFP_URL', plugin_dir_url(__FILE__));
define('SFP_PATH', plugin_dir_path(__FILE__));

define('SINERGIA_API_URL', 'https://fcsd.sinergiacrm.org/custom/service/v4_1_SticCustom/rest.php');
define('SINERGIA_LANG', 'es_ES');
define('SINERGIA_NOTIFY_ON_SAVE', false);
define('SINERGIA_VERBOSE', true);

require_once SFP_PATH . 'api-client/APIClient.php';
require_once SFP_PATH . 'admin/class-sfp-admin.php';
require_once SFP_PATH . 'public/class-sfp-public.php';

add_action('plugins_loaded', function () {
    if (class_exists('SFP_Admin')) {
        new SFP_Admin();
    }
    
    if (class_exists('SFP_Public')) {
        new SFP_Public();
    }
});
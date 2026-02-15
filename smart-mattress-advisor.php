<?php
/*
Plugin Name: مشاور فروش درب ورودی و داخلی
Plugin URI: https://liamwp.com
Description: افزونه مشاوره فروش درب ورودی و داخلی و پیشنهاد محصول مناسب بر اساس نیاز مشتری
Version: 1.0.3
Author: Yousef Rostami
Author URI: https://liamwp.com
Text Domain: smart-mattress-advisor
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// مسیرها و نسخه
define('MATTRESS_ADVISOR_PATH', plugin_dir_path(__FILE__));
define('MATTRESS_ADVISOR_URL', plugin_dir_url(__FILE__));
define('MATTRESS_ADVISOR_VERSION', '1.0.1');

// شامل توابع اصلی
require_once MATTRESS_ADVISOR_PATH . 'includes/functions.php';

// فرم shortcode
require_once MATTRESS_ADVISOR_PATH . 'frontend/form-shortcode.php';

// منوی ادمین
require_once MATTRESS_ADVISOR_PATH . 'admin/admin-menu.php';

// فعال‌سازی پلاگین
register_activation_hook(__FILE__, 'mattress_advisor_activate_tables');

// اضافه کردن استایل‌ها
function mattress_advisor_enqueue_styles() {
    wp_enqueue_style('mattress-wizard-style', MATTRESS_ADVISOR_URL . 'frontend/form-wizard.css', [], MATTRESS_ADVISOR_VERSION);
    wp_enqueue_style('mattress-result-style', MATTRESS_ADVISOR_URL . 'frontend/result-styles.css', [], MATTRESS_ADVISOR_VERSION);
}
add_action('wp_enqueue_scripts', 'mattress_advisor_enqueue_styles');

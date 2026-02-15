<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * قبلاً توابع دیگری اینجا بودند — این کد را اضافه کن یا جایگزین کن.
 */



function mattress_advisor_activate_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $rules_table = $wpdb->prefix . 'mattress_rules';
    $history_table = $wpdb->prefix . 'mattress_history';

    // The dbDelta function is smart enough to add new columns. No need for manual ALTER TABLE.
    $sql = "CREATE TABLE $rules_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        conditions text NOT NULL,
        product_id bigint(20) NOT NULL,
        key_features longtext NULL,
        why_suitable longtext NULL,
        match_count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE $history_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NULL,
        email varchar(191) NULL,
        form_data longtext NOT NULL,
        product_id bigint(20) NULL,
        order_id bigint(20) NULL,
        purchase_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        user_actions longtext NULL,
        share_token varchar(64) NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY share_token (share_token)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    dbDelta( $sql2 );
}

// Ensure history table has necessary columns (for upgrades)
add_action('admin_init', 'mattress_advisor_ensure_history_schema');
function mattress_advisor_ensure_history_schema() {
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    // Check if order_id column exists
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'order_id'));
    if (empty($col)) {
        // Add column order_id
        $wpdb->query("ALTER TABLE $table ADD COLUMN order_id bigint(20) NULL AFTER product_id");
    }
}

// ---------------------- AJAX: admin get rule ----------------------
add_action('wp_ajax_get_mattress_rule', 'mattress_advisor_get_rule');

function mattress_advisor_get_rule() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';
    $id = intval($_POST['rule_id']);

    if (!$id) {
        wp_send_json_error(['message' => 'شناسه قانون معتبر نیست.']);
    }

    $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if ($rule) {
        wp_send_json_success(['rule' => $rule]);
    } else {
        wp_send_json_error(['message' => 'قانون یافت نشد.']);
    }
}

// ---------------------- AJAX: admin update rule ----------------------
add_action('wp_ajax_update_mattress_rule', 'mattress_advisor_update_rule');

function mattress_advisor_update_rule() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';
    
    $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if (!$rule_id || !$product_id) {
        wp_send_json_error(['message' => 'شناسه قانون و محصول الزامی است.']);
    }

    // Define the fields that make up a rule's conditions
    $condition_keys = [
        'door_type', 'building_type', 'weather_exposure', 'facade_style', 'entrance_material',
        'door_width_min', 'door_width_max', 'door_height_min', 'door_height_max',
        'waterproof', 'metal_frame_installed', 'usage_space', 'interior_style',
        'color_theme', 'weatherstrip', 'interior_material'
    ];

    $conditions = [];
    foreach ($condition_keys as $key) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            continue;
        }

        $raw = $_POST[$key];
        if (is_array($raw)) {
            $sanitized_items = array_map('sanitize_text_field', $raw);
            $sanitized_items = array_filter(array_map('trim', $sanitized_items));
            if (!empty($sanitized_items)) {
                $conditions[sanitize_key($key)] = implode(',', $sanitized_items);
            }
        } else {
            $conditions[sanitize_key($key)] = sanitize_text_field($raw);
        }
    }

    // Prepare the final data for database insertion, ensuring all parts are sanitized.
    $data = [
        'product_id'   => $product_id,
        'conditions'   => wp_json_encode($conditions),
        'key_features' => isset($_POST['key_features']) ? sanitize_textarea_field($_POST['key_features']) : '',
        'why_suitable' => isset($_POST['why_suitable']) ? sanitize_textarea_field($_POST['why_suitable']) : ''
    ];

    $result = $wpdb->update($table, $data, ['id' => $rule_id]);

    if ($result !== false) {
        wp_send_json_success(['message' => 'قانون با موفقیت به‌روزرسانی شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در به‌روزرسانی قانون: ' . $wpdb->last_error]);
    }
}

// ---------------------- AJAX: process form and return HTML result ----------------------
add_action('wp_ajax_get_mattress_recommendation', 'mattress_advisor_process_form');
add_action('wp_ajax_nopriv_get_mattress_recommendation', 'mattress_advisor_process_form');

function mattress_advisor_process_form() {
    check_ajax_referer('mattress_nonce', 'nonce');

    // Handle both old and new form data structures
    if (isset($_POST['data'])) {
        parse_str($_POST['data'], $form_data);
    } else {
        $form_data = $_POST;
        unset($form_data['action'], $form_data['nonce']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';
    
    $rules = $wpdb->get_results("SELECT * FROM $table");
    
    if (empty($rules)) {
    wp_send_json_error('هیچ مشاوره‌ای در سیستم تعریف نشده است. لطفاً ابتدا از پنل ادمین مشاوره اضافه کنید.');
    }

    // 1) Try exact match first
    foreach ($rules as $rule) {
        $conditions = json_decode($rule->conditions, true);
        if (empty($conditions)) {
            continue;
        }
        $matched = true;
        foreach ($conditions as $key => $value) {
            // Skip range keys, they are handled with their base keys
            if (in_array($key, ['door_width_min', 'door_width_max', 'door_height_min', 'door_height_max'])) {
                continue;
            }

            if (in_array($key, ['door_width', 'door_height'])) {
                $val = isset($form_data[$key]) ? intval($form_data[$key]) : null;
                $min = isset($conditions[$key.'_min']) ? intval($conditions[$key.'_min']) : null;
                $max = isset($conditions[$key.'_max']) ? intval($conditions[$key.'_max']) : null; // Corrected typo: ._max -> _max

                if ($val === null) { $matched = false; break; }
                if ($min !== null && $val < $min) { $matched = false; break; }
                if ($max !== null && $val > $max) { $matched = false; break; }

            } else {
                $lhs = isset($form_data[$key]) ? mattress_advisor_normalize_value($key, $form_data[$key]) : null;
                $rhs = mattress_advisor_normalize_value($key, $value);
                if (in_array($key, ['entrance_material', 'usage_space'], true)) {
                    $lhs_values = is_array($lhs) ? $lhs : array_filter(array_map('trim', explode(',', (string)$lhs)));
                    $rhs_values = array_filter(array_map('trim', explode(',', (string)$rhs)));
                    if (empty($lhs_values) || empty($rhs_values) || empty(array_intersect($lhs_values, $rhs_values))) {
                        $matched = false;
                        break;
                    }
                } elseif ($lhs === null || (string)$lhs !== (string)$rhs) {
                    $matched = false;
                    break;
                }
            }
        }

        if ($matched) {
            if (!function_exists('wc_get_product')) {
                wp_send_json_error('ووکامرس فعال نیست.');
            }
            $product = wc_get_product($rule->product_id);
            if ($product) {
                require_once MATTRESS_ADVISOR_PATH . 'frontend/result-template.php';
                $related_ids = wc_get_related_products($product->get_id(), 3);
                $related = array_map('wc_get_product', $related_ids);
                $history_id = mattress_advisor_save_history($form_data, $product->get_id());
                $wpdb->query($wpdb->prepare("UPDATE $table SET match_count = match_count + 1 WHERE id = %d", $rule->id));
                $display_options = get_option('mattress_advisor_display_options');
                $html = mattress_advisor_render_result($product, $form_data, $related, $display_options, $history_id);
                wp_send_json_success(['html' => $html, 'product_id' => $product->get_id(), 'history_id' => $history_id]);
            } else {
                // This rule is broken, continue to the next one
                continue;
            }
        }
    }

    // 2) Fallback: choose closest rule by scoring
    $best_rule = null;
    $best_score = -1;
    foreach ($rules as $rule) {
        $conditions = json_decode($rule->conditions, true);
        if (empty($conditions)) continue;
        $score = 0;
        foreach ($form_data as $key => $form_val) {
            if (!isset($conditions[$key])) continue;

            $form_val_norm = mattress_advisor_normalize_value($key, $form_val);

            if (in_array($key, ['door_width', 'door_height'])) {
                $fv = intval($form_val_norm);
                $min = isset($conditions[$key.'_min']) ? intval($conditions[$key.'_min']) : null;
                $max = isset($conditions[$key.'_max']) ? intval($conditions[$key.'_max']) : null;
                if ($min !== null && $max !== null) {
                    if ($fv >= $min && $fv <= $max) {
                        $score += 10;
                    } else {
                        $dist = ($fv < $min) ? ($min - $fv) : ($fv - $max);
                        $score += max(0, 10 - min(10, intval($dist / 5)));
                    }
                }
            } else {
                $rule_val_norm = mattress_advisor_normalize_value($key, $conditions[$key]);
                if (in_array($key, ['entrance_material', 'usage_space'], true)) {
                    $form_values = is_array($form_val_norm) ? $form_val_norm : array_filter(array_map('trim', explode(',', (string)$form_val_norm)));
                    $rule_values = array_filter(array_map('trim', explode(',', (string)$rule_val_norm)));
                    if (!empty(array_intersect($form_values, $rule_values))) {
                        $score += 10;
                    }
                } elseif ((string)$form_val_norm === (string)$rule_val_norm) {
                    $score += 10;
                }
            }
        }
        if ($score > $best_score) {
            $best_score = $score;
            $best_rule = $rule;
        }
    }

    if ($best_rule && $best_score > 0) {
        if (!function_exists('wc_get_product')) {
            wp_send_json_error('ووکامرس فعال نیست.');
        }
        $product = wc_get_product($best_rule->product_id);
        if ($product) {
            require_once MATTRESS_ADVISOR_PATH . 'frontend/result-template.php';
            $related_ids = wc_get_related_products($product->get_id(), 3);
            $related = array_map('wc_get_product', $related_ids);
            $history_id = mattress_advisor_save_history($form_data, $product->get_id());
            $wpdb->query($wpdb->prepare("UPDATE $table SET match_count = match_count + 1 WHERE id = %d", $best_rule->id));
            $notice = '<div class="approximate-notice" style="margin:15px 0;padding:12px 16px;border:1px solid #ffd54f;background:#fff8e1;border-radius:8px;color:#8d6e63;">نتیجه‌ی زیر نزدیک‌ترین پیشنهاد بر اساس شرایط شماست.</div>';
            $display_options = get_option('mattress_advisor_display_options');
            $html = $notice . mattress_advisor_render_result($product, $form_data, $related, $display_options, $history_id);
            wp_send_json_success(['html' => $html, 'product_id' => $product->get_id(), 'history_id' => $history_id, 'approximate' => true, 'score' => $best_score]);
        }
    }

    wp_send_json_error('هیچ محصولی با شرایط شما یافت نشد.');
}

// ---------------------- save history ----------------------
function mattress_advisor_save_history( $form_data, $product_id = null ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';

    $user_id = get_current_user_id() ? get_current_user_id() : null;
    $email = isset($form_data['email']) && is_email($form_data['email']) ? sanitize_email($form_data['email']) : null;

    // Generate a unique share token to allow reliable linking (especially for guest orders)
    if ( function_exists('wp_generate_password') ) {
        $tries = 0;
        do {
            $share_token = wp_generate_password(12, false, false);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE share_token = %s", $share_token));
            $tries++;
            if ($tries > 5) break;
        } while ($exists);
    } else {
        // fallback
        $share_token = uniqid('msh_', true);
    }

    $wpdb->insert( $table, [
        'user_id'    => $user_id,
        'email'      => $email,
        'form_data'  => wp_json_encode($form_data),
        'product_id' => $product_id,
        'order_id'   => null,
        'purchase_status' => 'pending',
        'user_actions' => null,
        'share_token' => $share_token,
        'created_at' => current_time('mysql', 1)
    ] );

    return $wpdb->insert_id;
}

// ---------------------- AJAX: update purchase status ----------------------
add_action('wp_ajax_update_mattress_purchase_status', 'update_mattress_purchase_status');

function update_mattress_purchase_status() {
    check_ajax_referer('mattress_nonce', 'nonce');

    if ( !is_user_logged_in() ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if ( !$history_id || !in_array($status, ['completed', 'cancelled']) ) {
        wp_send_json_error(['message' => 'اطلاعات نامعتبر است.']);
    }

    $result = $wpdb->update(
        $table,
        ['purchase_status' => $status],
        ['id' => $history_id, 'user_id' => get_current_user_id()],
        ['%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'وضعیت با موفقیت به‌روزرسانی شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در به‌روزرسانی وضعیت.']);
    }
}

// ---------------------- CSV/Excel export for history ----------------------
add_action('admin_post_mattress_export_csv', 'mattress_advisor_export_submissions');

function mattress_advisor_export_submissions() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Unauthorized');
    }
    check_admin_referer('mattress_export_csv');

    // Include jdate if not available (similar to iranform)
    if (!class_exists('jdf') && !function_exists('jdate')) {
        $jdate_path = MATTRESS_ADVISOR_PATH . 'iranform/assets/jalaliDatepicker.php';
        if (file_exists($jdate_path)) {
            require_once $jdate_path;
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    
    // Get format (csv or excel)
    $format = isset($_GET['format']) && $_GET['format'] === 'excel' ? 'excel' : 'csv';
    $extension = $format === 'excel' ? 'xls' : 'csv';
    
    // Optional: export only selected IDs if provided
    $ids = [];
    if ( isset($_REQUEST['ids']) ) {
        $ids_raw = (array) $_REQUEST['ids'];
        foreach ($ids_raw as $id) {
            $id = intval($id);
            if ($id > 0) { $ids[] = $id; }
        }
        $ids = array_values(array_unique($ids));
    }

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare("SELECT * FROM $table WHERE id IN ($placeholders) ORDER BY created_at DESC", $ids);
        $rows = $wpdb->get_results($query);
    } else {
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    $filename = 'mattress_history_' . date('Y-m-d_H-i-s') . '.' . $extension;
    
    // Headers setup
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 support in Excel/CSV
    fwrite($output, "\xEF\xBB\xBF");

    // Define columns mapping
    $columns = [
        'id' => 'شناسه',
        'created_at' => 'تاریخ ثبت',
        'full_name' => 'نام و نام خانوادگی',
        'mobile' => 'موبایل',
        'province' => 'استان',
        'product' => 'محصول پیشنهادی',
        'price' => 'قیمت محصول',
        'order_id' => 'کد سفارش',
        'purchase_status' => 'وضعیت خرید',
        // Details
        'age' => 'سن',
        'height' => 'قد',
        'weight' => 'وزن',
        'back_curve' => 'گودی کمر',
        'sleep_type' => 'نوع خواب',
        'persons' => 'تعداد نفرات',
        'quality' => 'کیفیت',
        'elasticity' => 'حالت ارتجاعی',
        'back_pain' => 'مشکل کمر',
        'usage_type' => 'نوع استفاده',
        'usage_place' => 'نوع کاربرد'
    ];

    // Helper for Persian mapping
    $value_maps = [
        'back_curve' => ['has_curve' => 'دارم (مناسب گودی کمر)', 'supports_curve' => 'تشک مناسب گودی کمر', 'not_allowed' => 'خرید مجاز نیست', 'کم' => 'کم', 'متوسط' => 'متوسط', 'زیاد' => 'زیاد'],
        'sleep_type' => ['light' => 'سبک', 'heavy' => 'سنگین'],
        'persons' => ['1' => 'یک نفره', '2' => 'دو نفره'],
        'quality' => ['excellent' => 'عالی (درجه یک)', 'good' => 'مطلوب (درجه دو)'],
        'elasticity' => ['low' => 'کم', 'very_low' => 'خیلی کم', 'has' => 'دارد'],
        'back_pain' => ['no' => 'ندارد', 'yes' => 'دارد'],
        'usage_type' => ['temporary' => 'موقت', 'permanent' => 'دائم'],
        'usage_place' => ['home' => 'خانه', 'villa' => 'ویلا'],
        'purchase_status' => ['pending' => 'در انتظار', 'completed' => 'تکمیل شده', 'cancelled' => 'لغو شده']
    ];

    // Write Header Row
    if ($format === 'excel') {
        fwrite($output, '<table border="1"><thead><tr>');
        foreach ($columns as $col_name) {
            fwrite($output, '<th style="background-color:#f0f0f0; padding:10px;">' . $col_name . '</th>');
        }
        fwrite($output, '</tr></thead><tbody>');
    } else {
        fputcsv($output, array_values($columns));
    }

    foreach ($rows as $row) {
        $form_data = json_decode($row->form_data, true);
        if (!is_array($form_data)) $form_data = [];

        // Prepare row data
        $data = [];
        
        // ID
        $data[] = $row->id;
        
        // Date (Jalaali if available)
        if (function_exists('jdate')) {
            $data[] = jdate('Y/m/d H:i', strtotime($row->created_at));
        } else {
            $data[] = $row->created_at;
        }

        // Full Name
        $full_name = '-';
        if (isset($form_data['first_name']) || isset($form_data['last_name'])) {
            $first = isset($form_data['first_name']) ? trim($form_data['first_name']) : '';
            $last = isset($form_data['last_name']) ? trim($form_data['last_name']) : '';
            $full_name = trim("$first $last");
        } elseif (!empty($form_data['full_name'])) {
            $full_name = $form_data['full_name'];
        }
        $data[] = $full_name ?: '-';

        // Mobile
        $data[] = isset($form_data['mobile']) ? $form_data['mobile'] : '-';

        // Province
        $province = isset($form_data['province']) ? mattress_advisor_get_province_name($form_data['province']) : '-';
        $data[] = $province;

        // Product
        $product_title = $row->product_id ? get_the_title($row->product_id) : '-';
        $data[] = $product_title;

        // Price
        $price = '-';
        if ($row->product_id && function_exists('wc_get_product')) {
            $prod = wc_get_product($row->product_id);
            if ($prod) $price = $prod->get_price();
        }
        $data[] = $price;

        // Order ID
        $data[] = $row->order_id ?: '-';

        // Status
        $status = isset($value_maps['purchase_status'][$row->purchase_status]) ? $value_maps['purchase_status'][$row->purchase_status] : $row->purchase_status;
        $data[] = $status;

        // Form Fields
        $fields = ['age', 'height', 'weight', 'back_curve', 'sleep_type', 'persons', 'quality', 'elasticity', 'back_pain', 'usage_type', 'usage_place'];
        foreach ($fields as $field) {
            $val = isset($form_data[$field]) ? $form_data[$field] : '';
            // Map value if exists
            if (isset($value_maps[$field]) && isset($value_maps[$field][$val])) {
                $val = $value_maps[$field][$val];
            }
            $data[] = $val;
        }

        // Write Row
        if ($format === 'excel') {
            fwrite($output, '<tr>');
            foreach ($data as $cell) {
                fwrite($output, '<td style="padding:5px;">' . $cell . '</td>');
            }
            fwrite($output, '</tr>');
        } else {
            fputcsv($output, $data);
        }
    }

    if ($format === 'excel') {
        fwrite($output, '</tbody></table>');
    }

    fclose($output);
    exit;
}

// ---------------------- bulk delete selected history rows ----------------------
add_action('admin_post_mattress_delete_history', 'mattress_advisor_delete_history');

function mattress_advisor_delete_history() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Unauthorized');
    }
    check_admin_referer('mattress_delete_history');

    $ids = [];
    if ( isset($_REQUEST['ids']) ) {
        $ids_raw = (array) $_REQUEST['ids'];
        foreach ($ids_raw as $id) {
            $id = intval($id);
            if ($id > 0) { $ids[] = $id; }
        }
        $ids = array_values(array_unique($ids));
    }

    $deleted = 0;
    if (!empty($ids)) {
        global $wpdb;
        $table = $wpdb->prefix . 'mattress_history';
        // Build a safe IN() list without relying on prepare with array args
        $in = implode(',', array_map('intval', $ids));
        $sql = "DELETE FROM $table WHERE id IN ($in)";
        $wpdb->query($sql);
        $deleted = intval($wpdb->rows_affected);
    }

    // Redirect back to history page with status
    $url = add_query_arg([
        'page' => 'mattress-advisor-history',
        'deleted' => $deleted,
    ], admin_url('admin.php'));
    wp_safe_redirect($url);
    exit;
}

// ---------------------- small helper: explain choice ----------------------
function mattress_advisor_explain_choice( $form_data ) {
    $reasons = [];

    if ( isset($form_data['door_type']) && $form_data['door_type'] === 'entrance' ) {
        $reasons[] = 'با توجه به اینکه درب ورودی مدنظر شماست، مقاومت و امنیت در اولویت قرار گرفته است.';
        if ( isset($form_data['weather_exposure']) && $form_data['weather_exposure'] === 'yes' ) {
            $reasons[] = 'به دلیل برخورد مستقیم آفتاب و باران، گزینه‌های مقاوم در برابر شرایط محیطی پیشنهاد شده‌اند.';
        }
        if ( isset($form_data['door_width'], $form_data['door_height']) ) {
            $w = intval($form_data['door_width']);
            $h = intval($form_data['door_height']);
            if ($w > 220 || $h > 235) {
                $reasons[] = 'با توجه به ابعاد اعلام‌شده، درب پیووت می‌تواند انتخاب مناسب‌تری نسبت به درب ضد سرقت استاندارد باشد.';
            }
        }
    }

    if ( isset($form_data['door_type']) && $form_data['door_type'] === 'interior' ) {
        $reasons[] = 'با توجه به داخلی بودن درب، هماهنگی با دکوراسیون و کاربری فضا لحاظ شده است.';
        if ( isset($form_data['waterproof']) && in_array($form_data['waterproof'], ['yes', 'water_resistant', 'waterproof'], true) ) {
            $reasons[] = 'به دلیل نیاز به مقاومت رطوبتی، گزینه‌های ضدآب در اولویت قرار گرفته‌اند.';
        }
    }

    if ( empty($reasons) ) {
        return 'این گزینه بر اساس اطلاعات شما از نظر متریال، استایل و کاربرد بهترین انتخاب است.';
    }

    return implode(' ', $reasons);
}

// ---------------------- normalization helper for legacy/admin values ----------------------
function mattress_advisor_normalize_value( $key, $value ) {
    $val = is_string($value) ? trim($value) : $value;
    if (!is_string($val)) return $val;

    switch ($key) {
        case 'door_type':
            $map = ['ورودی' => 'entrance', 'داخلی' => 'interior', 'entrance' => 'entrance', 'interior' => 'interior'];
            return $map[$val] ?? strtolower($val);
        case 'building_type':
            $map = ['آپارتمانی' => 'apartment', 'ویلایی' => 'villa', 'apartment' => 'apartment', 'villa' => 'villa'];
            return $map[$val] ?? strtolower($val);
        case 'weather_exposure':
        case 'metal_frame_installed':
        case 'weatherstrip':
            $map = ['بله' => 'yes', 'خیر' => 'no', 'دارد' => 'yes', 'ندارد' => 'no', 'yes' => 'yes', 'no' => 'no'];
            return $map[$val] ?? strtolower($val);
        case 'waterproof':
            $map = [
                'بله' => 'yes',
                'خیر' => 'no',
                'yes' => 'yes',
                'no' => 'no',
                'water_resistant' => 'water_resistant',
                'waterproof' => 'waterproof',
                'مقاوم به آب' => 'water_resistant',
                '100%ضدآب' => 'waterproof',
            ];
            return $map[$val] ?? strtolower($val);
        case 'interior_material':
            $map = [
                'mdf_melamine' => 'mdf',
                'MDF و ملامینه' => 'mdf',
                'mdf' => 'mdf',
                'melamine' => 'melamine',
                'ملامینه' => 'melamine',
                'abs' => 'abs',
                'polywood' => 'polywood',
                'پلی وود' => 'polywood',
                'پلی‌وود' => 'polywood',
            ];
            return $map[$val] ?? strtolower($val);
        default:
            return strtolower($val);
    }
}

// Return Persian display name for stored province code
function mattress_advisor_get_province_name($code) {
    if (!$code) return '-';
    $map = [
        'tehran' => 'تهران',
        'isfahan' => 'اصفهان',
        'mashhad' => 'مشهد',
        'shiraz' => 'شیراز',
        'tabriz' => 'تبریز',
        'ahvaz' => 'اهواز',
        'qom' => 'قم',
        'karaj' => 'کرج',
        'urmia' => 'ارومیه',
        'rasht' => 'رشت',
        'kerman' => 'کرمان',
        'hamadan' => 'همدان',
        'yazd' => 'یزد',
        'ardabil' => 'اردبیل',
        'bandar_abbas' => 'بندرعباس',
        'arak' => 'اراک',
        'eslamshahr' => 'اسلامشهر',
        'kermanshah' => 'کرمانشاه',
        'gorgan' => 'گرگان',
        'sanandaj' => 'سنندج',
        'zahedan' => 'زاهدان',
        'zanjan' => 'زنجان',
        'sari' => 'ساری',
        'abadan' => 'آبادان',
        'khorramabad' => 'خرم‌آباد',
        'ilam' => 'ایلام',
        'bojnurd' => 'بجنورد',
        'birjand' => 'بیرجند',
        'bushehr' => 'بوشهر',
        'semnan' => 'سمنان',
        'yasuj' => 'یاسوج',
        'shahrekord' => 'شهرکرد',
    ];

    $k = strtolower(trim($code));
    return isset($map[$k]) ? $map[$k] : $code;
}

// ---------------------- AJAX: admin add rule ----------------------
add_action('wp_ajax_add_mattress_rule', 'mattress_advisor_add_rule');

function mattress_advisor_add_rule() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';
    
    // Get form data directly from $_POST since it's serialized
    $product_id = intval($_POST['product_id']);
    if ( !$product_id || !wc_get_product($product_id) ) {
        wp_send_json_error(['message' => 'محصول انتخاب شده معتبر نیست.']);
    }

    $conditions = [];
    
    // Process all form fields except product_id, action, nonce, key_features, and why_suitable
    foreach ($_POST as $key => $value) {
        if ( !in_array($key, ['product_id', 'action', 'nonce', 'key_features', 'why_suitable']) && !empty($value) ) {
            if (is_array($value)) {
                $sanitized_items = array_map('sanitize_text_field', $value);
                $sanitized_items = array_filter(array_map('trim', $sanitized_items));
                if (!empty($sanitized_items)) {
                    $conditions[sanitize_key($key)] = implode(',', $sanitized_items);
                }
            } else {
                $conditions[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
    }

    if (empty($conditions)) {
        wp_send_json_error(['message' => 'حداقل یک شرط باید تعیین شود.']);
    }

    // Only allow relevant condition keys
    $allowed_keys = [
        'door_type','building_type','weather_exposure','facade_style','entrance_material',
        'door_width','door_height','door_width_min','door_width_max','door_height_min','door_height_max',
        'waterproof','metal_frame_installed','usage_space','interior_style','color_theme','weatherstrip','interior_material'
    ];
    $filtered = [];
    foreach ($conditions as $k => $v) {
        if (in_array($k, $allowed_keys, true)) {
            $filtered[$k] = $v;
        }
    }

    // Get key features and why suitable
    $key_features = isset($_POST['key_features']) ? sanitize_textarea_field($_POST['key_features']) : '';
    $why_suitable = isset($_POST['why_suitable']) ? sanitize_textarea_field($_POST['why_suitable']) : '';

    $result = $wpdb->insert($table, [
        'conditions' => wp_json_encode($filtered),
        'product_id' => $product_id,
        'key_features' => $key_features,
        'why_suitable' => $why_suitable
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'قانون با موفقیت افزوده شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در افزودن قانون: ' . $wpdb->last_error]);
    }
}

// ---------------------- AJAX: admin delete rule ----------------------
add_action('wp_ajax_delete_mattress_rule', 'mattress_advisor_delete_rule');

function mattress_advisor_delete_rule() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';
    $id = intval($_POST['rule_id']);

    if (!$id) {
        wp_send_json_error(['message' => 'شناسه قانون معتبر نیست.']);
    }

    $result = $wpdb->delete($table, ['id' => $id], ['%d']);

    if ($result) {
        wp_send_json_success(['message' => 'قانون با موفقیت حذف شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در حذف قانون: ' . $wpdb->last_error]);
    }
}

// ---------------------- AJAX: admin preview rule ----------------------
add_action('wp_ajax_preview_mattress_rule', 'mattress_advisor_preview_rule');

function mattress_advisor_preview_rule() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');
    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if ( ! function_exists('wc_get_product') ) {
        wp_send_json_error(['message' => 'ووکامرس فعال نیست.']);
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error(['message' => 'محصول انتخاب نشده است.']);
    }
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => 'محصول یافت نشد.']);
    }

    require_once MATTRESS_ADVISOR_PATH . 'frontend/result-template.php';
    $html  = '<div class="approximate-notice" style="margin:15px 0;padding:12px 16px;border:1px solid #90caf9;background:#e3f2fd;border-radius:8px;color:#1565c0;">';
    $html .= 'این یک پیش‌نمایش از نمایش نتیجه این قانون است.';
    $html .= '</div>';
    $display_options = get_option('mattress_advisor_display_options');
    $html .= mattress_advisor_render_result($product, [], [], $display_options, null);
    wp_send_json_success(['html' => $html]);
}

// ---------------------- AJAX: admin check conflicts ----------------------
add_action('wp_ajax_check_mattress_rule_conflicts', 'mattress_advisor_check_conflicts');

function mattress_advisor_check_conflicts() {
    check_ajax_referer('mattress_admin_nonce', 'nonce');
    if ( !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_rules';

    $allowed_keys = [
        'door_type', 'building_type', 'weather_exposure', 'facade_style', 'entrance_material',
        'door_width_min', 'door_width_max', 'door_height_min', 'door_height_max',
        'waterproof', 'metal_frame_installed', 'usage_space', 'interior_style',
        'color_theme', 'weatherstrip', 'interior_material'
    ];
    $new_conditions = [];
    foreach ($allowed_keys as $key) {
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            if (is_array($_POST[$key])) {
                $sanitized_items = array_map('sanitize_text_field', $_POST[$key]);
                $sanitized_items = array_filter(array_map('trim', $sanitized_items));
                if (!empty($sanitized_items)) {
                    $new_conditions[$key] = implode(',', $sanitized_items);
                }
            } else {
                $new_conditions[$key] = sanitize_text_field($_POST[$key]);
            }
        }
    }

    $rules = $wpdb->get_results("SELECT id, product_id, conditions FROM $table");
    $conflicts = [];

    foreach ($rules as $rule) {
        $existing_conditions = json_decode($rule->conditions, true);
        if (empty($existing_conditions)) continue;

        $is_conflict = true;
        // Check for any non-overlapping condition
        foreach ($new_conditions as $key => $new_val) {
            if (!isset($existing_conditions[$key])) {
                $is_conflict = false; // Existing rule doesn't have this condition, so no conflict on this key
                break;
            }

            // Handle numeric range conflicts
            if (in_array($key, ['door_width', 'door_height'])) {
                $new_min = isset($new_conditions[$key.'_min']) ? intval($new_conditions[$key.'_min']) : null;
                $new_max = isset($new_conditions[$key.'_max']) ? intval($new_conditions[$key.'_max']) : null;
                $existing_min = isset($existing_conditions[$key.'_min']) ? intval($existing_conditions[$key.'_min']) : null;
                $existing_max = isset($existing_conditions[$key.'_max']) ? intval($existing_conditions[$key.'_max']) : null;

                // If both have ranges, check for overlap
                if ($new_min && $new_max && $existing_min && $existing_max) {
                    if ($new_max < $existing_min || $new_min > $existing_max) {
                        $is_conflict = false; // Ranges do not overlap
                        break;
                    }
                }
            } else { // Handle exact match conflicts
                if (in_array($key, ['entrance_material', 'usage_space'], true)) {
                    $new_values = array_filter(array_map('trim', explode(',', (string)$new_val)));
                    $existing_values = array_filter(array_map('trim', explode(',', (string)$existing_conditions[$key])));
                    if (empty(array_intersect($new_values, $existing_values))) {
                        $is_conflict = false;
                        break;
                    }
                } elseif ((string)$new_val !== (string)$existing_conditions[$key]) {
                    $is_conflict = false; // Values are different, no conflict
                    break;
                }
            }
        }

        if ($is_conflict) {
            $conflicts[] = [
                'id' => $rule->id,
                'product' => get_the_title($rule->product_id)
            ];
        }
    }

    wp_send_json_success(['conflicts' => $conflicts]);
}

// ---------------------- Form completion validation ----------------------
function mattress_advisor_is_form_complete($form_data) {
    $base_fields = ['full_name', 'mobile', 'door_type'];
    foreach ($base_fields as $field) {
        if (!isset($form_data[$field]) || trim((string)$form_data[$field]) === '') {
            return false;
        }
    }

    if (!preg_match('/^09[0-9]{9}$/', $form_data['mobile'])) {
        return false;
    }

    if ($form_data['door_type'] === 'entrance') {
        $required = ['building_type','weather_exposure','facade_style','entrance_material','door_width','door_height'];
        foreach ($required as $field) {
            if (!isset($form_data[$field]) || trim((string)$form_data[$field]) === '') {
                return false;
            }
        }

        $door_width = intval($form_data['door_width']);
        $door_height = intval($form_data['door_height']);
        if ($door_width < 60 || $door_width > 350 || $door_height < 150 || $door_height > 350) {
            return false;
        }
    }

    if ($form_data['door_type'] === 'interior') {
        $required = ['waterproof','metal_frame_installed','usage_space','interior_style','color_theme','weatherstrip','interior_material'];
        foreach ($required as $field) {
            if (!isset($form_data[$field])) {
                return false;
            }
            if (is_array($form_data[$field])) {
                if (empty(array_filter(array_map('trim', $form_data[$field])))) {
                    return false;
                }
            } elseif (trim((string)$form_data[$field]) === '') {
                return false;
            }
        }
    }

    return true;
}

// ---------------------- Purchase status check ----------------------
function mattress_advisor_check_purchase($user_id, $product_id) {
    if ( !$user_id || !$product_id || !function_exists('wc_get_orders') ) {
        return false;
    }

    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => ['wc-completed', 'wc-processing'],
        'limit'       => -1,
    ]);

    if (empty($orders)) {
        return false;
    }

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id || $item->get_variation_id() == $product_id) {
                return true;
            }
        }
    }

    return false;
}

// ---------------------- User action tracking ----------------------
function mattress_advisor_track_user_action($history_id, $action) {
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    
    // Get current actions
    $current_actions = $wpdb->get_var($wpdb->prepare(
        "SELECT user_actions FROM $table WHERE id = %d",
        $history_id
    ));
    
    $actions = $current_actions ? json_decode($current_actions, true) : [];
    if (!is_array($actions)) {
        $actions = [];
    }
    
    // Add new action with timestamp
    $actions[] = [
        'action' => $action,
        'timestamp' => current_time('mysql', 1),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    // Update database
    $wpdb->update(
        $table,
        ['user_actions' => wp_json_encode($actions)],
        ['id' => $history_id],
        ['%s'],
        ['%d']
    );
}

// ---------------------- AJAX: Track user action ----------------------
add_action('wp_ajax_track_mattress_action', 'mattress_advisor_track_action_ajax');
add_action('wp_ajax_nopriv_track_mattress_action', 'mattress_advisor_track_action_ajax');

function mattress_advisor_track_action_ajax() {
    check_ajax_referer('mattress_nonce', 'nonce');
    
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
    $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (!$history_id || !$action) {
        wp_send_json_error(['message' => 'اطلاعات نامعتبر است.']);
    }
    
    mattress_advisor_track_user_action($history_id, $action);
    wp_send_json_success(['message' => 'عملیات ثبت شد.']);
}

// ---------------------- Determine purchase status based on user actions ----------------------
function mattress_advisor_determine_purchase_status($history_record, $form_data) {
    // First check if form is complete
    if (!mattress_advisor_is_form_complete($form_data)) {
        return '<span class="status-incomplete">فرم ناتمام</span>';
    }
    
    // Get user actions to check if they went through the consultation flow
    $user_actions = $history_record->user_actions ? json_decode($history_record->user_actions, true) : [];
    
    // Check if user actually purchased the product through consultation
    $order_status = mattress_advisor_get_consultation_order_status($history_record->user_id, $history_record->product_id, $user_actions);
    
    if ($order_status['status'] === 'completed') {
        return '<span class="status-success">خرید موفق</span>';
    } elseif ($order_status['status'] === 'processing') {
        return '<span class="status-processing">در حال پردازش</span>';
    } elseif ($order_status['status'] === 'pending') {
        return '<span class="status-pending">در انتظار پرداخت</span>';
    } elseif ($order_status['status'] === 'cancelled') {
        return '<span class="status-cancelled">لغو شده</span>';
    } elseif ($order_status['status'] === 'failed') {
        return '<span class="status-failed">خرید ناموفق</span>';
    }
    
    // Check for specific failure actions
    $failed_actions = ['restart_consultation', 'share_result', 'page_leave', 'cancel'];
    $has_failed_action = false;
    
    foreach ($user_actions as $action) {
        if (isset($action['action']) && in_array($action['action'], $failed_actions)) {
            $has_failed_action = true;
            break;
        }
    }
    
    if ($has_failed_action) {
        return '<span class="status-failed">خرید ناموفق</span>';
    }
    
    // If user viewed product but didn't purchase through consultation flow
    return '<span class="status-abandoned">رها شده</span>';
}

// ---------------------- Check if user went through consultation flow ----------------------
function mattress_advisor_did_user_go_through_consultation($user_actions) {
    if (empty($user_actions)) {
        return false;
    }
    
    // Check for consultation-specific actions
    $consultation_actions = ['add_to_cart', 'view_product', 'successful_purchase'];
    $has_consultation_action = false;
    
    foreach ($user_actions as $action) {
        if (isset($action['action']) && in_array($action['action'], $consultation_actions)) {
            $has_consultation_action = true;
            break;
        }
    }
    
    return $has_consultation_action;
}

// ---------------------- Get consultation order status from WooCommerce ----------------------
function mattress_advisor_get_consultation_order_status($user_id, $product_id, $user_actions) {
    if (!$product_id || !function_exists('wc_get_orders')) {
        return ['status' => 'no_woocommerce', 'order_id' => null];
    }
    
    // Check if user went through consultation flow
    $went_through_consultation = mattress_advisor_did_user_go_through_consultation($user_actions);
    if (!$went_through_consultation) {
        return ['status' => 'no_consultation', 'order_id' => null];
    }
    
    // Get orders for this user
    $orders = [];

    // First, if we have a user_id try to fetch their orders
    if ($user_id) {
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed'],
            'limit' => -1,
        ]);
    }

    // If no orders found via user, try to look up by order meta (history token/id)
    if (empty($orders) && function_exists('get_posts')) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'mattress_history';
        // try to find recent history row for this user or product
        $recent_history = null;
        if ($user_id) {
            $recent_history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $history_table WHERE user_id = %d AND product_id = %d ORDER BY created_at DESC LIMIT 1", $user_id, $product_id));
        }
        // if not found by user, try by product only
        if (!$recent_history) {
            $recent_history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $history_table WHERE product_id = %d ORDER BY created_at DESC LIMIT 1", $product_id));
        }

        if ($recent_history && !empty($recent_history->share_token)) {
            // search orders with meta _mattress_history_token = share_token
            $found = get_posts([
                'post_type' => 'shop_order',
                'posts_per_page' => 1,
                'meta_key' => '_mattress_history_token',
                'meta_value' => $recent_history->share_token,
            ]);
            if (!empty($found)) {
                $orders = array_map('wc_get_order', $found);
            }
        }
    }
    
    if (empty($orders)) {
        return ['status' => 'no_orders', 'order_id' => null];
    }
    
    // Find orders containing the consultation product
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id || $item->get_variation_id() == $product_id) {
                $order_status = $order->get_status();
                
                // Map WooCommerce status to our status
                switch ($order_status) {
                    case 'completed':
                        return ['status' => 'completed', 'order_id' => $order->get_id()];
                    case 'processing':
                        return ['status' => 'processing', 'order_id' => $order->get_id()];
                    case 'on-hold':
                        return ['status' => 'pending', 'order_id' => $order->get_id()];
                    case 'pending':
                        return ['status' => 'pending', 'order_id' => $order->get_id()];
                    case 'cancelled':
                        return ['status' => 'cancelled', 'order_id' => $order->get_id()];
                    case 'failed':
                        return ['status' => 'failed', 'order_id' => $order->get_id()];
                    case 'refunded':
                        return ['status' => 'cancelled', 'order_id' => $order->get_id()];
                    default:
                        return ['status' => 'unknown', 'order_id' => $order->get_id()];
                }
            }
        }
    }
    
    return ['status' => 'no_matching_product', 'order_id' => null];
}

// Map WooCommerce order status string to mattress_history.purchase_status value
function mattress_advisor_map_wc_to_purchase_status($wc_status) {
    switch ($wc_status) {
        case 'completed':
            return 'completed';
        case 'processing':
            return 'processing';
        case 'on-hold':
        case 'pending':
            return 'pending';
        case 'cancelled':
        case 'refunded':
            return 'cancelled';
        case 'failed':
            return 'failed';
        default:
            return 'unknown';
    }
}

// ---------------------- WooCommerce integration for purchase tracking ----------------------
add_action('woocommerce_checkout_order_processed', 'mattress_advisor_track_woocommerce_purchase');
add_action('woocommerce_order_status_changed', 'mattress_advisor_track_order_status_change', 10, 3);

function mattress_advisor_track_woocommerce_purchase($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    $billing_email = $order->get_billing_email();
    if (!$user_id && !$billing_email) return;
    
    // Get the most recent consultation history for this user
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    
    // Try to find a matching recent history: prefer user_id, fallback to matching email for guest orders
    if ($user_id) {
        $recent_history = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    } else {
        $recent_history = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s ORDER BY created_at DESC LIMIT 1",
            $billing_email
        ));
    }
    
    if (!$recent_history) return;
    
    // Check if any of the ordered products match the consultation product
    $consultation_product_id = $recent_history->product_id;
    $ordered_products = [];
    
    foreach ($order->get_items() as $item) {
        $ordered_products[] = $item->get_product_id();
        $ordered_products[] = $item->get_variation_id();
    }
    
    if (in_array($consultation_product_id, $ordered_products)) {
        // Track successful purchase through consultation
        mattress_advisor_track_user_action($recent_history->id, 'successful_purchase');
        // Update stored purchase_status to reflect the actual WooCommerce order status
        $mapped = mattress_advisor_map_wc_to_purchase_status($order->get_status());
        if ($mapped) {
            $wpdb->update(
                $table,
                ['purchase_status' => $mapped, 'order_id' => $order_id],
                ['id' => $recent_history->id],
                ['%s', '%d'],
                ['%d']
            );
        } else {
            // still store order_id
            $wpdb->update(
                $table,
                ['order_id' => $order_id],
                ['id' => $recent_history->id],
                ['%d'],
                ['%d']
            );
        }

        // Persist history linkage into order meta for reliable future mapping
        if (function_exists('update_post_meta')) {
            update_post_meta($order_id, '_mattress_history_id', intval($recent_history->id));
            if (!empty($recent_history->share_token)) {
                update_post_meta($order_id, '_mattress_history_token', sanitize_text_field($recent_history->share_token));
            }
        }
    }
}

function mattress_advisor_track_order_status_change($order_id, $old_status, $new_status) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    $billing_email = $order->get_billing_email();
    if (!$user_id && !$billing_email) return;
    
    // Get the most recent consultation history for this user
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';
    
    if ($user_id) {
        $recent_history = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    } else {
        $recent_history = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s ORDER BY created_at DESC LIMIT 1",
            $billing_email
        ));
    }
    
    if (!$recent_history) return;
    
    // Check if any of the ordered products match the consultation product
    $consultation_product_id = $recent_history->product_id;
    $ordered_products = [];
    
    foreach ($order->get_items() as $item) {
        $ordered_products[] = $item->get_product_id();
        $ordered_products[] = $item->get_variation_id();
    }
    
    if (in_array($consultation_product_id, $ordered_products)) {
        // Track status change based on new status
        // Track user action and also persist purchase_status in history table
        switch ($new_status) {
            case 'completed':
                mattress_advisor_track_user_action($recent_history->id, 'order_completed');
                break;
            case 'processing':
                mattress_advisor_track_user_action($recent_history->id, 'order_processing');
                break;
            case 'cancelled':
                mattress_advisor_track_user_action($recent_history->id, 'order_cancelled');
                break;
            case 'failed':
                mattress_advisor_track_user_action($recent_history->id, 'order_failed');
                break;
        }

        // Persist mapped status and order_id into mattress_history
        $mapped = mattress_advisor_map_wc_to_purchase_status($new_status);
        $update_data = ['order_id' => $order_id];
        $update_formats = ['%d'];
        if ($mapped) {
            $update_data['purchase_status'] = $mapped;
            array_unshift($update_formats, '%s');
        }
        $wpdb->update(
            $table,
            $update_data,
            ['id' => $recent_history->id],
            $update_formats,
            ['%d']
        );

        // Persist history linkage into order meta for reliable future mapping
        if (function_exists('update_post_meta')) {
            update_post_meta($order_id, '_mattress_history_id', intval($recent_history->id));
            if (!empty($recent_history->share_token)) {
                update_post_meta($order_id, '_mattress_history_token', sanitize_text_field($recent_history->share_token));
            }
        }
    }
}

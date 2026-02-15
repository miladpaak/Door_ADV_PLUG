<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', 'mattress_advisor_admin_menu');
add_action('admin_enqueue_scripts', 'mattress_advisor_admin_assets');

function mattress_advisor_admin_menu() {
    add_menu_page(
        'مشاور درب',
        'مشاور درب',
        'manage_options',
        'mattress-advisor',
        'mattress_advisor_rules_page', // changed from mattress_advisor_admin_page
        'dashicons-feedback',
        56
    );

    add_submenu_page(
        'mattress-advisor',
        'مدیریت مشاوره',
        'مدیریت مشاوره',
        'manage_options',
        'mattress-advisor-rules',
        'mattress_advisor_rules_page'
    );

    add_submenu_page(
        'mattress-advisor',
        'تاریخچه مشاوره‌ها',
        'تاریخچه مشاوره‌ها',
        'manage_options',
        'mattress-advisor-history',
        'mattress_advisor_history_page'
    );
    
    add_submenu_page(
        'mattress-advisor',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'mattress-advisor-settings',
        'mattress_advisor_settings_page'
    );

    // remove the default submenu
    remove_submenu_page('mattress-advisor', 'mattress-advisor');
}

function mattress_advisor_admin_assets($hook) {
    // load assets on all plugin pages
    if ( strpos($hook, 'mattress-advisor') === false ) return;

    wp_enqueue_style('mattress-admin-style', MATTRESS_ADVISOR_URL . 'admin/assets/css/admin.css', [], MATTRESS_ADVISOR_VERSION);
    wp_enqueue_script('mattress-admin-script', MATTRESS_ADVISOR_URL . 'admin/assets/js/admin.js', ['jquery'], MATTRESS_ADVISOR_VERSION, true);
    wp_localize_script('mattress-admin-script', 'mattress_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mattress_admin_nonce') // changed nonce
    ]);
}

function mattress_advisor_rules_page() {
    global $wpdb;
    $rules_table = $wpdb->prefix . 'mattress_rules';
    $rules = $wpdb->get_results("SELECT * FROM $rules_table ORDER BY id DESC");

    // get products
    $products = [];
    if ( function_exists('wc_get_products') ) {
        $products = wc_get_products(['limit' => -1]);
    }

    $product_categories = [];
    if ( taxonomy_exists('product_cat') ) {
        $product_categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if ( is_wp_error($product_categories) ) {
            $product_categories = [];
        }
    }

    ?>
    <div class="wrap mattress-advisor-admin">
        <div class="admin-header">
                <h1>
                <img src="<?php echo MATTRESS_ADVISOR_URL . 'assets/logo-placeholder.webp'; ?>" alt="Smart Mattress Advisor" style="height: 40px; width: auto; margin-left: 15px; vertical-align: middle;">
                <span class="dashicons dashicons-admin-settings"></span> 
                مدیریت مشاوره فروش درب
            </h1>
            <p class="description">از این بخش می‌توانید پرسش/پاسخ‌ها و قواعد مشاوره‌ای برای پیشنهاد درب مناسب به کاربران تعریف کنید.</p>
        </div>

        <div class="admin-tabs">
            <button class="tab-button active" data-tab="rules-list">📋 لیست مشاوره‌ها</button>
            <button class="tab-button" data-tab="add-rule">➕ افزودن مشاوره جدید</button>
            <button class="tab-button" data-tab="edit-rule" style="display: none;">✏️ ویرایش قانون</button>
        </div>

        <!-- Tab: Rules List -->
        <div id="rules-list" class="tab-content active">
            <div class="rules-container">
                <div class="rules-header">
                    <h2>مشاوره‌های تعریف شده</h2>
                    <div class="rules-stats">
                        <span class="stat-item">تعداد کل: <strong><?php echo count($rules); ?></strong></span>
                    </div>
                </div>
                
                <?php if ($rules): ?>
                    <div class="rules-filters" style="margin-bottom:15px;display:flex;gap:10px;flex-wrap:wrap;">
                        <input type="text" id="filter-text" placeholder="جستجو در شرایط یا عنوان محصول" style="min-width:220px;">
                        <select id="filter-door-type">
                            <option value="">نوع درب</option>
                            <option value="entrance">ورودی</option>
                            <option value="interior">داخلی</option>
                        </select>
                        <select id="filter-building-type">
                            <option value="">نوع ساختمان</option>
                            <option value="apartment">آپارتمانی</option>
                            <option value="villa">ویلایی</option>
                        </select>
                        <button id="filter-clear" class="button">پاک‌سازی فیلتر</button>
                    </div>
                    <div class="rules-grid">
                        <?php foreach ($rules as $rule): ?>
                            <div class="rule-card" data-id="<?php echo $rule->id; ?>">
                                <div class="rule-header">
                                    <span class="rule-id"><?php echo $rule->id; ?></span>
                                    <div class="rule-actions">
                                        <button class="edit-rule-btn" data-rule-id="<?php echo $rule->id; ?>" title="ویرایش قانون">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button class="delete-rule-btn" data-rule-id="<?php echo $rule->id; ?>" title="حذف قانون">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="rule-product">
                                    <h4>محصول پیشنهادی:</h4>
                                    <p><strong><?php echo esc_html(get_the_title($rule->product_id)); ?></strong></p>
                                </div>
                                
                                <div class="rule-conditions">
                                    <h4>شرایط:</h4>
                                    <div class="conditions-list">
                                        <?php 
                                            $conditions = json_decode($rule->conditions, true);
                                            $condition_labels = [
                                                'door_type' => 'نوع درب',
                                                'building_type' => 'نوع ساختمان',
                                                'weather_exposure' => 'برخورد آفتاب و باران',
                                                'facade_style' => 'سبک نما',
                                                'entrance_material' => 'متریال درب ورودی',
                                                'door_width' => 'عرض درب',
                                                'door_height' => 'ارتفاع درب',
                                                'waterproof' => 'ضدآب',
                                                'metal_frame_installed' => 'چهارچوب فلزی نصب شده',
                                                'usage_space' => 'فضای مورد استفاده',
                                                'interior_style' => 'سبک داخلی',
                                                'color_theme' => 'تم رنگ',
                                                'weatherstrip' => 'نوار درزگیر',
                                                'interior_material' => 'جنس درب داخلی'
                                            ];
                                            // Merge numeric ranges for display
                                            $ranges = [
                                                'door_width' => ['min' => isset($conditions['door_width_min']) ? $conditions['door_width_min'] : null, 'max' => isset($conditions['door_width_max']) ? $conditions['door_width_max'] : null],
                                                'door_height' => ['min' => isset($conditions['door_height_min']) ? $conditions['door_height_min'] : null, 'max' => isset($conditions['door_height_max']) ? $conditions['door_height_max'] : null]
                                            ];
                                            foreach (['door_width','door_height'] as $nk) {
                                                if ($ranges[$nk]['min'] !== null || $ranges[$nk]['max'] !== null) {
                                                    $label = $condition_labels[$nk];
                                                    $min = $ranges[$nk]['min'] !== null ? $ranges[$nk]['min'] : '—';
                                                    $max = $ranges[$nk]['max'] !== null ? $ranges[$nk]['max'] : '—';
                                                    echo '<span class="condition-tag">' . esc_html($label) . ': ' . esc_html($min) . ' تا ' . esc_html($max) . '</span>';
                                                    unset($conditions[$nk.'_min'], $conditions[$nk.'_max']);
                                                    unset($conditions[$nk]);
                                                }
                                            }
                                            foreach($conditions as $key => $val) {
                                                if (!empty($val)) {
                                                    $label = isset($condition_labels[$key]) ? $condition_labels[$key] : $key;
                                                    echo '<span class="condition-tag" data-key="' . esc_attr($key) . '">' . esc_html($label) . ': ' . esc_html($val) . '</span>';
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-rules">
                        <div class="no-rules-icon">📝</div>
                        <h3>هیچ قانونی تعریف نشده است</h3>
                        <p>برای شروع، اولین قانون خود را اضافه کنید.</p>
                        <button class="button button-primary" onclick="switchTab('add-rule')">افزودن قانون جدید</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Edit Rule -->
        <div id="edit-rule" class="tab-content">
            <div class="add-rule-container">
                <div class="form-header">
                    <h2>ویرایش قانون</h2>
                    <p>اطلاعات قانون را ویرایش کنید و تغییرات را ذخیره کنید.</p>
                </div>

                <form id="edit-rule-form" class="modern-form">
                    <div id="edit-rule-message" class="message-container"></div>
                    <input type="hidden" id="edit-rule-id" name="rule_id" value="">
                    
                    <!-- Product Selection -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-products"></span> انتخاب محصول</h3>
                        <div class="form-group">
                            <label for="edit_product_category">دسته‌بندی محصول *</label>
                            <select id="edit_product_category" class="mattress-product-category" data-target-product="#edit_product_id" required>
                                <option value="">یک دسته‌بندی را انتخاب کنید</option>
                                <?php foreach($product_categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_id">محصول پیشنهادی *</label>
                            <select name="product_id" id="edit_product_id" class="mattress-product-select" required disabled>
                                <option value="">ابتدا دسته‌بندی را انتخاب کنید</option>
                                <?php foreach($products as $product): ?>
                                    <?php $category_ids = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']); ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" data-categories="<?php echo esc_attr(implode(',', $category_ids)); ?>"><?php echo esc_html($product->get_name()); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Physical Characteristics -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-admin-users"></span> مشخصات فیزیکی</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>سن (بازه)</label>
                                <div class="range-row">
                                    <input type="number" name="age_min" min="1" max="120" placeholder="حداقل">
                                    <input type="number" name="age_max" min="1" max="120" placeholder="حداکثر">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>قد (سانتی‌متر، بازه)</label>
                                <div class="range-row">
                                    <input type="number" name="height_min" min="100" max="250" placeholder="حداقل">
                                    <input type="number" name="height_max" min="100" max="250" placeholder="حداکثر">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>وزن (کیلوگرم، بازه)</label>
                                <div class="range-row">
                                    <input type="number" name="weight_min" min="30" max="200" placeholder="حداقل">
                                    <input type="number" name="weight_max" min="30" max="200" placeholder="حداکثر">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sleep Preferences -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-rest"></span> ترجیحات خواب</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_back_curve">گودی کمر</label>
                                <select name="back_curve" id="edit_back_curve">
                                    <option value="">انتخاب کنید</option>
                                    <option value="has_curve">دارم</option>
                                    <option value="supports_curve">گزینه مناسب</option>
                                    <option value="not_allowed">خرید مجاز نیست</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_sleep_type">نوع خواب</label>
                                <select name="sleep_type" id="edit_sleep_type">
                                    <option value="">انتخاب کنید</option>
                                    <option value="light">خواب سبک</option>
                                    <option value="heavy">خواب سنگین</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_persons">تعداد نفرات</label>
                                <select name="persons" id="edit_persons">
                                    <option value="">انتخاب کنید</option>
                                    <option value="1">یک نفره</option>
                                    <option value="2">دو نفره</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Mattress Preferences -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-admin-settings"></span> ترجیحات درب</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_quality">کیفیت</label>
                                <select name="quality" id="edit_quality">
                                    <option value="">انتخاب کنید</option>
                                    <option value="excellent">عالی (درجه یک)</option>
                                    <option value="good">مطلوب (درجه دو)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_elasticity">حالت ارتجاعی</label>
                                <select name="elasticity" id="edit_elasticity">
                                    <option value="">انتخاب کنید</option>
                                    <option value="low">کم</option>
                                    <option value="very_low">خیلی کم</option>
                                    <option value="has">دارد</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_back_pain">مشکل کمر</label>
                                <select name="back_pain" id="edit_back_pain">
                                    <option value="">انتخاب کنید</option>
                                    <option value="no">ندارد</option>
                                    <option value="yes">دارد</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Type -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-location"></span> نوع استفاده</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_usage_type">نوع استفاده</label>
                                <select name="usage_type" id="edit_usage_type">
                                    <option value="">انتخاب کنید</option>
                                    <option value="temporary">موقت</option>
                                    <option value="permanent">دائم</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_usage_place">نوع کاربرد</label>
                                <select name="usage_place" id="edit_usage_place">
                                    <option value="">انتخاب کنید</option>
                                    <option value="home">خانه</option>
                                    <option value="villa">ویلا</option>
                                </select>
                            </div>
                        </div>
                    </div>


                    <!-- Door Conditions -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-admin-home"></span> شرایط درب</h3>
                        <div class="form-row">
                            <div class="form-group"><label for="edit_door_type">نوع درب</label><select name="door_type" id="edit_door_type"><option value="">انتخاب کنید</option><option value="entrance">ورودی</option><option value="interior">داخلی</option></select></div>
                            <div class="form-group"><label for="edit_building_type">نوع ساختمان</label><select name="building_type" id="edit_building_type"><option value="">انتخاب کنید</option><option value="apartment">آپارتمانی</option><option value="villa">ویلایی</option></select></div>
                            <div class="form-group"><label for="edit_weather_exposure">برخورد آفتاب و باران</label><select name="weather_exposure" id="edit_weather_exposure"><option value="">انتخاب کنید</option><option value="yes">بله</option><option value="no">خیر</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="edit_facade_style">سبک نما</label><select name="facade_style" id="edit_facade_style"><option value="">انتخاب کنید</option><option value="modern">مدرن</option><option value="neo_classic">نئو کلاسیک</option><option value="classic">کلاسیک</option></select></div>
                            <div class="form-group"><label for="edit_entrance_material">متریال درب ورودی</label><select name="entrance_material" id="edit_entrance_material"><option value="">انتخاب کنید</option><option value="wood">چوب</option><option value="metal">فلزی</option><option value="glass">شیشه</option><option value="thermowood">ترمو وود</option><option value="mdf">MDF</option><option value="synthetic_coating">روکش مصنوعی</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>عرض درب (بازه)</label><div class="range-row"><input type="number" name="door_width_min" min="60" max="350" placeholder="حداقل"><input type="number" name="door_width_max" min="60" max="350" placeholder="حداکثر"></div></div>
                            <div class="form-group"><label>ارتفاع درب (بازه)</label><div class="range-row"><input type="number" name="door_height_min" min="150" max="350" placeholder="حداقل"><input type="number" name="door_height_max" min="150" max="350" placeholder="حداکثر"></div></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="edit_waterproof">ضدآب</label><select name="waterproof" id="edit_waterproof"><option value="">انتخاب کنید</option><option value="water_resistant">مقاوم به آب</option><option value="waterproof">100%ضدآب</option><option value="no">خیر</option></select></div>
                            <div class="form-group"><label for="edit_metal_frame_installed">چهارچوب فلزی نصب شده</label><select name="metal_frame_installed" id="edit_metal_frame_installed"><option value="">انتخاب کنید</option><option value="yes">بله</option><option value="no">خیر</option></select></div>
                            <div class="form-group"><label for="edit_usage_space">فضای مورد استفاده</label><select name="usage_space[]" id="edit_usage_space" multiple><option value="room">اتاق</option><option value="wc">سرویس بهداشتی</option><option value="bathroom">حمام</option><option value="pool">استخر</option><option value="management">اتاق مدیریت</option><option value="conference">کنفرانس</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="edit_interior_style">سبک داخلی</label><select name="interior_style" id="edit_interior_style"><option value="">انتخاب کنید</option><option value="modern">مدرن</option><option value="neo_classic">نئو کلاسیک</option><option value="classic">کلاسیک</option></select></div>
                            <div class="form-group"><label for="edit_color_theme">تم رنگ</label><select name="color_theme" id="edit_color_theme"><option value="">انتخاب کنید</option><option value="colored">رنگی</option><option value="black_theme">تم سیاه</option><option value="light_theme">تم روشن</option></select></div>
                            <div class="form-group"><label for="edit_weatherstrip">نوار درزگیر</label><select name="weatherstrip" id="edit_weatherstrip"><option value="">انتخاب کنید</option><option value="yes">دارد</option><option value="no">ندارد</option></select></div>
                            <div class="form-group"><label for="edit_interior_material">جنس درب داخلی</label><select name="interior_material" id="edit_interior_material"><option value="">انتخاب کنید</option><option value="mdf">MDF</option><option value="melamine">ملامینه</option><option value="abs">ABS</option><option value="polywood">پلی وود</option></select></div>
                        </div>
                    </div>

                    <!-- Product Features -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-star-filled"></span> ویژگی‌های کلیدی محصول</h3>
                        <div class="form-group">
                            <label for="edit_key_features">ویژگی‌های کلیدی (هر ویژگی در یک خط)</label>
                            <textarea name="key_features" id="edit_key_features" rows="4" placeholder="مثال:&#10;طراحی ارتوپدیک برای مشکلات کمر&#10;فوم مموری فشرده با تراکم بالا&#10;تأیید شده توسط متخصصین فیزیوتراپی&#10;تهویه مناسب برای درجه حرارت بدن"></textarea>
                        </div>
                    </div>

                    <!-- Why Suitable -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-lightbulb"></span> چرا این درب برای شما مناسب است؟</h3>
                        <div class="form-group">
                            <label for="edit_why_suitable">توضیحات مناسب بودن</label>
                            <textarea name="why_suitable" id="edit_why_suitable" rows="4" placeholder="مثال:&#10;به دلیل وزن بالاتر از ۸۰ کیلوگرم، درب‌های مقاوم با متریال مناسب برای شما مناسب هستند.&#10;چون خواب سنگین دارید، درب با کیفیت و دوام بالا برای شما توصیه می‌شود.&#10;با توجه به مشکل کمر، درب مناسب فضای شما پیشنهاد می‌شود."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <!-- دکمه پیش‌نمایش حذف شد -->
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-yes"></span> ذخیره تغییرات
                        </button>
                        <button type="button" class="button button-secondary" onclick="switchTab('rules-list')">انصراف</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Add New Rule -->
        <div id="add-rule" class="tab-content">
            <div class="add-rule-container">
                <div class="form-header">
                    <h2>افزودن قانون جدید</h2>
                    <p>برای تعریف قانون جدید، ابتدا محصول مورد نظر را انتخاب کنید، سپس شرایط مربوط به آن را مشخص کنید.</p>
                </div>

                <form id="add-rule-form" class="modern-form">
                    <div id="rule-message" class="message-container"></div>
                    
                    <!-- Product Selection -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-products"></span> انتخاب محصول</h3>
                        <div class="form-group">
                            <label for="product_category">دسته‌بندی محصول *</label>
                            <select id="product_category" class="mattress-product-category" data-target-product="#product_id" required>
                                <option value="">یک دسته‌بندی را انتخاب کنید</option>
                                <?php foreach($product_categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_id">محصول پیشنهادی *</label>
                            <select name="product_id" id="product_id" class="mattress-product-select" required disabled>
                                <option value="">ابتدا دسته‌بندی را انتخاب کنید</option>
                                <?php foreach($products as $product): ?>
                                    <?php $category_ids = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']); ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" data-categories="<?php echo esc_attr(implode(',', $category_ids)); ?>"><?php echo esc_html($product->get_name()); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Door Conditions -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-admin-home"></span> شرایط درب</h3>
                        <div class="form-row">
                            <div class="form-group"><label for="door_type">نوع درب</label><select name="door_type" id="door_type"><option value="">انتخاب کنید</option><option value="entrance">ورودی</option><option value="interior">داخلی</option></select></div>
                        </div>

                        <div class="door-conditions-subsection">
                            <h4>درب ورودی</h4>
                            <div class="form-row">
                                <div class="form-group"><label for="building_type">نوع ساختمان</label><select name="building_type" id="building_type"><option value="">انتخاب کنید</option><option value="apartment">آپارتمانی</option><option value="villa">ویلایی</option></select></div>
                                <div class="form-group"><label for="weather_exposure">برخورد آفتاب و باران</label><select name="weather_exposure" id="weather_exposure"><option value="">انتخاب کنید</option><option value="yes">بله</option><option value="no">خیر</option></select></div>
                                <div class="form-group"><label for="facade_style">سبک نما</label><select name="facade_style" id="facade_style"><option value="">انتخاب کنید</option><option value="modern">مدرن</option><option value="neo_classic">نئو کلاسیک</option><option value="classic">کلاسیک</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="entrance_material">متریال درب ورودی</label><select name="entrance_material[]" id="entrance_material" multiple><option value="wood">چوب</option><option value="metal">فلزی</option><option value="glass">شیشه</option><option value="thermowood">ترمو وود</option><option value="mdf">MDF</option><option value="synthetic_coating">روکش مصنوعی</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>عرض درب</label><small class="field-help">ارتفاع تا 235 و عرض تا 220 برای درب ضد سرقت</small><div class="range-row"><input type="number" name="door_width_max" min="60" max="350" placeholder="واحد به سانتی متر"></div></div>
                                <div class="form-group"><label>ارتفاع درب (بازه)</label><div class="range-row"><input type="number" name="door_height_min" min="150" max="350" placeholder="حداقل"><input type="number" name="door_height_max" min="150" max="350" placeholder="حداکثر"></div></div>
                            </div>
                        </div>

                        <div class="door-conditions-subsection">
                            <h4>درب داخلی</h4>
                            <div class="form-row">
                                <div class="form-group"><label for="waterproof">مقاوم به آب</label><select name="waterproof" id="waterproof"><option value="">انتخاب کنید</option><option value="water_resistant">مقاوم به آب</option><option value="waterproof">100%ضدآب</option><option value="no">خیر</option></select></div>
                                <div class="form-group"><label for="metal_frame_installed">چهارچوب فلزی نصب شده</label><select name="metal_frame_installed" id="metal_frame_installed"><option value="">انتخاب کنید</option><option value="yes">بله</option><option value="no">خیر</option></select></div>
                                <div class="form-group"><label for="usage_space">فضای مورد استفاده</label><select name="usage_space[]" id="usage_space" multiple><option value="room">اتاق</option><option value="wc">سرویس بهداشتی</option><option value="bathroom">حمام</option><option value="pool">استخر</option><option value="management">اتاق مدیریت</option><option value="conference">کنفرانس</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="interior_style">سبک داخلی</label><select name="interior_style" id="interior_style"><option value="">انتخاب کنید</option><option value="modern">مدرن</option><option value="neo_classic">نئو کلاسیک</option><option value="classic">کلاسیک</option></select></div>
                                <div class="form-group"><label for="color_theme">تم رنگ</label><select name="color_theme" id="color_theme"><option value="">انتخاب کنید</option><option value="colored">رنگی</option><option value="black_theme">تم سیاه</option><option value="light_theme">تم روشن</option></select></div>
                                <div class="form-group"><label for="weatherstrip">نوار درزگیر</label><select name="weatherstrip" id="weatherstrip"><option value="">انتخاب کنید</option><option value="yes">دارد</option><option value="no">ندارد</option></select></div>
                                <div class="form-group"><label for="interior_material">جنس درب داخلی</label><select name="interior_material" id="interior_material"><option value="">انتخاب کنید</option><option value="mdf">MDF</option><option value="melamine">ملامینه</option><option value="abs">ABS</option><option value="polywood">پلی وود</option></select></div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Features -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-star-filled"></span> ویژگی‌های کلیدی محصول</h3>
                        <div class="form-group">
                            <label for="key_features">ویژگی‌های کلیدی (هر ویژگی در یک خط)</label>
                            <textarea name="key_features" id="key_features" rows="4" placeholder="مثال:&#10;طراحی ارتوپدیک برای مشکلات کمر&#10;فوم مموری فشرده با تراکم بالا&#10;تأیید شده توسط متخصصین فیزیوتراپی&#10;تهویه مناسب برای درجه حرارت بدن"></textarea>
                        </div>
                    </div>

                    <!-- Why Suitable -->
                    <div class="form-section">
                        <h3><span class="dashicons dashicons-lightbulb"></span> چرا این درب برای شما مناسب است؟</h3>
                        <div class="form-group">
                            <label for="why_suitable">توضیحات مناسب بودن</label>
                            <textarea name="why_suitable" id="why_suitable" rows="4" placeholder="مثال:&#10;به دلیل وزن بالاتر از ۸۰ کیلوگرم، درب‌های مقاوم با متریال مناسب برای شما مناسب هستند.&#10;چون خواب سنگین دارید، درب با کیفیت و دوام بالا برای شما توصیه می‌شود.&#10;با توجه به مشکل کمر، درب مناسب فضای شما پیشنهاد می‌شود."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="button button-secondary button-preview">
                            <span class="dashicons dashicons-visibility"></span> پیش‌نمایش
                        </button>
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-plus-alt"></span> افزودن قانون
                        </button>
                        <button type="reset" class="button button-secondary">پاک کردن فرم</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function mattress_advisor_history_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'mattress_history';

    // Params: pagination and sorting
    $per_page = isset($_GET['per_page']) ? max(1, min(200, intval($_GET['per_page']))) : 20;
    $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $allowed_orderby = ['created_at', 'id', 'product_id', 'order_id', 'purchase_status'];
    $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby, true)) ? $_GET['orderby'] : 'created_at';
    $order   = (isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC','DESC'], true)) ? strtoupper($_GET['order']) : 'DESC';

    // Total count and pages
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_pages = max(1, (int) ceil($total / $per_page));
    if ($paged > $total_pages) { $paged = $total_pages; }
    $offset = ($paged - 1) * $per_page;

    // Build query (safe: column name whitelisted, order validated)
    $query = "SELECT * FROM $table ORDER BY $orderby $order LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

    // Helpers
    $base_admin = admin_url('admin.php');
    $toggle = ($order === 'ASC') ? 'DESC' : 'ASC';
    $sort_indicator = function($col) use ($orderby, $order) {
        if ($orderby === $col) {
            return '<span class="sort-indicator">' . ($order === 'ASC' ? '↑' : '↓') . '</span>';
        }
        return '';
    };

    // Handle status messages
    $msg = '';
    if (isset($_GET['deleted']) && is_numeric($_GET['deleted'])) {
        $deleted = intval($_GET['deleted']);
        if ($deleted > 0) {
            $msg = '<div class="notice notice-success is-dismissible"><p>' . sprintf('حذف %d رکورد با موفقیت انجام شد.', $deleted) . '</p></div>';
        } else {
            $msg = '<div class="notice notice-warning is-dismissible"><p>رکوردی برای حذف انتخاب نشده بود.</p></div>';
        }
    }

    ?>
    <div class="wrap mattress-advisor-admin">
        <div class="admin-header">
            <h1>
                <img src="<?php echo MATTRESS_ADVISOR_URL . 'assets/logo-placeholder.webp'; ?>" alt="Smart Mattress Advisor" style="height: 40px; width: auto; margin-left: 15px; vertical-align: middle;">
                <span class="dashicons dashicons-clock"></span> 
                تاریخچه مشاوره‌ها
            </h1>
            <p class="description">مشاهده تاریخچه درخواست‌های مشاوره کاربران و نتایج ارائه شده.</p>
        </div>
        <?php if ($msg) { echo $msg; } ?>
        
        <div class="tab-content active">
            <div class="history-filters" style="margin:10px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="history-q" placeholder="جستجو در اطلاعات کاربر" style="min-width:220px;">
                <input type="date" id="history-from" placeholder="از تاریخ">
                <input type="date" id="history-to" placeholder="تا تاریخ">

                <form method="get" action="<?php echo esc_url( $base_admin ); ?>" class="history-controls" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="page" value="mattress-advisor-history">
                    <label>مرتب‌سازی:
                            <select name="orderby">
                            <option value="created_at" <?php selected($orderby,'created_at'); ?>>زمان</option>
                            <option value="id" <?php selected($orderby,'id'); ?>>شناسه</option>
                            <option value="product_id" <?php selected($orderby,'product_id'); ?>>شناسه محصول</option>
                            <option value="order_id" <?php selected($orderby,'order_id'); ?>>شناسه سفارش</option>
                            <option value="purchase_status" <?php selected($orderby,'purchase_status'); ?>>وضعیت مشاوره</option>
                        </select>
                    </label>
                    <label>ترتیب:
                        <select name="order">
                            <option value="DESC" <?php selected($order,'DESC'); ?>>نزولی</option>
                            <option value="ASC" <?php selected($order,'ASC'); ?>>صعودی</option>
                        </select>
                    </label>
                    <label>در هر صفحه:
                        <select name="per_page">
                            <?php foreach ([10,20,50,100,200] as $pp): ?>
                                <option value="<?php echo (int) $pp; ?>" <?php selected($per_page,$pp); ?>><?php echo (int) $pp; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="button">اعمال</button>
                </form>

                <div class="history-actions" style="display:flex;gap:8px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="select-all"> انتخاب همه
                    </label>
                    <a href="<?php echo esc_url( admin_url('admin-post.php?action=mattress_export_csv&format=csv&_wpnonce=' . wp_create_nonce('mattress_export_csv')) ); ?>" class="button">خروجی CSV</a>
                    <a href="<?php echo esc_url( admin_url('admin-post.php?action=mattress_export_csv&format=excel&_wpnonce=' . wp_create_nonce('mattress_export_csv')) ); ?>" class="button">خروجی Excel</a>
                    <form id="csv-selected-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="hidden" name="action" value="mattress_export_csv">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('mattress_export_csv'); ?>">
                        <button type="button" class="button" id="csv-selected">CSV منتخب</button>
                    </form>
                    <?php /* Open delete form here so it can wrap the table below. It will be closed after the table. */ ?>
                    <form id="delete-selected-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="hidden" name="action" value="mattress_delete_history">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('mattress_delete_history'); ?>">
                        <button type="button" class="button button-danger" id="delete-selected">حذف انتخاب‌شده‌ها</button>
                    <!-- form intentionally left open to include the table checkboxes -->
                    
                </div>
            </div>

            <table class="widefat striped" id="mattress-history-table">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="select-all-header" title="انتخاب همه">
                        </th>
                        <th>
                            <a class="sortable<?php echo $orderby==='id' ? ' sorted':''; ?>" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','orderby'=>'id','order'=> ($orderby==='id'?$toggle:'ASC'),'per_page'=>$per_page,'paged'=>$paged], $base_admin) ); ?>"># <?php echo $sort_indicator('id'); ?></a>
                        </th>
                        <th>
                            <a class="sortable<?php echo $orderby==='created_at' ? ' sorted':''; ?>" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','orderby'=>'created_at','order'=> ($orderby==='created_at'?$toggle:'DESC'),'per_page'=>$per_page,'paged'=>$paged], $base_admin) ); ?>">زمان <?php echo $sort_indicator('created_at'); ?></a>
                        </th>
                        <th>نام و نام خانوادگی</th>
                        <th>موبایل</th>
                        <th>استان</th>
                        <th>
                            <a class="sortable<?php echo $orderby==='product_id' ? ' sorted':''; ?>" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','orderby'=>'product_id','order'=> ($orderby==='product_id'?$toggle:'ASC'),'per_page'=>$per_page,'paged'=>$paged], $base_admin) ); ?>">محصول پیشنهادی <?php echo $sort_indicator('product_id'); ?></a>
                        </th>
                        <th>قیمت محصول</th>
                        <th>
                            <a class="sortable<?php echo $orderby==='order_id' ? ' sorted':''; ?>" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','orderby'=>'order_id','order'=> ($orderby==='order_id'?$toggle:'ASC'),'per_page'=>$per_page,'paged'=>$paged], $base_admin) ); ?>">کد سفارش <?php echo $sort_indicator('order_id'); ?></a>
                        </th>
                        <th>
                            <a class="sortable<?php echo $orderby==='purchase_status' ? ' sorted':''; ?>" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','orderby'=>'purchase_status','order'=> ($orderby==='purchase_status'?$toggle:'ASC'),'per_page'=>$per_page,'paged'=>$paged], $base_admin) ); ?>">وضعیت مشاوره <?php echo $sort_indicator('purchase_status'); ?></a>
                        </th>
                    </tr>
                </thead>
                <tbody>
    <?php
    if ($rows) {
        foreach ($rows as $r) {
            $form_data = json_decode($r->form_data, true);
            
            // Combine name fields into single full name
            $full_name = '-';
            if (isset($form_data['first_name']) || isset($form_data['last_name'])) {
                $first_name = isset($form_data['first_name']) && $form_data['first_name'] !== '' ? trim($form_data['first_name']) : '';
                $last_name = isset($form_data['last_name']) && $form_data['last_name'] !== '' ? trim($form_data['last_name']) : '';
                $full_name = trim($first_name . ' ' . $last_name);
                if ($full_name === '') $full_name = '-';
            } elseif (!empty($form_data['full_name'])) {
                $full_name = trim((string) $form_data['full_name']);
                if ($full_name === '') $full_name = '-';
            }
            
            $mobile     = isset($form_data['mobile']) ? esc_html($form_data['mobile']) : '-';
            $province_raw   = isset($form_data['province']) ? $form_data['province'] : '';
            $province   = $province_raw ? esc_html( mattress_advisor_get_province_name($province_raw) ) : '-';
            $prod_name  = $r->product_id ? esc_html( get_the_title( $r->product_id ) ) : '-';
            
            // Get product price
            $product_price = '-';
            if ($r->product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($r->product_id);
                if ($product) {
                    $product_price = '<span class="product-price">' . $product->get_price_html() . '</span>';
                }
            }

            // Try to resolve an associated WooCommerce order. Prefer stored order_id, otherwise attempt to discover one.
            $order_id = !empty($r->order_id) ? intval($r->order_id) : null;
            if (!$order_id) {
                $user_actions = $r->user_actions ? json_decode($r->user_actions, true) : [];
                $order_status = mattress_advisor_get_consultation_order_status($r->user_id, $r->product_id, $user_actions);
                if (!empty($order_status['order_id'])) {
                    $order_id = intval($order_status['order_id']);
                }
            }

            $purchase_status = '';
            $order_number_display = '';

            if ($order_id && function_exists('wc_get_order')) {
                $order_obj = wc_get_order($order_id);
                if ($order_obj) {
                    // Display exact WooCommerce order status (with a Persian label) and order number
                    $wc_status = $order_obj->get_status();
                    switch ($wc_status) {
                        case 'completed':
                            $purchase_status = '<span class="status-success">خرید موفق (completed)</span>';
                            break;
                        case 'processing':
                            $purchase_status = '<span class="status-processing">در حال پردازش (processing)</span>';
                            break;
                        case 'on-hold':
                            $purchase_status = '<span class="status-pending">نگهداری/در انتظار (on-hold)</span>';
                            break;
                        case 'pending':
                            $purchase_status = '<span class="status-pending">در انتظار پرداخت (pending)</span>';
                            break;
                        case 'cancelled':
                            $purchase_status = '<span class="status-cancelled">لغو شده (cancelled)</span>';
                            break;
                        case 'refunded':
                            $purchase_status = '<span class="status-cancelled">مرجوع شده (refunded)</span>';
                            break;
                        case 'failed':
                            $purchase_status = '<span class="status-failed">پرداخت ناموفق (failed)</span>';
                            break;
                        default:
                            $purchase_status = '<span class="status-unknown">' . esc_html($wc_status) . '</span>';
                            break;
                    }

                    // order number (uses get_order_number if available)
                    $order_number = method_exists($order_obj, 'get_order_number') ? $order_obj->get_order_number() : $order_obj->get_id();
                    $order_number_display = '<div class="order-number" style="font-size:12px;color:#666;margin-top:4px;">کد سفارش: #' . esc_html($order_number) . '</div>';

                    // Add edit link
                    $order_link = admin_url('post.php?post=' . $order_obj->get_id() . '&action=edit');
                    $purchase_status .= ' <a href="' . esc_url($order_link) . '" target="_blank" style="margin-right: 5px; font-size: 12px;">[مشاهده سفارش]</a>';
                }
            }

            // If we couldn't find an order, fallback to previous logic
            if ($purchase_status === '') {
                $is_form_complete = mattress_advisor_is_form_complete($form_data);
                $purchase_status = mattress_advisor_determine_purchase_status($r, $form_data);
                $user_actions = $r->user_actions ? json_decode($r->user_actions, true) : [];
                $order_status = mattress_advisor_get_consultation_order_status($r->user_id, $r->product_id, $user_actions);
                if (!empty($order_status['order_id'])) {
                    $order_link = admin_url('post.php?post=' . $order_status['order_id'] . '&action=edit');
                    $purchase_status .= ' <a href="' . esc_url($order_link) . '" target="_blank" style="margin-right: 5px; font-size: 12px;">[مشاهده سفارش]</a>';
                    if (function_exists('wc_get_order')) {
                        $o = wc_get_order($order_status['order_id']);
                        if ($o) {
                            $order_number_display = '<div class="order-number" style="font-size:12px;color:#666;margin-top:4px;">کد سفارش: #' . esc_html(method_exists($o,'get_order_number') ? $o->get_order_number() : $o->get_id()) . '</div>';
                        }
                    }
                }
            }

                                        echo "<tr>
                                        <td><input type=\"checkbox\" class=\"row-select\" name=\"ids[]\" value=\"{$r->id}\" /></td>
                                        <td>{$r->id}</td>
                                        <td>{$r->created_at}</td>
                                        <td>" . esc_html($full_name) . "</td>
                                        <td>{$mobile}</td>
                                        <td>{$province}</td>
                                        <td>{$prod_name}</td>
                                        <td>{$product_price}</td>
                                        <td>{$order_number_display}</td>
                                        <td>{$purchase_status}</td>
                                    </tr>";
        }
    } else {
        echo '<tr><td colspan="10" style="text-align: center; padding: 40px;">هیچ تاریخچه‌ای یافت نشد</td></tr>';
    }
    ?>
                </tbody>
            </table>

            </form> <!-- close delete-selected-form -->

            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top:16px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span class="page-info">صفحه <?php echo (int) $paged; ?> از <?php echo (int) $total_pages; ?> | کل: <?php echo (int) $total; ?></span>
                <?php 
                    $prev = max(1, $paged - 1); 
                    $next = min($total_pages, $paged + 1);
                ?>
                <a class="button" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','paged'=>1,'per_page'=>$per_page,'orderby'=>$orderby,'order'=>$order], $base_admin) ); ?>">اول</a>
                <a class="button" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','paged'=>$prev,'per_page'=>$per_page,'orderby'=>$orderby,'order'=>$order], $base_admin) ); ?>">قبلی</a>
                <?php
                    // show up to 7 numeric links around current page
                    $start = max(1, $paged - 3);
                    $end   = min($total_pages, $paged + 3);
                    for ($i = $start; $i <= $end; $i++) {
                        $cls = ($i === $paged) ? 'button button-primary' : 'button';
                        echo '<a class="' . $cls . '" href="' . esc_url( add_query_arg(['page'=>'mattress-advisor-history','paged'=>$i,'per_page'=>$per_page,'orderby'=>$orderby,'order'=>$order], $base_admin) ) . '">' . (int) $i . '</a>';
                    }
                ?>
                <a class="button" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','paged'=>$next,'per_page'=>$per_page,'orderby'=>$orderby,'order'=>$order], $base_admin) ); ?>">بعدی</a>
                <a class="button" href="<?php echo esc_url( add_query_arg(['page'=>'mattress-advisor-history','paged'=>$total_pages,'per_page'=>$per_page,'orderby'=>$orderby,'order'=>$order], $base_admin) ); ?>">آخر</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Settings page and registration
add_action('admin_init', 'mattress_advisor_register_settings');

function mattress_advisor_register_settings() {
    register_setting(
        'mattress_advisor_display_settings',
        'mattress_advisor_display_options',
        'mattress_advisor_settings_sanitize'
    );

    register_setting(
        'mattress_advisor_display_settings',
        'mattress_advisor_style_options',
        'mattress_advisor_style_sanitize'
    );

    add_settings_section(
        'mattress_advisor_display_section',
        'کدام بخش‌ها در صفحه نتیجه نمایش داده شوند؟',
        null,
        'mattress-advisor-settings'
    );

    add_settings_field(
        'show_attributes',
        'نمایش ویژگی‌های محصول',
        'mattress_advisor_show_attributes_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'show_categories',
        'نمایش دسته‌بندی‌های محصول',
        'mattress_advisor_show_categories_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'show_tags',
        'نمایش تگ‌های محصول',
        'mattress_advisor_show_tags_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'show_description',
        'نمایش توضیحات محصول',
        'mattress_advisor_show_description_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'show_short_description',
        'نمایش توضیحات کوتاه محصول',
        'mattress_advisor_show_short_description_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    // New: Show recommended product on result page (last step)
    add_settings_field(
        'show_recommendation',
        'نمایش محصولات پیشنهادی در مرحله آخر',
        'mattress_advisor_show_recommendation_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'primary_color',
        'رنگ اصلی فرم',
        'mattress_advisor_primary_color_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'accent_color',
        'رنگ تیترها',
        'mattress_advisor_accent_color_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );

    add_settings_field(
        'bg_color',
        'رنگ پس‌زمینه فرم',
        'mattress_advisor_bg_color_callback',
        'mattress-advisor-settings',
        'mattress_advisor_display_section'
    );
}

function mattress_advisor_show_attributes_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_attributes']) ? 'checked' : '';
    echo '<input type="checkbox" name="mattress_advisor_display_options[show_attributes]" value="1" ' . $checked . ' />';
}

function mattress_advisor_show_categories_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_categories']) ? 'checked' : '';
    echo '<input type="checkbox" name="mattress_advisor_display_options[show_categories]" value="1" ' . $checked . ' />';
}

function mattress_advisor_show_tags_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_tags']) ? 'checked' : '';
    echo '<input type="checkbox" name="mattress_advisor_display_options[show_tags]" value="1" ' . $checked . ' />';
}

function mattress_advisor_show_description_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_description']) ? 'checked' : '';
    echo '<input type="checkbox" name="mattress_advisor_display_options[show_description]" value="1" ' . $checked . ' />';
}

function mattress_advisor_show_short_description_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_short_description']) ? 'checked' : '';
    echo '<input type="checkbox" name="mattress_advisor_display_options[show_short_description]" value="1" ' . $checked . ' />';
}

function mattress_advisor_show_recommendation_callback() {
    $options = get_option('mattress_advisor_display_options');
    $checked = !empty($options['show_recommendation']) ? 'checked' : '';
    echo '<label><input type="checkbox" name="mattress_advisor_display_options[show_recommendation]" value="1" ' . $checked . ' /> نمایش محصول پیشنهادی نزدیک به انتخاب</label>';
}

function mattress_advisor_settings_sanitize($input) {
    // Ensure all known keys are stored explicitly as 1 (enabled) or 0 (disabled)
    $keys = [
        'show_attributes',
        'show_categories',
        'show_tags',
        'show_description',
        'show_short_description',
        'show_recommendation',
    ];
    $sanitized = [];
    foreach ($keys as $key) {
        $sanitized[$key] = isset($input[$key]) ? 1 : 0;
    }
    return $sanitized;
}


function mattress_advisor_primary_color_callback() {
    $options = get_option('mattress_advisor_style_options', []);
    $value = !empty($options['primary_color']) ? $options['primary_color'] : '#4CAF50';
    echo '<input type="color" name="mattress_advisor_style_options[primary_color]" value="' . esc_attr($value) . '" />';
}

function mattress_advisor_accent_color_callback() {
    $options = get_option('mattress_advisor_style_options', []);
    $value = !empty($options['accent_color']) ? $options['accent_color'] : '#1f2937';
    echo '<input type="color" name="mattress_advisor_style_options[accent_color]" value="' . esc_attr($value) . '" />';
}

function mattress_advisor_bg_color_callback() {
    $options = get_option('mattress_advisor_style_options', []);
    $value = !empty($options['bg_color']) ? $options['bg_color'] : '#ffffff';
    echo '<input type="color" name="mattress_advisor_style_options[bg_color]" value="' . esc_attr($value) . '" />';
}

function mattress_advisor_style_sanitize($input) {
    return [
        'primary_color' => isset($input['primary_color']) ? sanitize_hex_color($input['primary_color']) : '#4CAF50',
        'accent_color'  => isset($input['accent_color']) ? sanitize_hex_color($input['accent_color']) : '#1f2937',
        'bg_color'      => isset($input['bg_color']) ? sanitize_hex_color($input['bg_color']) : '#ffffff',
    ];
}

function mattress_advisor_settings_page() {
    ?>
    <div class="wrap">
        <h1>تنظیمات نمایش اطلاعات محصول</h1>
        <p>از این بخش می‌توانید اطلاعاتی که در صفحه نتیجه به کاربر نمایش داده می‌شود را مدیریت کنید.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('mattress_advisor_display_settings');
            do_settings_sections('mattress-advisor-settings');
            submit_button('ذخیره تنظیمات');
            ?>
        </form>
    </div>
    <?php
}

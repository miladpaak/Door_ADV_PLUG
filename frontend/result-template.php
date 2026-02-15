<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mattress_advisor_render_result( $product, $form_data, $related_products = [], $display_options = [], $history_id = null ) {
    global $wpdb;
    $rules_table = $wpdb->prefix . 'mattress_rules';
    
    // Get rule data with key features and why suitable
    $rule = $wpdb->get_row($wpdb->prepare(
        "SELECT key_features, why_suitable FROM $rules_table WHERE product_id = %d LIMIT 1",
        $product->get_id()
    ));
    
    ob_start();
    ?>
    <div class="mattress-result-card" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-history-id="<?php echo esc_attr( $history_id ); ?>">
        <!-- Success Header -->
        <div class="result-header">
            <div class="success-icon">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="#4CAF50"/>
                    <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2>تبریک!</h2>
            <p>بهترین درب متناسب با نیاز شما پیدا شد</p>
        </div>

        <!-- Product Main Section -->
        <div class="product-showcase">
            <div class="product-badge">پیشنهاد ویژه</div>
            <div class="product-image">
                <?php
                // Use responsive image with srcset/sizes for faster loading
                $main_image_id = method_exists($product, 'get_image_id') ? $product->get_image_id() : 0;
                if ( $main_image_id ) {
                    echo wp_get_attachment_image(
                        $main_image_id,
                        'medium',
                        false,
                        [
                            'class' => 'product-main-image',
                            'loading' => 'eager',
                            'decoding' => 'async',
                            'fetchpriority' => 'high',
                            'alt' => $product->get_name(),
                        ]
                    );
                } else {
                    // Fallback to URL if attachment ID is missing
                    echo '<img class="product-main-image" src="' . esc_url( get_the_post_thumbnail_url( $product->get_id(), 'medium' ) ) . '" alt="' . esc_attr( $product->get_name() ) . '" loading="eager" decoding="async" fetchpriority="high">';
                }
                ?>
            </div>
            <div class="product-details">
                <h3 class="product-title"><?php echo esc_html( $product->get_name() ); ?></h3>
                <div class="product-price"><?php echo $product->get_price_html(); ?></div>
                <div class="product-actions">
                    <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="btn btn-primary">
                        <span class="btn-icon">👁️</span>
                        مشاهده محصول
                    </a>
                    <a href="<?php echo esc_url( add_query_arg('add-to-cart', $product->get_id(), get_permalink( $product->get_id() ) ) ); ?>" class="btn btn-success purchase-btn" data-history-id="<?php echo esc_attr( $history_id ); ?>">
                        <span class="btn-icon">🛒</span>
                        افزودن به سبد خرید
                    </a>
                </div>
            </div>
        </div>

        <?php
        $show_short_description = isset($display_options['show_short_description']) && $display_options['show_short_description'];
        if ($show_short_description && $product->get_short_description()) :
        ?>
        <!-- Product Short Description -->
        <div class="product-description-section">
            <h3 class="section-title">
                <span class="section-icon">📄</span>
                توضیحات کوتاه
            </h3>
            <div class="description-content">
                <?php echo apply_filters('the_content', $product->get_short_description()); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $show_description = isset($display_options['show_description']) && $display_options['show_description'];
        if ($show_description && $product->get_description()) :
        ?>
        <!-- Product Description -->
        <div class="product-description-section">
            <h3 class="section-title">
                <span class="section-icon">📝</span>
                توضیحات کامل
            </h3>
            <div class="description-content">
                <?php echo apply_filters('the_content', $product->get_description()); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Key Features Section -->
        <div class="features-section">
            <h3 class="section-title">
                <span class="section-icon">⭐</span>
                ویژگی‌های کلیدی
            </h3>
            <div class="features-grid">
                <?php 
                if ($rule && !empty($rule->key_features)) {
                    $features = explode("\n", trim($rule->key_features));
                    foreach ($features as $feature) {
                        $feature = trim($feature);
                        if (!empty($feature)) {
                            echo '<div class="feature-item">';
                            echo '<span class="feature-icon">✓</span>';
                            echo '<span class="feature-text">' . esc_html($feature) . '</span>';
                            echo '</div>';
                        }
                    }
                } else {
                    // Default features if none specified
                    echo '<div class="feature-item"><span class="feature-icon">✓</span><span class="feature-text">کیفیت بالا و مواد درجه یک</span></div>';
                    echo '<div class="feature-item"><span class="feature-icon">✓</span><span class="feature-text">طراحی ارگونومیک و راحت</span></div>';
                    echo '<div class="feature-item"><span class="feature-icon">✓</span><span class="feature-text">مناسب برای شرایط شما</span></div>';
                    echo '<div class="feature-item"><span class="feature-icon">✓</span><span class="feature-text">گارانتی و خدمات پس از فروش</span></div>';
                }
                ?>
            </div>
        </div>

        <!-- Why Suitable Section -->
        <div class="why-suitable-section">
            <h3 class="section-title">
                <span class="section-icon">💡</span>
                چرا این درب برای شما مناسب است؟
            </h3>
            <div class="suitability-content">
                <?php 
                if ($rule && !empty($rule->why_suitable)) {
                    $reasons = explode("\n", trim($rule->why_suitable));
                    foreach ($reasons as $reason) {
                        $reason = trim($reason);
                        if (!empty($reason)) {
                            echo '<div class="reason-item">';
                            echo '<span class="reason-icon">🎯</span>';
                            echo '<p class="reason-text">' . esc_html($reason) . '</p>';
                            echo '</div>';
                        }
                    }
                } else {
                    // Default explanation if none specified
                    echo '<div class="reason-item">';
                    echo '<span class="reason-icon">🎯</span>';
                    echo '<p class="reason-text">' . mattress_advisor_explain_choice( $form_data ) . '</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Product Meta Section -->
        <div class="product-meta-section">
            <?php
            $show_attributes = isset($display_options['show_attributes']) && $display_options['show_attributes'];
            $show_categories = isset($display_options['show_categories']) && $display_options['show_categories'];
            $show_tags = isset($display_options['show_tags']) && $display_options['show_tags'];

            if ($show_attributes && $product->has_attributes()) : 
                $attributes = $product->get_attributes();
            ?>
                <div class="meta-block">
                    <h4 class="meta-title">ویژگی‌های محصول:</h4>
                    <div class="meta-content attributes-list">
                        <?php foreach ($attributes as $attribute) : ?>
                            <div class="attribute-item">
                                <span class="attribute-name"><?php echo wc_attribute_label($attribute->get_name()); ?>:</span>
                                <span class="attribute-value">
                                    <?php
                                    $values = [];
                                    if ($attribute->is_taxonomy()) {
                                        $attribute_taxonomy = $attribute->get_taxonomy_object();
                                        $attribute_values = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                                        foreach ($attribute_values as $attribute_value) {
                                            $values[] = esc_html($attribute_value->name);
                                        }
                                    } else {
                                        $values = $attribute->get_options();
                                    }
                                    echo implode(', ', $values);
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($show_categories) :
                $categories = wc_get_product_category_list($product->get_id());
                if ($categories) :
            ?>
                <div class="meta-block">
                    <h4 class="meta-title">دسته‌بندی‌ها:</h4>
                    <div class="meta-content tags-list">
                        <?php echo $categories; ?>
                    </div>
                </div>
            <?php endif; endif; ?>

            <?php if ($show_tags) :
                $tags = wc_get_product_tag_list($product->get_id());
                 if ($tags) :
            ?>
                <div class="meta-block">
                    <h4 class="meta-title">تگ‌ها:</h4>
                    <div class="meta-content tags-list">
                        <?php echo $tags; ?>
                    </div>
                </div>
            <?php endif; endif; ?>
        </div>

        <!-- Compatibility Score -->
        <div class="compatibility-section">
            <h3 class="section-title">میزان تطبیق</h3>
            <div class="compatibility-score">
                <div class="score-circle">
                    <div class="score-fill" data-score="88"></div>
                    <span class="score-text">88%</span>
                </div>
                <div class="score-details">
                    <div class="score-item">
                        <span class="score-label">گزینه اول</span>
                        <div class="score-bar">
                            <div class="score-progress" style="width: 95%"></div>
                        </div>
                        <span class="score-value">95%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">گزینه دوم</span>
                        <div class="score-bar">
                            <div class="score-progress" style="width: 85%"></div>
                        </div>
                        <span class="score-value">85%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">گزینه سوم</span>
                        <div class="score-bar">
                            <div class="score-progress" style="width: 75%"></div>
                        </div>
                        <span class="score-value">75%</span>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        // Control showing recommended/related products via settings
        $show_recommendation = true;
        if ( is_array($display_options) && array_key_exists('show_recommendation', $display_options) ) {
            $show_recommendation = !empty($display_options['show_recommendation']);
        }
        if ( $show_recommendation && !empty($related_products) ): ?>
        <!-- Related Products Section -->
        <div class="related-section">
            <h3 class="section-title">
                <span class="section-icon">🛒</span>
                سایر گزینه‌های پیشنهادی
            </h3>
            <div class="related-grid">
                <?php foreach( $related_products as $rp ): if(!$rp) continue; ?>
                    <div class="related-item">
                        <div class="related-image">
                            <?php 
                            // Responsive thumbnails with lazy-load for related items
                            $rp_image_id = method_exists($rp, 'get_image_id') ? $rp->get_image_id() : 0;
                            if ( $rp_image_id ) {
                                echo wp_get_attachment_image(
                                    $rp_image_id,
                                    'thumbnail',
                                    false,
                                    [
                                        'class' => 'related-thumb',
                                        'loading' => 'lazy',
                                        'decoding' => 'async',
                                        'fetchpriority' => 'low',
                                        'alt' => $rp->get_name(),
                                    ]
                                );
                            } else {
                                echo '<img class="related-thumb" src="' . esc_url( get_the_post_thumbnail_url( $rp->get_id(), 'thumbnail' ) ) . '" alt="' . esc_attr( $rp->get_name() ) . '" loading="lazy" decoding="async" fetchpriority="low">';
                            }
                            ?>
                        </div>
                        <div class="related-info">
                            <h4><?php echo esc_html( $rp->get_name() ); ?></h4>
                            <div class="related-price"><?php echo $rp->get_price_html(); ?></div>
                            <a href="<?php echo esc_url( get_permalink( $rp->get_id() ) ); ?>" class="btn btn-outline">مشاهده</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="result-actions">
            <button id="restart-advice" class="btn btn-secondary" onclick="restartWizard()" data-history-id="<?php echo esc_attr( $history_id ); ?>">
                <span class="btn-icon">🔁</span>
                شروع مجدد مشاوره درب
            </button>
            <button id="share-result" class="btn btn-outline" onclick="shareResult()" data-history-id="<?php echo esc_attr( $history_id ); ?>">
                <span class="btn-icon">🔗</span>
                اشتراک‌گذاری نتیجه
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

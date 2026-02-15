jQuery(document).ready(function($) {
    // Tab functionality
    $('.tab-button').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Remove active class from all tabs and content
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        
        // Add active class to clicked tab and corresponding content
        $(this).addClass('active');
        $('#' + targetTab).addClass('active');
    });
    

    function filterProductsByCategory(categorySelector) {
        var categoryId = $(categorySelector).val();
        var targetSelector = $(categorySelector).data('target-product');
        var productSelect = $(targetSelector);

        if (!productSelect.length) {
            return;
        }

        var currentValue = productSelect.val();
        var hasVisibleOption = false;

        productSelect.find('option').each(function(index) {
            var option = $(this);
            if (index === 0) {
                option.show();
                return;
            }

            if (!categoryId) {
                option.hide();
                return;
            }

            var categories = (option.data('categories') || '').toString().split(',');
            var isMatch = categories.indexOf(categoryId.toString()) !== -1;
            option.toggle(isMatch);
            if (isMatch) {
                hasVisibleOption = true;
            }
        });

        if (!categoryId) {
            productSelect.prop('disabled', true);
            productSelect.val('');
            productSelect.find('option:first').text('ابتدا دسته‌بندی را انتخاب کنید');
            return;
        }

        productSelect.prop('disabled', false);
        productSelect.find('option:first').text(hasVisibleOption ? 'یک محصول را انتخاب کنید' : 'محصولی در این دسته‌بندی یافت نشد');

        if (!currentValue || !productSelect.find('option[value="' + currentValue + '"]:visible').length) {
            productSelect.val('');
        }
    }

    $(document).on('change', '.mattress-product-category', function() {
        filterProductsByCategory(this);
    });

    // Add Rule AJAX
    $('#add-rule-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=add_mattress_rule&nonce=' + mattress_ajax.nonce;
        
        // Show loading state
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.html('<span class="dashicons dashicons-update"></span> در حال ذخیره...').prop('disabled', true);
        
        $.post(mattress_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                // Show success message
                showMessage(response.data.message, 'success');
                
                // Reset form
                $('#add-rule-form')[0].reset();
                
                // Switch to rules list tab and reload
                $('.tab-button[data-tab="rules-list"]').click();
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showMessage(response.data.message, 'error');
            }
        }).always(function() {
            // Reset button state
            submitBtn.html(originalText).prop('disabled', false);
        });
    });
    
    // Edit Rule functionality
    $('.edit-rule-btn').on('click', function(e) {
        e.preventDefault();
        
        var ruleId = $(this).data('rule-id');
        
        // Get rule data via AJAX
        $.post(mattress_ajax.ajax_url, {
            action: 'get_mattress_rule',
            rule_id: ruleId,
            nonce: mattress_ajax.nonce
        }, function(response) {
            if (response.success) {
                var rule = response.data.rule;
                
                // Populate edit form
                $('#edit-rule-id').val(rule.id);
                $('#edit_key_features').val(rule.key_features);
                $('#edit_why_suitable').val(rule.why_suitable);

                var editProductOption = $('#edit_product_id').find('option[value="' + rule.product_id + '"]');
                if (editProductOption.length) {
                    var categories = (editProductOption.data('categories') || '').toString().split(',').filter(Boolean);
                    if (categories.length) {
                        $('#edit_product_category').val(categories[0]);
                        filterProductsByCategory('#edit_product_category');
                    }
                    $('#edit_product_id').val(rule.product_id);
                }

                var conditions = {};
                try { conditions = JSON.parse(rule.conditions || '{}'); } catch(e) { conditions = {}; }
                var keys = ['door_type','building_type','weather_exposure','facade_style','entrance_material','waterproof','metal_frame_installed','usage_space','interior_style','color_theme','weatherstrip','interior_material','door_width_min','door_width_max','door_height_min','door_height_max'];
                keys.forEach(function(key){
                    var val = conditions[key] || '';
                    if (key === 'entrance_material' || key === 'usage_space') {
                        var values = Array.isArray(val) ? val : (String(val).split(',').map(function(v){ return v.trim(); }).filter(Boolean));
                        $('#edit-rule-form').find('[name="'+key+'[]"]').val(values);
                    } else {
                        $('#edit-rule-form').find('[name="'+key+'"]').val(val);
                    }
                });
                
                // Show edit tab
                $('.tab-button[data-tab="edit-rule"]').show().click();
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });
    
    // Edit Rule AJAX
    $('#edit-rule-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=update_mattress_rule&nonce=' + mattress_ajax.nonce;
        
        // Show loading state
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.html('<span class="dashicons dashicons-update"></span> در حال ذخیره...').prop('disabled', true);
        
        $.post(mattress_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                // Show success message
                showMessage(response.data.message, 'success');
                
                // Switch to rules list tab and reload
                $('.tab-button[data-tab="rules-list"]').click();
                $('.tab-button[data-tab="edit-rule"]').hide();
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showMessage(response.data.message, 'error');
            }
        }).always(function() {
            // Reset button state
            submitBtn.html(originalText).prop('disabled', false);
        });
    });
    
    // Delete Rule AJAX
    $(document).on('click', '.delete-rule-btn', function(e) {
        e.preventDefault();
        
        if (!confirm('آیا مطمئن هستید که می‌خواهید این قانون را حذف کنید؟')) {
            return;
        }
        
        var ruleId = $(this).data('rule-id');
        var card = $(this).closest('.rule-card');
        
        // Show loading state
        $(this).prop('disabled', true).html('⏳');
        
        $.post(mattress_ajax.ajax_url, {
            action: 'delete_mattress_rule',
            rule_id: ruleId,
            nonce: mattress_ajax.nonce
        }, function(response) {
            if (response.success) {
                card.fadeOut(function() {
                    $(this).remove();
                    
                    // Check if no rules left
                    if ($('.rule-card').length === 0) {
                        location.reload();
                    }
                });
                showMessage('قانون با موفقیت حذف شد', 'success');
            } else {
                showMessage('خطا در حذف قانون: ' + response.data.message, 'error');
                $(this).prop('disabled', false).html('🗑️');
            }
        });
    });
    
    // View form data
    $('.view-form').on('click', function(e) {
        e.preventDefault();
        
        var formData = $(this).data('form');
        
        if (formData) {
            try {
                var parsedData = JSON.parse(formData);
                var text = JSON.stringify(parsedData, null, 2);
                $('#history-modal-body').text(text);
                $('#history-modal').css('display','flex');
            } catch (e) {
                $('#history-modal-body').text(formData);
                $('#history-modal').css('display','flex');
            }
        }
    });

    $(document).on('click', '#history-modal, #history-modal-close', function(e){
        if (e.target.id === 'history-modal' || e.target.id === 'history-modal-close') {
            $('#history-modal').hide();
        }
    });

    // History filters (client-side quick filter)
    function applyHistoryFilters(){
        var q = ($('#history-q').val()||'').toLowerCase();
        var from = $('#history-from').val();
        var to = $('#history-to').val();
        $('#mattress-history-table tbody tr').each(function(){
            var tr = $(this);
            // After adding selection column: date column is 3rd
            var t = tr.find('td:nth-child(3)').text();
            var rowText = tr.text().toLowerCase();
            var ok = true;
            if (q && rowText.indexOf(q) === -1) ok = false;
            if (from && t < from) ok = false;
            if (to && t > to) ok = false;
            tr.toggle(ok);
        });
    }
    $(document).on('input change', '#history-q, #history-from, #history-to', applyHistoryFilters);
    
    // Helper function to show messages
    function showMessage(message, type) {
        var messageHtml = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>';
        
        // Remove existing messages
        $('.message-container .notice').remove();
        
        // Add new message
        if ($('.message-container').length === 0) {
            $('.mattress-advisor-admin').prepend('<div class="message-container"></div>');
        }
        
        $('.message-container').html(messageHtml);
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.message-container .notice').fadeOut();
            }, 3000);
        }
    }
    
    // Initialize first tab as active if none is active
    if ($('.tab-button.active').length === 0) {
        $('.tab-button:first').addClass('active');
        $('.tab-content:first').addClass('active');
    }


    $('.mattress-product-category').each(function() {
        filterProductsByCategory(this);
    });

    // -------- Rules filters --------
    function applyFilters() {
        var text = $('#filter-text').val() ? $('#filter-text').val().toLowerCase() : '';
        var doorType = $('#filter-door-type').val();
        var buildingType = $('#filter-building-type').val();
        $('.rules-grid .rule-card').each(function(){
            var card = $(this);
            var hay = card.text().toLowerCase();
            var ok = true;
            if (text && hay.indexOf(text) === -1) ok = false;
            if (doorType) {
                var dtag = card.find('.condition-tag[data-key="door_type"]').text().toLowerCase();
                if (dtag.indexOf(doorType) === -1) ok = false;
            }
            if (buildingType) {
                var btag = card.find('.condition-tag[data-key="building_type"]').text().toLowerCase();
                if (btag.indexOf(buildingType) === -1) ok = false;
            }
            card.toggle(ok);
        });
    }
    $(document).on('input change', '#filter-text, #filter-door-type, #filter-building-type', applyFilters);
    $(document).on('click', '#filter-clear', function(){
        $('#filter-text').val('');
        $('#filter-door-type').val('');
        $('#filter-building-type').val('');
        applyFilters();
    });

    // -------- Rule preview --------
    $(document).on('click', '#add-rule-form .button-preview, #edit-rule-form .button-preview', function(e){
        e.preventDefault();
        var form = $(this).closest('form');
        var data = form.serialize() + '&action=preview_mattress_rule&nonce=' + mattress_ajax.nonce;
        $.post(mattress_ajax.ajax_url, data, function(response){
            if (response.success) {
                var container = form.find('.message-container');
                if (!container.length) {
                    form.prepend('<div class="message-container"></div>');
                    container = form.find('.message-container');
                }
                container.html('<div class="preview-box">' + response.data.html + '</div>');
            } else {
                showMessage(response.data.message || 'خطا در پیش‌نمایش', 'error');
            }
        });
    });

    // -------- Conflict check before save (add form only) --------
    $(document).on('click', '#add-rule-form button[type="submit"]', function(e){
        var form = $('#add-rule-form');
        var data = form.serialize() + '&action=check_mattress_rule_conflicts&nonce=' + mattress_ajax.nonce;
        var submitBtn = $(this);
        // Run sync check; if conflicts, confirm
        $.ajax({
            url: mattress_ajax.ajax_url,
            type: 'POST',
            data: data,
            async: false,
            success: function(response){
                if (response.success && response.data.conflicts && response.data.conflicts.length) {
                    var msg = 'مشاوره\u200cهای هم‌پوشان شناسایی شد:\n';
                    response.data.conflicts.forEach(function(c){
                        msg += '- مشاوره #' + c.id + ' (' + c.product + ')\n';
                    });
                    msg += '\nآیا مایل به ادامه ذخیره هستید؟';
                    if (!confirm(msg)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }
            }
        });
    });
});
    // -------- History selection actions --------
    (function($){
        function getSelectedIds(){
            return $('.row-select:checked').map(function(){ return $(this).val(); }).get();
        }

        function updateSelectAllState(){
            var total = $('.row-select').length;
            var selected = $('.row-select:checked').length;
            $('#select-all, #select-all-header').prop('checked', selected > 0 && selected === total);
        }

        // Initialize select-all state on load
        $(function(){
            updateSelectAllState();
        });

        // Use click to ensure immediate reaction across browsers
        $(document).on('click', '#select-all, #select-all-header', function(){
            var checked = $(this).is(':checked');
            // Keep both select-all checkboxes in sync
            $('#select-all, #select-all-header').prop('checked', checked);
            // Toggle all row selects
            $('.row-select').prop('checked', checked);
            updateSelectAllState();
        });

        $(document).on('change', '.row-select', function(){
            updateSelectAllState();
        });

        $(document).on('click', '#csv-selected', function(e){
            e.preventDefault();
            var ids = getSelectedIds();
            if (!ids.length) { alert('هیچ موردی انتخاب نشده است.'); return; }
            var form = $('#csv-selected-form');
            form.find('input[name="ids[]"]').remove();
            ids.forEach(function(id){ form.append('<input type="hidden" name="ids[]" value="'+id+'">'); });
            // Submit natively to bypass any JS interceptors
            if (form.length && form[0]) { form[0].submit(); }
        });

        $(document).on('click', '#delete-selected', function(e){
            e.preventDefault();
            var ids = getSelectedIds();
            if (!ids.length) { alert('هیچ موردی انتخاب نشده است.'); return; }
            if (!confirm('آیا از حذف موارد انتخاب‌شده مطمئن هستید؟')) { return; }
            var form = $('#delete-selected-form');
            form.find('input[name="ids[]"]').remove();
            ids.forEach(function(id){ form.append('<input type="hidden" name="ids[]" value="'+id+'">'); });
            // Submit natively to bypass any JS interceptors
            if (form.length && form[0]) { form[0].submit(); }
        });
    })(jQuery);

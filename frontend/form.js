jQuery(document).ready(function($) {
    let currentStep = 1;
    const totalSteps = 4;

    function activeDoorType() {
        return $('input[name="door_type"]:checked').val() || '';
    }

    function refreshDoorSections() {
        const doorType = activeDoorType();

        $('.door-section').hide();
        $('.door-type-hint').hide();

        if (doorType) {
            $(`.door-section[data-door="${doorType}"]`).show();
            $(`.door-type-hint[data-hint="${doorType === 'entrance' ? 'interior' : 'entrance'}"]`).show();
        }

        $('[data-conditional-required]').each(function() {
            const requiredFor = $(this).data('conditional-required');
            const isRequired = requiredFor === doorType;

            if ($(this).is(':radio')) {
                const radioName = $(this).attr('name');
                $(`input[name="${radioName}"][data-conditional-required]`).prop('required', isRequired).prop('disabled', !isRequired);
            } else if ($(this).is(':checkbox')) {
                const checkboxName = $(this).attr('name');
                $(`input[name="${checkboxName}"][data-conditional-required]`).prop('required', false).prop('disabled', !isRequired);
                if (!isRequired) {
                    $(`input[name="${checkboxName}"][data-conditional-required]`).prop('checked', false);
                }
            } else {
                $(this).prop('required', isRequired).prop('disabled', !isRequired);
                if (!isRequired) {
                    $(this).val('');
                }
            }
        });

        updateRadioTiles();
    }

    function updateProgressBar() {
        const progressPercentage = (currentStep / totalSteps) * 100;
        $('#progress-fill').css('width', progressPercentage + '%');

        $('.step').removeClass('active completed');
        for (let i = 1; i <= currentStep; i++) {
            if (i === currentStep) {
                $('.step[data-step="' + i + '"]').addClass('active');
            } else {
                $('.step[data-step="' + i + '"]').addClass('completed');
            }
        }
    }

    function updateNavigationButtons() {
        $('#prev-btn').toggle(currentStep > 1);
        if (currentStep === totalSteps) {
            $('#next-btn').hide();
            $('#submit-btn').show();
        } else {
            $('#next-btn').show();
            $('#submit-btn').hide();
        }
    }

    function scrollToTop() {
        $('html, body').animate({ scrollTop: $('#mattress-advisor-container').offset().top - 20 }, 200);
    }

    function validateCurrentStep() {
        const step = $('#step-' + currentStep);
        const fields = step.find('[required]').filter(':enabled');
        let ok = true;

        step.find('.form-group').removeClass('error success');
        step.find('.error-message').remove();

        fields.each(function() {
            const $field = $(this);
            const $group = $field.closest('.form-group');

            if ($field.is(':radio') || $field.is(':checkbox')) {
                const name = $field.attr('name');
                if (!$(`input[name="${name}"]:enabled:checked`).length) {
                    ok = false;
                    $group.addClass('error');
                    if (!$group.find('.error-message').length) {
                        $group.append('<div class="error-message">لطفاً یک گزینه انتخاب کنید</div>');
                    }
                } else {
                    $group.addClass('success');
                }
                return;
            }

            const value = ($field.val() || '').toString().trim();
            if (!value) {
                ok = false;
                $group.addClass('error').append('<div class="error-message">این فیلد الزامی است</div>');
                return;
            }

            if ($field.attr('type') === 'tel' && !/^09[0-9]{9}$/.test(value)) {
                ok = false;
                $group.addClass('error').append('<div class="error-message">شماره موبایل معتبر نیست</div>');
                return;
            }

            if ($field.attr('type') === 'number') {
                const num = parseFloat(value);
                const min = parseFloat($field.attr('min'));
                const max = parseFloat($field.attr('max'));
                if ((!isNaN(min) && num < min) || (!isNaN(max) && num > max)) {
                    ok = false;
                    $group.addClass('error').append('<div class="error-message">مقدار واردشده خارج از بازه مجاز است</div>');
                    return;
                }
            }

            $group.addClass('success');
        });

        return ok;
    }

    function submitForm() {
        const ajaxData = $('#mattress-advisor-form').serialize() + '&action=get_mattress_recommendation&nonce=' + mattress_form.nonce;
        $('#mattress-advisor-form, .wizard-progress').hide();
        $('#mattress-result').html('<div class="loading-spinner"></div><h3>در حال پردازش...</h3>').show();

        $.post(mattress_form.ajax_url, ajaxData)
            .done(function(response) {
                if (response.success) {
                    $('#mattress-result').html('<div class="result-success"><h3>🎉 پیشنهاد مناسب شما</h3>' + (response.data.html || '') + '</div>');
                } else {
                    $('#mattress-result').html('<div class="result-error"><h3>❌ نتیجه‌ای پیدا نشد</h3><p>' + (typeof response.data === 'string' ? response.data : 'لطفاً اطلاعات را بررسی کنید.') + '</p></div>');
                }
            })
            .fail(function() {
                $('#mattress-result').html('<div class="result-error"><h3>❌ خطای ارتباطی</h3><p>لطفاً دوباره تلاش کنید.</p></div>');
            });
    }

    function updateRadioTiles() {
        $('.form-options .option').each(function() {
            const input = $(this).find('input[type="radio"], input[type="checkbox"]');
            $(this).toggleClass('selected', input.is(':checked'));
        });
    }

    $('#next-btn').on('click', function() {
        if (!validateCurrentStep()) return;
        if (currentStep < totalSteps) {
            $('#step-' + currentStep).removeClass('active');
            currentStep++;
            $('#step-' + currentStep).addClass('active');
            updateProgressBar();
            updateNavigationButtons();
            scrollToTop();
        }
    });

    $('#prev-btn').on('click', function() {
        if (currentStep > 1) {
            $('#step-' + currentStep).removeClass('active');
            currentStep--;
            $('#step-' + currentStep).addClass('active');
            updateProgressBar();
            updateNavigationButtons();
            scrollToTop();
        }
    });

    $('#mattress-advisor-form').on('submit', function(e) {
        e.preventDefault();
        if (validateCurrentStep()) submitForm();
    });

    $(document).on('change', 'input[name="door_type"]', refreshDoorSections);
    $(document).on('change', 'input[type="radio"], input[type="checkbox"]', function() {
        updateRadioTiles();
        $(this).closest('.form-group').removeClass('error').addClass('success').find('.error-message').remove();
    });

    updateProgressBar();
    updateNavigationButtons();
    refreshDoorSections();
    updateRadioTiles();

    window.restartWizard = function() {
        currentStep = 1;
        $('#mattress-advisor-form')[0].reset();
        $('#mattress-result').hide().empty();
        $('#mattress-advisor-form, .wizard-progress').show();
        $('.form-step').removeClass('active');
        $('#step-1').addClass('active');
        refreshDoorSections();
        updateProgressBar();
        updateNavigationButtons();
    };

    window.shareResult = function() {
        const text = $('#mattress-result').text().trim();
        if (!text) return;
        if (navigator.share) {
            navigator.share({ title: 'نتیجه مشاوره درب', text: text, url: window.location.href }).catch(function(){});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        }
    };

});

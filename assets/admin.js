jQuery(document).ready(function($) {
    var mediaUploader;

    // البيانات المعرفة من wp_localize_script
    var wooAiVision = window.wooAiVision || {};

    // رفع الصورة
    $('#woo-ai-vision-select-image-btn, #woo-ai-vision-uploader-dragndrop').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: wooAiVision.l10n.selectImage,
            button: { text: 'اعتماد صورة المنتج' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            setMediaSelection(attachment.id, attachment.url);
        });

        mediaUploader.open();
    });

    function setMediaSelection(id, url) {
        $('#woo-ai-vision-attachment-id').val(id);
        $('#woo-ai-vision-preview-img').attr('src', url);
        $('#woo-ai-vision-uploader-dragndrop').slideUp();
        $('#woo-ai-vision-preview-wrapper').slideDown();
        $('#woo-ai-vision-step-2').slideUp();
        $('#woo-ai-vision-notice-center').html('');
    }

    // إزالة الصورة
    $('#woo-ai-vision-remove-image-btn').on('click', function() {
        $('#woo-ai-vision-attachment-id').val('');
        $('#woo-ai-vision-preview-img').attr('src', '');
        $('#woo-ai-vision-preview-wrapper').slideUp();
        $('#woo-ai-vision-uploader-dragndrop').slideDown();
        $('#woo-ai-vision-step-2').slideUp();
        $('#woo-ai-vision-notice-center').html('');
    });

    // تحليل الصورة
    $('#woo-ai-vision-analyze-btn').on('click', function() {
        var attachmentId = $('#woo-ai-vision-attachment-id').val();
        if (!attachmentId) {
            alert(wooAiVision.l10n.selectImage);
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).css('opacity', 0.6);
        $('#woo-ai-vision-loading-box').slideDown();
        $('#woo-ai-vision-notice-center').html('');

        updateStepState(1);
        setTimeout(function() { updateStepState(2); }, 1500);
        setTimeout(function() { updateStepState(3); }, 3000);

        $.ajax({
            url: wooAiVision.ajaxurl,
            type: 'POST',
            data: {
                action: 'woo_ai_vision_analyze',
                attachment_id: attachmentId,
                nonce: wooAiVision.nonce
            },
            success: function(response) {
                $('#woo-ai-vision-loading-box').slideUp();
                $btn.prop('disabled', false).css('opacity', 1);

                if (response.success) {
                    var data = response.data;
                    $('#generated-product-name').val(data.name || '');
                    $('#generated-product-price').val(data.price || '150.00');
                    $('#generated-product-desc').val(data.description || '');
                    $('#generated-product-cat').val(data.category || '');
                    $('#generated-product-sku').val('AI-' + Math.floor(Math.random() * 900000 + 100000));

                    $('#woo-ai-vision-step-2').slideDown();
                    $('html, body').animate({
                        scrollTop: $('#woo-ai-vision-step-2').offset().top - 50
                    }, 500);
                } else {
                    var msg = response.data && response.data.message ? response.data.message : 'حدث خطأ غير متوقع.';
                    $('#woo-ai-vision-notice-center').html(
                        '<div class="notice notice-error" style="background:#fef2f2; border-right:4px solid #ef4444; padding:15px; border-radius:6px; margin-top:20px;">' +
                        '<p style="color:#b91c1c; font-weight:bold; margin:0 0 5px;">🚨 ' + wooAiVision.l10n.analyzeFailed + '</p>' +
                        '<p style="margin:0; color:#4b5563; font-size:13px; line-height:1.5;">' + msg + '</p>' +
                        '<p style="margin:8px 0 0; font-size:12px; color:#6b7280;">' + wooAiVision.l10n.checkApi + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#woo-ai-vision-loading-box').slideUp();
                $btn.prop('disabled', false).css('opacity', 1);
                $('#woo-ai-vision-notice-center').html(
                    '<div class="notice notice-error" style="background:#fef2f2; border-right:4px solid #ef4444; padding:15px; border-radius:6px; margin-top:20px;">' +
                    '<p style="color:#b91c1c; font-weight:bold; margin:0;">🚨 ' + wooAiVision.l10n.analyzeFailed + ' (AJAX error)</p>' +
                    '</div>'
                );
            }
        });
    });

    function updateStepState(stepNum) {
        for (var i = 1; i <= 3; i++) {
            var el = $('#step-' + i);
            if (i < stepNum) {
                el.html('✅ ' + el.text().substring(2)).css('color', '#059669').css('font-weight', 'medium');
            } else if (i === stepNum) {
                el.html('⚡ ' + el.text().substring(2)).css('color', '#2563eb').css('font-weight', 'bold');
            } else {
                el.css('color', '#64748b').css('font-weight', 'normal');
            }
        }
    }

    // نشر المنتج
    $('#woo-ai-vision-publish-btn').on('click', function() {
        var name = $('#generated-product-name').val();
        var price = $('#generated-product-price').val();
        var category = $('#generated-product-cat').val();
        var desc = $('#generated-product-desc').val();
        var attachmentId = $('#woo-ai-vision-attachment-id').val();
        var sku = $('#generated-product-sku').val();

        if (!name) {
            alert(wooAiVision.l10n.selectImage); // Actually we need a specific message
            alert('اسم المنتج مطلوب!');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('جاري نشر وتخزين منتج ووكومرس...');

        $.ajax({
            url: wooAiVision.ajaxurl,
            type: 'POST',
            data: {
                action: 'woo_ai_vision_publish',
                name: name,
                price: price,
                category: category,
                description: desc,
                attachment_id: attachmentId,
                sku: sku,
                nonce: wooAiVision.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text('نشر المنتج فورا في كتالوج ووكومرس 🚀');

                if (response.success) {
                    var editUrl = response.data.edit_url;
                    var statusStr = response.data.status === 'draft' ? 'مسودة' : 'منتج منشور نشط';

                    $('#woo-ai-vision-notice-center').html(
                        '<div class="notice notice-success" style="background:#f0fdf4; border-right:4px solid #10b981; padding:20px; border-radius:10px; margin-top:20px;">' +
                        '<p style="color:#065f46; font-weight:bold; font-size:16px; margin:0 0 8px;">🎉 تم توليد ونشر المنتج بنجاح في متجرك!</p>' +
                        '<p style="margin:0 0 12px; color:#374151; font-size:13px;">تم تسجيل المنتج بنجاح كـ <strong>(' + statusStr + ')</strong> وضبط الصورة المحددة كغلاف رسمي للمنتج.</p>' +
                        '<div style="display:flex; gap:10px;">' +
                        '<a href="' + editUrl + '" class="button button-primary" target="_blank" style="background:#10b981; border-color:#059669; font-weight:bold; color:#fff;">✍️ تعديل بيانات المنتج بووكومرس</a>' +
                        '<button type="button" class="button" id="start-new-listing-btn">➕ ابدأ نشر منتج آخر</button>' +
                        '</div>' +
                        '</div>'
                    );

                    $('#start-new-listing-btn').on('click', function() {
                        $('#woo-ai-vision-remove-image-btn').click();
                    });
                } else {
                    alert(response.data.message || wooAiVision.l10n.publishFailed);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('نشر المنتج فورا في كتالوج ووكومرس 🚀');
                alert(wooAiVision.l10n.publishFailed + ' (AJAX error)');
            }
        });
    });
});
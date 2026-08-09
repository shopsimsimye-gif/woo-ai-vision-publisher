<?php
/**
 * قالب إعدادات لوحة التحكم
 * يحتوي على واجهة رفع الصور والإعدادات.
 */

defined( 'ABSPATH' ) || exit;

function woo_ai_vision_render_admin_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // جلب الإعدادات
    $api_key        = get_option( 'woo_ai_vision_api_key', '' );
    $default_status = get_option( 'woo_ai_vision_default_status', 'draft' );
    $image_size     = get_option( 'woo_ai_vision_image_size', '800x800' );
    $auto_optimize  = get_option( 'woo_ai_vision_auto_optimize', 'yes' );

    // حفظ الإعدادات
    if ( isset( $_POST['woo_ai_vision_save_settings'] ) ) {
        check_admin_referer( 'woo_ai_vision_save_nonce', 'woo_ai_vision_settings_nonce' );

        $new_api_key = sanitize_text_field( wp_unslash( $_POST['woo_ai_vision_api_key'] ?? '' ) );
        $new_status  = sanitize_text_field( wp_unslash( $_POST['woo_ai_vision_default_status'] ?? 'draft' ) );
        $new_size    = sanitize_text_field( wp_unslash( $_POST['woo_ai_vision_image_size'] ?? '800x800' ) );
        $new_opt     = sanitize_text_field( wp_unslash( $_POST['woo_ai_vision_auto_optimize'] ?? 'no' ) );

        update_option( 'woo_ai_vision_api_key', $new_api_key );
        update_option( 'woo_ai_vision_default_status', $new_status );
        update_option( 'woo_ai_vision_image_size', $new_size );
        update_option( 'woo_ai_vision_auto_optimize', $new_opt );

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تم حفظ الإعدادات بنجاح! 💾', 'woo-ai-vision-publisher' ) . '</p></div>';
    }

    ?>
    <div class="wrap" style="max-width:1000px; margin:30px auto; font-family:system-ui, sans-serif; direction:rtl; text-align:right;">
        <h1 style="font-weight:800; display:flex; align-items:center; gap:10px; color:#0f172a; margin-bottom:5px;">
            🧠 <?php esc_html_e( 'WooCommerce AI Vision Auto-Publisher', 'woo-ai-vision-publisher' ); ?>
        </h1>
        <p style="color:#64748b; font-size:15px; margin-bottom:25px; margin-top:5px;">
            <?php esc_html_e( 'أداة ثورية تعمل بالذكاء الاصطناعي لتحويل صور المنتجات الخام إلى منتجات ووكومرس متكاملة ومنسقة تلقائياً بضغطة زر واحدة.', 'woo-ai-vision-publisher' ); ?>
        </p>

        <div style="display:grid; grid-template-columns:1fr; gap:25px;">
            <!-- قسم رفع الصورة -->
            <div style="background:#fff; padding:30px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0; font-size:18px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:20px;">
                    🔮 <?php esc_html_e( 'رفع ومعالجة صورة منتج جديد (Workflow)', 'woo-ai-vision-publisher' ); ?>
                </h3>

                <div class="woo-ai-vision-uploader-area" id="woo-ai-vision-uploader-dragndrop" style="margin-bottom:20px; border:2px dashed #cbd5e1; border-radius:8px; text-align:center; padding:40px 20px; background:#f8fafc; cursor:pointer; transition:all 0.3s;">
                    <span style="font-size:40px; display:block; margin-bottom:15px;">📸</span>
                    <h4 style="margin:0 0 10px; font-size:16px; color:#334155;"><?php esc_html_e( 'اسحب وأفلت صورة المنتج هنا', 'woo-ai-vision-publisher' ); ?></h4>
                    <p style="color:#64748b; margin:0 0 15px; font-size:13px;"><?php esc_html_e( 'أو اضغط لاختيار صورة من حاسوبك أو مكتبة الوسائط', 'woo-ai-vision-publisher' ); ?></p>
                    <button type="button" class="woo-ai-vision-btn" id="woo-ai-vision-select-image-btn" style="background:#10b981; color:#fff; padding:10px 20px; border-radius:6px; border:none; font-weight:bold; cursor:pointer;">
                        <?php esc_html_e( 'اختر صورة منتج خام', 'woo-ai-vision-publisher' ); ?>
                    </button>
                </div>

                <!-- معاينة الصورة -->
                <div id="woo-ai-vision-preview-wrapper" style="display:none; border:1px solid #e2e8f0; border-radius:10px; padding:20px; background:#f8fafc; text-align:center; max-width:500px; margin:0 auto 20px;">
                    <img id="woo-ai-vision-preview-img" src="" style="max-height:250px; border-radius:8px; margin-bottom:15px; box-shadow:0 2px 8px rgba(0,0,0,0.1);" />
                    <input type="hidden" id="woo-ai-vision-attachment-id" value="" />
                    <div style="display:flex; gap:10px; justify-content:center;">
                        <button type="button" class="woo-ai-vision-btn" id="woo-ai-vision-remove-image-btn" style="background:#ef4444; color:#fff; padding:10px 20px; border-radius:6px; border:none; font-weight:bold; cursor:pointer;"><?php esc_html_e( 'إزالة الصورة', 'woo-ai-vision-publisher' ); ?></button>
                        <button type="button" class="woo-ai-vision-btn" id="woo-ai-vision-analyze-btn" style="background:#3b82f6; color:#fff; padding:10px 20px; border-radius:6px; border:none; font-weight:bold; cursor:pointer;"><?php esc_html_e( 'تحليل الصورة بالذكاء الاصطناعي', 'woo-ai-vision-publisher' ); ?></button>
                    </div>
                </div>

                <!-- مؤشر التحميل -->
                <div id="woo-ai-vision-loading-box" style="display:none; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:20px; margin-bottom:20px; color:#1e40af;">
                    <div style="display:flex; align-items:center; gap:10px; font-weight:bold; margin-bottom:10px;">
                        <span class="spinner is-active" style="float:none; margin:0;"></span>
                        <span><?php esc_html_e( 'جاري معالجة Gemini Vision وتوليد البيانات...', 'woo-ai-vision-publisher' ); ?></span>
                    </div>
                    <ul style="margin:0 0 0 20px; padding:0; list-style-type:none; font-size:13px; display:flex; flex-direction:column; gap:5px;">
                        <li id="step-1" style="color:#64748b;">⏳ <?php esc_html_e( '1. كشف المعالم البصرية بـ Gemini', 'woo-ai-vision-publisher' ); ?></li>
                        <li id="step-2" style="color:#64748b;">⏳ <?php esc_html_e( '2. صياغة الاسم والوصف الـ SEO', 'woo-ai-vision-publisher' ); ?></li>
                        <li id="step-3" style="color:#64748b;">⏳ <?php esc_html_e( '3. تحضير ومزامنة البيانات المنشورة', 'woo-ai-vision-publisher' ); ?></li>
                    </ul>
                </div>

                <!-- الخطوة 2: مراجعة البيانات -->
                <div id="woo-ai-vision-step-2" style="display:none; border-top:2px dashed #e2e8f0; padding-top:25px; margin-top:15px;">
                    <h3 style="font-size:18px; color:#1e293b; margin-bottom:15px;"><?php esc_html_e( 'خطوة 2: مراجعة تفاصيل المنتج وتعديلها', 'woo-ai-vision-publisher' ); ?></h3>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; color:#334155;"><?php esc_html_e( 'اسم المنتج المقترح', 'woo-ai-vision-publisher' ); ?></label>
                        <input type="text" id="generated-product-name" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" />
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#334155;"><?php esc_html_e( 'السعر المقترح (د.إ)', 'woo-ai-vision-publisher' ); ?></label>
                            <input type="text" id="generated-product-price" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" />
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#334155;"><?php esc_html_e( 'رمز المنتج (SKU)', 'woo-ai-vision-publisher' ); ?></label>
                            <input type="text" id="generated-product-sku" readonly style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#f1f5f9;" />
                        </div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; color:#334155;"><?php esc_html_e( 'التصنيف المقترح', 'woo-ai-vision-publisher' ); ?></label>
                        <input type="text" id="generated-product-cat" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" />
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; color:#334155;"><?php esc_html_e( 'الوصف التسويقي المميز', 'woo-ai-vision-publisher' ); ?></label>
                        <textarea id="generated-product-desc" rows="6" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; line-height:1.6;"></textarea>
                    </div>

                    <button type="button" class="woo-ai-vision-btn" id="woo-ai-vision-publish-btn" style="background:#2563eb; width:100%; padding:12px; font-size:15px; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer; box-shadow:0 4px 6px -1px rgba(37,99,235,0.2);">
                        <?php esc_html_e( 'نشر المنتج فورا في كتالوج ووكومرس 🚀', 'woo-ai-vision-publisher' ); ?>
                    </button>
                </div>

                <div id="woo-ai-vision-notice-center" style="margin-top:15px;"></div>
            </div>

            <!-- نموذج الإعدادات -->
            <div style="background:#fff; padding:30px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0; font-size:18px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:20px;">
                    ⚙️ <?php esc_html_e( 'إعدادات الإضافة والذكاء الاصطناعي', 'woo-ai-vision-publisher' ); ?>
                </h3>

                <form method="post" action="">
                    <?php wp_nonce_field( 'woo_ai_vision_save_nonce', 'woo_ai_vision_settings_nonce' ); ?>

                    <table class="form-table" style="width:100%;">
                        <tr>
                            <th scope="row" style="width:30%; font-weight:600; text-align:right; padding:15px 10px; vertical-align:middle;">
                                <?php esc_html_e( 'مفتاح API للذكاء الاصطناعي البصري (Gemini):', 'woo-ai-vision-publisher' ); ?>
                            </th>
                            <td style="padding:15px 10px;">
                                <input type="password" name="woo_ai_vision_api_key" value="<?php echo esc_attr( $api_key ); ?>" style="width:100%; max-width:500px; padding:10px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="<?php esc_attr_e( 'أدخل مفتاح GEMINI_API_KEY هنا', 'woo-ai-vision-publisher' ); ?>" />
                                <p class="description" style="margin-top:5px; color:#64748b;"><?php esc_html_e( 'مفتاح API يتم استخدامه محلياً وبشكل آمن من قبل الخادم لتحليل وصياغة بيانات المنتجات.', 'woo-ai-vision-publisher' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width:30%; font-weight:600; text-align:right; padding:15px 10px; vertical-align:middle;">
                                <?php esc_html_e( 'حالة نشر المنتج الافتراضية:', 'woo-ai-vision-publisher' ); ?>
                            </th>
                            <td style="padding:15px 10px;">
                                <select name="woo_ai_vision_default_status" style="width:100%; max-width:500px; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                                    <option value="draft" <?php selected( $default_status, 'draft' ); ?>><?php esc_html_e( 'حفظ كمسودة (موصى به للمراجعة)', 'woo-ai-vision-publisher' ); ?></option>
                                    <option value="publish" <?php selected( $default_status, 'publish' ); ?>><?php esc_html_e( 'نشر مباشر وتلقائي فوراً', 'woo-ai-vision-publisher' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width:30%; font-weight:600; text-align:right; padding:15px 10px; vertical-align:middle;">
                                <?php esc_html_e( 'مقاس أبعاد الصور الموحدة:', 'woo-ai-vision-publisher' ); ?>
                            </th>
                            <td style="padding:15px 10px;">
                                <select name="woo_ai_vision_image_size" style="width:100%; max-width:500px; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                                    <option value="800x800" <?php selected( $image_size, '800x800' ); ?>><?php esc_html_e( 'معيار المتجر المربع 800x800 بكسل', 'woo-ai-vision-publisher' ); ?></option>
                                    <option value="1000x1000" <?php selected( $image_size, '1000x1000' ); ?>><?php esc_html_e( 'مقاس متقدم عالي الدقة 1000x1000 بكسل', 'woo-ai-vision-publisher' ); ?></option>
                                    <option value="original" <?php selected( $image_size, 'original' ); ?>><?php esc_html_e( 'بلا تغيير (استخدام الأبعاد الأصلية)', 'woo-ai-vision-publisher' ); ?></option>
                                </select>
                                <p class="description" style="margin-top:5px; color:#64748b;"><?php esc_html_e( 'تأخذ الإضافة صورتك الخام وتقوم بإعادة تشكيل أبعادها وتوسيطها لضمان تجربة تصفح موحدة.', 'woo-ai-vision-publisher' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width:30%; font-weight:600; text-align:right; padding:15px 10px; vertical-align:middle;">
                                <?php esc_html_e( 'تحسين الصورة الفوري بالذكاء الاصطناعي:', 'woo-ai-vision-publisher' ); ?>
                            </th>
                            <td style="padding:15px 10px;">
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="woo_ai_vision_auto_optimize" value="yes" <?php checked( $auto_optimize, 'yes' ); ?> />
                                    <span><?php esc_html_e( 'نعم، قم بإزالة الخلفية العشوائية وتثبيتها باللون الأبيض أو الشفاف', 'woo-ai-vision-publisher' ); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:25px; border-top:1px solid #f1f5f9; padding-top:20px; display:flex; justify-content:flex-end;">
                        <input type="submit" name="woo_ai_vision_save_settings" class="woo-ai-vision-btn" value="<?php esc_attr_e( 'حفظ إعدادات الملحق الذكي', 'woo-ai-vision-publisher' ); ?>" style="background:#2563eb; color:#fff; padding:10px 20px; border:none; border-radius:6px; font-weight:bold; cursor:pointer;" />
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    // تم نقل كل الكود إلى ملف assets/admin.js المنفصل
    // لاحظ أننا سنقوم بإنشاء هذا الملف في الخطوة التالية
    </script>
    <?php
}
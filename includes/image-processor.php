<?php
/**
 * معالج الصور: تغيير الحجم، توسيط، إزالة خلفية بسيطة، رفع إلى المكتبة
 */

defined( 'ABSPATH' ) || exit;

class Woo_AI_Vision_Image_Processor {

    /**
     * معالجة الصورة: تغيير الحجم، توسيط، إزالة خلفية (اختياري)
     *
     * @param string $source_path  مسار الملف الأصلي
     * @param string $target_size  مثل '800x800'
     * @param bool   $remove_bg    إزالة الخلفية أم لا
     * @return string|false        مسار الملف المعالج أو false
     */
    public static function process_and_standardize( $source_path, $target_size = '800x800', $remove_bg = true ) {
        if ( ! file_exists( $source_path ) || ! function_exists( 'imagecreatefromstring' ) ) {
            return $source_path; // العودة للملف الأصلي في حال عدم توفر GD
        }

        list( $target_width, $target_height ) = explode( 'x', $target_size ) + array( 800, 800 );
        $target_width  = absint( $target_width );
        $target_height = absint( $target_height );

        $image_string = file_get_contents( $source_path );
        $source_image = imagecreatefromstring( $image_string );
        if ( ! $source_image ) {
            return $source_path;
        }

        $orig_width  = imagesx( $source_image );
        $orig_height = imagesy( $source_image );

        // إنشاء لوحة قماشية بيضاء
        $canvas = imagecreatetruecolor( $target_width, $target_height );
        $white = imagecolorallocate( $canvas, 255, 255, 255 );
        imagefill( $canvas, 0, 0, $white );

        // حساب الأبعاد الجديدة مع هوامش 40 بكسل
        $ratio_source = $orig_width / $orig_height;
        $ratio_target = $target_width / $target_height;

        if ( $ratio_source > $ratio_target ) {
            $new_width  = $target_width - 80;
            $new_height = round( $new_width / $ratio_source );
        } else {
            $new_height = $target_height - 80;
            $new_width  = round( $new_height * $ratio_source );
        }

        $dest_x = round( ( $target_width - $new_width ) / 2 );
        $dest_y = round( ( $target_height - $new_height ) / 2 );

        // إعادة التغيير الحجم
        imagecopyresampled( $canvas, $source_image, $dest_x, $dest_y, 0, 0, $new_width, $new_height, $orig_width, $orig_height );

        // إزالة الخلفية (تحويل البيكسلات البيضاء إلى شفاف؟ لكن سنبقيها بيضاء)
        if ( $remove_bg ) {
            // يمكن تحسين هذه الطريقة باستخدام خوارزمية بسيطة: تحويل البيكسلات القريبة من البياض إلى أبيض صريح
            // ولكننا سنكتفي بتطبيق بسيط: تلوين البيكسلات الفاتحة جداً بالأبيض
            for ( $x = 0; $x < $target_width; $x++ ) {
                for ( $y = 0; $y < $target_height; $y++ ) {
                    $rgb = imagecolorat( $canvas, $x, $y );
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // إذا كانت الألوان قريبة من الأبيض (أكثر من 240)
                    if ( $r > 240 && $g > 240 && $b > 240 ) {
                        imagesetpixel( $canvas, $x, $y, $white );
                    }
                }
            }
        }

        // حفظ الملف في مجلد الرفع المؤقت
        $upload_dir = wp_upload_dir();
        $filename   = 'ai-vision-processed-' . uniqid() . '.jpg';
        $output_path = $upload_dir['path'] . '/' . $filename;

        if ( ! imagejpeg( $canvas, $output_path, 90 ) ) {
            imagedestroy( $source_image );
            imagedestroy( $canvas );
            return $source_path;
        }

        imagedestroy( $source_image );
        imagedestroy( $canvas );

        return $output_path;
    }

    /**
     * رفع الملف المعالج إلى مكتبة الوسائط
     *
     * @param string $file_path مسار الملف
     * @param int    $post_id   معرف المنتج (اختياري)
     * @return int|WP_Error     معرف المرفق
     */
    public static function register_in_media_library( $file_path, $post_id = 0 ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_missing', __( 'الملف المعالج غير موجود.', 'woo-ai-vision-publisher' ) );
        }

        $wp_filetype = wp_check_filetype( basename( $file_path ), null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
            'post_content'   => 'AI Vision Standardized Product Frame',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );

        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // حذف الملف المؤقت بعد الرفع (اختياري)
        // لكننا سنحتفظ به لأنه أصبح الملف الرئيسي للمرفق، وسيتم نقله تلقائياً إلى المجلد الصحيح

        return $attach_id;
    }
}
<?php
/**
 * Plugin Name: WooCommerce AI Vision Auto-Publisher
 * Plugin URI: https://wordpress.org/plugins/woo-ai-vision-publisher/
 * Description: إضافة ووردبريس ثورية لتحويل صور المنتجات الخام إلى منتجات ووكومرس متكاملة بالذكاء الاصطناعي البصري بضغطة زر.
 * Version: 1.2.0
 * Author: سندباد للحلول الذكية (Sindbad AI Solutions)
 * Author URI: https://github.com/wp-sindbad
 * License: GPLv2 or later
 * Text Domain: woo-ai-vision-publisher
 */

defined( 'ABSPATH' ) || exit;

// تعريف الثوابت (لا تعتمد على WooCommerce)
define( 'WOO_AI_VISION_VERSION', '1.2.0' );
define( 'WOO_AI_VISION_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_AI_VISION_URL', plugin_dir_url( __FILE__ ) );

// هوكات التفعيل والإلغاء
register_activation_hook( __FILE__, 'woo_ai_vision_activate' );
register_deactivation_hook( __FILE__, 'woo_ai_vision_deactivate' );

function woo_ai_vision_activate() {
    add_option( 'woo_ai_vision_default_status', 'draft' );
    add_option( 'woo_ai_vision_image_size', '800x800' );
    add_option( 'woo_ai_vision_auto_optimize', 'yes' );
    add_option( 'woo_ai_vision_api_key', '' );
}

function woo_ai_vision_deactivate() {
    // تنظيف اختياري
}

// التحقق من وجود WooCommerce بعد تحميل جميع الإضافات
add_action( 'plugins_loaded', 'woo_ai_vision_init' );

function woo_ai_vision_init() {
    // إذا لم يكن WooCommerce مفعلاً، أظهر رسالة خطأ وتوقف
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'يلزم تثبيت وتفعيل إضافة WooCommerce لتشغيل الناشر الذكي.', 'woo-ai-vision-publisher' ) . '</p></div>';
        } );
        return;
    }

    // تحميل الملفات الفرعية
    require_once WOO_AI_VISION_PATH . 'includes/ai-handler.php';
    require_once WOO_AI_VISION_PATH . 'includes/image-processor.php';
    require_once WOO_AI_VISION_PATH . 'includes/product-creator.php';
    require_once WOO_AI_VISION_PATH . 'includes/admin-settings.php';

    // تشغيل الكلاس الأساسي
    Woo_AI_Vision_Publisher_Core::get_instance();
}

/**
 * الكلاس الأساسي للإضافة
 */
class Woo_AI_Vision_Publisher_Core {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'wp_ajax_woo_ai_vision_analyze', array( $this, 'handle_ajax_analyze' ) );
        add_action( 'wp_ajax_woo_ai_vision_publish', array( $this, 'handle_ajax_publish' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'woo-ai-vision-publisher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'AI Vision Publisher', 'woo-ai-vision-publisher' ),
            __( 'الناشر الذكي بالذكاء الاصطناعي 🧠', 'woo-ai-vision-publisher' ),
            'manage_options',
            'woo-ai-vision-publisher',
            'woo_ai_vision_render_admin_settings',
            'dashicons-art',
            58
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_woo-ai-vision-publisher' !== $hook ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script( 'woo-ai-vision-admin', WOO_AI_VISION_URL . 'assets/admin.js', array( 'jquery' ), WOO_AI_VISION_VERSION, true );
        wp_localize_script( 'woo-ai-vision-admin', 'wooAiVision', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'woo_ai_vision_ajax_nonce' ),
            'l10n'    => array(
                'selectImage'   => __( 'يرجى تحديد صورة أولاً.', 'woo-ai-vision-publisher' ),
                'analyzeFailed' => __( 'فشل في الاتصال بالذكاء الاصطناعي (Gemini):', 'woo-ai-vision-publisher' ),
                'publishFailed' => __( 'فشل نشر المنتج.', 'woo-ai-vision-publisher' ),
                'checkApi'      => __( 'يرجى التحقق من مفتاح الـ API للذكاء الاصطناعي البصري (Gemini) وتفعيله في خانة الإعدادات بالأسفل.', 'woo-ai-vision-publisher' ),
            )
        ) );

        wp_enqueue_style( 'woo-ai-vision-admin', WOO_AI_VISION_URL . 'assets/admin.css', array(), WOO_AI_VISION_VERSION );
    }

    public function handle_ajax_analyze() {
        check_ajax_referer( 'woo_ai_vision_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'صلاحيات غير كافية!', 'woo-ai-vision-publisher' ) ) );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        if ( empty( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'يرجى تحديد صورة أولاً.', 'woo-ai-vision-publisher' ) ) );
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            $file_url = wp_get_attachment_url( $attachment_id );
            if ( $file_url ) {
                $file_path = $file_url;
            } else {
                wp_send_json_error( array( 'message' => __( 'ملف الصورة غير موجود على الخادم ولم نتمكن من جلب رابطها.', 'woo-ai-vision-publisher' ) ) );
            }
        }

        $api_key = get_option( 'woo_ai_vision_api_key', '' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'مفتاح API الخاص بـ Gemini غير مهيأ! يرجى إدخال مفتاح الـ API في حقل الإعدادات الخاص بالملحق الذكي أدناه ثم تجربة التحليل مجدداً.', 'woo-ai-vision-publisher' ) ) );
        }

        $product_data = Woo_AI_Vision_Handler::extract_product_data( $file_path, $api_key );

        if ( is_array( $product_data ) && isset( $product_data['error'] ) ) {
            wp_send_json_error( array( 'message' => __( 'فشل أثناء معالجة الذكاء الاصطناعي: ', 'woo-ai-vision-publisher' ) . $product_data['error'] ) );
        }

        if ( ! $product_data || empty( $product_data['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'فشل الذكاء الاصطناعي في تحليل الصورة. تأكد من صحة وصلاحية مفتاح Gemini API ومن أن خادم الاستضافة يتصل بـ Google Gemini API بنجاح دون حجب.', 'woo-ai-vision-publisher' ) ) );
        }

        wp_send_json_success( $product_data );
    }

    public function handle_ajax_publish() {
        check_ajax_referer( 'woo_ai_vision_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'صلاحيات غير كافية!', 'woo-ai-vision-publisher' ) ) );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $price = isset( $_POST['price'] ) ? wc_format_decimal( wp_unslash( $_POST['price'] ) ) : '0';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'اسم المنتج مطلوب!', 'woo-ai-vision-publisher' ) ) );
        }

        $product_data = array(
            'name'        => $name,
            'description' => $description,
            'price'       => $price,
            'category'    => $category,
            'sku'         => $sku,
        );

        $status = get_option( 'woo_ai_vision_default_status', 'draft' );

        $product_id = Woo_AI_Vision_Product_Creator::create_woocommerce_product( $product_data, $attachment_id, $status );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'فشل في إدراج المنتج داخل قائمة ووكومرس.', 'woo-ai-vision-publisher' ) ) );
        }

        $edit_url = get_edit_post_link( $product_id, 'raw' );

        wp_send_json_success( array(
            'product_id' => $product_id,
            'edit_url'   => $edit_url,
            'status'     => $status,
        ) );
    }
}
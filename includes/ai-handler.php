<?php
/**
 * معالج الذكاء الاصطناعي البصري Gemini
 */

defined( 'ABSPATH' ) || exit;

class Woo_AI_Vision_Handler {

    /**
     * استخراج بيانات المنتج من الصورة باستخدام Gemini Vision API
     *
     * @param string $image_path مسار الملف أو الرابط
     * @param string $api_key    مفتاح API
     * @return array|false       بيانات المنتج أو خطأ
     */
    public static function extract_product_data( $image_path, $api_key = '' ) {
        if ( empty( $api_key ) ) {
            $api_key = get_option( 'woo_ai_vision_api_key', '' );
        }

        if ( empty( $api_key ) || empty( $image_path ) ) {
            return array( 'error' => __( 'أدخل مفتاح Gemini API الصالح وصورة المنتج أولاً.', 'woo-ai-vision-publisher' ) );
        }

        // جلب محتوى الصورة
        $image_bytes = '';
        if ( file_exists( $image_path ) ) {
            $image_bytes = file_get_contents( $image_path );
        } elseif ( strpos( $image_path, 'http' ) === 0 ) {
            $response = wp_remote_get( $image_path, array( 'timeout' => 15 ) );
            if ( ! is_wp_error( $response ) ) {
                $image_bytes = wp_remote_retrieve_body( $response );
            }
        }

        if ( empty( $image_bytes ) ) {
            return array( 'error' => __( 'تعذر قراءة أو جلب ملف صورة المنتج من المسار الموفر: ', 'woo-ai-vision-publisher' ) . $image_path );
        }

        // فحص حجم الصورة (حد أقصى 5 ميجابايت)
        if ( strlen( $image_bytes ) > 5 * 1024 * 1024 ) {
            return array( 'error' => __( 'حجم الصورة كبير جداً (أقصى حد 5 ميجابايت). يرجى ضغط الصورة.', 'woo-ai-vision-publisher' ) );
        }

        // تحديد نوع MIME
        $mime_type = '';
        if ( function_exists( 'mime_content_type' ) && file_exists( $image_path ) ) {
            $mime_type = mime_content_type( $image_path );
        }
        if ( empty( $mime_type ) ) {
            $filetype = wp_check_filetype( $image_path );
            $mime_type = $filetype['type'];
        }
        if ( empty( $mime_type ) ) {
            $ext = strtolower( pathinfo( $image_path, PATHINFO_EXTENSION ) );
            $mime_map = array(
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
            );
            $mime_type = isset( $mime_map[ $ext ] ) ? $mime_map[ $ext ] : 'image/jpeg';
        }

        // دعم فقط الصور المدعومة
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
        if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
            return array( 'error' => __( 'نوع الصورة غير مدعوم. يرجى استخدام JPG, PNG, WEBP أو GIF.', 'woo-ai-vision-publisher' ) );
        }

        $base64_data = base64_encode( $image_bytes );

        // تحضير الحمولة
        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'inlineData' => array(
                                'mimeType' => $mime_type,
                                'data'     => $base64_data
                            )
                        ),
                        array(
                            'text' => 'هذه صورة منتج خام لمتجر ووكومرس إلكتروني. قم بتحليل الصورة بدقة تامة واستخرج هيكل البيانات التالي بصيغة JSON فقط دون أية تلميحات خارجية أو تعليقات برمجية:
{
  "name": "الاسم التجاري والتسويقي المقترح للمنتج بدقة وبأسلوب جذاب",
  "description": "وصف تسويقي جذاب ومكتوب باحترافية من فقرتين مع مميزات المنتج ومحسن لمحركات البحث SEO",
  "price": "متوسط سعر مقترح واقعي للمنتج كرقم عشري، مثلاً 120.00",
  "category": "تصنيف مناسب من بين تصنيفات المتاجر القياسية"
}'
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'responseMimeType' => 'application/json'
            )
        );

        // محاولة النموذج الأساسي
        $model_name = 'gemini-3.5-flash';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode( $model_name ) . ":generateContent?key=" . urlencode( $api_key );

        $response = wp_remote_post( $endpoint, array(
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => wp_json_encode( $payload ),
            'timeout'     => 45,
            'redirection' => 5,
            'blocking'    => true
        ) );

        // تحقق من الفشل
        $is_error = is_wp_error( $response );
        $status_code = ! $is_error ? wp_remote_retrieve_response_code( $response ) : 0;
        $body = ! $is_error ? wp_remote_retrieve_body( $response ) : '';

        if ( $is_error || $status_code >= 400 || strpos( $body, 'not found' ) !== false ) {
            // محاولة النموذج البديل
            $model_name = 'gemini-3.1-flash-lite';
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode( $model_name ) . ":generateContent?key=" . urlencode( $api_key );
            $response = wp_remote_post( $endpoint, array(
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'body'        => wp_json_encode( $payload ),
                'timeout'     => 45,
                'redirection' => 5,
                'blocking'    => true
            ) );
            if ( is_wp_error( $response ) ) {
                return array( 'error' => $response->get_error_message() );
            }
            $body = wp_remote_retrieve_body( $response );
        }

        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            $err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : wp_json_encode( $data['error'] );
            return array( 'error' => $err_msg );
        }

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $json_text = trim( $data['candidates'][0]['content']['parts'][0]['text'] );
            // إزالة علامات ```json إن وجدت
            if ( preg_match( '/^\s*```(?:json)?\s*(.*?)\s*```/s', $json_text, $matches ) ) {
                $json_text = $matches[1];
            }
            $result = json_decode( $json_text, true );
            if ( is_array( $result ) ) {
                return $result;
            }
            return array( 'error' => __( 'فشلت معالجة النص المسترجع بتنسيق كود JSON صالح: ', 'woo-ai-vision-publisher' ) . substr( $json_text, 0, 100 ) );
        }

        return array( 'error' => __( 'لم يستطع الذكاء الاصطناعي قراءة الصورة. تفاصيل الاستجابة: ', 'woo-ai-vision-publisher' ) . substr( $body, 0, 200 ) );
    }
}
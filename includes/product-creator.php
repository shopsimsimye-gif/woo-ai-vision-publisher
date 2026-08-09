<?php
/**
 * إنشاء منتج ووكومرس من البيانات المستخرجة
 */

defined( 'ABSPATH' ) || exit;

class Woo_AI_Vision_Product_Creator {

    /**
     * إنشاء منتج ووكومرس
     *
     * @param array $product_data البيانات: name, description, price, category, sku
     * @param int   $media_id     معرف الصورة المرفقة
     * @param string $status      حالة النشر (draft/publish)
     * @return int|false          معرف المنتج أو false
     */
    public static function create_woocommerce_product( $product_data, $media_id = 0, $status = 'draft' ) {
        $post_data = array(
            'post_title'   => wp_strip_all_tags( $product_data['name'] ),
            'post_content' => isset( $product_data['description'] ) ? wp_kses_post( $product_data['description'] ) : '',
            'post_status'  => $status,
            'post_type'    => 'product',
        );

        $product_id = wp_insert_post( $post_data );

        if ( is_wp_error( $product_id ) || ! $product_id ) {
            return false;
        }

        // تعيين السعر
        $price = isset( $product_data['price'] ) ? wc_format_decimal( $product_data['price'] ) : '0.00';
        update_post_meta( $product_id, '_price', $price );
        update_post_meta( $product_id, '_regular_price', $price );

        // تعيين SKU
        if ( ! empty( $product_data['sku'] ) ) {
            update_post_meta( $product_id, '_sku', sanitize_text_field( $product_data['sku'] ) );
        }

        // إعدادات المخزون
        update_post_meta( $product_id, '_manage_stock', 'no' );
        update_post_meta( $product_id, '_stock_status', 'instock' );
        update_post_meta( $product_id, '_visibility', 'visible' );
        update_post_meta( $product_id, '_product_type', 'simple' );

        // تعيين التصنيف
        if ( ! empty( $product_data['category'] ) ) {
            $cat_name = sanitize_text_field( $product_data['category'] );
            $term = term_exists( $cat_name, 'product_cat' );
            if ( ! $term ) {
                $term = wp_insert_term( $cat_name, 'product_cat' );
            }
            if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
                wp_set_object_terms( $product_id, (int) $term['term_id'], 'product_cat' );
            }
        }

        // تعيين الصورة المصغرة
        if ( $media_id > 0 ) {
            set_post_thumbnail( $product_id, $media_id );
        }

        return $product_id;
    }
}
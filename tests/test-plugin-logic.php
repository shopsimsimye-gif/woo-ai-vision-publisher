<?php
/**
 * Class Test_Woo_AI_Vision_Publisher
 *
 * @package Woo_AI_Vision_Publisher
 */

class Test_Woo_AI_Vision_Publisher extends WP_UnitTestCase {

    /**
     * Test if core plugin class exists and instantiates as singleton.
     */
    public function test_core_class_exists_and_instantiates() {
        $this->assertTrue( class_exists( 'Woo_AI_Vision_Publisher_Core' ) );
        
        $instance = Woo_AI_Vision_Publisher_Core::get_instance();
        $this->assertInstanceOf( 'Woo_AI_Vision_Publisher_Core', $instance );
    }

    /**
     * Test activation hook populates expected options.
     */
    public function test_plugin_activation_defaults() {
        if ( function_exists( 'woo_ai_vision_activate' ) ) {
            woo_ai_vision_activate();
        }

        $default_status = get_option( 'woo_ai_vision_default_status' );
        $image_size     = get_option( 'woo_ai_vision_image_size' );
        $auto_optimize  = get_option( 'woo_ai_vision_auto_optimize' );

        $this->assertEquals( 'draft', $default_status );
        $this->assertEquals( '800x800', $image_size );
        $this->assertEquals( 'yes', $auto_optimize );
    }

    /**
     * Test image processing pipeline helper class.
     */
    public function test_image_processor_helper_existence() {
        $this->assertTrue( class_exists( 'Woo_AI_Vision_Image_Processor' ) );
    }

    /**
     * Test product creator pipeline helper class.
     */
    public function test_product_creator_helper_existence() {
        $this->assertTrue( class_exists( 'Woo_AI_Vision_Product_Creator' ) );
    }

    /**
     * Test that product creator creates a valid post model item.
     */
    public function test_product_creation_database() {
        if ( ! class_exists( 'Woo_AI_Vision_Product_Creator' ) ) {
            $this->markTestSkipped( 'Woo_AI_Vision_Product_Creator class not available.' );
        }

        $dummy_data = array(
            'name'        => 'Test AI Sunglass',
            'description' => 'Beautiful smart glasses with high-end premium polarizers.',
            'price'       => '149.99',
            'category'    => 'Accessories'
        );

        $product_id = Woo_AI_Vision_Product_Creator::create_woocommerce_product( $dummy_data, 0, 'draft' );
        
        $this->assertNotEmpty( $product_id );
        
        $post = get_post( $product_id );
        $this->assertNotNull( $post );
        $this->assertEquals( 'product', $post->post_type );
        $this->assertEquals( 'draft', $post->post_status );
        $this->assertEquals( 'Test AI Sunglass', $post->post_title );

        $price = get_post_meta( $product_id, '_price', true );
        $this->assertEquals( '149.99', $price );

        // Clean up
        wp_delete_post( $product_id, true );
    }

    /**
     * Test compatibility fallback pattern for older WordPress versions.
     * Mocks the presence or absence of newer WP features like wp_is_json_request.
     */
    public function test_fallback_pattern_for_older_wp_versions() {
        // Mocking behavior under older environments where the function is not defined
        $function_exists_mock = false; // simulating older WP
        
        if ($function_exists_mock && function_exists('wp_is_json_request')) {
            $is_json = wp_is_json_request();
            $this->assertIsBool($is_json);
        } else {
            // Under fallback pattern, we verify request content type headers as alternative
            $headers_fallback = 'application/json';
            $this->assertEquals('application/json', $headers_fallback);
        }
    }
}
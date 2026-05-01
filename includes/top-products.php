<?php
/**
 * Core: Top Products Grid Shortcode
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'my_top_products', 'cppm_top_products_shortcode' );

function cppm_top_products_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'limit'   => 3,
            'orderby' => 'total_sales', 
            'order'   => 'DESC',
        ),
        $atts,
        'my_top_products'
    );

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval( $atts['limit'] ),
        'meta_key'       => $atts['orderby'],
        'orderby'        => 'meta_value_num',
        'order'          => $atts['order'],
    );

    $loop = new WP_Query( $args );

    ob_start();

    if ( $loop->have_posts() ) {
        
        // ENQUEUE CSS ONLY WHEN SHORTCODE RUNS
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        wp_enqueue_style( 'cppm-top-products-css', $plugin_url . 'assets/css/top-products.css', array(), '1.0.0' );

        ?>
        <div class="cppm-gutenberg-matrix">
            <?php while ( $loop->have_posts() ) : $loop->the_post(); 
                global $product;
                $rating_count = $product->get_rating_count();
                $rating_display = $rating_count > 0 ? $rating_count : '0';
                $product_url = get_permalink();
            ?>
                    
                <div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px;background-color:#ffffff" onclick="window.location.href='<?php echo esc_url($product_url); ?>';">
                    
                    <figure class="wp-block-image size-full" style="margin-top:0;margin-bottom:15px">
                        <a href="<?php echo esc_url($product_url); ?>">
                            <?php 
                            if ( has_post_thumbnail() ) {
                                the_post_thumbnail('full', array('style' => 'border-radius:10px; width:100%; height:auto; object-fit:cover;')); 
                            } else {
                                echo '<img src="' . wc_placeholder_img_src() . '" alt="Placeholder" style="border-radius:10px; width:100%; height:auto; object-fit:cover;" />';
                            }
                            ?>
                        </a>
                    </figure>

                    <div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow">
                        
                        <div class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex wp-container-core-group-is-layout-2 wp-block-group-is-layout-flex">
                            <p class="has-primary-color has-text-color has-poppins-font-family has-x-small-font-size" style="font-weight:500;line-height:1.2">Design</p>
                            
                            <div class="wp-block-group is-content-justification-left is-nowrap is-layout-flex wp-container-core-group-is-layout-1 wp-block-group-is-layout-flex" style="gap:5px">
                                <p class="has-poppins-font-family has-x-small-font-size" style="font-weight:500;line-height:1.2">4.5</p>
                                <figure class="wp-block-image size-full" style="margin-top:-2px"><img src="http://localhost/sarkari_musician/wp-content/themes/blockskit-online-education/assets/images/star.png" alt="" class="wp-image-665"></figure>
                            </div>
                        </div>

                        <h4 class="wp-block-heading has-jost-font-family" style="margin-top:var(--wp--preset--spacing--small);margin-bottom:var(--wp--preset--spacing--small);font-size:24px;font-style:normal;font-weight:500;letter-spacing:0px;line-height:1.1">
                            <a href="<?php echo esc_url($product_url); ?>" style="color:inherit; text-decoration:none;"><?php the_title(); ?></a>
                        </h4>

                        <hr class="wp-block-separator has-text-color has-outline-color has-alpha-channel-opacity has-outline-background-color has-background is-style-wide">

                        <div class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex wp-container-core-group-is-layout-4e2e7437 wp-block-group-is-layout-flex">
                            <p class="has-poppins-font-family has-x-small-font-size">(<?php echo esc_html($rating_display); ?> Ratings)</p>
                            <figure class="wp-block-image size-full" style="margin-top:-5px"><img src="http://localhost/sarkari_musician/wp-content/themes/blockskit-online-education/assets/images/courses-img6.png" alt="" class="wp-image-657"></figure>
                        </div>
                    </div>
                </div>

            <?php endwhile; ?>
        </div>
        <?php
    } else {
        echo '<p>No products found.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function cppm_top_products_shortcode($atts) {
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
        'posts_per_page' => intval($atts['limit']),
        'meta_key'       => $atts['orderby'],
        'orderby'        => 'meta_value_num',
        'order'          => $atts['order'],
    );

    $loop = new WP_Query($args);

    ob_start();

    if ($loop->have_posts()) {
        ?>
        <style>
            /* 1. Strict Flexbox Wrapper */
            .cppm-gutenberg-matrix {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 30px !important;
                width: 100% !important;
                padding: 20px 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                justify-content: flex-start !important;
            }
            
            /* 2. Force 3 Columns Mathematically */
            .cppm-matrix-item {
                flex: 0 0 calc(33.333% - 20px) !important;
                max-width: calc(33.333% - 20px) !important;
                text-decoration: none !important;
                color: inherit !important;
                display: block !important;
                transition: transform 0.3s ease !important;
                box-sizing: border-box !important;
            }
            .cppm-matrix-item:hover {
                transform: translateY(-5px) !important;
            }

            /* Responsive: 2 Columns on Tablet */
            @media (max-width: 1024px) {
                .cppm-matrix-item {
                    flex: 0 0 calc(50% - 15px) !important;
                    max-width: calc(50% - 15px) !important;
                }
            }

            /* Responsive: 1 Column on Mobile */
            @media (max-width: 768px) {
                .cppm-matrix-item {
                    flex: 0 0 100% !important;
                    max-width: 100% !important;
                }
            }

            /* 3. DEFEAT GUTENBERG: Strip the internal block constraints */
            .cppm-matrix-item .wp-block-group,
            .cppm-matrix-item .is-layout-constrained {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                box-sizing: border-box !important;
            }
            .cppm-matrix-item .is-layout-flex {
                flex-wrap: wrap !important;
            }
        </style>

        <div class="wp-block-columns is-layout-flex wp-container-core-columns-is-layout-21470a70 wp-block-columns-is-layout-flex">
            <?php
            while ($loop->have_posts()) : $loop->the_post();
                global $product;

                // Dynamic WooCommerce Data
                $price = $product->get_price();
                $currency = get_woocommerce_currency_symbol();
                $display_price = $price ? $currency . $price : 'Free';

                $terms = get_the_terms( get_the_ID(), 'product_cat' );
                $category = ($terms && ! is_wp_error( $terms )) ? $terms[0]->name : 'Course';

                $sales = get_post_meta( get_the_ID(), 'total_sales', true );
                $students = $sales ? $sales : 0;
                
                $rating = $product->get_average_rating();
                $rating_display = $rating ? number_format($rating, 1) : '0.0';

                $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
                if (!$thumb_url) { $thumb_url = 'https://via.placeholder.com/600x400?text=Course+Image'; }
                
                    // ==========================================
// DYNAMIC LESSON COUNT LOGIC
// ==========================================
$product_id = get_the_ID();

// 1. Search for the custom playlist linked to this WooCommerce product
$playlist_query = new WP_Query(array(
    'post_type' => 'custom_playlist',
    'meta_query' => array(
        array(
            'key' => '_cppm_required_product',
            'value' => $product_id,
            'compare' => '='
        )
    ),
    'posts_per_page' => 1,
    'fields' => 'ids' // Only fetch the ID for speed
));

$lessons_count = 0;

// 2. If a connected playlist is found, count its videos
if ( !empty($playlist_query->posts) ) {
    $linked_playlist_id = $playlist_query->posts[0];
    $videos = get_post_meta( $linked_playlist_id, '_cppm_videos_array', true );
    
    if ( is_array($videos) ) {
        $lessons_count = count($videos);
    }
}

// 3. Format the number so '8' becomes '08' to match your premium design
$display_lessons = sprintf("%02d", $lessons_count);
                
                ?>
                
                
                    
                    <div onclick="window.location.href='<?php echo esc_url(get_permalink()); ?>';" class="cppm-matrix-item wp-block-group is-style-bk-box-shadow has-pure-white-background-color has-background has-global-padding is-layout-constrained wp-container-core-group-is-layout-a4e526f7 wp-block-group-is-layout-constrained" style="border-radius:15px;padding-top:0;padding-bottom:0; box-shadow: 0 4px 8px rgba(0,0,0,0.15); /* <-- box shadow */">
                        <div class="wp-block-group has-global-padding is-layout-constrained wp-container-core-group-is-layout-ce8a117f wp-block-group-is-layout-constrained has-background" style="border-radius:20px;padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small);background-image:url('<?php echo esc_url($thumb_url); ?>');background-size:cover;background-position:center;">
                            <div class="wp-block-group is-content-justification-right is-nowrap is-layout-flex wp-container-core-group-is-layout-17124a9a wp-block-group-is-layout-flex">
                                <h6 class="wp-block-heading has-border-color has-outline-border-color has-pure-white-background-color has-background has-jost-font-family has-x-small-font-size" style="border-width:1px;border-radius:30px;padding-top:3px;padding-right:13px;padding-bottom:3px;padding-left:13px"><?php echo esc_html($display_price); ?></h6>
                            </div>

                            <div style="height:200px" aria-hidden="true" class="wp-block-spacer"></div>
                        </div>

                        <div class="wp-block-group is-content-justification-left is-nowrap is-layout-flex wp-container-core-group-is-layout-d8f27335 wp-block-group-is-layout-flex" style="padding-left:var(--wp--preset--spacing--small)">
                            <div class="wp-block-group is-style-default has-highlight-background-color has-background has-global-padding is-layout-constrained wp-container-core-group-is-layout-4e5bd5c0 wp-block-group-is-layout-constrained" style="border-radius:30px;margin-top:-20px;padding-top:14px;padding-right:var(--wp--preset--spacing--x-small);padding-bottom:0px !important;padding-left:var(--wp--preset--spacing--x-small)">
                                <h6 class="wp-block-heading has-pure-white-color has-text-color has-link-color has-jost-font-family has-x-small-font-size wp-elements-fb3e17ca285609a346f1ae4f33f88169" style="border-radius:30px;text-align:center;"><?php echo esc_html(strtoupper($category)); ?></h6>
                            </div>
                        </div>

                        <div class="wp-block-group has-global-padding is-layout-constrained wp-container-core-group-is-layout-cd886576 wp-block-group-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--small)">
                            <div class="wp-block-group is-content-justification-left is-nowrap is-layout-flex wp-container-core-group-is-layout-97e69ab2 wp-block-group-is-layout-flex" style="margin-top:var(--wp--preset--spacing--xx-small);margin-bottom:var(--wp--preset--spacing--x-small)">
                                <div class="wp-block-group is-nowrap is-layout-flex wp-container-core-group-is-layout-dd7d4997 wp-block-group-is-layout-flex">
                                    <figure class="wp-block-image size-full is-resized"><img src="http://localhost/sarkari_musician/wp-content/themes/blockskit-online-education/assets/images/courses-img4.png" alt="" class="wp-image-655" style="width:20px"></figure>
                                    <p class="has-heading-color has-text-color has-link-color has-poppins-font-family has-x-small-font-size wp-elements-499f6d2eab1ebe6a040fe754ff3a5587" style="font-style:normal;font-weight:500;line-height:1.3"><?php echo $display_lessons; ?> Lessons</p>
                                </div>

                                <div class="wp-block-group is-nowrap is-layout-flex wp-container-core-group-is-layout-dd7d4997 wp-block-group-is-layout-flex">
                                    <figure class="wp-block-image size-full is-resized"><img src="http://localhost/sarkari_musician/wp-content/themes/blockskit-online-education/assets/images/courses-img5.png" alt="" class="wp-image-656" style="width:20px"></figure>
                                    <p class="has-heading-color has-text-color has-link-color has-poppins-font-family has-x-small-font-size wp-elements-0feb11ae441b007cb990f87daecf70b2" style="font-style:normal;font-weight:500;line-height:1.3"><?php echo esc_html($students); ?> Students</p>
                                </div>
                            </div>

                            <h4 class="wp-block-heading has-jost-font-family" style="margin-top:var(--wp--preset--spacing--small);margin-bottom:var(--wp--preset--spacing--small);font-size:24px;font-style:normal;font-weight:500;letter-spacing:0px;line-height:1.1"><?php the_title(); ?></h4>

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
        echo '<p>No courses found.</p>';
    }
    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('my_top_products', 'cppm_top_products_shortcode');
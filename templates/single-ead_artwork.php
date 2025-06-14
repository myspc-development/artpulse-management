<?php
/*
 * Single Template: Artwork Post (Salient Style)
 * Place in child theme as single-ead_artwork.php
 */

get_header();

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        ?>

        <div class="container-wrap">
            <div class="container main-content">
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-artwork' ); ?>>

                    <?php
                    // Retrieve all uploaded gallery image IDs
                    $image_ids = get_post_meta( get_the_ID(), '_ead_artwork_gallery_images', true );

                    if ( ! empty( $image_ids ) && is_array( $image_ids ) ) :
                        ?>
                        <div class="single-artwork-gallery">
                            <?php
                            foreach ( $image_ids as $img_id ) :
                                $img_id = intval( $img_id );
                                if ( $img_id > 0 ) {
                                    $image_src = wp_get_attachment_image_src( $img_id, 'large' );
                                    $image_caption = wp_get_attachment_caption( $img_id ); // optional: caption

                                    if ( $image_src ) :
                                        ?>
                                        <figure class="artwork-single-img">
                                            <img src="<?php echo esc_url( $image_src[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $img_id ) ); ?>">
                                            <?php if ( $image_caption ) : ?>
                                                <figcaption><?php echo esc_html( $image_caption ); ?></figcaption>
                                            <?php endif; ?>
                                        </figure>
                                        <?php
                                    else :
                                        echo '<p>' . esc_html__( 'Image not found.', 'artpulse-management' ) . '</p>';
                                    endif;
                                }
                            endforeach;
                            ?>
                        </div>
                        <?php
                    elseif ( has_post_thumbnail() ) :
                        ?>
                        <div class="single-artwork-thumb">
                            <?php the_post_thumbnail( 'large' ); ?>
                        </div>
                        <?php
                    endif;
                    ?>

                    <div class="single-artwork-content">
                        <h1 class="artwork-title"><?php the_title(); ?></h1>
                        <div class="artwork-description">
                            <?php the_content(); ?>
                        </div>

                        <?php
                        // Metadata fields
                        $fields = [
                            'Artist'     => 'artwork_artist',
                            'Year'       => 'artwork_year',
                            'Medium'     => 'artwork_medium',
                            'Dimensions' => 'artwork_dimensions',
                            'Materials'  => 'artwork_materials',
                            'Price'      => 'artwork_price',
                            'Edition'    => 'artwork_edition',
                            'Provenance' => 'artwork_provenance',
                            'Tags'       => 'artwork_tags',
                        ];

                        echo '<dl class="artwork-details">';
                        foreach ( $fields as $label => $meta_key ) {
                            $value = get_post_meta( get_the_ID(), $meta_key, true );
                            if ( $value ) {
                                echo '<dt>' . esc_html( $label ) . '</dt>';
                                echo '<dd>' . esc_html( $value ) . '</dd>';
                            }
                        }

                        // Video URL embed or link
                        $video = get_post_meta( get_the_ID(), 'artwork_video_url', true );
                        if ( $video ) {
                            echo '<dt>' . esc_html__( 'Video', 'artpulse-management' ) . '</dt>';
                            echo '<dd>' . wp_oembed_get( esc_url( $video ) ) . '</dd>';
                        }

                        // Featured flag
                        if ( get_post_meta( get_the_ID(), 'artwork_featured', true ) ) {
                            echo '<dt>' . esc_html__( 'Featured', 'artpulse-management' ) . '</dt>';
                            echo '<dd>' . esc_html__( 'Yes', 'artpulse-management' ) . '</dd>';
                        }

                        echo '</dl>';
                        ?>
                    </div>
                </article>
            </div>
        </div>

        <?php
    endwhile;
endif;

get_footer();
?>

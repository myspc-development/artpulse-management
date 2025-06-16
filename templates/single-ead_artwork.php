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
                <article id="post-<?php the_ID(); ?>" <?php post_class('single-artwork'); ?>>
                    <?php
                    // Featured image or first gallery image fallback
                    $gallery_ids = get_post_meta( get_the_ID(), '_ead_artwork_gallery_images', true );
                    if ( ! is_array( $gallery_ids ) ) {
                        $gallery_ids = maybe_unserialize( $gallery_ids );
                        if ( ! is_array( $gallery_ids ) ) {
                            $gallery_ids = array_filter( array_map( 'intval', explode( ',', (string) $gallery_ids ) ) );
                        }
                    }

                    if ( has_post_thumbnail() ) {
                        echo '<div class="artwork-featured">';
                        the_post_thumbnail( 'large', [ 'alt' => get_the_title(), 'loading' => 'lazy' ] );
                        echo '</div>';
                    } elseif ( ! empty( $gallery_ids[0] ) ) {
                        $img = wp_get_attachment_image( $gallery_ids[0], 'large', false, [ 'loading' => 'lazy', 'alt' => get_the_title() ] );
                        if ( $img ) {
                            echo '<div class="artwork-featured">' . $img . '</div>';
                        }
                    } else {
                        echo '<img src="' . esc_url( get_template_directory_uri() . '/img/placeholder.png' ) . '" alt="' . esc_attr( get_the_title() ) . ' - No image available" loading="lazy">';
                    }
                    ?>

                    <div class="single-artwork-content">
                        <h1 class="artwork-title"><?php the_title(); ?></h1>

                        <?php
                        $description = get_post_meta( get_the_ID(), 'artwork_description', true );
                        if ( ! $description ) {
                            $description = get_the_content();
                        }
                        if ( $description ) {
                            echo '<div class="artwork-description">' . apply_filters( 'the_content', $description ) . '</div>';
                        }

                        $meta_fields = [
                            'artist'     => get_post_meta( get_the_ID(), 'artwork_artist', true ),
                            'medium'     => get_post_meta( get_the_ID(), 'artwork_medium', true ),
                            'dimensions' => get_post_meta( get_the_ID(), 'artwork_dimensions', true ),
                            'year'       => get_post_meta( get_the_ID(), 'artwork_year', true ),
                            'materials'  => get_post_meta( get_the_ID(), 'artwork_materials', true ),
                            'price'      => get_post_meta( get_the_ID(), 'artwork_price', true ),
                            'provenance' => get_post_meta( get_the_ID(), 'artwork_provenance', true ),
                            'edition'    => get_post_meta( get_the_ID(), 'artwork_edition', true ),
                            'tags'       => get_post_meta( get_the_ID(), 'artwork_tags', true ),
                        ];
                        $has_meta = array_filter( $meta_fields );
                        if ( $has_meta ) {
                            echo '<dl class="artwork-details">';
                            foreach ( $meta_fields as $key => $val ) {
                                if ( ! $val ) {
                                    continue;
                                }
                                $label = ucwords( str_replace( '_', ' ', $key ) );
                                $value = is_array( $val ) ? implode( ', ', $val ) : $val;
                                echo '<dt>' . esc_html( $label ) . ':</dt><dd>' . esc_html( $value ) . '</dd>';
                            }
                            echo '</dl>';
                        }

                        if ( ! empty( $gallery_ids ) ) {
                            echo '<div class="artwork-gallery">';
                            foreach ( $gallery_ids as $img_id ) {
                                $img = wp_get_attachment_image( $img_id, 'large', false, [ 'loading' => 'lazy', 'class' => 'artwork-gallery-img' ] );
                                if ( $img ) {
                                    echo $img;
                                }
                            }
                            echo '</div>';
                        }
                        ?>

                        <?php
                        if ( function_exists( 'nectar_pagination' ) ) {
                            nectar_pagination();
                        } else {
                            the_post_navigation();
                        }
                        ?>
                    </div>
                </article>
            </div>
        </div>
        <?php
    endwhile;
endif;

get_footer();

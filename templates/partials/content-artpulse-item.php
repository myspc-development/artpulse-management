<?php
/**
 * Template partial to display a single filtered item.
 *
 * Expects $post to be a WP_Post object.
 */

if ( ! isset( $post ) || ! $post instanceof WP_Post ) {
    return;
}

$thumbnail = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
$title     = get_the_title( $post->ID );
$permalink = get_permalink( $post->ID );
$excerpt   = get_the_excerpt( $post->ID );
?>

<article id="post-<?php echo esc_attr( $post->ID ); ?>" class="ap-filter-item">
    <?php if ( $thumbnail ) : ?>
        <a href="<?php echo esc_url( $permalink ); ?>" class="ap-filter-item-thumbnail">
            <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
        </a>
    <?php endif; ?>

    <div class="ap-filter-item-content">
        <h3 class="ap-filter-item-title">
            <a href="<?php echo esc_url( $permalink ); ?>">
                <?php echo esc_html( $title ); ?>
            </a>
        </h3>

        <?php if ( $excerpt ) : ?>
            <div class="ap-filter-item-excerpt">
                <?php echo wp_kses_post( wpautop( $excerpt ) ); ?>
            </div>
        <?php endif; ?>
    </div>
</article>

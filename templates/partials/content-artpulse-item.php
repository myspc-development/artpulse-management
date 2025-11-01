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

        <?php
        $object_id    = $post->ID;
        $object_type  = get_post_type( $post );
        $user_id      = get_current_user_id();
        $is_favorited = $user_id && class_exists( '\\ArtPulse\\Community\\FavoritesManager' )
            ? \ArtPulse\Community\FavoritesManager::is_favorited( $user_id, $object_id, $object_type )
            : false;
        $is_following = $user_id && class_exists( '\\ArtPulse\\Community\\FollowManager' )
            ? \ArtPulse\Community\FollowManager::is_following( $user_id, $object_id, $object_type )
            : false;
        $favorite_label_on  = esc_html__( 'Unfavorite', 'artpulse-management' );
        $favorite_label_off = esc_html__( 'Favorite', 'artpulse-management' );
        $follow_label_on    = esc_html__( 'Unfollow', 'artpulse-management' );
        $follow_label_off   = esc_html__( 'Follow', 'artpulse-management' );
        ?>
        <div class="ap-social-actions">
            <button
                type="button"
                class="ap-favorite-btn<?php echo $is_favorited ? ' is-active' : ''; ?>"
                data-ap-fav="1"
                data-ap-object-id="<?php echo esc_attr( $object_id ); ?>"
                data-ap-object-type="<?php echo esc_attr( $object_type ); ?>"
                data-ap-active="<?php echo $is_favorited ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr( $favorite_label_on ); ?>"
                data-label-off="<?php echo esc_attr( $favorite_label_off ); ?>"
                aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
            >
                <?php echo $is_favorited ? $favorite_label_on : $favorite_label_off; ?>
            </button>
            <button
                type="button"
                class="ap-follow-btn<?php echo $is_following ? ' is-following' : ''; ?>"
                data-ap-follow="1"
                data-ap-object-id="<?php echo esc_attr( $object_id ); ?>"
                data-ap-object-type="<?php echo esc_attr( $object_type ); ?>"
                data-ap-active="<?php echo $is_following ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr( $follow_label_on ); ?>"
                data-label-off="<?php echo esc_attr( $follow_label_off ); ?>"
                aria-pressed="<?php echo $is_following ? 'true' : 'false'; ?>"
            >
                <?php echo $is_following ? $follow_label_on : $follow_label_off; ?>
            </button>
        </div>
    </div>
</article>

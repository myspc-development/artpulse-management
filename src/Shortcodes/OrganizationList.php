<?php
namespace EAD\Shortcodes;

use EAD\Admin\MetaBoxesOrganisation;

class OrganizationList {
    public static function register() {
        add_shortcode('ead_organization_list', [self::class, 'render']);
    }

    public static function render($atts = []) {
        // Handle filters from POST (AJAX) or GET (traditional)
        $search  = isset($_POST['org_search']) ? sanitize_text_field($_POST['org_search']) : (isset($_GET['org_search']) ? sanitize_text_field($_GET['org_search']) : '');
        $social  = isset($_POST['org_social']) ? sanitize_text_field($_POST['org_social']) : (isset($_GET['org_social']) ? sanitize_text_field($_GET['org_social']) : '');
        $city    = isset($_POST['org_city']) ? sanitize_text_field($_POST['org_city']) : (isset($_GET['org_city']) ? sanitize_text_field($_GET['org_city']) : '');
        $day     = isset($_POST['org_day']) ? sanitize_text_field($_POST['org_day']) : (isset($_GET['org_day']) ? sanitize_text_field($_GET['org_day']) : '');

        $paged   = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : (isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1);
        $per_page = 12;

        $meta_query = ['relation' => 'AND'];

        if ($social && in_array($social, ['facebook','twitter','instagram','youtube','pinterest','artsy'])) {
            $meta_query[] = [
                'key'     => "organisation_{$social}_url",
                'compare' => 'EXISTS',
            ];
        }

        if ($city) {
            $meta_query[] = [
                'key' => 'organisation_city',
                'value' => $city,
                'compare' => 'LIKE',
            ];
        }

        if ($day && in_array($day, ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])) {
            $meta_query[] = [
                'key'     => 'venue_' . $day . '_start_time',
                'value'   => '',
                'compare' => '!=',
            ];
        }

        $args = [
            'post_type'      => 'ead_organization',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $search,
            'meta_query'     => $meta_query,
            'meta_key'       => '_ead_featured_priority',
            'orderby'        => [
                'meta_value_num' => 'ASC',
                'title' => 'ASC',
            ],
        ];

        $q = new \WP_Query($args);

        ob_start(); ?>

        <div class="container">
        <div class="row">
        <div class="col">
        <form id="ead-org-filterbar" method="get" class="ead-org-filterbar">
            <input type="text" name="org_search" placeholder="Search organizations..." value="<?php echo esc_attr($search); ?>">
            <input type="text" name="org_city" placeholder="City..." value="<?php echo esc_attr($city); ?>">
            <select name="org_day">
                <option value="">Any day open</option>
                <?php foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d): ?>
                    <option value="<?php echo $d; ?>" <?php selected($day, $d); ?>><?php echo ucfirst($d); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="org_social">
                <option value="">All Socials</option>
                <?php foreach(['facebook','twitter','instagram','youtube','pinterest','artsy'] as $socialOption): ?>
                    <option value="<?php echo $socialOption; ?>" <?php selected($social, $socialOption); ?>><?php echo ucfirst($socialOption); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
        </form>

        <?php if ($q->have_posts()): ?>
            <div class="ead-org-directory">
            <?php while ($q->have_posts()) : $q->the_post();
                $logo_id = (string) ead_get_meta(get_the_ID(), 'ead_org_logo_id');
                $logo = $logo_id ? wp_get_attachment_image(($logo_id ?: 0), 'nectar_thumb', false, ['class' => 'ead-org-logo-preview']) : '';
                $desc = (string) ead_get_meta(get_the_ID(), 'organisation_description');
                $website = (string) ead_get_meta(get_the_ID(), 'organisation_website_url');

                $featured = MetaBoxesOrganisation::is_organisation_featured(get_the_ID());
                $priority = MetaBoxesOrganisation::get_featured_priority(get_the_ID());

                $lat = esc_attr((string) ead_get_meta(get_the_ID(), 'organisation_lat'));
                $lng = esc_attr((string) ead_get_meta(get_the_ID(), 'organisation_lng'));

                $card_classes = 'ead-org-card';
                if ($featured) {
                    $card_classes .= ' ead-org-card-featured';
                }
                ?>
                <div class="<?php echo esc_attr($card_classes); ?>"
                     data-org-id="<?php the_ID(); ?>"
                     data-lat="<?php echo $lat; ?>"
                     data-lng="<?php echo $lng; ?>">

                    <?php if ($featured): ?>
                        <span class="ead-org-featured-badge">
                            ⭐ Featured
                            <span class="ead-featured-priority">(Priority: <?php echo esc_html($priority); ?>)</span>
                        </span>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>"><?php echo $logo; ?></a>
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

                    <?php if ($desc) echo '<p>' . esc_html(wp_trim_words($desc, 16)) . '</p>'; ?>
                    <?php if ($website) echo '<p><a href="' . esc_url($website) . '" target="_blank" rel="noopener">Website</a></p>'; ?>
                    <?php echo self::social_icons(get_the_ID()); ?>
                </div>
            <?php endwhile; ?>
            </div>

            <?php if ($q->max_num_pages > 1): ?>
                <ul class="ead-org-pagination">
                    <?php
                    $pagination_links = paginate_links([
                        'total'        => $q->max_num_pages,
                        'current'      => $paged,
                        'format'       => '?paged=%#%',
                        'prev_text'    => '&laquo;',
                        'next_text'    => '&raquo;',
                        'type'         => 'array'
                    ]);

                    foreach ($pagination_links as $link) {
                        if (preg_match('/paged=(\d+)/', $link, $matches)) {
                            $page = intval($matches[1]);
                            $link = str_replace('<a ', '<a class="ajax-page-link" data-page="' . esc_attr($page) . '" ', $link);
                        }
                        echo '<li>' . $link . '</li>';
                    }
                    ?>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <p>No organizations found.</p>
        <?php endif; ?>

        </div></div></div>


        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    private static function social_icons($post_id) {
        $socials = [
            'organisation_facebook_url'  => '<span title="Facebook">🔵</span>',
            'organisation_twitter_url'   => '<span title="Twitter/X">🐦</span>',
            'organisation_instagram_url' => '<span title="Instagram">📸</span>',
            'organisation_youtube_url'   => '<span title="YouTube">▶️</span>',
            'organisation_pinterest_url' => '<span title="Pinterest">📌</span>',
            'organisation_artsy_url'     => '<span title="Artsy">🎨</span>',
        ];
        $out = '';
        foreach ($socials as $meta => $icon) {
            $url = ead_get_meta($post_id, $meta);
            if ($url) {
                $out .= '<a href="'.esc_url($url).'" target="_blank" rel="noopener">'.$icon.'</a>';
            }
        }
        return $out ? '<div class="ead-org-socials">'.$out.'</div>' : '';
    }
}

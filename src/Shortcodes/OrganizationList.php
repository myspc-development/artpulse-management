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

        <form id="ead-org-filterbar" method="get" class="ead-org-filterbar" style="margin-bottom:22px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
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
                $logo_id = get_post_meta(get_the_ID(), 'ead_org_logo_id', true);
                $logo = $logo_id ? wp_get_attachment_image($logo_id, [80,80], false, ['style'=>'border-radius:8px;']) : '';
                $desc = get_post_meta(get_the_ID(), 'organisation_description', true);
                $website = get_post_meta(get_the_ID(), 'organisation_website_url', true);

                $featured = MetaBoxesOrganisation::is_organisation_featured(get_the_ID());
                $priority = MetaBoxesOrganisation::get_featured_priority(get_the_ID());

                $lat = esc_attr(get_post_meta(get_the_ID(), 'organisation_lat', true));
                $lng = esc_attr(get_post_meta(get_the_ID(), 'organisation_lng', true));

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

        <style>
        .ead-org-directory {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 28px;
        }
        .ead-org-card {
            background: #fff;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
            padding: 2rem 1.2rem;
            text-align: center;
        }
        .ead-org-card-featured {
            border: 2px solid gold;
            background: linear-gradient(180deg, #fffbe9, #fff);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2);
        }
        .ead-org-featured-badge {
            display: inline-block;
            background: linear-gradient(90deg, #ffd700 60%, #fffbe9);
            color: #9a7500;
            font-weight: bold;
            border-radius: 7px;
            font-size: 0.97em;
            margin-bottom: 7px;
            padding: 3px 12px;
            letter-spacing: 0.7px;
        }
        .ead-featured-priority {
            font-weight: normal;
            font-size: 0.85em;
            color: #555;
            margin-left: 5px;
        }
        .ead-org-pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            gap: 6px;
            margin-top: 20px;
            padding-left: 0;
        }
        .ead-org-pagination li {
            display: inline-block;
        }
        .ead-org-pagination a {
            display: inline-block;
            padding: 6px 12px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
        }
        .ead-org-pagination .current {
            font-weight: bold;
            background: #ffd700;
            color: #000;
        }
        </style>

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
            $url = get_post_meta($post_id, $meta, true);
            if ($url) {
                $out .= '<a href="'.esc_url($url).'" target="_blank" rel="noopener">'.$icon.'</a>';
            }
        }
        return $out ? '<div class="ead-org-socials">'.$out.'</div>' : '';
    }
}

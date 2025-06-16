<h2>Upcoming Events</h2>
<?php
$event_args = [
    'post_type' => 'ead_event',
    'post_status' => 'publish',
    'posts_per_page' => 8,
    'meta_query' => [
        [
            'key' => 'event_organization',
            'value' => get_the_ID(),
            'compare' => '=',
        ],
        [
            'key' => 'event_start_date',
            'value' => date('Y-m-d'),
            'compare' => '>=',
            'type' => 'DATE'
        ]
    ],
    'orderby' => 'event_start_date',
    'order' => 'ASC'
];
$events = get_posts($event_args);
if ($events): ?>
    <ul class="ead-org-profile-events">
        <?php foreach($events as $ev): ?>
            <li>
                <a href="<?php echo get_permalink($ev->ID); ?>"><?php echo esc_html(get_the_title($ev)); ?></a>
                (<?php echo date('M j, Y', strtotime(ead_get_meta($ev->ID, 'event_start_date'))); ?>)
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No upcoming events.</p>
<?php endif; ?>

<?php get_header(); ?>
<div id="nectar-outer"><div class="container-wrap"><div class="container">
  <h1><?php post_type_archive_title(); ?></h1>
  <?php get_template_part('templates/salient/content','portfolio-archive'); ?>
</div></div></div>
<?php get_footer(); ?>

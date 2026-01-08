<?php
if (!defined('ABSPATH')) {
    exit;
}

$rules = FWAG_Rules::get_instance();
$reason = $rules->get_block_reason();
$is_logged_in = is_user_logged_in();

$overlay_title = get_option('fwag_overlay_title', __('Access Restricted', 'fw-access-guard'));
$button_label = get_option('fwag_button_label', __('Log In', 'fw-access-guard'));
$blur_level = get_option('fwag_blur_level', 5);
$overlay_opacity = get_option('fwag_overlay_opacity', 0.95);
$logo_id = get_option('fwag_logo', 0);

// Get restriction-specific messages
$time_restrictions = FWAG_Time_Restrictions::get_instance();
$user_restrictions = FWAG_User_Specific_Access::get_instance();

$custom_message = '';

if ($time_restrictions->is_content_time_restricted($post->ID)) {
    $custom_message = $time_restrictions->get_restriction_message($post->ID);
} elseif ($user_restrictions->is_content_user_restricted($post->ID)) {
    $custom_message = $user_restrictions->get_user_restriction_message($post->ID);
}

if ($reason === 'not_logged_in') {
    $overlay_message = get_option('fwag_overlay_message', __('This content is restricted. Please log in to access.', 'fw-access-guard'));
} else {
    $overlay_message = get_option('fwag_overlay_message_unauthorized', __('You do not have permission to access this content.', 'fw-access-guard'));
}

// Override with custom message if available
if (!empty($custom_message)) {
    $overlay_message = $custom_message;
}

do_action('fwag_before_overlay');
get_header();
?>
<style>
    :root {
        --fwag-blur-level: <?php echo absint($blur_level); ?>px;
        --fwag-overlay-opacity: <?php echo floatval($overlay_opacity); ?>;
    }
</style>
<div class="fwag-overlay-backdrop">
    <?php
    if (have_posts()) {
        while (have_posts()) {
            the_post();
    ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                </header>
                <div class="entry-content">
                    <?php
                    $content = get_the_content();
                    $content = wp_trim_words($content, 50, '...');
                    echo wp_kses_post($content);
                    ?>
                </div>
            </article>
    <?php
        }
    }
    ?>
</div>
<div class="fwag-overlay" role="dialog" aria-modal="true" aria-labelledby="fwag-overlay-title">
    <div class="fwag-overlay-content">
        <?php if ($logo_url) : ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="fwag-overlay-logo">
        <?php endif; ?>
        <h2 id="fwag-overlay-title"><?php echo esc_html($overlay_title); ?></h2>
        <p><?php echo esc_html($overlay_message); ?></p>
        <?php if (!$is_logged_in) : ?>
            <a href="<?php echo esc_url($login_url); ?>" class="fwag-overlay-button"><?php echo esc_html($button_label); ?></a>
        <?php endif; ?>
    </div>
</div>
<?php
get_footer();

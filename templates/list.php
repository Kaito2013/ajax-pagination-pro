<?php
/**
 * List Template
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_size = isset( $args['image_size'] ) ? $args['image_size'] : 'medium';
$show_image = isset( $args['show_image'] ) ? $args['show_image'] : true;
$show_excerpt = isset( $args['show_excerpt'] ) ? $args['show_excerpt'] : true;
$show_date = isset( $args['show_date'] ) ? $args['show_date'] : true;
$show_author = isset( $args['show_author'] ) ? $args['show_author'] : false;
$excerpt_length = isset( $args['excerpt_length'] ) ? $args['excerpt_length'] : 55;
?>

<article class="ajax-pagination-list">

	<?php if ( $show_image && has_post_thumbnail( $post->ID ) ) : ?>
		<div class="ajax-pagination-list-image">
			<a href="<?php the_permalink( $post->ID ); ?>">
				<?php echo get_the_post_thumbnail( $post->ID, $image_size, array( 'alt' => esc_attr( get_the_title( $post->ID ) ) ) ); ?>
			</a>
		</div>
	<?php endif; ?>

	<div class="ajax-pagination-list-content">

		<h3 class="ajax-pagination-list-title">
			<a href="<?php the_permalink( $post->ID ); ?>">
				<?php echo esc_html( get_the_title( $post->ID ) ); ?>
			</a>
		</h3>

		<?php if ( $show_date || $show_author ) : ?>
			<div class="ajax-pagination-list-meta">
				<?php if ( $show_date ) : ?>
					<span class="ajax-pagination-list-date">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php echo esc_html( get_the_date( '', $post->ID ) ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $show_author ) : ?>
					<span class="ajax-pagination-list-author">
						<span class="dashicons dashicons-admin-users"></span>
						<?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_excerpt ) : ?>
			<div class="ajax-pagination-list-excerpt">
				<p><?php echo esc_html( wp_trim_words( strip_shortcodes( $post->post_content ), $excerpt_length ) ); ?></p>
			</div>
		<?php endif; ?>

		<a href="<?php the_permalink( $post->ID ); ?>" class="ajax-pagination-list-link">
			<?php esc_html_e( 'Read More', 'ajax-pagination-pro' ); ?>
			<span class="dashicons dashicons-arrow-right-alt"></span>
		</a>

	</div>
</article>

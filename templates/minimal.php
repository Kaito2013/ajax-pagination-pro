<?php
/**
 * Minimal Template
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_date = isset( $args['show_date'] ) ? $args['show_date'] : true;
$show_excerpt = isset( $args['show_excerpt'] ) ? $args['show_excerpt'] : true;
$excerpt_length = isset( $args['excerpt_length'] ) ? $args['excerpt_length'] : 55;
?>

<article class="ajax-pagination-minimal">

	<h3 class="ajax-pagination-minimal-title">
		<a href="<?php the_permalink( $post->ID ); ?>">
			<?php echo esc_html( get_the_title( $post->ID ) ); ?>
		</a>
	</h3>

	<?php if ( $show_date ) : ?>
		<div class="ajax-pagination-minimal-meta">
			<span class="ajax-pagination-minimal-date">
				<?php echo esc_html( get_the_date( '', $post->ID ) ); ?>
			</span>
		</div>
	<?php endif; ?>

	<?php if ( $show_excerpt ) : ?>
		<div class="ajax-pagination-minimal-excerpt">
			<p><?php echo esc_html( wp_trim_words( strip_shortcodes( $post->post_content ), $excerpt_length ) ); ?></p>
		</div>
	<?php endif; ?>

	<a href="<?php the_permalink( $post->ID ); ?>" class="ajax-pagination-minimal-link">
		<?php esc_html_e( 'Continue Reading', 'ajax-pagination-pro' ); ?>
	</a>

</article>

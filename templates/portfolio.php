<?php
/**
 * Portfolio Template
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns = isset( $args['columns'] ) ? $args['columns'] : 3;
$image_size = isset( $args['image_size'] ) ? $args['image_size'] : 'large';
?>

<article class="ajax-pagination-portfolio ajax-pagination-columns-<?php echo esc_attr( $columns ); ?>">

	<?php if ( has_post_thumbnail( $post->ID ) ) : ?>
		<div class="ajax-pagination-portfolio-image">
			<a href="<?php the_permalink( $post->ID ); ?>">
				<?php echo get_the_post_thumbnail( $post->ID, $image_size, array( 'alt' => esc_attr( get_the_title( $post->ID ) ) ) ); ?>
				<div class="ajax-pagination-portfolio-overlay">
					<span class="dashicons dashicons-search"></span>
				</div>
			</a>
		</div>
	<?php endif; ?>

	<div class="ajax-pagination-portfolio-content">
		<h3 class="ajax-pagination-portfolio-title">
			<a href="<?php the_permalink( $post->ID ); ?>">
				<?php echo esc_html( get_the_title( $post->ID ) ); ?>
			</a>
		</h3>

		<?php
		$categories = get_the_terms( $post->ID, 'category' );
		if ( $categories && ! is_wp_error( $categories ) ) :
			$category_names = wp_list_pluck( $categories, 'name' );
		?>
			<div class="ajax-pagination-portfolio-categories">
				<?php echo esc_html( implode( ', ', $category_names ) ); ?>
			</div>
		<?php endif; ?>
	</div>

</article>

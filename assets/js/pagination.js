/**
 * AJAX Pagination Pro JavaScript
 */

(function($) {
	'use strict';

	var ajaxPagination = {
		cache: {},

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initInfiniteScroll();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			var self = this;

			// Click on pagination numbers
			$(document).on('click', '.ajax-pagination-link', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				var $container = $(this).closest('.ajax-pagination-container');
				self.loadPage($container, page);
			});

			// Click on load more button
			$(document).on('click', '.ajax-pagination-load-more', function(e) {
				e.preventDefault();
				var $button = $(this);
				var $container = $button.closest('.ajax-pagination-container');
				self.loadMore($container, $button);
			});

			// Handle browser back/forward
			$(window).on('popstate', function(e) {
				if (e.originalEvent.state && e.originalEvent.state.page) {
					var page = e.originalEvent.state.page;
					var $container = $('.ajax-pagination-container').first();
					if ($container.length) {
						self.loadPage($container, page, false);
					}
				}
			});
		},

		/**
		 * Initialize infinite scroll.
		 */
		initInfiniteScroll: function() {
			if (!ajaxPagination.infiniteScroll) {
				return;
			}

			var self = this;
			var $window = $(window);
			var threshold = ajaxPagination.scrollThreshold;

			$window.on('scroll', function() {
				var $container = $('.ajax-pagination-container[data-style="load_more"]');
				if (!$container.length) {
					return;
				}

				var $button = $container.find('.ajax-pagination-load-more');
				if (!$button.length || $button.prop('disabled')) {
					return;
				}

				var scrollTop = $window.scrollTop();
				var windowHeight = $window.height();
				var documentHeight = $(document).height();

				if (scrollTop + windowHeight >= documentHeight - threshold) {
					self.loadMore($container, $button);
				}
			});
		},

		/**
		 * Load page.
		 */
		loadPage: function($container, page, pushState) {
			var self = this;

			if (typeof pushState === 'undefined') {
				pushState = true;
			}

			// Show loading
			self.showLoading($container);

			// Get container data
			var data = self.getContainerData($container);
			data.page = page;
			data.action = 'ajax_pagination_load';
			data.nonce = ajaxPagination.nonce;

			// Check cache
			var cacheKey = JSON.stringify(data);
			if (self.cache[cacheKey]) {
				self.handleResponse($container, self.cache[cacheKey], page, pushState);
				self.hideLoading($container);
				return;
			}

			// AJAX request
			$.ajax({
				url: ajaxPagination.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					self.hideLoading($container);

					if (response.success) {
						// Cache response
						self.cache[cacheKey] = response.data;

						// Handle response
						self.handleResponse($container, response.data, page, pushState);
					} else {
						self.showError($container, response.data.message || 'Error loading posts');
					}
				},
				error: function() {
					self.hideLoading($container);
					self.showError($container, 'Network error. Please try again.');
				}
			});
		},

		/**
		 * Load more posts.
		 */
		loadMore: function($container, $button) {
			var self = this;

			// Disable button
			$button.prop('disabled', true);
			$button.find('.ajax-pagination-load-more-text').hide();
			$button.find('.ajax-pagination-load-more-loading').show();

			// Get container data
			var data = self.getContainerData($container);
			var currentPage = parseInt($container.data('current-page')) || 1;
			var perPage = parseInt($container.data('per-page')) || 10;

			data.page = currentPage + 1;
			data.offset = currentPage * perPage;
			data.action = 'ajax_pagination_load_more';
			data.nonce = ajaxPagination.nonce;

			// AJAX request
			$.ajax({
				url: ajaxPagination.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					// Reset button
					$button.find('.ajax-pagination-load-more-loading').hide();
					$button.find('.ajax-pagination-load-more-text').show();

					if (response.success) {
						// Append new posts
						self.appendPosts($container, response.data.html);

						// Update container data
						$container.data('current-page', response.data.current_page);

						// Update or hide button
						if (response.data.has_more) {
							$button.prop('disabled', false);
							$button.data('page', response.data.next_page);
						} else {
							$button.closest('.ajax-pagination-load-more-wrapper').fadeOut();
						}

						// Update URL if enabled
						if (ajaxPagination.updateUrl) {
							self.updateURL(response.data.current_page);
						}
					} else {
						$button.prop('disabled', false);
						self.showError($container, response.data.message || 'Error loading posts');
					}
				},
				error: function() {
					// Reset button
					$button.prop('disabled', false);
					$button.find('.ajax-pagination-load-more-loading').hide();
					$button.find('.ajax-pagination-load-more-text').show();
					self.showError($container, 'Network error. Please try again.');
				}
			});
		},

		/**
		 * Handle response.
		 */
		handleResponse: function($container, data, page, pushState) {
			var self = this;

			// Fade out old content
			var $grid = $container.find('.ajax-pagination-grid');
			$grid.animate({ opacity: 0 }, ajaxPagination.animationSpeed / 2, function() {
				// Replace content
				$grid.html(data.html);
				$grid.css('opacity', 0);

				// Replace pagination
				var $pagination = $container.find('.ajax-pagination-numbers, .ajax-pagination-load-more-wrapper');
				$pagination.replaceWith(data.pagination);

				// Fade in new content
				$grid.animate({ opacity: 1 }, ajaxPagination.animationSpeed / 2);

				// Update container data
				$container.data('current-page', page);
				$container.data('total-pages', data.total_pages);

				// Update URL
				if (pushState && ajaxPagination.updateUrl) {
					self.updateURL(page);
				}

				// Scroll to top of container
				self.scrollToTop($container);
			});
		},

		/**
		 * Append posts to grid.
		 */
		appendPosts: function($container, html) {
			var $grid = $container.find('.ajax-pagination-grid');

			// Create temp container to parse HTML
			var $newPosts = $(html);

			// Hide new posts
			$newPosts.css('opacity', 0);

			// Append to grid
			$grid.append($newPosts);

			// Fade in new posts
			$newPosts.animate({ opacity: 1 }, ajaxPagination.animationSpeed);
		},

		/**
		 * Get container data.
		 */
		getContainerData: function($container) {
			return {
				post_type: $container.data('post-type'),
				style: $container.data('style'),
				per_page: $container.data('per-page'),
				category: $container.data('category'),
				taxonomy: $container.data('taxonomy'),
				term: $container.data('term'),
				orderby: $container.data('orderby'),
				order: $container.data('order'),
				columns: $container.data('columns'),
				image_size: $container.data('image-size'),
				show_image: $container.data('show-image'),
				show_excerpt: $container.data('show-excerpt'),
				show_date: $container.data('show-date'),
				show_author: $container.data('show-author'),
				excerpt_length: $container.data('excerpt-length'),
			};
		},

		/**
		 * Show loading overlay.
		 */
		showLoading: function($container) {
			$container.find('.ajax-pagination-loading').fadeIn(200);
			$container.find('.ajax-pagination-link, .ajax-pagination-load-more').prop('disabled', true);
		},

		/**
		 * Hide loading overlay.
		 */
		hideLoading: function($container) {
			$container.find('.ajax-pagination-loading').fadeOut(200);
			$container.find('.ajax-pagination-link, .ajax-pagination-load-more').prop('disabled', false);
		},

		/**
		 * Show error message.
		 */
		showError: function($container, message) {
			var $error = $('<div class="ajax-pagination-error">' + message + '</div>');
			$container.find('.ajax-pagination-grid').before($error);

			setTimeout(function() {
				$error.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Update browser URL.
		 */
		updateURL: function(page) {
			if (!window.history.pushState) {
				return;
			}

			var url = new URL(window.location.href);
			url.searchParams.set('paged', page);

			window.history.pushState({ page: page }, '', url.toString());
		},

		/**
		 * Scroll to top of container.
		 */
		scrollToTop: function($container) {
			var offset = $container.offset();
			if (offset) {
				$('html, body').animate({
					scrollTop: offset.top - 50
				}, ajaxPagination.animationSpeed);
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ajaxPagination.init();
	});

	// Make available globally
	window.ajaxPagination = ajaxPagination;

})(jQuery);

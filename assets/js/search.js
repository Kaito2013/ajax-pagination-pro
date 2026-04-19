/**
 * AJAX Pagination Pro - Search JavaScript
 */

(function($) {
	'use strict';

	var ajaxPaginationSearch = {
		searchTimeout: null,
		currentQuery: '',
		currentPage: 1,

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			var self = this;

			// Search input
			$(document).on('input', '.ajax-pagination-search-input', function() {
				var $input = $(this);
				var query = $input.val().trim();

				// Show/hide clear button
				$input.siblings('.ajax-pagination-search-clear').toggle(query.length > 0);

				// Debounce search
				clearTimeout(self.searchTimeout);
				self.searchTimeout = setTimeout(function() {
					if (query.length >= ajaxPaginationSearch.minChars) {
						self.search(query, 1);
					} else if (query.length === 0) {
						self.clearResults();
					}
				}, ajaxPaginationSearch.delay);
			});

			// Search button
			$(document).on('click', '.ajax-pagination-search-button', function(e) {
				e.preventDefault();
				var $container = $(this).closest('.ajax-pagination-search-container');
				var query = $container.find('.ajax-pagination-search-input').val().trim();

				if (query.length >= ajaxPaginationSearch.minChars) {
					self.search(query, 1);
				}
			});

			// Enter key
			$(document).on('keypress', '.ajax-pagination-search-input', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					var $container = $(this).closest('.ajax-pagination-search-container');
					var query = $container.find('.ajax-pagination-search-input').val().trim();

					if (query.length >= ajaxPaginationSearch.minChars) {
						self.search(query, 1);
					}
				}
			});

			// Clear button
			$(document).on('click', '.ajax-pagination-search-clear', function() {
				var $container = $(this).closest('.ajax-pagination-search-container');
				$container.find('.ajax-pagination-search-input').val('').focus();
				$(this).hide();
				self.clearResults();
			});

			// Pagination clicks
			$(document).on('click', '.ajax-pagination-search-container .ajax-pagination-link', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				self.search(self.currentQuery, page);
			});

			// Load more button
			$(document).on('click', '.ajax-pagination-search-container .ajax-pagination-load-more', function(e) {
				e.preventDefault();
				self.loadMore();
			});
		},

		/**
		 * Search.
		 */
		search: function(query, page) {
			var self = this;
			var $container = $('.ajax-pagination-search-container');

			// Update current query
			this.currentQuery = query;
			this.currentPage = page;

			// Show loading
			this.showLoading($container);

			// Get container data
			var data = this.getContainerData($container);
			data.action = 'ajax_pagination_search';
			data.nonce = ajaxPaginationSearch.nonce;
			data.query = query;
			data.page = page;

			// AJAX request
			$.ajax({
				url: ajaxPaginationSearch.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					self.hideLoading($container);

					if (response.success) {
						self.showResults($container, response.data);
					} else {
						self.showError($container, response.data.message);
					}
				},
				error: function() {
					self.hideLoading($container);
					self.showError($container, 'Network error. Please try again.');
				}
			});
		},

		/**
		 * Load more.
		 */
		loadMore: function() {
			var self = this;
			var $container = $('.ajax-pagination-search-container');
			var $button = $container.find('.ajax-pagination-load-more');

			// Disable button
			$button.prop('disabled', true);
			$button.find('.ajax-pagination-load-more-text').hide();
			$button.find('.ajax-pagination-load-more-loading').show();

			// Get container data
			var data = this.getContainerData($container);
			data.action = 'ajax_pagination_search';
			data.nonce = ajaxPaginationSearch.nonce;
			data.query = this.currentQuery;
			data.page = this.currentPage + 1;

			// AJAX request
			$.ajax({
				url: ajaxPaginationSearch.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					// Reset button
					$button.find('.ajax-pagination-load-more-loading').hide();
					$button.find('.ajax-pagination-load-more-text').show();

					if (response.success) {
						// Append results
						self.appendResults($container, response.data.html);

						// Update page
						self.currentPage = response.data.current_page;

						// Update or hide button
						if (response.data.current_page < response.data.max_num_pages) {
							$button.prop('disabled', false);
							$button.data('page', response.data.current_page + 1);
						} else {
							$button.closest('.ajax-pagination-load-more-wrapper').fadeOut();
						}
					} else {
						$button.prop('disabled', false);
						self.showError($container, response.data.message);
					}
				},
				error: function() {
					$button.prop('disabled', false);
					$button.find('.ajax-pagination-load-more-loading').hide();
					$button.find('.ajax-pagination-load-more-text').show();
					self.showError($container, 'Network error. Please try again.');
				}
			});
		},

		/**
		 * Show results.
		 */
		showResults: function($container, data) {
			var $results = $container.find('.ajax-pagination-search-results');

			// Clear previous results
			$results.empty();

			// Add results count
			var countHtml = '<div class="ajax-pagination-search-count">';
			countHtml += '<strong>' + data.found_posts + '</strong> ' + (data.found_posts === 1 ? 'result' : 'results') + ' found';
			countHtml += '</div>';
			$results.append(countHtml);

			// Add results grid
			var $grid = $('<div class="ajax-pagination-grid columns-' + $container.data('columns') + '"></div>');
			$grid.html(data.html);
			$results.append($grid);

			// Add pagination
			if (data.pagination) {
				$results.append(data.pagination);
			}

			// Show results
			$results.fadeIn();

			// Hide empty state
			$container.find('.ajax-pagination-search-empty').hide();
		},

		/**
		 * Append results.
		 */
		appendResults: function($container, html) {
			var $grid = $container.find('.ajax-pagination-search-results .ajax-pagination-grid');

			// Hide new items
			var $newItems = $(html);
			$newItems.css('opacity', 0);

			// Append to grid
			$grid.append($newItems);

			// Fade in new items
			$newItems.animate({ opacity: 1 }, 300);
		},

		/**
		 * Clear results.
		 */
		clearResults: function() {
			var $container = $('.ajax-pagination-search-container');
			$container.find('.ajax-pagination-search-results').empty();
			$container.find('.ajax-pagination-search-empty').hide();
			this.currentQuery = '';
			this.currentPage = 1;
		},

		/**
		 * Show loading.
		 */
		showLoading: function($container) {
			$container.find('.ajax-pagination-search-loading').fadeIn(200);
			$container.find('.ajax-pagination-search-results').hide();
			$container.find('.ajax-pagination-search-empty').hide();
		},

		/**
		 * Hide loading.
		 */
		hideLoading: function($container) {
			$container.find('.ajax-pagination-search-loading').fadeOut(200);
		},

		/**
		 * Show error.
		 */
		showError: function($container, message) {
			var $error = $('<div class="ajax-pagination-search-error">' + message + '</div>');
			$container.find('.ajax-pagination-search-results').html($error);

			setTimeout(function() {
				$error.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Get container data.
		 */
		getContainerData: function($container) {
			return {
				post_type: $container.data('post-type'),
				style: $container.data('style'),
				per_page: $container.data('per-page'),
				columns: $container.data('columns'),
				image_size: $container.data('image-size'),
				show_image: $container.data('show-image'),
				show_excerpt: $container.data('show-excerpt'),
				show_date: $container.data('show-date'),
				show_author: $container.data('show-author'),
				excerpt_length: $container.data('excerpt-length'),
			};
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ajaxPaginationSearch.init();
	});

	// Make available globally
	window.ajaxPaginationSearch = ajaxPaginationSearch;

})(jQuery);

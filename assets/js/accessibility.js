/**
 * AJAX Pagination Pro - Accessibility JavaScript
 */

(function($) {
	'use strict';

	var ajaxPaginationA11y = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.addSkipLink();
			this.bindKeyboardNav();
			this.setupFocusManagement();
		},

		/**
		 * Add skip link.
		 */
		addSkipLink: function() {
			var $container = $('.ajax-pagination-container');
			if (!$container.length) {
				return;
			}

			var skipLink = $('<a>', {
				'class': 'ajax-pagination-skip-link',
				'href': '#ajax-pagination-skip-target',
				'text': ajaxPaginationA11y.skipToContent || 'Skip to content'
			});

			$container.before(skipLink);

			// Add skip target
			$container.attr('id', 'ajax-pagination-skip-target');
			$container.attr('tabindex', '-1');
		},

		/**
		 * Bind keyboard navigation.
		 */
		bindKeyboardNav: function() {
			var self = this;

			// Arrow keys for pagination
			$(document).on('keydown', '.ajax-pagination-numbers', function(e) {
				var $focused = $(document.activeElement);
				var $links = $(this).find('a, span').not('.ajax-pagination-ellipsis');

				if (!$focused.closest('.ajax-pagination-numbers').length) {
					return;
				}

				var currentIndex = $links.index($focused);

				switch(e.key) {
					case 'ArrowRight':
					case 'ArrowDown':
						e.preventDefault();
						if (currentIndex < $links.length - 1) {
							$links.eq(currentIndex + 1).focus();
						}
						break;

					case 'ArrowLeft':
					case 'ArrowUp':
						e.preventDefault();
						if (currentIndex > 0) {
							$links.eq(currentIndex - 1).focus();
						}
						break;

					case 'Home':
						e.preventDefault();
						$links.first().focus();
						break;

					case 'End':
						e.preventDefault();
						$links.last().focus();
						break;

					case 'Enter':
					case ' ':
						e.preventDefault();
						if ($focused.hasClass('ajax-pagination-link')) {
							$focused.trigger('click');
						}
						break;
				}
			});

			// Tab trap for modal-like behavior
			$(document).on('keydown', '.ajax-pagination-container', function(e) {
				if (e.key === 'Tab') {
					self.handleTabNavigation(e, $(this));
				}
			});
		},

		/**
		 * Handle tab navigation.
		 */
		handleTabNavigation: function(e, $container) {
			var $focusable = $container.find('a, button, input, [tabindex]:not([tabindex="-1"])');
			var $first = $focusable.first();
			var $last = $focusable.last();

			if (e.shiftKey && document.activeElement === $first[0]) {
				e.preventDefault();
				$last.focus();
			} else if (!e.shiftKey && document.activeElement === $last[0]) {
				e.preventDefault();
				$first.focus();
			}
		},

		/**
		 * Setup focus management.
		 */
		setupFocusManagement: function() {
			var self = this;

			// Focus on first post after AJAX load
			$(document).on('ajaxPaginationLoaded', function() {
				self.focusFirstPost();
			});

			// Announce page change
			$(document).on('ajaxPaginationLoaded', function(e, data) {
				self.announcePageChange(data.current_page);
			});
		},

		/**
		 * Focus on first post.
		 */
		focusFirstPost: function() {
			var $firstPost = $('.ajax-pagination-card, .ajax-pagination-list, .ajax-pagination-minimal, .ajax-pagination-portfolio').first();

			if ($firstPost.length) {
				$firstPost.attr('tabindex', '-1');
				$firstPost.focus();

				// Remove tabindex after blur
				$firstPost.one('blur', function() {
					$(this).removeAttr('tabindex');
				});
			}
		},

		/**
		 * Announce page change to screen readers.
		 */
		announcePageChange: function(page) {
			var $liveRegion = $('#ajax-pagination-live-region');
			if (!$liveRegion.length) {
				return;
			}

			var message = ajaxPaginationA11y.pageLoaded.replace('%d', page);

			$liveRegion.text(message);

			// Clear after announcement
			setTimeout(function() {
				$liveRegion.text('');
			}, 1000);
		},

		/**
		 * Announce custom message.
		 */
		announce: function(message) {
			var $liveRegion = $('#ajax-pagination-live-region');
			if (!$liveRegion.length) {
				return;
			}

			$liveRegion.text(message);

			setTimeout(function() {
				$liveRegion.text('');
			}, 1000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ajaxPaginationA11y.init();
	});

	// Make available globally
	window.ajaxPaginationA11y = ajaxPaginationA11y;

})(jQuery);

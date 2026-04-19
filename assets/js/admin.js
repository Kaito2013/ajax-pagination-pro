/**
 * AJAX Pagination Pro Admin JavaScript
 */

(function($) {
	'use strict';

	var ajaxPaginationAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.initColorPicker();
			this.bindEvents();
		},

		/**
		 * Initialize color picker.
		 */
		initColorPicker: function() {
			if (typeof $.fn.wpColorPicker !== 'undefined') {
				$('.ajax-pagination-color-picker').wpColorPicker();
			}
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Toggle infinite scroll settings
			$('#ajax_pagination_infinite_scroll').on('change', function() {
				var isChecked = $(this).is(':checked');
				$('#ajax_pagination_scroll_threshold').closest('tr').toggle(isChecked);
			});

			// Toggle cache settings
			$('#ajax_pagination_cache_enabled').on('change', function() {
				var isChecked = $(this).is(':checked');
				$('#ajax_pagination_cache_duration').closest('tr').toggle(isChecked);
			});

			// Trigger on load
			$('#ajax_pagination_infinite_scroll').trigger('change');
			$('#ajax_pagination_cache_enabled').trigger('change');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ajaxPaginationAdmin.init();
	});

})(jQuery);

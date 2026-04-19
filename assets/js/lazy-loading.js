/**
 * AJAX Pagination Pro - Lazy Loading JavaScript
 */

(function($) {
	'use strict';

	var ajaxPaginationLazy = {
		observer: null,

		/**
		 * Initialize.
		 */
		init: function() {
			if (!('IntersectionObserver' in window)) {
				this.loadAllImages();
				return;
			}

			this.createObserver();
			this.observeImages();
		},

		/**
		 * Create Intersection Observer.
		 */
		createObserver: function() {
			var self = this;
			var options = {
				root: null,
				rootMargin: ajaxPaginationLazy.rootMargin || '50px',
				threshold: ajaxPaginationLazy.threshold || 0.1
			};

			this.observer = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (entry.isIntersecting) {
						self.loadImage(entry.target);
						self.observer.unobserve(entry.target);
					}
				});
			}, options);
		},

		/**
		 * Observe all lazy images.
		 */
		observeImages: function() {
			var self = this;
			var images = document.querySelectorAll('.ajax-pagination-lazy:not(.loaded)');

			images.forEach(function(img) {
				self.observer.observe(img);
			});
		},

		/**
		 * Load single image.
		 */
		loadImage: function(img) {
			var self = this;
			var src = img.getAttribute('data-src');
			var srcset = img.getAttribute('data-srcset');

			if (!src) {
				return;
			}

			// Create new image to preload
			var tempImg = new Image();

			tempImg.onload = function() {
				// Apply src
				img.src = src;

				// Apply srcset if exists
				if (srcset) {
					img.srcset = srcset;
				}

				// Add loaded class with fade effect
				img.style.opacity = '0';
				img.style.transition = 'opacity ' + (ajaxPaginationLazy.fadeDuration || 300) + 'ms ease';

				setTimeout(function() {
					img.style.opacity = '1';
					img.classList.add('loaded');
				}, 10);

				// Remove data attributes
				img.removeAttribute('data-src');
				img.removeAttribute('data-srcset');
			};

			tempImg.onerror = function() {
				// Show error placeholder
				img.src = ajaxPaginationLazy.errorPlaceholder || '';
				img.classList.add('error');
			};

			// Start loading
			tempImg.src = src;
		},

		/**
		 * Load all images (fallback for old browsers).
		 */
		loadAllImages: function() {
			var images = document.querySelectorAll('.ajax-pagination-lazy');

			images.forEach(function(img) {
				var src = img.getAttribute('data-src');
				var srcset = img.getAttribute('data-srcset');

				if (src) {
					img.src = src;
					img.removeAttribute('data-src');
				}

				if (srcset) {
					img.srcset = srcset;
					img.removeAttribute('data-srcset');
				}

				img.classList.add('loaded');
			});
		},

		/**
		 * Re-observe new images (after AJAX load).
		 */
		reobserve: function() {
			if (!this.observer) {
				this.loadAllImages();
				return;
			}

			this.observeImages();
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ajaxPaginationLazy.init();
	});

	// Re-observe after AJAX pagination
	$(document).on('ajaxPaginationLoaded', function() {
		ajaxPaginationLazy.reobserve();
	});

	// Make available globally
	window.ajaxPaginationLazy = ajaxPaginationLazy;

})(jQuery);

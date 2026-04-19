# AJAX Pagination Pro

A powerful WordPress plugin for AJAX-based pagination with numbered pagination and load more button. Works with posts and custom post types.

## Features

- ✅ **AJAX Pagination** - No page reloads
- ✅ **Numbered Pagination** - Classic page numbers
- ✅ **Load More Button** - Infinite scroll style
- ✅ **Custom Post Types** - Works with any post type
- ✅ **Taxonomy Filtering** - Filter by category, tags, custom taxonomies
- ✅ **Responsive Design** - Works on all devices
- ✅ **SEO Friendly** - URL updates with History API
- ✅ **Shortcode Support** - Easy to use with any theme
- ✅ **Admin Settings** - Configure globally
- ✅ **Caching Support** - Better performance
- ✅ **Infinite Scroll** - Auto-load on scroll
- ✅ **Customizable** - Columns, images, excerpts, and more

## Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/ajax-pagination-pro`
3. Activate the plugin
4. Go to Settings → AJAX Pagination to configure

## Usage

### Basic Shortcode

```
[ajax_pagination]
```

### With Custom Post Type

```
[ajax_pagination post_type="product"]
```

### Load More Style

```
[ajax_pagination style="load_more"]
```

### Filter by Category

```
[ajax_pagination category="news"]
```

### Custom Columns

```
[ajax_pagination columns="4" per_page="12"]
```

## Shortcode Attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `post_type` | `post` | Post type to display |
| `style` | `numbered` | Pagination style: `numbered` or `load_more` |
| `per_page` | `10` | Number of posts per page |
| `category` | `""` | Filter by category slug |
| `taxonomy` | `""` | Filter by taxonomy |
| `term` | `""` | Filter by taxonomy term slug |
| `orderby` | `date` | Order by: `date`, `title`, `rand` |
| `order` | `DESC` | Order direction: `ASC` or `DESC` |
| `columns` | `3` | Number of columns (1-4) |
| `image_size` | `medium` | Image size: `thumbnail`, `medium`, `large` |
| `show_image` | `true` | Show/hide featured image |
| `show_excerpt` | `true` | Show/hide excerpt |
| `show_date` | `true` | Show/hide date |
| `show_author` | `false` | Show/hide author |
| `excerpt_length` | `55` | Number of words in excerpt |
| `css_class` | `""` | Custom CSS class |
| `loading_text` | `Loading...` | Loading message |
| `no_posts_text` | `No posts found` | Empty state message |
| `button_text` | `Load More` | Load more button text |

## Examples

### Product Grid

```
[ajax_pagination post_type="product" style="load_more" per_page="12" columns="4" show_excerpt="false"]
```

### Blog Posts

```
[ajax_pagination post_type="post" style="numbered" per_page="10" columns="2" show_author="true"]
```

### Portfolio

```
[ajax_pagination post_type="portfolio" category="web" columns="3" image_size="large"]
```

### News

```
[ajax_pagination post_type="post" category="news" style="load_more" per_page="5"]
```

## Admin Settings

Go to **Settings → AJAX Pagination** to configure:

### General Settings

- **Posts Per Page** - Default number of posts
- **Pagination Style** - Default style (numbered or load more)
- **Loading Text** - Custom loading message
- **No Posts Text** - Custom empty state message

### Display Settings

- **Show Loading Spinner** - Show/hide loading overlay
- **Loading Color** - Spinner color
- **Animation Speed** - Transition duration (ms)
- **Update URL on Page Change** - SEO-friendly URLs

### Advanced Settings

- **Infinite Scroll** - Auto-load on scroll
- **Scroll Threshold** - Distance to trigger scroll (px)
- **Enable Caching** - Cache AJAX responses
- **Cache Duration** - Cache time in seconds
- **Debug Mode** - Enable for troubleshooting

## Hooks & Filters

### Actions

```php
// Before AJAX response
do_action( 'ajax_pagination_before_response', $query );

// After AJAX response
do_action( 'ajax_pagination_after_response', $response );
```

### Filters

```php
// Filter query args
$query_args = apply_filters( 'ajax_pagination_query_args', $args );

// Filter post card HTML
$html = apply_filters( 'ajax_pagination_post_html', $html, $post );

// Filter pagination HTML
$html = apply_filters( 'ajax_pagination_pagination_html', $html, $current_page, $total_pages );
```

## CSS Customization

### Override Styles

```css
/* Custom card style */
.ajax-pagination-card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Custom pagination style */
.ajax-pagination-numbers a {
    background: #f0f0f0;
    border: none;
}

.ajax-pagination-numbers span.current {
    background: #ff6b6b;
}

/* Custom load more button */
.ajax-pagination-load-more {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 30px;
}
```

## Browser Support

- Chrome/Edge: Full support
- Firefox: Full support
- Safari: Full support
- IE11: Basic support (no animations)

## Requirements

- WordPress 6.0+
- PHP 8.0+
- jQuery (included with WordPress)

## Support

- GitHub Issues: https://github.com/Kaito2013/ajax-pagination-pro/issues
- Documentation: See this README

## License

GPL-2.0+

## Credits

Developed by Kaito2013

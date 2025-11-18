=== Public Post Preview ===
Contributors: joerga, ocean90
Tags: public, preview, posts, drafts, sharing
Stable tag: 4.0.0
Requires at least: 6.5
Tested up to: 6.8.3
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/donate/?hosted_button_id=DTRF9JZ6CMJLQ

Allow anonymous users to preview a draft of a post before it is published.

== Description ==

Share a secure, expiring preview link with anyone—no WordPress account required. Perfect for collaboration, client approval, and content review workflows.

**Key Features:**

* 🔗 Generate secure preview links with expiring nonces
* ⏱️ Customizable expiration time (default: 48 hours)
* 🎨 Works with Block Editor and Classic Editor
* 🔒 Secure by design—no authentication needed for viewers
* 🎯 Supports all public post types
* 🏗️ Compatible with popular page builders (TagDiv, Elementor, etc.)
* 📊 Preview status indicator in post list
* ⚙️ Easy-to-use settings panel

**Perfect for:**

* Content creators collaborating with external reviewers
* Agencies sharing drafts with clients
* Teams reviewing content before publication
* Anyone needing to preview unpublished content

Have you ever been writing a post with the help of someone who doesn't have access to your WordPress site and needed to give them the ability to preview it before publishing? This plugin takes care of that by generating a secure URL with an expiring nonce that can be shared for public preview.

*Previously this plugin was maintained by [Matt Martz](http://profiles.wordpress.org/sivel/) and was an idea of [Jonathan Dingman](http://profiles.wordpress.org/jdingman/). Photo by [Annelies Geneyn](https://unsplash.com/photos/opened-book-on-grass-during-daytime-bhBONc07WsI).*

== Installation ==

=== Automatic Installation ===

1. Go to **Plugins > Add New** in your WordPress admin area
2. Search for **"Public Post Preview"**
3. Click **"Install Now"** and then **"Activate"**

=== Manual Installation via FTP ===

1. Download the plugin files
2. Upload the `public-post-preview` directory to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** screen in your WordPress admin area

=== Upload via WordPress Admin ===

1. Go to **Plugins > Add New**
2. Click the **"Upload Plugin"** button
3. Choose the downloaded zip file
4. Click **"Install Now"** and then **"Activate"**

== Screenshots ==

1. Block Editor - Preview toggle in document settings sidebar
2. Classic Editor - Preview checkbox in Publish meta box
3. Settings - Expiration time configuration
4. Post List - Preview status indicator

== Usage ==

=== Enabling a Preview ===

**Block Editor (Gutenberg):**
1. Open or create a post/page
2. Look for the **"Public Preview"** section in the document settings sidebar
3. Toggle the **"Enable public preview"** switch
4. Copy the generated preview link
5. Share the link with anyone who needs to review the content

**Classic Editor:**
1. Open or create a post/page
2. Find the **"Public Preview"** checkbox in the **"Publish"** meta box
3. Check the **"Enable public preview"** checkbox
4. Copy the preview link that appears
5. Share the link with reviewers

=== Disabling a Preview ===

Simply uncheck the **"Enable public preview"** checkbox or toggle in the editor. The preview link will immediately become invalid.

=== Customizing Expiration Time ===

**Via Settings:**
1. Go to **Settings > Reading**
2. Scroll to the **"Public Post Preview"** section
3. Adjust the **"Expiration Time"** (in hours)
4. Save changes

**Via Code (Filter):**

You can customize the expiration time programmatically using the `ppp_nonce_life` filter:

```php
add_filter( 'ppp_nonce_life', function() {
    return 5 * DAY_IN_SECONDS; // 5 days
} );
```

**Note:** When using the filter, the settings UI will be hidden automatically.

== Frequently Asked Questions ==

= I can't find the option for preview links. Where is it? =

The preview option is only available for:
* Non-published posts (drafts, pending, scheduled, etc.)
* Posts that have been saved at least once

If you don't see the option, make sure your post is not already published and that you've saved it as a draft first.

= After some time the preview link returns "The link has been expired!". Why? =

The plugin generates URLs with expiring nonces for security. By default, a preview link is valid for **48 hours**. After this time, the link expires and you'll need to generate a new one.

To extend the expiration time:
* Go to **Settings > Reading > Public Post Preview** and increase the expiration time
* Or use the `ppp_nonce_life` filter in your theme's `functions.php`

= Can I extend the nonce expiration time? =

Yes! You have two options:

**Option 1: Settings Panel**
1. Navigate to **Settings > Reading**
2. Find the **"Public Post Preview"** section
3. Adjust the **"Expiration Time"** setting (in hours)
4. Save your changes

**Option 2: Code Filter**

Add this to your theme's `functions.php` or a custom plugin:

```php
add_filter( 'ppp_nonce_life', 'my_custom_nonce_life' );
function my_custom_nonce_life() {
    return 5 * DAY_IN_SECONDS; // 5 days (or any duration you need)
}
```

**Note:** When using the filter, the settings UI will be automatically hidden.

= Does this work with page builders? =

Yes! The plugin includes built-in support for:
* **TagDiv Template Builder** (Newspaper theme)
* **Default WordPress themes**
* Other page builders via the adapter system

If you encounter issues with a specific builder, please report them on GitHub.

= Is it secure? =

Yes! The plugin uses:
* WordPress nonce system for token generation
* Expiring links that automatically become invalid
* No authentication required for viewers (they can't access your admin)
* Preview links are not indexed by search engines
* Proper security headers (no-cache, noindex)

= Can I preview scheduled posts? =

Yes! Preview links work for any non-published post status, including scheduled posts.

= What happens when I publish a post? =

When a post is published, the preview link automatically becomes invalid. If someone tries to access an old preview link for a published post, they'll be redirected to the public permalink.

== Changelog ==

See [CHANGELOG.md](CHANGELOG.md) for the complete version history.

== Upgrade Notice ==

= 4.0.0 =
Major rewrite with improved architecture, better page builder support, and enhanced security. Requires WordPress 6.5+ and PHP 8.0+.

= 3.0.0 =
Requires WordPress 6.5 and PHP 8.0. Adds expiration time settings and improved editor integration.

== Support ==

* **GitHub Issues:** [Report bugs or request features](https://github.com/ocean90/public-post-preview/issues)
* **WordPress.org Forums:** [Get help from the community](https://wordpress.org/support/plugin/public-post-preview)

== Credits ==

* **Current Maintainer:** Joerg Angeli
* **Previous Maintainer (2011-2024):** Dominik Schilling (ocean90)
* **Previous Maintainer (2009-2011):** Matt Martz
* **Original Idea:** Jonathan Dingman

== Development ==

This plugin follows WordPress coding standards and best practices:
* PSR-4 autoloading
* Dependency injection
* Modular architecture
* Comprehensive security measures
* Full input sanitization and validation

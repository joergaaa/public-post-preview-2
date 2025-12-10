# Changelog

## 4.0.2 (2025-01-XX)
* Security: Add validation to ensure `_ppp` token exists before accessing `$_GET` parameters in `resolve_preview_post_id()` method.
* Documentation: Enhance JavaScript source code documentation in readme.txt with clear directory structure and build instructions.
* Documentation: Update GitHub repository URLs to point to the correct repository.

## 4.0.1 (2025-12-09)
* Security: Add proper `sanitize_callback` to `register_setting()` for expiration time option.
* Security: Improve nonce sanitization with `sanitize_text_field()` in addition to `wp_unslash()`.
* Security: Change direct file access check from `class_exists('WP')` to `defined('ABSPATH')`.
* Security: Add ABSPATH checks to all PHP files in `src/` directory.
* Code Quality: Rename namespace from `PPP` to `PPrev` (4+ character prefix per WordPress guidelines).
* Code Quality: Rename class from `DS_Public_Post_Preview` to `PPrev_Public_Post_Preview`.
* Code Quality: Rename JavaScript globals from `DSPublicPostPreview*` to `PPrev*`.
* Code Quality: Rename filters from `ppp_*` to `pprev_*` (`pprev_nonce_life`, `pprev_post_types`, `pprev_published_statuses`, `pprev_preview_link`).
* Improvement: Move debug log location from plugin directory to `wp-content/uploads/`.

## 4.0.0 (2025-11-17)
* Rewrite preview pipeline with modular services (resolver, query factory, adapter bus) and enable TagDiv adapter support by default.
* Replace legacy option flow with service-oriented architecture; old `posts_results`-based swapping is removed from the frontend path.
* Add centralized logging (`preview-debug.log` with request correlation IDs), consistent no-cache handling, and structured diagnostics for adapters.
* Keep admin UI/checkbox intact while the frontend now exclusively uses the new controller.

## 3.0.1 (2024-12-23)
* Fix calculation of expiration time for preview nonce.

## 3.0.0 (2024-12-21)
* Requires WordPress 6.5.
* Requires PHP 8.0.
* Add setting to increase the default expiration time (Settings > Reading > Public Post Preview).
* Show icon for preview link in list tables next to the state.
* Change interface in block editor to match latest editor design.
* Update sidebar description to include the preview link.
* Extend Preview dropdown for public preview in WordPress 6.7+.
* Add Public Preview post list view. Props [@rafaucau](https://github.com/rafaucau).

## 2.10.0 (2022-11-19)
* Compatibility with WordPress 6.1.
* Integrate with [User Switching](https://wordpress.org/plugins/user-switching/): Direct the user to the public preview of a post when they switch off from the post editing screen. Props [@johnbillion](https://github.com/johnbillion).

## 2.9.3 (2021-03-12)
* Compatibility with WordPress 5.7.
* Create a fresh preview URL when enabling public preview.
* Add check for possibly undefined PHP "superglobals". Props [@waviaei](https://github.com/waviaei).

## 2.9.2 (2020-10-03):
* Fix saving of preview status without a previous Ajax request.

## 2.9.1 (2020-07-25):
* Improve HTTP status codes for expired/invalid preview links.

## 2.9.0 (2019-07-20):
* Requires WordPress 5.0
* Requires PHP 5.6
* Adds notice (as Snackbar if supported) when changing preview status in block editor.
* Fixes incorrect status message in classic editor.
* Fixes grammar in expired link notice. Props [@garrett-eclipse](https://github.com/garrett-eclipse).
* Improves internal checks to be more strict. Props [@PatelUtkarsh](https://github.com/PatelUtkarsh).
* Removes deprecated i18n compatibility layer from Gutenberg plugin.

## 2.8.0 (2018-11-27):
* Add support for WordPress 5.0 and the new block editor.

## 2.7.0 (2018-09-14):
* Initial support for Gutenberg.
* Block robots for public post previews. Props [@westonruter](https://github.com/westonruter).

## 2.6.0 (2017-04-27):
* Make `DS_Public_Post_Preview::get_preview_link()` public. Props [@rcstr](https://github.com/rcstr).
* Send no-cache headers for public post previews.

## 2.5.0 (2016-04-05):
* Auto select preview link on focus. Props [@JeroenSormani](https://github.com/JeroenSormani).
* Remove preview status from posts which are trashed or after scheduled posts are published.
* Add support for paged posts.

## 2.4.1 (2015-10-13):
* Update text domain to support language packs. Translations are now managed via http://translate.wordpress.org/projects/wp-plugins/public-post-preview.

## 2.4 (2014-08-21):
* Supports EditFlow and custom statuses
* Disables comments and pings during public post preview
* Adds __Public Preview__ to the list of display states used in the Posts list table
* Prevents flickering of link box on Post edit while loading

## 2.3 (2013-11-18):
* Introduces a filter `ppp_preview_link`. With the filter you can adjust the preview link.
* If a post has gone live, redirect to it's proper permalink.
* Adds the query var `_ppp` to WordPress SEO by Yoast whitelist.

## 2.2 (2013-03-15):
* Based on user feedback: Removed the extra meta box and added preview link to the main Publish meta box.
* Only show the checkbox if the post status/post type is good.
* Requires WordPress 3.5

## 2.1.1 (2012-09-19):
* Sorry for the new update. Through a change in 2.1 a filter was applied to each query. The misplaced "The link has been expired!" message is now gone. Props Aki Björklund and Jonathan Channon.

## 2.1 (2012-09-16):
* Introduces a filter `ppp_nonce_life`. With the filter you can adjust the expiration of a link. By default a link has a lifetime of 48 hours.
* In some situations (still not sure when) the preview link is rewritten as a permalink which results in an error. The plugin now works in this situations too.

## 2.0.1 (2012-07-25):
* Makes the preview link copyable again

## 2.0 (2012-07-23):
* Support for all public post types
* Saves public preview status via an AJAX request
* I18n
* Requires at least WordPress 3.3

## 1.3 (2009-06-30):
* Hook in earlier in the post selection process to fix PHP notices
* Add uninstall functionality to remove options from the options table

## 1.2 (2009-03-30):
* Fix preview URL for scheduled posts on sites with a permalink other than default activated.

## 1.1 (2009-03-11):
* Don't limit public previews to posts in draft or pending status.  Just exclude posts in publish status.

## 1.0 (2009-02-20):
* Initial Public Release

## Unreleased
- WIP: Investigating compatibility issues with TagDiv templates
  - Query successfully replaced with preview post via `set_post_to_publish`
  - Ensured preview post populates WP_Query (`post__in`, `rewind_posts`, flags)
  - Forced autosave drafts to get fallback slugs without persisting changes
  - Triggered `setup_postdata`, `td_global::load_single_post`, `tdb_state_single/content/template` updates
  - Added extensive logging (`preview-debug.log`) showing state updates succeed
  - Remaining issue: TagDiv template still renders blank content despite states being updated

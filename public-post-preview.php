<?php
/**
 * Plugin Name: Public Post Preview
 * Version: 4.0.0
 * Description: Allow anonymous users to preview a post before it is published.
 * Author: Joerg Angeli
 * Author URI: https://dominikschilling.de/
 * Plugin URI: https://github.com/ocean90/public-post-preview
 * Text Domain: public-post-preview
 * Requires at least: 6.5
 * Tested up to: 6.7
 * Requires PHP: 8.0
 * License: GPLv2 or later
 *
 * Previously (2009-2011) maintained by Jonathan Dingman and Matt Martz.
 * Previously (2011-2024) maintained by Dominik Schilling.
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; either version 2
 *  of the License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

/**
 * The class which controls the plugin.
 *
 * Inits at 'plugins_loaded' hook.
 */
class DS_Public_Post_Preview {

	/**
	 * Holds data required to defer preview swaps for TagDiv Template Builder (tdb_templates) requests.
	 *
	 * @since 3.2.0
	 * @var array|null
	 */
	private static $tagdiv_deferred_preview = null;

	/**
	 * Registers actions and filters.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_settings' ) );

		add_action( 'transition_post_status', array( __CLASS__, 'unregister_public_preview_on_status_change' ), 20, 3 );
		add_action( 'post_updated', array( __CLASS__, 'unregister_public_preview_on_edit' ), 20, 2 );

		// Frontend hooks are handled by the new PPP\Plugin pipeline.
		// This class is only used for admin/AJAX functionality.
		if ( is_admin() || wp_doing_ajax() ) {
			add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'post_submitbox_misc_actions' ) );
			add_action( 'save_post', array( __CLASS__, 'register_public_preview' ), 20, 2 );
			add_action( 'wp_ajax_public-post-preview', array( __CLASS__, 'ajax_register_public_preview' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );
			add_filter( 'display_post_states', array( __CLASS__, 'display_preview_state' ), 20, 2 );
			add_action( 'admin_init', array( __CLASS__, 'register_settings_ui' ) );

			foreach( self::get_post_types() as $post_type ) {
				add_filter( "views_edit-$post_type", array( __CLASS__, 'add_list_table_view' ) );
			}
			add_filter( 'pre_get_posts', array( __CLASS__, 'filter_post_list_for_public_preview' ) );
		}
	}

	/**
	 * Registers the settings used by the plugin.
	 *
	 * @since 3.0.0
	 */
	static function register_settings() {
		register_setting(
			'reading',
			'public_post_preview_expiration_time',
			array(
				'show_in_rest' => true,
				'type'         => 'integer',
				'description'  => __( 'Default expiration time in seconds.', 'public-post-preview' ),
				'default'      => 48,
			)
		);
	}

	/**
	 * Registers the settings UI.
	 *
	 * @since 3.0.0
	 */
	static function register_settings_ui() {
		// Only allow users with manage_options capability to see settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( has_filter( 'ppp_nonce_life' ) ) {
			return;
		}

		add_settings_section(
			'public_post_preview',
			__( 'Public Post Preview', 'public-post-preview' ),
			'__return_false',
			'reading'
		);

		add_settings_field(
			'public_post_preview_expiration_time',
			__( 'Expiration Time', 'public-post-preview' ),
			static function() {
				$value = get_option( 'public_post_preview_expiration_time' );
				?>
				<input type="number" id="public-post-preview-expiration-time" name="public_post_preview_expiration_time" value="<?php echo esc_attr( $value ); ?>" class="small-text" step="1" min="1" /> <?php _e( 'hours', 'public-post-preview' ); ?>
				<p class="description"><?php _e( 'Default expiration time of a preview link in hours.', 'public-post-preview' ); ?></p>
				<?php
			},
			'reading',
			'public_post_preview',
			array(
				'label_for' => 'public-post-preview-expiration-time',
			)
		);
	}

	/**
	 * Registers the JavaScript file for post(-new).php.
	 *
	 * @since 2.0.0
	 *
	 * @param string $hook_suffix Unique page identifier.
	 */
	public static function enqueue_script( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( get_current_screen()->is_block_editor() ) {
			$script_assets_path = plugin_dir_path( __FILE__ ) . 'js/dist/gutenberg-integration.asset.php';
			$script_assets      = file_exists( $script_assets_path ) ?
				require $script_assets_path :
				array(
					'dependencies' => array(),
					'version'      => '',
				);
			wp_enqueue_script(
				'public-post-preview-gutenberg',
				plugins_url( 'js/dist/gutenberg-integration.js', __FILE__ ),
				$script_assets['dependencies'],
				$script_assets['version'],
				true
			);

			wp_set_script_translations( 'public-post-preview-gutenberg', 'public-post-preview' );

			$post            = get_post();
			$preview_enabled = self::is_public_preview_enabled( $post );
			wp_localize_script(
				'public-post-preview-gutenberg',
				'DSPublicPostPreviewData',
				array(
					'previewEnabled' => $preview_enabled,
					'previewUrl'     => $preview_enabled ? self::get_preview_link( $post ) : '',
					'nonce'          => wp_create_nonce( 'public-post-preview_' . $post->ID ),
				)
			);
		} else {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script(
				'public-post-preview',
				plugins_url( "js/public-post-preview$suffix.js", __FILE__ ),
				array( 'jquery' ),
				'20221611',
				true
			);

			wp_localize_script(
				'public-post-preview',
				'DSPublicPostPreviewL10n',
				array(
					'enabled'  => __( 'Enabled!', 'public-post-preview' ),
					'disabled' => __( 'Disabled!', 'public-post-preview' ),
				)
			);
		}
	}

	/**
	 * Adds "Public Preview" to the list of display states used in the Posts list table.
	 *
	 * @since 2.4.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 * @return array Filtered array of post display states.
	 */
	public static function display_preview_state( $post_states, $post ) {
		if ( in_array( (int) $post->ID, self::get_preview_post_ids(), true ) ) {
			$post_states['ppp_enabled'] = sprintf(
				' %s&nbsp;<a href="%s" target="_blank" aria-label="%s"><span class="dashicons dashicons-format-links" aria-hidden="true"></span></a>',
				__( 'Public Preview', 'public-post-preview' ),
				esc_url( self::get_preview_link( $post ) ),
				esc_attr(
					sprintf(
						/* translators: %s: Post title */
						__( 'Open public preview of &#8220;%s&#8221;', 'public-post-preview' ), _draft_or_post_title( $post )
					)
				)
			);
		}

		return $post_states;
	}

	/**
	 * Adds a "Public Preview" view to the post list table.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $views An array of available list table views.
	 * @return string[] Filtered array of available list table views.
	 */
	public static function add_list_table_view( $views ) {
		$count = count( self::get_preview_post_ids() );
		if( ! $count ) {
			return $views;
		}

		$screen    = get_current_screen();
		$post_type = $screen->post_type;

		// Get the count of posts for this post type with public preview status.
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post__in'       => self::get_preview_post_ids(),
				'post_status'    => 'draft',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		if ( ! $query->post_count ) {
			return $views;
		}

		// Sanitize GET input.
		$public_preview = isset( $_GET['public_preview'] ) ? sanitize_key( wp_unslash( $_GET['public_preview'] ) ) : '';

		$views['public_preview'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( array( 'post_type' => $post_type, 'public_preview' => 1 ), 'edit.php' ) ),
			'1' === $public_preview ? ' class="current"  aria-current="page"' : '',
			__( 'Public Preview', 'public-post-preview' ),
			number_format_i18n( $query->post_count )
		);

		return $views;
	}

	/**
	 * Filters the post list to show only posts with public preview status.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public static function filter_post_list_for_public_preview( $query ) {
		if ( ! $query->is_admin || ! $query->is_main_query()) {
			return;
		}

		// Sanitize GET input.
		$public_preview = isset( $_GET['public_preview'] ) ? sanitize_key( wp_unslash( $_GET['public_preview'] ) ) : '';

		if ( '1' === $public_preview ) {
			$query->set( 'post__in', self::get_preview_post_ids() );
		}
	}

	/**
	 * Filters the redirect location after a user switches to another account or switches off with the User Switching plugin.
	 *
	 * This is used to direct the user to the public preview of a post when they switch off from the post editing screen.
	 *
	 * @since 2.10.0
	 *
	 * @param string       $redirect_to   The target redirect location, or an empty string if none is specified.
	 * @param string|null  $redirect_type The redirect type, see the `user_switching::REDIRECT_*` constants.
	 * @param WP_User|null $new_user      The user being switched to, or null if there is none.
	 * @param WP_User|null $old_user      The user being switched from, or null if there is none.
	 * @return string The target redirect location.
	 */
	public static function user_switching_redirect_to( $redirect_to, $redirect_type, $new_user, $old_user ) {
		// Sanitize GET input.
		$post_id = isset( $_GET['redirect_to_post'] ) ? absint( $_GET['redirect_to_post'] ) : 0;

		if ( ! $post_id ) {
			return $redirect_to;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $redirect_to;
		}

		if ( ! $old_user || ! user_can( $old_user, 'edit_post', $post->ID ) ) {
			return $redirect_to;
		}

		if ( ! self::is_public_preview_enabled( $post ) ) {
			return $redirect_to;
		}

		return self::get_preview_link( $post );
	}

	/**
	 * Adds the checkbox to the submit meta box.
	 *
	 * @since 2.2.0
	 */
	public static function post_submitbox_misc_actions() {
		$post = get_post();

		// Ignore non-viewable post types.
		if ( ! in_array( $post->post_type, self::get_post_types(), true ) ) {
			return false;
		}

		// Do nothing for auto drafts.
		if ( 'auto-draft' === $post->post_status ) {
			return false;
		}

		// Post is already published.
		if ( in_array( $post->post_status, self::get_published_statuses(), true ) ) {
			return false;
		}

		?>
		<div class="misc-pub-section public-post-preview">
			<?php self::get_checkbox_html( $post ); ?>
		</div>
		<?php

	}

	/**
	 * Returns the viewable post types.
	 *
	 * @since 3.0.0
	 *
	 * @return string[] List with post types.
	 */
	private static function get_post_types() {
		$viewable_post_types = array();
		$post_types          = get_post_types( [], 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( is_post_type_viewable( $post_type ) ) {
				$viewable_post_types[] = $post_type->name;
			}
		}

		return apply_filters( 'ppp_post_types', $viewable_post_types );
	}

	/**
	 * Returns post statuses which represent a published post.
	 *
	 * @since 2.4.0
	 *
	 * @return array List with post statuses.
	 */
	private static function get_published_statuses() {
		$published_statuses = array( 'publish', 'private' );

		return apply_filters( 'ppp_published_statuses', $published_statuses );
	}

	/**
	 * Prints the checkbox with the input field for the preview link.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Post $post The post object.
	 */
	private static function get_checkbox_html( $post ) {
		if ( empty( $post ) ) {
			$post = get_post();
		}

		wp_nonce_field( 'public-post-preview_' . $post->ID, 'public_post_preview_wpnonce' );

		$enabled = self::is_public_preview_enabled( $post );
		?>
		<label><input type="checkbox"<?php checked( $enabled ); ?> name="public_post_preview" id="public-post-preview" value="1" />
		<?php _e( 'Enable public preview', 'public-post-preview' ); ?> <span id="public-post-preview-ajax"></span></label>

		<div id="public-post-preview-link" style="margin-top:6px"<?php echo $enabled ? '' : ' class="hidden"'; ?>>
			<label>
				<input type="text" name="public_post_preview_link" class="regular-text" value="<?php echo esc_attr( $enabled ? self::get_preview_link( $post ) : '' ); ?>" style="width:99%" readonly />
				<span class="description"><?php _e( 'Copy and share this preview URL.', 'public-post-preview' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Checks if a public preview is enabled for a post.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post The post object.
	 * @return bool True if a public preview is enabled, false if not.
	 */
	private static function is_public_preview_enabled( $post ) {
		$preview_post_ids = self::get_preview_post_ids();
		return in_array( $post->ID, $preview_post_ids, true );
	}

	/**
	 * Returns the public preview link.
	 *
	 * The link is the home link with these parameters:
	 *  - preview, always true (query var for core)
	 *  - _ppp, a custom nonce, see DS_Public_Post_Preview::create_nonce()
	 *  - page_id or p or p and post_type to specify the post.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string The generated public preview link.
	 */
	public static function get_preview_link( $post ) {
		if ( 'page' === $post->post_type ) {
			$args = array(
				'page_id' => $post->ID,
			);
		} elseif ( 'post' === $post->post_type ) {
			$args = array(
				'p' => $post->ID,
			);
		} else {
			$args = array(
				'p'         => $post->ID,
				'post_type' => $post->post_type,
			);
		}

		$args['preview'] = true;
		$args['_ppp']    = self::create_nonce( 'public_post_preview_' . $post->ID );

		$link = add_query_arg( $args, home_url( '/' ) );

		return apply_filters( 'ppp_preview_link', $link, $post->ID, $post );
	}

	/**
	 * (Un)Registers a post for a public preview.
	 *
	 * Runs when a post is saved, ignores revisions and autosaves.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $post_id The post id.
	 * @param object $post    The post object.
	 * @return bool Returns true on a success, false on a failure.
	 */
	public static function register_public_preview( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( empty( $_POST['public_post_preview_wpnonce'] ) || ! wp_verify_nonce( $_POST['public_post_preview_wpnonce'], 'public-post-preview_' . $post_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$preview_post_ids = self::get_preview_post_ids();
		$preview_post_id  = (int) $post->ID;

		// Sanitize POST inputs.
		$public_post_preview = isset( $_POST['public_post_preview'] ) ? sanitize_text_field( wp_unslash( $_POST['public_post_preview'] ) ) : '';
		$original_post_status = isset( $_POST['original_post_status'] ) ? sanitize_key( wp_unslash( $_POST['original_post_status'] ) ) : '';

		if ( empty( $public_post_preview ) && in_array( $preview_post_id, $preview_post_ids, true ) ) {
			$preview_post_ids = array_diff( $preview_post_ids, (array) $preview_post_id );
		} elseif (
			! empty( $public_post_preview ) &&
			! empty( $original_post_status ) &&
			! in_array( $original_post_status, self::get_published_statuses(), true ) &&
			in_array( $post->post_status, self::get_published_statuses(), true )
		) {
			$preview_post_ids = array_diff( $preview_post_ids, (array) $preview_post_id );
		} elseif ( ! empty( $public_post_preview ) && ! in_array( $preview_post_id, $preview_post_ids, true ) ) {
			$preview_post_ids = array_merge( $preview_post_ids, (array) $preview_post_id );
		} else {
			return false; // Nothing has changed.
		}

		return self::set_preview_post_ids( $preview_post_ids );
	}

	/**
	 * Unregisters a post for public preview when a (scheduled) post gets published
	 * or trashed.
	 *
	 * @since 2.5.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return bool Returns true on a success, false on a failure.
	 */
	public static function unregister_public_preview_on_status_change( $new_status, $old_status, $post ) {
		$disallowed_status   = self::get_published_statuses();
		$disallowed_status[] = 'trash';

		if ( in_array( $new_status, $disallowed_status, true ) ) {
			return self::unregister_public_preview( $post->ID );
		}

		return false;
	}

	/**
	 * Unregisters a post for public preview when a post gets published or trashed.
	 *
	 * @since 2.5.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return bool Returns true on a success, false on a failure.
	 */
	public static function unregister_public_preview_on_edit( $post_id, $post ) {
		$disallowed_status   = self::get_published_statuses();
		$disallowed_status[] = 'trash';

		if ( in_array( $post->post_status, $disallowed_status, true ) ) {
			return self::unregister_public_preview( $post_id );
		}

		return false;
	}

	/**
	 * Unregisters a post for public preview.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool Returns true on a success, false on a failure.
	 */
	private static function unregister_public_preview( $post_id ) {
		$post_id          = (int) $post_id;
		$preview_post_ids = self::get_preview_post_ids();

		if ( ! in_array( $post_id, $preview_post_ids, true ) ) {
			return false;
		}

		$preview_post_ids = array_diff( $preview_post_ids, (array) $post_id );

		return self::set_preview_post_ids( $preview_post_ids );
	}

	/**
	 * (Un)Registers a post for a public preview for an AJAX request.
	 *
	 * @since 2.0.0
	 */
	public static function ajax_register_public_preview() {
		if ( ! isset( $_POST['post_ID'], $_POST['checked'] ) ) {
			wp_send_json_error( 'incomplete_data' );
		}

		// Sanitize AJAX inputs.
		$preview_post_id = absint( $_POST['post_ID'] );
		$checked         = sanitize_text_field( wp_unslash( $_POST['checked'] ) );

		check_ajax_referer( 'public-post-preview_' . $preview_post_id );

		$post = get_post( $preview_post_id );

		if ( ! current_user_can( 'edit_post', $preview_post_id ) ) {
			wp_send_json_error( 'cannot_edit' );
		}

		if ( in_array( $post->post_status, self::get_published_statuses(), true ) ) {
			wp_send_json_error( 'invalid_post_status' );
		}

		$preview_post_ids = self::get_preview_post_ids();

		if ( 'false' === $checked && in_array( $preview_post_id, $preview_post_ids, true ) ) {
			$preview_post_ids = array_diff( $preview_post_ids, (array) $preview_post_id );
		} elseif ( 'true' === $checked && ! in_array( $preview_post_id, $preview_post_ids, true ) ) {
			$preview_post_ids = array_merge( $preview_post_ids, (array) $preview_post_id );
		} else {
			wp_send_json_error( 'unknown_status' );
		}

		$ret = self::set_preview_post_ids( $preview_post_ids );

		if ( ! $ret ) {
			wp_send_json_error( 'not_saved' );
		}

		$data = null;
		if ( 'true' === $checked ) {
			$data = array( 'preview_url' => self::get_preview_link( $post ) );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Registers the new query var `_ppp`.
	 *
	 * @since 2.1.0
	 *
	 * @param  array $qv Existing list of query variables.
	 * @return array List of query variables.
	 */
	public static function add_query_var( $qv ) {
		$qv[] = '_ppp';

		return $qv;
	}

	/**
	 * Registers the filter to handle a public preview.
	 *
	 * Filter will be set if it's the main query, a preview, a singular page
	 * and the query var `_ppp` exists.
	 *
	 * @since 2.0.0
	 *
	 * @param object $query The WP_Query object.
	 */
	public static function show_public_preview( $query ) {
		if (
			$query->is_main_query() &&
			$query->is_preview() &&
			$query->is_singular() &&
			$query->get( '_ppp' )
		) {
			$removed_tagdiv_filter = false;

			if ( has_filter( 'pre_handle_404', 'tagdiv_pre_handle_404' ) ) {
				$removed_tagdiv_filter = remove_filter( 'pre_handle_404', 'tagdiv_pre_handle_404', 10 );
			}

			self::log_preview_debug(
				'show_public_preview:activated',
				array(
					'request'            => $_SERVER['REQUEST_URI'] ?? '',
					'tagdiv_filter_removed' => $removed_tagdiv_filter,
				)
			);

			$query->is_preview           = true;
			$query->is_singular          = true;
			$query->is_archive           = false;
			$query->is_post_type_archive = false;
			$query->is_404               = false;

			if ( ! headers_sent() ) {
				nocache_headers();
				header( 'X-Robots-Tag: noindex' );
			}
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
			add_filter( 'posts_results', array( __CLASS__, 'set_post_to_publish' ), 10, 2 );
		}
	}

	/**
	 * Checks if a public preview is available and allowed.
	 * Verifies the nonce and if the post id is registered for a public preview.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id The post id.
	 * @return bool True if a public preview is allowed, false on a failure.
	 */
	private static function is_public_preview_available( $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		if ( ! self::verify_nonce( get_query_var( '_ppp' ), 'public_post_preview_' . $post_id ) ) {
			wp_die( __( 'This link has expired!', 'public-post-preview' ), 403 );
		}

		if ( ! in_array( $post_id, self::get_preview_post_ids(), true ) ) {
			wp_die( __( 'No public preview available!', 'public-post-preview' ), 404 );
		}

		return true;
	}

	/**
	 * Filters the HTML output of individual page number links to use the
	 * preview link.
	 *
	 * @since 2.5.0
	 *
	 * @param string $link        The page number HTML output.
	 * @param int    $page_number Page number for paginated posts' page links.
	 * @return string The filtered HTML output.
	 */
	public static function filter_wp_link_pages_link( $link, $page_number ) {
		$post = get_post();
		if ( ! $post ) {
			return $link;
		}

		$preview_link = self::get_preview_link( $post );
		$preview_link = add_query_arg( 'page', $page_number, $preview_link );

		return preg_replace( '~href=(["|\'])(.+?)\1~', 'href=$1' . $preview_link . '$1', $link );
	}

	/**
	 * Sets the post status of the first post to publish, so we don't have to do anything
	 * *too* hacky to get it to load the preview.
	 *
	 * @since 2.0.0
	 *
	 * @param  array $posts The post to preview.
	 * @return array The post that is being previewed.
	 */
	public static function set_post_to_publish( $posts, $query ) {
		// Remove the filter again, otherwise it will be applied to other queries too.
		remove_filter( 'posts_results', array( __CLASS__, 'set_post_to_publish' ), 10 );

		$preview_post_id = self::resolve_preview_post_id( $query );
		$original_posts  = is_array( $posts ) ? $posts : array();
		$tagdiv_context  = self::is_tagdiv_template_request( $query );

		self::log_preview_debug(
			'set_post_to_publish:entry',
			array(
				'post_count' => is_array( $posts ) ? count( $posts ) : 0,
				'post_types' => is_array( $posts ) ? array_values(
					array_unique(
						array_map(
							static function ( $post ) {
								return $post instanceof WP_Post ? $post->post_type : null;
							},
							$posts
						)
					)
				) : array(),
				'query_vars' => $query instanceof WP_Query ? $query->query_vars : null,
				'_ppp'       => get_query_var( '_ppp' ),
				'preview_id' => $preview_post_id,
				'request'    => $_SERVER['REQUEST_URI'] ?? '',
			)
		);

		$has_preview_post = false;

		if ( is_array( $posts ) && $preview_post_id ) {
			foreach ( $posts as $maybe_post ) {
				if ( $maybe_post instanceof WP_Post && (int) $maybe_post->ID === $preview_post_id ) {
					$has_preview_post = true;
					break;
				}
			}
		}

		if ( get_query_var( '_ppp' ) && $preview_post_id && ! $has_preview_post ) {
			$posts = self::load_preview_post( $preview_post_id, $posts );
		} elseif ( get_query_var( '_ppp' ) && ! $preview_post_id ) {
			self::log_preview_debug(
				'set_post_to_publish:no_post_id',
				array(
					'query_vars' => $query instanceof WP_Query ? $query->query_vars : null,
					'get'        => array(
						'p'       => isset( $_GET['p'] ) ? (int) $_GET['p'] : null,
						'page_id' => isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : null,
						'post_id' => isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : null,
					),
				)
			);
		}

		if ( empty( $posts ) ) {
			self::log_preview_debug( 'set_post_to_publish:still_empty' );
			return $posts;
		}

		$preview_post = $posts[0] instanceof WP_Post ? $posts[0] : null;

		if ( ! $preview_post ) {
			return $posts;
		}

		self::log_preview_debug(
			'set_post_to_publish:resolved',
			array(
				'post_count' => count( $posts ),
				'post_type'  => $preview_post->post_type,
				'post_id'    => (int) $preview_post->ID,
				'status'     => $preview_post->post_status,
			)
		);

		if ( $tagdiv_context && ! empty( $original_posts ) ) {
			self::prime_tagdiv_preview_request( $preview_post );

			self::$tagdiv_deferred_preview = array(
				'preview_post' => $preview_post,
				'query'        => $query,
				'request'      => $_SERVER['REQUEST_URI'] ?? '',
			);

			add_action( 'template_redirect', array( __CLASS__, 'complete_deferred_preview_swap' ), 20 );

			self::log_preview_debug(
				'set_post_to_publish:tagdiv_deferred',
				array(
					'post_id'   => (int) $preview_post->ID,
					'post_type' => $preview_post->post_type,
				)
			);

			return $original_posts;
		}

		return self::apply_preview_post_to_query( $preview_post, $query );
	}

	/**
	 * Completes deferred preview swaps for TagDiv templates after their loader captured template state.
	 *
	 * @since 3.2.0
	 */
	public static function complete_deferred_preview_swap() {
		if ( empty( self::$tagdiv_deferred_preview ) ) {
			return;
		}

		$data = self::$tagdiv_deferred_preview;
		self::$tagdiv_deferred_preview = null;

		remove_action( 'template_redirect', array( __CLASS__, 'complete_deferred_preview_swap' ), 20 );

		$preview_post = isset( $data['preview_post'] ) && $data['preview_post'] instanceof WP_Post ? $data['preview_post'] : null;
		$query        = isset( $data['query'] ) && $data['query'] instanceof WP_Query ? $data['query'] : ( $GLOBALS['wp_query'] ?? null );

		if ( ! $preview_post || ! $query instanceof WP_Query ) {
			return;
		}

		self::log_preview_debug(
			'complete_deferred_preview_swap:start',
			array(
				'post_id' => (int) $preview_post->ID,
				'request' => $data['request'] ?? '',
			)
		);

		self::apply_preview_post_to_query( $preview_post, $query, false );
	}

	/**
	 * Ensures TagDiv templates know which post should be rendered in preview mode.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_Post $preview_post Post being previewed.
	 */
	private static function prime_tagdiv_preview_request( WP_Post $preview_post ) {
		$preview_id = (int) $preview_post->ID;

		if ( ! isset( $_GET['td_preview_post_id'] ) ) {
			$_GET['td_preview_post_id'] = $preview_id;
		}

		if ( ! isset( $_REQUEST['td_preview_post_id'] ) ) {
			$_REQUEST['td_preview_post_id'] = $preview_id;
		}

		self::log_preview_debug(
			'tagdiv_preview_context:primed',
			array(
				'post_id'   => $preview_id,
				'post_type' => $preview_post->post_type,
			)
		);
	}

	/**
	 * Applies the preview post data to the current query and updates TagDiv states.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_Post  $post                  Preview post object.
	 * @param WP_Query $query                 Current WP_Query instance.
	 * @param bool     $update_template_state Whether to update TagDiv template state.
	 * @param bool     $mutate_query          Whether to mutate the provided query (false keeps the original TagDiv template query intact).
	 * @return array Array containing the preview post.
	 */
	private static function apply_preview_post_to_query( WP_Post $post, $query, $update_template_state = true, $mutate_query = true ) {
		$posts         = array( $post );
		$post_type     = $post->post_type;
		$post_id       = (int) $post->ID;
		$is_page       = ( 'page' === $post_type );
		$is_attachment = ( 'attachment' === $post_type );

		$active_query = self::prepare_preview_query( $post, $query, $mutate_query );

		if ( $query instanceof WP_Query && ! $mutate_query ) {
			$is_page       = ( 'page' === $post_type );
			$is_attachment = ( 'attachment' === $post_type );

			$query->is_single            = ! $is_page && ! $is_attachment;
			$query->is_page              = $is_page;
			$query->is_attachment        = $is_attachment;
			$query->is_singular          = true;
			$query->is_preview           = true;
			$query->is_archive           = false;
			$query->is_post_type_archive = false;
			$query->is_404               = false;
		}

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		self::log_preview_debug(
			'set_post_to_publish:post_setup_complete',
			array(
				'post_id'    => $post_id,
				'post_title' => get_the_title( $post_id ),
				'post_type'  => $post_type,
			)
		);

		if ( class_exists( 'tdb_state_single', false ) && isset( $GLOBALS['tdb_state_single'] ) && $GLOBALS['tdb_state_single'] instanceof tdb_state_single ) {
			$GLOBALS['tdb_state_single']->set_wp_query( $active_query );

			self::log_preview_debug(
				'set_post_to_publish:tdb_state_single_updated',
				array(
					'post_id' => $post_id,
				)
			);
		}

		if ( class_exists( 'tdb_state_content', false ) && isset( $GLOBALS['tdb_state_content'] ) && $GLOBALS['tdb_state_content'] instanceof tdb_state_content ) {
			$GLOBALS['tdb_state_content']->set_wp_query( $active_query );

			self::log_preview_debug(
				'set_post_to_publish:tdb_state_content_updated',
				array(
					'post_id' => $post_id,
				)
			);
		}

		if ( $update_template_state && class_exists( 'tdb_state_template', false ) && method_exists( 'tdb_state_template', 'set_wp_query' ) ) {
			tdb_state_template::set_wp_query( $active_query );

			self::log_preview_debug(
				'set_post_to_publish:tdb_state_template_updated',
				array(
					'post_id' => $post_id,
				)
			);
		}

		if ( class_exists( 'td_global' ) && method_exists( 'td_global', 'load_single_post' ) ) {
			td_global::load_single_post( $post );

			self::log_preview_debug(
				'set_post_to_publish:tagdiv_global_loaded',
				array(
					'post_id'   => $post_id,
					'post_type' => $post_type,
				)
			);
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( '_ppp_after_setup_preview_post', $post, $active_query );
		}

		// If the post has gone live, redirect to it's proper permalink.
		self::maybe_redirect_to_published_post( $post_id );

		if ( self::is_public_preview_available( $post_id ) ) {
			// Set post status to publish so that it's visible.
			$post->post_status = 'publish';

			// Disable comments and pings for this post.
			add_filter( 'comments_open', '__return_false' );
			add_filter( 'pings_open', '__return_false' );
			add_filter( 'wp_link_pages_link', array( __CLASS__, 'filter_wp_link_pages_link' ), 10, 2 );
		}

		return $posts;
	}

	/**
	 * Creates or mutates a WP_Query instance so it represents the preview post.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_Post  $post        Previewed post.
	 * @param WP_Query $query       Query to mutate or clone.
	 * @param bool     $mutate_base Whether to mutate the provided query (true) or work on a clone (false).
	 * @return WP_Query Prepared query instance.
	 */
	private static function prepare_preview_query( WP_Post $post, $query, $mutate_base ) {
		$post_id       = (int) $post->ID;
		$post_type     = $post->post_type;
		$is_page       = ( 'page' === $post_type );
		$is_attachment = ( 'attachment' === $post_type );

		if ( $query instanceof WP_Query ) {
			$active_query = $mutate_base ? $query : clone $query;
		} else {
			$active_query = new WP_Query();
		}

		$active_query->posts             = array( $post );
		$active_query->post              = $post;
		$active_query->post_count        = 1;
		$active_query->found_posts       = 1;
		$active_query->max_num_pages     = 1;
		$active_query->queried_object    = $post;
		$active_query->queried_object_id = $post_id;

		self::log_preview_debug(
			'set_post_to_publish:query_before_set',
			array(
				'post_id'    => $post_id,
				'post_type'  => $post_type,
				'post_name'  => $post->post_name,
				'post_status'=> $post->post_status,
			)
		);

		$active_query->set( 'post_type', $post_type );
		$active_query->set( 'p', $post_id );
		$active_query->set( 'page_id', $is_page ? $post_id : 0 );
		$active_query->set( 'name', $post->post_name ?: $post_id );
		$active_query->set( 'post__in', array( $post_id ) );
		$active_query->set( 'fields', '' );
		$active_query->rewind_posts();

		self::log_preview_debug(
			'set_post_to_publish:query_after_rewind',
			array(
				'post_count'   => $active_query->post_count,
				'found_posts'  => $active_query->found_posts,
				'current_post' => $active_query->current_post,
			)
		);

		$active_query->is_single            = ! $is_page && ! $is_attachment;
		$active_query->is_page              = $is_page;
		$active_query->is_attachment        = $is_attachment;
		$active_query->is_singular          = true;
		$active_query->is_preview           = true;
		$active_query->is_archive           = false;
		$active_query->is_post_type_archive = false;
		$active_query->is_404               = false;

		if ( method_exists( $active_query, 'set_queried_object' ) ) {
			$active_query->set_queried_object( $post );
		}

		self::log_preview_debug(
			'set_post_to_publish:query_flags',
			array(
				'is_preview'  => $active_query->is_preview,
				'is_singular' => $active_query->is_singular,
				'is_single'   => $active_query->is_single,
				'is_404'      => $active_query->is_404,
				'current_post'=> $active_query->current_post,
			)
		);

		self::log_preview_debug(
			'set_post_to_publish:query_adjusted',
			array(
				'post_id'   => $post_id,
				'post_type' => $post_type,
				'name'      => $post->post_name,
			)
		);

		return $active_query;
	}

	/**
	 * Determines whether the current query is a TagDiv template request that should defer preview swapping.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_Query $query Current query instance.
	 * @return bool
	 */
	private static function is_tagdiv_template_request( $query ) {
		if ( ! class_exists( 'tdb_state_template', false ) || ! $query instanceof WP_Query ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) && in_array( 'tdb_templates', $post_type, true ) ) {
			return true;
		}

		if ( 'tdb_templates' === $post_type ) {
			return true;
		}

		$queried_object = $query->get_queried_object();
		if ( $queried_object instanceof WP_Post && 'tdb_templates' === $queried_object->post_type ) {
			return true;
		}

		if ( ! empty( $query->posts ) && is_array( $query->posts ) ) {
			foreach ( $query->posts as $maybe_post ) {
				if ( $maybe_post instanceof WP_Post && 'tdb_templates' === $maybe_post->post_type ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determines the intended preview post ID.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Query $query Current query instance.
	 * @return int Preview post ID or 0 if not found.
	 */
	private static function resolve_preview_post_id( $query ) {
		$candidates = array();

		foreach ( array( 'p', 'page_id', 'post_id' ) as $get_key ) {
			if ( isset( $_GET[ $get_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$candidates[] = absint( $_GET[ $get_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( $query instanceof WP_Query ) {
			foreach ( array( 'p', 'page_id' ) as $var_key ) {
				if ( ! empty( $query->query_vars[ $var_key ] ) ) {
					$candidates[] = absint( $query->query_vars[ $var_key ] );
				}
			}

			if ( ! empty( $query->query_vars['post__in'] ) && is_array( $query->query_vars['post__in'] ) ) {
				foreach ( $query->query_vars['post__in'] as $post_in_id ) {
					$candidates[] = absint( $post_in_id );
				}
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( $candidate > 0 ) {
				return $candidate;
			}
		}

		return 0;
	}

	/**
	 * Prevents WordPress from handling a valid preview request as 404.
	 *
	 * @since 3.1.0
	 *
	 * @param bool     $preempt  Whether to short-circuit 404 handling.
	 * @param WP_Query $wp_query Query instance.
	 * @return bool Modified preempt state.
	 */
	public static function prevent_preview_404( $preempt, $wp_query ) {
		if ( true === $preempt || ! $wp_query instanceof WP_Query ) {
			return $preempt;
		}

		if ( ! $wp_query->is_main_query() ) {
			return $preempt;
		}

		$nonce = $wp_query->get( '_ppp' );
		if ( ! $nonce ) {
			return $preempt;
		}

		$post = $wp_query->get_queried_object();
		if ( $post instanceof WP_Post && self::is_public_preview_enabled( $post ) ) {
			self::log_preview_debug(
				'prevent_preview_404:allowed',
				array(
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
				)
			);

			$wp_query->is_preview = true;
			$wp_query->is_singular = true;
			$wp_query->is_404 = false;

			return true;
		}

		$preview_post_id = self::resolve_preview_post_id( $wp_query );
		if ( $preview_post_id ) {
			$preview_post = get_post( $preview_post_id );
			if ( $preview_post instanceof WP_Post && self::is_public_preview_enabled( $preview_post ) ) {
				self::log_preview_debug(
					'prevent_preview_404:allowed_fallback',
					array(
						'post_id'   => $preview_post->ID,
						'post_type' => $preview_post->post_type,
					)
				);

				if ( method_exists( $wp_query, 'set_queried_object' ) ) {
					$wp_query->set_queried_object( $preview_post );
				} else {
					$wp_query->queried_object = $preview_post;
				}

				$wp_query->queried_object_id = $preview_post->ID;
				$wp_query->is_preview = true;
				$wp_query->is_singular = true;
				$wp_query->is_404 = false;

				return true;
			}
		}

		self::log_preview_debug(
			'prevent_preview_404:skipped',
			array(
				'reason'     => 'no_preview_post',
				'type'       => is_object( $post ) ? get_class( $post ) : gettype( $post ),
				'preview_id' => $preview_post_id,
			)
		);

		return $preempt;
	}

	/**
	 * Ensures post types remain viewable for public previews.
	 *
	 * @since 3.1.0
	 *
	 * @param bool          $is_viewable Current viewable state.
	 * @param WP_Post_Type  $post_type   Post type object.
	 * @return bool Filtered result.
	 */
	public static function maybe_allow_post_type_viewable( $is_viewable, $post_type ) {
		if ( $is_viewable || ! $post_type instanceof WP_Post_Type ) {
			return $is_viewable;
		}

		if ( empty( $GLOBALS['wp_query'] ) || ! $GLOBALS['wp_query'] instanceof WP_Query ) {
			return $is_viewable;
		}

		$wp_query = $GLOBALS['wp_query'];

		if ( ! $wp_query->is_main_query() ) {
			return $is_viewable;
		}

		$nonce = $wp_query->get( '_ppp' );
		if ( ! $nonce ) {
			return $is_viewable;
		}

		$wp_query->is_preview  = true;
		$wp_query->is_singular = true;

		$post = $wp_query->get_queried_object();

		if ( $post instanceof WP_Post && $post_type->name === $post->post_type && self::is_public_preview_enabled( $post ) ) {
			self::log_preview_debug(
				'maybe_allow_post_type_viewable:enabled',
				array(
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
				)
			);

			return true;
		}

		return $is_viewable;
	}

	/**
	 * Loads the preview post to ensure the correct content is returned.
	 *
	 * @since 3.1.0
	 *
	 * @param int   $preview_post_id Post ID to load.
	 * @param array $current_posts   Current posts array.
	 * @return array Modified posts array with the preview post.
	 */
	private static function load_preview_post( $preview_post_id, $current_posts ) {
		$fallback_post = get_post( $preview_post_id );

		if ( $fallback_post instanceof WP_Post ) {
			$generated_slug = sanitize_title( $fallback_post->post_title ? $fallback_post->post_title : $fallback_post->ID );

			if ( '' === $generated_slug ) {
				$generated_slug = (string) $fallback_post->ID;
			}

			$fallback_post->post_name = $generated_slug;

			self::log_preview_debug(
				'set_post_to_publish:fallback_loaded',
				array(
					'post_id'   => $fallback_post->ID,
					'post_type' => $fallback_post->post_type,
					'status'    => $fallback_post->post_status,
				)
			);

			return array( $fallback_post );
		}

		self::log_preview_debug(
			'set_post_to_publish:fallback_missing',
			array(
				'post_id' => $preview_post_id,
			)
		);

		return $current_posts;
	}

	/**
	 * Helper to log preview debugging into the plugin directory.
	 *
	 * @since 3.1.0
	 *
	 * @param string $message Message identifier.
	 * @param array  $context Optional context data.
	 */
	private static function log_preview_debug( $message, array $context = array() ) {
		$log_file = plugin_dir_path( __FILE__ ) . 'preview-debug.log';
		$log_dir  = dirname( $log_file );

		if ( ! is_dir( $log_dir ) || ! is_writable( $log_dir ) ) {
			return;
		}

		if ( file_exists( $log_file ) && ! is_writable( $log_file ) ) {
			return;
		}

		$entry = sprintf(
			"[PPP][%s] %s %s\n",
			wp_date( 'c' ),
			$message,
			empty( $context ) ? '' : wp_json_encode( $context )
		);

		error_log( $entry, 3, $log_file );
	}

	/**
	 * Redirects to post's proper permalink, if it has gone live.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id The post id.
	 * @return false False of post status is not a published status.
	 */
	private static function maybe_redirect_to_published_post( $post_id ) {
		if ( ! in_array( get_post_status( $post_id ), self::get_published_statuses(), true ) ) {
			return false;
		}

		wp_safe_redirect( get_permalink( $post_id ), 301 );
		exit;
	}

	/**
	 * Get the time-dependent variable for nonce creation.
	 *
	 * @see wp_nonce_tick()
	 *
	 * @since 2.1.0
	 *
	 * @return int The time-dependent variable.
	 */
	private static function nonce_tick() {
		$expiration = get_option( 'public_post_preview_expiration_time' ) ?: 48;
		$nonce_life = apply_filters( 'ppp_nonce_life', $expiration * HOUR_IN_SECONDS );

		return ceil( time() / ( $nonce_life / 2 ) );
	}

	/**
	 * Creates a random, one time use token. Without an UID.
	 *
	 * @see wp_create_nonce()
	 *
	 * @since 1.0.0
	 *
	 * @param  string|int $action Scalar value to add context to the nonce.
	 * @return string The one use form token.
	 */
	private static function create_nonce( $action = -1 ) {
		$i = self::nonce_tick();

		return substr( wp_hash( $i . $action, 'nonce' ), -12, 10 );
	}

	/**
	 * Verifies that correct nonce was used with time limit. Without an UID.
	 *
	 * @see wp_verify_nonce()
	 *
	 * @since 1.0.0
	 *
	 * @param string     $nonce  Nonce that was used in the form to verify.
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return bool               Whether the nonce check passed or failed.
	 */
	private static function verify_nonce( $nonce, $action = -1 ) {
		$i = self::nonce_tick();

		// Nonce generated 0-12 hours ago.
		if ( substr( wp_hash( $i . $action, 'nonce' ), -12, 10 ) === $nonce ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago.
		if ( substr( wp_hash( ( $i - 1 ) . $action, 'nonce' ), -12, 10 ) === $nonce ) {
			return 2;
		}

		// Invalid nonce.
		return false;
	}

	/**
	 * Returns the post IDs which are registered for a public preview.
	 *
	 * @since 2.0.0
	 *
	 * @return array The post IDs. (Empty array if no IDs are registered.)
	 */
	private static function get_preview_post_ids() {
		$post_ids = get_option( 'public_post_preview', array() );
		$post_ids = array_map( 'intval', $post_ids );

		return $post_ids;
	}

	/**
	 * Saves the post IDs which are registered for a public preview.
	 *
	 * @since 2.0.0
	 *
	 * @param array $post_ids List of post IDs that have a preview.
	 * @return bool Returns true on a success, false on a failure.
	 */
	private static function set_preview_post_ids( $post_ids = array() ) {
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );
		$post_ids = array_unique( $post_ids );

		return update_option( 'public_post_preview', $post_ids );
	}

	/**
	 * Deletes the option 'public_post_preview' if the plugin will be uninstalled.
	 *
	 * @since 2.0.0
	 */
	public static function uninstall() {
		delete_option( 'public_post_preview' );
	}

	/**
	 * Performs actions on plugin activation.
	 *
	 * @since 2.9.4
	 */
	public static function activate() {
		register_uninstall_hook( __FILE__, array( 'DS_Public_Post_Preview', 'uninstall' ) );
	}
}

// Load the next-generation preview pipeline (active by default since 4.0.0).
require_once __DIR__ . '/src/Autoloader.php';
$ppp_autoloader = new \PPP\Autoloader();
$ppp_autoloader->register( 'PPP', __DIR__ . '/src' );

add_action(
	'plugins_loaded',
	static function() {
		// Boot the new preview pipeline for frontend requests.
		\PPP\Plugin::boot();

		// Initialize legacy class only for admin/AJAX functionality.
		if ( is_admin() || wp_doing_ajax() ) {
			DS_Public_Post_Preview::init();
		}
	},
	1
);

register_activation_hook( __FILE__, array( 'DS_Public_Post_Preview', 'activate' ) );

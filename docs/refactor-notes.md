## Public Post Preview – Discovery Notes

> **Note (v4.0.1):** Class renamed from `DS_Public_Post_Preview` to `PPrev_Public_Post_Preview`. Namespace changed from `PPP` to `PPrev`. Filters renamed from `ppp_*` to `pprev_*`.

### 1. Current Hook & Responsibility Map

| Context | Hook / Filter | PPrev Callback | Responsibility | Risks / Observations |
| --- | --- | --- | --- | --- |
| bootstrap | `plugins_loaded` | `PPrev_Public_Post_Preview::init` | Registers every other hook at runtime. | Hard to test because everything is wire‑time rather than constructor injection. |
| shared | `init` | `register_settings` | Registers the expiration option used by `_ppp` nonces. | Directly stores raw integers without validation pipeline. |
| shared | `transition_post_status`, `post_updated` | `unregister_public_preview_*` | Removes previews when posts change status or content. | Called twice for certain editors; no batching/debouncing. |
| front end | `pre_get_posts` | `show_public_preview` | Detects `_ppp` in main query, flips query flags to “preview”, adds `posts_results` filter. | Runs before builders (TagDiv) replace `WP_Query`, so later rewrites clobber theirs. |
| front end | `query_vars` | `add_query_var` | Allows `_ppp` through WP parsing. | None. |
| front end | `user_switching_redirect_to` | `user_switching_redirect_to` | Sends User Switching plugin to public preview instead of dashboard. | Relies on `$_GET['redirect_to_post']`. |
| front end | `pre_handle_404` | `prevent_preview_404` | Prevents valid `_ppp` requests from becoming 404s before we swap posts. | Quantum leaps when other plugins also hook at high priority. |
| front end | `is_post_type_viewable` | `maybe_allow_post_type_viewable` | Forces post types marked non-viewable to be viewable for preview. | Mutates global concept of “viewable” for any query containing `_ppp`. |
| front end | `posts_results` | `set_post_to_publish` | After main query runs, replace whatever posts came back with the preview target. | Heavy global mutation: rewires `$wp_query`, sets globals, touches TagDiv state single/content/template, calls `setup_postdata`. |
| front end | (internal) | `load_preview_post` | Fetches fallback post when WP_Query did not include it. | Generates sanitized slug in-memory but never persists. |
| admin | `post_submitbox_misc_actions` | `post_submitbox_misc_actions` | Renders checkbox + preview URL in publish box. | DOM duplicated for block/classic; expensive inline HTML. |
| admin | `save_post`, `wp_ajax_public-post-preview` | `register_public_preview`, `ajax_register_public_preview` | Stores/removes preview IDs and responds with preview URL for JS toggles. | Both paths duplicate logic; AJAX path does not share validation with `save_post`. |
| admin | `display_post_states` | `display_preview_state` | Adds “Public Preview” label + icon in list table. | Always hits `get_preview_post_ids()` option (array of all IDs) which can grow unbounded. |
| admin | `views_edit-$post_type`, `pre_get_posts` | `add_list_table_view`, `filter_post_list_for_public_preview` | Adds “Public Preview” filter tab in list screens. | Queries `post__in` with entire preview ID array (no paging). |
| admin | `admin_enqueue_scripts` | `enqueue_script` | Loads block/classic editor JS to toggle preview state. | Gutenberg integration uses `wp_localize_script` with entire preview URL payload. |
| shared | `register_settings_ui` | `register_settings_ui` | Adds “Expiration Time” field under Settings > Reading (if no `pprev_nonce_life` filter). | No capability checks beyond Settings page itself. |
| shared | `comments_open`, `pings_open`, `wp_link_pages_link` | inline closures | Forces comments/pings closed and rewrites pagination links during preview. | Hard-coded filters; no easy override per site. |

### 2. High-Risk Code Paths / Failure Modes

1. **TagDiv / tdb_templates blank content**
   - Builder first loads template via `is_singular( 'tdb_templates' )`.
   - PPP’s `pre_get_posts` → `posts_results` rewrites the main query *before* TagDiv finishes capturing template state (e.g. `tdb_state_loader::on_template_redirect_load_state`), so TagDiv never sees its template query and renders empty modules (“Keine Beiträge vorhanden”).
   - Recent quick fixes (defer swap, mutate flags) illustrate how fragile the current “rewrite everything globally” approach is.

2. **Unbounded `public_post_preview` option**
   - Every enabled preview ID is stored in a single autoloaded option. Large editorial teams accumulate thousands of IDs, slowing `display_post_states`, `add_list_table_view`, and any request that calls `get_preview_post_ids()`.

3. **`posts_results` side effects**
   - The method mutates `$query`, `$GLOBALS['post']`, `tdb_state_single`, `tdb_state_content`, `tdb_state_template`, and triggers `td_global::load_single_post`.
   - If another plugin also hooks `posts_results` (e.g. caching layers, multilingual filters), ordering becomes critical and often breaks previews.

4. **Nonce / expiration drift**
   - Expiration option is stored in hours, but `verify_nonce` still relies on WP’s half-life tick logic, so site owners are confused when “48 hours” lasts anywhere between 36 and 72 depending on cron timing.

5. **404 / redirect loops**
   - `prevent_preview_404` runs at priority 999, but some themes/plugins return custom `WP_Error` earlier, triggering `template_redirect` before we ever swap posts. Result: preview link redirects to `?preview=1` standard preview which fails for non-authenticated users.

6. **Admin UX fragmentation**
   - Block editor toggle (JS) talks to admin-ajax; classic editor posts a full form submit. Both routes reshuffle the preview option array but do not share validation or logging, so state drift occurs under high concurrency.

### 3. External Integrations / Coupling Points

1. **Core WordPress preview system**
   - Relies on `_ppp` query var and `public_post_preview_<post_id>` nonce created with `wp_create_nonce`.
   - Incorporates `wp_robots_no_robots`, `nocache_headers`, `X-Robots-Tag` to keep previews hidden.

2. **Editor environment**
   - Classic: simple checkbox + localized strings.
   - Block (Gutenberg): React sidebar component (see `js/dist/gutenberg-integration.js`) uses `wp.data` to toggle state asynchronously.

3. **User Switching plugin**
   - Filter ensures “Switch off” sends the user to public preview when leaving editor.

4. **TagDiv Template Builder (td-cloud-library)**
   - Needs `td_preview_post_id` request param *and* access to the original template query before PPrev rewrites things.
   - Uses `tdb_state_single/content/template` globals and expects `WP_Query` to represent the previewed post only after TagDiv stores its own template query.

5. **Other builders / caching layers**
   - Elementor, Divi, Beaver Builder previews also rely on bespoke query lifecycles; PPrev presently assumes the default WP loop everywhere.

### 4. Observations to Carry into the Redesign

- **Monolithic class**: `PPrev_Public_Post_Preview` mixes options API, admin UI, AJAX, request routing, and third-party integrations. Any change risks regressions.
- **Implicit globals**: Instead of returning data, most methods mutate `$wp_query`/`$post`/`$GLOBALS`. Refactor should prefer immutable “preview context” objects and explicit adapters.
- **Logging**: Current log is a flat file in the plugin directory; multiple concurrent requests clobber each other and there’s no retention/rotation.
- **Testing gap**: Zero automated tests. Even a basic integration suite mocking `pre_get_posts` + `posts_results` would catch builder regressions earlier.

These notes satisfy the “discovery” phase and will guide the subsequent redesign steps (preview pipeline, TagDiv adapter, logging/testing, rollout).

### 5. Preview Pipeline Proposal (Modular Architecture)

1. **Request / context objects**
   - `PreviewRequest`: immutable view of the incoming HTTP request (`WP`, `WP_Query`, `_ppp` token, builder hints, UA/IP for logging).
   - `PreviewContext`: resolved `WP_Post`, expiration TTL, preview token metadata, and adapter directives (e.g. “needs TagDiv support”).

2. **Core services**
   - `PreviewResolver`: verifies nonce + expiration, ensures post is registered, returns `PreviewContext`.
   - `PreviewQueryFactory`: takes `PreviewContext` and produces a synthetic `WP_Query` that mirrors a single-post loop without mutating globals yet.
   - `PreviewAdapterBus`: dispatches adapters (CoreDefault, TagDiv, Element/Divi) so each can integrate with its own state model.
   - `PreviewResponseEmitter`: applies cache-control headers, `wp_robots_no_robots`, optional redirects, and handles final query swap only if downstream adapters signal readiness.

3. **Lifecycle & hooks**
   - Only one public entry point for front-end requests: a `parse_request` listener that calls the controller early but defers side effects until `template_redirect`.
   - Admin/editor toggles call into a shared `PreviewRegistry` (instead of duplicating AJAX + `save_post` logic) and emit REST responses for Gutenberg.
   - Legacy `posts_results` filter is retired; we instead inject our query via `wp`/`template_redirect` or short-circuit `template_include` in edge cases.

4. **Data layer**
   - Replace the monolithic option with a lightweight table `wp_public_post_previews (post_id, token, expires_at, meta)` or a custom post type `pprev_token`.
   - Provide repository APIs (`PreviewTokenRepository`, `PreviewStatsRepository`) so future versions can move storage to Redis, object cache, or remote API without rewriting calling code.

5. **Extensibility**
   - Dedicated filter `pprev_register_adapter` for third-party builders; adapters implement `AdapterInterface` (`supports( PreviewContext )`, `bootstrap()`, `finalize()`).
   - CLI command (`wp pprev preview --post=<id>`) uses the same services to generate temporary preview URLs for QA/CI.

### 6. TagDiv Adapter Requirements

1. **Detection**
   - Adapter activates when `td-cloud-library` (TagDiv Template Builder) is active *and* the incoming query either targets `tdb_templates` or the current theme declares TagDiv support.
   - Additional heuristics: check for `class_exists( 'tdb_state_loader' )` and `defined( 'TD_PATH' )`.

2. **Lifecycle hooks**
   - `bootstrap( PreviewContext $context )` — runs before `tdb_state_loader::on_template_redirect_load_state` finishes. Responsibilities:
     - Inject `td_preview_post_id` into `$_GET`/`$_REQUEST`.
     - Register a low-priority callback on `template_redirect` to swap queries only after TagDiv captured its template state.
   - `finalize( PreviewContext $context, WP_Query $preview_query )` — runs once we swap the query:
     - Update `tdb_state_single`, `tdb_state_content`, and `td_global` using the *preview* query clone, not the TagDiv template query.
     - Optionally notify TagDiv’s global autoload features (autoload counters, analytics) that this render is a “public preview”.

3. **Query management**
   - Instead of mutating the TagDiv template query, adapter hands PPP’s `PreviewQuery` to TagDiv via a method like `tdb_global_wp_query::set_wp_query_content( $preview_query )`.
   - Provide fallback if TagDiv denies the swap (e.g. template missing) — adapter can return `AdapterResult::SKIP`, letting core fallback to default rendering.

4. **Diagnostics**
   - Adapter should log its internal checkpoints (`primed_td_preview_post_id`, `template_redirect_swap_complete`) via the new logging infrastructure.
   - Include a status command `wp pprev diag tagdiv` to output current adapter configuration, detected template ID, and last preview outcome.

5. **Extensibility points**
   - Filters: `pprev_tagdiv_adapter_enabled`, `pprev_tagdiv_adapter_swap_priority`, `pprev_tagdiv_adapter_states` for sites that extend TagDiv (e.g. Newspaper child themes).
   - Action hook `_pprev_tagdiv_after_swap( PreviewContext $context, WP_Query $preview_query )` for themes needing extra cache invalidation.

### 7. Logging & Testing Strategy

1. **Logging**
   - Introduce `PPrevLoggerInterface` (PSR-3 compatible). Provide default implementation that writes to `wp-content/uploads/public-post-preview/preview.log` with daily rotation.
   - Every preview request gets a UUID (e.g. `PPrev-2025-11-17-<hash>`) stored inside `PreviewContext` for correlation across adapters, core services, and REST/CLI output.
   - Structured log fields: `timestamp`, `request_id`, `post_id`, `token`, `adapter`, `stage` (`resolver`, `adapter.bootstrap`, `swap`, `emit`), `message`, `context`.
   - Admin screen “Preview Diagnostics” lists recent log entries filtered by post ID/adapter, with export to JSON for support.

2. **Automated tests**
   - **PHPUnit unit tests** (fast): cover resolver, token repository, adapter bus, and logging, using mocked WordPress functions via Brain Monkey.
   - **Integration tests** (slower): spin up `WP_UnitTestCase`, register fake posts/templates, and assert that:
     - default adapter renders `the_content`.
     - TagDiv adapter defers swap until after template loader (use simplified stub).
     - nonces/expiration behave according to custom option.
   - **End-to-end smoke tests**: Playwright or Cypress scripts hitting a local WP instance, toggling preview via REST, loading `_ppp` URL, and verifying DOM selectors (`.tdb_single_content`, `.entry-content`) contain expected text.
   - **CLI tests**: use `wp-cli-tests` framework to ensure `wp pprev preview` and `wp pprev diag` commands behave correctly.

### 8. Migration & Rollout Plan

1. **Feature flags**
   - Environment variable `PPREV_PREVIEW_PIPELINE=beta` or WP filter `pprev_use_next_pipeline` controls whether the new controller handles requests.
   - Plugin settings screen gains a “Preview engine” toggle (Classic vs Next) with telemetry summary so site owners can roll back quickly.

2. **Data migration**
   - On upgrade, run a background task that reads the legacy `public_post_preview` option in chunks and inserts rows into the new token repository.
   - Preserve backward compatibility: if migration isn’t finished, legacy option remains authoritative; once complete, set `pprev_tokens_migrated` flag.

3. **Release phases**
   - **Phase 1 (beta)**: ship new architecture disabled by default, provide CLI/REST switches for testers, gather telemetry via logging.
   - **Phase 2 (opt-out)**: enable new pipeline by default for fresh installs, provide filter to revert.
   - **Phase 3 (general availability)**: remove legacy `posts_results` path once metrics confirm stability.

4. **Documentation & support**
   - Update `README.md` + `docs/tagdiv.md` with adapter instructions, CLI usage, troubleshooting steps.
   - Publish migration guide on GitHub Releases summarizing breaking changes (e.g. new database table, logging location).
   - Provide snippet for Health Check screen that reports preview engine status and recent errors for easier support tickets.


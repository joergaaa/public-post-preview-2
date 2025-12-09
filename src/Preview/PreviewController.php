<?php
namespace PPrev\Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PPrev\Adapters\AdapterBus;
use PPrev\Contracts\LoggerInterface;
use PPrev\Preview\PreviewRequest;
use WP;
use WP_Query;

class PreviewController {

	/**
	 * @var PreviewResolver
	 */
	private $resolver;

	/**
	 * @var PreviewQueryFactory
	 */
	private $query_factory;

	/**
	 * @var AdapterBus
	 */
	private $adapter_bus;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var PreviewContext|null
	 */
	private $active_context = null;

	public function __construct(
		PreviewResolver $resolver,
		PreviewQueryFactory $query_factory,
		AdapterBus $adapter_bus,
		LoggerInterface $logger
	) {
		$this->resolver      = $resolver;
		$this->query_factory = $query_factory;
		$this->adapter_bus   = $adapter_bus;
		$this->logger        = $logger;
	}

	/**
	 * Adds WP hooks for the controller.
	 */
	public function register_hooks() {
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'parse_request', array( $this, 'maybe_handle' ), 0 );
	}

	/**
	 * Ensures `_ppp` is a recognized query var.
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = '_ppp';
		return $vars;
	}

	/**
	 * Detects preview requests and boots the pipeline.
	 *
	 * @param WP $wp
	 */
	public function maybe_handle( WP $wp ) {
		if ( empty( $wp->query_vars['_ppp'] ) ) {
			return;
		}

		$request = PreviewRequest::from_wp( $wp );
		$context = $this->resolver->resolve( $request );

		if ( ! $context ) {
			return;
		}

		$this->active_context = $context;

		$this->adapter_bus->bootstrap( $context );

		add_action( 'template_redirect', array( $this, 'render_preview' ), 1 );
		add_filter( 'pre_handle_404', array( $this, 'prevent_404' ), 10, 2 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
	}

	/**
	 * Prevents WordPress from issuing 404s for valid preview requests.
	 *
	 * @param bool     $preempt
	 * @param WP_Query $wp_query
	 *
	 * @return bool
	 */
	public function prevent_404( $preempt, $wp_query ) {
		if ( ! $this->active_context || true === $preempt ) {
			return $preempt;
		}

		if ( $wp_query instanceof WP_Query && $wp_query->is_main_query() ) {
			$wp_query->is_preview  = true;
			$wp_query->is_singular = true;
			$wp_query->is_404      = false;
			return true;
		}

		return $preempt;
	}

	/**
	 * Executes the preview rendering.
	 */
	public function render_preview() {
		if ( ! $this->active_context ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex', true );

		$preview_query = $this->query_factory->build( $this->active_context );

		$handled = $this->adapter_bus->finalize( $this->active_context, $preview_query );

		if ( ! $handled ) {
			// Guarantee we still show the preview even if no adapter handled it.
			global $wp_query;
			$wp_query        = $preview_query;
			$GLOBALS['post'] = $preview_query->post;
			setup_postdata( $preview_query->post );
		}

		$this->logger->info(
			'Preview pipeline completed',
			array(
				'post_id'    => $this->active_context->post()->ID,
				'request_id' => $this->active_context->request_id(),
				'adapter_handled' => $handled ? 'yes' : 'no',
			)
		);
	}
}


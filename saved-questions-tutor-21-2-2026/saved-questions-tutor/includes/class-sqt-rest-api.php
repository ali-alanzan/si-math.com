<?php
/**
 * REST API handler for saved questions
 *
 * @package Saved_Questions_Tutor
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class
 */
class SQT_REST_API {

	/**
	 * Storage instance
	 *
	 * @var SQT_Storage
	 */
	private $storage;

	/**
	 * Namespace
	 *
	 * @var string
	 */
	private $namespace = 'saved-questions/v1';

	/**
	 * Constructor
	 *
	 * @param SQT_Storage $storage Storage instance
	 */
	public function __construct( $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Initialize REST API
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public function register_routes() {
		// Save question
		register_rest_route(
			$this->namespace,
			'/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_question' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'quiz_id'         => array(
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'wp_kses_post',
					),
					'question_id'     => array(
						'required' => false,
						'type'     => 'string',
						'sanitize_callback' => 'wp_kses_post',
					),
					'question_content' => array(
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'wp_kses_post',
					),
					'meta'            => array(
						'required' => false,
						'type'     => 'object',
					),
					'source_url'      => array(
						'required' => false,
						'type'     => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Get saved questions
		register_rest_route(
			$this->namespace,
			'/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_saved_questions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'limit'  => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
						'sanitize_callback' => 'absint',
					),
					'offset' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Remove saved question
		register_rest_route(
			$this->namespace,
			'/remove',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'remove_question' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'saved_id' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// Export saved questions
		register_rest_route(
			$this->namespace,
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_questions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'format' => array(
						'required' => false,
						'type'     => 'string',
						'default'  => 'json',
						'enum'     => array( 'json', 'csv' ),
					),
				),
			)
		);
	}

	/**
	 * Check permission
	 *
	 * @param WP_REST_Request $request Request object
	 * @return bool|WP_Error
	 */
	public function check_permission( $request ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access this resource.', 'saved-questions-tutor' ),
				array( 'status' => 401 )
			);
		}

		// Check nonce
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( 'nonce' );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'saved-questions-tutor' ),
				array( 'status' => 403 )
			);
		}

		// Check capability (students should be able to save questions)
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'saved-questions-tutor' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Save question endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_question( $request ) {
		$user_id = get_current_user_id();
		$data = $request->get_params();
// 		print_r([$_POST, $_REQUEST, $_GET, $data, $request->get_params()]);
// 		exit;
		$result = $this->storage->save_question( $user_id, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'saved_item' => $result,
				'message'    => __( 'Question saved successfully.', 'saved-questions-tutor' ),
			),
			200
		);
	}

	/**
	 * Get saved questions endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response
	 */
	public function get_saved_questions( $request ) {
		$user_id = get_current_user_id();
		$limit = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		$saved = $this->storage->get_saved_questions( $user_id, $limit, $offset );
		$count = $this->storage->get_count( $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'items'   => $saved,
				'count'   => $count,
			),
			200
		);
	}

	/**
	 * Remove question endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_question( $request ) {
		$user_id = get_current_user_id();
		$saved_id = $request->get_param( 'saved_id' );

		$result = $this->storage->remove_question( $user_id, $saved_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Question removed successfully.', 'saved-questions-tutor' ),
			),
			200
		);
	}

	/**
	 * Export questions endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response
	 */
	public function export_questions( $request ) {
		$user_id = get_current_user_id();
		$format = $request->get_param( 'format' );
		$saved = $this->storage->get_saved_questions( $user_id );

		if ( 'csv' === $format ) {
			// For CSV, we need to output directly
			$this->export_csv_direct( $saved );
			// This will exit, so we never reach here
			return new WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $saved,
					'format'  => 'json',
				),
				200
			);
		}
	}

	/**
	 * Export as CSV (direct output)
	 *
	 * @param array $saved Saved questions
	 * @return void
	 */
	private function export_csv_direct( $saved ) {
		$filename = 'saved-questions-' . date( 'Y-m-d' ) . '.csv';

		// Set headers
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$output = fopen( 'php://output', 'w' );

		// BOM for UTF-8
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Headers
		fputcsv( $output, array( 'ID', 'Quiz ID', 'Question ID', 'Question Content', 'Meta', 'Source URL', 'Saved At' ) );

		// Data
		foreach ( $saved as $item ) {
			fputcsv(
				$output,
				array(
					$item['saved_id'] ?? '',
					$item['quiz_id'] ?? '',
					$item['question_id'] ?? '',
					strip_tags( $item['question_content'] ?? '' ),
					! empty( $item['meta'] ) ? wp_json_encode( $item['meta'] ) : '',
					$item['source_url'] ?? '',
					$item['saved_at'] ?? '',
				)
			);
		}

		fclose( $output );
		exit;
	}
}
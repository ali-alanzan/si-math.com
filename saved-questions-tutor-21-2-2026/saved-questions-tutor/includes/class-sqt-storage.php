<?php
/**
 * Storage handler for saved questions
 *
 * Supports both user meta and custom database table
 *
 * @package Saved_Questions_Tutor
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage class
 */
class SQT_Storage {

	/**
	 * Table name for custom storage
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Whether to use custom table
	 *
	 * @var bool
	 */
	private $use_custom_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'saved_questions';
		$this->use_custom_table = false; // get_option( 'sqt_use_custom_table', false );
	}

	/**
	 * Save a question for a user
	 *
	 * @param int   $user_id User ID
	 * @param array $data Question data 
	 * @return array|WP_Error Saved item data or error
	 */
	public function save_question( $user_id, $data ) {
		// Validate user
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID.', 'saved-questions-tutor' ) );
		}
		
		

		// Sanitize and validate data
		$sanitized = $this->sanitize_question_data( $data );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		// Check if question already saved
		if ( $this->is_question_saved( $user_id, $sanitized['quiz_id'], $sanitized['question_id'] ) ) {
			return new WP_Error( 'already_saved', __( 'This question is already saved.', 'saved-questions-tutor' ) );
		}

		// Prepare saved item
		$saved_item = array(
			'user_id'        => $user_id,
			'quiz_id'        => absint( $sanitized['quiz_id'] ),
			'question_id'    => ! empty( $sanitized['question_id'] ) ?  $sanitized['question_id']  : null,
			'question_content' => wp_kses_post( $sanitized['question_content'] ),
			'meta'           => $sanitized['meta'],
			'saved_at'       => current_time( 'mysql' ),
			'source_url'     => esc_url_raw( $sanitized['source_url'] ?? '' ),
		);
        
        // print_r([$data, $sanitized, $saved_item]);
        // exit;
        
		if ( $this->use_custom_table ) {
			return $this->save_to_custom_table( $saved_item );
		} else {
			return $this->save_to_user_meta( $user_id, $saved_item );
		}
	}

	/**
	 * Get saved questions for a user
	 *
	 * @param int $user_id User ID
	 * @param int $limit Optional limit
	 * @param int $offset Optional offset
	 * @return array Array of saved questions
	 */
	public function get_saved_questions( $user_id, $limit = 0, $offset = 0 ) {
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return array();
		}

		if ( $this->use_custom_table ) {
			return $this->get_from_custom_table( $user_id, $limit, $offset );
		} else {
			return $this->get_from_user_meta( $user_id, $limit, $offset );
		}
	}

	/**
	 * Remove a saved question
	 *
	 * @param int $user_id User ID
	 * @param int $saved_id Saved item ID
	 * @return bool|WP_Error Success or error
	 */
	public function remove_question( $user_id, $saved_id ) {
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID.', 'saved-questions-tutor' ) );
		}

		if ( $this->use_custom_table ) {
			return $this->remove_from_custom_table( $user_id, $saved_id );
		} else {
			return $this->remove_from_user_meta( $user_id, $saved_id );
		}
	}

	/**
	 * Check if question is already saved
	 *
	 * @param int $user_id User ID
	 * @param int $quiz_id Quiz ID
	 * @param int $question_id Question ID (optional)
	 * @return bool
	 */
	public function is_question_saved( $user_id, $quiz_id, $question_id = null ) {
		$saved = $this->get_saved_questions( $user_id );
		
		foreach ( $saved as $item ) {
			if ( $item['quiz_id'] == $quiz_id ) {
				if ( null === $question_id || $item['question_id'] == $question_id ) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Get count of saved questions for a user
	 *
	 * @param int $user_id User ID
	 * @return int
	 */
	public function get_count( $user_id ) {
		$saved = $this->get_saved_questions( $user_id );
		return count( $saved );
	}

	/**
	 * Sanitize question data
	 *
	 * @param array $data Raw data
	 * @return array|WP_Error Sanitized data or error
	 */
	private function sanitize_question_data( $data ) {
		$required = array( 'quiz_id', 'question_content' );
		
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'saved-questions-tutor' ), $field ) );
			}
		}

		return array(
			'quiz_id'         => $data['quiz_id'],
			'question_id'     => ! empty( $data['question_id'] ) ?  $data['question_id'] : null,
			'question_content' => wp_kses_post( $data['question_content'] ),
			'meta'            => ! empty( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array(),
			'source_url'      => ! empty( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : '',
		);
	}

	/**
	 * Save to user meta
	 *
	 * @param int   $user_id User ID
	 * @param array $item Item data
	 * @return array Saved item with ID
	 */
	private function save_to_user_meta( $user_id, $item ) {
		$saved = get_user_meta( $user_id, 'saved_questions', true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Generate unique ID
		$item['saved_id'] = uniqid( 'sq_', true );
		$saved[] = $item;

		// Sort by saved_at descending
		usort( $saved, function( $a, $b ) {
			return strtotime( $b['saved_at'] ) - strtotime( $a['saved_at'] );
		} );
        
     
		update_user_meta( $user_id, 'saved_questions', $saved );
		return $item;
	}

	/**
	 * Get from user meta
	 *
	 * @param int $user_id User ID
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array
	 */
	private function get_from_user_meta( $user_id, $limit = 0, $offset = 0 ) {
		$saved = get_user_meta( $user_id, 'saved_questions', true );
		if ( ! is_array( $saved ) ) {
			return array();
		}

		// Sort by saved_at descending
		usort( $saved, function( $a, $b ) {
			return strtotime( $b['saved_at'] ) - strtotime( $a['saved_at'] );
		} );

		if ( $limit > 0 ) {
			$saved = array_slice( $saved, $offset, $limit );
		}

		return $saved;
	}

	/**
	 * Remove from user meta
	 *
	 * @param int $user_id User ID
	 * @param int $saved_id Saved item ID
	 * @return bool
	 */
	private function remove_from_user_meta( $user_id, $saved_id ) {
		$saved = get_user_meta( $user_id, 'saved_questions', true );
		if ( ! is_array( $saved ) ) {
			return false;
		}

		$filtered = array_filter( $saved, function( $item ) use ( $saved_id ) {
			return isset( $item['saved_id'] ) && $item['saved_id'] !== $saved_id;
		} );

		update_user_meta( $user_id, 'saved_questions', array_values( $filtered ) );
		return true;
	}

	/**
	 * Create custom table
	 */
	public function create_custom_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			quiz_id bigint(20) UNSIGNED NOT NULL,
			question_id bigint(20) UNSIGNED NULL,
			question_content longtext NOT NULL,
			meta longtext NULL,
			source_url varchar(500) NULL,
			saved_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY question_id (question_id),
			KEY saved_at (saved_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Save to custom table
	 *
	 * @param array $item Item data
	 * @return array Saved item with ID
	 */
	private function save_to_custom_table( $item ) {
		global $wpdb;

		$insert_data = array(
			'user_id'         => $item['user_id'],
			'quiz_id'         => $item['quiz_id'],
			'question_id'     => $item['question_id'],
			'question_content' => $item['question_content'],
			'meta'            => ! empty( $item['meta'] ) ? wp_json_encode( $item['meta'] ) : null,
			'source_url'      => $item['source_url'],
			'saved_at'        => $item['saved_at'],
		);

		$wpdb->insert( $this->table_name, $insert_data );
		$item['saved_id'] = $wpdb->insert_id;
		$item['id'] = $wpdb->insert_id;

		return $item;
	}

	/**
	 * Get from custom table
	 *
	 * @param int $user_id User ID
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array
	 */
	private function get_from_custom_table( $user_id, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$query = "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY saved_at DESC";
		$params = array( $user_id );

		if ( $limit > 0 ) {
			$query .= " LIMIT %d OFFSET %d";
			$params[] = $limit;
			$params[] = $offset;
		}

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		// Decode meta
		foreach ( $results as &$result ) {
			$result['saved_id'] = $result['id'];
			if ( ! empty( $result['meta'] ) ) {
				$result['meta'] = json_decode( $result['meta'], true );
			} else {
				$result['meta'] = array();
			}
		}

		return $results;
	}

	/**
	 * Remove from custom table
	 *
	 * @param int $user_id User ID
	 * @param int $saved_id Saved item ID
	 * @return bool
	 */
	private function remove_from_custom_table( $user_id, $saved_id ) {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array(
				'id'      => $saved_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Migrate from user meta to custom table
	 *
	 * @return int Number of migrated items
	 */
	public function migrate_to_custom_table() {
		global $wpdb;

		// Create table if not exists
		$this->create_custom_table();

		// Get all users with saved questions
		$users = $wpdb->get_col(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'saved_questions'"
		);

		$migrated = 0;

		foreach ( $users as $user_id ) {
			$saved = get_user_meta( $user_id, 'saved_questions', true );
			if ( ! is_array( $saved ) ) {
				continue;
			}

			foreach ( $saved as $item ) {
				$insert_data = array(
					'user_id'         => $user_id,
					'quiz_id'         => $item['quiz_id'],
					'question_id'     => $item['question_id'] ?? null,
					'question_content' => $item['question_content'],
					'meta'            => ! empty( $item['meta'] ) ? wp_json_encode( $item['meta'] ) : null,
					'source_url'      => $item['source_url'] ?? '',
					'saved_at'        => $item['saved_at'],
				);

				$wpdb->insert( $this->table_name, $insert_data );
				$migrated++;
			}
		}

		return $migrated;
	}
}


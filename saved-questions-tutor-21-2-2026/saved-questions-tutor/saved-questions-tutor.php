<?php
/**
 * Plugin Name: Saved Questions for Tutor LMS
 * Plugin URI: https://example.com/saved-questions-tutor
 * Description: Allows students to save quiz questions and access them later from their profile. Compatible with Tutor LMS.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: saved-questions-tutor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SQT_VERSION', '1.0.0' );
define( 'SQT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SQT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SQT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Saved_Questions_Tutor {

	/**
	 * Single instance of the class
	 *
	 * @var Saved_Questions_Tutor
	 */
	private static $instance = null;

	/**
	 * Storage handler instance
	 *
	 * @var SQT_Storage
	 */
	public $storage;

	/**
	 * REST API handler instance
	 *
	 * @var SQT_REST_API
	 */
	public $rest_api;

	/**
	 * Frontend handler instance
	 *
	 * @var SQT_Frontend
	 */
	public $frontend;

	/**
	 * Get single instance
	 *
	 * @return Saved_Questions_Tutor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		require_once SQT_PLUGIN_DIR . 'includes/class-sqt-storage.php';
		require_once SQT_PLUGIN_DIR . 'includes/class-sqt-rest-api.php';
		require_once SQT_PLUGIN_DIR . 'includes/class-sqt-frontend.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if Tutor LMS is active
		if ( ! $this->is_tutor_lms_active() ) {
			add_action( 'admin_notices', array( $this, 'tutor_lms_missing_notice' ) );
			return;
		}

		// Wait for Tutor LMS to be fully loaded
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}

		// Load text domain
		load_plugin_textdomain(
			'saved-questions-tutor',
			false,
			dirname( SQT_PLUGIN_BASENAME ) . '/languages'
		);

		// Initialize components only if Tutor LMS is ready
		if ( ! isset( $this->storage ) ) {
			$this->storage = new SQT_Storage();
		}
		if ( ! isset( $this->rest_api ) ) {
			$this->rest_api = new SQT_REST_API( $this->storage );
		}
		if ( ! isset( $this->frontend ) ) {
			$this->frontend = new SQT_Frontend( $this->storage );
		}

		// Initialize components
		if ( $this->rest_api ) {
			$this->rest_api->init();
		}
		if ( $this->frontend ) {
			$this->frontend->init();
		}
	}

	/**
	 * Check if Tutor LMS is active
	 *
	 * @return bool
	 */
	private function is_tutor_lms_active() {
		return class_exists( 'TUTOR\Tutor' ) || function_exists( 'tutor' );
	}

	/**
	 * Show notice if Tutor LMS is not active
	 */
	public function tutor_lms_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo esc_html__(
					'Saved Questions for Tutor LMS requires Tutor LMS plugin to be installed and activated.',
					'saved-questions-tutor'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Load dependencies first
		$this->load_dependencies();

		// Create custom table if option is enabled
		$use_custom_table = get_option( 'sqt_use_custom_table', false );
		if ( $use_custom_table ) {
			$this->storage = new SQT_Storage();
			$this->storage->create_custom_table();
		}

		// Create saved questions page
		$this->create_saved_questions_page();

		// Add rewrite endpoint
		add_rewrite_endpoint( 'saved-questions', EP_ROOT );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create saved questions page on activation
	 */
	private function create_saved_questions_page() {
		$page_slug = 'saved-questions-profile';
		$existing_page = get_page_by_path( $page_slug );

		if ( ! $existing_page ) {
			$page_data = array(
				'post_title'    => __( 'Saved Questions', 'saved-questions-tutor' ),
				'post_content'  => '[saved_questions_tab]',
				'post_status'   => 'publish',
				'post_type'     => 'page',
				'post_name'     => $page_slug,
			);

			$page_id = wp_insert_post( $page_data );

			if ( $page_id ) {
				update_option( 'sqt_saved_questions_page_id', $page_id );
			}
		} else {
			update_option( 'sqt_saved_questions_page_id', $existing_page->ID );
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Initialize plugin
 */
function saved_questions_tutor() {
	return Saved_Questions_Tutor::get_instance();
}

// Start the plugin
saved_questions_tutor();
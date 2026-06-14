<?php
/**
 * Frontend handler for saved questions
 *
 * @package Saved_Questions_Tutor
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class
 */
class SQT_Frontend {

	/**
	 * Storage instance
	 *
	 * @var SQT_Storage
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param SQT_Storage $storage Storage instance
	 */
	public function __construct( $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Initialize frontend
	 */
	public function init() {
		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add save button to quiz questions
		// Try Tutor LMS hooks first
		add_action( 'tutor_quiz/question_content', array( $this, 'add_save_button' ), 20 );
		add_action( 'tutor_single_quiz/question_content', array( $this, 'add_save_button' ), 20 );
		add_action( 'tutor_quiz_question_content', array( $this, 'add_save_button' ), 20 );
		
		// Fallback: inject via JavaScript
		add_action( 'wp_footer', array( $this, 'inject_save_buttons' ) );

		// Add tab to profile - Try multiple Tutor LMS hooks
		add_filter( 'tutor_profile_tabs', array( $this, 'add_profile_tab' ), 10, 1 );
		add_filter( 'tutor_profile_tabs_list', array( $this, 'add_profile_tab' ), 10, 1 );
		add_action( 'tutor_profile_tabs_content', array( $this, 'profile_tab_content' ) );
		add_action( 'tutor_profile_tab_content', array( $this, 'profile_tab_content_alt' ), 10, 1 );
		
		// Alternative method using Tutor's action hook
		add_action( 'tutor_profile_tabs_after', array( $this, 'add_profile_tab_alternative' ) );

		// Add link to saved questions page in profile menu
		add_filter( 'tutor_dashboard/nav_items', array( $this, 'add_profile_menu_link' ), 20, 1 );

		// Register shortcode
		add_shortcode( 'saved_questions_tab', array( $this, 'saved_questions_shortcode' ) );
		add_shortcode( 'saved_questions', array( $this, 'saved_questions_shortcode' ) );

		// Create page on activation
// 		add_action( 'admin_init', array( $this, 'maybe_create_saved_questions_page' ) );

		// Add direct output hook as last resort
		add_action( 'wp_footer', array( $this, 'maybe_output_saved_questions' ), 999 );

		// Add dashboard page for saved questions
		add_action( 'init', array( $this, 'add_dashboard_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_dashboard_query_vars' ) );
		
		// Add menu item to dashboard sidebar (Tutor LMS) - Multiple methods
		add_filter( 'tutor_dashboard/nav_items', array( $this, 'add_dashboard_nav_item' ), 10, 1 );
		add_filter( 'tutor_dashboard/instructor_nav_items', array( $this, 'add_dashboard_nav_item' ), 10, 1 );
		add_filter( 'tutor_dashboard/student_nav_items', array( $this, 'add_dashboard_nav_item' ), 10, 1 );
		
		// Handle dashboard page content
		add_action( 'tutor_dashboard/nav_items/saved_questions', array( $this, 'dashboard_nav_content' ) );
		add_action( 'tutor_dashboard_content', array( $this, 'maybe_render_dashboard_content' ) );
		
		
		
// Register the AJAX hook for logged-in users
        add_action('wp_ajax_sqt_get_saved_questions', array($this, 'get_saved_questions_callback'));
        
        // Enqueue your frontend script
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        
	}
	
	

    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'sqt-frontend-js', 
            SQT_PLUGIN_URL . 'assets/js/frontend.js', // Adjust path if necessary
            array('jquery'), 
            SQT_VERSION, 
            true
        );

        // This makes "sqt_vars" available in frontend.js
        wp_localize_script('sqt-frontend-js', 'sqt_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sqt_secure_nonce')
        ));
    }



    public function get_saved_questions_callback() {
        // 1. Security Check
        check_ajax_referer('sqt_secure_nonce', 'security');
    
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }
    
        // 2. Get params from JS request
        // We use $_POST because it's an AJAX call
        $target_quiz_id     = isset($_POST['tutor_quiz_id']) ? absint($_POST['tutor_quiz_id']) : 0;
        $target_question_id = isset($_POST['tutor_question_id']) ? sanitize_text_field($_POST['tutor_question_id']) : '';
    
        // 3. Fetch the saved questions array
        $saved_questions = get_user_meta($user_id, 'saved_questions', true);
        
        $is_saved = false;
    
        // 4. Logic to check if the specific question exists in the array
        if ( ! empty($saved_questions) && is_array($saved_questions) ) {
            foreach ( $saved_questions as $item ) {
                // Check if both quiz_id and question_id match
                if ( (int) $item['quiz_id'] === $target_quiz_id && $item['question_id'] === $target_question_id ) {
                    $is_saved = true;
                    break; // Exit loop early once found
                }
            }
        }
    
        // 5. Return the boolean result
        wp_send_json_success(array(
            'is_saved' => $is_saved
        ));
    
        wp_die();
    }

    

	/**
	 * Enqueue assets
	 */
	public function enqueue_assets() {
		// Safety check: don't proceed if Tutor LMS is not available
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}

		// Always enqueue on all pages (JavaScript will decide where to show buttons)
		// This ensures the script is loaded even if page detection fails

		wp_enqueue_script(
			'sqt-frontend',
			SQT_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wp-api-fetch' ),
			SQT_VERSION,
			true
		);

		wp_enqueue_style(
			'sqt-frontend',
			SQT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SQT_VERSION
		);

		// Localize script
		global $post;
		wp_localize_script(
			'sqt-frontend',
			'sqtData',
			array(
				'apiUrl'   => rest_url( 'saved-questions/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'postId'   => $post ? $post->ID : 0,
				'strings'  => array(
					'save'           => __( 'Save Question', 'saved-questions-tutor' ),
					'saved'          => __( 'Saved', 'saved-questions-tutor' ),
					'saving'         => __( 'Saving...', 'saved-questions-tutor' ),
					'remove'         => __( 'Remove', 'saved-questions-tutor' ),
					'removing'       => __( 'Removing...', 'saved-questions-tutor' ),
					'error'          => __( 'An error occurred. Please try again.', 'saved-questions-tutor' ),
					'export'         => __( 'Export', 'saved-questions-tutor' ),
					'exportJson'     => __( 'Export as JSON', 'saved-questions-tutor' ),
					'exportCsv'      => __( 'Export as CSV', 'saved-questions-tutor' ),
					'noQuestions'    => __( 'No saved questions yet.', 'saved-questions-tutor' ),
					'confirmRemove'  => __( 'Are you sure you want to remove this question?', 'saved-questions-tutor' ),
				),
			)
		);
	}

	/**
	 * Check if current page is a quiz page
	 *
	 * @return bool
	 */
	private function is_quiz_page() {
		global $post;
		if ( ! $post ) {
			return false;
		}

		// Check if Tutor quiz - with proper null checks
		if ( function_exists( 'tutor' ) ) {
			$tutor_instance = tutor();
			if ( $tutor_instance && isset( $tutor_instance->utils ) && is_object( $tutor_instance->utils ) ) {
				if ( method_exists( $tutor_instance->utils, 'is_quiz' ) ) {
					if ( $tutor_instance->utils->is_quiz( $post->ID ) ) {
						return true;
					}
				}
			}
		}

		// Check by post type
		if ( 'tutor_quiz' === get_post_type( $post->ID ) ) {
			return true;
		}

		// Check if quiz content exists in page
		if ( has_shortcode( $post->post_content, 'tutor_quiz' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current page is a profile page
	 *
	 * @return bool
	 */
	private function is_profile_page() {
		if ( function_exists( 'tutor' ) ) {
			$tutor_instance = tutor();
			if ( $tutor_instance && isset( $tutor_instance->utils ) && is_object( $tutor_instance->utils ) ) {
				if ( method_exists( $tutor_instance->utils, 'is_tutor_page' ) ) {
					return $tutor_instance->utils->is_tutor_page( 'profile' );
				}
			}
		}
		return false;
	}

	/**
	 * Add save button using Tutor hook (if available)
	 *
	 * @param object $question Question object
	 */
	public function add_save_button( $question ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$this->render_save_button( $question );
	}

	/**
	 * Inject save buttons via JavaScript (fallback)
	 */
	public function inject_save_buttons() {
		if ( ! $this->is_quiz_page() || ! is_user_logged_in() ) {
			return;
		}
		?>
		<script type="text/javascript">
		// This will be handled by frontend.js
		</script>
		<?php
	} 

	/**
	 * Render save button
	 *
	 * @param object|array $question Question data
	 */
	private function render_save_button( $question ) {
	    return;
		$question_id = is_object( $question ) ? ( $question->question_id ?? $question->ID ?? 0 ) : ( $question['question_id'] ?? $question['ID'] ?? 0 );
		$quiz_id = get_the_ID();
		$user_id = get_current_user_id();
	
		if( $this->storage->is_question_saved($user_id, $quiz_id, $question_id) ):
	
		    ?>
    		<button 
    			type="button" 
    			class="sqt-save-btn saved" 
    			data-question-id="<?php echo esc_attr( $question_id ); ?>"
    			data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
    			aria-label="<?php esc_attr_e( 'Save Question', 'saved-questions-tutor' ); ?>"
    			disabled=""
    		>
				<span class="dashicons dashicons-bookmark"></span>
    			<span class="sqt-btn-text"><?php esc_html_e( 'Saved', 'saved-questions-tutor' ); ?></span>
    		</button>
		    <?php
        else:
    	   // print_r([
    	   //     $this->storage->is_question_saved($user_id, $quiz_id, $question_id),
        //         $user_id, 
        //         $quiz_id, 
        //         $question_id
        //     ]);
            ?>
		<button 
			type="button" 
			class="sqt-save-btn" 
			data-question-id="<?php echo esc_attr( $question_id ); ?>"
			data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
			aria-label="<?php esc_attr_e( 'Save Question', 'saved-questions-tutor' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
				<polyline points="17 21 17 13 7 13 7 21"></polyline>
				<polyline points="7 3 7 8 15 8"></polyline>
			</svg>
			<span class="sqt-btn-text"><?php esc_html_e( 'Save Question', 'saved-questions-tutor' ); ?></span>
		</button>
            <?php
        endif;
	}

	/**
	 * Add tab to Tutor profile
	 *
	 * @param array $tabs Existing tabs
	 * @return array
	 */
	public function add_profile_tab( $tabs ) {
		// Ensure $tabs is an array
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		$tabs['saved_questions'] = array(
			'title' => __( 'Saved Questions', 'saved-questions-tutor' ),
			'icon'  => 'dashicons-bookmark',
			'slug'  => 'saved_questions',
		);
		return $tabs;
	}

	/**
	 * Profile tab content
	 *
	 * @param string $tab Current tab
	 */
	public function profile_tab_content( $tab ) {
		// Check if this is our tab
		if ( 'saved_questions' !== $tab && 'saved-questions' !== $tab ) {
			return;
		}

		$this->render_saved_questions_list();
	}

	/**
	 * Alternative profile tab content method
	 *
	 * @param string $tab Current tab
	 */
	public function profile_tab_content_alt( $tab ) {
		// Check if this is our tab
		if ( 'saved_questions' !== $tab && 'saved-questions' !== $tab ) {
			return;
		}

		$this->render_saved_questions_list();
	}

	/**
	 * Alternative method to add profile tab (if filter doesn't work)
	 */
	public function add_profile_tab_alternative() {
		// This will be handled by JavaScript if needed
	}


	/**
	 * Get Quiz and Question Titles only
	 * @param int    $quiz_id      The ID of the quiz (e.g., 219)
	 * @param string $question_raw_id  The raw string (e.g., 'quiz-attempt-single-question-24')
	 */
	function get_tutor_titles($quiz_id, $question_raw_id) {
		global $wpdb;
		// 1. Extract the numeric ID from the string 'quiz-attempt-single-question-24'
		// This turns the string into the integer 24
		$clean_question_id_parts = explode("-", $question_raw_id);
		$clean_question_id = $clean_question_id_parts[count($clean_question_id_parts)-1];

// 		print_r([
			
// 			$clean_question_id_parts
// 		]);
// 		exit;
		// 2. Get the Quiz Title (Post ID 219)
		$quiz_title = get_the_title($quiz_id);

// 		// 3. Get the Question Title (Post ID 24)
// 		$question_title = get_the_title($clean_question_id);
// 		print_r([
// 			$quiz_id, 
// 			$question_raw_id,
// 			$clean_question_id,
// 			$quiz_title,
// 			$question_title,
// 			$clean_question_id_parts
// 		]);

		// We use $wpdb->prefix to automatically handle 'wp5r_'
		$table_name = $wpdb->prefix . 'tutor_quiz_questions';

		$question_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE question_id = %d",
				$clean_question_id // Use the numeric ID extracted earlier
			)
		);

// 		print_r([
// 			$quiz_id, 
// 			$question_raw_id,
// 			$clean_question_id,
// 			$quiz_title,
// 			$question_title,
// 			$question_data
// 		]);
// 		exit;
		return [
			'title'     => $quiz_title ? $quiz_title : "Quiz not found",
			'text' => $question_data->question_title ? $question_data->question_title : "Question not found"
		];
	}


	/**
	 * Render saved questions list
	 */
	private function render_saved_questions_list() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your saved questions.', 'saved-questions-tutor' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();
		$saved = $this->storage->get_saved_questions( $user_id );
		?>
		<div class="sqt-saved-questions-container">
			<div class="sqt-header">
				<h3><?php esc_html_e( 'Saved Questions', 'saved-questions-tutor' ); ?></h3>
				<?php if ( ! empty( $saved ) ) : ?>
					<div class="sqt-export-buttons">
						<a href="<?php echo esc_url( rest_url( 'saved-questions/v1/export?format=json&nonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>" 
						   class="sqt-export-btn sqt-export-json" 
						   download>
							<?php esc_html_e( 'Export JSON', 'saved-questions-tutor' ); ?>
						</a>
						<a href="<?php echo esc_url( rest_url( 'saved-questions/v1/export?format=csv&nonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>" 
						   class="sqt-export-btn sqt-export-csv" 
						   download>
							<?php esc_html_e( 'Export CSV', 'saved-questions-tutor' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<div class="sqt-questions-list" id="sqt-questions-list">
				<?php if ( empty( $saved ) ) : ?>
					<p class="sqt-no-questions"><?php esc_html_e( 'No saved questions yet.', 'saved-questions-tutor' ); ?></p>
				<?php else : ?>
					<?php 
// 		echo "<pre style='display:none;'>";
// 					print_r([$saved]);
// 				echo "</pre>";
		foreach ( $saved as $item ) : ?>
						<div class="sqt-question-item" data-saved-id="<?php echo esc_attr( $item['saved_id'] ?? '' ); ?>">
							<div class="sqt-question-content">
								<?php echo $this->get_tutor_titles($item["quiz_id"], $item["question_id"])["title"] ?? ""; // wp_kses_post( $item['question_content'] ?? '' ); ?>
							</div>
							<?php if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) : ?>
								<div class="sqt-question-meta">
									<ul class="sqt-choices">
										<li><?php echo $this->get_tutor_titles($item["quiz_id"], $item["question_id"])["text"] ?? ""; ?></li>
									</ul>
									<?php if ( 0 && ! empty( $item['meta']['choices'] ) ) : ?>
										<ul class="sqt-choices">
											<?php foreach ( $item['meta']['choices'] as $choice ) : ?>
												<li><?php echo esc_html( $choice ); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="sqt-question-footer">
								<?php if ( ! empty( $item['source_url'] ) ) : ?>
									<a href="<?php echo esc_url( $item['source_url'] ); ?>" class="sqt-source-link">
										<?php esc_html_e( 'View Original', 'saved-questions-tutor' ); ?>
									</a>
								<?php endif; ?>
								<span class="sqt-saved-date">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Date */
											__( 'Saved on %s', 'saved-questions-tutor' ),
											date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['saved_at'] ?? '' ) )
										)
									);
									?>
								</span>
								<button type="button" class="sqt-remove-btn" data-saved-id="<?php echo esc_attr( $item['saved_id'] ?? '' ); ?>">
									<?php esc_html_e( 'Remove', 'saved-questions-tutor' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode to display saved questions
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function saved_questions_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your saved questions.', 'saved-questions-tutor' ) . '</p>';
		}

		// Enqueue assets if not already enqueued
		if ( ! wp_script_is( 'sqt-frontend', 'enqueued' ) ) {
			$this->enqueue_assets();
		}

		ob_start();
		$this->render_saved_questions_list();
		return ob_get_clean();
	}

	/**
	 * Direct method to output saved questions (for debugging)
	 */
	public function output_saved_questions_direct() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$this->render_saved_questions_list();
	}

	/**
	 * Maybe output saved questions if tab content area exists but is empty
	 */
	public function maybe_output_saved_questions() {
		// Only on profile pages
		if ( ! $this->is_profile_page() ) {
			return;
		}

		// Check if content area exists but is empty
		$content_area = '';
		if ( function_exists( 'tutor' ) ) {
			$tutor_instance = tutor();
			if ( $tutor_instance && isset( $tutor_instance->utils ) && is_object( $tutor_instance->utils ) ) {
				// Try to get current tab
				$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
				if ( 'saved_questions' === $current_tab || 'saved-questions' === $current_tab ) {
					// Content should be handled by profile_tab_content, but if not, output here
					echo '<div id="sqt-direct-output" style="display:none;">';
					$this->render_saved_questions_list();
					echo '</div>';
					?>
					<script>
					jQuery(document).ready(function($) {
						// Try to move content to proper location
						const content = $('#sqt-direct-output').html();
						if (content) {
							const target = $('.tutor-profile-tab-content-item[data-tab="saved_questions"], #saved_questions_content, [data-tab-content="saved_questions"]');
							if (target.length && !target.html().trim()) {
								target.html(content);
							}
							$('#sqt-direct-output').remove();
						}
					});
					</script>
					<?php
				}
			}
		}
	}

	/**
	 * Add dashboard endpoint
	 */
	public function add_dashboard_endpoint() {
		add_rewrite_endpoint( 'saved-questions', EP_ROOT );
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars
	 * @return array
	 */
	public function add_dashboard_query_vars( $vars ) {
		$vars[] = 'saved-questions';
		return $vars;
	}

	/**
	 * Maybe render dashboard content
	 */
	public function maybe_render_dashboard_content() {
		// Check if we're on the saved questions page
		$current_page = isset( $_GET['tutor_dashboard_page'] ) ? sanitize_text_field( $_GET['tutor_dashboard_page'] ) : '';
		
		if ( 'saved_questions' === $current_page || 'saved-questions' === $current_page ) {
			$this->render_dashboard_content();
		}
	}

	/**
	 * Add nav item to Tutor dashboard
	 *
	 * @param array $nav_items Navigation items
	 * @return array
	 */
	public function add_dashboard_nav_item( $nav_items ) {
		// Ensure $nav_items is an array
		if ( ! is_array( $nav_items ) ) {
			$nav_items = array();
		}

		// Get saved questions page URL
		$page_url = $this->get_saved_questions_page_url();

		$nav_items['saved_questions'] = array(
			'title' => __( 'Saved Questions', 'saved-questions-tutor' ),
			'icon'  => 'dashicons-bookmark',
			'url'   => $page_url,
		);
		return $nav_items;
	}

	/**
	 * Add link to profile menu
	 *
	 * @param array $nav_items Navigation items
	 * @return array
	 */
	public function add_profile_menu_link( $nav_items ) {
		// Ensure $nav_items is an array
		if ( ! is_array( $nav_items ) ) {
			$nav_items = array();
		}

		// Get saved questions page URL
		$page_url = $this->get_saved_questions_page_url();

		// Add after Question & Answer if exists, otherwise at the end
		if ( isset( $nav_items['question_answer'] ) ) {
			$new_items = array();
			foreach ( $nav_items as $key => $item ) {
				$new_items[ $key ] = $item;
				if ( 'question_answer' === $key ) {
					$new_items['saved_questions'] = array(
						'title' => __( 'Saved Questions', 'saved-questions-tutor' ),
						'icon'  => 'dashicons-bookmark',
						'url'   => $page_url,
					);
				}
			}
			return $new_items;
		} else {
			$nav_items['saved_questions'] = array(
				'title' => __( 'Saved Questions', 'saved-questions-tutor' ),
				'icon'  => 'dashicons-bookmark',
				'url'   => $page_url,
			);
		}

		return $nav_items;
	}

	/**
	 * Dashboard nav content
	 */
	public function dashboard_nav_content() {
		$this->render_dashboard_content();
	}
	


	/** 
	 * Render dashboard content (without header/footer - inside Tutor dashboard)
	 */
	private function render_dashboard_content() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Enqueue assets
		$this->enqueue_assets();
 
		$user_id = get_current_user_id();
		$saved = $this->storage->get_saved_questions( $user_id );

		?>
		<div class="sqt-saved-questions-dashboard">
			<div class="sqt-dashboard-header">
				<h2><?php esc_html_e( 'Saved Questions', 'saved-questions-tutor' ); ?></h2>
				<?php if ( ! empty( $saved ) ) : ?>
					<div class="sqt-export-buttons">
						<a href="<?php echo esc_url( rest_url( 'saved-questions/v1/export?format=json&nonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>" 
						   class="sqt-export-btn sqt-export-json" 
						   download>
							<?php esc_html_e( 'Export JSON', 'saved-questions-tutor' ); ?>
						</a>
						<a href="<?php echo esc_url( rest_url( 'saved-questions/v1/export?format=csv&nonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>" 
						   class="sqt-export-btn sqt-export-csv" 
						   download>
							<?php esc_html_e( 'Export CSV', 'saved-questions-tutor' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<div class="sqt-questions-list-dashboard">
				<?php if ( empty( $saved ) ) : ?>
					<div class="sqt-no-questions">
						<p><?php esc_html_e( 'No saved questions yet.', 'saved-questions-tutor' ); ?></p>
						<p><?php esc_html_e( 'Start saving questions from quizzes to see them here.', 'saved-questions-tutor' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $saved as $item ) : ?>
						<div class="sqt-question-item-dashboard" data-saved-id="<?php echo esc_attr( $item['saved_id'] ?? '' ); ?>">
							<!-- Question Content - Same as Quiz -->
							<div class="sqt-question-wrapper">
								<div class="sqt-question-title">
									<?php echo wp_kses_post( $item['question_content'] ?? '' ); ?>
								</div>
								
								<!-- Answers/Choices - Same as Quiz -->
								<?php if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) && ! empty( $item['meta']['choices'] ) ) : ?>
									<div class="sqt-question-answers">
										<?php 
										$choices = $item['meta']['choices'];
										$question_type = $item['meta']['type'] ?? 'single_choice';
										$is_multiple = ( 'multiple_choice' === $question_type );
										
										foreach ( $choices as $index => $choice ) : 
											$is_correct = ( isset( $item['meta']['correct'] ) && $item['meta']['correct'] == $index );
										?>
											<div class="sqt-answer-option <?php echo $is_correct ? 'sqt-correct-answer' : ''; ?>">
												<label class="sqt-answer-label">
													<input 
														type="<?php echo $is_multiple ? 'checkbox' : 'radio'; ?>" 
														name="sqt_question_<?php echo esc_attr( $item['saved_id'] ?? '' ); ?>" 
														value="<?php echo esc_attr( $index ); ?>"
														<?php echo $is_correct ? 'checked' : ''; ?>
														disabled
													/>
													<span class="sqt-answer-text"><?php echo esc_html( $choice ); ?></span>
													<?php if ( $is_correct ) : ?>
														<span class="sqt-correct-badge"><?php esc_html_e( 'Correct', 'saved-questions-tutor' ); ?></span>
													<?php endif; ?>
												</label>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<!-- Action buttons -->
								<div class="sqt-question-actions">
									<?php if ( ! empty( $item['source_url'] ) ) : ?>
										<a href="<?php echo esc_url( $item['source_url'] ); ?>" class="sqt-view-original-btn" target="_blank">
											<?php esc_html_e( 'View in Quiz', 'saved-questions-tutor' ); ?>
										</a>
									<?php endif; ?>
									<button type="button" class="sqt-remove-btn-dashboard" data-saved-id="<?php echo esc_attr( $item['saved_id'] ?? '' ); ?>">
										<?php esc_html_e( 'Remove', 'saved-questions-tutor' ); ?>
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Create saved questions page if it doesn't exist
	 */
	public function maybe_create_saved_questions_page() {
		// Only run in admin and check once
		if ( ! is_admin() ) {
			return;
		}

		// Check if page already exists
		$page_slug = 'saved-questions';
		$existing_page = get_page_by_path( $page_slug );

		if ( ! $existing_page ) {
			// Create the page
			$page_data = array(
				'post_title'    => __( 'Saved Questions', 'saved-questions-tutor' ),
				'post_content' => '[saved_questions_tab]',
				'post_status'   => 'publish',
				'post_type'     => 'page',
				'post_name'     => $page_slug,
			);

			$page_id = wp_insert_post( $page_data );

			if ( $page_id ) {
				// Store page ID in options
				update_option( 'sqt_saved_questions_page_id', $page_id );
			}
		} else {
			// Update existing page to include shortcode if not present
			$page_id = $existing_page->ID;
			update_option( 'sqt_saved_questions_page_id', $page_id );

			// Check if shortcode exists in content
			if ( ! has_shortcode( $existing_page->post_content, 'saved_questions_tab' ) && 
				 ! has_shortcode( $existing_page->post_content, 'saved_questions' ) ) {
				$updated_content = $existing_page->post_content . "\n\n[saved_questions_tab]";
				wp_update_post( array(
					'ID'           => $page_id,
					'post_content' => $updated_content,
				) );
			}
		}
	}

	/**
	 * Get saved questions page URL
	 *
	 * @return string
	 */
	public function get_saved_questions_page_url() {
		$page_id = get_option( 'sqt_saved_questions_page_id' );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}

		// Fallback to page slug
		$page = get_page_by_path( 'saved-questions' );
		if ( $page ) {
			return get_permalink( $page->ID );
		}

		// Last fallback
		return home_url( '/saved-questions/' );
	}
}
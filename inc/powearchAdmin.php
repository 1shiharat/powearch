<?php
/**
 * WordPress settings API demo class
 *
 * @author Tareq Hasan
 */
if ( ! class_exists( 'powearchAdmin' ) ) :

	class powearchAdmin {

		private $settings_api;

		public function __construct() {
			$this->settings_api = new settingApi();
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
		}

		public function enqueue_script( $hook ){

			if ( $hook !== "settings_page_powearch_setting"){
				return false;
			}
			wp_enqueue_script( 'mousetrap-min-js', plugins_url( 'assets/js/mousetrap.min.js' ), array( 'jquery' ), null , false );
			wp_enqueue_script( 'mousetrap-min-js', plugins_url( 'assets/js/admin.js' ), array( 'jquery' ), null , false );
		}

		public function admin_init() {
			$this->settings_api->set_sections( $this->get_settings_sections() );
			$this->settings_api->set_fields( $this->get_settings_fields() );
			$this->settings_api->admin_init();
		}

		public function admin_menu() {
			add_options_page( __( 'Powearch', 'powearch' ), __( 'Powearch', 'powearch' ), 'delete_posts', 'powearch_setting', array(
				$this,
				'plugin_page'
			) );
		}

		public function get_settings_sections() {
			$sections = array(
				array(
					'id'    => 'powearch_basics',
					'title' => __( 'Basic Settings', 'powearch' )
				),
			);

			return $sections;
		}

		/**
		 * Returns all the settings fields
		 *
		 * @return array settings fields
		 */
		public function get_settings_fields() {

			$settings_fields = array(
				'powearch_basics'   => array(
					array(
						'name'              => 'powearch_type_key',
						'label'             => __( 'Type key', 'powearch' ),
						'desc'              => __( 'Please select the key to type', 'powearch' ),
						'type'              => 'radio',
						'default'           => 1,
						'sanitize_callback' => 'textarea',
						'options' => array(
							'1' => __( 'Shift key + Shift key' ),
							'2'  => __( 'Ctrl key + Ctrl key' ),
							'3'  => __( 'Ctrl key + F key' ),
						)
					),
					array(
						'name'              => 'powearch_post_type',
						'label'             => __( 'Post Type', 'powearch' ),
						'desc'              => __( 'Please select the post type to enable the search', 'powearch' ),
						'type'              => 'post_type',
						'default'           => array( 'post', 'page', 'attachment' ),
					),
					array(
						'name'    => 'powearch_background_color',
						'label'   => __( 'Background Color', 'powearch' ),
						'desc'    => __( 'Please select the background color.', 'powearch' ),
						'type'    => 'color',
						'default' => '#41605b'
					),
					array(
						'name'    => 'powearch_user_select',
						'label'   => __( 'Choose a user', 'powearch' ),
						'desc'    => __( 'Please select the user to enable.', 'powearch' ),
						'type'    => 'user_select',
						'default' => array( get_current_user_id() )
					),
				),
			);

			return $settings_fields;

		}


		public function plugin_page() {
			echo '<div class="wrap"><h1>'. __( 'Powearch Settings', 'powearch' ) . '</h1>';
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
			echo '</div>';
		}

		/**
		 * Get all the pages
		 *
		 * @return array page names with key value pairs
		 */
		public function get_pages() {
			$pages         = get_pages();
			$pages_options = array();
			if ( $pages ) {
				foreach ( $pages as $page ) {
					$pages_options[ $page->ID ] = $page->post_title;
				}
			}

			return $pages_options;
		}
	}
	new powearchAdmin();
endif;

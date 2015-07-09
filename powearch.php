<?php

/*
Plugin Name: Powearch
Plugin URI: http://grow-group.jp/
Description: Is a powerful search plugin for WordPress users.Start by pressing SHIFT key two times.
Author: 1shiharaT
Version: 0.0.1
Author URI: http://grow-group.jp/
Text Domain: powearch
Domain Path: /languages/
*/

class powearch {

	protected $debug_mode = true;

	public $transient_key = 'powearch_cache';

	public $menus = array();

	/**
	 * initialization of class
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'typeahead_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'typeahead_init' ) );
		add_action( 'wp_footer', array( $this, 'tyepahead_search_template' ) );
		add_action( 'admin_footer', array( $this, 'tyepahead_search_template' ) );
		add_action( 'adminmenu', array( $this, 'save_adminmenu' ) );
		add_action( 'wp_ajax_launcher', array( $this, 'get_results' ) );
	}

	/**
	 * 記事を取得
	 *
	 * @param array $args WP_Query の配列
	 *
	 * @return array $posts 投稿の配列
	 */
	protected static function get_posts( $args ) {

		$posts        = array();
		$search_query = new WP_Query( $args );

		if ( $search_query->have_posts() ) {
			while ( $search_query->have_posts() ) {
				$search_query->the_post();
				$post          = get_post( get_the_ID() );
				$post_type_obj = get_post_type_object( $post->post_type );
				$posts[]       = array(
					'value' => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ) . __( ' View', 'powearch' ),
					'link'  => get_the_permalink( $post->ID ),
					'group' => $post_type_obj->labels->name
				);
				$posts[]       = array(
					'value' => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ) . __( ' Edit', 'powearch' ),
					'link'  => admin_url( '/post.php?post=' . $post->ID . '&action=edit' ),
					'group' => $post_type_obj->labels->name
				);
			}
		}

		wp_reset_query();

		return $posts;
	}

	/**
	 * Ajax へリクエストを返す
	 */
	public function get_results() {

		$nonce = ( isset( $_REQUEST['nonce'] ) ) ? $_REQUEST['nonce'] : '';

		if ( ! wp_verify_nonce( $nonce, plugin_basename( __FILE__ ) ) ) {
			wp_send_json( array( 'error' ) );
		}

		$q = ( isset( $_REQUEST['q'] ) ) ? esc_html( $_REQUEST['q'] ) : '';

		$returnMenuObject = array();
		$menus            = get_transient( $this->transient_key );

		if ( ! $q ) {
			wp_send_json( $menus );
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );

		$args = apply_filters( 'powearch_query_settings', array(
			'post_type' => $post_types,
			's'         => $q
		) );

		/**
		 * 投稿を取得
		 */
		$posts = self::get_posts( $args );

		$list = array_merge( $menus, $posts );

		if ( strpos( $q, ' ' ) > 0 ) {
			$search_word = explode( ' ', $q );
		} else {
			$search_word[] = $q;
		}

		foreach ( $list as $l ) {
			foreach ( $search_word as $sword ) {
				$word      = ( $sword ) ? $sword : '';
				$pos       = mb_strpos( $l['value'], $word );
				$group_pos = mb_strpos( $l['group'], $word );
				if ( $pos !== false || $group_pos !== false ) {
					$returnMenuObject[] = $l;
				}
			}
		}

		wp_send_json( $returnMenuObject );

	}

	/**
	 * Registration of assets files
	 * @return void
	 */
	public function typeahead_init() {

		if ( ! is_user_logged_in()
		     && ! is_admin()
		) {
			return false;
		}

		wp_enqueue_style( 'typeahead_init', plugins_url( 'assets/css/powearch.css', __FILE__ ), array(), null, false );

		wp_enqueue_script( 'typeahead_core', plugins_url( 'assets/js/typeahead.bundle.js', __FILE__ ), array(
			'jquery',
			'underscore'
		), null, false );

		wp_enqueue_script( 'typeahead_scripts', plugins_url( 'assets/js/powearch.js', __FILE__ ), array( 'typeahead_core' ), null, false );

		$dataset = array(
			'ajaxurl' => admin_url( '/admin-ajax.php' ),
			'nonce'   => wp_create_nonce( plugin_basename( __FILE__ ) )
		);

		wp_localize_script( 'typeahead_scripts', 'wpaTypeaheadConfig', $dataset );
	}

	/**
	 * 管理メニューを保存
	 */
	public function save_adminmenu() {

		global $menu, $submenu;
		$save_menus = $this->menu_output( $menu, $submenu );

		if ( ( $save_menus && ! get_transient( $this->transient_key ) ) || $this->debug_mode === true ) {
			delete_transient( $this->transient_key );
			set_transient( $this->transient_key, $save_menus );
		}

	}

	/**
	 * typeahead用のHTMLをフッターに出力
	 * @return bool
	 */
	public function tyepahead_search_template() {
		if ( ! is_user_logged_in() && ! is_admin() ) {
			return false;
		}
		?>
		<div id="launcher" class="launcher Typeahead">
			<form id="launcher_form" action="">
				<div class="launcher__form">
					<input class="launcher__input Typeahead-input" id="demo-input" type="text" name="q" placeholder="<?php _e( 'Search for action...' ) ?>">
				</div>
				<input type="submit" style="display: none"/>
			</form>
			<div class="laucher__results Typeahead-menu"></div>
		</div>
	<?php
	}


	/**
	 * メニューを配列として整形
	 *
	 * @param $menu
	 * @param $submenu
	 * @param bool $submenu_as_parent
	 *
	 * @return array|bool
	 */
	function menu_output( $menu, $submenu, $submenu_as_parent = true ) {
		global $self, $parent_file, $submenu_file, $plugin_page, $typenow;
		$menu_array = array();

		$first = true;
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url
		foreach ( $menu as $key => $item ) {
			$admin_is_parent = false;
			$class           = array();
			$aria_attributes = '';
			$is_separator    = false;

			if ( $first ) {
				$class[] = 'wp-first-item';
				$first   = false;
			}

			$submenu_items = array();
			if ( ! empty( $submenu[ $item[2] ] ) ) {
				$class[]       = 'wp-has-submenu';
				$submenu_items = $submenu[ $item[2] ];
			}

			if ( ( $parent_file && $item[2] == $parent_file ) || ( empty( $typenow ) && $self == $item[2] ) ) {
				$class[] = ! empty( $submenu_items ) ? 'wp-has-current-submenu wp-menu-open' : 'current';
			} else {
				$class[] = 'wp-not-current-submenu';
				if ( ! empty( $submenu_items ) ) {
					$aria_attributes .= 'aria-haspopup="true"';
				}
			}

			if ( ! empty( $item[4] ) ) {
				$class[] = esc_attr( $item[4] );
			}

			$class = $class ? ' class="' . join( ' ', $class ) . '"' : '';

			if ( false !== strpos( $class, 'wp-menu-separator' ) ) {
				$is_separator = true;
			}


			$title = wptexturize( $item[0] );


			if ( $is_separator ) {

			} elseif ( $submenu_as_parent && ! empty( $submenu_items ) ) {
				$submenu_items = array_values( $submenu_items );  // Re-index.
				$menu_hook     = get_plugin_page_hook( $submenu_items[0][2], $item[2] );
				$menu_file     = $submenu_items[0][2];
				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}
				if ( ! empty( $menu_hook ) || ( ( 'index.php' != $submenu_items[0][2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
					$admin_is_parent             = true;
					$menu_array[ $key ]['value'] = self::strip_title( $title );
					$menu_array[ $key ]['link']  = admin_url( "/admin.php?page={$submenu_items[0][2]}" );
					$menu_array[ $key ]['group'] = self::strip_title( $title );
				} else {
					$menu_array[ $key ]['value'] = self::strip_title( $title );
					$menu_array[ $key ]['link']  = admin_url( $submenu_items[0][2] );
					$menu_array[ $key ]['group'] = self::strip_title( $title );
				}
			} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
				$menu_hook = get_plugin_page_hook( $item[2], 'admin.php' );
				$menu_file = $item[2];
				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}
				if ( ! empty( $menu_hook ) || ( ( 'index.php' != $item[2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
					$admin_is_parent             = true;
					$menu_array[ $key ]['value'] = self::strip_title( $item[0] );
					$menu_array[ $key ]['link']  = admin_url( "/admin.php?page={$item[2]}" );
					$menu_array[ $key ]['group'] = self::strip_title( $item[0] );
				} else {
					$menu_array[ $key ]['value'] = self::strip_title( $item[0] );
					$menu_array[ $key ]['link']  = admin_url( $item[2] );
					$menu_array[ $key ]['group'] = self::strip_title( $item[0] );
				}
			}

			if ( ! empty( $submenu_items ) ) {

				$first = true;

				// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes
				foreach ( $submenu_items as $sub_key => $sub_item ) {
					if ( ! current_user_can( $sub_item[1] ) ) {
						continue;
					}

					$class = array();
					if ( $first ) {
						$class[] = 'wp-first-item';
						$first   = false;
					}

					$menu_file = $item[2];

					if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
						$menu_file = substr( $menu_file, 0, $pos );
					}

					$self_type = ! empty( $typenow ) ? $self . '?post_type=' . $typenow : 'nothing';

					if ( isset( $submenu_file ) ) {
						if ( $submenu_file == $sub_item[2] ) {
							$class[] = 'current';
						}
					} elseif (
						( ! isset( $plugin_page ) && $self == $sub_item[2] ) ||
						( isset( $plugin_page ) && $plugin_page == $sub_item[2] && ( $item[2] == $self_type || $item[2] == $self || file_exists( $menu_file ) === false ) )
					) {
						$class[] = 'current';
					}

					if ( ! empty( $sub_item[4] ) ) {
						$class[] = esc_attr( $sub_item[4] );
					}


					$menu_hook = get_plugin_page_hook( $sub_item[2], $item[2] );
					$sub_file  = $sub_item[2];
					if ( false !== ( $pos = strpos( $sub_file, '?' ) ) ) {
						$sub_file = substr( $sub_file, 0, $pos );
					}

					$title = wptexturize( $sub_item[0] );

					if ( ! empty( $menu_hook ) || ( ( 'index.php' != $sub_item[2] ) && file_exists( WP_PLUGIN_DIR . "/$sub_file" ) && ! file_exists( ABSPATH . "/wp-admin/$sub_file" ) ) ) {
						if ( ( ! $admin_is_parent && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! is_dir( WP_PLUGIN_DIR . "/{$item[2]}" ) ) || file_exists( $menu_file ) ) {
							$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), $item[2] );
						} else {
							$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), 'admin.php' );
						}
						$sub_item_url = esc_url( $sub_item_url );

						$menu_array[ $key . '_sub_' . $sub_key ]['value'] = self::strip_title( $item[0] ) . ' - ' . self::strip_title( $title );
						$menu_array[ $key . '_sub_' . $sub_key ]['link']  = admin_url( $sub_item_url );
						$menu_array[ $key . '_sub_' . $sub_key ]['group'] = $item[0];

					} else {
						$menu_array[ $key . '_sub_' . $sub_key ]['value'] = self::strip_title( $item[0] ) . ' - ' . self::strip_title( $title );
						$menu_array[ $key . '_sub_' . $sub_key ]['link']  = admin_url( $sub_item[2] );
						$menu_array[ $key . '_sub_' . $sub_key ]['group'] = self::strip_title( $item[0] );
					}
				}
			}
		}

		return $menu_array;
	}

	/**
	 * タイトルからHTMLを排除
	 *
	 * @param string $pre_title
	 *
	 * @return string
	 */
	protected static function strip_title( $pre_title ) {
		$pattern = sprintf( "/<%s.*?>.*?<\/%s>/mis", 'span', 'span' );
		$title   = preg_replace( $pattern, "", $pre_title );

		return strip_tags( $title );
	}

}

$powearch = new powearch();

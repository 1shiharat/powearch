<?php
/*
Plugin Name: Powearch
Plugin URI: http://grow-group.jp/
Description: ランチャー
Author: 1shiharaT
Version: 0.3.3
Author URI: http://grow-group.jp/
Text Domain: powearch
Domain Path: /languages/
*/

class powearch {

	public $menus = array();

	/**
	 * コストラクタ
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'typeahead_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'typeahead_init' ) );
		add_action( 'wp_footer', array( $this, 'tyepahead_search_template' ) );
		add_action( 'admin_footer', array( $this, 'tyepahead_search_template' ) );
		add_action( 'adminmenu', array( $this, 'save_adminmenu' ) );
		add_action( 'wp_ajax_launcher', array( $this, 'launcher' ) );
	}

	/**
	 * ajax end point
	 */
	public function launcher() {
		$nonce = ( isset( $_REQUEST['nonce'] ) ) ? $_REQUEST['nonce'] : '';

		if ( ! wp_verify_nonce( $nonce, __FILE__ ) ) {
			wp_send_json( array( 'error' ) );
		}

		$q = ( isset( $_REQUEST['q'] ) ) ? esc_html( $_REQUEST['q'] ) : '';

		$returnMenuObject = array();
		$menus            = get_transient( 'wpa_menu_object' );
		if ( ! $q ) {
			wp_send_json( $menus );
		}


		$args = apply_filters(  'powearch_query_settings', array(
			'post_type'      => array( 'post', 'page' ),
			's'              => $q,
			'posts_per_page' => 10
		) );

		$posts = get_posts( $args );

		foreach( $posts as $post ){
			$post_type_obj = get_post_type_object( $post->post_type );
			$menus[]       = array(
				'value' => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ) . __( ' View', 'powearch' ),
				'link'  => get_the_permalink( $post->ID ),
				'group' => $post_type_obj->labels->name
			);
			$menus[]       = array(
				'value' => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ) . __( ' Edit', 'powearch' ),
				'link'  => admin_url( '/post.php?post=' .  $post->ID . '&action=edit' ),
				'group' => $post_type_obj->labels->name
			);
		}

		wp_reset_query();

		if ( strpos( $q, ' ' ) > 0 ) {
			$search_word = explode( ' ', $q );
		} else {
			$search_word[] = $q;
		}

		foreach ( $menus as $m ) {
			foreach ( $search_word as $sword ) {
				$pos       = mb_strpos( $m['value'], $sword );
				$group_pos = mb_strpos( $m['group'], $sword );
				if ( $pos !== false || $group_pos !== false ) {
					$returnMenuObject[] = $m;
				}
			}

		}
		wp_send_json( $returnMenuObject );

	}

	/**
	 * 静的ファイルの登録
	 */
	public function typeahead_init() {
		if ( is_user_logged_in() || is_admin() ) {
			wp_enqueue_style( 'typeahead_init', plugins_url( 'assets/css/powearch.css', __FILE__ ) , array(), null, false );
			wp_enqueue_script( 'typeahead_core', plugins_url( 'assets/js/typeahead.bundle.js', __FILE__ ) , array(
				'jquery',
				'underscore'
			), null, false );
			wp_enqueue_script( 'typeahead_scripts', plugins_url( 'assets/js/powearch.js', __FILE__ ), array( 'typeahead_core' ), null, false );

			$dataset = array(
				'ajaxurl' => admin_url( '/admin-ajax.php' ),
				'nonce'   => wp_create_nonce( __FILE__ )
			);
			wp_localize_script( 'typeahead_scripts', 'wpaTypeaheadConfig', $dataset );
		}
	}

	/**
	 * 管理メニューを保存
	 */
	public function save_adminmenu() {

		global $menu, $submenu;
		$save_menus = $this->menu_output( $menu, $submenu );

		if ( $save_menus && ! get_transient( 'wpa_menu_object' ) ) {
			delete_transient( 'wpa_menu_object' );
			set_transient( 'wpa_menu_object', $save_menus );
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
	 * メニューを出力
	 *
	 * @param $menu
	 * @param $submenu
	 * @param bool $submenu_as_parent
	 *
	 * @return array|bool
	 */
	function menu_output( $menu, $submenu, $submenu_as_parent = true ) {
		global $self, $parent_file, $submenu_file, $plugin_page, $typenow;

		$menuArray = array();
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url
		if ( ! is_array( $menu ) ) {
			return false;
		}
		$loop = 0;
		foreach ( $menu as $key => $item ) {
			$menu_name = '';

			if ( ! empty( $submenu[ $item[2] ] ) ) {
				$submenu_items = $submenu[ $item[2] ];
			}


			if ( $submenu_as_parent && ! empty( $submenu_items ) ) {
				$submenu_items = array_values( $submenu_items );  // Re-index.
				$menu_hook     = get_plugin_page_hook( $submenu_items[0][2], $item[2] );
				$menu_file     = $submenu_items[0][2];
				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}
				if ( ! empty( $menu_hook ) || ( ( 'index.php' != $submenu_items[0][2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
					$admin_is_parent            = true;
					$menuArray[ $loop ]['link'] = admin_url( '/admin.php?page=' . $submenu_items[0][2] );
				} else {
					$menuArray[ $loop ]['link'] = admin_url( '/' . $submenu_items[0][2] );

				}
			} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
				$menu_hook = get_plugin_page_hook( $item[2], 'admin.php' );
				$menu_file = $item[2];
				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}
				if ( ! empty( $menu_hook ) || ( ( 'index.php' != $item[2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
					$menuArray[ $loop ]['link'] = admin_url( '/admin.php?page=' . $item[2] );

				} else {
					$menuArray[ $loop ]['link'] = admin_url( '/' . $item[2] );

				}
			}
			$pattern   = sprintf( "/<%s.*?>.*?<\/%s>/mis", 'span', 'span' );
			$menu_name = preg_replace( $pattern, "", $item[0] );
			if ( ! $menu_name ) {
				unset( $menuArray[ $loop ] );
				continue;
			}
			$menuArray[ $loop ]['value'] = strip_tags( $menu_name );
			$menuArray[ $loop ]['group'] = strip_tags( $menu_name );
			$submenu_as_parent           = true;

			if ( isset( $submenu_items ) ) {
				foreach ( $submenu_items as $submenu_key => $sm ) {
					if ( ! empty( $menu_hook ) || ( ( 'index.php' != $sm[2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
						if ( ( file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! is_dir( WP_PLUGIN_DIR . "/{$sm[2]}" ) ) || file_exists( $menu_file ) ) {
							$sub_item_url = add_query_arg( array( 'page' => $sm[2] ), $item[2] );
						} else {
							$sub_item_url = add_query_arg( array( 'page' => $sm[2] ), 'admin.php' );
						}
						$sub_item_url                                        = esc_url( $sub_item_url );
						$menuArray[ $loop . '_sub_' . $submenu_key ]['link'] = $sub_item_url;
					} else {
						$menuArray[ $loop . '_sub_' . $submenu_key ]['link'] = $sm[2];
					}
					$pattern                                              = sprintf( "/<%s.*?>.*?<\/%s>/mis", 'span', 'span' );
					$ret                                                  = preg_replace( $pattern, "", $sm[0] );
					$menuArray[ $loop . '_sub_' . $submenu_key ]['value'] = strip_tags( $menuArray[ $loop ]['value'] . ' - ' . $ret );
					$menuArray[ $loop . '_sub_' . $submenu_key ]['group'] = strip_tags( $menuArray[ $loop ]['value'] );

				}

			}
			$loop ++;

		}

		return $menuArray;
	}

}

$powearch = new powearch();

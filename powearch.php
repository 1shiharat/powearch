<?php
/*
Plugin Name: Powearch
Plugin URI: http://grow-group.jp/
Description: Is a powerful search plugin for WordPress users.Start by pressing SHIFT key two times.
Author: 1shiharaT
Version: 1.0.0
Author URI: http://grow-group.jp/
Text Domain: powearch
Domain Path: /languages/
*/

if ( ! defined( "WPINC" ) ){
	exit();
}

require_once( __DIR__ . '/inc/settingApi.php' );
require_once( __DIR__ . '/inc/powearchAdmin.php' );

class powearch {

	protected $debug_mode = false;

	public $transient_key = 'powearch_cache';

	public $menus = array();

	public $options;

	public $capability = 'administrator';

	/**
	 * initialization of class
	 */
	public function __construct() {

		$this->set_option();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'typeahead_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'typeahead_init' ) );

		add_action( 'wp_footer', array( $this, 'tyepahead_search_template' ) );
		add_action( 'admin_footer', array( $this, 'tyepahead_search_template' ) );

		add_action( 'adminmenu', array( $this, 'save_adminmenu' ) );

		add_action( 'wp_ajax_launcher', array( $this, 'get_results' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'powearch_toolbar' ), 999 );

		add_action( 'activated_plugin', array( $this, 'refresh_transient' ) );
		add_action( 'save_post', array( $this, 'refresh_transient' ) );

	}

	/**
	 * Registration of assets files
	 * @return void
	 */
	public function typeahead_init() {

		if ( ! $this->check_user_capabillity()
		     ||
		     (
			     ! is_user_logged_in()
			     &&
			     ! is_admin()
		     )
		) {
			return false;
		}

		wp_enqueue_style( 'typeahead_init', plugins_url( 'assets/css/powearch.css', __FILE__ ), array(), null, false );

		wp_enqueue_script( 'typeahead_core', plugins_url( 'assets/js/typeahead.bundle.js', __FILE__ ), array(
			'jquery',
			'underscore'
		), null, false );

		wp_enqueue_script( 'typeahead_scripts', plugins_url( 'assets/js/powearch.js', __FILE__ ), array( 'typeahead_core' ), null, false );

		$type_key = isset( $this->options['powearch_type_key'] ) ? $this->options['powearch_type_key'] : '1';

		switch ( $type_key ) {
			case "1" :
				$trigger = array( 16, 16 );
				break;
			case "2" :
				$trigger = array( 17, 17 );
				break;
			case "3" :
				$trigger = array( 17, 70 );
				break;
			default :
				$trigger = array( 16, 16 );
				break;
		}

		$dataset = array(
			'ajaxurl'      => admin_url( '/admin-ajax.php' ),
			'nonce'        => wp_create_nonce( plugin_basename( __FILE__ ) ),
			'emptyMessage' => __( 'Not found', 'powearch' ),
			'template'     => $this->get_template(),
			'trigger_key'  => $trigger,
		);

		wp_localize_script( 'typeahead_scripts', 'powearchConfig', $dataset );
	}

	/**
	 * 翻訳ファイルを登録
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'powearch', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * オプションをセット
	 * @return mixed|void
	 */
	public function set_option() {
		$this->options = get_option( 'powearch_basics' );

		return $this->options;
	}

	/**
	 * ユーザーが管理者権限か判断
	 * @return bool
	 */
	public function check_user_capabillity() {

		$current_user_id = get_current_user_id();

		if ( isset( $this->options['powearch_user_select'] ) && is_array( $this->options['powearch_user_select'] ) ) {
			foreach ( $this->options['powearch_user_select'] as $user_id ) {
				if ( $current_user_id == $user_id ) {
					return true;
				}
			}
		}

		return false;

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
					'value'   => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ),
					'link'    => get_the_permalink( $post->ID ),
					'group'   => $post_type_obj->labels->name,
					'post_id' => $post->ID,
					'operate' => 'view',
				);
				$posts[]       = array(
					'value'   => $post_type_obj->labels->name . ' : ' . get_the_title( $post->ID ),
					'link'    => admin_url( '/post.php?post=' . $post->ID . '&action=edit' ),
					'group'   => $post_type_obj->labels->name,
					'post_id' => $post->ID,
					'operate' => 'edit',
				);
			}
		}

		wp_reset_query();

		return $posts;
	}

	/**
	 * ユーザーを取得
	 * @param $q
	 *
	 * @return array
	 */
	protected static function get_users( $q ) {
		$user_query = new WP_User_Query( array( 'search' => $q, 'search_column' => 'user_login' ) );
		$users      = array();
		$results    = $user_query->get_results();
		if ( ! empty( $results ) ) {
			foreach ( $results as $user ) {
				$users[] = array(
					'value'   => $user->user_login,
					'link'    => admin_url( 'user-edit.php?user_id=' . $user->ID ),
					'group'   => __( 'User', 'powearch' ),
					'post_id' => $user->ID,
				);
			}
		}

		return $users;
	}

	/**
	 * Ajax へリクエストを返す
	 */
	public function get_results() {

		$nonce = ( isset( $_REQUEST['nonce'] ) ) ? $_REQUEST['nonce'] : '';

		if ( ! wp_verify_nonce( $nonce, plugin_basename( __FILE__ ) )
		     ||
		     ! $this->check_user_capabillity()
		) {
			wp_send_json( array( 'error' ) );
		}

		$q = ( isset( $_REQUEST['q'] ) ) ? esc_html( $_REQUEST['q'] ) : '';

		$returnMenuObject = array();
		$menus            = get_transient( $this->transient_key );

		if ( ! $q ) {
			wp_send_json( $menus );
		}

		/**
		 * 投稿を取得
		 */
		$args = apply_filters( 'powearch_query_settings', array(
			'post_type'   => isset( $this->options['powearch_post_type'] ) ? $this->options['powearch_post_type'] : array(),
			'post_status' => array( 'publish', 'inherit' ),
			's'           => $q
		) );

		$posts = self::get_posts( $args );

		$users = self::get_users( $q );

		$list = array_merge( $menus, $posts, $users );

		if ( strpos( $q, ' ' ) > 0 ) {
			$search_word = explode( ' ', $q );
		} else {
			$search_word[] = $q;
		}

		foreach ( $list as $l ) {
			foreach ( $search_word as $sword ) {
				$word      = ( $sword ) ? $sword : '';
				$pos       = mb_stristr( $l['value'], $word );
				$group_pos = mb_stristr( $l['group'], $word );
				if ( $pos !== false || $group_pos !== false ) {
					$returnMenuObject[] = $l;
				}
			}
		}

		wp_send_json( $returnMenuObject );

	}


	/**
	 * Typeahead のテンプレートを取得
	 * @return string
	 */
	public function get_template() {
		$template = <<<EOF
<div class="Typeahead-suggestion Typeahead-selectable">
	<span class="launcher__group"><%= group %></span>
	<strong class="launcher__text"><%= value %></strong>
	<% if ( typeof operate !== "undefined" ) { %>
		<span class="launcher__operate"><%= operate %></span>
	<% } %>
	<% if ( typeof post_id !== "undefined" ) { %>
		<spna class="launcher__id">ID : <%= post_id %></span>
	<% } %>
</div>
EOF;

		return $template;
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
	 * キャッシュデータを削除
	 */
	public function refresh_transient() {
		delete_transient( $this->transient_key );
	}

	/**
	 * typeahead用のHTMLをフッターに出力
	 * @return bool
	 */
	public function tyepahead_search_template() {
		if ( ! $this->check_user_capabillity() || ( ! is_user_logged_in() && ! is_admin() ) ) {
			return false;
		}
		?>
		<style>
			#launcher {
				background: rgba( <?php echo join( ',' , self::hex2rgb( $this->options['powearch_background_color'] ) ) ?>, 0.8);
			}

			#launcher.launcher .Typeahead-suggestion .launcher__group {
				background: rgba( <?php echo join( ',' , self::hex2rgb( $this->options['powearch_background_color'] ) ) ?>, 0.8);
			}
		</style>
		<div id="launcher" class="launcher Typeahead">
			<form id="launcher_form" action="<?php echo admin_url( '/edit.php' ) ?>">
				<div class="launcher__form">
					<input class="launcher__input Typeahead-input" id="demo-input" type="text" name="s" placeholder="<?php _e( 'Enter keyword...' ) ?>">
				</div>
				<input type="submit" style="display: none"/>
			</form>
			<div class="laucher__results Typeahead-menu"></div>
		</div>    <?php
	}

	/**
	 * convert Hex to rgb
	 *
	 * @param $colour
	 *
	 * @return array|bool
	 */
	public static function hex2rgb( $colour ) {
		if ( $colour[0] == '#' ) {
			$colour = substr( $colour, 1 );
		}
		if ( strlen( $colour ) == 6 ) {
			list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
		} elseif ( strlen( $colour ) == 3 ) {
			list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
		} else {
			return false;
		}
		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return array( 'red' => $r, 'green' => $g, 'blue' => $b );
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
		global $self, $parent_file, $typenow;
		$menu_array = array();

		$first = true;
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url
		foreach ( $menu as $key => $item ) {
			$admin_is_parent = false;
			$class           = array();
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

						$menu_array[ $key . '_sub_' . $sub_key ]['value'] = self::strip_title( $title );
						$menu_array[ $key . '_sub_' . $sub_key ]['link']  = admin_url( $sub_item_url );
						$menu_array[ $key . '_sub_' . $sub_key ]['group'] = $item[0];

					} else {
						$menu_array[ $key . '_sub_' . $sub_key ]['value'] = self::strip_title( $title );
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

	public function powearch_toolbar() {
		global $wp_admin_bar;

		$title = sprintf(
			'<span class="dashicons dashicons-search"></span><span class="ab-label">%s</span>',
			'Powearch'
		);
		$wp_admin_bar->add_menu( array(
			'id'    => 'powearch-toolbar',
			'meta'  => array(),
			'title' => $title,
			'href'  => home_url( '/app/' )
		) );
	}


}

$powearch = new powearch();

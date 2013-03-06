<?php
/**
 * Plugin Name: MW Google Maps
 * Plugin URI: http://2inc.org/blog/category/products/wordpress_plugins/mw-google-maps/
 * Description: MW Google Maps adds google maps in your post easy.
 * Version: 1.0.2
 * Author: Takashi Kitajima
 * Author URI: http://2inc.org
 * Text Domain: mw-google-maps
 * Domain Path: /languages/
 * Created: february 25, 2013
 * Modified: March 6, 2013
 * Modified:
 * License: GPL2
 *
 * Copyright 2013 Takashi Kitajima (email : inc@2inc.org)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
class MW_Google_Maps {

	const NAME = 'mw-google-maps';
	const DOMAIN = 'mw-google-maps';
	protected $option;

	/**
	 * __construct
	 * 初期化等
	 */
	public function __construct() {
		load_plugin_textdomain( self::DOMAIN, false, basename( dirname( __FILE__ ) ) . '/languages' );
		// 有効化した時の処理
		register_activation_hook( __FILE__, array( __CLASS__, 'activation' ) );
		// アンインストールした時の処理
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		// 管理画面
		include_once( plugin_dir_path( __FILE__ ) . 'system/mw_google_maps_admin_page.php' );
		$MW_Google_Maps_Admin_Page = new MW_Google_Maps_Admin_Page();

		add_action( 'wp', array( $this, 'init' ) );
	}

	/**
	 * activation
	 * 有効化した時の処理
	 */
	public static function activation() {
	}

	/**
	 * uninstall
	 * アンインストールした時の処理
	 */
	public static function uninstall() {
		delete_post_meta_by_key( '_'.self::NAME );
		delete_option( self::NAME );
	}

	/**
	 * init
	 */
	public function init() {
		$this->option = get_option( self::NAME );
		add_shortcode( 'mw-google-maps', array( $this, 'shortcode_mw_google_maps' ) );
		add_shortcode( 'mw-google-maps-multi', array( $this, 'shortcode_mw_google_maps_multi' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
	}

	/**
	 * wp_enqueue_scripts
	 */
	public function wp_enqueue_scripts() {
		$url = plugin_dir_url( __FILE__ );
		wp_register_script(
			'googlemaps-api',
			'http://maps.google.com/maps/api/js?sensor=false',
			array(),
			'',
			true
		);
		wp_register_script(
			'jquery.mw-google-maps',
			$url.'js/jquery.mw-google-maps.js',
			array( 'jquery', 'googlemaps-api' ),
			'1.0',
			true
		);
		wp_enqueue_script( 'jquery.mw-google-maps' );
		wp_register_style( self::DOMAIN, $url.'css/style.css' );
		wp_enqueue_style( self::DOMAIN );
	}

	/**
	 * shortcode_mw_google_maps
	 * @param	$atts
	 * @return	HTML map
	 */
	public function shortcode_mw_google_maps( $atts ) {
		global $post;
		if ( empty( $post->ID ) )
			return;

		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
		), $atts );

		return $this->shortcode_mw_google_maps_multi( array(
			'key' => self::NAME.'-map-'.$atts['id'],
			'ids' => $atts['id'],
		) );
	}

	/**
	 * shortcode_mw_google_maps_multi
	 * @param	$atts
	 * @return	HTML map
	 */
	public function shortcode_mw_google_maps_multi( $atts ) {
		global $wp_query, $post;

		$atts = shortcode_atts( array(
			'key' => self::NAME.'-map-multi',
			'ids' => '',
		), $atts );
		if ( !empty( $ids ) ) {
			$ids = explode( ',', $atts['ids'] );
			$_posts = get_posts( array(
				'post__in'  => $ids,
				'post_type' => 'any',
				'posts_per_page' => -1,
			) );
		} else {
			$_posts = $wp_query->posts;
		}

		foreach ( $_posts as $post ) {
			setup_postdata( $post );
			$post_meta = get_post_meta( $post->ID, '_'.self::NAME, true );
			if ( empty( $this->option['post_types'] ) ||
				!is_array( $this->option['post_types'] ) ||
				!in_array( get_post_type(), $this->option['post_types'] ) ||
				empty( $post_meta ) )
				continue;
			$points[] = array(
				'latitude'  => $post_meta['latitude'],
				'longitude' => $post_meta['longitude'],
				'title'     => '<a href="'.get_permalink().'">'.get_the_title().'</a>',
			);
		}
		wp_reset_postdata();
		if ( empty( $points ) )
			return;

		foreach ( $points as $point ) {
			$addMarker[] = "
				gmap.mw_google_maps( 'addMarker', {
					latitude : ".esc_html( $point['latitude'] ).",
					longitude: ".esc_html( $point['longitude'] ).",
					title    : '".$point['title']."'
				} );
			";
		}
		$_ret = sprintf( '
			<script type="text/javascript">
			jQuery( function( $ ) {
				var gmap = $( "#%s" ).mw_google_maps();
				%s
				gmap.mw_google_maps( "render" );
			} );
			</script>
			<div id="%s" class="%s"></div>
			',
			$atts['key'], implode( '', $addMarker ), $atts['key'], self::NAME.'-map'
		);
		return $_ret;
	}
}

// オブジェクト化（プラグイン実行）
$MW_Google_Maps = new MW_Google_Maps();

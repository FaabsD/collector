<?php

/*
Plugin Name: Collector
Plugin URI: https://fd-hendriks.nl
Description: A brief description of the Plugin.
Version: 1.0
Author: Fabian
Author URI: https://fd-hendriks.nl
License: A "Slug" license name e.g. GPL2
*/

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . "wp-admin/includes/plugin.php" );
}

class CollectorPlugin {

	public $pluginData;
	public $pluginUri;
	public $pluginTextDomain;

	public function __construct() {
		$this->pluginData       = get_plugin_data( __FILE__ );
		$this->pluginUri        = $this->pluginData['PluginURI'];
		$this->pluginTextDomain = $this->pluginData['TextDomain'];

		add_action( 'init', array( $this, 'collectorSetup' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function collectorSetup() {
		$this->registerPosttypes();

	}

	private function registerPosttypes() {
		register_post_type( 'collection', array(
			'labels'       => array(
				'name'               => __( 'Collection', $this->pluginTextDomain ),
				'singular_name'      => __( 'Collection', $this->pluginTextDomain ),
				'add_new'            => __( 'Add Collection item', $this->pluginTextDomain ),
				'add_new_item'       => __( 'Add new Collection item', $this->pluginTextDomain ),
				'all_items'          => __( 'All Collection items', $this->pluginTextDomain ),
				'edit_item'          => __( 'Edit Collection item', $this->pluginTextDomain ),
				'name_admin_bar'     => __( 'Collection', $this->pluginTextDomain ),
				'menu_name'          => __( 'Collection', $this->pluginTextDomain ),
				'new_item'           => __( 'New Collection Item', $this->pluginTextDomain ),
				'not_found'          => __( 'No Collection items found', $this->pluginTextDomain ),
				'not_found_in_trash' => __( 'No Collection items found in trash', $this->pluginTextDomain ),
				'search_items'       => __( 'Search Collection items', $this->pluginTextDomain ),
				'view_item'          => __( 'View Collection item', $this->pluginTextDomain ),
			),
			'public'       => true,
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-media-archive',
			'rewrite'      => array( 'with_front' => true ),
			'supports'     => array( 'title', 'editor', 'excerpt' ),
			'show_in_rest' => true,
		) );
	}

	private function unregisterPosttypes( array $posttypes ) {
		foreach ( $posttypes as $posttype ) {
			unregister_post_type( $posttype );
		}
	}

	public function activate() {
		$this->registerPosttypes();
		flush_rewrite_rules();
	}

	public function deactivate() {
		$this->unregisterPosttypes( array( 'collection' ) );
		flush_rewrite_rules();
	}

}

new CollectorPlugin();
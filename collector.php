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

	public array $pluginData;
	public string $pluginUri;
	public mixed $pluginTextDomain;

	private array $defaultSettings;

	public function __construct() {
		$this->pluginData       = get_plugin_data( __FILE__ );
		$this->pluginUri        = $this->pluginData['PluginURI'];
		$this->pluginTextDomain = $this->pluginData['TextDomain'];
		$this->defaultSettings  = array(
			'open_to_rest' => 0,
		);

		add_action( 'init', array( $this, 'collectorSetup' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function collectorSetup(): void {
		$this->registerPosttypes();
		add_action( 'admin_init', [ $this, 'options' ] );
		$this->settingsPage();

	}

	private function registerPosttypes(): void {
		$options = get_option( 'collector_options', $this->defaultSettings );
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
			'show_in_rest' => ( $options['open_to_rest'] === 1 ) ? true : false,
		) );
	}

	private function unregisterPosttypes( array $posttypes ): void {
		foreach ( $posttypes as $posttype ) {
			unregister_post_type( $posttype );
		}
	}

	/**
	 *
	 * @return void
	 */
	private function settingsPage(): void {
		add_options_page( 'Collection Settings', 'Collection Settings', 'manage_options', 'collection_options', function () {
			echo '<h2>FD Projects Settings</h2>';
			echo '<form action="options.php" method="post">';
			settings_fields( 'collector_options' );
			do_settings_sections( 'Collector' );
			echo '<input type="submit" value="' . esc_attr( 'Save' ) . '" name="submit" class="button button-primary">';
			echo '</form>';
		} );
	}

	public function options(): void {
		$fields = array(
			array(
				'field_id'       => 'open_to_rest',
				'field_text'     => 'Show in Rest API',
				'field_callback' => 'renderCheckbox',
				'page'           => 'Collector',
				'section'        => 'collector_general_settings',
			),
		);

		register_setting(
			'collector_options',
			'collector_options',
			[ $this, 'validateCollectorSettings' ] );
		add_settings_section(
			'collector_general_settings',
			'General settings',
			[ $this, 'generalSettingsText' ],
			'Collector'
		);

		// add fields
		foreach ( $fields as $field ) {
			add_settings_field(
				$field['field_id'],
				__( $field['field_text'], $this->pluginTextDomain ),
				[ $this, $field['field_callback'] ],
				$field['page'],
				$field['section'],
				array( 'field_id' => $field['field_id'] )
			);
		}

	}

	public function validateCollectorSettings( $input ): array {
		$output['open_to_rest'] = absint( $input['open_to_rest'] );

		return $output;
	}

	public function generalSettingsText(): void {
		echo '<p>From here you can set the general options</p>';
	}

	public function renderCheckbox( $args ): void {
		if ( defined( 'WP_DEBUG' ) ) {
			error_log( '======== DEBUG LOG FOR RENDERING CHECKBOXES ON SETTINGS PAGE FOR COLLECTOR PLUGIN ========' );
			error_log( print_r( $args, true ) );
		}

		$options = get_option( 'collector_options', $this->defaultSettings );

		$html = '<input type="checkbox" name="collector_options[' . $args['field_id'] . ']"' . checked( $options[ $args['field_id'] ], 1, false ) . ' value="1"/>';

		echo $html;

	}

	public function activate(): void {
		$this->registerPosttypes();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		$this->unregisterPosttypes( array( 'collection' ) );
		flush_rewrite_rules();
	}

}

new CollectorPlugin();
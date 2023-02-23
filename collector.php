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

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Carbon_Fields\Field\Complex_Field;

class CollectorPlugin {

	public array $pluginData;
	public string $pluginUri;
	public mixed $pluginTextDomain;

	private array $defaultSettings;

	public function __construct() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . "wp-admin/includes/plugin.php" );
		}
		require_once 'CollectionField.php';

		$this->pluginData       = get_plugin_data( __FILE__ );
		$this->pluginUri        = $this->pluginData['PluginURI'];
		$this->pluginTextDomain = $this->pluginData['TextDomain'];
		$this->defaultSettings  = array(
			'open_to_rest' => 0,
		);

		add_action( 'after_setup_theme', array( $this, 'loadCarbonFields' ) );
		add_action( 'init', array( $this, 'collectorSetup' ) );
		add_action( 'carbon_fields_register_fields', array( $this, 'registerCarbonFields' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Handle the Setup for the Collector plugin
	 *
	 * @return void
	 */
	public function collectorSetup(): void {
		$this->registerPosttypes();
		$this->registerRestFields();
		add_action( 'admin_init', [ $this, 'options' ] );
		// $this->settingsPage();

	}

	/**
	 * register Custom Post-types
	 *
	 * @return void
	 */
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

	/**
	 * Used to render all the specified post-types
	 *
	 * @param array $posttypes
	 *
	 * @return void
	 */
	private function unregisterPosttypes( array $posttypes ): void {
		foreach ( $posttypes as $posttype ) {
			unregister_post_type( $posttype );
		}
	}

	/**
	 * Render a settings page for the collector plugin
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

	/**
	 * Handles setting up the options for the collector plugin
	 *
	 * @return void
	 */
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

	/**
	 * render a checkbox on the settings page
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function renderCheckbox( $args ): void {
		if ( defined( 'WP_DEBUG' ) ) {
			error_log( '======== DEBUG LOG FOR RENDERING CHECKBOXES ON SETTINGS PAGE FOR COLLECTOR PLUGIN ========' );
			error_log( print_r( $args, true ) );
		}

		$options = get_option( 'collector_options', $this->defaultSettings );

		$html = '<input type="checkbox" name="collector_options[' . $args['field_id'] . ']"' . checked( $options[ $args['field_id'] ], 1, false ) . ' value="1"/>';

		echo $html;

	}

	/**
	 * Register REST API Fields
	 * @return void
	 */
	private function registerRestFields(): void {
		register_rest_field( 'collection', 'meta', [
			'get_callback' => function ( $data ) {
				return get_post_meta( $data['id'], '', '' );
			},
		] );
	}

	public function loadCarbonFields(): void {
		require_once 'vendor/autoload.php';
		\Carbon_Fields\Carbon_Fields::boot();
	}

	public function registerCarbonFields(): void {
		Container::make( 'theme_options', __( 'Collector Settings', $this->pluginTextDomain ) )
		         ->add_tab( 'general', array(
			         // Start custom icon settings
			         Field::make( 'separator', 'collector_collection_separator', __( 'Collection Icon Options', $this->pluginTextDomain ) ),

			         Field::make( 'select', 'collector_collection-icon', __( 'Choose Collection Icon', $this->pluginTextDomain ) )
			              ->set_options( array(
				              'dashicons-archive' => __( 'Archive Icon', $this->pluginTextDomain ),
				              'dashicons-book'    => __( 'Book/Album Icon', $this->pluginTextDomain ),
				              'custom-icon'       => __( 'Custom Icon', $this->pluginTextDomain ),
			              ) )
			              ->set_visible_in_rest_api( false )
			              ->set_help_text( __( 'Choose which icon you want the collection to use in the dashboard screen', $this->pluginTextDomain ) )
			              ->set_default_value( 'dashicons-archive' ),

			         Field::make( 'select', 'collector_choose-own-icon', __( 'Use your own icon?', $this->pluginTextDomain ) )
			              ->set_options( array(
				              'yes' => __( 'Yes', $this->pluginTextDomain ),
				              'no'  => __( 'No', $this->pluginTextDomain ),
			              ) )
			              ->set_visible_in_rest_api( false )
			              ->set_help_text( __( "Do you want to use your own icon instead of the plugin's one", $this->pluginTextDomain ) )
			              ->set_default_value( 'no' )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => 'collector_collection-icon',
					              'value'   => 'custom-icon',
					              'compare' => '=',
				              ),
			              ) ),

			         Field::make( 'media_gallery', 'collector_custom-icon', __( 'Upload/Choose custom icon', $this->pluginTextDomain ) )
			              ->set_type( 'image' )
			              ->set_duplicates_allowed( false )
			              ->set_help_text( __( 'Upload an icon in png format to use for the collection or select an existing one', $this->pluginTextDomain ) )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => 'collector_choose-own-icon',
					              'value'   => 'yes',
					              'compare' => '=',
				              ),
			              ) ),

			         // Start simple category options
			         Field::make( 'separator', 'collector_category_separator', __( 'Category Options', $this->pluginTextDomain ) ),

			         Field::make( 'select', 'collector_use_categories', __( 'Use Categories', $this->pluginTextDomain ) )
			              ->set_options( array(
				              'yes' => __( 'Yes', $this->pluginTextDomain ),
				              'no'  => __( 'No', $this->pluginTextDomain ),
			              ) )
			              ->set_default_value( 'yes' )
			              ->set_help_text( __( 'Do you want to categorize your collection(s)?', $this->pluginTextDomain ) )
			              ->set_visible_in_rest_api( false ),

			         Field::make( 'checkbox', 'collector_use_city_category', __( 'Use a "Cities" category', $this->pluginTextDomain ) )->set_visible_in_rest_api( false )
			              ->set_help_text( __( 'Check this if you want to categorize your collection items by city of origin', $this->pluginTextDomain ) )
			              ->set_default_value( 'no' )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => 'collector_use_categories',
					              'value'   => 'yes',
					              'compare' => '=',
				              ),
			              ) ),


		         ) )
		         ->add_tab( 'Advanced Category Options', array(

			         Field::make( 'separator', 'collector_advanced_category_setting_separator', __( 'Advanced Settings for using Categories', $this->pluginTextDomain ) )
			              ->set_help_text( __( 'Use the following settings only if you enabled category usage in the general settings tab', $this->pluginTextDomain ) ),

			         Field::make( 'checkbox', 'collector_add_custom_categories', __( 'Use your own categories besides collection type and City', $this->pluginTextDomain ) )
			              ->set_help_text( __( 'Check this if you want to add your own categories to your collections', $this->pluginTextDomain ) )
			              ->set_default_value( false )
			              ->set_visible_in_rest_api( false ),

			         Field::make( 'separator', 'collector_advanced_category_settings_custom_categories_separator', __( 'Add Categories', $this->pluginTextDomain ) )
			              ->set_help_text( __( 'From here you can add your own categories', $this->pluginTextDomain ) )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => 'collector_add_custom_categories',
					              'value'   => true,
					              'compare' => '=',
				              ),
			              ) ),

			         Field::make( 'complex', 'collector_collection_custom_category', __( 'Custom Category', $this->pluginTextDomain ) )
			              ->setup_labels( array(
				              'plural_name'   => __( 'Custom categories', $this->pluginTextDomain ),
				              'singular_name' => __( 'Custom category', $this->pluginTextDomain ),
			              ) )
			              ->add_fields( array(
				              Field::make( 'text', 'name' )
				                   ->set_help_text( __( 'The name of your custom category in plural form', $this->pluginTextDomain ) ),
				              Field::make( 'text', 'singular_name' )
				                   ->set_help_text( __( 'The name of your custom category in singular form', $this->pluginTextDomain ) ),
				              Field::make( 'checkbox', 'hierarchical' )
				                   ->set_help_text( __( 'Do you want to use sub categories for your custom category?', $this->pluginTextDomain ) )
				                   ->set_default_value( true ),
			              ) )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => 'collector_add_custom_categories',
					              'value'   => true,
					              'compare' => '=',
				              ),
			              ) ),
		         ) )
		         ->add_tab( 'Developer options', array(
			         Field::make( 'checkbox', 'collector_show_in_rest', 'Show in Rest API' )
			              ->set_visible_in_rest_api( false )
			              ->set_help_text( __( 'Open your collection to the REST API for usage in external apps' ) )
			              ->set_default_value( 'no' ),
		         ) );
	}

	/**
	 * Fires when the plugin activates
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->registerPosttypes();
		flush_rewrite_rules();
	}

	/**
	 * Fires when the plugin is deactivated
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->unregisterPosttypes( array( 'collection' ) );
		flush_rewrite_rules();
	}

}

new CollectorPlugin();
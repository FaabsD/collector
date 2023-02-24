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

		if ( gettype( carbon_get_theme_option( 'collector_use_categories' ) ) === "string" && carbon_get_theme_option( 'collector_use_categories' ) === "yes" ) {
			$this->registerTaxonomies();
		}

		// Add filters
		add_filter( 'use_block_editor_for_post_type', [ $this, 'disableGutenBergForCollection' ], 10, 2 );

	}

	/**
	 * register Custom Post-types
	 *
	 * @return void
	 */
	private function registerPosttypes(): void {

		$userIconPreference = $this->option( 'collector_collection-icon' );
		$openToRest         = $this->option( 'collector_show_in_rest' );

		if ( defined( 'WP_DEBUG' ) ) {
			error_log( '======== GET THE USER DEFINED ICON FOR COLLECTION ======' );
			error_log( gettype( $userIconPreference ) );
			error_log( carbon_get_theme_option( 'collector_collection-icon' ) );

			error_log( '======== IS COLLECTION OPEN TO REST? ========' );
			error_log( gettype( $openToRest ) );
			error_log( $openToRest );
		}

		$labels   = array(
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
		);
		$menuIcon = 'dashicons-media-archive';

		// check
		if ( gettype( $userIconPreference ) === "string" ) {
			if ( $userIconPreference === "custom-icon" ) {
				$menuIcon = plugin_dir_url( __FILE__ ) . "assets/collection-icon.svg";
			} elseif ( $userIconPreference === "dashicons-archive" || $userIconPreference === "dashicons-book" ) {
				$menuIcon = $userIconPreference;
			} else {
				$menuIcon = "dashicons-archive";
			}
		}

		register_post_type( 'collection', array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'menu_icon'    => $menuIcon,
			'rewrite'      => array( 'with_front' => true ),
			'supports'     => array( 'title', 'editor', 'excerpt' ),
			'show_in_rest' => ( gettype( $openToRest ) === "boolean" && $openToRest ) ? $openToRest : false,
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

	private function registerTaxonomies(): void {
		$collectionTypeArgs = array(
			'labels'       => array(
				'name'              => __( 'Collection-types', $this->pluginTextDomain ),
				'singular_name'     => __( 'Collection-type', $this->pluginTextDomain ),
				'search_items'      => __( 'Search Collection-types', $this->pluginTextDomain ),
				'all_items'         => __( 'All Collection-types', $this->pluginTextDomain ),
				'parent_item'       => __( 'Parent Collection-type', $this->pluginTextDomain ),
				'parent_item_colon' => __( 'Parent Collection-type:', $this->pluginTextDomain ),
				'edit_item'         => __( 'Edit Collection-type', $this->pluginTextDomain ),
				'update_item'       => __( 'Update Collection-type', $this->pluginTextDomain ),
				'add_new_item'      => __( 'Add New Collection-type', $this->pluginTextDomain ),
				'new_item_name'     => __( 'New Collection-type Name', $this->pluginTextDomain ),
				'menu_name'         => __( 'Collection-types', $this->pluginTextDomain ),
			),
			'description'  => __( 'To distinguish your different kinds of collections, You can add a type here', $this->pluginTextDomain ),
			'public'       => true,
			'hierarchical' => true,
			'show_in_rest' => ( gettype( $this->option( 'collector_show_in_rest' ) ) === 'boolean' && $this->option( 'collector_show_in_rest' ) ) ? $this->option( 'collector_show_in_rest' ) : false,
		);
		register_taxonomy( 'collection_type', 'collection', $collectionTypeArgs );

		if ( gettype( $this->option( 'collector_use_city_category' ) ) === 'boolean' && $this->option( 'collector_use_city_category' ) ) {
			$collectionCityArgs = array(
				'labels'       => array(
					'name'              => __( 'Cities', $this->pluginTextDomain ),
					'singular_name'     => __( 'City', $this->pluginTextDomain ),
					'search_items'      => __( 'Search Cities', $this->pluginTextDomain ),
					'all_items'         => __( 'All Cities', $this->pluginTextDomain ),
					'parent_item'       => __( 'Parent City', $this->pluginTextDomain ),
					'parent_item_colon' => __( 'Parent City:', $this->pluginTextDomain ),
					'edit_item'         => __( 'Edit City', $this->pluginTextDomain ),
					'update_item'       => __( 'Update City', $this->pluginTextDomain ),
					'add_new_item'      => __( 'Add New City', $this->pluginTextDomain ),
					'new_item_name'     => __( 'New City', $this->pluginTextDomain ),
					'menu_name'         => __( 'Cities', $this->pluginTextDomain ),
				),
				'description'  => __( 'If you want to show the city/place of origin for your collection item, you can add it here', $this->pluginTextDomain ),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => ( gettype( $this->option( 'collector_show_in_rest' ) ) === 'boolean' && $this->option( 'collector_show_in_rest' ) ) ? $this->option( 'collector_show_in_rest' ) : false,
			);

			register_taxonomy( 'collection_city', 'collection', $collectionCityArgs );
		}

		// check if user chose to use his/her own custom categories
		if ( gettype( $this->option( 'collector_add_custom_categories' ) ) === 'boolean' && $this->option( 'collector_add_custom_categories' ) ) {
			// add the user's own custom categories if present
			if ( gettype( $this->option( 'collector_collection_custom_category' ) ) === 'array' ) {
				$usersCategories = $this->option( 'collector_collection_custom_category' );

				foreach ( $usersCategories as $index => $userCategory ) {
					$category     = $userCategory['category'];
					$pluralName   = $userCategory['name'];
					$singularName = $userCategory['singular_name'];
					$hierarchical = $userCategory['hierarchical'];
					$description  = $userCategory['description'];

					$userCategoryArgs = array(
						'labels'       => array(
							'name'              => __( $pluralName, $this->pluginTextDomain ),
							'singular_name'     => __( $singularName, $this->pluginTextDomain ),
							'search_items'      => __( 'Search ' . $pluralName, $this->pluginTextDomain ),
							'all_items'         => __( 'All ' . $pluralName, $this->pluginTextDomain ),
							'parent_item'       => __( 'Parent ' . $singularName, $this->pluginTextDomain ),
							'parent_item_colon' => __( 'Parent ' . $singularName . ':', $this->pluginTextDomain ),
							'edit_item'         => __( 'Edit ' . $singularName, $this->pluginTextDomain ),
							'update_item'       => __( 'Update ' . $singularName, $this->pluginTextDomain ),
							'add_new_item'      => __( 'Add New ' . $singularName, $this->pluginTextDomain ),
							'new_item_name'     => __( 'New ' . $singularName . 'Name', $this->pluginTextDomain ),
							'menu_name'         => __( $pluralName, $this->pluginTextDomain ),
						),
						'description'  => __( $description, $this->pluginTextDomain ),
						'public'       => true,
						'hierarchical' => $hierarchical,
						'show_in_rest' => ( gettype( $this->option( 'collector_show_in_rest' ) ) === 'boolean' && $this->option( 'collector_show_in_rest' ) ) ? $this->option( 'collector_show_in_rest' ) : false,

					);
					register_taxonomy( $category, 'collection', $userCategoryArgs );
				}
			}
		}
	}

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
		$mainContainer = Container::make( 'theme_options', __( 'Collector Settings', $this->pluginTextDomain ) )
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
				                               Field::make( 'text', 'category' )
				                                    ->set_help_text( __( 'The name of your category in lowercase separated by underscores(_)', $this->pluginTextDomain ) ),
				                               Field::make( 'text', 'name' )
				                                    ->set_help_text( __( 'The name of your custom category in plural form (in the menu)', $this->pluginTextDomain ) ),
				                               Field::make( 'text', 'singular_name' )
				                                    ->set_help_text( __( 'The name of your custom category in singular form (in the menu)', $this->pluginTextDomain ) ),
				                               Field::make( 'textarea', 'description' )
				                                    ->set_help_text( __( 'Set an description for your category', $this->pluginTextDomain ) ),
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
		// add a sub settings field for adding custom fields to the collection

		$customFieldsSettings = Container::make( 'theme_options', __( 'Custom Field Groups', $this->pluginTextDomain ) )
		                                 ->set_page_parent( $mainContainer )
		                                 ->add_fields( array(
			                                 Field::make( 'separator', 'collector_collection_custom_field_groups_separator', __( 'Add Custom fields', $this->pluginTextDomain ) )
			                                      ->set_help_text( __( 'From here you can add extra field groups with custom fields to your collection for more information as you see fit', $this->pluginTextDomain ) ),
			                                 Field::make( 'complex', 'collector_collection_custom_field_groups', __( 'Custom Field Groups', $this->pluginTextDomain ) )
			                                      ->set_duplicate_groups_allowed( false )
			                                      ->setup_labels( array(
				                                      'plural_name'   => __( 'Custom Field Groups', $this->pluginTextDomain ),
				                                      'singular_name' => __( 'Custom Field Group', $this->pluginTextDomain ),
			                                      ) )->add_fields( array(
					                                 Field::make( 'text', 'group_name', __( 'Field group name' ) )
					                                      ->set_help_text( __( 'The name of the group in which these custom fields will reside', $this->pluginTextDomain ) )
					                                      ->set_default_value( 'Custom data' ),

				                                 ) ),
		                                 ) );
	}

	public function disableGutenBergForCollection( $current_status, $post_type ): mixed {
		$disabledPostTypes = array( 'collection' );

		if ( in_array( $post_type, $disabledPostTypes, true ) ) {
			$current_status = false;
		}

		return $current_status;
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

	/**
	 * Get plugin options in a short way
	 *
	 * @param $optionName
	 *
	 * @return mixed|null
	 */
	private function option( $optionName ): mixed {
		return carbon_get_theme_option( $optionName );
	}

}

new CollectorPlugin();
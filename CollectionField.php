<?php

class CollectionField {
	protected string $metaBoxId;
	protected string $metaBoxTitle;
	protected string $metaBoxName;

	protected string $metaBoxType;
	protected string $screen;

	public function __construct( string $metaBoxId, string $metaBoxTitle, string $metaBoxName, string $metaBoxType = 'text', string $screen = 'collection' ) {

		$this->metaBoxId    = $metaBoxId;
		$this->metaBoxTitle = $metaBoxTitle;
		$this->metaBoxName  = $metaBoxName;
		$this->metaBoxType  = $metaBoxType;
		$this->screen       = $screen;

		add_action( 'add_meta_boxes', [ $this, 'addMetaBox' ] );
		add_action( 'save_post', [ $this, 'saveMetaValue' ] );

	}

	public function addMetaBox( $args ): void {
		if ( defined( 'WP_DEBUG' ) ) {
			error_log( '======== START DEBUGGING METABOX ADDITION ========' );
			error_log( '===== ARGUMENTS =====' );
			error_log( print_r( $args, true ) );
		}
		add_meta_box(
			$this->metaBoxId,
			$this->metaBoxTitle, [ $this, 'createInput', ],
			$this->screen,
			'advanced',
			'high',
			[ 'input_type' => $this->metaBoxType ] );
	}

	public function createInput( $post ): void {
		$collectionMetaValue = get_post_meta( $post->ID, $this->metaBoxName, true );
		switch ( $this->metaBoxType ) {
			case 'text':
				echo '<input type="text" name="' . $this->metaBoxName . '" value="' . esc_attr( $collectionMetaValue ) . '">';
				break;
			case 'url':
				echo '<input type="url" name="' . $this->metaBoxName . '" value="' . esc_url( $collectionMetaValue ) . '">';
				break;
			case 'checkbox':
				echo '<input type="checkbox" name="' . $this->metaBoxName . '" value="1" ' . checked( $collectionMetaValue, 1, false ) . '>';
				break;
		}

	}

	public function saveMetaValue( $post_id ): void {
		if ( isset( $_POST[ $this->metaBoxName ] ) ) {
			update_post_meta( $post_id, $this->metaBoxName, $_POST[ $this->metaBoxName ] );
		}
	}

}
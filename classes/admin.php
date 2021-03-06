<?php
/**
 * Caldera Forms.
 *
 * @package   Caldera_Forms
 * @author    David <david@digilab.co.za>
 * @license   GPL-2.0+
 * @link      
 * @copyright 2014 David Cramer
 */

/**
 * Caldera_Forms Plugin class.
 * @package Caldera_Forms
 * @author  David Cramer <david@digilab.co.za>
 */

class Caldera_Forms_Admin {

	/**
	 * @var     string
	 */
	const VERSION = CFCORE_VER;

	/**
	 * @var      string
	 */
	protected $plugin_slug = 'caldera-forms';

	/**
	 * @var      string
	 */
	protected $screen_prefix = array();

	/**
	 * @var      string
	 */
	protected $sub_prefix = null;

	/**
	 * @var      string
	 */
	protected $addons = array();

	/**
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Holds admin notices
	 *
	 * @since 1.3.0
	 *
	 * @var array
	 */
	private static $admin_notices;

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 *
	 */
	private function __construct() {

		add_filter( 'all_plugins', array( $this, 'prepare_filter_addons' ) );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Add Admin menu page
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 9 );
		
		// Add admin scritps and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_stylescripts' ) );

		// add element & fields filters
		add_filter('caldera_forms_get_panel_extensions', array( $this, 'get_panel_extensions'), 1);
		add_filter('caldera_forms_entry_viewer_buttons', array( $this, 'set_viewer_buttons'),10, 4);
		add_filter('caldera_forms_entry_editor_buttons', array( $this, 'set_editor_buttons'),10, 4);
		
		// action
		add_action('caldera_forms_entry_actions', array( $this, 'get_entry_actions'),1);
		add_action('caldera_forms_admin_templates', array( $this, 'get_admin_templates'),1);
		add_action('caldera_forms_entry_meta_templates', array( $this, 'get_admin_meta_templates'),1);

		add_action( 'init', array( $this, 'save_form') );
		add_action( 'media_buttons', array($this, 'shortcode_insert_button' ), 11 );
		add_filter( 'wp_fullscreen_buttons', array($this, 'shortcode_insert_button_fs' ), 11 );


		if( current_user_can( Caldera_Forms::get_manage_cap( 'create' ) ) ){
			// create forms
			add_action("wp_ajax_create_form", array( $this, 'create_form') );
		}

		add_action("wp_ajax_toggle_form_state", array( $this, 'toggle_form_state') );
		add_action("wp_ajax_browse_entries", array( $this, 'browse_entries') );		
		add_action("wp_ajax_save_cf_setting", array( $this, 'save_cf_setting') );
		add_action("wp_ajax_cf_dismiss_pointer", array( $this, 'update_pointer') );
		add_action("wp_ajax_cf_bulk_action", array( $this, 'bulk_action') );
		add_action("wp_ajax_cf_get_form_preview", array( $this, 'get_form_preview') );
		
		add_action( 'caldera_forms_admin_footer', array( $this, 'admin_alerts' ) );
		add_action( 'admin_footer', array( $this, 'add_shortcode_inserter'));


		$this->addons = apply_filters( 'caldera_forms_get_active_addons', array() );


		add_action('admin_footer-edit.php', array( $this, 'render_editor_template')); // Fired on the page with the posts table
		add_action('admin_footer-post.php', array( $this, 'render_editor_template')); // Fired on post edit page
		add_action('admin_footer-post-new.php', array( $this, 'render_editor_template')); // Fired on add new post page		

		add_action( 'caldera_forms_new_form_template_end', array( $this, 'load_new_form_templates') );

		add_action( 'admin_init', array( $this, 'watch_tracking' ) );

		/**
		 * Runs after Caldera Forms admin is initialized
		 *
		 * @since 1.3.6
		 */
		do_action( 'caldera_forms_admin_init' );

		/** Adding anything to this constructor after caldera_forms_admin_init action is a violation of intergalactic law */
	}

	public function render_editor_template(){
		?>
	<script type="text/html" id="tmpl-editor-caldera-forms">
		<# if ( data.html ) { #>
			{{{ data.html }}}
		<# } else { #>
			<div class="wpview-error">
				<div class="dashicons dashicons-cf-logo"></div><p style="font-size: 13px;"><?php _e( 'Invalid Form.', 'caldera-forms' ); ?></p>
			</div>
		<# } #>
	</script>
	<?php

	}

	/**
	 * Returns the array of form templates.
	 *
	 * @since 1.2.3
	 *
	 * @return    array The form templates
	 */
	public static function internal_form_templates(){
		
		$internal_templates = array(
			'starter_contact_form'	=>	array(
				'name'	=>	__( 'Starter Contact Form', 'caldera-forms' ),
				'template'	=>	include CFCORE_PATH . 'includes/templates/starter-contact-form.php'
			),
			'variable_price_example'	=>	array(
				'name'	=>	__( 'Variable Pricing Form, with add-on products.', 'caldera-forms' ),
				'template'	=>	include CFCORE_PATH . 'includes/templates/variable-price-example.php'
			),
			'registration' => 	array(
				'name'	=>	__( 'Registration Form, with optional additional participants.', 'caldera-forms' ),
				'template'	=>	include CFCORE_PATH . 'includes/templates/registration-form-example.php'
			),

		);

		/**
		 * Filter form templates
		 *
		 * @since 1.2.3
		 *
		 * @param array $internal_templates Form templates
		 */
		return apply_filters( 'caldera_forms_get_form_templates', $internal_templates );

	}
	
	public function load_new_form_templates(){

		$form_templates = self::internal_form_templates();

		?>
		<div class="caldera-config-group">
			<label for=""><?php echo __('Form Template', 'caldera-forms'); ?></label>
			<div class="caldera-config-field">
				<select class="new-form-template block-input field-config" name="template" value="">
				<option value="0"><?php echo __('no template - blank form', 'caldera-forms'); ?></option>
				<?php

				foreach( $form_templates as $template_slug => $template ){
					if( !empty( $template['template'] ) && !empty( $template['name'] ) ){
						echo '<option value="' . $template_slug . '">' . $template['name'] . '</option>';
					}
				}

				?>
				</select>
			</div>
		</div>
		<?php
	}

	/**
	 * Make the form preview in post editor
	 *
	 * @since unknown
	 */
	public function get_form_preview(){
		global $post;
		add_filter('caldera_forms_render_form_element', array( $this, 'set_preview_form_element') );
		$post = get_post( (int) $_POST['post_id'] );
		if( isset($_POST['atts']['named']['id']) ){
			$form = $_POST['atts']['named']['id'];
		}elseif( isset($_POST['atts']['named']['name']) ){
			$form = $_POST['atts']['named']['name'];
		}
		
		add_filter('caldera_forms_get_form-' . $form, array( $this, 'set_preview_get_form'),100 );

		$atts = $_POST['atts']['named'];
		$atts['preview'] = true;

		if( !empty( $form ) ){
			ob_start();
			wp_head();
			$content = '';
			if( !empty( $_POST['content'] ) ){
				$content = stripslashes_deep( $_POST['content'] );
			}
			echo Caldera_Forms::shortcode_handler( $atts, $content, 'caldera_form' );
			echo '<div class="clear"></div>';
			wp_print_footer_scripts();
			$html = ob_get_clean();
		}
		$out = array();
		if( !empty( $html ) ){
			$out['html'] = $html;
		}
		
		wp_send_json_success( $out );
	}

	public function set_preview_get_form( $form ){
		$form['form_ajax'] = false;
		$form['settings']['responsive']['break_point'] = 'xs';
		return $form;
	}
	public function set_preview_form_element($element){
		return 'div';
	}

	public function prepare_filter_addons($plugins){
		global $wp_list_table, $status;

		if( !empty( $this->addons ) ){
			$addons = array();
			foreach( $this->addons as $addon ){
				$plugin_slug = basename( dirname( $addon['file'] ) ) .'/'.basename( $addon['file'] );
				if( isset( $plugins[$plugin_slug] ) ){
					if( isset( $addon['slug'] ) ){
						$plugins[$plugin_slug]['slug'] = $addon['slug'];
					}
				}				
			}
		}
		if( isset( $_REQUEST['plugin_status'] ) && $_REQUEST['plugin_status'] === 'caldera_forms' ){
			$status = 'caldera_forms';
		}

		return $plugins;
	}

	public function bulk_action(){

		// first validate
		self::verify_ajax_action();


		if(empty($_POST['do'])){
			die;
		}
		
		$do_action = strtolower( $_POST['do'] );

		switch ( $do_action ) {
			case 'active':
			case 'trash':
			case 'delete':
				global $wpdb;

				// clean out
				$items = array();
				$selectors = array();
				foreach((array) $_POST['items'] as $item_id){
					$items[] = (int) $item_id;
					$selectors[] = '#entry_row_' . (int) $item_id;
				}

				switch ($do_action) {
					case 'delete':
						if( current_user_can( 'delete_others_posts' ) ){
							$result = $wpdb->query( "DELETE FROM `" . $wpdb->prefix . "cf_form_entries` WHERE `id` IN (".implode(',', $items).");" );
							$result = $wpdb->query( "DELETE FROM `" . $wpdb->prefix . "cf_form_entry_values` WHERE `entry_id` IN (".implode(',', $items).");" );
							$result = $wpdb->query( "DELETE FROM `" . $wpdb->prefix . "cf_form_entry_meta` WHERE `entry_id` IN (".implode(',', $items).");" );
						}
						$out['status'] = 'reload';
						wp_send_json( $out );
						break;
					
					default:
						if( current_user_can( 'edit_others_posts' ) ){
							$result = $wpdb->query( $wpdb->prepare( "UPDATE `" . $wpdb->prefix . "cf_form_entries` SET `status` = %s WHERE `id` IN (".implode(',', $items).");", $do_action ) );
						}
						break;
				}
				
				if( $result ){

					$out['status'] = $do_action;
					$out['undo'] = ( $do_action === 'trash' ? 'active' : __('Trash') );
					$out['undo_text'] = ( $do_action === 'trash' ? __('Restore', 'caldera-forms') : __('Trash') );

					$out['entries'] = implode(',',$selectors);
					$out['total']	= $wpdb->get_var($wpdb->prepare("SELECT COUNT(`id`) AS `total` FROM `" . $wpdb->prefix . "cf_form_entries` WHERE `form_id` = %s && `status` = 'active';", $_POST['form']));
					$out['trash']	= $wpdb->get_var($wpdb->prepare("SELECT COUNT(`id`) AS `total` FROM `" . $wpdb->prefix . "cf_form_entries` WHERE `form_id` = %s && `status` = 'trash';", $_POST['form']));
					wp_send_json( $out );
				}
				exit();

				break;
			case 'export':

				$transientid = uniqid('cfe');
				set_transient( $transientid, $_POST['items'], 180 );				
				$out['url'] = "admin.php?page=caldera-forms&export=" . $_POST['form'] . "&tid=" . $transientid;
				wp_send_json( $out );
				exit();
				break;
			default:
				# code...
				break;
		}
		exit();	
	}


	public static function update_pointer(){

		if(!empty($_POST['pointer'])){
			add_user_meta( get_current_user_id() , 'cf_pointer_' . $_POST['pointer'] );
		}
		exit;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( $this->plugin_slug, FALSE, basename( CFCORE_PATH ) . '/languages');
	}
	
	

	public static function add_shortcode_inserter(){
		
		$screen = get_current_screen();

		if($screen->base === 'post'){
			include CFCORE_PATH . 'ui/insert_shortcode.php';
		}
	} 

	public static function get_admin_templates(){
		include CFCORE_PATH . 'ui/news_templates.php';
		include CFCORE_PATH . 'ui/admin_templates.php';
	}
	public static function get_admin_meta_templates(){
		
		$processors = $processors = Caldera_Forms_Processor_Load::get_instance()->get_processors();
		if(!empty($processors)){
			foreach($processors as $processor_type=>$processor_config){
				if( isset( $processor_config['meta_template'] ) && file_exists( $processor_config['meta_template'] ) ){
					echo "{{#if ".$processor_type."_template}}\r\n";
						echo "{{#each data}}\r\n";
							echo "{{#if title}}\r\n";
								echo "<h4>{{title}}</h4>\r\n";
							echo "{{/if}}\r\n";
							echo "{{#each entry}}\r\n";
								include $processor_config['meta_template'];
							echo "{{/each}}\r\n";
						echo "{{/each}}\r\n";
					echo "{{/if}}\r\n";
				}
			}
		}
	}

	public static function get_entry_actions(){

		$viewer_buttons_array = apply_filters( 'caldera_forms_entry_viewer_buttons', array());
		$viewer_buttons = null;
		if(!empty($viewer_buttons_array)){
			$viewer_buttons = array();
			foreach($viewer_buttons_array as $button){

				if(is_array($button['config'])){
					$config = $button['label'].'|'.json_encode($button['config']);
				}else{
					$config = $button['label'].'|'.$button['config'];	
				}
				if( isset( $button['class'] ) ){
					$config .= '|' . $button['class'];
				}
				$viewer_buttons[] = $config;
			}

			$viewer_buttons = 'data-modal-buttons=\'' . implode(';', $viewer_buttons) . '\'';
		}
		$editor_buttons_array = apply_filters( 'caldera_forms_entry_editor_buttons', array());
		$editor_buttons = null;
		if(!empty($editor_buttons_array)){
			$editor_buttons = array();
			foreach($editor_buttons_array as $button){

				if(is_array($button['config'])){
					$config = $button['label'].'|'.json_encode($button['config']);
				}else{
					$config = $button['label'].'|'.$button['config'];	
				}
				if( isset( $button['class'] ) ){
					$config .= '|' . $button['class'];
				}
				$editor_buttons[] = $config;
			}

			$editor_buttons = 'data-modal-buttons=\'' . implode(';', $editor_buttons) . '\'';
		}


		if( current_user_can( 'edit_others_posts' ) ){
			echo '{{#if ../../is_active}}<button class="hidden button button-small cfajax-trigger edit-entry-btn" id="edit-entry-{{_entry_id}}" data-active-class="current-edit" data-fixed="true" data-load-class="spinner" ' . $editor_buttons . ' data-modal-element="div" data-group="editentry" data-entry="{{_entry_id}}" data-form="{{../../form}}" data-request="' . site_url( "/cf-api/" ) . '{{../../form}}/{{_entry_id}}/" data-method="get" data-modal="view_entry" data-modal-width="700" data-modal-height="auto" data-modal-title="' . __('Editing Entry ', 'caldera-forms') . ' #{{_entry_id}}" type="button">' . __('Edit') . '</button> {{/if}}';
		}
		echo '{{#if ../../is_active}}<button class="button button-small ajax-trigger view-entry-btn" id="view-entry-{{_entry_id}}" data-active-class="current-view"  data-fixed="true" data-load-class="spinner" ' . $viewer_buttons . ' data-group="viewentry" data-entry="{{_entry_id}}" data-form="{{../../form}}" data-action="get_entry" data-modal="view_entry" data-modal-width="700" data-modal-height="700" data-modal-title="' . __('Entry', 'caldera-forms') . ' #{{_entry_id}}" data-template="#view-entry-tmpl" type="button">' . __('View') . '</button> {{/if}}';		
		if( current_user_can( 'delete_others_posts' ) ){
			echo '<button type="button" class="button button-small ajax-trigger" data-load-class="active" data-panel="{{#if ../../is_trash}}trash{{/if}}{{#if ../../is_active}}active{{/if}}" data-do="{{#if ../../is_trash}}active{{/if}}{{#if ../../is_active}}trash{{/if}}" data-callback="cf_refresh_view" data-form="{{../../form}}" data-active-class="disabled" data-group="row{{_entry_id}}" data-load-element="#entry_row_{{_entry_id}}" data-action="cf_bulk_action" data-items="{{_entry_id}}">{{#if ../../is_trash}}' . __('Restore', 'caldera-forms') . '{{/if}}{{#if ../../is_active}}' . __('Trash') . '{{/if}}</button>';
		}

	}
	
	public static function set_viewer_buttons($buttons){
		
		$buttons['close_panel'] = array(
			'label'		=>	__('Close', 'caldera-forms' ),
			'config'	=>	'dismiss',
			'class'		=>	'right'
		);
		if( current_user_can( 'edit_others_posts' ) ){
			$buttons['edit_entry'] = array(
				'label'		=>	__('Edit Entry', 'caldera-forms' ),
				'config'	=>	array(
					'data-trigger'	=> '#edit-entry-{{_entry_id}}'
				),
				'class'		=>	'button-primary'
			);
		}

		return $buttons;
	}

	
	public static function set_editor_buttons($buttons){

		$buttons['submit_form'] = array(
			'label'		=>	__('Save Changes', 'caldera-forms' ),
			'config'	=>	array(
				"data-for" => "#view_entry_baldrickModalBody .caldera_forms_form"
			),
			'class'		=>	'right button-primary'
		);
		$buttons['view_entry'] = array(
			'label'		=>	__('View Entry', 'caldera-forms' ),
			'config'	=>	array(
				"data-for" => ".view-entry-btn.current-view"
			),
			'class'		=>	''
		);		
		
		//$buttons = set_viewer_buttons($buttons);
		return $buttons;
	}


	public static function save_cf_setting(){
		if(empty($_POST['set'])){
			exit;
		}
		$style_includes = get_option( '_caldera_forms_styleincludes' );

		if(empty($style_includes[$_POST['set']])){
			$style_includes[$_POST['set']] = true;
		}else{
			$style_includes[$_POST['set']] = false;
		}
		update_option( '_caldera_forms_styleincludes', $style_includes);
		wp_send_json( $style_includes );
		exit;
	}

	/**
	 * Insert shortcode media button
	 *
	 *
	 */
	function shortcode_insert_button(){
		global $post;
		if(!empty($post)){
			echo "<a id=\"caldera-forms-form-insert\" title=\"".__('Add Form to Page','caldera-forms')."\" class=\"button caldera-forms-insert-button\" href=\"#inst\">\n";
			echo "	<img src=\"". CFCORE_URL . "assets/images/lgo-icon.png\" alt=\"".__("Insert Form Shortcode","caldera-forms")."\" style=\"padding: 0px 2px 0px 0px; width: 16px; margin: -2px 0px 0px;\" /> ".__('Caldera Form', 'caldera-forms')."\n";
			echo "</a>\n";
		}
	}
	function shortcode_insert_button_fs($buttons){
		
		$buttons['caldera-forms'] = array(
			"title"		=>	__('Add Form to Page','caldera-forms'),
			"both"		=> true
		);
		return $buttons;
	}

	/**
	 * Change form's state
	 *
	 * @uses "wp_ajax_toggle_form_state" action
	 *
	 * @since unknown
	 */
	public static function toggle_form_state(){
		
		// first validate
		self::verify_ajax_action();

		$forms = Caldera_Forms_Forms::get_forms( true );
		$form = sanitize_text_field( $_POST['form'] );
		$form = Caldera_Forms_Forms::get_form( $form );
		if( empty( $form ) || empty( $form['ID'] ) || empty( $forms[ $form['ID'] ]) ){
			wp_send_json_error( );
		}

		if( isset( $form['form_draft'] ) ){

			Caldera_Forms_Forms::form_state( $form );
			$state = 'active-form';
			$label = __('Deactivate', 'caldera-forms');
		}else{
			Caldera_Forms_Forms::form_state( $form , false );
			$state = 'draft-form';
			$label = __('Activate', 'caldera-forms');
		}


		wp_send_json_success( array( 'ID' => $form['ID'], 'state' => $state, 'label' => $label ) );
	}

	/**
	 * nonce verifier for ajax actions
	 *
	 * @since 1.3.2.1
	 */
	private static function verify_ajax_action(){
		if ( ! isset( $_POST['cf_toolbar_actions'] ) || ! wp_verify_nonce( $_POST['cf_toolbar_actions'], 'cf_toolbar' ) || !check_admin_referer( 'cf_toolbar', 'cf_toolbar_actions' ) ) {
			wp_send_json_error( $_POST );
		}
	}

	/**
	 * Show entries in admin
	 *
	 * @since unknown
	 */
	public static function browse_entries(){

		if ( isset( $_POST[ 'page' ] ) && 0 < $_POST[ 'page' ] ) {
			$page = absint( $_POST[ 'page' ] );
		}else{
			$page = 1;
		}
		$entry_perpage = get_option( '_caldera_forms_entry_perpage', 20 );
		if ( isset( $_POST[ 'perpage' ] ) && 0 < $_POST[ 'perpage' ] ) {
			$perpage = absint( (int) $_POST[ 'perpage' ] );
			if( $entry_perpage != $perpage ){
				update_option( '_caldera_forms_entry_perpage', $perpage );
			}			
		}else{
			$perpage = $entry_perpage;
		}

		if ( isset( $_POST[ 'status' ] ) ) {
			$status = strip_tags( $_POST[ 'status' ] );
		}else{
			$status = 'active';
		}

		$form = Caldera_Forms_Forms::get_form( $_POST['form'] );
			
		$data = self::get_entries( $form, $page, $perpage, $status );

		// set status output
		$data['is_' . $status ] = true;

		wp_send_json( $data );
		exit;


	}

	/**
	 * Get entries from a form
	 *
	 * @since 1.2.1
	 *
	 * @param string|array $form Form ID or form config.
	 * @param int $page Optional. Page of entries to get per page. Default is 1.
	 * @param int $perpage Optional. Number of entries per page. Default is 20.
	 * @param string $status Optional. Form status. Default is active.
	 *
	 * @return array
	 */
	public static function get_entries( $form, $page = 1, $perpage = 20, $status = 'active' ) {

		if ( is_string( $form ) ) {
			$form = Caldera_Forms_Forms::get_form( $form );
		}

		if ( isset( $form[ 'ID' ])) {
			$form_id = $form[ 'ID' ];
		}else{
			return;
		}

		global $wpdb;

		$field_labels = array();
		$backup_labels = array();
		$selects = array();

		// get all fieldtype
		$field_types = Caldera_Forms::get_field_types();


		$fields = array();
		if(!empty($form['fields'])){
			foreach($form['fields'] as $fid=>$field){
				$fields[$field['slug']] = $field;

				if(!empty($field['entry_list'])){
					$selects[] = "'".$field['slug']."'";
					$field_labels[$field['slug']] = $field['label'];
				}
				$has_vars = array();
				if( !empty( $form['variables']['types'] ) ){
					$has_vars = $form['variables']['types'];
				}
				if( ( count($backup_labels) < 4 && !in_array( 'entryitem', $has_vars ) ) && in_array($field['type'], array('text','email','date','name'))){
					// backup only first 4 fields
					$backup_labels[$field['slug']] = $field['label'];
				}
			}
		}
		if(empty($field_labels)){
			$field_labels = $backup_labels;
		}
		//ksort($field_labels);

		$data = array();

		$filter = null;

		$data['trash'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(`id`) AS `total` FROM `" . $wpdb->prefix . "cf_form_entries` WHERE `form_id` = %s AND `status` = 'trash';", $form_id ) );
		$data['active'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(`id`) AS `total` FROM `" . $wpdb->prefix . "cf_form_entries` WHERE `form_id` = %s AND `status` = 'active';", $form_id ) );

		// set current total
		if(!empty( $status ) && isset( $data[ $status ] ) ){
			$data['total'] = $data[ $status ];
		}else{
			$data['total'] = $data['active'];
		}


		$data['pages'] = ceil($data['total'] / $perpage );

		if(!empty( $page )){
			$page = abs( $page );
			if($page > $data['pages']){
				$page = $data['pages'];
			}
		}

		$data['current_page'] = $page;
		$gmt_offset = get_option( 'gmt_offset' );
		if($data['total'] > 0){

			$data['form'] = $form_id;

			$data['fields'] = $field_labels;
			$offset = ($page - 1) * $perpage;
			$limit = $offset . ',' . $perpage;



			$rawdata = $wpdb->get_results($wpdb->prepare("
			SELECT
				`id`,
				`form_id`
			FROM `" . $wpdb->prefix ."cf_form_entries`

			WHERE `form_id` = %s AND `status` = %s ORDER BY `datestamp` DESC LIMIT " . $limit . ";", $form_id, $status ) );

			if(!empty($rawdata)){

				$ids = array();
				foreach($rawdata as $row){
					$ids[] = $row->id;
				}

				$rawdata = $wpdb->get_results("
				SELECT
					`entry`.`id` as `_entryid`,
					`entry`.`form_id` AS `_form_id`,
					`entry`.`datestamp` AS `_date_submitted`,
					`entry`.`user_id` AS `_user_id`,
					`value`.*

				FROM `" . $wpdb->prefix ."cf_form_entries` AS `entry`
				LEFT JOIN `" . $wpdb->prefix ."cf_form_entry_values` AS `value` ON (`entry`.`id` = `value`.`entry_id`)

				WHERE `entry`.`id` IN (" . implode(',',$ids) . ")
				" . $filter ."
				ORDER BY `entry`.`datestamp` DESC;");


				$data['entries'] = array();

				foreach($rawdata as $row){
					if(!empty($row->_user_id)){
						$user = get_userdata( $row->_user_id );
						if(!empty($user)){
							$data['entries']['E' . $row->_entryid]['user']['ID'] = $user->ID;
							$data['entries']['E' . $row->_entryid]['user']['name'] = $user->data->display_name;
							$data['entries']['E' . $row->_entryid]['user']['email'] = $user->data->user_email;
							$data['entries']['E' . $row->_entryid]['user']['avatar'] = get_avatar( $user->ID, 64 );
						}
					}
					$data['entries']['E' . $row->_entryid]['_entry_id'] = $row->_entryid;

					$submitted =  $row->_date_submitted;



					$data['entries']['E' . $row->_entryid]['_date'] = Caldera_Forms::localize_time( $submitted );

					// setup default data array
					if(!isset($data['entries']['E' . $row->_entryid]['data'])){
						if(isset($field_labels)){
							foreach ($field_labels as $slug => $label) {
								// setup labels ordering
								$data['entries']['E' . $row->_entryid]['data'][$slug] = null;
							}
						}
					}

					if(!empty($field_labels[$row->slug])){

						$label = $field_labels[$row->slug];

						// check view handler
						$field = $fields[$row->slug];
						// filter the field to get field data
						$field = apply_filters( 'caldera_forms_render_get_field', $field, $form);
						$field = apply_filters( 'caldera_forms_render_get_field_type-' . $field['type'], $field, $form);
						$field = apply_filters( 'caldera_forms_render_get_field_slug-' . $field['slug'], $field, $form);

						// maybe json?
						$is_json = json_decode( $row->value, ARRAY_A );
						if( !empty( $is_json ) ){
							$row->value = $is_json;
						}

						if( is_string( $row->value ) ){
							$row->value = esc_html( stripslashes_deep( $row->value ) );
						}else{
							$row->value = stripslashes_deep( Caldera_Forms_Sanitize::sanitize( $row->value ) );
						}

						$row->value = apply_filters( 'caldera_forms_view_field_' . $field['type'], $row->value, $field, $form);


						if(isset($data['entries']['E' . $row->_entryid]['data'][$row->slug])){
							// array based - add another entry
							if(!is_array($data['entries']['E' . $row->_entryid]['data'][$row->slug])){
								$tmp = $data['entries']['E' . $row->_entryid]['data'][$row->slug];
								$data['entries']['E' . $row->_entryid]['data'][$row->slug] = array($tmp);
							}
							$data['entries']['E' . $row->_entryid]['data'][$row->slug][] = $row->value;
						}else{
							$data['entries']['E' . $row->_entryid]['data'][$row->slug] = $row->value;
						}
					}

					if( !empty( $form['variables']['types'] ) ){
						foreach( $form['variables']['types'] as $var_key=>$var_type ){
							if( $var_type == 'entryitem' ){
								$data['fields'][$form['variables']['keys'][$var_key]] = ucwords( str_replace( '_', ' ', $form['variables']['keys'][$var_key] ) );
								$data['entries']['E' . $row->_entryid]['data'][$form['variables']['keys'][$var_key]] = Caldera_Forms::do_magic_tags( $form['variables']['values'][$var_key], $row->_entryid );
							}
						}
					}


				}
			}
		}


		return $data;

	}


	/**
	 * Return an instance of this class.
	 *
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Registers the admin page
	 *
	 */
	public function register_admin_page(){
		global $menu, $submenu;
		
		$forms = Caldera_Forms_Forms::get_forms( true );

		// get current user
		if( current_user_can( Caldera_Forms::get_manage_cap() ) ){
		
			$this->screen_prefix[] = add_menu_page( __('Caldera Forms', 'caldera-forms'), __('Caldera Forms', 'caldera-forms'), Caldera_Forms::get_manage_cap(), $this->plugin_slug, array( $this, 'render_admin' ), 'dashicons-cf-logo', 52.81321 );
			add_submenu_page( $this->plugin_slug, __('Caldera Forms Admin', 'caldera-forms'), __('Forms', 'caldera-forms'), Caldera_Forms::get_manage_cap(), $this->plugin_slug, array( $this, 'render_admin' ) );
			
			if( ! empty( $forms ) ){
				foreach($forms as $form_id=>$form){
					if(!empty($form['pinned'])){
						$this->screen_prefix[] 	 = add_submenu_page( $this->plugin_slug, __('Caldera Forms', 'caldera-forms').' - ' . $form['name'], '- '.$form['name'], Caldera_Forms::get_manage_cap(), $this->plugin_slug . '-pin-' . $form_id, array( $this, 'render_admin' ) );
					}
				}
			}	


			$this->screen_prefix[] 	 = add_submenu_page( $this->plugin_slug, __('Caldera Forms', 'caldera-forms') .' - '. __('Community', 'caldera-forms'), __('Community', 'caldera-forms'), Caldera_Forms::get_manage_cap(), $this->plugin_slug . '-community', array( $this, 'render_admin' ) );
			$this->screen_prefix[] 	 = add_submenu_page( $this->plugin_slug, __('Caldera Forms', 'caldera-forms') . ' - ' . __('Extend', 'caldera-forms'), __('Extend', 'caldera-forms'), Caldera_Forms::get_manage_cap(), $this->plugin_slug . '-exend', array( $this, 'render_admin' ) );
		}else{
			// not an admin - pin for user
			if( ! empty( $forms ) ){
				$user = wp_get_current_user();
				if(empty($user->roles)){
					// no role - bye bye.
					return;
				}

				foreach($forms as $form_id=>$form){
					$capability = null;
					if(!empty($form['pinned']) && !empty( $form['pin_roles'] ) ){
						if( !empty( $form['pin_roles']['all_roles'] ) ){
							$user = wp_get_current_user();
							if( empty( $user ) || empty( $user->roles ) ){
								continue;
							}
							$capabilities = array_keys( $user->allcaps );
							if( empty( $capabilities ) ){
								continue;
							}
							$capability = $capabilities[0];
						}elseif( !empty( $form['pin_roles']['access_role'] ) ){
							foreach ($form['pin_roles']['access_role'] as $role => $enabled) {
								if( in_array( $role, $user->roles ) ){
									$role_details = get_role( $role );
									if(empty($role_details->capabilities)){
										continue;
									}
									$capabilities = array_keys( $role_details->capabilities );
									$capability = $capabilities[0];
									break;
								}
							}
						}
						if( empty($capability)){
							// not this one.
							continue;
						}

						if( empty( $this->screen_prefix ) ){
							// make top menu
							$main_slug = $this->plugin_slug . '-pin-' . $form_id;
							$this->screen_prefix[] = add_menu_page( __('Caldera Forms', 'caldera-forms'), __('Caldera Forms', 'caldera-forms'), $capability, $main_slug, array( $this, 'render_admin' ), 'dashicons-cf-logo', 52.999 );
							
						}

						$this->screen_prefix[] 	 = add_submenu_page( $main_slug, __('Caldera Forms', 'caldera-forms').' - ' . $form['name'], $form['name'], $capability, $this->plugin_slug . '-pin-' . $form_id, array( $this, 'render_admin' ) );
					
					}
				}
			}
		}


	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 *
	 * @return    null
	 */
	public function enqueue_admin_stylescripts() {

		$screen = get_current_screen();

		wp_enqueue_style( $this->plugin_slug . '-admin-icon-styles', CFCORE_URL . 'assets/css/dashicon.css', array(), self::VERSION );

		if ( $screen->base === 'post' ) {
			wp_enqueue_style( $this->plugin_slug . '-modal-styles', CFCORE_URL . 'assets/css/modals.css', array(), self::VERSION );
			wp_enqueue_script( $this->plugin_slug . '-shortcode-insert', CFCORE_URL . 'assets/js/shortcode-insert.min.js', array( 'jquery' ), self::VERSION );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );				
		}

		if ( ! in_array( $screen->base, $this->screen_prefix ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_style( 'wp-pointer' );


		wp_enqueue_script( 'password-strength-meter' );

		wp_enqueue_style( $this->plugin_slug . '-admin-styles', CFCORE_URL . 'assets/css/admin.css', array(), self::VERSION );
		wp_enqueue_style( $this->plugin_slug . '-modal-styles', CFCORE_URL . 'assets/css/modals.css', array(), self::VERSION );
		wp_enqueue_style( $this->plugin_slug . '-field-styles', CFCORE_URL . 'assets/css/fields.min.css', array(), self::VERSION );


		wp_enqueue_script( $this->plugin_slug . '-baldrick', CFCORE_URL . 'assets/js/wp-baldrick-full.js', array( 'jquery' ), self::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-admin-scripts', CFCORE_URL . 'assets/js/admin.min.js', array( $this->plugin_slug . '-baldrick' ), self::VERSION );

		if ( ! empty( $_GET[ 'edit' ] ) ) {

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			wp_enqueue_script( $this->plugin_slug . '-edit-fields', CFCORE_URL . 'assets/js/fields.min.js', array( 'jquery' ), self::VERSION );

			wp_enqueue_script( $this->plugin_slug . '-edit-editor', CFCORE_URL . 'assets/js/edit.min.js', array( 'jquery' ), self::VERSION );


			wp_enqueue_script( 'jquery-ui-users' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-ui-droppable' );

		} else {

			$field_types = apply_filters( 'caldera_forms_get_field_types', array() );

			wp_enqueue_style( 'cf-grid-styles' );
			wp_enqueue_style( 'cf-form-styles' );
			wp_enqueue_style( 'cf-alert-styles' );
			wp_enqueue_style( 'cf-field-styles' );

			wp_enqueue_script( 'cf-field' );
			wp_enqueue_script( 'cf-conditionals' );
			wp_enqueue_script( 'cf-validator' );
			wp_enqueue_script( 'cf-init' );

			foreach ( $field_types as $field ) {
				if ( ! empty( $field[ 'styles' ] ) ) {
					foreach ( $field[ 'styles' ] as $style ) {
						if ( false !== strpos( $style, '//' ) ) {
							wp_enqueue_style( 'cf-' . sanitize_key( basename( $style ) ), $style, array() );
						} else {
							wp_enqueue_style( $style );
						}
					}
				}

				//enqueue scripts
				if ( ! empty( $field[ 'scripts' ] ) ) {
					// check for jquery deps
					$depts[] = 'jquery';
					foreach ( $field[ 'scripts' ] as $script ) {
						if ( false !== strpos( $script, '//' ) ) {
							wp_enqueue_script( 'cf-' . sanitize_key( basename( $script ) ), $script, $depts );
						} else {
							wp_enqueue_script( $script );
						}
					}
				}
			}

		}
		if ( ! empty( $_GET[ 'edit-entry' ] ) ) {
			wp_enqueue_style( 'cf-grid-styles', CFCORE_URL . 'assets/css/caldera-grid.css', array(), self::VERSION );
		}


		// Load Field Types Styles & Scripts
		$field_types = apply_filters( 'caldera_forms_get_field_types', array() );

		// load panels
		$panel_extensions = apply_filters( 'caldera_forms_get_panel_extensions', array() );

		// load processors
		$form_processors = $processors = Caldera_Forms_Processor_Load::get_instance()->get_processors();

		// merge a list
		$merged_types = array_merge( $field_types, $panel_extensions, $form_processors );

		foreach ( $merged_types as $type => &$config ) {

			// set context
			if ( ! empty( $_GET[ 'edit' ] ) ) {
				$context = &$config[ 'setup' ];
			} else {
				$context = &$config;
			}

			/// Styles
			if ( ! empty( $context[ 'styles' ] ) ) {
				foreach ( $context[ 'styles' ] as $location => $styles ) {

					// front only scripts
					if ( $location === 'front' ) {
						continue;
					}


					foreach ( (array) $styles as $style ) {

						$key = $type . '-' . sanitize_key( basename( $style ) );

						// is url
						if ( false === strpos( $style, "/" ) ) {
							// is reference
							wp_enqueue_style( $style );

						} else {
							// is url -
							if ( '//' != substr( $style, 0, 2 ) && file_exists( $style ) ) {
								// local file
								wp_enqueue_style( $key, plugin_dir_url( $style ) . basename( $style ), array(), self::VERSION );
							} else {
								// most likely remote
								wp_enqueue_style( $key, $style, array(), self::VERSION );
							}

						}
					}
				}
			}
			/// scripts
			if ( ! empty( $context[ 'scripts' ] ) ) {

				foreach ( $context[ 'scripts' ] as $location => $scripts ) {

					// front only scripts
					if ( $location === 'front' ) {
						continue;
					}

					foreach ( (array) $scripts as $script ) {


						$key = $type . '-' . sanitize_key( basename( $script ) );

						// is url
						if ( false === strpos( $script, "/" ) ) {
							// is reference
							wp_enqueue_script( $script );

						} else {
							// is url -
							if ( '//' != substr( $script, 0, 2 ) && file_exists( $script ) ) {
								// local file
								wp_enqueue_script( $key, plugin_dir_url( $script ) . basename( $script ), array( 'jquery' ), self::VERSION );
							} else {
								// most likely remote
								wp_enqueue_script( $key, $script, array( 'jquery' ), self::VERSION );
							}

						}
					}
				}
			}
		}


	}

	/**
	 * Renders the admin pages
	 *
	*/
	public function render_admin(){
		
		echo "	<div class=\"wrap\">\r\n";
		if(!empty($_GET['edit'])){
			echo "<form method=\"POST\" action=\"admin.php?page=" . $this->plugin_slug . "\" data-load-element=\"#save_indicator\" data-sender=\"ajax\" class=\"caldera-forms-options-form edit-update-trigger\">\r\n";
				include CFCORE_PATH . 'ui/edit.php';
			echo "</form>\r\n";
		}elseif(!empty($_GET['page']) && $_GET['page'] == 'caldera-forms-exend'){
			include CFCORE_PATH . 'ui/extend.php';
		}elseif(!empty($_GET['page']) && $_GET['page'] == 'caldera-forms-community'){
			include CFCORE_PATH . 'ui/community.php';
		}elseif(!empty($_GET['page']) && false !== strpos($_GET['page'], 'caldera-forms-pin-')){
			$formID = substr($_GET['page'], 18);
			$form = Caldera_Forms_Forms::get_form( $formID );
			include CFCORE_PATH . 'ui/entries.php';

		}else{
			include CFCORE_PATH . 'ui/admin.php';
		}
		echo "	</div>\r\n";

	}

	/***
	 * Handles form updating, deleting, exporting and importing
	 *
	 * @uses "init" action
	 */
	static function save_form(){
		if( ! isset( $_GET[ 'page' ] ) || 'caldera-forms' != $_GET[ 'page' ] ){
			return;
		}

		add_filter( 'caldera_forms_manage_cap', array( __CLASS__ , 'save_form_cap_filter' ), 9, 3 );

		/// check for form delete
		if(!empty($_GET['delete']) && !empty($_GET['cal_del']) && current_user_can( Caldera_Forms::get_manage_cap( 'save' ), strip_tags( $_GET[ 'delete' ] ) ) ){

			if ( ! wp_verify_nonce( $_GET['cal_del'], 'cf_del_frm' ) ) {
				// This nonce is not valid.
				wp_die( __('Sorry, please try again', 'caldera-forms'), __('Form Delete Error', 'caldera-forms') );
			}else{
				$deleted = Caldera_Forms_Forms::delete_form( strip_tags( $_GET['delete'] ) );
				if ( $deleted ) {
					wp_redirect( 'admin.php?page=caldera-forms' );
					exit;
				} else {
					wp_die( __('Sorry, please try again', 'caldera-forms'), __('Form could not be deleted.', 'caldera-forms') );
				}

			}
			
		}

		/** IMPORT */
		if( isset($_POST['cfimporter']) && current_user_can( Caldera_Forms::get_manage_cap( 'import' )  ) ){

			if ( check_admin_referer( 'cf-import', 'cfimporter' ) ) {
				if ( isset( $_FILES[ 'import_file' ] ) && ! empty( $_FILES[ 'import_file' ][ 'size' ] ) ) {
					$loc = wp_upload_dir();

					if ( move_uploaded_file( $_FILES[ 'import_file' ][ 'tmp_name' ], $loc[ 'path' ] . '/cf-form-import.json' ) ) {
						$data = json_decode( file_get_contents( $loc[ 'path' ] . '/cf-form-import.json' ), true );
						if( ! is_array( $data ) ){
							wp_die( esc_html__( 'File is not a valid Caldera Form Import', 'caldera-forms' ) );
						}
						if( ! isset( $_POST[ 'name' ] ) ){
							wp_die( esc_html__( 'Form must have a name.', 'caldera-forms' ) );
						}


						$data[ 'name' ] = strip_tags( $_POST[ 'name' ] );

						$new_form_id = Caldera_Forms_Forms::import_form( $data );

						if( is_string( $new_form_id ) ){
							cf_redirect( 'admin.php?page=caldera-forms&edit=' . $new_form_id, 302 );
							exit;
						}else{
							wp_die( esc_html__( 'Form could not be imported.', 'caldera-forms' ) );
						}




					}
				} else {
					wp_die( esc_html__( 'Sorry, File not uploaded.', 'caldera-forms' ), esc_html__( 'Form Import Error', 'caldera-forms' ) );
				}

			} else {

				wp_die( esc_html__( 'Sorry, please try again', 'caldera-forms' ), esc_html__( 'Form Import Error', 'caldera-forms' ) );
			}

		}

		if(!empty($_GET['export-form']) && current_user_can( Caldera_Forms::get_manage_cap( 'export', strip_tags( $_GET[ 'export-form' ] ) ) )){

			$form = Caldera_Forms_Forms::get_form( $_GET['export-form'] );

			if(empty($form)){
				wp_die( __('Form does not exist.', 'caldera-forms') );
			}

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
			if( empty( $_GET['format'] ) || $_GET['format'] != 'php' ){
				header("Content-Type: application/json");
				header("Content-Disposition: attachment; filename=\"" . sanitize_file_name( strtolower( $form['name'] ) ) . "-export.json\";" );
				echo json_encode($form);
			}else{

				$form_id = sanitize_key( $_GET['form_id'] );
				if( !empty( $_GET['pin_menu'] ) ){
					$form['pinned'] = 1;
				}
				header("Content-Type: application/php");
				header("Content-Disposition: attachment; filename=\"" . sanitize_file_name( strtolower( $form_id ) ) . "-include.php\";" );
				echo '<?php' . "\r\n";
				echo "/**\r\n * Caldera Forms - PHP Export \r\n * {$form['name']} \r\n * @version    " . CFCORE_VER . "\r\n * @license   GPL-2.0+\r\n * \r\n */\r\n\r\n\r\n";

				$structure = "/**\r\n * Filter admin forms to include custom form in admin\r\n *\r\n * @since 1.3.1\r\n *\r\n * @param array \$forms All registered forms\r\n */\r\n";
				$structure .= 'add_filter( "caldera_forms_get_forms", function( $forms ){' . "\r\n";
  				$structure .= "\t" . '$forms["' . $form_id . '"] = apply_filters( "caldera_forms_get_form-' . $form_id . '", array() );' . "\r\n";
 				$structure .= "\t" . 'return $forms;' . "\r\n";
				$structure .= "} );\r\n\r\n";

				$structure .= "/**\r\n * Filter form request to include form structure to be rendered\r\n *\r\n * @since 1.3.1\r\n *\r\n * @param \$form array form structure\r\n */\r\n";
				$structure .= "add_filter( 'caldera_forms_get_form-{$form_id}', function( \$form ){\r\n return " . var_export( $form, true ) . ";\r\n" . '} );' . "\r\n";
				// cleanups because I'm me
				$structure = str_replace( 'array (', 'array(', $structure );
				$structure = str_replace( $form['ID'], $form_id, $structure );
				// switch field IDs
				if( !empty( $_GET['convert_slugs'] ) ){
					if ( !empty( $form['fields'] ) ){
						foreach( $form['fields'] as $field_id=>$field ){
							$structure = str_replace( $field_id, $field['slug'], $structure );
						}
					}
				}

				echo $structure;
			}
			exit;

		}

		if(!empty($_GET['export']) && current_user_can( Caldera_Forms::get_manage_cap( 'export', strip_tags( $_GET[ 'export' ] ) ) ) ){

			$form = Caldera_Forms_Forms::get_form( $_GET['export'] );

			global $wpdb;

			//build labels
			$labels = array();
			$structure = array();
			$field_types = apply_filters( 'caldera_forms_get_field_types', array());
			$headers = array();
			if(!empty($form['fields'])){
				$headers['date_submitted'] = 'Submitted';
				foreach($form['fields'] as $field_id=>$field){
					if(isset($field_types[$field['type']]['capture']) &&  false === $field_types[$field['type']]['capture']){
						continue;
					}
					$headers[$field['slug']] = $field['label'];
					$structure[$field['slug']] = $field_id;
				}
			}
			$filter = null;
			// export set - transient
			if(!empty($_GET['tid'])){
				$items = get_transient( $_GET['tid'] );
				if(!empty($items)){
					$filter = ' AND `entry`.`id` IN (' . implode(',', $items) . ') ';
				}else{
					wp_die( __('Export selection has expired', 'caldera-forms') , __('Export Expired', 'caldera-forms') );
				}
			}

			$rawdata = $wpdb->get_results($wpdb->prepare("
			SELECT
				`entry`.`id` as `_entryid`,
				`entry`.`form_id` AS `_form_id`,
				`entry`.`datestamp` AS `_date_submitted`,
				`entry`.`user_id` AS `_user_id`

			FROM `" . $wpdb->prefix ."cf_form_entries` AS `entry`
			

			WHERE `entry`.`form_id` = %s
			" . $filter . "
			AND `entry`.`status` = 'active'
			ORDER BY `entry`.`datestamp` DESC;", $_GET['export']));

			$data = array();

			foreach( $rawdata as $entry){
				$submission = Caldera_Forms::get_entry( $entry->_entryid, $form);
				$data[$entry->_entryid]['date_submitted'] = $entry->_date_submitted;

				foreach ($structure as $slug => $field_id) {
					$data[$entry->_entryid][$slug] = ( isset( $submission['data'][$field_id]['value'] ) ? $submission['data'][$field_id]['value'] : null );
				}

			}

			if( empty( $headers ) ){
				wp_die( esc_html_e( 'Could not process export. This is most likely due to a problem with the form configuration.', 'caldera-forms' ) );
			}

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
			header("Content-Type: text/csv charset=utf-8;");
			header("Content-Disposition: attachment; filename=\"" . sanitize_file_name( $form['name'] ) . ".csv\";" );
			header("Content-Transfer-Encoding: binary");
			$df = fopen("php://output", 'w');
			fputcsv($df, $headers);
			foreach($data as $row){
				$csvrow = array();
				foreach($headers as $key=>$label){
					if(!isset($row[$key])){
						$row[$key] = null;
					}else{
						if( is_array( $row[$key] ) && isset( $row[$key]['label'] ) ){
							$row[$key] = $row[$key]['value'];
						}elseif( is_array( $row[$key] ) ){
							$subs = array();
							foreach( $row[$key] as $row_part ){
								if( is_array( $row_part ) && isset( $row_part['label'] ) ){
									$subs[] = $row_part['value'];
								}else{
									$subs[] = $row_part;
								}
							}
							$row[$key] = implode(', ', $subs );
						}
					}

					$csvrow[] = $row[$key];
				}
				fputcsv($df, $row);
			}
			fclose($df);			
			exit;			
		}

		if( isset($_POST['config']) && isset( $_POST['cf_edit_nonce'] ) && current_user_can( Caldera_Forms::get_manage_cap( 'manage' ) ) ){
			
			// if this fails, check_admin_referer() will automatically print a "failed" page and die.
			if ( check_admin_referer( 'cf_edit_element', 'cf_edit_nonce' ) ) {
				
				// strip slashes
				$data = json_decode( stripslashes_deep($_POST['config']) , ARRAY_A );
				self::save_a_form( $data );

				if(!empty($_POST['sender'])){
					exit;
				}

				wp_redirect('admin.php?page=caldera-forms');
				die;

			}
			return;
		}
	}

	/**
	 * Save a form
	 *
	 * @since 1.3.4
	 *
	 * @param array $data
	 */
	public static function save_a_form( $data ){
		Caldera_Forms_Forms::save_form( $data );
	}

	public static function create_form(){

		parse_str( $_POST['data'], $newform );


		$newform = Caldera_Forms_Forms::create_form( $newform );
		echo $newform['ID'];
		exit;


	}


	// get internal panel extensions

	public function get_panel_extensions($panels){

		$path = CFCORE_PATH . "ui/panels/";
		
		$internal_panels = array(
			'form_layout' => array(
				"name"			=>	__("Layout", 'caldera-forms'),
				"setup"		=>	array(
					"scripts"	=>	array(
						'jquery-ui-sortable',
						'jquery-ui-draggable',
						'jquery-ui-droppable',
						//CFCORE_URL . "assets/js/processors-edit.js",
						//CFCORE_URL . "assets/js/layout-grid.js"
					),
					"styles"	=>	array(
						CFCORE_URL . "assets/css/editor-grid.css",
						CFCORE_URL . "assets/css/processors-edit.css"
					),
				),
				"tabs"		=>	array(
					"layout" => array(
						"name" => __("Layout", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Layout Builder", 'caldera-forms'),
						"active" => true,
						"actions" => array(
							$path . "layout_toolbar.php"
						),
						"repeat" => 0,
						"canvas" => $path . "layout.php",
						"side_panel" => $path . "layout_side.php",
					),
					"pages" => array(
						"name" => __("Pages", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Form Pages", 'caldera-forms'),
						"canvas" => $path . "pages.php",
					),
					"conditions" => array(
						"name" => __("Conditions", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Conditions", 'caldera-forms'),
						"canvas" => $path . "conditions.php",
					),					
					"processors" => array(
						"name" => __("Processors", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Form Processors", 'caldera-forms'),
						"canvas" => $path . "processors.php",
					),
					"variables" => array(
						"name" => __("Variables", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Variables", 'caldera-forms'),
						"canvas" => $path . "variables.php",
						"actions" => array(
							$path . "variable_add.php"
						)
					),
					"responsive" => array(
						"name" => __("Responsive", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Responsive Settings", 'caldera-forms'),
						"repeat" => 0,
						"fields" => array(
							"break_point" => array(
								"label" => __("Grid Collapse", 'caldera-forms'),
								"slug" => "break_point",
								"caption" => __("Set the smallest screen size at which to collapse the grid. (based on Bootstrap 3.0)", 'caldera-forms'),
								"type" => "radio",
								"config" => array(
									"default" => "sm",
									"option"	=> array(
										"xs"	=> array(
											'value'	=> 'xs',
											'label'	=> __('Maintain grid always', 'caldera-forms'),
										),
										"sm"	=> array(
											'value'	=> 'sm',
											'label'	=> '< 767px'
										),
										"md"	=> array(
											'value'	=> 'md',
											'label'	=> '< 991px'
										),
										"lg"	=> array(
											'value'	=> 'lg',
											'label'	=> '< 1199px'
										)
									)
								),
							)
						),
					),
					"mailer" => array(
						"name" => __("Mailer", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Email Notification Settings", 'caldera-forms'),
						"canvas" => $path . "emailer.php",
					),
					/*
					"styles" => array(
						"name" => __("Stylesheets", 'caldera-forms'),
						"location" => "lower",
						"label" => __("Stylesheet Includes", 'caldera-forms'),
						"repeat" => 0,
						"fields" => array(
							"use_grid" => array(
								"label" => __("Grid CSS", 'caldera-forms'),
								"slug" => "use_grid",
								"caption" => __("Include the built in grid stylesheet (based on Bootstrap 3.0)", 'caldera-forms'),
								"type" => "dropdown",
								"config" => array(
									"default" => "yes",
									"option"	=> array(
										"opt1"	=> array(
											'value'	=> 'yes',
											'label'	=> 'Yes'
										),
										"opt2"	=> array(
											'value'	=> 'no',
											'label'	=> 'No'
										)
									)
								),
							),
							"use_form" => array(
								"label" => __("Form CSS", 'caldera-forms'),
								"slug" => "use_grid",
								"caption" => __("Include the built in form stylesheet (based on Bootstrap 3.0)", 'caldera-forms'),
								"type" => "dropdown",
								"config" => array(
									"default" => "yes",
									"option"	=> array(
										"opt1"	=> array(
											'value'	=> 'yes',
											'label'	=> 'Yes'
										),
										"opt2"	=> array(
											'value'	=> 'no',
											'label'	=> 'No'
										)
									)
								),
							),
							"use_alerts" => array(
								"label" => __("Alerts CSS", 'caldera-forms'),
								"slug" => "use_alerts",
								"caption" => __("Include the built in alerts stylesheet (based on Bootstrap 3.0)", 'caldera-forms'),
								"type" => "dropdown",
								"config" => array(
									"default" => "yes",
									"option"	=> array(
										"opt1"	=> array(
											'value'	=> 'yes',
											'label'	=> 'Yes'
										),
										"opt2"	=> array(
											'value'	=> 'no',
											'label'	=> 'No'
										)
									)
								),
							),
						),
					),*/
				),
			),
		);
		
		return array_merge( $panels, $internal_panels );
		
	}

	/**
	 * Add to the admin notices
	 *
	 * @since 1.3.0
	 *
	 * @param string|array $notice The notice or array of notices to add.
	 */
	public static function add_admin_notice( $notice ) {
		if ( is_string( $notice ) ) {
			self::$admin_notices[] = $notice;
		}

		if ( is_array( $notice ) ) {
			foreach( $notice as $n) {
				self::add_admin_notice( $n );
			}

		}

	}

	/**
	 * Get the admin messages
	 *
	 * @since 1.3
	 *
	 * @param bool $as_string Optional. To return as string, the default, or as an array
	 * @param string $seperator Optional. What to break notices with, when returning as string. Default is "\n"
	 *
	 * @return string|array|void
	 */
	public static  function get_admin_notices( $as_string = true, $seperator = "\n" ) {
		if ( ! empty( self::$admin_notices ) ) {
			if ( $as_string ) {
				return implode( $seperator, self::$admin_notices  );

			}else{
				return self::$admin_notices;

			}

		}

	}

	/**
	 * Handle admin alert/notices
	 *
	 * @since 1.3.4
	 *
	 * @uses "caldera_forms_admin_footer"
	 */
	public static function admin_alerts(){
		$optin_status = Caldera_Forms_Tracking::tracking_optin_status();
		if(  'dismiss' !== $optin_status && 0 == $optin_status ){
			$base_url = add_query_arg( 'page', 'caldera-forms' );
			$base_url = add_query_arg( 'cal_tracking_nonce', wp_create_nonce(), $base_url );
			$allow = add_query_arg( 'cal_tracking', 1, $base_url );
			$dismiss = add_query_arg( 'cal_tracking', 'dismiss', $base_url );
			$message[] = __( 'Allow us to track basic usage data and receive a 10% discount at CalderaWP.com.', 'caldera-forms' );
			$message[] = __( 'No form entries, or sensitive data will be saved.', 'caldera-forms' );
			$message[] = __( 'This data is used to help improve Caldera Forms and it will never be shared with a third-party.', 'caldera-forms' );
			$message[] = __( 'If you choose to allow us to track data, a 10% discount code for CalderaWP.com will be sent to the admin email for this site.', 'caldera-forms' );
			$message[] = sprintf( '<em><a href="https://calderawp.com/?post_type=doc&p=17228" target="_blank" title="%s">%s</a></em>',
				esc_html__( 'Information on CalderaWP site about usage tracking', 'caldera-forms' ),
				esc_html__( 'Learn more about what is tracked here.', 'caldera-forms' )
			);
			$message = '<p>' . implode( ' ', $message ) . '</p>';

			$message .= sprintf( '<p style="display:inline;float:left;" ><a type="button" class="button button-secondary" href="%s">%s</a></p>', esc_url_raw( $dismiss ), __( 'No Thanks', 'caldera-forms') );

			$message .= sprintf( '<p style="display:inline; float:right;"><a type="button" class="button button-primary" href="%s">%s</a>', esc_url_raw( $allow ), __( 'Help Us & Save', 'caldera-forms') );

			self::create_admin_notice( __( 'Help us improve Caldera Forms & Get 10% Off At CalderaWP.com', 'caldera-forms' ), $message, false );
		}else{
			$notices = self::get_admin_alerts();
			if( ! empty( $notices ) ){
				shuffle( $notices );
				$notice = $notices[0];

				if( is_array( $notice ) && isset( $notice[ 'title' ], $notice[ 'content' ] ) ){
					unset( $notices[0]);
					update_option( '_cf_admin_alerts', $notices );
					self::create_admin_notice( $notice[ 'title' ], $notice[ 'content' ] );
				}
			}
		}



	}

	/**
	 * Create an admin notice
	 *
	 * @since 1.3.4
	 *
	 * @param $title
	 * @param $content
	 */
	public static function create_admin_notice( $title, $content, $sanitize = true  ){
		if( $sanitize ) {
			$content = wp_kses( $content, wp_kses_allowed_html( 'post' ) );
		}
		?>
		<div
			class="ajax-trigger"
			data-modal="cf-admin-notice"
			data-modal-title="<?php echo esc_html( $title ); ?>"
			data-template="#<?php echo esc_attr( sanitize_key( 'admin-modal' .  $title ) ); ?>"
			data-modal-height="300"
			data-modal-width="650"
			data-autoload="true"
		>
		</div>
		<script type="text/html" id="<?php echo esc_attr( sanitize_key('admin-modal' . $title ) ); ?>">
			<?php echo $content; ?>
		</script>
<?php
	}

	/**
	 * Get any admin alert/notices from remote API
	 *
	 * @since 1.3.4
	 *
	 * @return array|mixed|void
	 */
	public static function get_admin_alerts(){
		$notices = get_option( '_cf_admin_alerts', array() );
		if( ! is_array( $notices) ){
			$notices = array();
		}


		$day_ago = time() - DAY_IN_SECONDS;
		$last_check = get_option( '_cf_last_alert_check', false );

		if( false === $last_check || $day_ago > $last_check   ){
			$url       = Caldera_Forms_Tracking::api_url( 'notices' );
			$r_notices = Caldera_Forms_Tracking::send_to_api( $url );
			if ( ! empty( $r_notices ) ) {
				$notices = array_merge( $notices, $r_notices );
				update_option( '_cf_admin_alerts', $notices );
			}

		}

		update_option( '_cf_last_alert_check', time() );



		return $notices;

	}

	/**
	 * Watch for tracking optin change and update if needed
	 *
	 * @uses "admin_init"
	 *
	 * @since 1.3.5
	 */
	public static function watch_tracking(){
		if( isset( $_GET[ 'cal_tracking' ], $_GET[ 'cal_tracking_nonce' ] ) ){
			if( wp_verify_nonce( $_GET[ 'cal_tracking_nonce' ] ) ) {
				$value = $_GET[ 'cal_tracking' ];
				if( is_numeric(  $value ) ) {

					update_option( '_caldera_forms_tracking_allowed', absint( $value ) );
					if( 1 == $value ){
						$response = wp_remote_get( add_query_arg( 'cf-optin-email', urlencode( get_option( 'admin_email') ), 'http://CalderaWP.com/' ) );
						/**
						 * Runs after tracking optin is sent to CalderaWP.com
						 *
						 * @since 1.3.5
						 *
						 * @param array|WP_Error Response data or WP_Error
						 */
						add_action( 'caldera_form_after_tracking_optin', $response );
					}
				}elseif( 'dismiss' == trim( $value ) ){
					update_option( '_caldera_forms_tracking_allowed', trim( $value ) );
				}
			}
		}
	}

	/**
	 * Filter permissions used in self::save_form()
	 *
	 * @since 1.3.5
	 *
	 * @uses "caldera_forms_manage_cap"
	 *
	 * @param string $cap A capability. By default "manage_options".
	 * @param string $context Context to check in.
	 * @param array|null $form Form config if it was passed.
	 *
	 * @return int|string
	 */
	public static function save_form_cap_filter( $cap, $context, $form ){
		if( ! is_array( $form ) ){
			return $cap;
		}

		switch( $context ) {
			case 'export' :
				if( ! empty( $form[ 'pin_roles' ] ) ){
					if( isset( $form[ 'pin_roles' ][ 'access_role' ] ) && is_array($form[ 'pin_roles' ][ 'access_role' ] ) ){
						foreach( $form[ 'pin_roles' ][ 'access_role' ] as $cap => $i ) {
							if( current_user_can( $cap ) ){
								break;

							}
						}
					}
				}

				break;
		}

		return $cap;

	}

}



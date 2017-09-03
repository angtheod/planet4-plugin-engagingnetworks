<?php

namespace P4EN\Controllers\Menu;

use P4EN\Controllers\P4EN_Ensapi_Controller;

if ( ! class_exists( 'P4EN_Pages_Posts_Controller' ) ) {

	/**
	 * Class P4EN_Pages_Posts_Controller
	 */
	class P4EN_Pages_Posts_Controller extends P4EN_Pages_Controller {

		/**
		 * Hooks the method that Creates the menu item for the current controller.
		 */
		public function load() {
			parent::load();
			add_action( 'admin_init', array( $this, 'add_enpage_post_type' ) );
			add_filter( 'manage_edit-p4en_page_columns', array( $this, 'prepare_columns' ) );
			add_filter( 'manage_edit-p4en_page_sortable_columns', array( $this, 'sortable_columns' ) );
			add_action( 'manage_posts_custom_column' , array( $this, 'filter_columns_data' ), 11, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'add_filters' ), 11 );
			add_action( 'manage_posts_extra_tablenav', array( $this, 'add_sync' ), 11 );
		}

		/**
		 * Create menu/submenu entry.
		 */
		public function create_admin_menu() {

			$current_user = wp_get_current_user();

			if ( in_array( 'administrator', $current_user->roles, true ) || in_array( 'editor', $current_user->roles, true ) ) {
				add_submenu_page(
					P4EN_PLUGIN_SLUG_NAME,
					__( 'EN Pages', 'planet4-engagingnetworks' ),
					__( 'EN Pages', 'planet4-engagingnetworks' ),
					'edit_pages',
					'edit.php?post_type=p4en_page',
					null//array( $this, 'prepare_pages' )
				);
			}
		}

		/**
		 * Add new post type 'enpage'
		 */
		public function add_enpage_post_type() {

			$labels = array(
				'name'               => __( 'EN Pages', 'planet4-engagingnetworks' ),
				'singular_name'      => 'EN Page',
				'menu_name'          => __( 'EN Pages', 'planet4-engagingnetworks' ),
				'name_admin_bar'     => 'EN Page',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New EN Page',
				'new_item'           => 'New EN Page',
				'edit_item'          => 'Edit EN Page',
				'view_item'          => 'View EN Page',
				'all_items'          => 'All EN Pages',
				'search_items'       => 'Search EN Pages',
				'parent_item_colon'  => 'Parent EN Pages',
				'not_found'          => 'No EN Page Found',
				'not_found_in_trash' => 'No EN Page Found in Trash',
			);
			$args = array(
				'labels'              => $labels,
				'public'              => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_nav_menus'   => true,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'menu_position'       => null,
				'menu_icon'           => 'dashicons-admin-appearance',
				'capability_type'     => 'post',
				'capabilities' => array(
					'create_posts' => false, // Removes support for the "Add New" function ( use 'do_not_allow' instead of false for multisite set ups )
				),
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
				'has_archive'         => true,
				'rewrite'             => array(
					'slug' => 'p4en_page',
				),
				'query_var'           => true,
			);
			register_post_type( 'p4en_page', $args );
		}

		/**
		 *
		 */
		public function add_filters() {
			if ( isset($_GET['post_type'] )) {
				if ( 'p4en_page' === $_GET['post_type'] ) {
					$current_user = wp_get_current_user();
					$pages_settings = get_user_meta( $current_user->ID, 'p4en_pages_settings', true );

					if ( isset( $pages_settings['p4en_pages_subtype'] ) && $pages_settings['p4en_pages_subtype'] ) {
						$params['type'] = $pages_settings['p4en_pages_subtype'];

						if ( isset( $pages_settings['p4en_pages_status'] ) && 'all' !== $pages_settings['p4en_pages_status'] ) {
							$params['status'] = $pages_settings['p4en_pages_status'];
						}
					}

					$data = [
						'pages_settings' => $pages_settings,
						'subtypes'       => self::SUBTYPES,
						'statuses'       => self::STATUSES,
						'domain'         => 'planet4-engagingnetworks',
					];

					$this->view->pages_filters( $data );
				}
			}
		}

		/**
		 *
		 */
		public function add_sync() {
			if (isset($_GET['post_type'])) {
				if ( 'p4en_page' == $_GET['post_type'] ) {
					submit_button( __( 'Sync', 'planet4-engagingnetworks' ), 'primary', 'p4en_pages_posts_sync_button' );
				}
			}
		}

		/**
		 * Pass all needed data to the view object for the datatable page.
		 */
		public function prepare_pages() {
			$data   = [];
			$pages  = [];
			$params = [];
			$pages_settings = [];

			$current_user = wp_get_current_user();

			$pages_settings = get_user_meta( $current_user->ID, 'p4en_pages_settings', true );
			if ( isset( $pages_settings['p4en_pages_subtype'] ) && $pages_settings['p4en_pages_subtype'] ) {
				$params['type'] = $pages_settings['p4en_pages_subtype'];

				if ( isset( $pages_settings['p4en_pages_status'] ) && 'all' !== $pages_settings['p4en_pages_status'] ) {
					$params['status'] = $pages_settings['p4en_pages_status'];
				}

				$ens_api = new P4EN_Ensapi_Controller();
				$main_settings = get_option( 'p4en_main_settings' );

				if ( isset( $main_settings['p4en_private_api'] ) && $main_settings['p4en_private_api'] ) {
					// Check if the authentication API call is cached.
					$ens_auth_token = get_transient( 'ens_auth_token' );

					if ( false !== $ens_auth_token ) {
						$response = $ens_api->get_pages( $ens_auth_token, $params );

						if ( is_array( $response ) && $response['body'] ) {
							$pages = json_decode( $response['body'], true );
						} else {
							$this->error( $response );
						}
					} else {
						$ens_private_token = $main_settings['p4en_private_api'];
						$response = $ens_api->authenticate( $ens_private_token );

						if ( is_array( $response ) && $response['body'] ) {
							// Communication with ENS API is authenticated.
							$body           = json_decode( $response['body'], true );
							$ens_auth_token = $body['ens-auth-token'];
							// Time period in seconds to keep the ens_auth_token before refreshing. Typically 1 hour.
							$expiration     = (int) ($body['expires'] / 1000) - time();

							set_transient( 'ens_auth_token', $ens_auth_token, $expiration - time() );

							$response = $ens_api->get_pages( $ens_auth_token, $params );

							if ( is_array( $response ) && $response['body'] ) {
								$pages = json_decode( $response['body'], true );

								//
								if ( $pages ) {
									foreach ( $pages as $page ) {
										$post_data = [
											'post_title'   => wp_strip_all_tags( $page['name'] ),
											'post_content' => ' ',
											'post_status'  => 'publish',
											'post_type'    => 'p4en_page',
										];
										$post_id   = wp_insert_post( $post_data );
										$post_meta = [
											'id'             => $page['id'],
											'type'           => $page['type'],
											'name'           => $page['name'],
											'createdOn'      => $page['createdOn'],
											'modifiedOn'     => $page['modifiedOn'],
											'campaignBaseUrl'=> $page['campaignBaseUrl'],
											'campaignStatus' => $page['campaignStatus'],
											'title'          => $page['title'],
											'subType'        => $page['subType'],
										];

										$post_meta = $this->valitize( $post_meta );
										if ( false === $post_meta ) {
											$this->error( __( 'Pages did not update!', 'planet4-engagingnetworks' ) );
											return false;
										}
										// Avoid using serialize/unserialize due to PHP Object Injection vulnerability.
										// see https://www.owasp.org/index.php/PHP_Object_Injection
										update_post_meta( $post_id, 'p4en_page_meta', wp_json_encode( $post_meta ) );
									}
								}
								//

							} else {
								$this->error( $response );
							}
						} else {
							$this->error( $response );
						}
					}
				} else {
					$this->warning( __( 'Plugin Settings are not configured well!', 'planet4-engagingnetworks' ) );
				}
			} else {
				$this->notice( __( 'Select Subtype', 'planet4-engagingnetworks' ) );
			}
		}

		/**
		 *
		 *
		 * @param $columns
		 *
		 * @return array
		 */
		public function prepare_columns( $columns ) : array {
			return [
				'id'             => __( 'ID', 'planet4-engagingnetworks' ),
				'type'           => __( 'Type', 'planet4-engagingnetworks' ),
				'name'           => __( 'Name', 'planet4-engagingnetworks' ),
				'createdOn'      => __( 'Created', 'planet4-engagingnetworks' ),
				'modifiedOn'     => __( 'Modified', 'planet4-engagingnetworks' ),
				'campaignStatus' => __( 'Status', 'planet4-engagingnetworks' ),
				'en_title'       => __( 'Title', 'planet4-engagingnetworks' ),
				'subType'        => __( 'Subtype', 'planet4-engagingnetworks' ),
				'actions'        => __( 'Actions', 'planet4-engagingnetworks' ),
			];
		}

		/**
		 *
		 *
		 * @param $columns
		 *
		 * @return mixed
		 */
		public function sortable_columns( $columns ) {
			$columns['id']             = 'id';
			$columns['type']           = 'type';
			$columns['name']           = 'name';
			$columns['createdOn']      = 'createdOn';
			$columns['modifiedOn']     = 'modifiedOn';
			$columns['campaignStatus'] = 'campaignStatus';
			$columns['en_title']       = 'en_title';
			$columns['subType']        = 'subType';
			$columns['actions']        = 'actions';

			return $columns;
		}

		/**
		 *
		 *
		 * @param $column
		 * @param $post_id
		 */
		public function filter_columns_data( $column, $post_id ) {
			$post_meta = json_decode( get_post_meta( $post_id, 'p4en_page_meta' )[0], true );

			$post_meta['campaignStatus'] = ucfirst( $post_meta['campaignStatus'] );
			if ( ! $post_meta['subType'] ) {
				$post_meta['subType'] = strtoupper( $post_meta['type'] );
			}
			if( 'en_title' === $column ) {
				$post_meta[ $column ] = $post_meta['title'];
			}

			switch ( $post_meta['type'] ) {
				case 'dc':
					switch ( $post_meta['subType'] ) {
						case 'DCF':
							$post_meta['name'] = '<a class="p4en_link" href="' . esc_url( $post_meta['campaignBaseUrl'] . '/page/' . $post_meta['id'] . '/data/1' ) . '" title="" data-title="Open page in new tab" target="_blank">' . esc_html( $post_meta['name'] ) . '</a>';
							break;
						case 'PET':
							$post_meta['name'] = '<a class="p4en_link" href="' . esc_url( $post_meta['campaignBaseUrl'] . '/page/' . $post_meta['id'] . '/petition/1' ) . '" title="" data-title="Open page in new tab" target="_blank">' . esc_html( $post_meta['name'] ) . '</a>';
							break;
						default:
							$post_meta['name'] = '<a class="p4en_link" href="' . esc_url( $post_meta['campaignBaseUrl'] . '/page/' . $post_meta['id'] . '/petition/1' ) . '" title="" data-title="Open page in new tab" target="_blank">' . esc_html( $post_meta['name'] ) . '</a>';
					}
					break;
				case 'nd':
					$post_meta['name'] = '<a class="p4en_link" href="' . esc_url( $post_meta['campaignBaseUrl'] . '/page/' . $post_meta['id'] . '/donation/1' ) . '" title="" data-title="Open page in new tab" target="_blank">' . esc_html( $post_meta['name'] ) . '</a>';
					break;
			}

			$post_meta['type'] = P4EN_Pages_Controller::SUBTYPES[ $post_meta['subType'] ]['type'];
			$post_meta['subType'] = P4EN_Pages_Controller::SUBTYPES[ $post_meta['subType'] ]['subType'];

			if ( 'createdOn' === $column || 'modifiedOn' === $column ) {
				if ( $post_meta[ $column ] ) {
					$post_meta[ $column ] = date( 'M d, Y', $post_meta[ $column ] / 1000 );
				}
			}

			$this->view->column_data( $column, $post_meta[ $column ] );
		}

		/**
		 * Validates the settings input.
		 *
		 * @param array $settings The associative array with the settings that are registered for the plugin.
		 *
		 * @return bool
		 */
		public function validate( $settings ) : bool {
			// TODO: Implement validate() method.
			$has_errors = false;
			return ! $has_errors;
		}

		/**
		 * Sanitizes the settings input.
		 *
		 * @param array $settings The associative array with the settings that are registered for the plugin (Call by Reference).
		 */
		public function sanitize( &$settings ) {
			// TODO: Implement sanitize() method.
		}
	}
}
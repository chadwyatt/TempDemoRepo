<?php

namespace ProfitablePlugins\VMD;

class Updater {
    protected static $instance = null;
    private static $file;
	private $plugin;
	private $basename;
	private $active;
	private $username;
	private $repository;
	private $authorize_token;
	private $update_response;
    
    private function __construct() {
        $plugin = Plugin::get_instance();
        $this->plugin_slug = $plugin->get_plugin_slug();
        // $this->file = $file;
		add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );
	}

    public static function get_instance() {
        // If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
        }
        return self::$instance;
    }


    public function set_plugin_properties() {
		$this->plugin	= get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active	= is_plugin_active( $this->basename );
	}

	public function set_file( $file ){
		$this->file = $file;
	}

	private function get_repository_info() {
	    if ( is_null( $this->update_response ) ) { // Do we have a response?
	        if(substr($_SERVER['HTTP_HOST'], -6) == '.local'){
                $domain = 'http://smswordpress.local';
            } else {
                $domain = 'https://smswordpress.com';
            }
            $url = $domain.'/wp-json/profitable-plugins-updates/v1/ppu/update/'.$this->plugin_slug;

            $response = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ), true ); // Get JSON and parse it
            
            $this->update_response = $response; // Set it to our property
        }
	}

	public function initialize() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3);
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_action( 'in_plugin_update_message-'.plugin_basename( $this->file ), array( $this, 'add_text_to_plugin_udpate_notification'));
        
        // die( 'in_plugin_update_message-'.$this->basename );
    }
    
    public function add_text_to_plugin_udpate_notification($data){
        if(empty($data['package'])){
            printf('<span style="margin-top:5px; display:block;"><strong>UPDATES DISABLED:</strong> Please visit <a href="%s" target="_blank">%s</a> to renew/manage your subscription.</span>', $data['url'], $data['url']);
        }
    }

	public function modify_transient( $transient ) {
        if( property_exists( $transient, 'checked') ) { // Check if transient has a checked property
			if( $checked = $transient->checked ) { // Did Wordpress check for updates?
                $this->get_repository_info(); // Get the repo info
                $out_of_date = version_compare( $this->update_response['version'], $checked[ $this->basename ], 'gt' ); // Check if we're out of date
                if( $out_of_date ) {
					$new_files = $this->update_response['download_url']; // Get the ZIP
					$slug = current( explode('/', $this->basename ) ); // Create valid slug
					$plugin = array( // setup our plugin info
						'url' => $this->plugin["PluginURI"],
						'slug' => $slug,
						'package' => $new_files,
						'new_version' => $this->update_response['version']
					);
					$transient->response[$this->basename] = (object) $plugin; // Return it in response
				}
			}
		}
		return $transient; // Return filtered transient
	}

	public function plugin_popup( $result, $action, $args ) {
		if( ! empty( $args->slug ) ) { // If there is a slug
			if( $args->slug == current( explode( '/' , $this->basename ) ) ) { // And it's our slug
				$this->get_repository_info(); // Get our repo info
				// Set it to an array
				$plugin = array(
					'name'				=> $this->plugin["Name"],
					'slug'				=> $this->basename,
					'requires'			=> '3.3',
					'tested'			=> '4.4.1',
					'rating'			=> '100.0',
					'num_ratings'		=> '1',
					'downloaded'		=> '1',
					'added'				=> '2020-02-16',
					'version'			=> $this->update_response['version'],
					'author'			=> $this->plugin["AuthorName"],
					'author_profile'	=> $this->plugin["AuthorURI"],
					'last_updated'		=> $this->update_response['published_at'],
					'homepage'			=> $this->plugin["PluginURI"],
					'short_description' => $this->plugin["Description"],
					'sections'			=> array(
						'Description'	=> $this->plugin["Description"],
						'Updates'		=> $this->update_response['body'],
					),
					'download_link'		=> $this->update_response['download_url']
				);
				return (object) $plugin; // Return the data
			}
		}
		return $result; // Otherwise return default
	}

	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem; // Get global FS object
		$install_directory = plugin_dir_path( $this->file ); // Our plugin directory
		$wp_filesystem->move( $result['destination'], $install_directory ); // Move files to the plugin dir
		$result['destination'] = $install_directory; // Set the destination for the rest of the stack
		if ( $this->active ) { // If it was active
			activate_plugin( $this->basename ); // Reactivate
		}
		return $result;
	}

}

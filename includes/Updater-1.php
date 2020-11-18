<?php

namespace ProfitablePlugins\VMD;


class Updates {
    protected static $instance = null;
    protected $plugin_slug;
    
    private function __construct() {
        $plugin = Plugin::get_instance();
        $this->plugin_slug = $plugin->get_plugin_slug();
    }

    public function do_hooks() {
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('site_transient_update_plugins', array($this, 'push_update') );
    }

    public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
            self::$instance->do_hooks();
        }

        return self::$instance;
    }

    function plugin_info( $res, $action, $args ){
 
        // do nothing if this is not about getting plugin information
        if( 'plugin_information' !== $action ) {
            return false;
        }
        
        $plugin_slug = $this->plugin_slug; // we are going to use it in many places in this function
        
        // do nothing if it is not our plugin
        if( $plugin_slug !== $args->slug ) {
            return false;
        }
        
        // trying to get from cache first
        if( false == $remote = get_transient( 'profitable_plugins_update_' . $plugin_slug ) ) {
        
            // info.json is the file with the actual plugin information on your server
            if(substr($_SERVER['HTTP_HOST'], -6) == '.local'){
                $domain = 'smswordpress.local';
            } else {
                $domain = 'smswordpress.com';
            }
            $url = 'https://'.$domain.'/wp-json/profitable-plugins-updates/v1/ppu/update/'.$plugin_slug;
                
            $remote = wp_remote_get( $url, array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json'
                ) )
            );
        
            if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
                set_transient( 'profitable_plugins_update_' . $plugin_slug, $remote, 43200 ); // 12 hours cache
            }
        
        }
        
        if( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
        
            $remote = json_decode( $remote['body'] );
            $res = new stdClass();
        
            $res->name = $remote->name;
            $res->slug = $plugin_slug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->author = $remote->author; //'<a href="https://rudrastyh.com">Misha Rudrastyh</a>';
            $res->author_profile = $remote->author_profile; //'https://profiles.wordpress.org/rudrastyh';
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->requires_php = $remote->requires_php;
            $res->last_updated = $remote->last_updated;
            $res->sections = array(
                'description' => $remote->sections->description,
                'updates' => $remote->sections->udpates,
                // 'changelog' => $remote->sections->changelog
            );
        
            // in case you want the screenshots tab, use the following HTML format for its content:
            // <ol><li><a href="IMG_URL" target="_blank"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>
            // if( !empty( $remote->sections->screenshots ) ) {
            //     $res->sections['screenshots'] = $remote->sections->screenshots;
            // }
        
            // $res->banners = array(
            //     'low' => 'https://YOUR_WEBSITE/banner-772x250.jpg',
            //     'high' => 'https://YOUR_WEBSITE/banner-1544x500.jpg'
            // );
            return $res;
        
        }
        
        return false;
        
    }
    
    function push_update( $transient ){
 
        if ( empty($transient->checked) ) {
            return $transient;
        }
     
        // trying to get from cache first, to disable cache comment 10,20,21,22,24
        if( false == $remote = get_transient( $this->plugin_slug ) ) {
     
            if(substr($_SERVER['HTTP_HOST'], -6) == '.local'){
                $domain = 'smswordpress.local';
            } else {
                $domain = 'smswordpress.com';
            }
            $url = 'https://'.$domain.'/wp-json/profitable-plugins-updates/v1/ppu/update/'.$plugin_slug;

            // info.json is the file with the actual plugin information on your server
            $remote = wp_remote_get( $url, array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json'
                ) )
            );
     
            if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
                set_transient( $this->plugin_slug, $remote, 43200 ); // 12 hours cache
            }
     
        }
     
        if( $remote ) {
     
            $remote = json_decode( $remote['body'] );
     
            // your installed plugin version should be on the line below! You can obtain it dynamically of course 
            if( $remote && version_compare( '1.0', $remote->version, '<' ) && version_compare($remote->requires, get_bloginfo('version'), '<' ) ) {
                $res = new stdClass();
                $res->slug = $this->plugin_slug;
                $res->plugin = 'YOUR_PLUGIN_FOLDER/YOUR_PLUGIN_SLUG.php'; // it could be just YOUR_PLUGIN_SLUG.php if your plugin doesn't have its own directory
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;
                       $transient->response[$res->plugin] = $res;
                       //$transient->checked[$res->plugin] = $remote->version;
                   }
     
        }
            return $transient;
    }


}

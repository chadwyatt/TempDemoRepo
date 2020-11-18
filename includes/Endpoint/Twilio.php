<?php

namespace ProfitablePlugins\VMD\Endpoint;
use ProfitablePlugins\VMD;
use Twilio\Rest\Client;

class Twilio {
    protected static $instance = null;
    
    private function __construct() {
        $plugin = VMD\Plugin::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
	}

    public function do_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::$instance->do_hooks();
		}

        return self::$instance;
	}

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        $version = '1';
        $namespace = $this->plugin_slug . '/v' . $version;
        $endpoint = '/twilio';

        //GET /twilio/user
        register_rest_route( $namespace, $endpoint.'/generalSettings', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_general_settings' ),
                'permission_callback'   => array( $this, 'permissions_check_all' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/user
        register_rest_route( $namespace, $endpoint.'/user', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_user' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/phone_numbers
        register_rest_route( $namespace, $endpoint.'/phone_numbers/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_phone_numbers' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/campaigns
        register_rest_route( $namespace, $endpoint.'/campaigns', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_campaigns' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //POST /twilio/campaign
        register_rest_route( $namespace, $endpoint.'/campaign', array(
            array(
                'methods'               => \WP_REST_Server::CREATABLE,
                'callback'              => array( $this, 'create_campaign' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //POST /twilio/campaign/<ID>
        register_rest_route( $namespace, $endpoint.'/campaign/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::EDITABLE,
                'callback'              => array( $this, 'update_campaign' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/campaign
        register_rest_route( $namespace, $endpoint.'/campaign/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_campaign' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        
        //GET /twilio/campaignPhoneRecords/<ID>
        register_rest_route( $namespace, $endpoint.'/campaignPhoneRecords/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_campaign_phone_records' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));
        
        
        //GET /twilio/import_list/<ID>
        register_rest_route( $namespace, $endpoint.'/import_list/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'import_list' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        

        //POST /twilio/campaign
        register_rest_route( $namespace, $endpoint.'/campaign', array(
            array(
                'methods'               => \WP_REST_Server::DELETABLE,
                'callback'              => array( $this, 'delete_campaign' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/settings
        register_rest_route( $namespace, $endpoint.'/settings', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'get_twilio' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        //GET /twilio/check_connection
        register_rest_route( $namespace, $endpoint.'/check_connection', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'check_connection' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));

        register_rest_route( $namespace, $endpoint.'/settings', array(
            array(
                'methods'               => \WP_REST_Server::EDITABLE,
                'callback'              => array( $this, 'update_campaign' ),
                'permission_callback'   => array( $this, 'permissions_check' ),
                'args'                  => array(),
            ),
        ));
    }

    public function get_general_settings(){
        $login_url = wp_login_url();
        $rest_url = get_rest_url();
        return new \WP_REST_Response( array(
            'success' => true,
            'value' => array(
                "login_url" => $login_url,
                "run_campaigns_url" => $rest_url.$this->plugin_slug."/v1/ppvmd/run_campaigns"
            )
        ), 200 );
    }

    public function get_user(){
        $user = wp_get_current_user();
        return new \WP_REST_Response( array(
            'success' => true,
            'value' => $user
        ), 200 );
    }
    
    public function get_phone_numbers( $request ) {
        $meta = get_post_meta($request->get_param('ID'));
        
        $sid = $meta['sid'][0];
        $account_sid = $meta['sid'][0];
        $token = $meta['token'][0];
        
        //get incoming twilio numbers
        $url = "https://$sid:$token@api.twilio.com/2010-04-01/Accounts/$account_sid/IncomingPhoneNumbers.json";
        $response = wp_remote_get($url);
        $data = wp_remote_retrieve_body( $response );
        $incoming_phone_numbers = json_decode($data);

        //get outgoing/verified caller ids
        $url = "https://$sid:$token@api.twilio.com/2010-04-01/Accounts/$account_sid/OutgoingCallerIds.json";
        $response = wp_remote_get($url);
        $data = wp_remote_retrieve_body( $response );
        $outgoing_caller_ids = json_decode( $data );

        $phone_numbers = array("incoming_phone_numbers" => $incoming_phone_numbers, "outgoing_caller_ids" => $outgoing_caller_ids);
        
        return new \WP_REST_Response( array(
            'success' => true,
            'value' => $phone_numbers
        ), 200 );
    }

    public function get_campaigns($request) {
        $current_user_id = get_current_user_id();

        $campaigns = get_posts(array(
            "post_type" => "pp_vmd_campaign", 
            "numberposts" => -1,
            "author" => $current_user_id
        ));
        // print_r($campaigns);

        foreach($campaigns as $campaign){
            $meta = get_post_meta($campaign->ID);
            // $campaign->meta = $meta;
            foreach($meta as $key => $value){
                if($key === "selectedPhoneNumber")
                    $campaign->$key = unserialize($value[0]);
                elseif($key !== "phoneNumbers")
                    $campaign->$key = $value[0];
            }
            $data[] = $campaign;
        }

        return new \WP_REST_Response( array(
            'success'   => $id > 0,
            'value'     => $data
        ), 200 );
    }

    public function import_list($request){
        $ID = $request->get_param('ID');
        $list_meta = get_post_meta($ID);
        $phone_numbers = [];
        foreach($list_meta as $key => $value){
            if(strstr($key, '_phone_') !== false && $value[0] !== ''){
                $phone_numbers[] = $value[0];
            }
        }
        $text = implode("\n", $phone_numbers);

        return new \WP_REST_Response( array(
            'success'   => $id > 0,
            'value'     => $text
        ), 200 );
    }

    public function get_campaign($request) {
        $ID = $request->get_param('ID');
        $campaign = get_post($ID);
        if($campaign->post_author != get_current_user_id()){
            return new \WP_REST_Response( array(
                'error'   => 'Unauthorized',
                'value'     => array("code" => 403)
            ), 401 );
        }
        $meta = get_post_meta($ID);
        foreach($meta as $key => $value){
            if($key === "selectedPhoneNumber")
                $campaign->$key = unserialize($value[0]);
            else
                $campaign->$key = $value[0];
        }
        $campaign->host = $_SERVER['HTTP_HOST'];
        $campaign->site_url = site_url();
        
        //get gpapiscraper lists if they exist
        $scraper_posts = get_posts(array(
            "post_type" => "gpapiscraper",
            "numberposts" => -1,
            "author" => $current_user_id
        ));
        $lists = [];
        foreach($scraper_posts as $list){
            $lists[] = array('ID' => $list->ID, 'post_title' => $list->post_title);
        }
        $campaign->lists = $lists;


        return new \WP_REST_Response( array(
            'success'   => $id > 0,
            'value'     => $campaign
        ), 200 );
    }

    //get_campaign_phone_records
    public function get_campaign_phone_records($request){
        $ID = $request->get_param('ID');
        $phone_numbers = get_posts(array(
            "post_type" => "pp_vmd_phone_number",
            "numberposts" => -1,
            // "meta_key" => "status",
            // "meta_value" => "queued",
            "post_status" => "publish",
            "post_parent" => $ID
        ));
        foreach($phone_numbers as $phone_number){
            $meta = get_post_meta($phone_number->ID);
            $phone_number->meta = $meta;
            $data[] = $phone_number;
        }
        return new \WP_REST_Response( array(
            'success'   => $id > 0,
            'value'     => $data
        ), 200 );
    }


    public function create_campaign( $request ) {
        // $settings = $request->get_param( 'campaignSettings' );
        $data = $request->get_param( 'campaignSettings' );
        $my_post = array(
            'post_title' => $data['post_title'],
            'post_type' => 'pp_vmd_campaign',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );
        $ID = wp_insert_post( $my_post );

        if($ID > 0){
            update_post_meta($ID, 'sid', $data['sid']);
            update_post_meta($ID, 'token', $data['token']);
            update_post_meta($ID, 'selectedPhoneNumber', $data['selectedPhoneNumber']);
            update_post_meta($ID, 'phoneNumbers', $data['phoneNumbers']);
            update_post_meta($ID, 'audioFileUrl', $data['audioFileUrl']);
            update_post_meta($ID, 'active', $data['active']);
        }
        
        $campaign = get_post($ID);
        $meta = get_post_meta($ID);
        // $campaign->meta = $meta;
        foreach($meta as $key => $value){
            if($key === "selectedPhoneNumber")
                $campaign->$key = unserialize($value[0]);
            else
                $campaign->$key = $value[0];
        }

        return new \WP_REST_Response( array(
            'success'   => $ID > 0,
            'value'     => $campaign
        ), 200 );
    }

    public function update_campaign( $request ) {
        // $updated = update_option( 'ppvmd_twilio_settings', $request->get_param( 'campaignSettings' ) );
        $data = $request->get_param( 'campaignSettings' );
        $ID = $request->get_param('ID');
        $args = array(
            "ID" => $data['ID'],
            "post_title" => $data['post_title']
        );
        $update = wp_update_post($args);


        if($data['update_status_only'] !== "1"){
            update_post_meta($ID, 'sid', $data['sid']);
            update_post_meta($ID, 'token', $data['token']);
            update_post_meta($ID, 'selectedPhoneNumber', $data['selectedPhoneNumber']);
            update_post_meta($ID, 'phoneNumbers', $data['phoneNumbers']);
            update_post_meta($ID, 'audioFileUrl', $data['audioFileUrl']);
            update_post_meta($ID, 'mobileOnly', $data['mobileOnly']);
        }
        update_post_meta($ID, 'active', $data['active']);

        $campaign = get_post($ID);
        $meta = get_post_meta($ID);
        foreach($meta as $key => $value){
            if($key === "selectedPhoneNumber")
                $campaign->$key = unserialize($value[0]);
            else
                $campaign->$key = $value[0];
        }

        return new \WP_REST_Response( array(
            'success'   => $updated,
            'value'     => $campaign
        ), 200 );
    }

    public function delete_campaign( $request ) {
        $deleted = wp_delete_post($request->get_param('ID'), true);
        $child_posts = get_posts(array(
            "post_parent" => $deleted->ID
        ));
        foreach($child_posts as $post){
            wp_delete_post($post->ID, true);
        }
        return new \WP_REST_Response( array(
            'success'   => $deleted !== false && $deleted !== null,
            'value'     => $deleted
        ), 200 );
    }

    public function get_twilio( $request ) {
        $twilio_settings = get_option( 'ppvmd_twilio_settings' );
        return new \WP_REST_Response( array(
            'success'   => true,
            'value'     => $twilio_settings
        ), 200 );
    }

    public function check_connection(){

        return new \WP_REST_Response( array(
            'success'   => true,
            'value'     => $twilio_settings
        ), 200 );
    }

    /**
     * Check if a given request has access to update a setting
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function permissions_check( $request ) {
        return current_user_can( 'read' );
    }

    public function permissions_check_all() {
        return true;
    }
}

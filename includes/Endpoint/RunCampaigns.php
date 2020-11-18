<?php

namespace ProfitablePlugins\VMD\Endpoint;
use ProfitablePlugins\VMD;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;

class RunCampaigns {
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
            // self::run_campaigns();
		}

        return self::$instance;
    }
    
    public function register_routes() {
        $version = '1';
        $namespace = $this->plugin_slug . '/v' . $version;
        $endpoint = '/ppvmd';

        //GET /ppvmd/run_campaigns
        register_rest_route( $namespace, $endpoint.'/run_campaigns', array(
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array( $this, 'run_campaigns' ),
                'permission_callback'   => array( $this, 'permissions_check_all' ),
                'args'                  => array(),
            ),
        ));

        //POST /ppvmd/twilio_twiml
        register_rest_route( $namespace, $endpoint.'/twilio_twiml', array(
            array(
                'methods'               => \WP_REST_Server::EDITABLE,
                'callback'              => array( $this, 'twilio_twiml' ),
                'permission_callback'   => array( $this, 'permissions_check_all' ),
                'args'                  => array(),
            ),
        ));

        //POST /ppvmd/twilio_status_callback
        register_rest_route( $namespace, $endpoint.'/twilio_status_callback/(?P<ID>\d+)', array(
            array(
                'methods'               => \WP_REST_Server::EDITABLE,
                'callback'              => array( $this, 'twilio_status_callback' ),
                'permission_callback'   => array( $this, 'permissions_check_all' ),
                'args'                  => array(),
            ),
        ));
    }


    public function run_campaigns() {
        $campaigns = get_posts(array(
            "post_type" => "pp_vmd_campaign", 
            "numberposts" => -1,
            "meta_key" => "active",
            "meta_value" => true,
            "post_status" => "publish"
        ));

        foreach($campaigns as $campaign){
            $campaign_meta = get_post_meta($campaign->ID);
            $campaign->meta = $campaign_meta;
            $selectedPhoneNumber = unserialize($campaign_meta['selectedPhoneNumber'][0]);
            $campaign->fromPhoneNumber = $selectedPhoneNumber[0]['id'];
            // $campaign->fromPhoneNumber = $selectedPhoneNumber;

            //have phone numbers been queued?
            //if not, create the phone number child posts
            if($campaign_meta["phone_numbers_queued"][0] !== "1"){
                $phone_numbers = explode("\n", $campaign_meta["phoneNumbers"][0]);

                // print_r($campaign);
                // print_r($phone_numbers);

                foreach($phone_numbers as $phone_number){
                    
                    //check for existing record
                    $existing_phone_number = get_post(array(
                        'post_title' => $phone_number,
                        'post_parent' => $campaign->ID,
                        'post_type' => 'pp_vmd_phone_number'
                    ));

                    //add record if not existing
                    if(true || !$existing_phone_number){
                        $post = array(
                            'post_title' => $phone_number,
                            'post_type' => 'pp_vmd_phone_number',
                            'post_status' => 'publish',
                            'post_author' => $campaign->post_author,
                            'post_parent' => $campaign->ID
                        );
                        $ID = wp_insert_post( $post );
                        update_post_meta($ID, 'status', 'queued');
                    }
                }

                //mark campaign as done with queueing the phone numbers
                update_post_meta($campaign->ID, "phone_numbers_queued", true);

                //get the phone numbers
                $phone_numbers = get_posts(array(
                    "post_type" => "pp_vmd_phone_number",
                    "numberposts" => 30,
                    "meta_key" => "status",
                    "meta_value" => "queued",
                    "post_status" => "publish",
                    "post_parent" => $campaign->ID
                ));
                update_post_meta($campaign->ID, "total_phone_numbers", count($phone_numbers));
            } else {
                //this campaign is already in progress and/or the phone number records have already been created
                //get the phone numbers
                $phone_numbers = get_posts(array(
                    "post_type" => "pp_vmd_phone_number",
                    "numberposts" => 30,
                    "meta_key" => "status",
                    "meta_value" => "queued",
                    "post_status" => "publish",
                    "post_parent" => $campaign->ID
                ));
            }

            // if none, then we are done and need to update the campaign as completed
            if(count($phone_numbers) === 0){
                update_post_meta($campaign->ID, 'active', false);
                update_post_meta($campaign->ID, 'status', 'completed');
            } else {
                //initiate the phone calls
                foreach($phone_numbers as $phone_number){
                    //retrieve phone_number meta again. It's possible it was updated via another process
                    //due to the sleep() command delay in the vmd function below
                    $latest_phone_status = get_post_meta($phone_number->ID, 'status', true);
                    if($latest_phone_status === 'queued'){
                        update_post_meta($phone_number->ID, 'status', 'in-progress');
                        $result = $this->vmd($campaign, $phone_number);
                        $phone_number->result = $result;
                        if($result)
                            $voicemails[] = $phone_number;
                    }
                }
            }
        }

        return new \WP_REST_Response( array(
            'success'   => true,
            'value'     => array("campaigns" => $campaigns, "voicemails" => $voicemails)
        ), 200 );
    }

    public function permissions_check_all() {
        return true;
    }

    public function vmd($campaign, $phone_number){ 
        // print_r($campaign);
        // print_r($phone_number);
        // die();
        if($campaign->meta['sid'][0] == "" || $campaign->meta['token'][0] == "")
            return false;

        $client = new Client($campaign->meta['sid'][0], $campaign->meta['token'][0]);
        
        //check for type = mobile?
        if($campaign->meta['mobileOnly'][0] == true){
            $lookup = $client->lookups->v1->phoneNumbers($phone_number->post_title)->fetch(["type" => ["carrier"]]);
            update_post_meta($phone_number->ID, 'lookup', $lookup);
            if($lookup->carrier['type'] !== 'mobile'){
                update_post_meta($phone_number->ID, 'status', 'Not mobile');
                return;
            }
        }

        $client->account->calls->create(  
            $phone_number->post_title,
            $campaign->fromPhoneNumber,
            array(
                "url" => get_rest_url().$this->plugin_slug."/v1/ppvmd/twilio_twiml?audioFileUrl=".$campaign->meta['audioFileUrl'][0],
                "timeout" => 4,
                "record" => true,
                "statusCallback" => get_rest_url().$this->plugin_slug."/v1/ppvmd/twilio_status_callback/".$phone_number->ID,
			    "statusCallbackEvent" => array("answered","completed"),
			    "machineDetection" => "DetectMessageEnd"
            )
        );
        sleep(1);

        $client->account->calls->create(  
            $phone_number->post_title,
            $campaign->fromPhoneNumber,
            array(
                "url" => get_rest_url().$this->plugin_slug."/v1/ppvmd/twilio_twiml?audioFileUrl=".$campaign->meta['audioFileUrl'][0],
                "timeout" => 4,
                "record" => true,
                "statusCallback" => get_rest_url().$this->plugin_slug."/v1/ppvmd/twilio_status_callback/".$phone_number->ID,
			    "statusCallbackEvent" => array("answered","completed"),
			    "machineDetection" => "DetectMessageEnd"
            )
        );
        sleep(1);

        return true;
    }

    public function twilio_twiml($request){
        $response = new VoiceResponse();
        $AnsweredBy = $request->get_param("AnsweredBy");
        
        //if not human, play the audio file
        if($AnsweredBy !== null && $AnsweredBy !== 'human'){	
            $response->play($request->get_param('audioFileUrl'));
        } else {
            //if human, hangup
            $response->hangup();
        }   

        header("Content-type: text/xml");
        echo $response;
        die();
    }

    public function twilio_status_callback($request){
        //update phone number record
        //add recording url
        $data = $request->get_body_params();

        $ID = $request->get_param('ID');
        update_post_meta($ID, 'status', $data["CallStatus"]);
        update_post_meta($ID, 'modified', $data["Timestamp"]);
        if($data["RecordingUrl"] != ""){
            update_post_meta($ID, 'RecordingUrl', $data["RecordingUrl"]);
        }

        return new \WP_REST_Response( array(
            'success'   => true,
            'value'     => 'OK'
        ), 200 );
    }
}

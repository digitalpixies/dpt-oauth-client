<?php
/*
Plugin Name: OAuth Client by DigitialPixies
Plugin URI: http://wordpress.digitalpixies.com/dpt-oauth-client
Description: Connect to an OAuth server to access protected contents
Version: 1.0.0
Author: Robert Huie
Author URI: http://DigitalPixies.com
License: GPLv2
*/

if(!class_exists("dpt_oauth_client")) {
	class dpt_oauth_client {
		public static $data = null;
		public static function RegisterHooks() {
      add_action('show_user_profile', 'dpt_oauth_client::OAuthProfileCRUD');
      add_action('edit_user_profile', 'dpt_oauth_client::OAuthProfileCRUD');
			add_action('admin_menu', 'dpt_oauth_client::AdminMenu');
			add_action('admin_init', 'dpt_oauth_client::AdminInit');
			add_action('admin_enqueue_scripts', 'dpt_oauth_client::EnableCSSJS');
			//is_user_logged_in already checked by wp core
			add_action('wp_ajax_dpt-oauth-ajax', 'dpt_oauth_client::AJAX');
			add_filter('template_redirect', 'dpt_oauth_client::ProcessOAuthCallback');

			add_rewrite_tag('%dpt-oauth-client%', '(.*)');
			add_rewrite_rule('^dpt-oauth-callback$', 'index.php?dpt-oauth-client=1', 'top');

		}
		//used to determine if we should process the page request as ajax
		public static function ProcessOAuthCallback() {
			if(get_query_var('dpt-oauth-client')) {
				if(isset($_REQUEST['code'])) {
					$code = preg_replace('/[^a-z0-9\/\-\_]+/i','', $_REQUEST['code']);

					$callbackurl = get_home_url(null, 'dpt-oauth-callback');
					$data=array(
						'client_id'=>get_option(__CLASS__.'_id'),
						'client_secret'=>get_option(__CLASS__.'_secret'),
						'grant_type'=>'authorization_code',
						'redirect_uri'=>$callbackurl,
						'code'=>$code
						);
					$postData=http_build_query($data);
					$options = array('http'=>array(
						'method'=>'POST',
						'header'=>"Content-type: application/x-www-form-urlencoded\r\n"
							.'Content-Length: '.strlen($postData),//."\r\n"
					//		.'Authorization: Basic '.base64_encode(get_option('dpt_oauth_client_id').":".get_option('dpt_oauth_client_secret')),
						'content'=>$postData
						));
					$context = stream_context_create($options);
					$token_url=get_option(__CLASS__.'_token_url');
					$output["success"]=false;
					$output["tokenResponse"]=json_decode(file_get_contents($token_url, false, $context),$assoc=true);
//					$output["data"]=$data;
//					$output["options"]=$options;
					if(empty($output["tokenResponse"]["refresh_token"])) {
						$output["message"]="Problems getting refresh_token";
						header('Content-Type: application/json');
						print json_encode($output);
						exit();
					}
					$output["success"]=true;
					update_user_option(get_current_user_id(), __CLASS__.'_auth', $output["tokenResponse"]["refresh_token"]);
				}
				else if(isset($_REQUEST['error'])) {
					switch($_REQUEST['error']) {
						case "access_denied":
							//do nothing so it can gracefully redirect. we probably should put a message back to end user
							break;
						default:
							header('Content-Type: application/json');
							print json_encode($_REQUEST);
							exit();
							break;
					}
				}
				//redirect to the profile page
				wp_redirect(get_dashboard_url(get_current_user_id(),'profile.php'), 302);
				exit();
			}
		}
		public static function EnableCSSJS($hook) {
      wp_register_style('localizedbootstrap', plugin_dir_url(__FILE__).'includes/css/localizedbootstrap.css');
      wp_register_style('localizedbootstrap', plugins_url('includes/css/localizedbootstrap-theme.css', __FILE__));
      wp_enqueue_style('localizedbootstrap');

      wp_register_script('angular-ui', plugin_dir_url(__FILE__).'includes/js/vendor.js', array(), "2.5.0", true);
      wp_enqueue_script('angular-ui');

			wp_register_script('oauth-client', plugin_dir_url(__FILE__).'includes/js/scripts.js', array("angular-ui"), "2.5.0", true);
			wp_enqueue_script('oauth-client');
			$params['ajax_url']=admin_url('admin-ajax.php');
			wp_localize_script('oauth-client', 'wordpress', $params);
    }
    public static function OAuthProfileCRUD() {
			$label = get_option(__CLASS__.'_label');
			$has_auth_code = empty(get_user_option(__CLASS__.'_auth'))?"false":"true";
			$has_resource_url = !empty(get_option(__CLASS__.'_resource_url'));
			$client_id = get_option(__CLASS__.'_id');
			$scope = get_option(__CLASS__.'_scope');
			$callbackurl = get_home_url(null, 'dpt-oauth-callback');
			$auth_url=get_option(__CLASS__.'_auth_url');
			$authorize_url = $auth_url."?response_type=code&client_id={$client_id}&scope={$scope}&state=xyz&redirect_uri=".urlencode($callbackurl);
			$auth_querystring=get_option(__CLASS__.'_auth_querystring');
			$invokeHTML="";
//			$debug["resource_url"]=get_option(__CLASS__.'_resource_url');
//			$debugHTML = "<pre>".htmlentities(print_r($debug, true))."</pre>";
			if($has_resource_url) {
				$invokeHTML=<<<EOF
<button type="button" class="button ng-hide" ng-click="Invoke()" ng-show="control.has_auth_code">Invoke</button>
<script type="text/ng-template" id="OAuthResourceResponseModal.html">
		<div class="modal-header">
				<h3 class="modal-title" id="modal-title">OAuth Resource Response</h3>
		</div>
		<div class="modal-body" id="modal-body">
			<table class="table">
				<tbody>
					<tr ng-repeat="(key, entry) in entries">
						<th>
							{{key}}
						</th>
						<td>
							{{entry}}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="modal-footer">
				<button class="btn btn-primary" type="button" ng-click="control.modal.OK()">OK</button>
		</div>
</script>
<div id="OAuthResourceResponseModal" class="localizedbootstrap">
</div>
EOF;
			}
			if(!empty($auth_querystring))
				$authorize_url .="&".$auth_querystring;
      print <<<EOF
<span ng-app="DPTOAuthClientApp" ng-controller="ProfileCtrl" ng-init="control.has_auth_code={$has_auth_code}">
<h3>Linked Accounts</h3>
<table class="form-table">
<tbody><tr id="oauth-link-section" class="">
	<th><label for="oauth-link">{$label}</label></th>
	<td>
		<a href="{$authorize_url}" class="button ng-hide" ng-show="!control.has_auth_code">Link</a>
		<button type="button" class="button ng-hide" ng-click="Unlink()" ng-show="control.has_auth_code">Unlink</button>

{$invokeHTML}
	</td>
</tr>
</tbody></table>
</span>
EOF;
    }
		public function dpt_oauth_client() {
			dpt_oauth_client::Initialize();
		}
		public static function Initialize() {
			session_start();
//			if(!isset($_SESSION[__CLASS__]))
				$_SESSION[__CLASS__]=array(
					'settings'=>array(
						'label'=>"OAuth Service",
						'id'=>"",
						'secret'=>"",
						'auth'=>"",//this needs to be dropped in favor of refresh token
						'auth_querystring'=>"",
						'scope'=>"",
						'refresh_token'=>"",
						'auth_url'=>"",
						'token_url'=>"",
						'resource_url'=>"",
						'resource_querystring'=>""
					)
				);
			dpt_oauth_client::$data=&$_SESSION[__CLASS__];
			add_action('init', 'dpt_oauth_client::RegisterHooks');
		}
		public static function AdminInit() {
			foreach(dpt_oauth_client::$data["settings"] as $name=>$value) {
				register_setting(__CLASS__, __CLASS__.'_'.$name);
			}
		}
		public static function AdminMenu() {
			add_options_page('OAuth Client by DigitalPixies', 'OAuth', 'manage_options', __CLASS__, 'dpt_oauth_client::AdminHTML');
    }
		public static function AdminHTML() {
			//add ui for flushing privileges flush_rewrite_rules( $hard );
			$callbackurl = get_home_url(null, 'dpt-oauth-callback');
			print <<<EOF
<div class="wrap">
  <h1>OAuth Client by DigitalPixies</h1>
  <form method="post" action="options.php">
EOF;
			settings_fields(__CLASS__);
			do_settings_sections(__CLASS__);

			include_once(dirname(__FILE__).'/admin.crud.html');
//			print file_get_contents(dirname(__FILE__).'/admin.crud.html');

			submit_button('Save Changes');
			print <<<EOF
  </form>
</div>
EOF;
//			print "<pre>".htmlentities(print_r(dpt_oauth_client::$data, true))."</pre>";
		}
		public static function AJAX() {
			global $wpdb;
			header('Content-Type: application/json');
			$output["success"]=false;
			switch($_REQUEST['call']) {
				case "unlink_auth_code":
					delete_user_option(get_current_user_id(), __CLASS__.'_auth');
					$output["success"]=true;
					break;
				case "invoke":
					$access_token = dpt_oauth_client::GetAccessToken();
					if($access_token) {
						$resource_url=get_option('dpt_oauth_client_resource_url').'?access_token='.$access_token;
//						$output["resourceRequest"]=$resource_url;
						$output["response"]=json_decode(file_get_contents($resource_url),$assoc=true);
					}
					$output["success"]=true;
					break;
			}
      print json_encode($output);
      wp_die();
		}
		public static function GetAccessToken() {
			$callbackurl = get_home_url(null, 'dpt-oauth-callback');
			$data=array(
				'client_id'=>get_option(__CLASS__.'_id'),
				'client_secret'=>get_option(__CLASS__.'_secret'),
				'grant_type'=>'refresh_token',
				'refresh_token'=>get_user_option(__CLASS__.'_auth')
				);
			$postData=http_build_query($data);
			$options = array('http'=>array(
				'method'=>'POST',
				'header'=>"Content-type: application/x-www-form-urlencoded\r\n"
					.'Content-Length: '.strlen($postData),//."\r\n"
			//		.'Authorization: Basic '.base64_encode(get_option('dpt_oauth_client_id').":".get_option('dpt_oauth_client_secret')),
				'content'=>$postData
				));
			$context = stream_context_create($options);
			$token_url=get_option(__CLASS__.'_token_url');
			$tokenResponse=json_decode(file_get_contents($token_url, false, $context),$assoc=true);
			if(isset($tokenResponse["access_token"]))
				return $tokenResponse["access_token"];
			return false;
		}
  }
}

$dpt_oauth_client = new dpt_oauth_client();

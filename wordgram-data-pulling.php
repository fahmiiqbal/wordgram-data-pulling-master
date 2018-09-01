<?php defined( 'ABSPATH' ) or die( 'Iam Handsome, Quite Please :)' ); 
/*
    Plugin Name: wordgram Data Pulling
    Plugin URI: http://rezza.kurniawan.me
    Description: Plugin For Get instagram by username
    Author: Rezza Kurniawan
    Version: 1.0
    Author URI: http://rezza.kurniawan.me
*/

class Wordgram_data_pulling {
	/** 
	 * Create Own Client here : https://instagram.com/developer/clients/manage/
	 */
	private $client_id = "";
	private $client_secret = "";

	public function __construct() {
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		add_action('init', array($this, 'init'));
		add_shortcode( 'instagram_masonry', array($this, 'mansonry_view') );
		// add_action('admin_enqueue_scripts', array($this, 'load_custom_wp_admin_style'));
	}

	public function deactivate() {
		delete_option( 'instagram_userdata' );	
		flush_rewrite_rules();
	}

	public function init()
    	{
   		add_action('admin_menu', array( $this, 'instagram_menu' ));
    	}

	/**
	 * Add Menu Instagram Settings to Menu Setting WP-Admin
	 */
	public function instagram_menu() {
		add_submenu_page('options-general.php', 'Instagram Settings', 'Instagram Settings', 'manage_options', 'instagram-menu', array($this,'instagram_admin_view'));
	}

	/**
	 * Load Style & Script to admin front-end
	 * @return [type] [description]
	 */
	private function load_custom_wp_admin_style() {
		wp_enqueue_style('bootstrap-css', plugins_url('/provider/bootstrap/css/bootstrap.min.css',__FILE__));

		wp_enqueue_style('fontawesome_css-css', plugins_url('/provider/font-awesome/css/font-awesome.min.css' ,__FILE__));

		wp_enqueue_style('bootstrap_social-css', plugins_url('/provider/bootstrap-social/bootstrap-social.css' ,__FILE__));
	}

	/**
	 * Instagram Client to store detail information
	 * @param  String $id ID Array
	 * @return String     Value Array
	 */
	private function client_instagram($id) {
		$plugin_url = admin_url( "options-general.php?page=instagram-menu", "http" );
		$instagram_arr = array(
						"client_id"	=>	$this->client_id,
						"auth_url"	=>	"https://api.instagram.com/oauth/authorize/?client_id=" . $this->client_id . "&redirect_uri=" . $plugin_url . "&response_type=code",
						"plugin_url"	=>	$plugin_url,
						"client_secret"	=> $this->client_secret
					);
		return $instagram_arr[$id];
	}

	/**
	 * Generate Button Sign in With Instagram
	 */
	function getButtonRegister() {
		if(!get_option('instagram_userdata')) :
		?>
			<div class="row">
				<div class="col-md-3">
					<a href="<?php echo $this->client_instagram('auth_url'); ?>" class="btn btn-block btn-social btn-md btn-instagram"><i class="fa fa-instagram"></i> Sign in with Instagram</a>
				</div>
			</div>
		<?php
		endif;
	}

	/**
	 * Pattern For Connect to Instagram (GET METHOD)
	 * @param  String $url Target
	 * @return Array      Results
	 */
	private function connectToInstagram($url){
		$ch = curl_init();					

		curl_setopt_array($ch, array(			
			CURLOPT_URL => $url,				
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,	
			CURLOPT_SSL_VERIFYHOST => 2			
		));

		$result = curl_exec($ch);				
		curl_close($ch);											
		return json_decode($result, true);
	}

	/**
	 * Get AccessToken
	 */
	private function requestAccessTokenInstagram() {
		if(isset($_GET['code'])) {
			$code = $_GET['code'];
			$query = array(
				'client_id'		=> $this->client_instagram('client_id'),
				'client_secret' 	=> $this->client_instagram('client_secret'),
				'grant_type'		=> 'authorization_code',
				'redirect_uri'		=> $this->client_instagram('plugin_url'),
				'code'			=> $code
			);
			
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL,"https://api.instagram.com/oauth/access_token");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($query));

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$server_output = curl_exec ($ch);
			curl_close ($ch);

			$results = json_decode($server_output, true);

			update_option( 'instagram_userdata', serialize($results) );
			echo "<script type='text/javascript'>";
			echo 'location.href=" ' . $this->client_instagram('plugin_url') .' ";';
			echo "</script>";

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get Access Token
	 * @return String Access Token
	 */
	private function getAccessToken() {
		$arr = unserialize(get_option( 'instagram_userdata'));
	     return($arr['access_token']);	
	}

	/**
	 * Get User Data
	 * @param  String $id ID Array
	 * @return Mixed     Value Array
	 */
	public function getUserData($id) {
		$arr = unserialize(get_option( 'instagram_userdata'));
	     return $arr['user'][$id];
	}

	/**
	 * Get User Feed Image & Video
	 * @return Array Feed Details
	 */
	public function getUserRecentPost($limit = 5,$type = "image") {
		$url = "https://api.instagram.com/v1/users/self/media/recent/?access_token=" . $this->getAccessToken() ."";
		$results = $this->connectToInstagram($url);

		if ($type == "image" ) {
			$images = array();
			foreach ($results['data'] as $image) {
				if ($image['type'] == 'image' && count($images) < $limit) {
					array_push($images,$image);
				}
			}
			return $images;
		} else if ($type == "video") {
			$videos = array();
			foreach ($results['data'] as $video) {
				if ($video['type'] == 'video' && count($videos) < $limit) {
					array_push($videos,$video);
				}
			}
			return $videos;
		} else {
			return $results;			
		}
	}

	/**
	 * Check Visibility Instagram Account
	 * @return Boolean true/false
	 */
	public function check() {
		$url = "https://api.instagram.com/v1/users/self/?access_token=" . $this->getAccessToken() ."";
		$results = $this->connectToInstagram($url);
		if ($results['meta']['code'] == 400) {
			delete_option( 'instagram_userdata' );
			return false;
		}
		return true;
	}

	/**
	 * Instagram Admin View Settings
	 */
	public function instagram_admin_view(){
		$this->check();
		$this->requestAccessTokenInstagram();
		$this->load_custom_wp_admin_style();
		?>	
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-5">
						<h3>Instagram Settings </h3>
						<p>You Must login this app with instagram account for grant permission get some data and photo</p>
					</div>
				</div>
				<?php $this->getButtonRegister(); ?>
				<?php if($this->check()) : ?>
					<div class="row">
						<div class="col-md-3">
							<div class="thumbnail" style="margin-top:20px;">
								<img src="<?php echo $this->getUserData('profile_picture'); ?>" alt="<?php echo $this->getUserData('username'); ?>">
									<div class="caption">
										<h3><?php echo $this->getUserData('full_name'); ?></h3>
										<small><?php echo $this->getUserData('username'); ?></small>
										<p><?php echo $this->getUserData('bio'); ?></p>
										<p><a href="<?php echo $this->getUserData('website'); ?>" class="btn btn-primary" target="_new">Website</a></p>
									</div>
								</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

		<?php
	}

	public function mansonry_view( $atts ) {
		ob_start();
		if ($this->check() == false) : ?>
		
			<h1 class="bigheadline" style="color:#000;text-align:center;">
				Sign Instagram First
			</h1>
			
		<?php else : ?>
			<div class="imagegrid">
				<div class="grid-sizer"></div>
				<?php 
					$typeGrid = array(
						'normal'			=> array('class'=>'grid-normal','res'=>'low_resolution'),
						'big'	 		=> array('class'=>'grid-big','res'=>'standard_resolution')
					);

					$i =0 ; $typeSelected = "normal"; 
					foreach ($this->getUserRecentPost(10) as $feed) :
					if ($i == 0 || $i % 5 == 0) {
						$typeSelected = "big";
					} else {
						$typeSelected = "normal";
					}
				?>
					<div class="grid-item <?php echo $typeGrid[$typeSelected]['class']; ?> ">
						<img src="<?php echo $feed['images'][$typeGrid[$typeSelected]['res']]['url']; ?>" alt="Thumbnails">		
					</div>
				<?php $i++; endforeach; ?>
			</div>	
			<div class="container">
				<div class="row center">
					<a href="http://instagram.com/<?php echo $this->getUserData('username'); ?>?ref=badge" class="ig-b- ig-b-v-24"><img src="http://badges.instagram.com/static/images/ig-badge-view-24.png" alt="Instagram" /></a>
				</div>
			</div>
		<?php
		endif;
		return ob_get_clean();
	}
}

$instagram_wp = new Wordgram_data_pulling();

?>
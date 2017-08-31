<?php
/*
Plugin Name: Broken Link Checker for YouTube
Plugin URI: https://wordpress.org/plugins/broken-link-checker-for-youtube/
Description: Can automatically validate YouTube embeds in your posts.
Version: 1.1
Released: November 12th, 2015
Author: Super Blog Me
Author URI: http://www.superblogme.com/
License: GPL2
*/

defined( 'ABSPATH' ) or die( "Oops! This is a WordPress plugin and should not be called directly.\n" );

////////////////////////////////////////////////////////////////////////////////////////////

add_filter('cron_schedules', function($schedules){
	$schedules['custom-schedule-time'] = array(
		'interval' => get_option('schedule_set_option'),
		'display'  => 'Custom Schedule Time'
		);
	return $schedules;
});

if(!class_exists('YouTube_Link_Checker'))
{

require_once plugin_dir_path( __FILE__ ) . 'youtube-link-functions.php';

    class YouTube_Link_Checker extends YouTube_Link_Functions
    {
	protected $version = "1.1";

        /**
         * Construct the plugin object
         */
        public function __construct()
        {
            	// register actions
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('admin_menu', array(&$this, 'add_menu'));
        } // END public function __construct

	/**
	 * hook into WP's admin_init action hook
	 */
	public function admin_init()
	{
    		// Set up the settings for this plugin
    		$this->init_settings();
	} // END public static function activate


	/**
	 * Initialize some custom settings
	 */     
	public function init_settings()
	{
		// set defaults if needed
		add_option( 'ytlc_logfile', plugin_dir_path( __FILE__ ) . 'logfile.txt' );

	} // END public function init_custom_settings()

	/**
	* add a menu
	*/     
	public function add_menu()
	{
		$page = add_management_page('Broken Link Checker for YouTube Settings', 'YouTube Checker', 'manage_options', 'youtube_link_checker', array(&$this, 'plugin_settings_page'));
	} // END public function add_menu()

//----- PLUGIN settings page ---------------------------------------------------------------------

	/**
	* Menu Callback
	*/     
	public function plugin_settings_page()
	{
		if(!current_user_can('manage_options'))
		{
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
    		// Render the settings template
		include(sprintf("%s/templates/settings.php", dirname(__FILE__)));
		if ( isset( $_POST['scan_now'] ) ) {
			$this->check_for_broken_links();
			echo "<div id='message' class='updated'><p>" . __('Scan Complete.','youtube-link-checker') . "</p></div>";
		}


		if( isset($_POST['schedule_set_option']) ) {
        // Save the posted value in the database
        update_option( 'schedule_set_option', $_POST['schedule_set_option'] );
        update_option( 'report_recive_email_option', $_POST['report_recive_email_option'] );
        // Put a "settings saved" message on the screen
		?>
			<div class="updated">
				<p><strong><?php _e('settings saved.' ); ?></strong></p>
			</div>
		<?php
    	}
		?>
		<div class="warp">
			<h2>Custom Setting</h2>
			<form method="post" id="" action="">
			<table class="form-table">
			    <tr valign="top">
		        <th scope="row">Set Schedule For Check</th>
		        <td><input type="text" name="schedule_set_option" value="<?php echo get_option('schedule_set_option'); ?>" /> 
		        </td>
			    </tr>
		        <tr valign="top">
		        <th scope="row">Reciver Email </th>
		        <td> <input type="text" name="report_recive_email_option" value="<?php echo get_option('report_recive_email_option'); ?>" /> 
		        </td>
		        </tr>
			</table>
			  <?php submit_button(); ?>
			</form>
		</div>
		<?php
	} // END public function plugin_settings_page()




    } // END class YouTube_Link_Checker
} // END if(!class_exists('YouTube_Link_Checker'))

add_action('init', function(){
    if (!wp_next_scheduled('schedule-error-check')) {
        wp_schedule_event(time(), 'custom-schedule-time', 'schedule-error-check');
}});

add_action('schedule-error-check', function(){
	$mainfunction = new YouTube_Link_Functions();
	$mainfunction->check_for_broken_links_and_email();
});

////////////////////////////////////////////////////////////////////////////////////////////

if(class_exists('YouTube_Link_Checker'))
{
	// instantiate the plugin class
	$youtube_link_checker = new YouTube_Link_Checker();
}

////////////////////////////////////////////////////////////////////////////////////////////
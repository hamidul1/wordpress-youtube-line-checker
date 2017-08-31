<?php

defined( 'ABSPATH' ) or die( "Oops! This is a WordPress plugin and should not be called directly.\n" );

////////////////////////////////////////////////////////////////////////////////////////////

if(!class_exists('YouTube_Link_Functions'))
{
    class YouTube_Link_Functions
    {
	private $report 		= array();	// final results to log and/or email
	private $errors			= array();	// error results to log and/or email

	private $video_links 		= array();	// queue of links found
	private $total_found 		= 0;		// total of all video links found 
	private $total_checked 		= 0;		// total of all video links checked
	private $total_broken 		= 0;		// total of all video links broken
	private $odd			= 0;		// used for report even/odd rows

	private $youtube_batch 		= 50;		// max number we can send at once

	private $args			= array();	// function args override saved option values

	private $css			= array();	// have to use inline styles for email

	// video url search patterns ---------------------------------------------
	#private $pattern_youtube_embed = '((?:https?://)?(?:www\.)?youtube\.com/(?:embed/|v/)(?!videoseries)([a-zA-Z0-9-_\%]+))';
	private $pattern_youtube_embed = '((?:https?://)?(?:www\.)?youtube\.com/(?:embed/videoseries\?list=|embed/|v/)(?!videoseries)([a-zA-Z0-9-_\%]+))';
	private $pattern_youtube_link = '((?:https?://)?(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)(?!videoseries)([a-zA-Z0-9-_\%]+))';


        /**
         * Construct the plugin object
         */
        public function __construct()
        {
        } // END public function __construct

        /**
         * Add message to our report
         */
        protected function add_to_report( $msg )
        {
		$this->report[] = $msg;
        } 

        /**
         * Add message to our errors
         */
        protected function add_to_errors( $msg )
        {
		$this->errors[] = date('Y-m-d H:i:s',current_time("timestamp")) . " - " . $msg . "<br>";
        } 

	/**
	 * We have to use inline CSS to be email compatible
	 * allow filters so user can change
	 */
	private function load_css()
	{
		$this->css = array();

		$this->css['report'] = '<div style="display:table; border: 1px solid black; width: 100%; margin: 10px 0;">';
		$this->css['row_header'] = '<div style="display: table-row; font-weight: bold;">';
		$this->css['row_even'] = '<div style="display: table-row;">';
		$this->css['row_odd'] = '<div style="display: table-row;background-color: #ddd;">';
		$this->css['col'] = '<div style="display: table-cell; padding: 5px;">';
		$this->css['col_bad'] = '<div style="display: table-cell; padding: 5px; color:red;">';
		$this->css['col_good'] = '<div style="display: table-cell; padding: 5px; color:blue;">';

		// any filters?
		$this->css['report'] = apply_filters( 'ytlc_css_report', $this->css['report']);
		$this->css['row_header'] = apply_filters( 'ytlc_css_row_header', $this->css['row_header']);
		$this->css['row_even'] = apply_filters( 'ytlc_css_row_even', $this->css['row_even']);
		$this->css['row_odd'] = apply_filters( 'ytlc_css_row_odd', $this->css['row_odd']);
		$this->css['col'] = apply_filters( 'ytlc_css_col', $this->css['col']);
		$this->css['col_bad'] = apply_filters( 'ytlc_css_col_bad', $this->css['col_bad']);
		$this->css['col_good'] = apply_filters( 'ytlc_css_col_good', $this->css['col_good']);
		
	}

//----- CHECK BROKEN LINKS functions ---------------------------------------------------------------------

	/**
	* This is run when the video_link_checker_event is triggered by cron
	* Passed in args override saved option values
	*/     
	public function check_for_broken_links( $args = array() )
	{
	global $wpdb;

		$this->load_css();

		// read posts in manageable chunks so we don't run out of memory
		$numberposts = isset( $args['post_chunksize'] ) ? $args['post_chunksize'] : 100;

		$maxposts = isset( $args['post_maxnumber'] ) ? $args['post_maxnumber'] : -1;

		$post_types = 'post';
		$post_statuses = 'publish';

		$str = '<p />';
		$str .= sprintf( __( 'Scan report for <a target="blank" href="%s">%s</a> from Broken Link Checker for YouTube v%s', 'youtube-link-checker' ), get_bloginfo( 'url' ), get_bloginfo( 'name' ), $this->version ) . '<br />';
		$str .= sprintf( __( 'Searching post type \'%s\'', 'youtube-link-checker' ), $post_types );
		$str .= sprintf( __( ' with post status \'%s\'', 'youtube-link-checker' ), $post_statuses );
		$str .= '<p>' . sprintf( __( 'Scan started: %s', 'youtube-link-checker' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '</p>';
		$this->add_to_report( $str );

		$str = sprintf( __( 'Last scan report for %s on %s', 'youtube-link-checker' ), get_bloginfo( 'name' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '<br>';
		$last_results[] = $str;

		$this->add_to_report( $this->css['report'] );

		// report header
		$str = $this->css['row_header'];
		$str .= $this->css['col'] . __( ' Status ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Video Url ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Url Location ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Post ', 'youtube-link-checker' ) .  '</div>';
		$str .= '</div>';
		$this->add_to_report( $str );

		// scan POSTS and look for links. -------------------------------------
		$getMore = TRUE;
		$total = 0;
		$this->total_found = $this->total_checked = $this->total_broken = $odd = 0;

		$query_args = array(
			'numberposts'        	=> $numberposts,
			'post_type'             => $post_types,
			'post_status'           => $post_statuses
		);
		$query_args = apply_filters( 'ytlc_scan_posts', $query_args );

		// get total to process here
		$totalposts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = '{$post_statuses}' AND post_type = '{$post_types}'");

		// tell WordPress not to fill up the object cache with useless data
		wp_suspend_cache_addition(true);

		while ( $getMore ) 
		{
			$query_args['offset'] = $total;
                	$posts = get_posts( $query_args );
			$num = count( $posts );
			$total += $num;

			$this->process_posts( $posts, $args );
			$this->handle_checked_videos();

			$percent = intval( $total / $totalposts * 100 ) . "%";

			if ( $num < $numberposts )
			{
				$getMore = FALSE;
			}
			if ( $maxposts > 0 && $total >= $maxposts )
			{
				$getMore = FALSE;
			}
		}

		$this->add_to_report( '</div>' );
		$str = '<p>' . sprintf( __( 'Scan ended: %s', 'youtube-link-checker' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '</p>';
		$this->add_to_report( $str );

		$str = '<p><strong>' . sprintf( __( 'Scanned %d posts. %d videos checked. %d videos broken.', 'youtube-link-checker' ), $total, $this->total_checked, $this->total_broken ) . '</strong></p>';
		$this->add_to_report( $str );
		$report = implode( "\n", $this->report );
		echo $report;
		echo "<p/>";
		$errors = implode( "\n", $this->errors );
		echo $errors;
	}





	public function check_for_broken_links_and_email( $args = array() )
	{
	global $wpdb;

		$this->load_css();

		// read posts in manageable chunks so we don't run out of memory
		$numberposts = isset( $args['post_chunksize'] ) ? $args['post_chunksize'] : 100;

		$maxposts = isset( $args['post_maxnumber'] ) ? $args['post_maxnumber'] : -1;

		$post_types = 'post';
		$post_statuses = 'publish';

		$str = '<p />';
		$str .= sprintf( __( 'Scan report for <a target="blank" href="%s">%s</a> from Broken Link Checker for YouTube v%s', 'youtube-link-checker' ), get_bloginfo( 'url' ), get_bloginfo( 'name' ), $this->version ) . '<br />';
		$str .= sprintf( __( 'Searching post type \'%s\'', 'youtube-link-checker' ), $post_types );
		$str .= sprintf( __( ' with post status \'%s\'', 'youtube-link-checker' ), $post_statuses );
		$str .= '<p>' . sprintf( __( 'Scan started: %s', 'youtube-link-checker' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '</p>';
		$this->add_to_report( $str );

		$str = sprintf( __( 'Last scan report for %s on %s', 'youtube-link-checker' ), get_bloginfo( 'name' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '<br>';
		$last_results[] = $str;

		$this->add_to_report( $this->css['report'] );

		// report header
		$str = $this->css['row_header'];
		$str .= $this->css['col'] . __( ' Status ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Video Url ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Url Location ', 'youtube-link-checker' ) .  '</div>';
		$str .= $this->css['col'] . __( ' Post ', 'youtube-link-checker' ) .  '</div>';
		$str .= '</div>';
		$this->add_to_report( $str );

		// scan POSTS and look for links. -------------------------------------
		$getMore = TRUE;
		$total = 0;
		$this->total_found = $this->total_checked = $this->total_broken = $odd = 0;

		$query_args = array(
			'numberposts'        	=> $numberposts,
			'post_type'             => $post_types,
			'post_status'           => $post_statuses
		);
		$query_args = apply_filters( 'ytlc_scan_posts', $query_args );

		// get total to process here
		$totalposts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = '{$post_statuses}' AND post_type = '{$post_types}'");

		// tell WordPress not to fill up the object cache with useless data
		wp_suspend_cache_addition(true);

		while ( $getMore ) 
		{
			$query_args['offset'] = $total;
                	$posts = get_posts( $query_args );
			$num = count( $posts );
			$total += $num;

			$this->process_posts( $posts, $args );
			$this->handle_checked_videos();

			$percent = intval( $total / $totalposts * 100 ) . "%";

			if ( $num < $numberposts )
			{
				$getMore = FALSE;
			}
			if ( $maxposts > 0 && $total >= $maxposts )
			{
				$getMore = FALSE;
			}
		}

		$this->add_to_report( '</div>' );
		$str = '<p>' . sprintf( __( 'Scan ended: %s', 'youtube-link-checker' ), date('Y-m-d H:i:s',current_time("timestamp")) ) . '</p>';
		$this->add_to_report( $str );

		$str = '<p><strong>' . sprintf( __( 'Scanned %d posts. %d videos checked. %d videos broken.', 'youtube-link-checker' ), $total, $this->total_checked, $this->total_broken ) . '</strong></p>';
		$this->add_to_report( $str );

		$report = implode( "\n", $this->report );
		$errors = implode( "\n", $this->errors );
		
		$to = get_option('report_recive_email_option');;
	    $subject = 'YouTube Video Error Report';
	    $message = $report.'</p>'.$errors.'';
	    $headersss = array('Content-Type: text/html; charset=UTF-8');
	    wp_mail( $to, $subject, $message, $headersss );
	}
	/**
	* Process the posts returned by get_posts 
	* returns an array of videos found and checked
	*/
	public function process_posts( $posts, $args = array() )
	{
		// in this top function, we will use class variable for ease of use
		$this->video_links = array();

		unset( $args['content'] ); // make double sure this is clear when starting a new batch...

		if ( empty( $posts ) )
		{
                       	$this->add_to_errors( sprintf( __( '%s ERROR: missing posts.', 'youtube-link-checker' ), __FUNCTION__ ) );
			return $this->video_links;
		}

		foreach ( $posts as $post ) 
		{
			$found_links = array();
			$args['post'] = $post;
			$links = $this->youtube_search_post( $args );
			$found_links = array_merge( $found_links, $links );
		}

		// send videos in queue to their APIs for checking
		$this->send_requests();

		return $this->video_links;
	}

	/**
	* Send API requests for any videos we have in queue
	*/
	private function send_requests()
	{
		// YouTube Videos
		$queryIDs = $this->getQueryIDs( 'YouTube', 'video' );
		$notfound_vids = $this->youtube_query_links( $queryIDs ); 
		$this->handle_notfound_videos( 'YouTube', $notfound_vids );
	}



	/**
	* Grab all the videoIDs from a video source
	*/
	private function getQueryIDs( $source, $videoType = null )
	{
		$links = & $this->video_links;
		$videoIDs = array();
		foreach ( $links as $key => $value ) 
		{
			if ( $links[$key]['source'] == $source )
			{
				if ( $videoType && $links[$key]['videoType'] != $videoType )
				{
					continue;
				}
				$videoIDs[] = $key;
			}
		}
		return $videoIDs;
	}


	/**
	 * Make a query and check for errors.
	 * paramter url to wp_remote_get
	 * optional headers to send
	 * optional flag to decode with JSON (default) or XML 
	 */
	private function queryApi( $url, $headers = null, $xml = false )
	{
		if ( empty ( $headers ) )
		{
			$headers = array( 'User-Agent' => 'video link checker;' );
		}
                $response = wp_remote_get( $url, array( 
			'headers' => $headers,
			'decompress' => FALSE, 
			'sslverify' => FALSE 
			)
		);
    		if ( is_wp_error( $response ) && strpos( $response->get_error_message(), 'timed out' ) !== FALSE ) 
		{
			// rarely we see a timeout when YouTube servers respond slowly. so let's try again automatically
                	$response = wp_remote_get( $url, array( 'decompress' => FALSE, 'sslverify' => FALSE ) );
		}
    		if ( is_wp_error( $response ) ) 
		{
			$this->add_to_errors( sprintf( __( 'WP error in %s (%s) - %s ', 'youtube-link-checker' ), __FUNCTION__, htmlentities( $url ), $response->get_error_message() ) );
			return null;
		}
		if ( ! $xml )
		{
                	$data = json_decode( wp_remote_retrieve_body( $response ) );
		}
		else
		{
                	$data = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		}

		if ( isset( $data->error ) )
		{
			return null;
		}
		return $data;
	}

	/**
	 * Take an array of videoIDs not found at all, set broken and status
	 */
	private function handle_notfound_videos( $source, $broken_vids )
	{
		if ( empty( $broken_vids ) )
		{
			return;
		}

		$links = & $this->video_links;
		foreach ( $broken_vids as $del )
		{
			if ( isset( $links[$del] ) && $links[$del]['source'] == $source )
			{
				$links[(string)$del]['broken'] = 1;
				if ( ! isset( $links[$del]['status'] ) )
				{
					$links[(string)$del]['status'] = 'NOT FOUND';
				}
			}
		}
	}

	/**
	 * Take an array of videoIDs, change post status and/or report
	 * optional source if we only want to process links from that video site
	 */
	private function handle_checked_videos( $source = '' )
	{
		$args = $this->args;
		$links = $this->video_links;

		foreach ( $links as $link )
		{
			if ( $source && $link['source'] != $source )
			{
				continue;
			}

			else if ( ! isset( $link['broken'] ) )
			{
				// should not happen - all links should be checked at this point...
                        	$this->add_to_errors( sprintf( __( '%s ERROR: link has not been checked: %s', 'youtube-link-checker' ), __FUNCTION__, print_r($link,true) ) );
				continue;
			}

			$this->total_checked++;
			if ( $link['broken'] == 1 )
			{
				$even_odd = $this->odd % 2 ? $this->css['row_even'] : $this->css['row_odd'];
				$str = $even_odd;
				$this->odd++;
				$str .= sprintf( '%s %s %s', $this->css['col_bad'], $link['status'], '</div>' );
				$str .= sprintf( '%s <a target="blank" href="%s">%s</a> %s', $this->css['col'], $link['videoUrl'], $link['videoUrl'], '</div>' );
				$str .= sprintf( '%s %s %s', $this->css['col'], $link['location'], '</div>' );
				$epl = $this->get_epl_link( $link );
				$str .= sprintf( '%s <a target="blank" href="%s">[edit]</a> <a target="blank" href="%s">%s</a> %s', $this->css['col'], $epl, $link['postUrl'], $link['title'], '</div>' );

				$this->total_broken++;
				$str .= '</div>';
				$this->add_to_report( $str );
			}
		}
	}

	/**
	 * Get the edit post link for this entry
	 */
	private function get_epl_link( $link )
	{
		if ( preg_match( '!contus (\d+)!', $link['location'], $matches ) )
		{
			$epl = admin_url('admin.php?page=newvideo&videoId=') . $matches[1];
		}
		else
		{
			$epl = get_edit_post_link( $link['postID'] ); 
		}
		return $epl;
	}

	/**
	 * Add a new entry to our queue of links to check
	 */
	private function add_entry_to_links( $args )
	{
		$videoID = $args['videoID'];
		if ( empty( $videoID ) )
		{
			$this->add_to_errors( sprintf( __( '%s Error: No ID for : %s in post %s', 'youtube-link-checker' ), __FUNCTION__, $args['videoUrl'], $args['postID'] ) );
			return null;
		}

		$videoType = 'video';

		$link = array(
			'source'	=> $args['source'],
			'videoUrl'	=> trim( $args['videoUrl'] ),
			'postUrl'	=> get_post_permalink( $args['postID'] ),
			'location'	=> $args['location'],
			'title'		=> $args['title'],
			'postID'	=> $args['postID'],
			'videoType'	=> $videoType
		);
		if ( ! isset( $this->video_links[$videoID] ) )
		{
			$this->total_found++;
			$this->video_links[(string)$videoID] = $link;
			return $link;
		}
		return null;
	}

	/**
	* Check the post for any valid video links or embeds ======================
        * override_content: if we want to search a specific string instead of post_content
	*/
	public function check_for_video_links_in_content( $args )
	{
		$links = array();

		if ( empty( $args ) )
		{
                       	$this->add_to_errors( sprintf( __( '%s ERROR: missing args.', 'youtube-link-checker' ), __FUNCTION__ ) );
			return $links;
		}
		$post = $args['post'];

		$content = isset( $args['content'] ) ? $args['content'] : $post->post_content;
		$pattern = $args['pattern'];
		$source = $args['source'];
		$location = isset( $args['location'] ) ? $args['location'] : 'post content';

		if ( empty( $post ) || empty( $source ) || empty( $pattern ) )
		{
                       	$this->add_to_errors( sprintf( __( '%s ERROR: missing required value(s) in args %s.', 'youtube-link-checker' ), __FUNCTION__, htmlentities( print_r( $args, true) ) ) );
			return $links;
		}

		#check the content
		if ( FALSE === preg_match_all( $pattern, $content, $matches ) )
		{
                       	$this->add_to_errors( sprintf( __( '%s preg_match_all ERROR on pattern %s.', 'youtube-link-checker' ), __FUNCTION__, $pattern ) );
		}
		foreach( $matches[1] as $key => $videoUrl )
		{
			$videoID = trim( $matches[2][$key] );
			$link = $this->add_entry_to_links( array( 
				'source'	=> $source, 
				'videoID'	=> $videoID, 
				'videoUrl'	=> $videoUrl, 
				'location'	=> $location,
				'postID'	=> $post->ID, 
				'title'		=> $post->post_title
			) );
			if ( ! empty( $link ) )
			{
				$links[] = $link;
			}
		}
		return $links;
	}

//----- SITES wrapper function ---------------------------------------------------------------------

	/**
	* Simplify with one entry function which will call the appropriate site function
	*/
	public function query_IDs( $IDs, $args = array() ) 
	{
		$notfound = array();
		if ( empty( $args ) || ! isset( $args['source'] ) )
		{
                       	$this->add_to_errors( sprintf( __( '%s ERROR: missing args or args[source].', 'youtube-link-checker' ), __FUNCTION__ ) );
		}
		else if ( FALSE !== stripos( $args['source'], 'YouTube' ) )
		{
			$notfound = $this->youtube_query_links( $IDs, $args );
		}
		return $notfound;
	}


//----- YOUTUBE functions ---------------------------------------------------------------------

	/**
	* YouTube search a post for YouTube link patterns
	*/
	private function youtube_search_post( $args = array() ) 
	{
		$post = $args['post'];

		$found_links = array();

		$args['source'] = 'YouTube';

			// search for the embed url:
			$pattern = '%' . $this->pattern_youtube_embed . '%i';
			$args['pattern'] = $pattern;
			$links = $this->check_for_video_links_in_content( $args );
			$found_links = array_merge( $found_links, $links );

			// search for YouTube urls on their own line:
			$pattern = '%^(?:\s*)' . $this->pattern_youtube_link . '(?:\s*)$%im';
			$args['pattern'] = $pattern;
			$links = $this->check_for_video_links_in_content( $args );
			$found_links = array_merge( $found_links, $links );

			// search for YouTube urls between [embed] URL [/embed] shortcode
			$pattern = get_shortcode_regex();
    			if ( preg_match_all( '/'. $pattern .'/s', $post->post_content, $matches )
        			&& array_key_exists( 2, $matches ) )
			{
				$pattern = '%' . $this->pattern_youtube_link . '%i';
				foreach ( $matches[5] as $key => $videoUrl )
				{
					if ( $matches[2][$key] == 'embed' )
    					{
						$args['pattern'] = $pattern;
						$args['content'] = $videoUrl;
						$links = $this->check_for_video_links_in_content( $args );
						$found_links = array_merge( $found_links, $links );
						unset( $args['content'] );
    					}
				}
			}

		return $found_links;
	}

	/**
	 * Query a batch of video ids to see if they've been deleted from YouTube
	 * param $videoIDs: an array or comma delimited string of YouTube videoIDs. 
	 * Returns a list of videoIDs not found on video site
	 * Uses https://developers.google.com/youtube/v3/docs/videos/list
	 */
	private function youtube_query_links( $videoIDs, $args = array() ) 
	{
		if ( empty( $videoIDs ) ) 
		{
			return array();
		}

		$youtube_api_key = 'AIzaSyBf-NOyIgxE_3fe0LlwkfZBpXe7sCWOri4';

		if ( ! is_array( $videoIDs ) )
		{
			$videoIDs = explode( ",", $videoIDs );
		}

		$uniqueIDs = array_unique( $videoIDs );

		$chunks = array_chunk( $uniqueIDs, $this->youtube_batch );
		foreach ( $chunks as $chunk )
		{
			$commaList = implode( ',', $chunk);

        		$query_args = array(
				'part'		=> 'id,status',
				'id'		=> $commaList,
				'key'		=> $youtube_api_key
			);
			$url = 'https://www.googleapis.com/youtube/v3/videos'. '?' . http_build_query( $query_args );
			$data = $this->queryApi( $url );

			if ( ! $data ) 
			{ 	// error occurred - probably timeout. Try again another time
				continue;
			}
			// YouTube only returns a list of public videos on YouTube
			$videoResults = isset( $data->items ) ? $data->items : array();

			foreach ( $videoResults as $video )  
			{
				# https://developers.google.com/youtube/v3/docs/videos#status.privacyStatus
				if ( $video->status->privacyStatus == 'private' )
				{
					$this->video_links[(string)$video->id]['broken'] = 1;
					$this->video_links[$video->id]['status'] = strtoupper( $video->status->privacyStatus );
				}
				# https://developers.google.com/youtube/v3/docs/videos#status.embeddable
				else if ( $video->status->embeddable !== TRUE )
				{
					$this->video_links[(string)$video->id]['broken'] = 1;
					$this->video_links[$video->id]['status'] = 'NOT EMBEDDABLE';
				}
				# https://developers.google.com/youtube/v3/docs/videos#status.uploadStatus
				else if ( ( $key = array_search( $video->id, $videoIDs ) ) !== FALSE && ( $video->status->uploadStatus == 'processed' || $video->status->uploadStatus == 'uploaded' ) ) 
				{ 	# video is valid
					$this->video_links[(string)$video->id]['broken'] = 0;
					$this->video_links[$video->id]['status'] = strtoupper( $video->status->uploadStatus );
					unset( $videoIDs[$key] );

					// special notification for unlisted videos
					if ( $video->status->privacyStatus == 'unlisted' )
					{
						$this->video_links[$video->id]['status'] = ' UNLISTED';
					}
				}
				else
				{
					$this->video_links[(string)$video->id]['broken'] = 1;
					$this->video_links[$video->id]['status'] = isset( $video->status->uploadStatus ) ? strtoupper( $video->status->uploadStatus ) : 'UNAVAILABLE';
				}
			}
		}

		// videoIDs now only contain the ones not valid on YouTube
		return $videoIDs;
	}


////////////////////////////////////////////////////////////////////////////////////////////


    } // END class YouTube_Link_Functions
} // END if(!class_exists('YouTube_Link_Functions'))

////////////////////////////////////////////////////////////////////////////////////////////

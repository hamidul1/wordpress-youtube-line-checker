=== Broken Link Checker for YouTube ===
Plugin URI: 
Author: Hamidul Islam
Author URI: http://www.wordpress-tips.org
Tags: Youtube, oEmbed, video, embed video, broken links, link checker
Requires at least: 3.0
Tested up to: 4.3
Stable Tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Will automatically validate
YouTube embeds found in your published posts content using the official API.



== Description ==
Broken Link Checker for YouTube is a WordPress plugin that will automatically validate
YouTube embeds found in your published posts content using the official API.

It does this by extracting video IDs from each post and querying the YouTube
API to ensure those IDs are still valid. This is a MUST for any video
site that wants to retain their visitors and not annoy them with broken
content.

The truth is videos are uploaded and removed frequently - either by the user
or the site itself for usage violations. If this happens to a video you have
embedded on your site, your visitors are going to see broken content instead
of the video they expected. Don't let that happen.

Quick Summary:

https://youtu.be/jxc6ioU0WJ0


== Installation ==

1. Install the plugin through WordPress admin or upload the `YouTube Link
Checker` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit `Tools -> YouTube Checker` to run a Scan.


== Frequently Asked Questions ==

= I installed the plugin. Where is it? =

You will find it under `Tools -> YouTube Checker`.

= What are the statuses returned for broken YouTube embed links? =

* NOT FOUND - The video was not found on YouTube.
* DELETED - The video is marked as deleted, but not removed from YouTube.
* REJECTED - The video is marked as rejected for copyright violation, account
termination, etc.
* NOT EMBEDDABLE - The video is marked as only playable on YouTube, and
embedding the video on other sites will not work.

= Why this plugin over the other Broken Link Checker plugin? =

Use this plugin for checking YouTube videos.

The other plugin only checks if the YouTube video exists or not.
On YouTube, a video still exists if its status is DELETED, REJECTED or NOT
EMBEDDABLE, and the other plugin will not report those as broken links.

= The plugin times out. What can I do? =

The plugin will scan and verify roughly 30 videos per second. If your max
execution time for PHP is 30 seconds it will timeout after roughly 900 videos.
If you need more than that, you can change your PHP runtime configuration for
[max execution
time](http://php.net/manual/en/info.configuration.php#ini.max-execution-time)
or try the premium Video Link Checker, which has no timeouts under mod_php.

= The plugin isn't finding my videos! What now? =

Verify those videos are in the post content. Some themes will store the video
in custom meta fields (Avada, Newspaper, Goodwork, Enfold, Sahifa, SimpleMag,
True Mag, Valenti, VideoTube, and Video Member to name a few). If so you can
use the premium Video Link Checker.

= The plugin isn't finding some videos that are blocked. Why not? =

This case happens when the person who uploaded the video chose to allow the
video to be embedded, but the legal copyright holder of some content on the
video ( usually the music ) has the legal right to restrict or block the
embed. This info is currently not available through the YouTube API.

= Where can I find the premium Video Link Checker? =

You can find the [Video Link
Checker](http://codecanyon.net/item/video-link-checker-detect-broken-youtube-urls/13003626) at CodeCanyon.


== Screenshots ==

1. A sample scan report.

2. Difference between Broken Link Checker for YouTube and Video Link Checker

== Changelog ==

= 1.1 Released November 12th, 2015 = 

* Tweak for finding YouTube urls on their own line.
* Tweak for embed shortcode url detection.

= 1.0 Released November 2nd, 2015 = 

* Initial Release

<div class="wrap">

<h2>Broken Link Checker for YouTube v<?php echo $this->version; ?></h2>

	<p>
	<strong>
	<?php _e("Will verify embedded YouTube videos in published posts are valid using API requests.", 'youtube-link-checker' ); ?>
	</strong>
	</p>

<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">

	<input type="submit" name="scan_now" value="<?php _e('Scan Now', 'youtube-link-checker' ); ?> &raquo;" />

</form>

	<p>
	<?php _e("Want options? More features? Additional sites? Premium support? Then check out ", 'youtube-link-checker' ); ?>
	<a target='blank' href='http://codecanyon.net/item/video-link-checker-detect-broken-youtube-urls/13003626?ref=johnh10'>Video Link Checker</a>
	</p>
</div>

<?php

/**
 * @package Convert GIF to MP4 in Post
 * @version 1.0.0
 */
/*
Plugin Name: Convert GIF to MP4 in Post
Plugin URI: https://ncube.net/
Description: This plugin animated gif to mp4 using ffmpeg in post.
Author: chicpro
Version: 1.0.0
Author URI: https://ncube.net/
*/

require ( plugin_dir_path( __FILE__ ) . '/functions.php' );

add_filter ('the_content', 'convert_gif2mp4', 100);

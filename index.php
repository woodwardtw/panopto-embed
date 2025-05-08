<?php 
/*
Plugin Name: Panopto Embed
Plugin URI:  https://github.com/
Description: Prevent the auto display of captions on embedded Panopto videos
Version:     1.0
Author:      Tom Woodward
Author URI:  https://dlinq.middcreate.net
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: my-toolset

*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


add_action('wp_enqueue_scripts', 'panopto_cure_load_scripts');

function panopto_cure_load_scripts() {                           
    $deps = array('jquery');
    $version= '1.0'; 
    $in_footer = true;    
    wp_enqueue_script('panoptop-cure-js', plugin_dir_url( __FILE__) . 'js/panopto-cure-main.js', $deps, $version, $in_footer); 
    wp_enqueue_style( 'prefix-main-css', plugin_dir_url( __FILE__) . 'css/prefix-main.css');
}

//add content filter that finds iframe, verifies panopto URL, parses out the video ID and returns the right HTML 

add_filter( 'the_content', 'panopto_cure_clean_iframe', 1 );

function panopto_cure_clean_iframe($content){
     $pattern = '/<iframe[^>]+src=["\']([^"\']*panopto[^"\']*)["\'][^>]*>.*?<\/iframe>/is';

    $i = 0;
    $cleaned_text = preg_replace_callback($pattern, function($matches) use (&$panopto_urls, &$i) {
        $url = $matches[1];
        $panopto_urls[] = $url;
        $id = panopto_cure_id($url);
        $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>[panoptocure id='{$id}']";
        $i++;
        return $shortcode;
    }, $content);

    return $cleaned_text;
}

function panopto_cure_id($url){
    $parsed_url = parse_url($url);

    if (!isset($parsed_url['query'])) {
        return null; // No query string
    }
    parse_str($parsed_url['query'], $query_params);

    return $query_params['id'] ?? null;
}


function extract_iframe_urls($text) {
    $iframe_urls = [];
    $pattern = '/<iframe[^>]+src=["\']([^"\']+)["\']/i';

    if (preg_match_all($pattern, $text, $matches)) {
        $iframe_urls = $matches[1]; // [1] contains the captured src values
    }

    return $iframe_urls;
}


//LOGGER -- like frogger but more useful

if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}

  //print("<pre>".print_r($a,true)."</pre>");

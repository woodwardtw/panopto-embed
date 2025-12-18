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
    $i = 0;

    // Pre-handle explicit [panopto] shortcodes so we can support
    // editor/typography changes (curly quotes, HTML-encoded attrs) that
    // might prevent WP from parsing the shortcode later.
    $content = preg_replace_callback('/\[panopto\b([^\]]*)\]/i', function($matches) use (&$i) {
        $attr_text = html_entity_decode($matches[1]);

        // Normalize curly quotes to straight quotes so parsing works
        $attr_text = str_replace(
            array("\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"),
            array('"', '"', "'", "'"),
            $attr_text
        );

        // Use WP helper to parse attributes if available
        if (function_exists('shortcode_parse_atts')) {
            $attrs = shortcode_parse_atts($attr_text);
        } else {
            $attrs = array();
            if (preg_match('/url\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s]+))/i', $attr_text, $am)) {
                $attrs['url'] = $am[1] ?? ($am[2] ?? $am[3]);
            }
        }

        $url = trim( $attrs['url'] ?? '' );
        if (empty($url)) {
            return $matches[0];
        }

        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }

        return $matches[0];
    }, $content);

    // First, handle Gutenberg embed blocks with plain text Panopto URLs (no iframe yet)
    $gutenberg_text_pattern = '/<figure\s+class="[^"]*wp-block-embed[^"]*"[^>]*>\s*<div\s+class="[^"]*wp-block-embed__wrapper[^"]*"[^>]*>\s*(https?:\/\/[^\s<]*panopto[^\s<]*\/Panopto\/Pages\/Viewer\.aspx\?[^\s<]+)\s*<\/div>\s*<\/figure>/is';

    $content = preg_replace_callback($gutenberg_text_pattern, function($matches) use (&$i) {
        $url = $matches[1];
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    // Handle Gutenberg embed blocks that contain Panopto iframes
    $gutenberg_iframe_pattern = '/<figure\s+class="[^"]*wp-block-embed[^"]*"[^>]*>.*?<div\s+class="[^"]*wp-block-embed__wrapper[^"]*"[^>]*>.*?<iframe[^>]+src=["\']([^"\']*panopto[^"\']*)["\'][^>]*>.*?<\/iframe>.*?<\/div>.*?<\/figure>/is';

    $content = preg_replace_callback($gutenberg_iframe_pattern, function($matches) use (&$i) {
        $url = $matches[1];
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    // SiteOrigin and generic handlers:
    // 1) Anchors anywhere (useful for SiteOrigin widgets that wrap links in divs)
    $anchor_pattern = '/<a[^>]+href=["\'](https?:\/\/[^"\']*panopto[^"\']*\/Panopto\/Pages\/Viewer\.aspx\?[^"\']+)["\'][^>]*>.*?<\/a>/is';

    $content = preg_replace_callback($anchor_pattern, function($matches) use (&$i) {
        $url = $matches[1];
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    // 2) Plain URLs anywhere in text nodes (avoid matching inside attributes)
    $any_url_pattern = '/(?<!["\'>=])\b(https?:\/\/[^\s<]*panopto[^\s<]*\/Panopto\/Pages\/Viewer\.aspx\?[^\s<]+)\b/is';

    $content = preg_replace_callback($any_url_pattern, function($matches) use (&$i) {
        $url = $matches[1];
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    // Backward-compatible: still keep paragraph/anchor pattern (already covered above but kept for safety)
    // Handle plain paragraph links to Panopto URLs.
    // Supports both raw URL text and anchor tags where the URL is in the href (Classic Editor).
    $link_pattern = '/(?:<p>\s*)?(?:<a[^>]+href=("|\')(https?:\/\/[^"\']*panopto[^"\']*\/Panopto\/Pages\/Viewer\.aspx\?[^"\']+)\1[^>]*>.*?<\/a>|(https?:\/\/[^\s<]*panopto[^\s<]*\/Panopto\/Pages\/Viewer\.aspx\?[^\s<]+))(?:\s*<\/p>)?/is';

    $content = preg_replace_callback($link_pattern, function($matches) use (&$i) {
        // If an anchor href was matched it will be in $matches[2], otherwise raw URL will be in $matches[3]
        $url = !empty($matches[2]) ? $matches[2] : ($matches[3] ?? '');
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    // Handle regular iframes with panopto URLs (not already caught by Gutenberg pattern)
    $iframe_pattern = '/<iframe[^>]+src=["\']([^"\']*panopto[^"\']*)["\'][^>]*>.*?<\/iframe>/is';

    $content = preg_replace_callback($iframe_pattern, function($matches) use (&$i) {
        $url = $matches[1];
        $id = panopto_cure_id($url);
        if ($id) {
            $shortcode = "<div class='player' id='player-{$i}' data-session-id='{$id}'></div>";
            $i++;
            return $shortcode;
        }
        return $matches[0];
    }, $content);

    return $content;
}

function panopto_cure_id($url){
    // Some editors or shortcodes may pass HTML-encoded URLs (e.g. &amp;)
    $decoded = html_entity_decode($url);

    $parsed_url = parse_url($decoded);

    if (!isset($parsed_url['query'])) {
        return null; // No query string
    }

    // Ensure ampersands are real separators for parse_str
    $query = str_replace('&amp;', '&', $parsed_url['query']);
    parse_str($query, $query_params);

    return $query_params['id'] ?? null;
}

/**
 * Convert Panopto Viewer URL to iframe embed code
 *
 * @param string $url Panopto viewer URL (e.g., https://midd.hosted.panopto.com/Panopto/Pages/Viewer.aspx?id=VIDEO_ID)
 * @param int $width Optional width of iframe (default: 720)
 * @param int $height Optional height of iframe (default: 405)
 * @return string|false iframe HTML code or false if URL is invalid
 */
function panopto_url_to_iframe($url, $width = 720, $height = 405) {
    // Validate that this is a Panopto URL
    if (strpos($url, 'panopto') === false) {
        return false;
    }

    // Extract the video ID from the URL
    $video_id = panopto_cure_id($url);

    if (empty($video_id)) {
        return false;
    }

    // Parse the URL to get the host
    $parsed_url = parse_url($url);
    $host = $parsed_url['host'] ?? '';

    if (empty($host)) {
        return false;
    }

    // Construct the embed URL
    $embed_url = "https://{$host}/Panopto/Pages/Embed.aspx?id={$video_id}&autoplay=false&offerviewer=true&showtitle=true&showbrand=true&captions=false&interactivity=all";

    // Build the iframe HTML
    $iframe = sprintf(
        '<iframe src="%s" width="%d" height="%d" style="border: 1px solid #464646;" allowfullscreen allow="autoplay" aria-label="Panopto Embedded Video Player"></iframe>',
        esc_url($embed_url),
        intval($width),
        intval($height)
    );

    return $iframe;
}

// Register the filter
add_filter('panopto_url_to_iframe', 'panopto_url_to_iframe', 10, 3);


/**
 * Shortcode: [panopto url="..."]
 *
 * Examples:
 *  [panopto url="https://host.panopto.com/Panopto/Pages/Viewer.aspx?id=VIDEO_ID"]
 *  [panopto url="..." width="640" height="360"]
 *
 * Returns the iframe generated by panopto_url_to_iframe() or an empty string on failure.
 */
function panopto_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'url'    => '',
            'width'  => 720,
            'height' => 405,
        ),
        $atts,
        'panopto'
    );

    $url = trim( $atts['url'] );
    if ( empty( $url ) ) {
        return '';
    }

    // Basic validation: must be a Panopto URL
    if ( strpos( $url, 'panopto' ) === false ) {
        return '';
    }

    $width  = intval( $atts['width'] );
    $height = intval( $atts['height'] );

    $iframe = panopto_url_to_iframe( $url, $width, $height );

    if ( $iframe === false ) {
        return '';
    }

    return $iframe;
}
add_shortcode( 'panopto', 'panopto_shortcode' );


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

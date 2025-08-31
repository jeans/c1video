<?php
namespace GoDAM\Publics;
if ( ! defined('ABSPATH') ) exit;

class BunnyShortcodes {
    public static function init() {
        add_shortcode('bunny_video', [__CLASS__, 'shortcode_bunny_video']);
        add_shortcode('godam_video', [__CLASS__, 'shortcode_bunny_video']);
        // API routes for TUS
        if ( is_admin() ) {
            // Load REST endpoints
            require_once dirname(__FILE__,2).'/admin/class-godam-admin-bunny-api.php';
            \GoDAM\Admin\Admin_Bunny_API::init();
        }
    }

    public static function shortcode_bunny_video( $atts ) : string {
        $atts = shortcode_atts([
            'id' => '',
            'ratio' => '56.25%',
            'autoplay' => 'false',
            'muted' => 'false',
            'loop' => 'false',
            'responsive' => 'true',
        ], $atts, 'bunny_video');
        $videoId = $atts['id'];
        if ( ! $videoId && is_singular('attachment') ) {
            $videoId = get_post_meta( get_the_ID(), '_bunny_stream_video_id', true );
        }
        $libraryId = get_option( \GoDAM\Admin\Admin_Bunny_Settings::OPT_LIBRARY_ID, '' );
        if ( ! $videoId || ! $libraryId ) return '';
        $params = [
            'autoplay'   => $atts['autoplay'],
            'muted'      => $atts['muted'],
            'loop'       => $atts['loop'],
            'responsive' => $atts['responsive'],
        ];
        $src = sprintf('https://iframe.mediadelivery.net/embed/%s/%s?%s', rawurlencode($libraryId), rawurlencode($videoId), http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        return '<div class="godam-bunny-embed" style="position:relative;padding-top:'.esc_attr($atts['ratio']).';">'
             . '<iframe src="'.esc_url($src).'" loading="lazy" style="border:0;position:absolute;top:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen></iframe>'
             . '</div>';
    }
}

<?php
namespace GoDAM\Admin;
use GoDAM\Providers\BunnyStreamProvider;
if ( ! defined('ABSPATH') ) exit;

class Admin_Bunny_API {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('godam-bunny/v1', '/create-video', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'create_video'],
            'permission_callback' => function() { return current_user_can('upload_files'); },
            'args' => [
                'title' => [ 'type' => 'string', 'required' => false ],
                'collectionId' => [ 'type' => 'string', 'required' => false ],
                'thumbnailTime' => [ 'type' => 'integer', 'required' => false ],
            ],
        ]);

        register_rest_route('godam-bunny/v1', '/presign', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'presign'],
            'permission_callback' => function() { return current_user_can('upload_files'); },
            'args' => [
                'videoId' => [ 'type' => 'string', 'required' => true ],
                'expiresIn' => [ 'type' => 'integer', 'required' => false, 'default' => 3600 ],
            ],
        ]);
    }

    protected static function provider() : BunnyStreamProvider {
        $libraryId = get_option( Admin_Bunny_Settings::OPT_LIBRARY_ID, '' );
        $accessKey = get_option( Admin_Bunny_Settings::OPT_ACCESS_KEY, '' );
        if (!$libraryId || !$accessKey) {
            throw new \RuntimeException('Bunny Stream ist nicht konfiguriert.');
        }
        return new BunnyStreamProvider($libraryId, $accessKey);
    }

    public static function create_video( \WP_REST_Request $req ) {
        $provider = self::provider();
        $title = $req->get_param('title') ?: ('WP Upload '. current_time('mysql'));
        $args  = [];
        if ($req->get_param('collectionId'))  $args['collectionId']  = $req->get_param('collectionId');
        if ($req->get_param('thumbnailTime')) $args['thumbnailTime'] = (int)$req->get_param('thumbnailTime');
        $res = $provider->create( $title, $args );
        $videoId = $res['guid'] ?? ($res['videoId'] ?? null);
        if (!$videoId) return new \WP_Error('bunny_no_videoid', 'videoId/guid missing', ['status'=>500]);
        return [ 'videoId' => $videoId, 'raw' => $res ];
    }

    public static function presign( \WP_REST_Request $req ) {
        $provider = self::provider();
        $videoId   = $req->get_param('videoId');
        $expiresIn = (int) $req->get_param('expiresIn');
        $expireTs  = time() + max(60, $expiresIn);
        $sig = $provider->presignTus($videoId, $expireTs);
        return [ 'authorizationSignature' => $sig['signature'], 'authorizationExpire' => $sig['expire'] ];
    }
}

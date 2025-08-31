<?php
namespace GoDAM\Providers;
if ( ! defined('ABSPATH') ) exit;

class BunnyStreamProvider implements VideoProviderInterface {
    protected string $libraryId;
    protected string $accessKey;

    public function __construct( string $libraryId, string $accessKey ) {
        $this->libraryId = trim($libraryId);
        $this->accessKey = trim($accessKey);
    }

    protected function request( string $method, string $url, array $args = [] ) : array {
        $headers = [
            'AccessKey' => $this->accessKey,
            'Accept'    => 'application/json',
        ];
        $args['headers'] = array_merge($headers, $args['headers'] ?? []);
        $args['method']  = $method;
        $args['timeout'] = $args['timeout'] ?? 60;
        $res = wp_remote_request( $url, $args );
        if ( is_wp_error($res) ) throw new \RuntimeException( $res->get_error_message() );
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ( $code >= 300 ) throw new \RuntimeException( "Bunny API error ($code): $body" );
        $decoded = json_decode( $body, true );
        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    /** Create Video (POST /library/{lib}/videos) */
    public function create( string $title, array $args = [] ) : array {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos', $this->libraryId);
        $payload = [ 'title' => $title ];
        if ( isset($args['collectionId']) )  $payload['collectionId']  = $args['collectionId'];
        if ( isset($args['thumbnailTime']) ) $payload['thumbnailTime'] = (int)$args['thumbnailTime'];
        return $this->request('POST', $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode($payload),
        ]);
    }

    /** Upload binary (PUT /library/{lib}/videos/{videoId}) */
    public function upload( string $videoId, string $filepath ) : void {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos/%s', $this->libraryId, rawurlencode($videoId));
        $this->request('PUT', $url, [
            'headers' => [ 'Content-Type' => 'application/octet-stream' ],
            'body'    => file_get_contents($filepath),
            'timeout' => 0,
        ]);
    }

    /** Get video meta */
    public function get( string $videoId ) : array {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos/%s', $this->libraryId, rawurlencode($videoId));
        return $this->request('GET', $url);
    }

    public function delete( string $videoId ) : bool {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos/%s', $this->libraryId, rawurlencode($videoId));
        $this->request('DELETE', $url);
        return true;
    }

    /** Iframe embed URL */
    public function embedUrl( string $videoId, array $params = [] ) : string {
        $base = sprintf('https://iframe.mediadelivery.net/embed/%s/%s', $this->libraryId, $videoId);
        if ($params) $base .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $base;
    }

    /** Analytics */
    public function getLibraryStats(?string $dateFrom = null, ?string $dateTo = null, bool $hourly = false, ?string $videoGuid = null) : array {
        $url = sprintf('https://video.bunnycdn.com/library/%s/statistics', $this->libraryId);
        $q = [];
        if ($dateFrom) $q['dateFrom'] = $dateFrom; // ISO8601
        if ($dateTo)   $q['dateTo']   = $dateTo;
        $q['hourly']   = $hourly ? 'true' : 'false';
        if ($videoGuid) $q['videoGuid'] = $videoGuid;
        if ($q) $url .= '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        return $this->request('GET', $url);
    }

    public function getPlayData(string $videoId, ?int $expires = 0) : array {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos/%s/play', $this->libraryId, rawurlencode($videoId));
        if ($expires) $url .= '?expires='.$expires;
        return $this->request('GET', $url);
    }

    public function getHeatmap(string $videoId, ?int $expires = 0) : array {
        $url = sprintf('https://video.bunnycdn.com/library/%s/videos/%s/play/heatmap', $this->libraryId, rawurlencode($videoId));
        if ($expires) $url .= '?expires='.$expires;
        return $this->request('GET', $url);
    }

    /** Presign TUS headers: returns ['signature' => ..., 'expire' => ...] */
    public function presignTus(string $videoId, int $expireTs) : array {
        // According to Bunny TUS docs: signature = sha256(library_id + api_key + expiration_time + video_id)
        $data = $this->libraryId . $this->accessKey . $expireTs . $videoId;
        $sig  = hash('sha256', $data);
        return ['signature' => $sig, 'expire' => $expireTs];
    }
}

<?php
namespace GoDAM\Providers;
if ( ! defined('ABSPATH') ) exit;

interface VideoProviderInterface {
    public function create(string $title, array $args = []) : array;
    public function upload(string $videoId, string $filepath) : void;
    public function get(string $videoId) : array;
    public function delete(string $videoId) : bool;
    public function embedUrl(string $videoId, array $params = []) : string;

    // Analytics
    public function getLibraryStats(?string $dateFrom = null, ?string $dateTo = null, bool $hourly = false, ?string $videoGuid = null) : array;
    public function getPlayData(string $videoId, ?int $expires = 0) : array;
    public function getHeatmap(string $videoId, ?int $expires = 0) : array;
}

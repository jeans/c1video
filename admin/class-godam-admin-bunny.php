<?php
namespace GoDAM\Admin;
if ( ! defined('ABSPATH') ) exit;

class Admin_Bunny_Settings {
    const OPT_LIBRARY_ID = 'godam_bunny_library_id';
    const OPT_ACCESS_KEY = 'godam_bunny_access_key';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
    }

    public static function register_settings() {
        register_setting('godam_bunny', self::OPT_LIBRARY_ID, ['type'=>'string','sanitize_callback'=>'sanitize_text_field']);
        register_setting('godam_bunny', self::OPT_ACCESS_KEY, ['type'=>'string','sanitize_callback'=>'sanitize_text_field']);

        add_settings_section('godam_bunny_main', __('Bunny Stream','godam'), function(){
            echo '<p>'.esc_html__('Konfigurieren Sie Ihre Bunny.net Stream-Zugangsdaten (pro Video Library).','godam').'</p>';
        }, 'godam_bunny');

        add_settings_field(self::OPT_LIBRARY_ID, __('Library ID','godam'), function(){
            $v = get_option(self::OPT_LIBRARY_ID,'');
            echo '<input type="text" name="'.esc_attr(self::OPT_LIBRARY_ID).'" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'godam_bunny', 'godam_bunny_main');

        add_settings_field(self::OPT_ACCESS_KEY, __('Access Key','godam'), function(){
            $v = get_option(self::OPT_ACCESS_KEY,'');
            echo '<input type="password" name="'.esc_attr(self::OPT_ACCESS_KEY).'" value="'.esc_attr($v).'" class="regular-text" />';
            echo '<p class="description">'.esc_html__('Bunny Stream AccessKey (nicht der globale Account-Key)','godam').'</p>';
        }, 'godam_bunny', 'godam_bunny_main');
    }

    public static function add_menu() {
        add_submenu_page('upload.php', __('Bunny Stream','godam'), __('Bunny Stream','godam'), 'manage_options', 'godam-bunny', [__CLASS__,'render_settings']);
        add_submenu_page('upload.php', __('Bunny Analytics','godam'), __('Bunny Analytics','godam'), 'manage_options', 'godam-bunny-analytics', [__CLASS__,'render_analytics']);
        add_submenu_page('upload.php', __('Bunny Upload (TUS)','godam'), __('Bunny Upload (TUS)','godam'), 'upload_files', 'godam-bunny-upload', [__CLASS__,'render_uploader']);
    }

    public static function render_settings() {
        echo '<div class="wrap"><h1>Bunny Stream</h1><form method="post" action="options.php">';
        settings_fields('godam_bunny'); do_settings_sections('godam_bunny'); submit_button(); echo '</form></div>';
    }

    public static function render_analytics() {
        $libraryId = get_option(self::OPT_LIBRARY_ID,''); $accessKey = get_option(self::OPT_ACCESS_KEY,'');
        echo '<div class="wrap"><h1>Bunny Stream Analytics</h1>';
        if (!$libraryId || !$accessKey) { echo '<p>'.esc_html__('Bitte Library ID und Access Key speichern.','godam').'</p></div>'; return; }
        $dateFrom  = isset($_GET['dateFrom']) ? sanitize_text_field($_GET['dateFrom']) : '';
        $dateTo    = isset($_GET['dateTo'])   ? sanitize_text_field($_GET['dateTo'])   : '';
        $videoGuid = isset($_GET['videoGuid'])? sanitize_text_field($_GET['videoGuid']): '';
        $hourly    = isset($_GET['hourly'])   ? (bool) $_GET['hourly'] : false;
        echo '<form method="get"><input type="hidden" name="page" value="godam-bunny-analytics" />';
        echo '<input type="datetime-local" name="dateFrom" value="'.esc_attr($dateFrom).'" /> ';
        echo '<input type="datetime-local" name="dateTo" value="'.esc_attr($dateTo).'" /> ';
        echo '<input type="text" name="videoGuid" placeholder="Video GUID (optional)" value="'.esc_attr($videoGuid).'" /> ';
        echo '<label><input type="checkbox" name="hourly" value="1" '.checked($hourly,true,false).'/> stündlich</label> ';
        submit_button('Aktualisieren', 'secondary', '', false); echo '</form>';
        $provider = new \GoDAM\Providers\BunnyStreamProvider($libraryId, $accessKey);
        try {
            $stats = $provider->getLibraryStats($dateFrom ?: null, $dateTo ?: null, $hourly, $videoGuid ?: null);
            echo '<pre style="background:#fff;border:1px solid #ddd;padding:10px;overflow:auto">'.esc_html(wp_json_encode($stats, JSON_PRETTY_PRINT)).'</pre>';
            if ($videoGuid) {
                $play    = $provider->getPlayData($videoGuid, 0);
                $heatmap = $provider->getHeatmap($videoGuid, 0);
                echo '<h2>Play Data</h2><pre>'.esc_html(wp_json_encode($play, JSON_PRETTY_PRINT)).'</pre>';
                echo '<h2>Heatmap</h2><pre>'.esc_html(wp_json_encode($heatmap, JSON_PRETTY_PRINT)).'</pre>';
            }
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>';
        }
        echo '</div>';
    }

    public static function render_uploader() {
        $lib = get_option(self::OPT_LIBRARY_ID,''); $key = get_option(self::OPT_ACCESS_KEY,'');
        echo '<div class="wrap"><h1>Bunny Upload (TUS)</h1>';
        if (!$lib || !$key) { echo '<p>'.esc_html__('Bitte zuerst Library ID & Access Key speichern.','godam').'</p></div>'; return; }
        // Enqueue Scripts & Localize
        wp_enqueue_script('tus-js', 'https://cdn.jsdelivr.net/npm/tus-js-client@3.1.0/dist/tus.min.js', [], null, true);
        wp_enqueue_script('godam-bunny-tus', plugins_url('../assets/js/bunny-tus-uploader.js', __FILE__), ['tus-js'], null, true);
        wp_localize_script('godam-bunny-tus', 'GodamBunnyTus', [
            'restUrl'   => esc_url_raw( rest_url('godam-bunny/v1/') ),
            'nonce'     => wp_create_nonce('wp_rest'),
            'libraryId' => $lib,
        ]);
        echo '<div id="godam-bunny-tus-root">';
        echo '<p>'.esc_html__('Wähle ein Video (<= einige GB, resumierbar).', 'godam').'</p>';
        echo '<input type="text" id="tus-title" placeholder="Titel (optional)" class="regular-text" /> ';
        echo '<input type="file" id="tus-file" accept="video/*" /> ';
        echo '<button class="button button-primary" id="tus-start">Upload starten</button>';
        echo '<div style="margin-top:10px"><progress id="tus-progress" value="0" max="100" style="width:300px"></progress> <span id="tus-status"></span></div>';
        echo '<pre id="tus-log" style="background:#fff;border:1px solid #ddd;padding:10px;overflow:auto;max-height:260px"></pre>';
        echo '</div></div>';
    }
}

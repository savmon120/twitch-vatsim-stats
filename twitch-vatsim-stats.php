<?php
/*
Plugin Name: Twitch + VATSIM Stats (OAuth)
Plugin URI: https://example.com
Description: Displays Twitch followers, subscribers, Twitch live status, and your VATSIM pilot/controller hours via shortcode [twitch_vatsim_stats]. Includes built-in Twitch OAuth (user token + refresh).
Version: 3.0.0
Author: Sav Monzac
Author URI: https://example.com
License: GPL2
*/

if (!defined('ABSPATH')) exit; // No direct access

class TwitchVatsimStatsOAuth {
    private $option_name = 'tvs_settings';
    private $redirect_action = 'tvs_twitch_oauth_callback';
    private $disconnect_action = 'tvs_twitch_disconnect';

    function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_shortcode('twitch_vatsim_stats', [$this, 'render_shortcode']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // OAuth callback + disconnect
        add_action('admin_post_' . $this->redirect_action, [$this, 'handle_oauth_callback']);
        add_action('admin_post_nopriv_' . $this->redirect_action, [$this, 'handle_oauth_callback']);
        add_action('admin_post_' . $this->disconnect_action, [$this, 'handle_disconnect']);
        add_action('admin_post_nopriv_' . $this->disconnect_action, [$this, 'handle_disconnect']);
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'tvs-stats-style',
            plugin_dir_url(__FILE__) . 'assets/css/tvs-style.css',
            [],
            '3.0.0'
        );
    }

    /* -----------------------------
     * Admin Settings
     * --------------------------- */
    function add_settings_page() {
        add_options_page(
            'Twitch + VATSIM Stats',
            'Twitch + VATSIM Stats',
            'manage_options',
            'tvs-settings',
            [$this, 'settings_page_html']
        );
    }

    function register_settings() {
        register_setting('tvs_settings_group', $this->option_name);
    }

    private function get_settings() {
        $defaults = [
            'client_id' => '',
            'client_secret' => '',
            'username' => '',
            'vatsim_cid' => '',
            // NEW fallback fields
            'fallback_pilot_hours' => 0,
            'fallback_controller_hours' => 0,

            'debug' => 0,
            'access_token' => '',
            'refresh_token' => '',
            'token_expires_at' => 0,
            'user_id' => '',
            'login' => '',
            'display_name' => '',
            'scopes' => [],
        ];
        $settings = get_option($this->option_name, []);
        return wp_parse_args($settings, $defaults);
    }

    private function save_settings($updates) {
        $settings = $this->get_settings();
        $settings = array_merge($settings, $updates);
        update_option($this->option_name, $settings);
        return $settings;
    }

    private function get_redirect_uri() {
        return admin_url('admin-post.php?action=' . $this->redirect_action);
    }

    private function get_authorize_url($client_id) {
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $this->get_redirect_uri(),
            'response_type' => 'code',
            'scope' => 'moderator:read:followers channel:read:subscriptions',
            'force_verify' => 'true'
        ];
        return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query($params);
    }

    function settings_page_html() {
        if (!current_user_can('manage_options')) return;
        $s = $this->get_settings();
        $connected = !empty($s['access_token']) && !empty($s['user_id']);
        ?>
        <div class="wrap">
            <h1>Twitch + VATSIM Stats</h1>
            <p>Shortcode: <code>[twitch_vatsim_stats]</code></p>

            <form method="post" action="options.php">
                <?php settings_fields('tvs_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Twitch Client ID</th>
                        <td><input type="text" name="tvs_settings[client_id]" value="<?php echo esc_attr($s['client_id']); ?>" size="50"></td>
                    </tr>
                    <tr>
                        <th scope="row">Twitch Client Secret</th>
                        <td><input type="text" name="tvs_settings[client_secret]" value="<?php echo esc_attr($s['client_secret']); ?>" size="50"></td>
                    </tr>
                    <tr>
                        <th scope="row">Twitch Username (optional)</th>
                        <td><input type="text" name="tvs_settings[username]" value="<?php echo esc_attr($s['username']); ?>" size="30">
                        <p class="description">Display only. OAuth token resolves account.</p></td>
                    </tr>
                    <tr>
                        <th scope="row">Your VATSIM CID</th>
                        <td><input type="number" name="tvs_settings[vatsim_cid]" value="<?php echo esc_attr($s['vatsim_cid']); ?>"></td>
                    </tr>

                    <!-- NEW: Fallback fields -->
                    <tr>
                        <th scope="row">Fallback Pilot Hours</th>
                        <td>
                            <input type="number" name="tvs_settings[fallback_pilot_hours]" value="<?php echo esc_attr($s['fallback_pilot_hours']); ?>">
                            <p class="description">Optional: Added to API total or used if API fails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback Controller Hours</th>
                        <td>
                            <input type="number" name="tvs_settings[fallback_controller_hours]" value="<?php echo esc_attr($s['fallback_controller_hours']); ?>">
                            <p class="description">Optional: Added to API total or used if API fails.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Enable Debug Mode</th>
                        <td><label><input type="checkbox" name="tvs_settings[debug]" value="1" <?php checked($s['debug'], 1); ?>> Show raw API responses</label></td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>

            <h2>Twitch Connection</h2>
            <p>Status:
                <?php if ($connected): ?>
                    <span style="color: #008000; font-weight:700;">Connected</span>
                    <?php if ($s['display_name']): ?>
                        as <strong><?php echo esc_html($s['display_name']); ?></strong>
                        (<?php echo esc_html($s['login']); ?>, ID: <?php echo esc_html($s['user_id']); ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #b00; font-weight:700;">Not Connected</span>
                <?php endif; ?>
            </p>

            <?php if (empty($s['client_id']) || empty($s['client_secret'])): ?>
                <p style="color:#b00;">Enter Client ID & Secret above, Save, then connect.</p>
            <?php else: ?>
                <p>Ensure this Redirect URI is added to your Twitch app:</p>
                <code><?php echo esc_html($this->get_redirect_uri()); ?></code>
                <?php if ($connected): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr($this->disconnect_action); ?>">
                        <?php wp_nonce_field('tvs_disconnect'); ?>
                        <?php submit_button('Disconnect Twitch', 'delete'); ?>
                    </form>
                <?php else: ?>
                    <a class="button button-primary" href="<?php echo esc_url($this->get_authorize_url($s['client_id'])); ?>">Connect with Twitch</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($_GET['tvs_msg'])): ?>
                <div class="notice notice-info" style="margin-top:20px;"><p><?php echo esc_html($_GET['tvs_msg']); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* -----------------------------
     * OAuth Handlers
     * --------------------------- */
    function handle_disconnect() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'tvs_disconnect')) wp_die('Invalid nonce.');
        $this->save_settings([
            'access_token' => '', 'refresh_token' => '', 'token_expires_at' => 0,
            'user_id' => '', 'login' => '', 'display_name' => '', 'scopes' => [],
        ]);
        wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode('Disconnected from Twitch.'), admin_url('options-general.php?page=tvs-settings')));
        exit;
    }

    function handle_oauth_callback() {
        $s = $this->get_settings();
        if (empty($_GET['code'])) {
            $msg = !empty($_GET['error_description']) ? $_GET['error_description'] : 'Missing authorization code.';
            wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode('OAuth failed: ' . $msg), admin_url('options-general.php?page=tvs-settings')));
            exit;
        }
        if (empty($s['client_id']) || empty($s['client_secret'])) {
            wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode('Client ID/Secret missing.'), admin_url('options-general.php?page=tvs-settings')));
            exit;
        }

        $token_response = wp_remote_post('https://id.twitch.tv/oauth2/token', [
            'timeout' => 20,
            'body' => [
                'client_id' => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'code' => sanitize_text_field($_GET['code']),
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->get_redirect_uri(),
            ],
        ]);
        $body = json_decode(wp_remote_retrieve_body($token_response), true);

        if (empty($body['access_token'])) {
            $msg = 'OAuth token exchange failed.' . (!empty($s['debug']) ? print_r($body, true) : '');
            wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode($msg), admin_url('options-general.php?page=tvs-settings')));
            exit;
        }

        $access_token = $body['access_token'];
        $refresh_token = $body['refresh_token'] ?? '';
        $expires_in = intval($body['expires_in'] ?? 0);
        $scopes = $body['scope'] ?? [];

        $user_response = $this->api_get('https://api.twitch.tv/helix/users', $access_token, $s['client_id']);
        $user_data = $user_response['body']['data'][0] ?? null;
        $user_id = $user_data['id'] ?? '';
        $login = $user_data['login'] ?? '';
        $display_name = $user_data['display_name'] ?? '';

        if (!$user_id) {
            $msg = 'Could not resolve user from token.' . (!empty($s['debug']) ? print_r($user, true) : '');
            wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode($msg), admin_url('options-general.php?page=tvs-settings')));
            exit;
        }

        $this->save_settings([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_expires_at' => time() + $expires_in - 60,
            'user_id' => $user_id,
            'login' => $login,
            'display_name' => $display_name,
            'scopes' => $scopes,
        ]);

        wp_safe_redirect(add_query_arg('tvs_msg', rawurlencode('Connected to Twitch as ' . $display_name . '.'), admin_url('options-general.php?page=tvs-settings')));
        exit;
    }

    private function refresh_token_if_needed(&$settings) {
        if (empty($settings['access_token']) || empty($settings['refresh_token']) || empty($settings['client_id']) || empty($settings['client_secret'])) return false;
        if (time() < intval($settings['token_expires_at'])) return true;

        $resp = wp_remote_post('https://id.twitch.tv/oauth2/token', [
            'timeout' => 20,
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $settings['refresh_token'],
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
            ],
        ]);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!empty($body['access_token'])) {
            $settings = $this->save_settings([
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? $settings['refresh_token'],
                'token_expires_at' => time() + intval($body['expires_in'] ?? 3600) - 60,
            ]);
            return true;
        }
        return false;
    }

    private function api_get($url, $access_token, $client_id, $args = []) {
        $headers = ['Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $access_token];
        $resp = wp_remote_get(add_query_arg($args, $url), ['headers' => $headers, 'timeout' => 20]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return ['code' => $code, 'body' => $body];
    }

    /* -----------------------------
     * VATSIM helper (stats API; offline totals)
     * --------------------------- */
private function fetch_vatsim_hours($cid, $debug = false) {
    // Use floats — v2 returns decimal hours (e.g. 762.5)
    $pilot_hours = 0.0;
    $controller_hours = 0.0;
    $raw = null;

    if (!$cid) {
        return [$pilot_hours, $controller_hours, $raw];
    }

    $url = 'https://api.vatsim.net/v2/members/' . intval($cid) . '/stats';
    $resp = wp_remote_get($url, ['timeout' => 20]);

    if (is_wp_error($resp)) {
        if ($debug) $raw = ['error' => $resp->get_error_message()];
        return [$pilot_hours, $controller_hours, $raw];
    }

    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);

    if (!$data) {
        if ($debug) $raw = ['raw_body' => $body];
        return [$pilot_hours, $controller_hours, $raw];
    }

    // v2 returns 'pilot' and 'atc' (floats). Support other key names too for robustness.
    if (isset($data['pilot'])) {
        $pilot_hours = floatval($data['pilot']);
    } elseif (isset($data['pilot_hours'])) {
        $pilot_hours = floatval($data['pilot_hours']);
    } elseif (isset($data['pilotHours'])) {
        $pilot_hours = floatval($data['pilotHours']);
    }

    if (isset($data['atc'])) {
        $controller_hours = floatval($data['atc']);
    } elseif (isset($data['atc_hours'])) {
        $controller_hours = floatval($data['atc_hours']);
    } elseif (isset($data['controller_hours'])) {
        $controller_hours = floatval($data['controller_hours']);
    } elseif (isset($data['atc_hours'])) {
        $controller_hours = floatval($data['atc_hours']);
    }

    if ($debug) $raw = $data;

    return [$pilot_hours, $controller_hours, $raw];
}

    /* -----------------------------
     * Shortcode
     * --------------------------- */
    function render_shortcode() {
        $s = $this->get_settings();
        if (empty($s['client_id']) || empty($s['client_secret'])) return "<p><em>Please configure Client ID/Secret in Settings → Twitch + VATSIM Stats.</em></p>";
        if (empty($s['access_token']) || empty($s['user_id'])) {
            $connect_url = admin_url('options-general.php?page=tvs-settings');
            return "<p><em>Not connected to Twitch. <a href='".esc_url($connect_url)."'>Connect now</a>.</em></p>";
        }

        $this->refresh_token_if_needed($s);

        // Twitch followers/subs
        $followers_resp = $this->api_get('https://api.twitch.tv/helix/channels/followers', $s['access_token'], $s['client_id'], ['broadcaster_id'=>$s['user_id']]);
        $subs_resp = $this->api_get('https://api.twitch.tv/helix/subscriptions', $s['access_token'], $s['client_id'], ['broadcaster_id'=>$s['user_id']]);
        $followers_total = $followers_resp['body']['total'] ?? 0;
        $subs_total = isset($subs_resp['body']['total']) ? intval($subs_resp['body']['total']) : (isset($subs_resp['body']['data']) ? count($subs_resp['body']['data']) : 0);

        // Twitch live status
        $live_resp = $this->api_get('https://api.twitch.tv/helix/streams', $s['access_token'], $s['client_id'], ['user_id'=>$s['user_id']]);
        $is_live = !empty($live_resp['body']['data']);

        // VATSIM hours (stats API) + fallback addition
        list($pilot_hours, $controller_hours, $vatsim_raw) = $this->fetch_vatsim_hours($s['vatsim_cid'] ?? 0, !empty($s['debug']));

        // Add fallback values
        $pilot_hours += intval($s['fallback_pilot_hours'] ?? 0);
        $controller_hours += intval($s['fallback_controller_hours'] ?? 0);

        // Output
        $out = "<div class='tvs-stats-wrapper'>
            <div class='tvs-stat'>
                <div class='tvs-value'>".esc_html($followers_total)."</div>
                <div class='tvs-label'>Twitch Followers".($is_live ? " <span class='tvs-live'>LIVE</span>" : "")."</div>
            </div>
            <div class='tvs-stat'>
                <div class='tvs-value'>".esc_html($subs_total)."</div>
                <div class='tvs-label'>Twitch Subscribers</div>
            </div>
            <div class='tvs-stat'>
                <div class='tvs-value'>".esc_html($pilot_hours)."</div>
                <div class='tvs-label'>Pilot Hours</div>
            </div>
            <div class='tvs-stat'>
                <div class='tvs-value'>".esc_html($controller_hours)."</div>
                <div class='tvs-label'>Controller Hours</div>
            </div>
        </div>";

        if (!empty($s['debug'])) {
            $dbg = [
                'followers_resp'=>$followers_resp,
                'subs_resp'=>$subs_resp,
                'live_resp'=>$live_resp,
                'vatsim_cid'=>$s['vatsim_cid'],
                'vatsim_raw'=>$vatsim_raw,
            ];
            $out .= "<pre style='background:#111;color:#0f0;padding:10px;overflow:auto;max-height:400px;white-space:pre-wrap;'>DEBUG:\n".esc_html(print_r($dbg,true))."</pre>";
        }

        return $out;
    }
}

new TwitchVatsimStatsOAuth();

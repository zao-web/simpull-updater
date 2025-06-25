<?php
/**
 * Simpull Updater - Lightweight GitHub-based WordPress plugin updater
 *
 * @package SimpullUpdater
 * @version 2.0.0
 */
class SimpullUpdater {
    protected $pluginFile;
    protected $slug;
    protected $repo;
    protected $githubToken;
    protected $cacheKey;
    protected $cacheExpiry = 3600; // 1 hour

    public function __construct($pluginFile, $slug, $repo, $githubToken = null) {
        $this->pluginFile = $pluginFile;
        $this->slug = sanitize_key($slug);
        $this->repo = $this->sanitizeRepo($repo);
        $this->githubToken = $githubToken;
        $this->cacheKey = 'simpull_updater_' . $this->slug;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
    }

    /**
     * Sanitize repository name to prevent injection
     */
    protected function sanitizeRepo($repo) {
        // Only allow alphanumeric, hyphens, underscores, and forward slashes
        return preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $repo);
    }

    /**
     * Get cached release data or fetch from GitHub
     */
    protected function getReleaseData() {
        // Check cache first
        $cached = get_transient($this->cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            'Accept' => 'application/vnd.github.v3+json',
        ];

        if ($this->githubToken) {
            $headers['Authorization'] = 'token ' . $this->githubToken;
        }

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log("SimpullUpdater: Failed to fetch release data for {$this->slug}: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !isset($data->tag_name)) {
            error_log("SimpullUpdater: Invalid response data for {$this->slug}");
            return null;
        }

        // Cache the response
        set_transient($this->cacheKey, $data, $this->cacheExpiry);

        return $data;
    }

    /**
     * Safely extract version from tag name
     */
    protected function extractVersion($tagName) {
        // Remove all 'v' characters from the beginning
        return preg_replace('/^v+/', '', $tagName);
    }

    /**
     * Safely get download URL from release assets
     */
    protected function getDownloadUrl($release) {
        if (!isset($release->assets) || !is_array($release->assets) || empty($release->assets)) {
            return null;
        }

        // Look for a zip file
        foreach ($release->assets as $asset) {
            if (isset($asset->browser_download_url) &&
                pathinfo($asset->name, PATHINFO_EXTENSION) === 'zip') {
                return $asset->browser_download_url;
            }
        }

        // Fallback to first asset if no zip found
        return $release->assets[0]->browser_download_url ?? null;
    }

    /**
     * Sanitize HTML content
     */
    protected function sanitizeHtml($content) {
        return wp_kses($content, [
            'a' => ['href' => [], 'target' => []],
            'br' => [],
            'p' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
        ]);
    }

    public function checkForUpdate($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->getReleaseData();
        if (!$release) {
            return $transient;
        }

        $currentVersion = $transient->checked[$this->slug] ?? null;
        if (!$currentVersion) {
            return $transient;
        }

        $latestVersion = $this->extractVersion($release->tag_name);
        $downloadUrl = $this->getDownloadUrl($release);

        if (!$downloadUrl) {
            error_log("SimpullUpdater: No download URL found for {$this->slug}");
            return $transient;
        }

        if (version_compare($latestVersion, $currentVersion, '>')) {
            $transient->response[$this->slug] = (object)[
                'slug'        => $this->slug,
                'plugin'      => $this->slug,
                'new_version' => $latestVersion,
                'url'         => esc_url($release->html_url),
                'package'     => esc_url($downloadUrl),
                'requires'    => '5.0',
                'tested'      => '6.4',
                'last_updated' => $release->published_at ?? '',
            ];
        }

        return $transient;
    }

    public function pluginInfo($res, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->slug) {
            return $res;
        }

        $release = $this->getReleaseData();
        if (!$release) {
            return $res;
        }

        $downloadUrl = $this->getDownloadUrl($release);
        if (!$downloadUrl) {
            return $res;
        }

        return (object)[
            'name'          => esc_html(ucwords(str_replace('-', ' ', $this->slug))),
            'slug'          => $this->slug,
            'version'       => $this->extractVersion($release->tag_name),
            'author'        => $this->sanitizeHtml('<a href="https://simpull.co" target="_blank">Simpull</a>'),
            'homepage'      => esc_url($release->html_url),
            'download_link' => esc_url($downloadUrl),
            'requires'      => '5.0',
            'tested'        => '6.4',
            'last_updated'  => $release->published_at ?? '',
            'sections'      => [
                'description' => $this->sanitizeHtml(nl2br($release->body ?? '')),
                'changelog'   => $this->sanitizeHtml(nl2br($release->body ?? '')),
            ],
        ];
    }

    /**
     * Clear cached data (useful for testing or manual refresh)
     */
    public function clearCache() {
        delete_transient($this->cacheKey);
    }
}

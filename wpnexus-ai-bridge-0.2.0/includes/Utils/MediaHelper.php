<?php
namespace WPNexusAIBridge\Utils;

use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) { exit; }

final class MediaHelper {

    /** @var Logger */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    /**
     * Download an external URL and attach to post.
     * @return int attachment_id or 0
     */
    public function sideload_url(string $url, int $post_id, string $desc = ''): int {
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            $this->logger->warn('media.download.fail', ['url' => $url, 'err' => $tmp->get_error_message()]);
            return 0;
        }

        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
        if (!$name) { $name = 'image.jpg'; }

        $file = [
            'name' => sanitize_file_name($name),
            'type' => '',
            'tmp_name' => $tmp,
            'error' => 0,
            'size' => filesize($tmp),
        ];

        $id = media_handle_sideload($file, $post_id, $desc);
        @unlink($tmp);

        if (is_wp_error($id)) {
            $this->logger->warn('media.sideload.fail', ['url' => $url, 'err' => $id->get_error_message()]);
            return 0;
        }

        return (int) $id;
    }

    /**
     * Sideload base64 image data.
     * @return int attachment_id or 0
     */
    public function sideload_b64(string $b64, string $filename, string $mime, int $post_id, string $desc = ''): int {
        $data = base64_decode($b64);
        if (!$data) {
            $this->logger->warn('media.b64.decode.fail');
            return 0;
        }

        $tmp = wp_tempnam($filename);
        if (!$tmp) { return 0; }

        file_put_contents($tmp, $data);

        $file = [
            'name' => sanitize_file_name($filename),
            'type' => $mime,
            'tmp_name' => $tmp,
            'error' => 0,
            'size' => filesize($tmp),
        ];

        $id = media_handle_sideload($file, $post_id, $desc);
        @unlink($tmp);

        if (is_wp_error($id)) {
            $this->logger->warn('media.b64.sideload.fail', ['err' => $id->get_error_message()]);
            return 0;
        }

        return (int) $id;
    }
}

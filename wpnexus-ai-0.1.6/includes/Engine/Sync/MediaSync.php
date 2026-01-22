<?php
namespace WPNexusAI\Engine\Sync;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Bridge\Client\BridgeClient;

if (!defined('ABSPATH')) { exit; }

/**
 * Media sync (queued context):
 * - Uploads featured + inline media to Bridge
 * - Rewrites content URLs to target URLs
 * - Stores per-post cache map in post meta to avoid re-upload
 */
final class MediaSync {

  private $logger;
  private $bridge;

  public function __construct() {
    $this->logger = Logger::instance();
    $this->bridge = new BridgeClient();
  }

  /**
   * @param int $source_post_id
   * @param array<string,mixed> $target_row
   * @param string $target_lang
   * @param array<string,mixed> $extracted
   * @param array<string,mixed> $job_payload
   * @return array{content:string, featured_image_id:int, rewritten:int, uploaded:int}|WP_Error
   */
  public function sync(int $source_post_id, array $target_row, string $target_lang, array $extracted, array $job_payload) {
    $target_id = isset($target_row['id']) ? (int) $target_row['id'] : 0;
    if ($source_post_id <= 0 || $target_id <= 0) {
      return new WP_Error('wpnexus_media_invalid', 'Invalid sync payload');
    }

    $content = isset($extracted['content']) && is_string($extracted['content']) ? (string) $extracted['content'] : '';
    $uploads = wp_upload_dir();
    $baseurl = isset($uploads['baseurl']) ? rtrim((string) $uploads['baseurl'], '/') : '';
    $basedir = isset($uploads['basedir']) ? rtrim((string) $uploads['basedir'], DIRECTORY_SEPARATOR) : '';

    $meta_key = '_wpnexus_ai_media_map';
    $all_maps = get_post_meta($source_post_id, $meta_key, true);
    $all_maps = is_array($all_maps) ? $all_maps : [];
    $map = [];
    if (isset($all_maps[$target_id]) && is_array($all_maps[$target_id]) && isset($all_maps[$target_id][$target_lang]) && is_array($all_maps[$target_id][$target_lang])) {
      $map = $all_maps[$target_id][$target_lang];
    }

    $this->logger->info('media.sync.start', ['post_id'=>$source_post_id,'target_id'=>$target_id,'lang'=>$target_lang]);

    $ids = $this->extract_attachment_ids($content);
    $urls = $this->extract_urls($content);

    $uploaded = 0;
    $rewritten = 0;
    $id_map = [];
    $url_map = [];

    // Featured Image
    $featured_id = 0;
    if (!empty($extracted['featured_image']) && is_array($extracted['featured_image'])) {
      $fi = $extracted['featured_image'];
      $src_att_id = isset($fi['attachment_id']) ? (int) $fi['attachment_id'] : 0;
      if ($src_att_id > 0) {
        $res = $this->upload_attachment_id($src_att_id, $target_row, $map);
        if (is_wp_error($res)) {
          if ((string)$res->get_error_code() === 'wpnexus_media_bridge_missing') { return $res; }
        } else {
          $featured_id = (int) ($res['attachment_id'] ?? 0);
          if ($featured_id > 0) {
            $id_map[$src_att_id] = $featured_id;
            $uploaded += (int) ($res['uploaded'] ?? 0);
            $this->logger->info('media.featured.upload.ok', ['target_id'=>$target_id,'src'=>$src_att_id,'dst'=>$featured_id]);
          }
        }
      }
    }

    // IDs from content
    foreach ($ids as $src_att_id) {
      if ($src_att_id <= 0 || isset($id_map[$src_att_id])) { continue; }
      $res = $this->upload_attachment_id($src_att_id, $target_row, $map);
      if (is_wp_error($res)) {
        if ((string)$res->get_error_code() === 'wpnexus_media_bridge_missing') { return $res; }
        continue;
      }
      $tid = (int) ($res['attachment_id'] ?? 0);
      if ($tid > 0) { 
        $id_map[$src_att_id] = $tid; 
        $uploaded += (int) ($res['uploaded'] ?? 0); 
      }
    }

    // URLs from content
    foreach ($urls as $u) {
      $u = (string) $u;
      if ($u === '' || isset($url_map[$u])) { continue; }
      $local = $this->local_path_from_url($u, $baseurl, $basedir);
      if (!$local) { continue; }
      $res = $this->upload_local_file($local, $u, $target_row, $map);
      if (is_wp_error($res)) {
        if ((string)$res->get_error_code() === 'wpnexus_media_bridge_missing') { return $res; }
        continue;
      }
      $remote_url = isset($res['url']) ? (string) $res['url'] : '';
      if ($remote_url !== '') {
        $url_map[$u] = $remote_url;
        $uploaded += (int) ($res['uploaded'] ?? 0);
      }
    }

    if (!empty($url_map)) {
      foreach ($url_map as $src => $dst) {
        $before = $content;
        $content = str_replace($src, $dst, $content);
        if ($content !== $before) { $rewritten++; }
      }
    }

    // Strip srcset/sizes
    $c2 = preg_replace('/\s(?:srcset|sizes)=\"[^\"]*\"/i', '', $content);
    if (is_string($c2)) { $content = $c2; }

    // Best-effort ID rewrite
    if (!empty($id_map)) {
      foreach ($id_map as $src_id => $dst_id) {
        $content = preg_replace('/\bwp-image-' . (int) $src_id . '\b/', 'wp-image-' . (int) $dst_id, $content);
        $content = preg_replace('/\bdata-id=\"' . (int) $src_id . '\"/', 'data-id=\"' . (int) $dst_id . '\"', $content);
      }
    }

    // Persist cache
    $all_maps[$target_id] = isset($all_maps[$target_id]) && is_array($all_maps[$target_id]) ? $all_maps[$target_id] : [];
    $all_maps[$target_id][$target_lang] = $map;
    update_post_meta($source_post_id, $meta_key, $all_maps);

    $this->logger->info('media.sync.done', [
        'post_id'           => $source_post_id,
        'target_id'         => $target_id,
        'lang'              => $target_lang,
        'featured_image_id' => $featured_id,
        'uploaded'          => $uploaded,
        'rewritten'         => $rewritten
    ]);

    return [
        'content'           => (string)$content,
        'featured_image_id' => (int)$featured_id,
        'rewritten'         => (int)$rewritten,
        'uploaded'          => (int)$uploaded
    ];
  }

  private function extract_attachment_ids(string $html): array {
    $ids = [];
    if ($html === '') { return $ids; }
    if (preg_match_all('/\bwp-image-(\d+)\b/', $html, $m)) { foreach ($m[1] as $id) { $ids[] = (int)$id; } }
    if (preg_match_all('/\bdata-id=\"(\d+)\"/', $html, $m2)) { foreach ($m2[1] as $id) { $ids[] = (int)$id; } }
    $ids = array_values(array_unique(array_filter($ids)));
    return $ids;
  }

  private function extract_urls(string $html): array {
    $urls = [];
    if ($html === '') { return $urls; }
    if (preg_match_all('/\s(?:src|href)=\"([^\"]+)\"/i', $html, $m)) {
      foreach ($m[1] as $u) { $u = trim((string)$u); if ($u !== '') { $urls[] = $u; } }
    }
    return array_values(array_unique($urls));
  }

  private function local_path_from_url(string $url, string $baseurl, string $basedir): ?string {
    if ($url === '' || $baseurl === '' || $basedir === '') { return null; }
    if (strpos($url, $baseurl) !== 0) { return null; }
    $rel = ltrim(substr($url, strlen($baseurl)), '/');
    $path = wp_normalize_path($basedir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
    if (!file_exists($path) || !is_readable($path)) { return null; }
    return $path;
  }

  private function upload_attachment_id(int $attachment_id, array $target_row, array &$map) {
    $file = (string) get_attached_file($attachment_id);
    $url  = (string) wp_get_attachment_url($attachment_id);
    $mime = (string) get_post_mime_type($attachment_id);
    if ($file === '' || !file_exists($file) || !is_readable($file)) {
      return new WP_Error('wpnexus_media_missing_file', 'Media file missing on disk');
    }
    return $this->upload_local_file($file, $url, $target_row, $map, $attachment_id, $mime);
  }

  private function upload_local_file(string $file_path, string $source_url, array $target_row, array &$map, int $source_attachment_id = 0, string $mime = '') {
    $target_id = isset($target_row['id']) ? (int)$target_row['id'] : 0;

    // Start upload profiling and size check
    $size = @filesize($file_path);
    $size = is_int($size) ? $size : 0;
    
    $this->logger->info('media.upload.start', [
      'target_id' => $target_id,
      'file'      => basename($file_path),
      'bytes'     => $size,
      'mime'      => $mime,
    ]);

    // Hard limit (e.g. 8MB) - mitigates 300s timeout risks
    $max = defined('WPNEXUS_AI_MEDIA_MAX_BYTES') ? (int) WPNEXUS_AI_MEDIA_MAX_BYTES : (8 * 1024 * 1024);
    if ($size > 0 && $size > $max) {
      $this->logger->warning('media.upload.skipped.too_large', [
        'target_id' => $target_id,
        'bytes'     => $size,
        'max'       => $max,
      ]);
      return new \WP_Error('wpnexus_media_too_large', 'Media too large');
    }

    $hash = @md5_file($file_path);
    $hash = is_string($hash) ? $hash : '';
    $signature = hash('sha256', site_url() . '|t:' . $target_id . '|a:' . (int)$source_attachment_id . '|u:' . $source_url . '|h:' . $hash);

    if (isset($map[$signature]) && is_array($map[$signature]) && !empty($map[$signature]['attachment_id']) && !empty($map[$signature]['url'])) {
      return ['attachment_id'=>(int)$map[$signature]['attachment_id'], 'url'=>(string)$map[$signature]['url'], 'uploaded'=>0];
    }

    $filename = basename($file_path);
    if ($mime === '') {
      $ft = wp_check_filetype($filename);
      $mime = isset($ft['type']) ? (string)$ft['type'] : 'application/octet-stream';
    }
    if ($mime === '') { $mime = 'application/octet-stream'; }

    $res = $this->bridge->media_upload($target_row, $file_path, $filename, $mime, $signature, $source_url);
    if (!$res->ok) {
      $status = is_int($res->status) ? $res->status : 0;
      if ($status == 404) {
        return new WP_Error('wpnexus_media_bridge_missing', 'Bridge client missing', ['status'=>404]);
      }
      return new WP_Error('wpnexus_media_upload_failed', $res->error ? (string)$res->error : 'Upload failed', ['status'=>$status]);
    }

    $att_id = 0; $url = '';
    if (is_array($res->json)) {
      $att_id = !empty($res->json['attachment_id']) ? (int)$res->json['attachment_id'] : 0;
      $url = !empty($res->json['url']) ? (string)$res->json['url'] : '';
    }
    if ($att_id <= 0 || $url === '') {
      return new WP_Error('wpnexus_media_upload_failed', 'Upload failed to return valid data', ['status'=>is_int($res->status)?(int)$res->status:0]);
    }

    $map[$signature] = ['attachment_id'=>$att_id,'url'=>$url,'source_url'=>$source_url,'source_attachment_id'=>$source_attachment_id];
    return ['attachment_id'=>$att_id,'url'=>$url,'uploaded'=>1];
  }
}

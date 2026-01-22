<?php
namespace WPNexusAI\Engine\Sync;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\Queue\Dispatcher;

if (!defined('ABSPATH')) { exit; }

/**
 * Automatically enqueue sync jobs when a post is created/updated.
 * Lightweight only: enqueue jobs, no remote/media heavy work here.
 */
final class AutoEnqueue {

  public static function init(): void {
    $logger = Logger::instance();
    $logger->info('engine.auto_enqueue.init');

    // Reliable hook (WP 5.6+). Safe on older versions too.
    add_action('wp_after_insert_post', [__CLASS__, 'maybe_enqueue'], 20, 3);

    // Back-compat fallback.
    add_action('save_post', [__CLASS__, 'maybe_enqueue'], 20, 3);
  }

  /**
   * @param int $post_id
   * @param \WP_Post $post
   * @param bool $update
   */
  public static function maybe_enqueue($post_id, $post, $update): void {
    $post_id = (int) $post_id;
    if ($post_id <= 0) { return; }

    // Check if process is already locked before proceeding
    $lock_key = 'wpnexus_ai_auto_enqueue_' . $post_id;
    if (get_transient($lock_key)) {
      Logger::instance()->debug('engine.auto_enqueue.skip.locked', ['post_id' => $post_id]);
      return;
    }

    if (!($post instanceof \WP_Post)) {
      $post = get_post($post_id);
    }
    if (!$post) {
      Logger::instance()->debug('engine.auto_enqueue.skip.no_post', ['post_id' => $post_id]);
      return;
    }

    // Skip revisions and autosaves
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
      Logger::instance()->debug('engine.auto_enqueue.skip.revision_or_autosave', ['post_id' => $post_id]);
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      Logger::instance()->debug('engine.auto_enqueue.skip.doing_autosave', ['post_id' => $post_id]);
      return;
    }

    $post_type = (string) $post->post_type;
    if ($post_type === 'attachment') {
      Logger::instance()->debug('engine.auto_enqueue.skip.attachment', ['post_id' => $post_id]);
      return;
    }

    $obj = get_post_type_object($post_type);
    if (!$obj || empty($obj->public)) {
      Logger::instance()->debug('engine.auto_enqueue.skip.non_public', ['post_id' => $post_id, 'post_type' => $post_type]);
      return;
    }

    $status = (string) $post->post_status;
    $allowed = apply_filters('wpnexus_ai_auto_enqueue_statuses', ['publish','future','private'], $post_id, $post);
    if (!is_array($allowed) || !in_array($status, $allowed, true)) {
      Logger::instance()->debug('engine.auto_enqueue.skip.status', ['post_id' => $post_id, 'status' => $status]);
      return;
    }

    // Set lock ONLY after we are sure this is a valid post update/creation
    set_transient($lock_key, 1, 10);

    $enabled = apply_filters('wpnexus_ai_auto_enqueue_enabled', true, $post_id, $post);
    if (!$enabled) {
      Logger::instance()->debug('engine.auto_enqueue.skip.disabled', ['post_id' => $post_id]);
      return;
    }

    $targets = (new TargetsRepo())->list(500);
    if (empty($targets)) {
      Logger::instance()->debug('engine.auto_enqueue.skip.no_targets', ['post_id' => $post_id]);
      return;
    }

    $dispatcher = new Dispatcher();
    $enqueued = 0;

    Logger::instance()->info('engine.auto_enqueue.start', [
      'post_id' => $post_id,
      'post_type' => $post_type,
      'status' => $status,
      'update' => $update ? 1 : 0,
      'targets' => count($targets),
    ]);

    foreach ($targets as $t) {
      $target_id = isset($t['id']) ? (int) $t['id'] : 0;
      $base_url  = isset($t['base_url']) ? (string) $t['base_url'] : '';
      if ($target_id <= 0 || $base_url === '') { continue; }

      $payload = [
        'source_post_id' => $post_id,
        'target_id'      => $target_id,
        'chain_upsert'   => 1,
        'language_code'  => 'auto',
      ];

      $payload = apply_filters('wpnexus_ai_auto_enqueue_payload', $payload, $post_id, $t);

      $job_id = $dispatcher->enqueue('translate', $payload, null);
      if ($job_id > 0) {
        $enqueued++;
        Logger::instance()->info('engine.auto_enqueue.job', [
          'post_id' => $post_id,
          'target_id' => $target_id,
          'job_id' => $job_id,
        ]);
      }
    }

    Logger::instance()->info('engine.auto_enqueue.done', [
      'post_id' => $post_id,
      'enqueued' => $enqueued,
    ]);
  }
}

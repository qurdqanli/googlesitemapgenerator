<?php
namespace WPNexusAI\Rules;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Queue\Dispatcher;

if (!defined('ABSPATH')) { exit; }

final class TriggerManager {

    /** @var Logger */
    private $logger;

    /** @var RulesRepo */
    private $rules;

    /** @var Dispatcher */
    private $dispatcher;

    public function __construct() {
        $this->logger = Logger::instance();
        $this->rules = new RulesRepo();
        $this->dispatcher = new Dispatcher();
    }

    public function register(): void {
        // transition_post_status is more reliable than save_post for status-based triggers.
        add_action('transition_post_status', [$this, 'on_transition'], 10, 3);
        // Also catch updates where status doesn't change (publish -> publish), via post_updated.
        add_action('post_updated', [$this, 'on_updated'], 10, 3);
    }

    public function on_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type === 'revision' || wp_is_post_revision($post->ID)) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }

        $event = ($old_status === $new_status) ? ($new_status . '_update') : $new_status;

        $this->evaluate($post, $event, $old_status, $new_status);
    }

    public function on_updated(int $post_ID, \WP_Post $post_after, \WP_Post $post_before): void {
        if ($post_after->post_type === 'revision' || wp_is_post_revision($post_after->ID)) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }

        if ($post_after->post_status === 'publish') {
            $this->evaluate($post_after, 'publish_update', $post_before->post_status, $post_after->post_status);
        }
    }

    private function evaluate(\WP_Post $post, string $event, string $old_status, string $new_status): void {
        $rules = $this->rules->all(true);
        if (!$rules) { return; }

        foreach ($rules as $rule) {
            if (!$this->matches($rule, $post, $event)) { continue; }

            $target_ids = $this->rules->target_ids($rule);
            if (!$target_ids) { continue; }

            foreach ($target_ids as $target_id) {
                $job_id = $this->dispatcher->enqueue('upsert', [
                    'post_id' => (int) $post->ID,
                    'rule_id' => (int) $rule['id'],
                    'target_id' => (int) $target_id,
                    'event' => $event,
                ], 10);

                $this->logger->info('autopilot.job.created', [
                    'job_id' => $job_id,
                    'post_id' => (int) $post->ID,
                    'rule_id' => (int) $rule['id'],
                    'target_id' => (int) $target_id,
                    'event' => $event,
                ]);
            }
        }
    }

    private function matches(array $rule, \WP_Post $post, string $event): bool {
        // Post type filter
        $types_csv = (string) ($rule['source_post_types'] ?? 'post');
        $types = array_filter(array_map('trim', explode(',', $types_csv)));
        if ($types && !in_array($post->post_type, $types, true)) {
            return false;
        }

        // Trigger status/event filter
        $tr_csv = (string) ($rule['trigger_statuses'] ?? 'publish');
        $allowed = array_filter(array_map('trim', explode(',', $tr_csv)));
        if ($allowed && !in_array($event, $allowed, true) && !in_array($post->post_status, $allowed, true)) {
            return false;
        }

        // Author filter
        $author_ids = $this->rules->source_author_ids($rule);
        if ($author_ids && !in_array((int) $post->post_author, $author_ids, true)) {
            return false;
        }

        // Taxonomy + term filter
        $tax = (string) ($rule['source_taxonomy'] ?? '');
        $term_ids = $this->rules->source_term_ids($rule);
        if ($tax && $term_ids) {
            $post_terms = wp_get_post_terms($post->ID, $tax, ['fields' => 'ids']);
            if (is_wp_error($post_terms)) { return false; }
            $hit = array_intersect($term_ids, array_map('intval', (array) $post_terms));
            if (!$hit) { return false; }
        }

        return true;
    }
}

<?php
namespace WPNexusAIBridge\API;

use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\TokenManager;
use WPNexusAIBridge\Services\UpsertReceiver;

if (!defined('ABSPATH')) { exit; }

final class Routes {

    /** @var TokenManager */
    private $tokens;

    /** @var Logger */
    private $logger;

    /** @var UpsertReceiver */
    private $receiver;

    public function __construct(TokenManager $tokens) {
        $this->tokens = $tokens;
        $this->logger = Logger::instance();
        $this->receiver = new UpsertReceiver();
    }

    public function register(): void {
        register_rest_route('wpnexus-ai-bridge/v1', '/ping', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'permission'],
            'callback' => [$this, 'ping'],
        ]);

        register_rest_route('wpnexus-ai-bridge/v1', '/upsert', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'permission'],
            'callback' => [$this, 'upsert'],
        ]);
    }

    public function permission(): bool {
        return $this->tokens->authorize_request();
    }

    public function ping(\WP_REST_Request $req) {
        return new \WP_REST_Response([
            'ok' => true,
            'ts' => time(),
        ], 200);
    }

    public function upsert(\WP_REST_Request $req) {
        $payload = $req->get_json_params();
        if (!is_array($payload)) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Invalid JSON'], 400);
        }

        try {
            $res = $this->receiver->handle($payload);
            return new \WP_REST_Response(['ok' => true, 'result' => $res], 200);
        } catch (\Throwable $e) {
            $this->logger->error('upsert.exception', ['msg' => $e->getMessage()]);
            return new \WP_REST_Response(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

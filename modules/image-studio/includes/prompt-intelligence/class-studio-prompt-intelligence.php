<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-image-prompt-orchestrator.php';

/**
 * Studio Prompt Intelligence — delegates image runs to Premium Orchestration Layer.
 */
final class YooY_Studio_Prompt_Intelligence {

    private YooY_Image_Prompt_Orchestrator $orchestrator;

    public function __construct() {
        $this->orchestrator = new YooY_Image_Prompt_Orchestrator();
    }

    /**
     * @param string               $raw_user_request
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function run_for_image(string $raw_user_request, array $params = []): array {
        $out = $this->orchestrator->run($raw_user_request, $params);

        if (defined('YOOY_DEBUG') && YOOY_DEBUG && class_exists('YooY_System_Log')) {
            YooY_System_Log::write('info', 'prompt_intelligence', [
                'raw_user_request' => mb_substr($raw_user_request, 0, 200),
                'intent_domain'    => $out['intent_domain'] ?? '',
                'preset'           => $out['preset'] ?? '',
                'quality_score'    => $out['quality']['score'] ?? 0,
                'qa_score'         => $out['visual_qa']['score'] ?? 0,
                'prompt_version'   => $out['prompt_version'] ?? '',
            ]);
        }

        return $out;
    }
}

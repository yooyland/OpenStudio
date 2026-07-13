<?php
if (!defined('ABSPATH')) exit;

/**
 * AI Assistant orchestrator — wires engines. No new Store / Credits charge.
 */
final class YooY_Assistant_Service {

    /** @var YooY_Assistant_Context_Engine */
    private $context;

    /** @var YooY_Assistant_Recommendation_Engine */
    private $recommendations;

    /** @var YooY_Assistant_Prompt_Composer */
    private $composer;

    /** @var YooY_Assistant_Conversation_Engine */
    private $conversation;

    public function __construct() {
        $this->context         = new YooY_Assistant_Context_Engine();
        $this->recommendations = new YooY_Assistant_Recommendation_Engine();
        $this->composer        = new YooY_Assistant_Prompt_Composer();
        $this->conversation    = new YooY_Assistant_Conversation_Engine(
            $this->composer,
            $this->recommendations
        );
    }

    public function config(): array {
        return [
            'id'          => 'ai-assistant',
            'name'        => 'AI Assistant',
            'role'        => 'creative_partner',
            'version'     => '2.0.0',
            'credits'     => [
                'chat'     => false,
                'recommend'=> false,
                'compose'  => false,
                'studio'   => 'existing_credits',
            ],
            'gallery'     => [
                'save_conversation' => false,
                'save_assets_only'  => true,
            ],
            'studios'     => [
                'image', 'video', 'writing', 'translator', 'music', 'voice', 'avatar',
            ],
            'ux'          => [
                'mode'           => 'conversational_creative_partner',
                'prompt_policy'  => 'ask_first_prompt_secondary',
                'cards'          => 'purpose_first',
                'hero'           => 'large_input_first',
            ],
            'phase'       => [
                'included' => ['conversation', 'recommendation', 'prompt_composer_secondary', 'context', 'ui'],
                'next'     => ['image_analysis', 'video_analysis', 'ocr', 'document', 'website', 'audio', 'youtube'],
            ],
        ];
    }

    /**
     * @param int         $user_id
     * @param string|null $project_id
     * @param string|null $current_studio
     */
    public function context(int $user_id, ?string $project_id = null, ?string $current_studio = null): array {
        return $this->context->build($user_id, $project_id, $current_studio);
    }

    /**
     * @param int         $user_id
     * @param string|null $project_id
     */
    public function recommendations(int $user_id, ?string $project_id = null): array {
        $ctx = $this->context->build($user_id, $project_id, null);
        return [
            'context' => [
                'mode'    => $ctx['mode'],
                'project' => $ctx['project'],
            ],
            'cards'   => $this->recommendations->cards($ctx),
        ];
    }

    /**
     * @param int                  $user_id
     * @param string               $message
     * @param string|null          $project_id
     * @param string|null          $current_studio
     * @param array<int, mixed>    $history
     */
    public function chat(
        int $user_id,
        string $message,
        ?string $project_id = null,
        ?string $current_studio = null,
        array $history = [],
        array $brief = []
    ): array {
        $ctx = $this->context->build($user_id, $project_id, $current_studio);
        $out = $this->conversation->reply($message, $ctx, $history, $brief);
        $out['context'] = [
            'mode'    => $ctx['mode'],
            'project' => $ctx['project'],
        ];
        $out['persisted'] = false;
        return $out;
    }

    /**
     * @param int         $user_id
     * @param string      $prompt
     * @param string|null $studio
     * @param string|null $project_id
     */
    public function compose(int $user_id, string $prompt, ?string $studio = null, ?string $project_id = null): array {
        $ctx = $this->context->build($user_id, $project_id, $studio);
        $out = $this->composer->compose($prompt, $studio, $ctx);
        $out['context'] = [
            'mode'    => $ctx['mode'],
            'project' => $ctx['project'],
        ];
        $out['credits_charged'] = false;
        return $out;
    }
}

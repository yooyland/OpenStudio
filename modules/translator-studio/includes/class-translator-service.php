<?php
if (!defined('ABSPATH')) exit;

/**
 * Orchestrates validation → same-language gate → provider → credits → gallery.
 * Credits deduct only for successful real providers (not mock, not fallback).
 */
final class YooY_Translator_Service {

    /** @var YooY_Translator_API_Router */
    private $router;

    /** @var YooY_Translator_Credits */
    private $credits;

    /** @var YooY_Translator_Gallery */
    private $gallery;

    public function __construct(
        YooY_Translator_API_Router $router,
        YooY_Translator_Credits $credits,
        ?YooY_Translator_Gallery $gallery = null
    ) {
        $this->router  = $router;
        $this->credits = $credits;
        $this->gallery = $gallery ?: new YooY_Translator_Gallery();
    }

    public function languages(): array {
        $out = [];
        foreach (YooY_Translator_Validator::languages() as $code => $label) {
            $out[] = [
                'code'      => $code,
                'label'     => $label,
                'as_source' => true,
                'as_target' => ($code !== 'auto'),
            ];
        }
        return $out;
    }

    public function modes(): array {
        $out = [];
        foreach (YooY_Translator_Validator::modes() as $id => $label) {
            $out[] = ['id' => $id, 'label' => $label];
        }
        return $out;
    }

    public function history(int $user_id, int $limit = 50): array {
        return $this->gallery->list_history($user_id, $limit);
    }

    public function reopen(int $user_id, string $id): ?array {
        return $this->gallery->reopen_payload($user_id, $id);
    }

    /**
     * @return array Translation result + meta for the client.
     * @throws YooY_Translator_Exception|Exception
     */
    public function translate(int $user_id, array $params): array {
        $validated = YooY_Translator_Validator::validate_translate($params);
        $project_id = sanitize_text_field((string) ($params['project_id'] ?? $validated['project_id'] ?? ''));

        // Auto source: detect first, block same language BEFORE any provider / credits.
        $detected = '';
        if ($validated['source_language'] === 'auto') {
            $detected = YooY_Translator_Validator::normalize_language_code(
                YooY_Translator_Validator::detect_language($validated['text'])
            );
            YooY_Translator_Validator::assert_different_languages($detected, $validated['target_language']);
            $validated['resolved_source_language'] = $detected;
        } else {
            $detected = $validated['source_language'];
            $validated['resolved_source_language'] = $detected;
        }

        // Pre-flight credits only when Auto/explicit path intends a real provider.
        $intent = $this->billing_intent($validated['provider']);
        if ($intent === 'openai' && !$this->credits->can_afford($user_id, $validated, 'openai')) {
            $need = $this->credits->estimate($validated, 'openai');
            throw new YooY_Translator_Exception(
                '크레딧이 부족합니다. 필요: ' . $need,
                'insufficient_credits',
                402
            );
        }

        $result = $this->router->translate($validated);
        if (empty($result['success'])) {
            throw new YooY_Translator_Exception('번역에 실패했습니다. 잠시 후 다시 시도해 주세요.', 'translate_failed', 400);
        }

        $provider_detected = isset($result['detected_language'])
            ? YooY_Translator_Validator::normalize_language_code((string) $result['detected_language'])
            : $detected;
        YooY_Translator_Validator::assert_different_languages($provider_detected, $validated['target_language']);

        $translated = isset($result['translated_text']) ? (string) $result['translated_text'] : '';
        if (trim($translated) === '') {
            throw new YooY_Translator_Exception('번역 결과가 비어 있습니다.', 'empty_result', 400);
        }

        $used_provider = isset($result['provider']) ? (string) $result['provider'] : 'mock';
        $fallback_used = !empty($result['fallback_used']);
        $fallback_from = isset($result['fallback_from']) ? (string) $result['fallback_from'] : '';

        // Deduct only after a successful real-provider result (never mock / fallback / failed).
        $debit = $this->credits->deduct($user_id, $validated, $used_provider, $fallback_used);

        $save = $this->gallery->save_translation($user_id, [
            'source_text'       => $validated['text'],
            'translated_text'   => $translated,
            'source_language'   => $validated['source_language'],
            'target_language'   => $validated['target_language'],
            'mode'              => $validated['mode'],
            'provider'          => $used_provider,
            'model'             => isset($result['model']) ? (string) $result['model'] : '',
            'detected_language' => $provider_detected !== '' ? $provider_detected : $detected,
            'character_count'   => isset($result['character_count']) ? (int) $result['character_count'] : $validated['character_count'],
            'credits_used'      => (int) ($debit['deducted'] ?? 0),
            'fallback_used'     => $fallback_used,
            'fallback_from'     => $fallback_from,
            'project_id'        => $project_id,
        ]);

        return [
            'success'           => true,
            'translated_text'   => $translated,
            'source_text'       => $validated['text'],
            'detected_language' => $provider_detected !== '' ? $provider_detected : $detected,
            'source_language'   => $validated['source_language'],
            'target_language'   => $validated['target_language'],
            'mode'              => $validated['mode'],
            'provider'          => $used_provider,
            'model'             => isset($result['model']) ? (string) $result['model'] : '',
            'character_count'   => isset($result['character_count']) ? (int) $result['character_count'] : $validated['character_count'],
            'credit_cost'       => (int) ($debit['cost'] ?? 0),
            'credits'           => $debit,
            'usage'             => isset($result['usage']) && is_array($result['usage']) ? $result['usage'] : [],
            'fallback_used'     => $fallback_used,
            'fallback_from'     => $fallback_from,
            'saved'             => !empty($save['saved']),
            'gallery_item_id'   => $save['gallery_item_id'] ?? null,
            'store_ready'       => $this->gallery->is_ready(),
            'project_id'        => $project_id,
        ];
    }

    /**
     * Which provider we expect to bill before the call.
     * Auto + OpenAI ready → openai; otherwise mock (free).
     */
    private function billing_intent(string $requested): string {
        $requested = sanitize_key($requested);
        if ($requested === 'openai' || $requested === 'openai-translator') {
            return $this->router->openai_ready() ? 'openai' : 'mock';
        }
        if ($requested === '' || $requested === 'auto') {
            return $this->router->openai_ready() ? 'openai' : 'mock';
        }
        return 'mock';
    }
}

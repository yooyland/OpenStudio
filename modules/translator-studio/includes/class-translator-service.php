<?php
if (!defined('ABSPATH')) exit;

/**
 * Orchestrates validation → credits gate → provider → Language Asset save → ledger deduct.
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

    public function toggle_favorite(int $user_id, string $id): array {
        return $this->gallery->toggle_favorite($user_id, $id);
    }

    public function delete_history(int $user_id, string $id): bool {
        return $this->gallery->delete_item($user_id, $id);
    }

    /**
     * @return array{project:?array,item:?array}
     * @throws YooY_Translator_Exception
     */
    public function attach_to_project(int $user_id, string $id, ?string $project_id = null): array {
        return $this->gallery->attach_to_project($user_id, $id, $project_id);
    }

    /**
     * Credit estimate for UI / REST (no side effects).
     */
    public function estimate_credits(int $user_id, array $params): array {
        $text = (string) ($params['text'] ?? '');
        $count = YooY_Translator_Validator::char_count($text);
        $requested = sanitize_key((string) ($params['provider'] ?? 'auto'));
        $provider = $this->credits->resolve_estimate_provider($requested, $this->router->openai_ready());
        return $this->credits->estimate_payload($user_id, [
            'text'            => $text,
            'character_count' => $count,
        ], $provider);
    }

    /**
     * @return array Translation result + meta for the client.
     * @throws YooY_Translator_Exception|Exception
     */
    public function translate(int $user_id, array $params): array {
        $validated = YooY_Translator_Validator::validate_translate($params);

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

        // Pre-flight: if OpenAI path is likely, require balance (Unlimited Admin always OK).
        $pre_provider = $this->credits->resolve_estimate_provider(
            (string) ($validated['provider'] ?? 'auto'),
            $this->router->openai_ready()
        );
        if (!$this->credits->can_afford($user_id, $validated, $pre_provider)) {
            $need = $this->credits->estimate($validated, $pre_provider);
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
        $char_count = isset($result['character_count'])
            ? (int) $result['character_count']
            : $validated['character_count'];

        // Language Asset must succeed BEFORE ledger deduct.
        $save = $this->gallery->save_translation($user_id, [
            'source_text'       => $validated['text'],
            'translated_text'   => $translated,
            'source_type'       => (string) ($validated['source_type'] ?? 'text'),
            'source_language'   => $validated['source_language'],
            'target_language'   => $validated['target_language'],
            'mode'              => $validated['mode'],
            'provider'          => $used_provider,
            'model'             => isset($result['model']) ? (string) $result['model'] : '',
            'detected_language' => $provider_detected !== '' ? $provider_detected : $detected,
            'character_count'   => $char_count,
            'credits_used'      => 0,
            'fallback_used'     => $fallback_used,
            'fallback_from'     => $fallback_from,
            'project_id'        => (string) ($validated['project_id'] ?? ''),
        ]);

        if (empty($save['saved'])) {
            // Save failed → never deduct.
            throw new YooY_Translator_Exception(
                'Language Asset 저장에 실패하여 크레딧을 차감하지 않았습니다.',
                'gallery_save_failed',
                500
            );
        }

        $debit = $this->credits->plan_debit($user_id, $validated, $used_provider, $fallback_used);
        $billable = $this->credits->is_billable_result($used_provider, $fallback_used);

        if ($billable) {
            try {
                $debit = $this->credits->deduct(
                    $user_id,
                    $validated,
                    $used_provider,
                    $fallback_used,
                    [
                        'gallery_item_id' => (string) ($save['gallery_item_id'] ?? ''),
                    ]
                );
            } catch (YooY_Translator_Exception $e) {
                // Concurrent spend: asset already saved; keep Language Asset, do not invent refund.
                if ($e->error_code() === 'insufficient_credits') {
                    $debit['skipped'] = true;
                    $debit['reason'] = 'deduct_failed_after_save';
                    $debit['deducted'] = 0;
                } else {
                    // Unexpected billing failure after save → refund N/A; surface error but asset remains.
                    throw $e;
                }
            }

            $deducted = (int) ($debit['deducted'] ?? 0);
            if ($deducted > 0 && !empty($save['gallery_item_id'])) {
                $this->gallery->stamp_credits_used($user_id, (string) $save['gallery_item_id'], $deducted);
            }
        }

        return [
            'success'           => true,
            'translated_text'   => $translated,
            'source_text'       => $validated['text'],
            'detected_language' => $provider_detected !== '' ? $provider_detected : $detected,
            'source_language'   => $validated['source_language'],
            'target_language'   => $validated['target_language'],
            'source_type'       => (string) ($validated['source_type'] ?? 'text'),
            'mode'              => $validated['mode'],
            'provider'          => $used_provider,
            'model'             => isset($result['model']) ? (string) $result['model'] : '',
            'character_count'   => $char_count,
            'credit_cost'       => (int) ($debit['cost'] ?? 0),
            'credits'           => $debit,
            'usage'             => isset($result['usage']) && is_array($result['usage']) ? $result['usage'] : [],
            'fallback_used'     => $fallback_used,
            'fallback_from'     => $fallback_from,
            'saved'             => true,
            'gallery_item_id'   => $save['gallery_item_id'] ?? null,
            'project_id'        => $save['project_id'] ?? ((string) ($validated['project_id'] ?? '')),
            'store_ready'       => $this->gallery->is_ready(),
        ];
    }
}

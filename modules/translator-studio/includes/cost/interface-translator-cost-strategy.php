<?php
if (!defined('ABSPATH')) exit;

/**
 * Provider-specific credit cost strategy (DESIGN ONLY — not wired).
 *
 * Current Translator pricing stays in YooY_Translator_Credits::estimate()
 * (max(1, ceil(chars/500)) for OpenAI). Future: OpenAICostStrategy,
 * GoogleCostStrategy, DeepLCostStrategy, etc.
 *
 * Do not require this file from the Translator module boot until strategies ship.
 */
interface YooY_Translator_Cost_Strategy {

    /** Provider id this strategy applies to (e.g. openai, google, deepl). */
    public function provider_id(): string;

    /**
     * @param array $params text, character_count, mode, model, …
     */
    public function estimate(array $params): int;
}

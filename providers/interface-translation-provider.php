<?php
if (!defined('ABSPATH')) exit;

/**
 * Translation provider capability (Translator Studio).
 * Separate from image/video/voice interfaces — do not mix with those.
 */
interface YooY_Translation_Provider_Interface {

    public function id(): string;

    public function name(): string;

    public function models(): array;

    /**
     * @param array $request {
     *   @type string $text
     *   @type string $source_language
     *   @type string $target_language
     *   @type string $mode
     *   @type string $context
     *   @type array  $glossary
     * }
     * @return array {
     *   @type bool   $success
     *   @type string $translated_text
     *   @type string $detected_language
     *   @type string $provider
     *   @type string $model
     *   @type int    $character_count
     *   @type int    $credit_cost
     *   @type array  $usage
     *   @type array  $raw_response
     * }
     */
    public function translate(array $request): array;
}

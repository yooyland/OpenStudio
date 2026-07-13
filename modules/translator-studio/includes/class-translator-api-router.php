<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes translation requests: Auto → OpenAI (if ready) → Mock fallback on infra failure.
 */
final class YooY_Translator_API_Router {

    /** @var array<string, YooY_Translation_Provider_Interface> */
    private $providers = [];

    public function __construct() {
        $this->boot_providers();
    }

    public function providers(): array {
        if (class_exists('YooY_Provider_Catalog')) {
            return YooY_Provider_Catalog::list_for_studio('translation', $this->providers);
        }
        $list = [];
        foreach ($this->providers as $key => $p) {
            // Skip catalog aliases in flat listing when Catalog unavailable.
            if ($key !== $p->id()) {
                continue;
            }
            $list[] = [
                'id'     => $p->id(),
                'name'   => $p->name(),
                'models' => $p->models(),
            ];
        }
        return $list;
    }

    public function openai_ready(): bool {
        $p = $this->resolve('openai');
        if (!$p) {
            return false;
        }
        if (method_exists($p, 'is_configured') && !$p->is_configured()) {
            return false;
        }
        if (class_exists('YooY_Secrets') && !YooY_Secrets::has_api_key('yoy_openai_api_key')) {
            return false;
        }
        if (class_exists('YooY_Provider_Resolver')) {
            $eval = YooY_Provider_Resolver::evaluate('openai-translator', 'translation');
            // If catalog row missing evaluation usable flag, key presence is enough.
            if (isset($eval['error_code']) && $eval['error_code'] === 'provider_disabled') {
                return false;
            }
            if (isset($eval['billing_blocked']) && $eval['billing_blocked']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @throws YooY_Translator_Exception|Exception
     */
    public function translate(array $request): array {
        $requested = isset($request['provider']) ? (string) $request['provider'] : 'auto';
        if ($requested === '' || $requested === 'auto') {
            $requested = $this->openai_ready() ? 'openai' : 'mock';
        }

        $route = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::route_id($requested)
            : $requested;

        $provider = $this->resolve($route);
        if (!$provider) {
            throw new YooY_Translator_Exception('Translation provider not available.', 'provider_unavailable', 500);
        }

        // Prefer OpenAI when explicitly asked but not ready → mock with fallback meta.
        if (($route === 'openai' || $requested === 'openai-translator') && !$this->openai_ready()) {
            return $this->run_mock_fallback($request, 'openai', 'openai_key_missing');
        }

        try {
            $result = $provider->translate($request);
            if (empty($result['success']) || trim((string) ($result['translated_text'] ?? '')) === '') {
                if ($provider->id() === 'openai') {
                    return $this->run_mock_fallback($request, 'openai', 'openai_empty_result');
                }
                throw new YooY_Translator_Exception('번역에 실패했습니다.', 'translate_failed', 400);
            }
            $result['fallback_used'] = false;
            $result['fallback_from'] = '';
            return apply_filters('yoy_translator_studio_translate', $result, $request);
        } catch (YooY_Translator_Exception $e) {
            // Validation / domain errors — never fallback.
            throw $e;
        } catch (YooY_Translator_Provider_Exception $e) {
            $this->log_provider_failure('openai', $e);
            if ($e->is_fallbackable() && $provider->id() === 'openai') {
                return $this->run_mock_fallback($request, 'openai', $e->internal_code());
            }
            throw new YooY_Translator_Exception('번역 공급자에 연결할 수 없습니다.', 'provider_error', 502);
        } catch (Exception $e) {
            if ($provider->id() === 'openai') {
                $this->log_generic_failure('openai', $e);
                return $this->run_mock_fallback($request, 'openai', 'openai_exception');
            }
            throw $e;
        }
    }

    private function run_mock_fallback(array $request, string $from, string $reason): array {
        $mock = $this->resolve('mock');
        if (!$mock) {
            throw new YooY_Translator_Exception('Mock translator unavailable.', 'mock_unavailable', 500);
        }
        $result = $mock->translate($request);
        if (empty($result['success']) || trim((string) ($result['translated_text'] ?? '')) === '') {
            throw new YooY_Translator_Exception('번역에 실패했습니다.', 'translate_failed', 400);
        }
        $result['provider'] = 'mock';
        $result['fallback_used'] = true;
        $result['fallback_from'] = $from;
        $result['fallback_reason'] = $reason;
        if (empty($result['model'])) {
            $result['model'] = 'mock-translator-v1';
        }
        return apply_filters('yoy_translator_studio_translate', $result, $request);
    }

    private function resolve(string $id): ?YooY_Translation_Provider_Interface {
        if (isset($this->providers[$id])) {
            return $this->providers[$id];
        }
        $route = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::route_id($id)
            : $id;
        if (isset($this->providers[$route])) {
            return $this->providers[$route];
        }
        return isset($this->providers['mock']) ? $this->providers['mock'] : null;
    }

    private function boot_providers(): void {
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-bootstrap.php';
        if (class_exists('YooY_Provider_Bootstrap')) {
            YooY_Provider_Bootstrap::register_translation($this->providers);
        }
        do_action_ref_array('yoy_translator_register_providers', [&$this->providers]);
    }

    private function log_provider_failure(string $provider, YooY_Translator_Provider_Exception $e): void {
        if (!class_exists('YooY_System_Log')) {
            return;
        }
        YooY_System_Log::write('warning', 'Translator provider failure — falling back to mock', [
            'provider'      => $provider,
            'http_status'   => $e->http_status(),
            'internal_code' => $e->internal_code(),
            'request_id'    => $e->request_id(),
            'timestamp'     => gmdate('c'),
            // Never log API key or full source text.
        ]);
    }

    private function log_generic_failure(string $provider, Exception $e): void {
        if (!class_exists('YooY_System_Log')) {
            return;
        }
        YooY_System_Log::write('warning', 'Translator provider exception — falling back to mock', [
            'provider'      => $provider,
            'http_status'   => 0,
            'internal_code' => 'exception',
            'request_id'    => '',
            'timestamp'     => gmdate('c'),
        ]);
    }
}

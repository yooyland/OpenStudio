<?php
if (!defined('ABSPATH')) exit;

/**
 * Structured generation / validation failure for REST diagnostics.
 */
final class YooY_Generation_Exception extends Exception {

    /** @var string */
    private $stage;

    /** @var string */
    private $error_code;

    /** @var array */
    private $context;

    public function __construct(string $stage, string $code, string $message, array $context = []) {
        parent::__construct($message);
        $this->stage      = $stage;
        $this->error_code = $code;
        $this->context    = $context;
    }

    public function stage(): string {
        return $this->stage;
    }

    public function error_code(): string {
        return $this->error_code;
    }

    public function context(): array {
        return $this->context;
    }

    public function to_detail(): array {
        if (!class_exists('YooY_Rest_Error')) {
            return [
                'success' => false,
                'stage'   => $this->stage,
                'code'    => $this->error_code,
                'message' => $this->getMessage(),
            ];
        }
        return YooY_Rest_Error::format(array_merge([
            'stage'   => $this->stage,
            'code'    => $this->error_code,
            'message' => $this->getMessage(),
        ], $this->context));
    }
}

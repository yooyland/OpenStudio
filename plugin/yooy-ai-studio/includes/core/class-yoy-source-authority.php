<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal Source Authority policy constants for a future Source Authority Layer.
 *
 * NOT a user-facing feature. Do not register admin menus, REST settings, or banners
 * for this class. See docs/SOURCE_AUTHORITY.md.
 *
 * Pipelines (Korean Context Engine, Writing, Translator, Research, Chat) may read
 * these constants when grounding Korea-related facts. Ordinary UI must not surface
 * this policy as a product setting.
 */
final class YooY_Source_Authority {

    /** Canonical internal policy document (repo-relative). */
    const POLICY_DOC = 'docs/SOURCE_AUTHORITY.md';

    /** Highest-priority host for presidency-related official materials. */
    const PRESIDENT_OFFICIAL_BASE = 'https://www.president.go.kr/';

    /**
     * Domains that must prefer the presidential official site first.
     *
     * @return string[]
     */
    public static function presidency_topics(): array {
        return [
            'president',
            'presidential_office',
            'presidential_speech',
            'presidential_briefing',
            'presidential_schedule',
            'national_vision',
            'national_agenda',
        ];
    }

    /**
     * Logical domain → preferred authority hint (institution key, not a crawl list).
     * Concrete URLs are resolved at fetch time against the live official host.
     *
     * @return array<string, string>
     */
    public static function domain_authority_hints(): array {
        return [
            'presidency' => 'president.go.kr',
            'law'        => 'official_statute_or_ministry',
            'statistics' => 'kostat_or_issuing_ministry',
            'judgments'  => 'judiciary_official',
            'elections'  => 'nec.go.kr',
            'finance'    => 'fsc_bok_or_supervisor',
        ];
    }

    /**
     * Whether a pipeline may surface a citation to end users.
     * Citations must use existing citation UI only — never a new policy banner.
     */
    public static function allow_user_facing_citation(): bool {
        return true;
    }

    /**
     * Bulk crawl / mirror of official sites is forbidden.
     */
    public static function allow_bulk_site_mirror(): bool {
        return false;
    }

    /**
     * This policy must not be exposed as a dedicated product setting.
     */
    public static function expose_as_user_setting(): bool {
        return false;
    }
}

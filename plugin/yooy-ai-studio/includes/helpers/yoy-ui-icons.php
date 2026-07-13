<?php
if (!defined('ABSPATH')) exit;

final class YooY_UI_Icons {

    public static function svg(string $name, int $size = 20): string {
        $paths = self::paths();
        if (!isset($paths[$name])) {
            return '';
        }
        return '<svg class="yai-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
    }

    private static function paths(): array {
        return [
            'home'       => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
            'projects'   => '<path d="M3 7h18v12H3z"/><path d="M8 7V5h8v2"/>',
            'video'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 6 3-6 3z"/>',
            'image'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-4 4-2-2-5 5"/>',
            'music'      => '<path d="M9 18V5l10-2v13"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="16" r="2"/>',
            'voice'      => '<path d="M12 3a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3z"/><path d="M19 11a7 7 0 0 1-14 0"/><path d="M12 18v3"/>',
            'avatar'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 6-6 8-6s6.5 2 8 6"/>',
            'writing'    => '<path d="M4 6h16M4 12h10M4 18h7"/>',
            'translate'  => '<path d="M5 8h14"/><path d="M12 4v4"/><path d="m4.5 16 4-8 4 8"/><path d="M6.2 13h4.6"/><path d="M14 13h6"/><path d="M17 13c0 3-1.5 5.5-3.5 7"/><path d="M20 13c0 3-1.5 5.5-3.5 7"/>',
            'prompts'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
            'gallery'    => '<rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'community'  => '<circle cx="9" cy="8" r="3"/><circle cx="16" cy="11" r="2.5"/><path d="M3 19c0-3 3-5 6-5s6 2 6 5"/>',
            'market'     => '<path d="M3 9 5 5h14l2 4"/><path d="M5 9v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"/><path d="M9 13h6"/>',
            'credits'    => '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M9 10h4a2 2 0 1 1 0 4H9"/>',
            'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'admin'      => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'bell'       => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'help'       => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4"/><path d="M12 17h.01"/>',
            'spark'      => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>',
            'heart'      => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
            'folder'     => '<path d="M3 7h6l2 3h10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'zap'        => '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z"/>',
            'chart'      => '<path d="M3 3v18h18"/><path d="M7 16V9M12 16V5M17 16v-4"/>',
            'plus'       => '<path d="M12 5v14M5 12h14"/>',
            'upload'     => '<path d="M12 16V4M8 8l4-4 4 4"/><path d="M4 18v2h16v-2"/>',
            'arrow-r'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        ];
    }
}

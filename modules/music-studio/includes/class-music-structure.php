<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Structure {

    public function templates(): array {
        return [
            ['id' => 'pop_standard', 'label' => 'Pop Standard', 'sections' => ['intro', 'verse', 'chorus', 'verse', 'chorus', 'bridge', 'chorus', 'outro']],
            ['id' => 'kpop_hook', 'label' => 'K-Pop Hook', 'sections' => ['intro', 'verse', 'pre-chorus', 'chorus', 'verse', 'chorus', 'bridge', 'chorus']],
            ['id' => 'ballad', 'label' => '발라드', 'sections' => ['intro', 'verse', 'verse', 'chorus', 'verse', 'chorus', 'bridge', 'chorus', 'outro']],
            ['id' => 'edm_drop', 'label' => 'EDM Drop', 'sections' => ['intro', 'build', 'drop', 'break', 'build', 'drop', 'outro']],
            ['id' => 'ost', 'label' => '드라마 OST', 'sections' => ['intro', 'verse', 'chorus', 'verse', 'chorus', 'instrumental', 'chorus']],
            ['id' => 'shorts', 'label' => '쇼츠/릴스 30초', 'sections' => ['hook', 'verse', 'chorus']],
        ];
    }

    public function apply_template(string $id): array {
        foreach ($this->templates() as $tpl) {
            if ($tpl['id'] === $id) return $tpl;
        }
        throw new Exception('Structure template not found.');
    }

    public function build_lyrics_skeleton(string $template_id, string $language = 'ko'): string {
        $tpl = $this->apply_template($template_id);
        $lines = [];
        foreach ($tpl['sections'] as $section) {
            $tag = '[' . ucfirst($section) . ']';
            $lines[] = $tag;
            $lines[] = $this->placeholder_line($section, $language);
            $lines[] = '';
        }
        return trim(implode("\n", $lines));
    }

    public function parse_lyrics(string $lyrics): array {
        $sections = [];
        $current  = null;
        $lines    = [];

        foreach (preg_split('/\r\n|\r|\n/', $lyrics) as $line) {
            if (preg_match('/^\[([^\]]+)\]/', trim($line), $m)) {
                if ($current) {
                    $sections[] = ['tag' => $current, 'lines' => $lines];
                }
                $current = strtolower($m[1]);
                $lines   = [];
            } elseif (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }
        if ($current) {
            $sections[] = ['tag' => $current, 'lines' => $lines];
        }
        return $sections;
    }

    private function placeholder_line(string $section, string $language): string {
        if ($language === 'ko') {
            switch ($section) {
                case 'chorus':
                    return '(후렴 가사를 입력하세요)';
                case 'verse':
                    return '(절 가사를 입력하세요)';
                case 'bridge':
                    return '(브릿지 가사를 입력하세요)';
                case 'intro':
                case 'outro':
                case 'instrumental':
                    return '(연주 / 간주)';
                default:
                    return '(가사를 입력하세요)';
            }
        }
        return '(' . $section . ' lyrics here)';
    }
}

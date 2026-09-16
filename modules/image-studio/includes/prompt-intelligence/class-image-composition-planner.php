<?php
if (!defined('ABSPATH')) exit;

/**
 * Composition Planner — premium framing / depth / lighting brief for short or vague prompts.
 * Internal only; never shown as user-facing copy.
 */
final class YooY_Image_Composition_Planner {

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $scene
     * @param array<string, mixed> $normalized
     * @return array{
     *   primary_subject:string,
     *   supporting:string[],
     *   framing:string,
     *   camera:string,
     *   depth:string,
     *   lighting:string,
     *   mood:string,
     *   material_emphasis:string,
     *   richness:string,
     *   realism_balance:string,
     *   composition_lines:string[],
     *   anti_cheap_lines:string[]
     * }
     */
    public static function plan(array $brief, array $scene = [], array $normalized = []): array {
        $domain = sanitize_key((string) ($brief['content_domain'] ?? 'general'));
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $normalized['normalized'] ?? ''));
        $subject = trim((string) ($scene['subject'] ?? $brief['primary_subject'] ?? ''));
        if ($subject === '') {
            $subject = mb_substr(trim((string) ($normalized['normalized'] ?? $raw)), 0, 80);
        }

        $plan = [
            'primary_subject'   => $subject,
            'supporting'        => [],
            'framing'           => 'intentional rule-of-thirds or dynamic diagonal hierarchy — not dead-center toy layout',
            'camera'            => 'cinematic camera language with clear focal plane',
            'depth'             => 'layered foreground / midground / background with atmospheric perspective',
            'lighting'          => 'tasteful directional lighting with soft fill and elegant highlights',
            'mood'              => 'refined contemporary premium mood',
            'material_emphasis' => 'believable materials and micro-detail where relevant',
            'richness'          => 'scene-rich but uncluttered — every element earns its place',
            'realism_balance'   => 'commercially usable finish',
            'composition_lines' => [],
            'anti_cheap_lines'  => self::anti_cheap_defaults($domain, $raw),
        ];

        switch ($domain) {
            case 'storybook':
            case 'fantasy':
            case 'illustration':
                $plan = self::plan_storybook($plan, $raw);
                break;
            case 'beauty':
            case 'beauty_model_campaign':
            case 'beauty_poster_editorial':
            case 'fashion':
                $plan = self::plan_beauty($plan, $raw, $domain);
                break;
            case 'beauty_product_packshot':
            case 'product':
            case 'ecommerce':
                $plan = self::plan_product($plan, $raw);
                break;
            case 'architecture':
                $plan = self::plan_architecture($plan, $raw);
                break;
            case 'portrait':
            case 'editorial':
                $plan = self::plan_portrait($plan, $raw);
                break;
            case 'lifestyle':
            case 'cinematic':
                $plan = self::plan_lifestyle($plan, $raw);
                break;
            default:
                $plan = self::plan_general($plan, $raw, $domain);
                break;
        }

        $plan['composition_lines'] = array_values(array_filter([
            'COMPOSITION: primary subject = ' . $plan['primary_subject'],
            'FRAMING: ' . $plan['framing'],
            'CAMERA: ' . $plan['camera'],
            'DEPTH: ' . $plan['depth'],
            'LIGHTING: ' . $plan['lighting'],
            'MOOD: ' . $plan['mood'],
            'DETAIL: ' . $plan['material_emphasis'],
            'RICHNESS: ' . $plan['richness'],
            'FINISH: ' . $plan['realism_balance'],
        ]));

        return $plan;
    }

    /**
     * Inject composition + anti-cheap lines into composed prompt (once).
     *
     * @param array<string, mixed> $plan
     */
    public static function inject_into_prompt(string $prompt, array $plan): string {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return $prompt;
        }
        if (stripos($prompt, 'COMPOSITION:') !== false) {
            return $prompt;
        }
        $block = implode('. ', array_slice($plan['composition_lines'] ?? [], 0, 6));
        $anti = implode('; ', array_slice($plan['anti_cheap_lines'] ?? [], 0, 5));
        if ($anti !== '' && stripos($prompt, 'ANTI-CHEAP:') === false) {
            $block .= '. ANTI-CHEAP: ' . $anti;
        }
        if ($block === '') {
            return $prompt;
        }
        return rtrim($prompt, '. ') . '. ' . $block;
    }

    /** @return string[] */
    private static function anti_cheap_defaults(string $domain, string $raw): array {
        $base = [
            'avoid tacky cartoonish oversimplification unless explicitly requested',
            'avoid clumsy symmetry and awkward empty backgrounds',
            'avoid muddy colors, plastic skin, generic stock poses',
            'avoid amateur poster look and low-detail landmark fillers',
            'avoid unintentional readable text or invented logos',
        ];
        if (preg_match('/촌스럽|키치|kitsch|싸구려|저렴|저가|cheap|childish|유치/u', $raw)) {
            // User asked for that vibe — soften anti-cheap.
            return ['preserve user-requested style tone', 'still keep composition intentional'];
        }
        if (in_array($domain, ['storybook', 'fantasy', 'illustration'], true)) {
            $base[] = 'avoid flat kiddie mural / bedroom-window observer cliché';
            $base[] = 'avoid dim basic storybook cliché and sparse blank sky';
        }
        if (in_array($domain, ['beauty', 'product', 'ecommerce'], true)) {
            $base[] = 'avoid pharmacy-shelf snapshot and glitter overload';
        }
        if ($domain === 'architecture') {
            $base[] = 'avoid warped towers and cartoon real-estate flyers';
        }
        if (in_array($domain, ['portrait', 'lifestyle', 'editorial'], true)) {
            $base[] = 'avoid catalogue mannequin faces and outdated fashion stock pose';
        }
        return $base;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_storybook(array $plan, string $raw): array {
        $plan['framing'] = 'dynamic sky-travel or adventure composition with strong focal hierarchy';
        $plan['camera'] = 'slightly wide cinematic angle emphasizing scale and wonder';
        $plan['depth'] = 'layered landmarks and atmospheric depth — not flat cutouts on empty sky';
        $plan['lighting'] = 'luminous but tasteful fantasy lighting; elegant color harmony';
        $plan['mood'] = 'warm imaginative premium picture-book cover emotion';
        $plan['material_emphasis'] = 'rich fabric/feather/water/cloud micro-detail without clutter';
        $plan['richness'] = 'scene-rich wonder: supporting landmarks earn scale and journey';
        $plan['realism_balance'] = 'premium illustrated realism — refined, never naive clipart';
        if (preg_match('/펭귄|고래|하늘|여행/u', $raw)) {
            $plan['supporting'] = ['journey path', 'atmospheric sky layers', 'distant landmarks for scale'];
            $plan['framing'] = 'grand outdoor sky adventure — primary subjects large and readable, background layered for depth';
        }
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_beauty(array $plan, string $raw, string $domain = 'beauty_model_campaign'): array {
        $is_poster = ($domain === 'beauty_poster_editorial') || (bool) preg_match('/포스터|poster/u', $raw);
        $is_packshot = ($domain === 'beauty_product_packshot')
            || (bool) preg_match('/제품만|누끼|상세페이지|packshot|product\s*only/u', $raw);

        if ($is_packshot) {
            $plan['framing'] = 'elevated beauty product hero — deliberate commercial still-life angle';
            $plan['camera'] = 'studio beauty still camera, controlled depth of field';
            $plan['depth'] = 'soft background falloff; packaging remains razor-clear';
            $plan['lighting'] = 'elegant soft key + gentle rim; quiet luxury speculars';
            $plan['mood'] = 'clean premium Korean/global beauty product mood — refined, radiant, calm';
            $plan['material_emphasis'] = 'glass, serum viscosity, packaging edges';
            $plan['richness'] = 'minimal but expensive — one hero, refined props only';
            $plan['realism_balance'] = 'ad-grade beauty product still';
            $plan['anti_cheap_lines'] = array_merge($plan['anti_cheap_lines'], [
                'no pharmacy bottle aesthetic',
                'no cheap e-commerce snapshot',
                'no flat dead-center catalog look',
            ]);
            return $plan;
        }

        $plan['framing'] = $is_poster
            ? 'premium vertical beauty advertising poster — model + product hierarchy with headline negative space'
            : 'model-led luxury beauty campaign framing; product legible in hand or near model';
        $plan['camera'] = '85mm-equivalent beauty campaign camera, soft subject separation';
        $plan['depth'] = 'layered campaign set; face and product both readable';
        $plan['lighting'] = 'soft flattering beauty key + gentle fill; luminous skin; quiet luxury product highlights';
        $plan['mood'] = 'refined elegant radiant premium calm clean confident — never anger or hostile intensity';
        $plan['material_emphasis'] = 'luminous skin micro-texture, glass/cream packaging, contemporary wardrobe';
        $plan['richness'] = 'campaign-ready polish with usable copy space; no cluttered props';
        $plan['realism_balance'] = 'premium K-beauty advertising campaign / editorial poster';
        $plan['supporting'] = ['skincare product hero', 'clean luxurious set', 'copy-ready negative space'];
        $plan['anti_cheap_lines'] = array_merge($plan['anti_cheap_lines'], [
            'no product-only empty tabletop unless requested',
            'no pharmacy bottle aesthetic',
            'no cheap home-shopping mood',
            'no plastic skin or mannequin face',
            'no generic stock-cosmetic look',
            'no kitschy or tacky styling',
            'no flat dead-center catalog look',
        ]);
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_product(array $plan, string $raw): array {
        $plan['framing'] = 'elevated product hero — deliberate angle, not phone snapshot';
        $plan['camera'] = 'commercial still-life camera with crisp focus plane';
        $plan['depth'] = 'premium surface + subtle environmental reflection';
        $plan['lighting'] = 'controlled studio lighting; material-true highlights';
        $plan['mood'] = 'quiet luxury commercial clarity';
        $plan['material_emphasis'] = 'accurate geometry, glass/metal/plastic truth';
        $plan['richness'] = 'hero clarity first; background supports brand tone';
        $plan['realism_balance'] = 'commercial-grade product photography';
        unset($raw);
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_architecture(array $plan, string $raw): array {
        $plan['framing'] = 'brochure-worthy archviz framing with straight verticals';
        $plan['camera'] = 'slightly elevated wide or classic aerial for masterplan when asked';
        $plan['depth'] = 'believable landscaping layers, sky, and material perspective';
        $plan['lighting'] = 'premium golden-hour or clear daylight with soft shadows';
        $plan['mood'] = 'high-end real-estate campaign aspiration';
        $plan['material_emphasis'] = 'façade glass, stone, balconies, planting, road edges';
        $plan['richness'] = 'sales-worthy context without cluttered chaos';
        $plan['realism_balance'] = 'photoreal architectural visualization';
        if (preg_match('/조감|aerial|master.?plan/u', $raw)) {
            $plan['camera'] = 'premium aerial masterplan angle — coherent campus layout';
        }
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_portrait(array $plan, string $raw): array {
        $plan['framing'] = 'editorial portrait hierarchy — face/eyes lead, environment supports';
        $plan['camera'] = '85mm-equivalent portrait feel, shallow but controlled DOF';
        $plan['depth'] = 'soft environmental bokeh; subject separation';
        $plan['lighting'] = 'natural-looking premium key light; believable skin speculars';
        $plan['mood'] = 'contemporary editorial confidence';
        $plan['material_emphasis'] = 'fabric texture, hair strands, natural skin detail';
        $plan['richness'] = 'stylish but believable — no stock catalogue stiffness';
        $plan['realism_balance'] = 'premium lifestyle / fashion editorial photography';
        unset($raw);
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_lifestyle(array $plan, string $raw): array {
        $plan['framing'] = 'cinematic lifestyle moment — relationship and place readable';
        $plan['camera'] = 'storytelling mid-shot or environmental portrait';
        $plan['depth'] = 'layered city/nature context with atmospheric haze if needed';
        $plan['lighting'] = 'natural cinematic light; elegant color grade';
        $plan['mood'] = 'warm premium contemporary lifestyle';
        $plan['material_emphasis'] = 'wardrobe, architecture, and skin all believable';
        $plan['richness'] = 'lived-in premium world, not empty cyclorama';
        $plan['realism_balance'] = 'cinematic lifestyle campaign still';
        unset($raw);
        return $plan;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private static function plan_general(array $plan, string $raw, string $domain): array {
        $plan['mood'] = 'refined contemporary premium visual';
        $plan['realism_balance'] = 'commercially usable modern finish';
        if (preg_match('/광고|캠페인|브랜드|social|sns/u', $raw) || $domain === 'brand' || $domain === 'social') {
            $plan['framing'] = 'social-ad premium composition — clear hero, campaign-ready crop';
            $plan['mood'] = 'Korean premium brand visual — modern, restrained gold-adjacent luxury without tackiness';
        }
        return $plan;
    }
}

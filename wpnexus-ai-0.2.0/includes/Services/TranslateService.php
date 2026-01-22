<?php
namespace WPNexusAI\Services;

use WPNexusAI\Providers\ProviderChain;

if (!defined('ABSPATH')) { exit; }

final class TranslateService {

    /** @var ProviderChain */
    private $providers;

    public function __construct() {
        $this->providers = new ProviderChain();
    }

    /**
     * @param string $text
     * @param array<string, mixed> $ctx
     */
    public function translate(string $text, array $ctx): string {
        $text = (string) $text;
        if (trim($text) === '') { return $text; }

        $source_lang = (string) ($ctx['source_lang'] ?? '');
        $target_lang = (string) ($ctx['target_lang'] ?? '');
        if ($source_lang && $target_lang && strtolower($source_lang) === strtolower($target_lang)) {
            return $text;
        }

        $persona = (string) ($ctx['persona'] ?? 'neutral');
        $custom_prompt = (string) ($ctx['custom_prompt'] ?? '');

        $instructions = $this->base_instructions($source_lang, $target_lang, $persona, $custom_prompt);

        $res = $this->providers->translate([
            'input' => $text,
            'instructions' => $instructions,
            'temperature' => 0.4,
            'max_output_tokens' => 4000,
        ]);

        if (!$res['ok']) {
            // Fail open: keep original.
            return $text;
        }

        return (string) $res['text'];
    }

    private function base_instructions(string $source_lang, string $target_lang, string $persona, string $custom_prompt): string {
        $tone = $this->persona_instruction($persona);

        $lang = '';
        if ($source_lang && $target_lang) {
            $lang = "Translate from {$source_lang} to {$target_lang}.";
        } elseif ($target_lang) {
            $lang = "Translate into {$target_lang}.";
        } else {
            $lang = "Translate to the target language.";
        }

        $core = [
            $lang,
            "Rewrite naturally like a human editor. Preserve factual meaning. Keep proper nouns unless translation is standard.",
            "Keep formatting (paragraphs, headings, lists). Keep URLs unchanged.",
            "Do NOT add commentary or disclaimers. Output ONLY the final text.",
            $tone,
        ];

        if (trim($custom_prompt) !== '') {
            $core[] = "Custom instruction from user: " . trim($custom_prompt);
        }

        return implode("\n", array_filter($core));
    }

    private function persona_instruction(string $persona): string {
        $persona = strtolower(trim($persona));
        switch ($persona) {
            case 'formal':
            case 'rəsmi':
                return "Tone: formal, clear, professional.";
            case 'funny':
            case 'zarafatcıl':
                return "Tone: light, witty, friendly (no cringe).";
            case 'sales':
            case 'satış':
            case 'satış yönümlü':
                return "Tone: persuasive, marketing-friendly, but not spammy.";
            case 'story':
            case 'hekayə':
            case 'hekayə tərzi':
                return "Tone: storytelling, vivid, engaging.";
            default:
                return "Tone: neutral, modern, readable.";
        }
    }
}

<?php

namespace App\Translation;

/**
 * Traduit une page HTML déjà rendue, texte par texte : chaque texte français (contenu des balises, attributs
 * alt / title / aria-label / placeholder, description) est remplacé par sa traduction enregistrée en base. C'est ce qui
 * couvre les textes écrits en dur dans les gabarits. Lecture seule : un texte inconnu reste en français.
 */
final class HtmlTranslator
{
    private const BLOCKS = '#(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<!--.*?-->|<[^>]*>)#is';
    private const ATTRIBUTES = '#\b(alt|title|aria-label|placeholder)="([^"]*)"#i';
    private const MAX_LENGTH = 2000;

    /** Morceaux du nom du club (titre découpé par des balises) : jamais traduits. */
    private const NEVER = ['Entente', 'Sportive', 'Coutançaise'];

    public function __construct(private readonly TranslationStore $store)
    {
    }

    public function translate(string $html, string $locale): string
    {
        return $this->walk($html, fn (string $text): ?string => $this->store->get($text, $locale));
    }

    /**
     * Tous les textes français d'une page (à traduire par app:translations:sync).
     *
     * @return list<string>
     */
    public function collect(string $html): array
    {
        $found = [];
        $this->walk($html, static function (string $text) use (&$found): ?string {
            $found[$text] = true;

            return null;
        });

        return array_map('strval', array_keys($found));
    }

    /** @param callable(string): ?string $lookup texte français => texte de remplacement (ou null) */
    private function walk(string $html, callable $lookup): string
    {
        $parts = preg_split(self::BLOCKS, $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return $html;
        }

        foreach ($parts as $i => $part) {
            if (0 === $i % 2) {
                $parts[$i] = $this->text($part, $lookup);
            } elseif (str_starts_with($part, '<') && !preg_match('#^<(script|style|textarea|!--)#i', $part)) {
                $parts[$i] = $this->tag($part, $lookup);
            }
        }

        return implode('', $parts);
    }

    /** @param callable(string): ?string $lookup */
    private function text(string $text, callable $lookup): string
    {
        if (!preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $m) || '' === $m[2]) {
            return $text;
        }
        $translated = $this->lookup($m[2], $lookup);

        return null === $translated ? $text : $m[1].htmlspecialchars($translated, \ENT_NOQUOTES | \ENT_SUBSTITUTE).$m[3];
    }

    /** @param callable(string): ?string $lookup */
    private function tag(string $tag, callable $lookup): string
    {
        $tag = (string) preg_replace_callback(self::ATTRIBUTES, function (array $m) use ($lookup): string {
            $translated = $this->lookup($m[2], $lookup);

            return null === $translated ? $m[0] : $m[1].'="'.htmlspecialchars($translated, \ENT_COMPAT | \ENT_SUBSTITUTE).'"';
        }, $tag);

        if (preg_match('#^<meta\b[^>]*\b(?:name="description"|property="og:(?:title|description)")#i', $tag)) {
            $tag = (string) preg_replace_callback('#\bcontent="([^"]*)"#i', function (array $m) use ($lookup): string {
                $translated = $this->lookup($m[1], $lookup);

                return null === $translated ? $m[0] : 'content="'.htmlspecialchars($translated, \ENT_COMPAT | \ENT_SUBSTITUTE).'"';
            }, $tag);
        }

        return $tag;
    }

    /** @param callable(string): ?string $lookup */
    private function lookup(string $raw, callable $lookup): ?string
    {
        $text = self::normalize(html_entity_decode($raw, \ENT_QUOTES | \ENT_HTML5));
        if ('' === $text || \in_array($text, self::NEVER, true) || \strlen($text) > self::MAX_LENGTH || !preg_match('/\p{L}{2}/u', $text) || str_contains($text, '{{') || str_contains($text, '://') || str_contains($text, '@')) {
            return null;
        }

        return $lookup($text);
    }

    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}

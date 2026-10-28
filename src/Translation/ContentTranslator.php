<?php

namespace App\Translation;

use App\Service\Locale\LocaleRegistry;
use App\Service\Locale\Locales;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Traduit les textes français dans les langues actives et les range dans TranslationStore. Seuls les textes pas
 * encore traduits sont envoyés au service (un texte inchangé n'est jamais retraduit ni refacturé).
 */
final class ContentTranslator
{
    public function __construct(
        private readonly TranslationProvider $provider,
        private readonly TranslationStore $store,
        private readonly LocaleRegistry $locales,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * @param list<string>      $texts   textes français
     * @param list<string>|null $locales langues visées (par défaut : les langues actives hors français)
     *
     * @return int nombre de traductions enregistrées
     */
    public function translateTexts(array $texts, bool $html = false, ?array $locales = null): int
    {
        if (!$this->provider->isConfigured()) {
            return 0;
        }
        $texts    = array_values(array_unique(array_filter(array_map('strval', $texts), static fn (string $t): bool => '' !== trim($t))));
        $locales ??= array_keys(array_diff_key($this->locales->enabled(), [Locales::DEFAULT => true]));

        $saved = 0;
        foreach ($locales as $locale) {
            $missing = array_values(array_filter($texts, fn (string $t): bool => !$this->store->has($t, $locale)));
            if ([] === $missing) {
                continue;
            }
            try {
                $translated = $this->provider->translate(array_map(static fn (string $t): string => $html ? self::protectNames($t) : self::protect($t), $missing), $locale, $html);
            } catch (\Throwable $e) {
                $this->logger->warning('Traduction automatique impossible ('.$locale.') : '.$e->getMessage());
                continue;
            }
            foreach ($missing as $i => $source) {
                $this->store->put($source, $locale, $html ? self::stripProtection($translated[$i]) : self::restore($translated[$i]));
                ++$saved;
            }
        }

        return $saved;
    }

    /**
     * Traduit tous les champs #[Translatable] de ces entités.
     *
     * @param iterable<object>  $entities
     * @param list<string>|null $locales
     */
    public function translateEntities(iterable $entities, ?array $locales = null): int
    {
        $plain = $html = [];
        foreach ($entities as $entity) {
            foreach (TranslatableFields::of($entity) as $field => $isHtml) {
                $value = TranslatableFields::read($entity, $field);
                if (null === $value || '' === trim($value)) {
                    continue;
                }
                if ($isHtml) {
                    $html[] = $value;
                } else {
                    $plain[] = $value;
                }
            }
        }

        return $this->translateTexts($plain, false, $locales) + $this->translateTexts($html, true, $locales);
    }

    /**
     * Traduit tout le contenu déjà saisi (toutes les entités ayant des champs #[Translatable]).
     *
     * @param list<string>|null $locales
     */
    public function translateAllContent(?array $locales = null): int
    {
        $saved = 0;
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || [] === TranslatableFields::ofClass($metadata->getName())) {
                continue;
            }
            $saved += $this->translateEntities($this->em->getRepository($metadata->getName())->findAll(), $locales);
        }

        return $saved;
    }

    /** Noms propres qui ne doivent jamais être traduits. */
    private const NAMES = ['Entente Sportive Coutançaise', 'ES Coutances', 'FFF Normandie', 'Portailclubs', 'HelloAsso', 'Facebook', 'Instagram'];

    /** Entoure les noms propres de balises <x> : DeepL les laisse tels quels (ignore_tags). */
    public static function protectNames(string $text): string
    {
        return (string) preg_replace('/'.implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), self::NAMES)).'/u', '<x>$0</x>', $text);
    }

    public static function stripProtection(string $text): string
    {
        return str_replace(['<x>', '</x>'], '', $text);
    }

    /** Texte simple → pseudo-XML : caractères spéciaux échappés, « %variables% » protégées de la traduction. */
    public static function protect(string $text): string
    {
        return self::protectNames((string) preg_replace('/%[A-Za-z_]+%/', '<x>$0</x>', htmlspecialchars($text, \ENT_XML1 | \ENT_NOQUOTES)));
    }

    public static function restore(string $xml): string
    {
        return html_entity_decode(self::stripProtection($xml), \ENT_XML1 | \ENT_QUOTES);
    }
}

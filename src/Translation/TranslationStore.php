<?php

namespace App\Translation;

use App\Doctrine\TablePrefixListener;
use Doctrine\DBAL\Connection;

/**
 * Traductions déjà calculées, lues et écrites en base. Le site ne lit que ici : aucun appel au service de
 * traduction à l'affichage, donc aucun coût par visiteur.
 */
final class TranslationStore
{
    /** @var array<string, array<string, string>> langue => (empreinte => texte traduit) */
    private array $loaded = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function hash(string $source): string
    {
        return sha1($source);
    }

    /** Texte traduit en $locale, ou null si pas (encore) traduit. */
    public function get(string $source, string $locale): ?string
    {
        return $this->all($locale)[self::hash($source)] ?? null;
    }

    public function has(string $source, string $locale): bool
    {
        return null !== $this->get($source, $locale);
    }

    public function put(string $source, string $locale, string $translated): void
    {
        $table = TablePrefixListener::prefixed('translation');
        $this->connection->executeStatement(
            "INSERT INTO $table (hash, locale, source, translated, created_at) VALUES (:h, :l, :s, :t, :c) ON DUPLICATE KEY UPDATE translated = VALUES(translated)",
            ['h' => self::hash($source), 'l' => $locale, 's' => $source, 't' => $translated, 'c' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
        );
        $this->loaded[$locale][self::hash($source)] = $translated;
    }

    /** @return array<string, string> */
    private function all(string $locale): array
    {
        if (!isset($this->loaded[$locale])) {
            try {
                $table = TablePrefixListener::prefixed('translation');
                $rows  = $this->connection->fetchAllKeyValue("SELECT hash, translated FROM $table WHERE locale = ?", [$locale]);
                $this->loaded[$locale] = array_map('strval', $rows);
            } catch (\Throwable) {
                $this->loaded[$locale] = []; // table absente (migration pas encore passée) : on reste en français
            }
        }

        return $this->loaded[$locale];
    }
}

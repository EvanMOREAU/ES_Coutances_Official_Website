<?php

namespace App\Translation;

use App\Service\Locale\Locales;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** DeepL (clé gratuite « …:fx » → api-free.deepl.com, sinon api.deepl.com). Clé dans DEEPL_API_KEY. */
final class DeepLProvider implements TranslationProvider
{
    private const BATCH = 40;

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(string:DEEPL_API_KEY)%')] private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    public function translate(array $texts, string $locale, bool $html): array
    {
        $target = Locales::AVAILABLE[$locale]['deepl'] ?? null;
        if (!$this->isConfigured() || null === $target) {
            throw new \RuntimeException('DeepL non configuré.');
        }

        $host = str_ends_with(trim($this->apiKey), ':fx') ? 'api-free.deepl.com' : 'api.deepl.com';
        $out  = [];
        foreach (array_chunk($texts, self::BATCH) as $chunk) {
            $response = $this->http->request('POST', 'https://'.$host.'/v2/translate', [
                'headers' => ['Authorization' => 'DeepL-Auth-Key '.trim($this->apiKey)],
                'body'    => [
                    'text'         => $chunk,
                    'source_lang'  => 'FR',
                    'target_lang'  => $target,
                    'tag_handling' => $html ? 'html' : 'xml',
                    'ignore_tags'  => 'x',
                ],
                'timeout' => 20,
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('DeepL a répondu '.$response->getStatusCode().'.');
            }
            foreach ($response->toArray()['translations'] ?? [] as $item) {
                $out[] = (string) ($item['text'] ?? '');
            }
        }
        if (count($out) !== count($texts)) {
            throw new \RuntimeException('Réponse DeepL incomplète.');
        }

        return $out;
    }
}

<?php

namespace App\Translation;

use App\Service\Locale\Locales;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Texte du site absent du fichier de traduction d'une langue : au lieu d'afficher le français, on cherche sa
 * traduction automatique déjà enregistrée en base (commande app:translations:sync). Lecture seule : aucun appel au
 * service de traduction pendant la visite.
 */
#[AsDecorator(decorates: 'translator')]
final class StoredTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    public function __construct(
        #[AutowireDecorated] private readonly TranslatorInterface $inner,
        private readonly TranslationStore $store,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $locale ??= $this->inner->getLocale();
        if (Locales::DEFAULT === $locale || !Locales::isSupported($locale) || !$this->inner instanceof TranslatorBagInterface) {
            return $this->inner->trans($id, $parameters, $domain, $locale);
        }

        $domain ??= 'messages';
        if ($this->inner->getCatalogue($locale)->defines($id, $domain)) {
            return $this->inner->trans($id, $parameters, $domain, $locale);
        }

        // Pas de traduction écrite à la main : on part du texte français (clé « zone.element » ou phrase) …
        $french = $this->inner->trans($id, [], $domain, Locales::DEFAULT);
        if (!str_contains($french, '|')) {
            $stored = $this->store->get($french, $locale);
            if (null !== $stored) {
                return $parameters ? strtr($stored, $parameters) : $stored;
            }
        }

        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    public function setLocale(string $locale): void
    {
        if ($this->inner instanceof LocaleAwareInterface) {
            $this->inner->setLocale($locale);
        }
    }

    public function getCatalogue(?string $locale = null): \Symfony\Component\Translation\MessageCatalogueInterface
    {
        \assert($this->inner instanceof TranslatorBagInterface);

        return $this->inner->getCatalogue($locale);
    }

    /** @return array<\Symfony\Component\Translation\MessageCatalogueInterface> */
    public function getCatalogues(): array
    {
        \assert($this->inner instanceof TranslatorBagInterface);

        return $this->inner->getCatalogues();
    }
}

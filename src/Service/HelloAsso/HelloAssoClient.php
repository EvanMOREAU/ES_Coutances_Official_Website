<?php

namespace App\Service\HelloAsso;

use App\Entity\HelloAssoSettings;
use App\Mail\SecretBox;
use App\Repository\HelloAssoSettingsRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client de l'API HelloAsso (Checkout Intent) : crée une intention de paiement pour un montant
 * donné et vérifie son état au retour. Pas de « formulaire » HelloAsso à créer côté organisation :
 * l'API fonctionne directement sur l'organisation (voir doc admin/configuration → HelloAsso).
 */
class HelloAssoClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly HelloAssoSettingsRepository $settingsRepository,
        private readonly SecretBox $secretBox,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param array{firstName?: string, lastName?: string, email?: string} $payer
     *
     * @return array{id: int, redirectUrl: string}
     *
     * @throws HelloAssoException
     */
    public function creerIntentionPaiement(int $montantCentimes, string $libelle, string $backUrl, string $errorUrl, array $payer = []): array
    {
        $settings = $this->settingsUtilisables();

        // Ne filtrer que le payer (facultatif) : array_filter() sur tout le tableau retirerait
        // aussi containsDonation => false, or ce champ booléen est obligatoire pour HelloAsso.
        $payload = [
            'totalAmount'      => $montantCentimes,
            'initialAmount'    => $montantCentimes,
            'itemName'         => $libelle,
            'backUrl'          => $backUrl,
            'errorUrl'         => $errorUrl,
            'returnUrl'        => $backUrl,
            'containsDonation' => false,
        ];
        $payer = array_filter($payer);
        if ([] !== $payer) {
            $payload['payer'] = $payer;
        }

        $data = $this->request('POST', $settings, sprintf('/v5/organizations/%s/checkout-intents', $settings->getOrganisationSlug()), ['json' => $payload]);

        if (!isset($data['id'], $data['redirectUrl'])) {
            throw new HelloAssoException('Réponse HelloAsso inattendue (intention de paiement).');
        }

        return ['id' => (int) $data['id'], 'redirectUrl' => (string) $data['redirectUrl']];
    }

    /**
     * État d'une intention de paiement créée précédemment : « Authorized » si le paiement est
     * confirmé, « Waiting »/« Processing » s'il est en cours, autre chose sinon (refusé, expiré…).
     *
     * @return array{state: string}
     *
     * @throws HelloAssoException
     */
    public function recupererIntention(int $checkoutIntentId): array
    {
        $settings = $this->settingsUtilisables();

        $data = $this->request('GET', $settings, sprintf('/v5/organizations/%s/checkout-intents/%d', $settings->getOrganisationSlug(), $checkoutIntentId));

        return ['state' => $this->etatPaiement($data)];
    }

    /**
     * La réponse de l'API n'a pas de champ « state » à la racine (contrairement à ce que laisse
     * penser la doc rapide) : l'état se déduit des paiements de la commande associée
     * (order.payments[].state), absente tant que le client n'a pas encore payé.
     *
     * @param array<string, mixed> $data
     */
    private function etatPaiement(array $data): string
    {
        $paiements = $data['order']['payments'] ?? null;
        if (!is_array($paiements) || [] === $paiements) {
            return 'Waiting'; // intention créée, pas encore de paiement associé
        }

        $etats = array_map(static fn ($p) => is_array($p) ? ($p['state'] ?? null) : null, $paiements);

        if (in_array('Authorized', $etats, true)) {
            return 'Authorized';
        }
        if (array_intersect($etats, ['Pending', 'Registered', 'Processing', 'Waiting'])) {
            return 'Processing';
        }

        return (string) ($etats[0] ?? 'Unknown');
    }

    private function settingsUtilisables(): HelloAssoSettings
    {
        $settings = $this->settingsRepository->getSingleton();
        if (!$settings->isUtilisable()) {
            throw new HelloAssoException('Le paiement en ligne HelloAsso n\'est pas configuré.');
        }

        return $settings;
    }

    private function apiBaseUrl(HelloAssoSettings $settings): string
    {
        return $settings->isSandbox() ? 'https://api.helloasso-sandbox.com' : 'https://api.helloasso.com';
    }

    /** @return array<string, mixed> */
    private function request(string $method, HelloAssoSettings $settings, string $path, array $options = []): array
    {
        $options['auth_bearer'] = $this->accessToken($settings);

        return $this->decode($this->httpClient->request($method, $this->apiBaseUrl($settings) . $path, $options));
    }

    private function accessToken(HelloAssoSettings $settings): string
    {
        $secret = $this->secretBox->decrypt((string) $settings->getClientSecretChiffre());
        if (null === $secret) {
            throw new HelloAssoException('Le client secret HelloAsso est illisible : ressaisissez-le dans la configuration.');
        }

        $cacheKey = 'helloasso_token_' . $settings->getEnvironnement() . '_' . md5((string) $settings->getClientId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($settings, $secret): string {
            $data = $this->decode($this->httpClient->request('POST', $this->apiBaseUrl($settings) . '/oauth2/token', [
                'body' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $settings->getClientId(),
                    'client_secret' => $secret,
                ],
            ]));

            if (!isset($data['access_token'])) {
                throw new HelloAssoException('Authentification HelloAsso refusée : vérifiez le client ID et le client secret.');
            }

            // Marge de sécurité d'une minute avant l'expiration réelle du jeton.
            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 1800) - 60));

            return (string) $data['access_token'];
        });
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        try {
            $status  = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new HelloAssoException('Connexion à HelloAsso impossible : ' . $e->getMessage(), previous: $e);
        }

        $data = json_decode($content, true);
        $data = is_array($data) ? $data : [];

        if ($status >= 400) {
            throw new HelloAssoException($this->messageErreur($data) ?? sprintf('Erreur HelloAsso (HTTP %d).', $status));
        }

        return $data;
    }

    /**
     * L'API HelloAsso renvoie ses erreurs sous plusieurs formes selon l'endpoint :
     *   - OAuth2 : {"error": "...", "error_description": "..."}
     *   - Erreurs métier (ex. Checkout Intent) : {"errors": [{"code": "...", "message": "..."}]}
     *   - Erreurs de validation (ASP.NET ProblemDetails) : {"errors": {"champ": ["message"]}, "title": "..."}
     *
     * @param array<string, mixed> $data
     */
    private function messageErreur(array $data): ?string
    {
        if (is_string($data['message'] ?? null)) {
            return $data['message'];
        }
        if (is_string($data['error_description'] ?? null)) {
            return $data['error_description'];
        }
        if (is_array($data['errors'] ?? null)) {
            foreach ($data['errors'] as $erreur) {
                if (is_array($erreur) && is_string($erreur['message'] ?? null)) {
                    return $erreur['message']; // forme liste : [{"code":..., "message":...}, ...]
                }
                if (is_array($erreur) && is_string($erreur[0] ?? null)) {
                    return $erreur[0]; // forme dictionnaire : {"champ": ["message", ...]}
                }
            }
        }
        if (is_string($data['title'] ?? null)) {
            return $data['title'];
        }

        return null;
    }
}

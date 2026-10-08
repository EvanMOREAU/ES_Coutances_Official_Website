<?php

namespace App\Twig;

use App\Security\CspNonce;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** {{ csp_nonce() }} : valeur à placer dans l'attribut « nonce » de chaque balise <script> en ligne. */
final class CspExtension extends AbstractExtension
{
    public function __construct(private readonly CspNonce $nonce)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', $this->nonce->value(...))];
    }
}

<?php

namespace App\Service\Boutique;

/** Code de réduction inconnu, expiré, épuisé, ou dont les conditions (livraison…) ne sont pas remplies. */
class PromoCodeException extends \RuntimeException
{
}

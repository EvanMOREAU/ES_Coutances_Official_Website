<?php

namespace App\Service\Boutique;

/** Levée quand une commande ne peut pas être honorée (stock épuisé, article retiré). */
class StockInsuffisantException extends \RuntimeException
{
}

<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class TimezoneListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        date_default_timezone_set('Europe/Paris');
    }
}
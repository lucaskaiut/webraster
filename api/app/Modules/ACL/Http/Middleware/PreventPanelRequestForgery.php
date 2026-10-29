<?php

namespace App\Modules\ACL\Http\Middleware;

use App\Modules\ACL\Enums\Panel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Emite o cookie XSRF com o nome do painel. Com sessões separadas, um
 * XSRF-TOKEN único seria sobrescrito pelo outro painel e geraria 419.
 */
class PreventPanelRequestForgery extends PreventRequestForgery
{
    protected function newCookie($request, $config)
    {
        $cookie = parent::newCookie($request, $config);
        $panel = Panel::fromRequest($request);

        if ($panel === null) {
            return $cookie;
        }

        return new Cookie(
            $panel->xsrfCookie(),
            $cookie->getValue(),
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            $cookie->isRaw(),
            $cookie->getSameSite(),
            $cookie->isPartitioned(),
        );
    }
}

<?php

namespace App\Support;

/**
 * Generates a URL for a non-domain-scoped route that always points at the
 * main site, regardless of which domain is currently serving the request.
 *
 * Needed for "View this combo" (comble/_game.blade.php,
 * activity/_comble-game.blade.php), which still renders on comble.show's
 * own comble.* subdomain (routes/comble.php) once Comble moved there:
 * route()/url() default to the *current* request's host for any route
 * that isn't itself domain-scoped, so a plain route('combos.show', $target)
 * call rendered from comble.combosuki.com would generate a link back to
 * comble.combosuki.com/combos/... instead of the main site.
 *
 * Deliberately not a blanket URL::forceRootUrl() override in a service
 * provider: that would also redirect asset()/@vite() calls to the main
 * domain, breaking comble.show's own same-origin asset loading (see
 * ActivityAssetController) — module scripts loaded cross-origin need CORS
 * headers the main site doesn't send. This only rewrites the host on
 * explicit calls to this helper, nothing else.
 *
 * Never use this for comble.show/comble.guess/etc. themselves — those are
 * domain-scoped on purpose and must keep resolving to the comble.*
 * subdomain when one is configured.
 */
class MainSiteUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }
}

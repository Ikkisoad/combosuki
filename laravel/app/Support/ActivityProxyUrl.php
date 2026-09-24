<?php

namespace App\Support;

/**
 * Rewrites an external embed URL into Discord's Activity proxy path
 * (`/.proxy/<prefix>/...`), so an iframe/video src actually loads when the
 * page is rendered inside the Discord Activity's discordsays.com iframe.
 *
 * Discord Activities run behind a proxy that blocks any request — including
 * an iframe's own src — to a domain that isn't registered as a "URL
 * Mapping" in the Discord Developer Portal (your app > Activities > URL
 * Mappings); an unmapped request fails client-side with a blocked:csp error
 * before it ever reaches the network. Each prefix below must have a
 * matching portal entry mapping that exact prefix to that exact target
 * host, or the rewritten src just 404s against Discord's proxy instead:
 *
 *   youtube       -> www.youtube.com
 *   streamable    -> streamable.com
 *   twitch-clips  -> clips.twitch.tv
 *   gfycat        -> gfycat.com
 *   medal         -> medal.tv
 *
 * Only covers providers whose embed is a single same-host iframe/video src
 * (see video-embed.blade.php). The script-widget providers (Twitter, Imgur,
 * NicoNico) pull in further cross-origin requests at runtime that no fixed
 * set of mappings can cover, so those render a plain link inside the
 * Activity instead of being proxied — see video-embed.blade.php.
 */
class ActivityProxyUrl
{
    private const PREFIXES = [
        'www.youtube.com' => 'youtube',
        'streamable.com' => 'streamable',
        'clips.twitch.tv' => 'twitch-clips',
        'gfycat.com' => 'gfycat',
        'medal.tv' => 'medal',
    ];

    public static function rewrite(string $url): string
    {
        $parts = parse_url($url);
        $prefix = self::PREFIXES[$parts['host'] ?? ''] ?? null;

        if ($prefix === null) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return "/.proxy/{$prefix}{$path}{$query}";
    }
}

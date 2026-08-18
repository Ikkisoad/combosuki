<?php

namespace App\Services;

class VideoEmbedResolver
{
    private const FILE_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    private const HOSTS = [
        'twitter' => ['twitter.com', 'x.com'],
        'streamable' => ['streamable.com'],
        'twitch-clip' => ['clips.twitch.tv', 'twitch.tv', 'www.twitch.tv'],
        'imgur' => ['imgur.com'],
        'nicovideo' => ['nicovideo.jp', 'nico.ms'],
        'gfycat' => ['gfycat.com'],
        'medal' => ['medal.tv'],
        'youtube' => ['youtube.com', 'youtube-nocookie.com', 'youtu.be'],
    ];

    /**
     * Sniff a free-text video URL and resolve it to a provider + the data
     * needed to render its embed. Values are returned raw (not HTML) so the
     * Blade component can escape everything on output.
     *
     * The URL's scheme and host are validated against an allow-list before
     * any provider match is attempted: everything downstream (iframe src,
     * script src) is only ever built from a URL that has already been
     * confirmed to be https:// and to point at a known provider domain.
     */
    public function resolve(?string $video): ?array
    {
        if ($video === null || $video === '') {
            return null;
        }

        $host = $this->httpsHost($video);

        if ($host === null) {
            return null;
        }

        if ($this->hostMatches($host, self::HOSTS['youtube'])) {
            return $this->resolveYoutube($video);
        }

        if ($this->hostMatches($host, self::HOSTS['twitter'])) {
            return ['provider' => 'twitter', 'url' => $video];
        }

        if ($this->hostMatches($host, self::HOSTS['streamable'])) {
            return ['provider' => 'streamable', 'url' => $video];
        }

        if ($this->hostMatches($host, self::HOSTS['twitch-clip'])) {
            $clipId = basename(parse_url($video, PHP_URL_PATH) ?? '');

            if ($clipId === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $clipId)) {
                return ['provider' => 'raw', 'url' => $video];
            }

            return ['provider' => 'twitch-clip', 'clipId' => $clipId];
        }

        if ($this->hostMatches($host, self::HOSTS['imgur'])) {
            $id = basename(parse_url($video, PHP_URL_PATH) ?? '');

            return ['provider' => 'imgur', 'url' => $video, 'id' => $id];
        }

        if ($this->hostMatches($host, self::HOSTS['nicovideo'])) {
            preg_match('%((?:sm|nm|so)\d+)%i', $video, $match);
            $id = $match[1] ?? basename(rtrim(parse_url($video, PHP_URL_PATH) ?? '', '/'));

            if ($id === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                return ['provider' => 'raw', 'url' => $video];
            }

            return ['provider' => 'nicovideo', 'id' => $id];
        }

        if ($this->hostMatches($host, self::HOSTS['gfycat'])) {
            return ['provider' => 'gfycat', 'url' => $video];
        }

        if ($this->hostMatches($host, self::HOSTS['medal'])) {
            return ['provider' => 'medal', 'url' => str_replace('/clips/', '/clip/', $video)];
        }

        $extension = strtolower(pathinfo(parse_url($video, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($extension, self::FILE_EXTENSIONS, true)) {
            return ['provider' => 'file', 'url' => $video, 'extension' => $extension];
        }

        return ['provider' => 'raw', 'url' => $video];
    }

    /**
     * Resolve a video URL to the data needed for a link-preview embed
     * (Open Graph / Twitter Card meta tags), as opposed to resolve()'s
     * on-page embed. Only providers that can be embedded from any parent
     * domain (no "parent"/referrer allow-listing required, unlike e.g.
     * Twitch clips) are given a player: it's rendered as an iframe/video by
     * whichever platform unfurls the link, so it must work unmodified for
     * an unknown embedding host.
     */
    public function openGraph(?string $video): ?array
    {
        $embed = $this->resolve($video);

        if ($embed === null) {
            return null;
        }

        return match ($embed['provider']) {
            'youtube' => [
                'kind' => 'html',
                'player' => 'https://www.youtube.com/embed/'.$embed['videoId'].($embed['start'] ? '?start='.$embed['start'] : ''),
                'width' => 1280,
                'height' => 720,
                'image' => 'https://i.ytimg.com/vi/'.$embed['videoId'].'/hqdefault.jpg',
            ],
            'streamable' => [
                'kind' => 'html',
                'player' => $embed['url'],
                'width' => 1280,
                'height' => 720,
                'image' => null,
            ],
            'medal' => [
                'kind' => 'html',
                'player' => $embed['url'],
                'width' => 640,
                'height' => 360,
                'image' => null,
            ],
            'gfycat' => [
                'kind' => 'html',
                'player' => str_replace('gfycat.com/', 'gfycat.com/ifr/', $embed['url']),
                'width' => 640,
                'height' => 360,
                'image' => null,
            ],
            'file' => [
                'kind' => 'video',
                'player' => $embed['url'],
                'width' => 1280,
                'height' => 720,
                'image' => null,
            ],
            default => null,
        };
    }

    private function resolveYoutube(string $video): array
    {
        preg_match(
            '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})%i',
            $video,
            $match
        );

        if (! isset($match[1])) {
            return ['provider' => 'raw', 'url' => $video];
        }

        $start = null;

        if (preg_match('/[?&]t=(\d+)/', $video, $tMatch)) {
            $start = $tMatch[1];
        }

        return ['provider' => 'youtube', 'videoId' => $match[1], 'start' => $start];
    }

    /**
     * Returns the lowercased host if $video is a well-formed https:// URL,
     * null otherwise (rejects javascript:, data:, http:// and malformed
     * strings up front so no provider branch below ever has to worry about
     * them).
     */
    private function httpsHost(string $video): ?string
    {
        $parts = parse_url($video);

        if (! isset($parts['scheme'], $parts['host']) || strtolower($parts['scheme']) !== 'https') {
            return null;
        }

        return strtolower($parts['host']);
    }

    private function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}

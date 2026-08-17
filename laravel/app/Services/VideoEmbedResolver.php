<?php

namespace App\Services;

class VideoEmbedResolver
{
    /**
     * Sniff a free-text video URL and resolve it to a provider + the data
     * needed to render its embed. Values are returned raw (not HTML) so the
     * Blade component can escape everything on output.
     */
    public function resolve(?string $video): ?array
    {
        if ($video === null || $video === '') {
            return null;
        }

        if (str_contains($video, 'twitter') && str_contains($video, 'https')) {
            return ['provider' => 'twitter', 'url' => $video];
        }

        if (str_contains($video, 'youtu')) {
            preg_match(
                '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i',
                $video,
                $match
            );

            if (! isset($match[1])) {
                return ['provider' => 'raw', 'url' => $video];
            }

            $start = str_contains($video, '=') ? substr($video, strpos($video, '=') + 1) : null;

            return ['provider' => 'youtube', 'videoId' => $match[1], 'start' => $start];
        }

        if (str_contains($video, 'streamable') && str_contains($video, 'https')) {
            return ['provider' => 'streamable', 'url' => $video];
        }

        if (str_contains($video, 'twitch') && str_contains($video, 'clips') && str_contains($video, 'https')) {
            $clipId = substr($video, strrpos($video, '/') + 1);

            return ['provider' => 'twitch-clip', 'clipId' => $clipId];
        }

        if (str_contains($video, 'imgur') && str_contains($video, 'https')) {
            $id = substr($video, 18);

            return ['provider' => 'imgur', 'url' => $video, 'id' => $id];
        }

        if ((str_contains($video, 'nicovideo') || str_contains($video, 'nico.ms')) && str_contains($video, 'https')) {
            preg_match('%((?:sm|nm|so)\d+)%i', $video, $match);
            $id = $match[1] ?? basename(rtrim($video, '/'));

            return ['provider' => 'nicovideo', 'id' => $id];
        }

        if (str_contains($video, 'gfycat') && str_contains($video, 'https')) {
            return ['provider' => 'gfycat', 'url' => $video];
        }

        if (str_contains($video, 'medal') && str_contains($video, 'https')) {
            return ['provider' => 'medal', 'url' => str_replace('/clips/', '/clip/', $video)];
        }

        return ['provider' => 'raw', 'url' => $video];
    }
}

<?php

namespace Tests\Unit;

use App\Support\ActivityProxyUrl;
use Tests\TestCase;

class ActivityProxyUrlTest extends TestCase
{
    public function test_youtube_embed_url_is_rewritten_to_the_proxy_prefix(): void
    {
        $this->assertSame(
            '/.proxy/youtube/embed/dQw4w9WgXcQ?start=90',
            ActivityProxyUrl::rewrite('https://www.youtube.com/embed/dQw4w9WgXcQ?start=90')
        );
    }

    public function test_streamable_twitch_gfycat_and_medal_urls_are_rewritten(): void
    {
        $this->assertSame('/.proxy/streamable/e/abc123', ActivityProxyUrl::rewrite('https://streamable.com/e/abc123'));
        $this->assertSame('/.proxy/twitch-clips/embed?clip=Foo', ActivityProxyUrl::rewrite('https://clips.twitch.tv/embed?clip=Foo'));
        $this->assertSame('/.proxy/gfycat/ifr/somegif', ActivityProxyUrl::rewrite('https://gfycat.com/ifr/somegif'));
        $this->assertSame('/.proxy/medal/clip/abc123', ActivityProxyUrl::rewrite('https://medal.tv/clip/abc123'));
    }

    public function test_a_url_with_no_path_rewrites_to_the_bare_prefix(): void
    {
        $this->assertSame('/.proxy/youtube/', ActivityProxyUrl::rewrite('https://www.youtube.com'));
    }

    public function test_a_host_without_a_registered_mapping_is_returned_unchanged(): void
    {
        $url = 'https://cdn.example.com/clip.mp4';

        $this->assertSame($url, ActivityProxyUrl::rewrite($url));
    }
}

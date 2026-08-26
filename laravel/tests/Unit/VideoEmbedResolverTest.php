<?php

namespace Tests\Unit;

use App\Services\VideoEmbedResolver;
use Tests\TestCase;

class VideoEmbedResolverTest extends TestCase
{
    private VideoEmbedResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new VideoEmbedResolver();
    }

    public function test_null_and_empty_input_resolve_to_null(): void
    {
        $this->assertNull($this->resolver->resolve(null));
        $this->assertNull($this->resolver->resolve(''));
    }

    public function test_non_https_and_malformed_urls_are_rejected(): void
    {
        $this->assertNull($this->resolver->resolve('http://youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull($this->resolver->resolve('javascript:alert(1)'));
        $this->assertNull($this->resolver->resolve('not a url at all'));
    }

    public function test_youtube_watch_url_resolves_video_id_and_start_time(): void
    {
        $result = $this->resolver->resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90');

        $this->assertSame([
            'provider' => 'youtube',
            'videoId' => 'dQw4w9WgXcQ',
            'start' => '90',
        ], $result);
    }

    public function test_youtube_short_url_resolves_video_id_without_start_time(): void
    {
        $result = $this->resolver->resolve('https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame('youtube', $result['provider']);
        $this->assertSame('dQw4w9WgXcQ', $result['videoId']);
        $this->assertNull($result['start']);
    }

    public function test_youtube_nocookie_embed_url_resolves(): void
    {
        $result = $this->resolver->resolve('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');

        $this->assertSame('youtube', $result['provider']);
        $this->assertSame('dQw4w9WgXcQ', $result['videoId']);
    }

    public function test_youtube_host_with_unmatched_id_pattern_falls_back_to_raw(): void
    {
        $result = $this->resolver->resolve('https://www.youtube.com/');

        $this->assertSame(['provider' => 'raw', 'url' => 'https://www.youtube.com/'], $result);
    }

    public function test_twitter_and_x_hosts_resolve_as_twitter(): void
    {
        $this->assertSame(
            ['provider' => 'twitter', 'url' => 'https://twitter.com/user/status/123'],
            $this->resolver->resolve('https://twitter.com/user/status/123')
        );

        $this->assertSame(
            ['provider' => 'twitter', 'url' => 'https://x.com/user/status/123'],
            $this->resolver->resolve('https://x.com/user/status/123')
        );
    }

    public function test_streamable_resolves_with_raw_url(): void
    {
        $result = $this->resolver->resolve('https://streamable.com/abc123');

        $this->assertSame(['provider' => 'streamable', 'url' => 'https://streamable.com/abc123'], $result);
    }

    public function test_twitch_clip_resolves_clip_id(): void
    {
        $result = $this->resolver->resolve('https://clips.twitch.tv/AwkwardClipName');

        $this->assertSame(['provider' => 'twitch-clip', 'clipId' => 'AwkwardClipName'], $result);
    }

    public function test_twitch_clip_with_invalid_characters_falls_back_to_raw(): void
    {
        $result = $this->resolver->resolve('https://clips.twitch.tv/Invalid%20Clip%20Name');

        $this->assertSame('raw', $result['provider']);
    }

    public function test_imgur_resolves_id_from_path_basename(): void
    {
        $result = $this->resolver->resolve('https://imgur.com/a1B2c3D');

        $this->assertSame(['provider' => 'imgur', 'url' => 'https://imgur.com/a1B2c3D', 'id' => 'a1B2c3D'], $result);
    }

    public function test_nicovideo_extracts_sm_id_pattern(): void
    {
        $result = $this->resolver->resolve('https://www.nicovideo.jp/watch/sm12345');

        $this->assertSame(['provider' => 'nicovideo', 'id' => 'sm12345'], $result);
    }

    public function test_nicovideo_falls_back_to_path_basename_when_no_id_pattern_matches(): void
    {
        $result = $this->resolver->resolve('https://nico.ms/somebasename');

        $this->assertSame(['provider' => 'nicovideo', 'id' => 'somebasename'], $result);
    }

    public function test_nicovideo_with_invalid_id_falls_back_to_raw(): void
    {
        $result = $this->resolver->resolve('https://nico.ms/');

        $this->assertSame('raw', $result['provider']);
    }

    public function test_gfycat_resolves_with_raw_url(): void
    {
        $result = $this->resolver->resolve('https://gfycat.com/somegif');

        $this->assertSame(['provider' => 'gfycat', 'url' => 'https://gfycat.com/somegif'], $result);
    }

    public function test_medal_rewrites_clips_path_to_singular_clip(): void
    {
        $result = $this->resolver->resolve('https://medal.tv/clips/abc123');

        $this->assertSame(['provider' => 'medal', 'url' => 'https://medal.tv/clip/abc123'], $result);
    }

    public function test_subdomain_hosts_match_via_suffix_rule(): void
    {
        $result = $this->resolver->resolve('https://sub.streamable.com/abc123');

        $this->assertSame('streamable', $result['provider']);
    }

    public function test_unknown_host_with_known_file_extension_resolves_as_file(): void
    {
        $result = $this->resolver->resolve('https://cdn.example.com/clip.mp4');

        $this->assertSame(['provider' => 'file', 'url' => 'https://cdn.example.com/clip.mp4', 'extension' => 'mp4'], $result);
    }

    public function test_unknown_host_with_unknown_extension_falls_back_to_raw(): void
    {
        $result = $this->resolver->resolve('https://example.com/page');

        $this->assertSame(['provider' => 'raw', 'url' => 'https://example.com/page'], $result);
    }

    public function test_open_graph_returns_null_for_unresolvable_input(): void
    {
        $this->assertNull($this->resolver->openGraph(null));
        $this->assertNull($this->resolver->openGraph(''));
    }

    public function test_open_graph_maps_youtube_to_html_embed_with_thumbnail(): void
    {
        $og = $this->resolver->openGraph('https://youtu.be/dQw4w9WgXcQ?t=5');

        $this->assertSame('html', $og['kind']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ?start=5', $og['player']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $og['image']);
    }

    public function test_open_graph_maps_streamable_medal_and_gfycat_and_file(): void
    {
        $streamable = $this->resolver->openGraph('https://streamable.com/abc123');
        $this->assertSame('html', $streamable['kind']);
        $this->assertSame('https://streamable.com/abc123', $streamable['player']);

        $medal = $this->resolver->openGraph('https://medal.tv/clips/abc123');
        $this->assertSame('html', $medal['kind']);
        $this->assertSame('https://medal.tv/clip/abc123', $medal['player']);

        $gfycat = $this->resolver->openGraph('https://gfycat.com/somegif');
        $this->assertSame('html', $gfycat['kind']);
        $this->assertSame('https://gfycat.com/ifr/somegif', $gfycat['player']);

        $file = $this->resolver->openGraph('https://cdn.example.com/clip.mp4');
        $this->assertSame('video', $file['kind']);
        $this->assertSame('https://cdn.example.com/clip.mp4', $file['player']);
    }

    public function test_open_graph_returns_null_for_providers_without_a_universally_embeddable_player(): void
    {
        $this->assertNull($this->resolver->openGraph('https://twitter.com/user/status/123'));
        $this->assertNull($this->resolver->openGraph('https://clips.twitch.tv/AwkwardClipName'));
        $this->assertNull($this->resolver->openGraph('https://imgur.com/a1B2c3D'));
        $this->assertNull($this->resolver->openGraph('https://nico.ms/sm12345'));
        $this->assertNull($this->resolver->openGraph('https://example.com/page'));
    }
}

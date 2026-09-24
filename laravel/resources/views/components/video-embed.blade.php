@props(['video', 'activity' => false])

@php
    $embed = app(\App\Services\VideoEmbedResolver::class)->resolve($video);
    $src = fn (string $url) => $activity ? \App\Support\ActivityProxyUrl::rewrite($url) : $url;
@endphp

@if ($embed)
    <div class="card card-body p-3 mb-2 bg-dark border border-5 border-dark">
        @switch($embed['provider'])
            {{--
                Twitter/Imgur/NicoNico embed via a third-party script that
                fetches further cross-origin resources at runtime — no fixed
                Discord "URL Mapping" list can cover that, so inside the
                Activity (no proxying possible) this falls back to a plain
                link instead of a guaranteed-broken widget. See
                App\Support\ActivityProxyUrl's docblock.
            --}}
            @case('twitter')
                @if ($activity)
                    <a href="{{ $video }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm">Watch on Twitter/X &#8599;</a>
                @else
                    <blockquote class="twitter-tweet" data-conversation="none" data-lang="en">
                        <p lang="en" dir="ltr"><a href="{{ $embed['url'] }}"></a></p>
                    </blockquote>
                    <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                @endif
                @break

            @case('youtube')
                <div class="ratio ratio-16x9">
                    <iframe src="{{ $src('https://www.youtube.com/embed/'.$embed['videoId'].'?start='.$embed['start']) }}" allowfullscreen></iframe>
                </div>
                @break

            @case('streamable')
                <div style="width: 100%; height: 0; position: relative; padding-bottom: 56.25%;">
                    <iframe src="{{ $src($embed['url']) }}" frameborder="0" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe>
                </div>
                <br>Please consider uploading your video to another platform, streamable videos that are inactive for 3 months are deleted.
                @break

            @case('twitch-clip')
                <iframe
                    src="{{ $src('https://clips.twitch.tv/embed?autoplay=false&clip='.$embed['clipId']) }}"
                    height="360" width="640" frameborder="0" scrolling="no" allowfullscreen>
                </iframe>
                @break

            @case('imgur')
                @if ($activity)
                    <a href="{{ $video }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm">Watch on Imgur &#8599;</a>
                @else
                    <blockquote class="imgur-embed-pub" lang="en" data-id="{{ $embed['id'] }}">
                        <a href="{{ $embed['url'] }}"></a>
                    </blockquote>
                    <script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>
                @endif
                @break

            @case('nicovideo')
                @if ($activity)
                    <a href="{{ $video }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm">Watch on Niconico &#8599;</a>
                @else
                    <script type="application/javascript" src="https://embed.nicovideo.jp/watch/{{ $embed['id'] }}/script?w=640&h=360"></script>
                @endif
                @break

            @case('gfycat')
                <div style="position:relative; padding-bottom:calc(56.40% + 44px)">
                    <iframe src="{{ $src(str_replace('gfycat.com/', 'gfycat.com/ifr/', $embed['url'])) }}" frameborder="0" scrolling="no" width="100%" height="100%" style="position:absolute;top:0;left:0;" allowfullscreen></iframe>
                </div>
                @break

            @case('medal')
                <iframe width="640" height="360" style="border: none;" src="{{ $src($embed['url']) }}" allowfullscreen></iframe>
                @break

            @case('file')
                {{-- Arbitrary user-submitted host: no fixed Discord URL Mapping can cover it, so this is left unproxied even inside the Activity — see ActivityProxyUrl's docblock. --}}
                <video controls class="w-100" style="max-height: 480px;" src="{{ $embed['url'] }}"></video>
                @break

            @default
                {{ $embed['url'] }}
        @endswitch
    </div>
@endif

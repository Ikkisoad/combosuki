@props(['video'])

@php
    $embed = app(\App\Services\VideoEmbedResolver::class)->resolve($video);
@endphp

@if ($embed)
    <div class="card card-body p-3 mb-2 bg-dark border border-5 border-dark">
        @switch($embed['provider'])
            @case('twitter')
                <blockquote class="twitter-tweet" data-conversation="none" data-lang="en">
                    <p lang="en" dir="ltr"><a href="{{ $embed['url'] }}"></a></p>
                </blockquote>
                <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                @break

            @case('youtube')
                <div class="ratio ratio-16x9">
                    <iframe src="https://www.youtube.com/embed/{{ $embed['videoId'] }}?start={{ $embed['start'] }}" allowfullscreen></iframe>
                </div>
                @break

            @case('streamable')
                <div style="width: 100%; height: 0; position: relative; padding-bottom: 56.25%;">
                    <iframe src="{{ $embed['url'] }}" frameborder="0" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe>
                </div>
                <br>Please consider uploading your video to another platform, streamable videos that are inactive for 3 months are deleted.
                @break

            @case('twitch-clip')
                <iframe
                    src="https://clips.twitch.tv/embed?autoplay=false&clip={{ $embed['clipId'] }}"
                    height="360" width="640" frameborder="0" scrolling="no" allowfullscreen>
                </iframe>
                @break

            @case('imgur')
                <blockquote class="imgur-embed-pub" lang="en" data-id="{{ $embed['id'] }}">
                    <a href="{{ $embed['url'] }}"></a>
                </blockquote>
                <script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>
                @break

            @case('nicovideo')
                <script type="application/javascript" src="https://embed.nicovideo.jp/watch/{{ $embed['id'] }}/script?w=640&h=360"></script>
                @break

            @case('gfycat')
                <div style="position:relative; padding-bottom:calc(56.40% + 44px)">
                    <iframe src="{{ str_replace('gfycat.com/', 'gfycat.com/ifr/', $embed['url']) }}" frameborder="0" scrolling="no" width="100%" height="100%" style="position:absolute;top:0;left:0;" allowfullscreen></iframe>
                </div>
                @break

            @case('medal')
                <iframe width="640" height="360" style="border: none;" src="{{ $embed['url'] }}" allowfullscreen></iframe>
                @break

            @case('file')
                <video controls class="w-100" style="max-height: 480px;" src="{{ $embed['url'] }}"></video>
                @break

            @default
                {{ $embed['url'] }}
        @endswitch
    </div>
@endif

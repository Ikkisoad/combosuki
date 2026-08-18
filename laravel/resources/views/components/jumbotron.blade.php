@props(['height' => 150])

<div class="jumbotron jumbotron-fluid">
    <div class="container">
        <a href="{{ url('/') }}">
            <img src="{{ asset('img/combosuki.png') }}" style="margin-top: 20px;" height="{{ $height }}">
        </a>
    </div>
</div>

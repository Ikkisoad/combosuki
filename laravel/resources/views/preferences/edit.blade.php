<x-layouts.app :title="'Preferences - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2>Preferences</h2>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('preferences.update') }}" class="form-control combosuki-main-reversed text-white">
                    @csrf
                    <div class="row">
                        <div class="col-auto mx-auto">
                            <label for="headcolor" class="form-label">Background Color:</label>
                            <input type="color" class="form-control form-control-color" name="color" id="headcolor" value="#{{ $color }}">
                        </div>
                        <div class="col-auto mx-auto">
                            <label class="form-label">Default Colors:</label><br>
                            <button type="button" onclick="returnColor('#C62114')" class="bg-combosuki-main-1" style="padding: 12px;"></button>
                            <button type="button" onclick="returnColor('#020202')" class="bg-combosuki-main-2" style="padding: 12px;"></button>
                            <button type="button" onclick="returnColor('#920000')" class="bg-combosuki-secondary-1" style="padding: 6px;"></button>
                            <button type="button" onclick="returnColor('#FA591C')" class="bg-combosuki-secondary-2" style="padding: 6px;"></button>
                            <button type="button" onclick="returnColor('#2E2E2E')" class="bg-combosuki-secondary-3" style="padding: 6px;"></button>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-auto mx-auto">
                            <button class="btn btn-combosuki">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function returnColor(color) {
            document.getElementById('headcolor').value = color;
        }
    </script>
</x-layouts.app>

@props(['label', 'nameField', 'userField', 'characterField', 'characters', 'nameValue' => null, 'userValue' => null, 'userLabel' => null, 'characterValue' => null, 'resourcesField' => null, 'resources' => [], 'resourceValues' => [], 'requireResources' => false])

<div class="col-md-6">
    <h5>{{ $label }}</h5>

    <div class="input-group mb-3">
        <span class="input-group-text">Name/Tag:</span>
        <input type="text" name="{{ $nameField }}" class="form-control player-name-input" maxlength="100" required value="{{ old($nameField, $nameValue) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">Link to account (optional):</label>
        <div class="input-group position-relative">
            <input type="text" name="{{ $userField }}_label" class="form-control user-search-input" autocomplete="off"
                   placeholder="Type a nickname to search&hellip;"
                   value="{{ old($userField.'_label', $userLabel) }}">
            <button type="button" class="btn btn-outline-secondary user-search-clear">Clear</button>
            <input type="hidden" name="{{ $userField }}" class="user-search-value" value="{{ old($userField, $userValue) }}">
            <div class="list-group user-search-results position-absolute w-100" style="top:100%;left:0;z-index:1000;display:none;max-height:200px;overflow-y:auto;"></div>
        </div>
    </div>

    <div class="input-group mb-3">
        <label class="input-group-text">Character:</label>
        <select name="{{ $characterField }}" class="form-select" required>
            @foreach ($characters as $character)
                <option value="{{ $character->idcharacter }}" @selected(old($characterField, $characterValue) == $character->idcharacter)>{{ $character->name }}</option>
            @endforeach
        </select>
    </div>

    @foreach ($resources as $resource)
        <div class="input-group mb-3">
            <label class="input-group-text">{{ $resource->text_name }}:</label>
            <select name="{{ $resourcesField }}[{{ $resource->idgame_resources }}]" class="form-select" @required($requireResources)>
                <option value="">&mdash;</option>
                @foreach ($resource->values as $value)
                    <option value="{{ $value->idResources_values }}" @selected(old($resourcesField.'.'.$resource->idgame_resources, $resourceValues[$resource->idgame_resources] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.user-search-input').forEach(function (input) {
                var wrapper = input.closest('.input-group');
                var hidden = wrapper.querySelector('.user-search-value');
                var results = wrapper.querySelector('.user-search-results');
                var clearButton = wrapper.querySelector('.user-search-clear');
                var nameInput = input.closest('.col-md-6').querySelector('.player-name-input');
                var debounceTimer;

                function hideResults() {
                    results.style.display = 'none';
                    results.innerHTML = '';
                }

                function runSearch(query) {
                    fetch('{{ route('users.search') }}?q=' + encodeURIComponent(query))
                        .then(function (response) { return response.json(); })
                        .then(function (users) {
                            results.innerHTML = '';

                            if (users.length === 0) {
                                hideResults();
                                return;
                            }

                            users.forEach(function (user) {
                                var item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action';
                                item.textContent = user.nickname;
                                item.addEventListener('mousedown', function (event) {
                                    event.preventDefault();
                                    hidden.value = user.iduser;
                                    input.value = user.nickname;
                                    nameInput.value = user.nickname;
                                    hideResults();
                                });
                                results.appendChild(item);
                            });

                            results.style.display = 'block';
                        })
                        .catch(hideResults);
                }

                input.addEventListener('input', function () {
                    hidden.value = '';
                    clearTimeout(debounceTimer);

                    var query = input.value.trim();

                    if (query.length < 2) {
                        hideResults();
                        return;
                    }

                    debounceTimer = setTimeout(function () { runSearch(query); }, 250);
                });

                input.addEventListener('blur', function () {
                    setTimeout(hideResults, 150);
                });

                clearButton.addEventListener('click', function () {
                    hidden.value = '';
                    input.value = '';
                    hideResults();
                    input.focus();
                });
            });
        });
    </script>
@endonce

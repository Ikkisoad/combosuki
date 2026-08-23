<x-layouts.app title="Donation Progress - Admin">
    <x-nav-bar />

    <div class="container my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h1 class="text-white mb-4">Donation Progress</h1>

        <form method="post" action="{{ route('admin.donation.update') }}" class="card combosuki-main-reversed text-white p-3">
            @csrf
            @method('put')

            <div class="mb-3">
                <label for="month" class="form-label">Month label</label>
                <input type="text" id="month" name="month" class="form-control" value="{{ old('month', $donation->month) }}" maxlength="45" required>
            </div>

            <div class="mb-3">
                <label for="goal" class="form-label">Goal ($)</label>
                <input type="number" id="goal" name="goal" class="form-control" value="{{ old('goal', $donation->goal) }}" min="0" step="0.01" required>
            </div>

            <div class="mb-3">
                <label for="raised" class="form-label">Raised ($)</label>
                <input type="number" id="raised" name="raised" class="form-control" value="{{ old('raised', $donation->raised) }}" min="0" step="0.01" required>
            </div>

            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</x-layouts.app>

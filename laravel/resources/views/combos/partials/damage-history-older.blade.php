<table class="table table-hover align-middle combosuki-main-reversed text-white mb-0">
    @forelse ($olderDamageHistory as $i => $entry)
        @php $delta = $i > 0 ? $entry->damage <=> $olderDamageHistory[$i - 1]->damage : 0; @endphp
        <tr>
            <td>{{ $entry->patch->label }}</td>
            <td>
                {{ number_format((float) $entry->damage, 0, '', '.') }}
                @if ($delta < 0)
                    <span class="text-danger">&#9660;</span>
                @elseif ($delta > 0)
                    <span class="text-success">&#9650;</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="2" class="text-white-50">No earlier patches recorded.</td>
        </tr>
    @endforelse
</table>

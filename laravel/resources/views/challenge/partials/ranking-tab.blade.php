@props(['rankings'])

@if ($rankings->isEmpty())
    <p>No ranked combos yet &mdash; check back once a registered user's combo tops a challenge.</p>
@else
    <table class="table table-hover align-middle caption-top text-white">
        <caption>Reflects current combo data, not a locked-in historical record &mdash; guest-submitted combos aren't attributed to a ranked user.</caption>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Wins</th>
        </tr>
        @foreach ($rankings as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><a href="{{ route('users.show', $row['user']) }}" class="link-light">{{ $row['user']->nickname }}</a></td>
                <td>{{ $row['wins'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

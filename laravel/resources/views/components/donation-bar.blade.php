@props(['hideWhenMet' => false])

@php
    $donation = \App\Models\DonationProgress::current();
    $goal = (float) $donation->goal;
    $raised = (float) $donation->raised;
    $percent = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
@endphp

@if (! $hideWhenMet || $raised < $goal)
    <div class="sidebar-backdrop mb-3 d-inline-block">
        <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JNX6A2HZETH5Y" class="donate" target="_blank">
            <span class="donate__title">Server costs — donations for {{ $donation->month }}</span>
            <div class="progress donate__bar" role="progressbar" aria-label="Donation progress" aria-valuenow="{{ $raised }}" aria-valuemin="0" aria-valuemax="{{ $goal }}">
                <div class="progress-bar donate__fill-bar" style="width: {{ $percent }}%"></div>
            </div>
            <span class="donate__text">
                raised <span class="donate__text--yellow">${{ number_format($raised, 2) }}</span> out of <span class="donate__text--yellow">${{ number_format($goal, 2) }}</span>
            </span>
        </a>
    </div>
@endif

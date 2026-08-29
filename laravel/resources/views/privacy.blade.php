<x-layouts.app :title="'Privacy Policy - Combo好き'" :description="'The privacy policy for Combo好き.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="card combosuki-main-reversed p-4 mb-3">
            <h1>Privacy Policy</h1>
            <p class="text-muted">Last updated: {{ now()->format('F j, Y') }}</p>

            <p>Combo好き is a free hobby project, and we try to collect as little personal data as possible. Here's exactly what we do (and don't) collect.</p>

            <h4 class="mt-4">What we collect</h4>
            <ul>
                <li>When you sign in with Discord, we store your Discord user ID and Discord display name. Discord also shares your email with us during sign-in so we can confirm it's verified with Discord — <strong>we never store your email</strong>.</li>
                <li>Your account nickname, role/trust flags, and last login time.</li>
                <li>A <code>color</code> cookie that remembers your chosen theme color, and a standard session cookie that keeps you logged in. Neither is used for tracking.</li>
                <li>Any combos, tier lists, or guides you submit — these are public by design, as they're the point of the site.</li>
            </ul>

            <h4 class="mt-4">What we don't collect</h4>
            <ul>
                <li>No email addresses.</li>
                <li>No IP address logging.</li>
                <li>No third-party analytics or advertising trackers. Page-view counts shown on the site (e.g. per game or combo) are aggregate totals, not tied to tracking individual visitors.</li>
                <li>No payment details — donations go directly through PayPal, and we never see your card or account information.</li>
            </ul>

            <h4 class="mt-4">How we use it</h4>
            <p>Your Discord ID and nickname are used only to identify your account and attribute the content you submit. We don't sell or share your data with third parties.</p>

            <h4 class="mt-4">Data deletion</h4>
            <p>Want your account or data removed? Email <a href="mailto:contact@combosuki.com">contact@combosuki.com</a> and we'll handle it manually — there's no automated self-service deletion yet.</p>

            <h4 class="mt-4">Children</h4>
            <p>The site isn't directed at children under 13, in line with Discord's own age requirements for its accounts.</p>

            <h4 class="mt-4">Changes</h4>
            <p>If this policy changes, the update will be posted on this page.</p>

            <h4 class="mt-4">Contact</h4>
            <p>Questions about your data? Reach out at <a href="mailto:contact@combosuki.com">contact@combosuki.com</a>.</p>
        </div>
    </div>
</x-layouts.app>

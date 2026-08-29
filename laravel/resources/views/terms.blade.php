<x-layouts.app :title="'Terms of Service - Combo好き'" :description="'The terms of service for Combo好き.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="card combosuki-main-reversed p-4 mb-3">
            <h1>Terms of Service</h1>
            <p class="text-muted">Last updated: {{ now()->format('F j, Y') }}</p>

            <p>Combo好き ("the site") is a free, community-run database for fighting game combos, tier lists, and guides, made and maintained by Ikki as a hobby project. It is not operated by a registered company. By using the site, you agree to these terms.</p>

            <h4 class="mt-4">Accounts</h4>
            <p>You sign in with Discord. We don't offer email/password registration, and we don't ask for or store your email address. Keep your Discord account secure — anyone with access to it can access your Combo好き account.</p>

            <h4 class="mt-4">Your content</h4>
            <p>Combos, tier lists, guides, and any other content you submit remain yours, but by submitting it you grant the site a license to host, display, and edit it (for example, to fix formatting or notation) as part of the database. Please only submit content you have the right to share.</p>
            <p>Content can be reviewed, edited, marked unverified, or removed by moderators and admins — including per-game moderators — to keep the database accurate and useful.</p>

            <h4 class="mt-4">Acceptable use</h4>
            <p>Don't post illegal content, impersonate others, or abuse the reporting/verification systems. Be a decent member of the FGC. Accounts or content that violate this can be suspended or removed.</p>

            <h4 class="mt-4">Donations</h4>
            <p>Donation links (e.g. PayPal) are handled entirely by the third-party payment provider. The site never receives, processes, or stores your payment details.</p>

            <h4 class="mt-4">No warranty</h4>
            <p>The site is provided "as is," free of charge, with no guarantee of uptime, accuracy, or fitness for any particular purpose. To the fullest extent permitted by law, the site and its operator aren't liable for any damages arising from your use of it.</p>

            <h4 class="mt-4">Changes</h4>
            <p>These terms may be updated from time to time; the current version is always the one posted on this page. Continuing to use the site after a change means you accept the updated terms.</p>

            <h4 class="mt-4">Contact</h4>
            <p>Questions about these terms? Reach out at <a href="mailto:contact@combosuki.com">contact@combosuki.com</a>.</p>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app :title="'About - Combo好き'" :description="'What Combo好き is and who makes it.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="body">
            <div class="card combosuki-main-reversed mb-3">
                <div class="row g-0">
                    <div class="col-md-4">
                        <img src="{{ asset('img/Ikki.jpg') }}" class="img-fluid" alt="Ikki">
                    </div>
                    <div class="col-md-8">
                        <div class="card-body">
                            <h5 class="card-title">Ikki</h5>
                            <p class="card-text">Creator of Combo好き.</p>
                            <p class="card-text">This application started as a fun project at the end of 2018, and the motivation to keep improving it is to help out the FGC assemble their findings and sort out the best options with specific sets of resources.</p>
                            <p class="card-text">Hopefully with this database we will be able to keep the best combos known, without losing them to endless feeds...</p>
                            <p class="card-text"><small class="text-muted">...</small></p>
                            <a href="https://bsky.app/profile/ikkisoad.combosuki.com" class="card-link" target="_blank">@ikkisoad.combosuki.com</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card combosuki-main-reversed mb-3">
                <div class="row g-0">
                    <div class="col-md-4">
                        <img src="{{ asset('img/Makai.jpg') }}" class="img-fluid" alt="Makai">
                    </div>
                    <div class="col-md-8">
                        <div class="card-body">
                            <h5 class="card-title">Makai</h5>
                            <p class="card-text">Branding.</p>
                            <p class="card-text">Has literally nothing to say.</p>
                            <p class="card-text">Graphic Designer</p>
                            <p class="card-text"><small class="text-muted">...</small></p>
                            <a href="https://twitter.com/Makaai_" class="card-link" target="_blank">@Makaai_</a>
                        </div>
                    </div>
                </div>
            </div>

            Submissions total: {{ $comboCount }}

            <br>You can help me pay server costs by donating through Paypal, there is also other ways to help this project; By submitting your combos and tech you can help not only this database grow, but make it easier for other people to improve their gameplay.
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                <input type="hidden" name="cmd" value="_s-xclick" />
                <input type="hidden" name="hosted_button_id" value="JNX6A2HZETH5Y" />
                <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
                <img alt="" border="0" src="https://www.paypal.com/en_BR/i/scr/pixel.gif" width="1" height="1" />
            </form>

            <div class="sidebar-backdrop mb-3 d-inline-block">
                <h3>Other FGC websites</h3>
                <div class="d-flex flex-wrap column-gap-4 row-gap-2">
                    <a href="https://supercombo.gg/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open SuperCombo.gg">
                        SuperCombo.gg
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://glossary.infil.net/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open The Fighting Game Glossary">
                        The Fighting Game Glossary
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="http://www.dustloop.com/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open Dustloop wiki">
                        Dustloop wiki
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://wiki.gbl.gg/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open Mizuumi wiki">
                        Mizuumi wiki
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://www.dreamcancel.com/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open Dream Cancel wiki">
                        Dream Cancel wiki
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://srk.shib.live/w/Main_Page" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open Shoryuken wiki">
                        Shoryuken wiki
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://fgcombo.com/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open FGCombo">
                        FGCombo
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                    <a href="https://www.top8er.com/" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open Top8er">
                        Top8er
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 3l6 5-6 5" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

<?php

namespace Tests\Feature\Activity;

use App\Http\Controllers\Activity\ActivityAssetController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Serves laravel/public/* (build/ assets, img/ favicons and backgrounds)
 * through Laravel instead of Apache on the comble.* subdomain (see
 * routes/activity.php and this controller's own docblock for why). Tested
 * by calling the controller directly with a crafted Request rather than
 * through the domain-scoped route it's actually registered under — that
 * route only exists when DISCORD_ACTIVITY_DOMAIN is configured before the
 * framework boots, which routes/activity.php's docblock explains is not
 * reliably testable across environments. The controller's own logic (file
 * serving, path-traversal protection) has no such dependency — it only
 * ever reads Request::path(), regardless of which route matched.
 */
class ActivityAssetControllerTest extends TestCase
{
    private string $probeFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probeFile = public_path('build/activity-asset-controller-test-probe.css');
        file_put_contents($this->probeFile, 'body{}');
    }

    protected function tearDown(): void
    {
        @unlink($this->probeFile);

        parent::tearDown();
    }

    private function requestFor(string $path): Request
    {
        return Request::create('/'.$path);
    }

    public function test_it_serves_a_file_that_exists_inside_public(): void
    {
        $response = (new ActivityAssetController)->show($this->requestFor('build/activity-asset-controller-test-probe.css'));

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * The Content-Type is set explicitly rather than left to
     * BinaryFileResponse's auto-detection — see the controller's docblock:
     * on the production host that guessing produced text/plain (fileinfo/
     * exec both unavailable there), which browsers refuse to execute as JS
     * or apply as CSS at all.
     */
    public function test_the_content_type_is_set_explicitly_by_extension_not_guessed(): void
    {
        $cssResponse = (new ActivityAssetController)->show($this->requestFor('build/activity-asset-controller-test-probe.css'));
        $this->assertSame('text/css', $cssResponse->headers->get('Content-Type'));

        $jsFile = public_path('build/activity-asset-controller-test-probe.js');
        file_put_contents($jsFile, 'export default 1;');

        try {
            $jsResponse = (new ActivityAssetController)->show($this->requestFor('build/activity-asset-controller-test-probe.js'));
            $this->assertSame('application/javascript', $jsResponse->headers->get('Content-Type'));
        } finally {
            @unlink($jsFile);
        }
    }

    public function test_it_404s_a_missing_file(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new ActivityAssetController)->show($this->requestFor('build/does-not-exist.css'));
    }

    /**
     * The exact vulnerability this controller exists to guard against: a
     * crafted path escaping laravel/public to read an arbitrary file
     * elsewhere on disk — composer.json (one level above public/) definitely
     * exists, so this proves the traversal is actually blocked, not just
     * incidentally 404ing because the target happens to be missing.
     */
    public function test_it_404s_a_path_traversal_attempt_instead_of_leaking_a_file_outside_public(): void
    {
        $this->assertFileExists(base_path('composer.json'));

        $this->expectException(NotFoundHttpException::class);

        (new ActivityAssetController)->show($this->requestFor('../composer.json'));
    }
}

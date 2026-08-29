<?php

namespace Tests\Feature\Activity;

use App\Http\Controllers\Activity\ActivityAssetController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Serves laravel/public/build/* through Laravel instead of Apache on the
 * comble.* subdomain (see routes/activity.php and this controller's own
 * docblock for why). Tested by calling the controller directly rather than
 * through the domain-scoped route it's actually registered under — that
 * route only exists when DISCORD_ACTIVITY_DOMAIN is configured before the
 * framework boots, which routes/activity.php's docblock explains is not
 * reliably testable across environments. The controller's own logic (file
 * serving, path-traversal protection) has no such dependency.
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

    public function test_it_serves_a_file_that_exists_inside_build(): void
    {
        $response = (new ActivityAssetController)->show('activity-asset-controller-test-probe.css');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_404s_a_missing_file(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new ActivityAssetController)->show('does-not-exist.css');
    }

    /**
     * The exact vulnerability this controller exists to guard against: a
     * crafted path escaping build/ to read an arbitrary file elsewhere on
     * disk — composer.json (two levels up from public/build) definitely
     * exists, so this proves the traversal is actually blocked, not just
     * incidentally 404ing because the target happens to be missing.
     */
    public function test_it_404s_a_path_traversal_attempt_instead_of_leaking_a_file_outside_build(): void
    {
        $this->assertFileExists(base_path('composer.json'));

        $this->expectException(NotFoundHttpException::class);

        (new ActivityAssetController)->show('../../composer.json');
    }
}

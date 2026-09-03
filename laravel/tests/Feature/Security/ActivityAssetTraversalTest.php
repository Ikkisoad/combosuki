<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Activity\ActivityAssetController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * ActivityAssetController reads files off disk from a route registered as
 * ->where('path', '.*'), which means an attacker controls the whole path
 * string. Its guard is realpath() plus
 * str_starts_with($file, $base.DIRECTORY_SEPARATOR).
 *
 * Tests\Feature\Activity\ActivityAssetControllerTest proves the plain
 * ../composer.json case. This file covers the encodings and near-misses an
 * attacker reaches for next, and — as the sibling file's docblock explains —
 * calls the controller directly with a crafted Request, because the route
 * only exists when DISCORD_ACTIVITY_DOMAIN is set before the framework boots.
 *
 * A raw null byte is deliberately not tested: realpath() with "\0" raises a
 * PHP ValueError rather than returning false, and it is unreachable over HTTP
 * anyway because Request::path() never percent-decodes (decodedPath() is the
 * decoding one) — so the %00-encoded form below is the reachable case.
 */
class ActivityAssetTraversalTest extends TestCase
{
    private string $probeFile;

    private string $siblingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probeFile = public_path('build/traversal-test-probe.css');
        file_put_contents($this->probeFile, 'body{}');

        // A directory whose name has laravel/public as a *string* prefix. The
        // only thing that rejects a path into it is the DIRECTORY_SEPARATOR
        // appended to $base in ActivityAssetController::serve() — without it,
        // str_starts_with() would match "…/public_html" against "…/public".
        $this->siblingDir = public_path().'_html';
        @mkdir($this->siblingDir);
        file_put_contents($this->siblingDir.DIRECTORY_SEPARATOR.'leak.css', 'body{}');
    }

    protected function tearDown(): void
    {
        @unlink($this->probeFile);
        @unlink($this->siblingDir.DIRECTORY_SEPARATOR.'leak.css');
        @rmdir($this->siblingDir);

        parent::tearDown();
    }

    private function requestFor(string $path): Request
    {
        return Request::create('/'.$path);
    }

    private function assertNotFoundFor(string $path): void
    {
        try {
            (new ActivityAssetController)->show($this->requestFor($path));
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("Path [{$path}] was served instead of being rejected.");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalPayloads(): array
    {
        return [
            'percent encoded' => ['%2e%2e%2f%2e%2e%2fcomposer.json'],
            'double encoded' => ['%252e%252e%252fcomposer.json'],
            'mixed encoding' => ['..%2fcomposer.json'],
            // Percent-encoded rather than literal: a raw backslash never
            // reaches the app at all, because Symfony's UriSigner/Request
            // rejects a URI containing one before routing.
            'encoded backslash' => ['..%5ccomposer.json'],
            'doubled encoded backslash' => ['..%5c..%5ccomposer.json'],
            'encoded null byte' => ['build/traversal-test-probe.css%00.png'],
            'absolute unix path' => ['/etc/passwd'],
            'absolute windows path' => ['C:/Windows/win.ini'],
            'deep traversal' => ['../../../../../../etc/passwd'],
            'dot segments' => ['./../composer.json'],
        ];
    }

    #[DataProvider('traversalPayloads')]
    public function test_a_crafted_path_cannot_escape_the_public_directory(string $path): void
    {
        $this->assertNotFoundFor($path);
    }

    /**
     * assertFileExists first, so a pass can never come from the target simply
     * being absent — the same discipline the sibling file applies to
     * composer.json.
     */
    public function test_a_sibling_directory_sharing_the_public_prefix_is_rejected(): void
    {
        $this->assertFileExists($this->siblingDir.DIRECTORY_SEPARATOR.'leak.css');

        $this->assertNotFoundFor('../'.basename($this->siblingDir).'/leak.css');
    }

    public function test_the_env_file_is_unreachable(): void
    {
        if (! file_exists(base_path('.env'))) {
            $this->markTestSkipped('No .env in this environment (CI copies .env.example late).');
        }

        $this->assertNotFoundFor('../.env');
    }

    /**
     * public/index.php is inside the served root, so it passes the boundary
     * check and is handed out as source rather than executed — .php is not in
     * the MIME allowlist, so it falls through to octet-stream. That is fine
     * for this file (it is public, non-secret, and Apache serves it anyway),
     * but it is pinned here so that nobody ever puts a file containing
     * secrets under public/ assuming this controller would refuse it.
     */
    public function test_a_php_file_inside_public_is_handed_out_as_octet_stream_not_executed(): void
    {
        $response = (new ActivityAssetController)->show($this->requestFor('index.php'));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function test_a_legitimate_asset_is_still_served(): void
    {
        $response = (new ActivityAssetController)->show($this->requestFor('build/traversal-test-probe.css'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/css', $response->headers->get('Content-Type'));
    }
}

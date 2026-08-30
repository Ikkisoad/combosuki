<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves laravel/public/* (build/ assets, img/ favicons and backgrounds —
 * everything comble.show's full page chrome references) directly through
 * Laravel instead of Apache, only on the comble.* subdomain (see
 * routes/activity.php, which registers this under both /build/{path} and
 * /img/{path}).
 *
 * comble/.htaccess deliberately doesn't try to rewrite straight to the
 * sibling laravel/public folder the way the repo root's .htaccess does:
 * shared hosts commonly sandbox each subdomain's vhost to its own docroot,
 * so Apache there can't read across into laravel/ even with a correct
 * relative rewrite target — confirmed in production, those requests 404'd.
 * PHP itself has no such restriction (comble/index.php already reaches into
 * ../laravel/ to boot the app at all), so this reads the file straight off
 * disk via public_path(), which always resolves relative to this app's own
 * base path regardless of which front controller/subdomain the request
 * came in through.
 *
 * The Content-Type is set explicitly rather than left to
 * BinaryFileResponse's auto-detection: that falls back to PHP's fileinfo
 * extension or shelling out to the `file` command, and on this host
 * neither worked reliably (confirmed in production — assets loaded with
 * Content-Type: text/plain, which browsers refuse to execute as JS or
 * apply as CSS at all), consistent with exec() already being disabled here
 * for other things (see the storage:link deploy workaround).
 */
class ActivityAssetController extends Controller
{
    private const MIME_TYPES = [
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'css' => 'text/css',
        'map' => 'application/json',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function show(Request $request): BinaryFileResponse
    {
        return $this->serve($request->path());
    }

    private function serve(string $relativePath): BinaryFileResponse
    {
        $base = realpath(public_path());
        $file = realpath(public_path($relativePath));

        // realpath() collapses ".." segments and resolves symlinks before
        // this check runs, so a path like "../../.env" can't escape
        // laravel/public — false (nonexistent file) is rejected the same
        // way as a successful escape attempt would be.
        abort_unless($base !== false && $file !== false && str_starts_with($file, $base.DIRECTORY_SEPARATOR), 404);

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return response()->file($file, [
            'Content-Type' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
        ]);
    }
}

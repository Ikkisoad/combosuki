<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * Thin wrapper around pragmarx/google2fa (secret generation + TOTP
 * verification) and bacon/bacon-qr-code (SVG rendering — no GD/Imagick
 * extension required, which matters on the shared-hosting target).
 */
class TwoFactorAuthenticator
{
    private const ISSUER = 'Combo好き';

    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code) === true;
    }

    public function qrCodeSvg(string $secret, string $label): string
    {
        $url = $this->engine->getQRCodeUrl(self::ISSUER, $label, $secret);

        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($url);
    }
}

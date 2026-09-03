<?php

namespace Tests\Unit;

use App\Services\TwoFactorAuthenticator;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticatorTest extends TestCase
{
    private TwoFactorAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticator = new TwoFactorAuthenticator(new Google2FA);
    }

    public function test_generate_secret_key_returns_a_valid_base32_secret(): void
    {
        $secret = $this->authenticator->generateSecretKey();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function test_generate_secret_key_returns_a_different_secret_each_time(): void
    {
        $this->assertNotSame($this->authenticator->generateSecretKey(), $this->authenticator->generateSecretKey());
    }

    public function test_verify_accepts_the_current_code_for_the_secret(): void
    {
        $secret = $this->authenticator->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->assertTrue($this->authenticator->verify($secret, $code));
    }

    public function test_verify_rejects_a_code_for_a_different_secret(): void
    {
        $secret = $this->authenticator->generateSecretKey();
        $otherSecret = $this->authenticator->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($otherSecret);

        $this->assertFalse($this->authenticator->verify($secret, $code));
    }

    public function test_verify_rejects_a_malformed_code(): void
    {
        $secret = $this->authenticator->generateSecretKey();

        $this->assertFalse($this->authenticator->verify($secret, 'not-a-code'));
    }

    public function test_qr_code_svg_renders_an_svg_document(): void
    {
        $svg = $this->authenticator->qrCodeSvg($this->authenticator->generateSecretKey(), 'someuser');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    /**
     * The label ends up in the otpauth:// URL encoded into the QR code, not
     * as literal markup — see TwoFactorAuthenticator::qrCodeSvg() and the
     * pragmarx/google2fa QRCode helper it delegates to. A label containing
     * markup-like characters must not appear verbatim in the SVG output.
     */
    public function test_qr_code_svg_does_not_leak_the_label_as_literal_markup(): void
    {
        $svg = $this->authenticator->qrCodeSvg($this->authenticator->generateSecretKey(), '"><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $svg);
    }
}

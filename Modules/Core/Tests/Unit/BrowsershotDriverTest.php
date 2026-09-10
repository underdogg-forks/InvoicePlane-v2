<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Support\PDF\Drivers\Browsershot;
use Modules\Core\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Spatie\Browsershot\Browsershot as BrowsershotEngine;

/**
 * RB-12 (#753) — getEngine() is pure config-to-engine mapping. Assert it
 * without launching Chromium (no ->pdf() call).
 */
class BrowsershotDriverTest extends AbstractTestCase
{
    #[Test]
    public function it_maps_the_paper_format_and_keeps_portrait_by_default(): void
    {
        /* Arrange */
        config()->set('ip.paperSize', 'A4');
        config()->set('ip.paperOrientation', 'portrait');

        /* Act */
        $options = $this->engineOptions((new Browsershot())->getEngine('<p>x</p>'));

        /* Assert */
        $this->assertSame('A4', $options['format'] ?? null);
        $this->assertArrayNotHasKey('landscape', $options);
    }

    #[Test]
    public function it_applies_landscape_for_landscape_orientation(): void
    {
        /* Arrange */
        config()->set('ip.paperOrientation', 'landscape');

        /* Act */
        $options = $this->engineOptions((new Browsershot())->getEngine('<p>x</p>'));

        /* Assert */
        $this->assertTrue($options['landscape'] ?? false);
    }

    #[Test]
    public function it_maps_the_configured_binaries_and_no_sandbox(): void
    {
        /* Arrange */
        config()->set('ip.browsershot.node_binary', '/usr/bin/node');
        config()->set('ip.browsershot.npm_binary', '/usr/bin/npm');
        config()->set('ip.browsershot.chrome_path', '/usr/bin/chromium');
        config()->set('ip.browsershot.no_sandbox', true);

        /* Act */
        $engine = (new Browsershot())->getEngine('<p>x</p>');

        /* Assert */
        $this->assertSame('/usr/bin/node', $this->engineProp($engine, 'nodeBinary'));
        $this->assertSame('/usr/bin/npm', $this->engineProp($engine, 'npmBinary'));
        $this->assertSame('/usr/bin/chromium', $this->engineOptions($engine)['executablePath'] ?? null);
        $this->assertTrue($this->engineProp($engine, 'noSandbox'));
    }

    #[Test]
    public function it_omits_the_optional_binaries_when_unconfigured(): void
    {
        /* Arrange */
        config()->set('ip.browsershot.node_binary', null);
        config()->set('ip.browsershot.npm_binary', null);
        config()->set('ip.browsershot.chrome_path', null);
        config()->set('ip.browsershot.no_sandbox', false);

        /* Act */
        $engine = (new Browsershot())->getEngine('<p>x</p>');

        /* Assert */
        $this->assertNull($this->engineProp($engine, 'nodeBinary'));
        $this->assertNull($this->engineProp($engine, 'npmBinary'));
        $this->assertFalse($this->engineProp($engine, 'noSandbox'));
        $this->assertArrayNotHasKey('executablePath', $this->engineOptions($engine));
    }

    /**
     * @return array<string, mixed>
     */
    private function engineOptions(BrowsershotEngine $engine): array
    {
        return $this->engineProp($engine, 'additionalOptions');
    }

    private function engineProp(BrowsershotEngine $engine, string $name): mixed
    {
        $property = new ReflectionProperty(BrowsershotEngine::class, $name);
        $property->setAccessible(true);

        return $property->getValue($engine);
    }
}

<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerCompositeImage.php';

final class HelioviewerCompositeImageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/' . uniqid('mtest_', true);
        mkdir($this->tmpDir);
        // Placeholder files; resolveMarkerPath only checks existence, not contents.
        file_put_contents($this->tmpDir . '/FOO.png', '');
        file_put_contents($this->tmpDir . '/UNK.png', '');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    public function testItShouldReturnTypeSpecificPathWhenFileExists(): void
    {
        $this->assertEquals(
            $this->tmpDir . '/FOO.png',
            Image_Composite_HelioviewerCompositeImage::resolveMarkerPath($this->tmpDir, 'FOO')
        );
    }

    public function testItShouldFallBackToUNKWhenTypeFileMissing(): void
    {
        $this->assertEquals(
            $this->tmpDir . '/UNK.png',
            Image_Composite_HelioviewerCompositeImage::resolveMarkerPath($this->tmpDir, 'BAR')
        );
    }

    public function testItShouldReturnUNKPathWhenTypeIsExplicitlyUNK(): void
    {
        $this->assertEquals(
            $this->tmpDir . '/UNK.png',
            Image_Composite_HelioviewerCompositeImage::resolveMarkerPath($this->tmpDir, 'UNK')
        );
    }

    public function testItShouldStillReturnUNKPathEvenWhenUNKFileItselfMissing(): void
    {
        $emptyDir = sys_get_temp_dir() . '/' . uniqid('mtest_empty_', true);
        mkdir($emptyDir);
        try {
            $this->assertEquals(
                $emptyDir . '/UNK.png',
                Image_Composite_HelioviewerCompositeImage::resolveMarkerPath($emptyDir, 'BAZ')
            );
        } finally {
            rmdir($emptyDir);
        }
    }
}

<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests;

use LukeDavis\GcpApiGatewaySpec\OutputHandler;
use LukeDavis\GcpApiGatewaySpec\Tests\Support\RunsFixtures;
use PHPUnit\Framework\TestCase;

final class OutputHandlerTest extends TestCase
{
    use RunsFixtures;

    public function testAbsolutePathToNonExistentFileIsUsedAsIs(): void
    {
        $tmp = $this->makeTempDir();
        $target = $tmp.'/nested/dir/spec.yaml';

        try {
            $handler = new OutputHandler($target);
            $saved = $handler->save(['swagger' => '2.0']);

            self::assertSame($target, $saved);
            self::assertFileExists($target);
        } finally {
            @unlink($target);
            @rmdir($tmp.'/nested/dir');
            @rmdir($tmp.'/nested');
            @rmdir($tmp);
        }
    }

    public function testAbsolutePathToExistingDirectoryAppendsDefaultFilename(): void
    {
        $tmp = $this->makeTempDir();

        try {
            $handler = new OutputHandler($tmp);
            $saved = $handler->save(['swagger' => '2.0']);

            self::assertSame(realpath($tmp).DIRECTORY_SEPARATOR.'generator-output.yaml', $saved);
            self::assertFileExists($saved);
        } finally {
            @unlink($tmp.'/generator-output.yaml');
            @rmdir($tmp);
        }
    }

    public function testRelativePathIsResolvedAgainstCwd(): void
    {
        $tmp = $this->makeTempDir();
        $cwd = (string) getcwd();
        chdir($tmp);

        try {
            $handler = new OutputHandler('sub/spec.yaml');
            $saved = $handler->save(['swagger' => '2.0']);

            self::assertSame($tmp.DIRECTORY_SEPARATOR.'sub/spec.yaml', $saved);
            self::assertFileExists($saved);
        } finally {
            chdir($cwd);
            @unlink($tmp.'/sub/spec.yaml');
            @rmdir($tmp.'/sub');
            @rmdir($tmp);
        }
    }

    public function testNullOutputPathDefaultsToGeneratorOutputInCwd(): void
    {
        $tmp = $this->makeTempDir();
        $cwd = (string) getcwd();
        chdir($tmp);

        try {
            $handler = new OutputHandler(null);
            $saved = $handler->save(['swagger' => '2.0']);

            self::assertSame($tmp.DIRECTORY_SEPARATOR.'generator-output.yaml', $saved);
            self::assertFileExists($saved);
        } finally {
            chdir($cwd);
            @unlink($tmp.'/generator-output.yaml');
            @rmdir($tmp);
        }
    }

    public function testExistingFileIsOverwritten(): void
    {
        $tmp = $this->makeTempDir();
        $target = $tmp.'/spec.yaml';

        try {
            file_put_contents($target, 'stale');

            $handler = new OutputHandler($target);
            $saved = $handler->save(['swagger' => '2.0']);

            self::assertSame(realpath($target), $saved);
            self::assertStringContainsString('swagger', (string) file_get_contents($target));
        } finally {
            @unlink($target);
            @rmdir($tmp);
        }
    }
}

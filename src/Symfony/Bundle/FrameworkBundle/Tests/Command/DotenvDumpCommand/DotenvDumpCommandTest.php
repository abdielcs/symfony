<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\DotenvDumpCommand;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\DotenvDumpCommand;
use Symfony\Bundle\FrameworkBundle\Tests\Command\DotenvDumpCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class DotenvDumpCommandTest extends TestCase
{

    /** @var TestAppKernel */
    private $kernel;
    /** @var Filesystem */
    private $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->kernel = new TestAppKernel('test', true);
        $this->fs->mkdir($this->kernel->getProjectDir());
    }

    protected function tearDown(): void
    {
        try {
            $this->fs->remove($this->kernel->getProjectDir());
        } catch (IOException $e) {
        }
    }

    public function testFileIsCreated()
    {
        $env = $this->kernel->getProjectDir().'/.env';
        $envLocal = $this->kernel->getProjectDir().'/.env.local.php';
        @unlink($env);
        @unlink($envLocal);

        $envContent = <<<EOF
APP_ENV=dev
APP_SECRET=abcdefgh123456789
EOF;
        file_put_contents($env, $envContent);

        $command = $this->createCommandDotenvDump();
        $command->execute([
            'env' => 'prod',
        ]);

        $this->assertFileExists($envLocal);

        $vars = require $envLocal;
        $this->assertSame([
            'APP_ENV' => 'prod',
            'APP_SECRET' => 'abcdefgh123456789',
        ], $vars);

        unlink($env);
        unlink($envLocal);
    }

    public function testEmptyOptionMustIgnoreContent()
    {
        $env = $this->kernel->getProjectDir().'/.env';
        $envLocal = $this->kernel->getProjectDir().'/.env.local.php';
        @unlink($env);
        @unlink($envLocal);

        $envContent = <<<EOF
APP_ENV=dev
APP_SECRET=abcdefgh123456789
EOF;
        file_put_contents($env, $envContent);

        $command = $this->createCommandDotenvDump();
        $command->execute([
            'env' => 'prod',
            '--empty' => true,
        ]);

        $this->assertFileExists($envLocal);

        $vars = require $envLocal;
        $this->assertSame([
            'APP_ENV' => 'prod',
        ], $vars);

        unlink($env);
        unlink($envLocal);
    }

    /**
     * @backupGlobals enabled
     */
    public function testEnvCanBeReferenced()
    {
        $env = $this->kernel->getProjectDir().'/.env';
        $envLocal = $this->kernel->getProjectDir().'/.env.local.php';
        @unlink($env);
        @unlink($envLocal);

        $envContent = <<<'EOF'
BAR=$FOO
FOO=123
EOF;
        file_put_contents($env, $envContent);

        $_SERVER['FOO'] = 'Foo';
        $_SERVER['BAR'] = 'Bar';

        $command = $this->createCommandDotenvDump();
        $command->execute([
            'env' => 'prod',
        ]);

        $this->assertFileExists($envLocal);

        $vars = require $envLocal;
        $this->assertSame([
            'APP_ENV' => 'prod',
            'BAR' => 'Foo',
            'FOO' => '123',
        ], $vars);

        unlink($env);
        unlink($envLocal);
    }

    public function testRequiresToSpecifyEnvArgumentWhenLocalFileDoesNotSpecifyAppEnv()
    {
        $env = $this->kernel->getProjectDir().'/.env';
        $envLocal = $this->kernel->getProjectDir().'/.env.local';

        file_put_contents($env, 'APP_ENV=dev');
        file_put_contents($envLocal, '');

        $command = $this->createCommandDotenvDump();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Please provide the name of the environment either by passing it as command line argument or by defining the "APP_ENV" variable in the ".env.local" file.');

        try {
            $command->execute([]);
        } finally {
            unlink($env);
            unlink($envLocal);
        }
    }

    public function testDoesNotRequireToSpecifyEnvArgumentWhenLocalFileIsPresent()
    {
        $env = $this->kernel->getProjectDir().'/.env';
        $envLocal = $this->kernel->getProjectDir().'/.env.local';
        $envLocalPhp = $this->kernel->getProjectDir().'/.env.local.php';
        @unlink($envLocalPhp);

        file_put_contents($env, 'APP_ENV=dev');
        file_put_contents($envLocal, 'APP_ENV=staging');

        $command = $this->createCommandDotenvDump();
        $command->execute([]);

        $this->assertFileExists($envLocalPhp);

        $this->assertSame(['APP_ENV' => 'staging'], require $envLocalPhp);

        unlink($env);
        unlink($envLocal);
        unlink($envLocalPhp);
    }

    private function createCommandDotenvDump(): CommandTester
    {
        $application = new Application($this->kernel);
        $application->add(new DotenvDumpCommand());

        return new CommandTester($application->find('dotenv:dump'));
    }
}

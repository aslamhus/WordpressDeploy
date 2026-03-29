<?php


use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Yashus\WPD\Composer\ComposerRemote;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Process\Process;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\DBLocal;


#[CoversClass(ComposerRemote::class)]
// #[UsesClass(DBLocalTest::class)]
final class ComposerPushTest extends TestCase
{

    private Settings $settings;
    private ComposerRemote $composerRemote;
    private Env $env;

    public function setUp(): void
    {
        parent::setUp();
        $this->settings = new Settings($_SERVER['YAS_WPD']);
        $this->env = new Env('staging');
        $this->composerRemote = new ComposerRemote($this->settings->composer, $this->env);
    }

    public function testDoesLocalComposerJsonExist(): void
    {

        $this->expectNotToPerformAssertions();
        $this->composerRemote->verifyLocalComposerJsonExists();
    }

    public function testDoesRemoteComposerJsonExist()
    {
        $this->expectNotToPerformAssertions();
        $ssh = new SSH($this->settings->ssh->getEnvConfig($this->env));
        $ssh->connect();
        $this->composerRemote->verifyRemoteComposerJsonExists($ssh);
    }
}

<?php


use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\DBLocal;


#[CoversClass(DBLocalTest::class)]
// #[UsesClass(DBLocalTest::class)]
final class DBLocalTest extends TestCase
{

    private Settings $settings;
    private DBLocal $dbLocal;

    public function setUp(): void
    {
        parent::setUp();
        $this->settings = new Settings($_SERVER['YAS_WPD']);
        $this->dbLocal = new DBLocal([
            'wpcli' => $this->settings->wpcli['local'],
            'wp_dir' => './'
        ]);
    }

    /**
     * @covers \Yashus\Wordpress\DBLocalTest
     */
    public function testDockerDatabaseSearchReplace(): void
    {
        $p = $this->dbLocal->searchReplace('https://asitethatisunlikelytoexist.test', 'http://localhost:8888', $output);
        if (!$p) {
            echo $output;
        }
        $this->assertTrue($p, true, 'wp search-replace command failed');
    }

    /**
     * @covers \Yashus\Wordpress\DBLocalTest
     */
    public function testDockerDatabaseLocalExport(): void
    {

        $testFileName = 'testDbLocalExport.sql';
        $dockerTmp = $this->settings->docker->getTmpDirContainer();
        $this->dbLocal->export($dockerTmp . DIRECTORY_SEPARATOR . $testFileName, $output, $exit_code);
        // check for db export file at docker path /tmp/Docker
        $p = Process::run(['./vendor/bin/whr', 'exec', 'bash', '-c', "ls $dockerTmp | grep $testFileName"]);
        $this->assertTrue($p, 'Failed to export DB to docker container.' . PHP_EOL . '-> Check that your container is running and/or the docker.docker_tmp_volume property in your wpd.json file. Docker tmp volume:  ' . $dockerTmp);
        // check for db export file at local mount point path ./Docker/Data/tmp
        $localTmp = $this->settings->docker->getTmpDirMount();
        $p = Process::fromShellCommandLine("ls $localTmp | grep $testFileName", $output);
        $this->assertTrue($p, 'DB export was not found in local project root Docker/Data/tmp.' . PHP_EOL . '-> Check the docker.docker_tmp_volume, local.root, local.public settings in your wpd.json file');
    }
}

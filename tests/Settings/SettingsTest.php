<?php


use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\DBLocal;


#[CoversClass(Settings::class)]
// #[UsesClass(DBLocalTest::class)]
final class SettingsTest extends TestCase
{





    /**
     * @covers \Yashus\Wordpress\DBLocalTest
     */
    public function testValidSettings(): void
    {
        $settings = new Settings($_SERVER['YAS_WPD']);
        file_put_contents('wpd.test.json', json_encode($settings, JSON_UNESCAPED_SLASHES));
        $this->assertArraysAreEqual(json_decode(json_encode($settings, JSON_UNESCAPED_SLASHES), true), $_SERVER['YAS_WPD']);
    }
}

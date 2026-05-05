<?php

namespace Cloudflare\APO\WordPress\Test;

use Cloudflare\APO\Integration\DefaultIntegration;
use Cloudflare\APO\WordPress\Constants\Plans;
use Cloudflare\APO\WordPress\PluginActions;
use phpmock\phpunit\PHPMock;

class PluginActionsTest extends \PHPUnit\Framework\TestCase
{
    use PHPMock;

    private $mockConfig;
    private $mockDataStore;
    private $mockDefaultIntegration;
    private $mockGetAdminUrl;
    private $mockLogger;
    private $mockPluginAPIClient;
    private $mockWordPressAPI;
    private $mockWordPressClientAPI;
    private $mockWPLoginUrl;
    private $mockRequest;
    private $pluginActions;

    public function setup(): void
    {
        $this->mockConfig = $this->getMockBuilder('Cloudflare\APO\Integration\DefaultConfig')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockDataStore = $this->getMockBuilder('Cloudflare\APO\WordPress\DataStore')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockGetAdminUrl = $this->getFunctionMock('Cloudflare\APO\WordPress', 'get_admin_url');
        $this->mockLogger = $this->getMockBuilder('\Psr\Log\LoggerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockPluginAPIClient = $this->getMockBuilder('Cloudflare\APO\API\Plugin')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockWordPressAPI = $this->getMockBuilder('Cloudflare\APO\WordPress\WordPressAPI')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockWordPressClientAPI = $this->getMockBuilder('Cloudflare\APO\WordPress\WordPressClientAPI')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockWPLoginUrl = $this->getFunctionMock('Cloudflare\APO\WordPress', 'wp_login_url');
        $this->mockRequest = $this->getMockBuilder('Cloudflare\APO\API\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockDefaultIntegration = new DefaultIntegration($this->mockConfig, $this->mockWordPressAPI, $this->mockDataStore, $this->mockLogger);
        $this->pluginActions = new PluginActions($this->mockDefaultIntegration, $this->mockPluginAPIClient, $this->mockRequest);
        $this->pluginActions->setClientAPI($this->mockWordPressClientAPI);
    }

    public function testReturnApplyDefaultSettingsWithZoneWithPlanBIZ()
    {
        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(
            array(
                'result' => array(
                    'plan' => array(
                        'legacy_id' => Plans::BIZ_PLAN,
                    ),
                ),
            )
        );
        $this->mockWordPressClientAPI->method('responseOk')->willReturn(true);
        $this->mockWordPressClientAPI->method('changeZoneSettings')->willReturn(true);
        $this->mockWordPressClientAPI->expects($this->exactly(15))->method('changeZoneSettings');

        $this->pluginActions->applyDefaultSettings();
    }

    public function testReturnApplyDefaultSettingsWithZoneWithFreePlan()
    {
        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(
            array(
                'result' => array(
                    'plan' => array(
                        'legacy_id' => Plans::FREE_PLAN,
                    ),
                ),
            )
        );
        $this->mockWordPressClientAPI->method('responseOk')->willReturn(true);
        $this->mockWordPressClientAPI->method('changeZoneSettings')->willReturn(true);
        $this->mockWordPressClientAPI->expects($this->exactly(13))->method('changeZoneSettings');

        $this->pluginActions->applyDefaultSettings();
    }

    public function testReturnApplyDefaultSettingsZoneDetailsThrowsZoneSettingFailException()
    {
        $this->expectException('\Cloudflare\APO\API\Exception\ZoneSettingFailException');

        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(false);

        $this->pluginActions->applyDefaultSettings();
    }

    public function testReturnApplyDefaultSettingsChangeZoneSettingsThrowsWithFailedSettingNames()
    {
        $this->expectException('\Cloudflare\APO\API\Exception\ZoneSettingFailException');
        $this->expectExceptionMessageMatches('/Failed to update the following settings/');

        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(
            array(
                'result' => array(
                    'plan' => array(
                        'legacy_id' => Plans::FREE_PLAN,
                    ),
                ),
            )
        );
        $this->mockWordPressClientAPI->method('responseOk')->willReturn(true);
        // All settings fail
        $this->mockWordPressClientAPI->method('changeZoneSettings')->willReturn(false);
        // All 13 settings (Free plan) should still be attempted despite failures
        $this->mockWordPressClientAPI->expects($this->exactly(13))->method('changeZoneSettings');

        $this->pluginActions->applyDefaultSettings();
    }

    public function testApplyDefaultSettingsContinuesAfterPartialFailure()
    {
        $this->expectException('\Cloudflare\APO\API\Exception\ZoneSettingFailException');
        $this->expectExceptionMessageMatches('/hotlink_protection/');
        $this->expectExceptionMessageMatches('/remaining settings were applied successfully/');

        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(
            array(
                'result' => array(
                    'plan' => array(
                        'legacy_id' => Plans::FREE_PLAN,
                    ),
                ),
            )
        );
        $this->mockWordPressClientAPI->method('responseOk')->willReturn(true);
        // Only hotlink_protection (11th call, index 10) fails
        $this->mockWordPressClientAPI->method('changeZoneSettings')->willReturnOnConsecutiveCalls(
            true, true, true, true, true, true, true, true, true, true,
            false, // hotlink_protection
            true, true
        );
        // All 13 settings should be attempted
        $this->mockWordPressClientAPI->expects($this->exactly(13))->method('changeZoneSettings');

        $this->pluginActions->applyDefaultSettings();
    }

    public function testApplyDefaultSettingsSucceedsWhenAllSettingsPass()
    {
        $this->mockRequest->method('getUrl')->willReturn('/plugin/:id/settings/default_settings');

        $this->mockWordPressClientAPI->method('zoneGetDetails')->willReturn(
            array(
                'result' => array(
                    'plan' => array(
                        'legacy_id' => Plans::FREE_PLAN,
                    ),
                ),
            )
        );
        $this->mockWordPressClientAPI->method('responseOk')->willReturn(true);
        $this->mockWordPressClientAPI->method('changeZoneSettings')->willReturn(true);

        // Should not throw any exception
        $this->pluginActions->applyDefaultSettings();
        $this->assertTrue(true); // Explicit assertion that we reached this point
    }
}

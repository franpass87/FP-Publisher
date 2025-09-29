<?php

declare(strict_types=1);

namespace FP\Publisher;

use FP\Publisher\Admin\Assets;
use FP\Publisher\Admin\Menu;
use FP\Publisher\Admin\Notices;
use FP\Publisher\Api\Routes;
use FP\Publisher\Infra\Capabilities;
use FP\Publisher\Infra\DB\Migrations;
use FP\Publisher\Infra\Options;
use FP\Publisher\Services\Assets\Pipeline as AssetPipeline;
use FP\Publisher\Services\Alerts;
use FP\Publisher\Services\GoogleBusiness\Dispatcher as GoogleBusinessDispatcher;
use FP\Publisher\Services\Links;
use FP\Publisher\Services\Meta\Dispatcher as MetaDispatcher;
use FP\Publisher\Services\TikTok\Dispatcher as TikTokDispatcher;
use FP\Publisher\Services\YouTube\Dispatcher as YouTubeDispatcher;
use FP\Publisher\Services\WordPress\Dispatcher as WordPressDispatcher;
use FP\Publisher\Services\Worker;
use FP\Publisher\Support\I18n;

final class Loader
{
    public static function init(): void
    {
        Migrations::maybeUpgrade();
        Options::bootstrap();
        I18n::register();
        Capabilities::register();
        Notices::register();
        Menu::register();
        Assets::register();
        Routes::register();
        AssetPipeline::register();
        Alerts::register();
        Links::register();
        MetaDispatcher::register();
        GoogleBusinessDispatcher::register();
        TikTokDispatcher::register();
        YouTubeDispatcher::register();
        WordPressDispatcher::register();
        Worker::register();
    }
}

<?php

declare(strict_types=1);

namespace FP\Publisher;

use FP\Publisher\Admin\Assets;
use FP\Publisher\Admin\Menu;
use FP\Publisher\Admin\Notices;
use FP\Publisher\Admin\UI\Enqueue as UiEnqueue;
use FP\Publisher\Api\HealthCheck;
use FP\Publisher\Api\OpenApiSpec;
use FP\Publisher\Api\Routes;
use FP\Publisher\Infra\Capabilities;
use FP\Publisher\Infra\DB\Migrations;
use FP\Publisher\Infra\DB\OptimizationMigration;
use FP\Publisher\Infra\Options;
use FP\Publisher\Monitoring\Metrics;
use FP\Publisher\Services\Assets\Pipeline as AssetPipeline;
use FP\Publisher\Services\Alerts;
use FP\Publisher\Services\GoogleBusiness\Dispatcher as GoogleBusinessDispatcher;
use FP\Publisher\Services\Housekeeping;
use FP\Publisher\Services\Links;
use FP\Publisher\Services\Meta\Dispatcher as MetaDispatcher;
use FP\Publisher\Services\TikTok\Dispatcher as TikTokDispatcher;
use FP\Publisher\Services\YouTube\Dispatcher as YouTubeDispatcher;
use FP\Publisher\Services\WordPress\Dispatcher as WordPressDispatcher;
use FP\Publisher\Services\Worker;
use FP\Publisher\Support\Cli\QueueCommand;
use FP\Publisher\Support\I18n;
use FP\Publisher\Support\Container;
use FP\Publisher\Support\CoreProvider;
use FP\Publisher\Support\ContainerRegistry;

final class Loader
{
    public static function init(): void
    {
        // Initialize lightweight service container and core services
        $container = new Container();
        ContainerRegistry::set($container);
        $core = new CoreProvider();
        $core->register($container);
        $core->boot($container);

        Migrations::maybeUpgrade();
        OptimizationMigration::maybeRun();
        Options::bootstrap();
        I18n::register();
        Capabilities::register();
        Notices::register();
        Menu::register();
        UiEnqueue::register();
        Assets::register();
        Routes::register();
        HealthCheck::register();
        OpenApiSpec::register();
        Metrics::register();
        AssetPipeline::register();
        Alerts::register();
        Links::register();
        Housekeeping::register();
        MetaDispatcher::register();
        GoogleBusinessDispatcher::register();
        TikTokDispatcher::register();
        YouTubeDispatcher::register();
        WordPressDispatcher::register();
        Worker::register();
        QueueCommand::register();
    }
}

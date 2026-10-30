<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCampaignRestartBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetSubscriber implements EventSubscriberInterface
{
    private const SCRIPT = 'plugins/MauticCampaignRestartBundle/Assets/js/campaign_restart.js';

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    /**
     * Loaded globally: the campaign event modal is fetched over AJAX, so its
     * data-onload-callback must already be defined from the initial page load.
     */
    public function injectAssets(CustomAssetsEvent $event): void
    {
        $event->addScript($this->versioned(self::SCRIPT));
    }

    /**
     * Mautic's asset version hash ignores plugin file contents, so append the
     * file's mtime to stop browsers serving a stale copy after an update.
     */
    private function versioned(string $script): string
    {
        $mtime = @filemtime(__DIR__.'/../Assets/js/'.basename($script));

        return $mtime ? $script.'?cb='.$mtime : $script;
    }
}

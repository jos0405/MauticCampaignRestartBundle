<?php

declare(strict_types=1);

use MauticPlugin\MauticCampaignRestartBundle\EventListener\AssetSubscriber;
use MauticPlugin\MauticCampaignRestartBundle\EventListener\CampaignActionSubscriber;
use MauticPlugin\MauticCampaignRestartBundle\EventListener\SegmentFilterSubscriber;
use MauticPlugin\MauticCampaignRestartBundle\Form\Type\JumpToCampaignEventType;
use MauticPlugin\MauticCampaignRestartBundle\Segment\Query\Filter\FinishedCampaignFilterQueryBuilder;

return [
    'name'        => 'Campaign Restart',
    'description' => 'Segment filter for contacts who finished a campaign, plus a cross-campaign jump action.',
    'version'     => '1.2.0',
    'author'      => 'Joey Keller',

    'services' => [
        'events' => [
            'mautic.campaign_restart.segment_filter_subscriber' => [
                'class'     => SegmentFilterSubscriber::class,
                'arguments' => ['database_connection'],
            ],
            'mautic.campaign_restart.campaign_action_subscriber' => [
                'class'     => CampaignActionSubscriber::class,
                'arguments' => [
                    'mautic.campaign.repository.event',
                    'mautic.campaign.repository.lead',
                    'mautic.campaign.scheduler',
                    'database_connection',
                    'translator',
                ],
            ],
            'mautic.campaign_restart.asset_subscriber' => [
                'class' => AssetSubscriber::class,
            ],
        ],
        'forms' => [
            'mautic.campaign_restart.form.jump_to_campaign_event' => [
                'class'     => JumpToCampaignEventType::class,
                'arguments' => ['database_connection'],
            ],
        ],
        'other' => [
            // Looked up by ID from the segment dictionary, see FinishedCampaignFilterQueryBuilder::getServiceId()
            'mautic.lead.query.builder.campaign.finished' => [
                'class'     => FinishedCampaignFilterQueryBuilder::class,
                'arguments' => ['mautic.lead.model.random_parameter_name', 'event_dispatcher'],
            ],
        ],
    ],
];

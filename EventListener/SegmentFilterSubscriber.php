<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCampaignRestartBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\SegmentDictionaryGenerationEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\MauticCampaignRestartBundle\Segment\Query\Filter\FinishedCampaignFilterQueryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SegmentFilterSubscriber implements EventSubscriberInterface
{
    public const FIELD = 'campaign_finished';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => ['onGenerateSegmentFilters', 0],
            LeadEvents::SEGMENT_DICTIONARY_ON_GENERATE   => ['onSegmentDictionaryGenerate', 0],
        ];
    }

    public function onGenerateSegmentFilters(LeadListFiltersChoicesEvent $event): void
    {
        if (!$event->isForSegmentation()) {
            return;
        }

        $rows = $this->connection->executeQuery(
            'SELECT id, name FROM '.MAUTIC_TABLE_PREFIX.'campaigns'
            .' WHERE is_published = 1 AND deleted IS NULL ORDER BY name'
        )->fetchAllAssociative();

        if (!$rows) {
            return;
        }

        $list = [];
        foreach ($rows as $row) {
            $list[$row['name']] = (string) $row['id'];
        }

        $translator = $event->getTranslator();
        $event->addChoice('lead', self::FIELD, [
            'label'      => $translator->trans('plugin.campaign_restart.segment.finished_campaign'),
            'properties' => ['type' => 'select', 'list' => $list],
            'operators'  => [
                $translator->trans('mautic.lead.list.form.operator.equals')    => OperatorOptions::EQUAL_TO,
                $translator->trans('mautic.lead.list.form.operator.notequals') => OperatorOptions::NOT_EQUAL_TO,
            ],
            'object'     => 'lead',
        ]);
    }

    public function onSegmentDictionaryGenerate(SegmentDictionaryGenerationEvent $event): void
    {
        $event->addTranslation(self::FIELD, [
            'type' => FinishedCampaignFilterQueryBuilder::getServiceId(),
        ]);
    }
}

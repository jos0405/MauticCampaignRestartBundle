<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCampaignRestartBundle\Segment\Query\Filter;

use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\Filter\BaseFilterQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;

class FinishedCampaignFilterQueryBuilder extends BaseFilterQueryBuilder
{
    public static function getServiceId(): string
    {
        return 'mautic.lead.query.builder.campaign.finished';
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $leadsTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');
        $membersAlias    = $this->generateRandomParameterName();
        $logAlias        = $this->generateRandomParameterName();
        $campaignParam   = $this->generateRandomParameterName();

        // The contact has no scheduled events left in this campaign
        $noScheduledEvents = sprintf(
            'SELECT NULL FROM %1$scampaign_lead_event_log %2$s'
            .' WHERE %2$s.lead_id = %3$s.lead_id AND %2$s.campaign_id = :%4$s AND %2$s.is_scheduled = 1',
            MAUTIC_TABLE_PREFIX,
            $logAlias,
            $membersAlias,
            $campaignParam
        );

        // ...and is still a member of it (not manually removed)
        $finishedMembers = $queryBuilder->createQueryBuilder()
            ->select($membersAlias.'.lead_id')
            ->from(MAUTIC_TABLE_PREFIX.'campaign_leads', $membersAlias)
            ->andWhere($membersAlias.'.campaign_id = :'.$campaignParam)
            ->andWhere($membersAlias.'.manually_removed = 0')
            ->andWhere('NOT EXISTS ('.$noScheduledEvents.')');

        // '!=' arrives normalised to 'neq'; anything else (incl. the legacy 'equals') means equals
        $expression = 'neq' === $filter->getOperator() ? 'notIn' : 'in';

        $queryBuilder->setParameter($campaignParam, (int) $filter->getParameterValue());
        $queryBuilder->addLogic(
            $queryBuilder->expr()->{$expression}($leadsTableAlias.'.id', $finishedMembers->getSQL()),
            $filter->getGlue()
        );

        return $queryBuilder;
    }
}

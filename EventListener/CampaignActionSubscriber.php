<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCampaignRestartBundle\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\CampaignBundle\Entity\LeadRepository as CampaignLeadRepository;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use MauticPlugin\MauticCampaignRestartBundle\Form\Type\JumpToCampaignEventType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignActionSubscriber implements EventSubscriberInterface
{
    public const EVENT_NAME       = 'campaign.jump_to_campaign_event';
    public const BATCH_EVENT_NAME = 'campaign.jump_to_campaign_event.execute';

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CampaignLeadRepository $campaignLeadRepository,
        private readonly EventScheduler $eventScheduler,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            self::BATCH_EVENT_NAME            => ['onExecute', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction(self::EVENT_NAME, [
            'label'          => 'plugin.campaign_restart.action.label',
            'description'    => 'plugin.campaign_restart.action.description',
            'formType'       => JumpToCampaignEventType::class,
            'batchEventName' => self::BATCH_EVENT_NAME,
        ]);
    }

    public function onExecute(PendingEvent $pendingEvent): void
    {
        $config     = $pendingEvent->getEvent()->getProperties();
        $campaignId = (int) ($config['campaignId'] ?? 0);
        $eventId    = (int) ($config['eventId'] ?? 0);

        if (!$campaignId || !$eventId) {
            $pendingEvent->failAll($this->translator->trans('plugin.campaign_restart.error.missing_config'));

            return;
        }

        $targetEvent = $this->eventRepository->getEntity($eventId);
        if (!$targetEvent || $targetEvent->isDeleted() || (int) $targetEvent->getCampaign()->getId() !== $campaignId) {
            $pendingEvent->failAll($this->translator->trans('plugin.campaign_restart.error.event_not_found'));

            return;
        }

        $memberIds    = array_flip($this->getCampaignMemberIds(array_keys($pendingEvent->getContactIds()), $campaignId));
        $notMemberMsg = $this->translator->trans('plugin.campaign_restart.error.not_member');
        $contacts     = new ArrayCollection();
        $logs         = new ArrayCollection();

        foreach ($pendingEvent->getPending() as $log) {
            $contact = $log->getLead();
            if (!isset($memberIds[$contact->getId()])) {
                $pendingEvent->passWithError($log, $notMemberMsg);

                continue;
            }

            $contacts->set($contact->getId(), $contact);
            $logs->add($log);
        }

        if ($contacts->isEmpty()) {
            return;
        }

        // Must run before schedule(): new logs read the contact's rotation, and
        // campaign_lead_event_log is unique on (event_id, lead_id, rotation).
        $this->campaignLeadRepository->incrementCampaignRotationForContacts($contacts->getKeys(), $campaignId);
        $this->eventScheduler->schedule($targetEvent, new \DateTime(), $contacts);

        $pendingEvent->passLogs($logs);
    }

    /**
     * @param int[] $contactIds
     *
     * @return int[]
     */
    private function getCampaignMemberIds(array $contactIds, int $campaignId): array
    {
        if (!$contactIds) {
            return [];
        }

        $rows = $this->connection->executeQuery(
            'SELECT lead_id FROM '.MAUTIC_TABLE_PREFIX.'campaign_leads'
            .' WHERE campaign_id = :campaignId AND lead_id IN (:contactIds) AND manually_removed = 0',
            ['campaignId' => $campaignId, 'contactIds' => $contactIds],
            ['contactIds' => ArrayParameterType::INTEGER]
        )->fetchFirstColumn();

        return array_map('intval', $rows);
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCampaignRestartBundle\Form\Type;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<mixed>
 */
class JumpToCampaignEventType extends AbstractType
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data            = is_array($builder->getData()) ? $builder->getData() : [];
        $selectedEventId = (int) ($data['eventId'] ?? 0);

        $campaignChoices  = [];
        $eventsByCampaign = [];

        $campaigns = $this->connection->executeQuery(
            'SELECT id, name FROM '.MAUTIC_TABLE_PREFIX.'campaigns'
            .' WHERE is_published = 1 AND deleted IS NULL ORDER BY name'
        )->fetchAllAssociative();

        foreach ($campaigns as $campaign) {
            $campaignId                         = (int) $campaign['id'];
            $campaignChoices[$campaign['name']] = $campaignId;
            $eventsByCampaign[$campaignId]      = [];
        }

        $events = $this->connection->executeQuery(
            'SELECT e.id, e.campaign_id, e.name, e.type FROM '.MAUTIC_TABLE_PREFIX.'campaign_events e'
            .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaigns c ON c.id = e.campaign_id'
            .' WHERE e.event_type = :eventType AND e.deleted IS NULL'
            .' AND c.is_published = 1 AND c.deleted IS NULL ORDER BY e.name',
            ['eventType' => 'action']
        )->fetchAllAssociative();

        foreach ($events as $event) {
            $campaignId = (int) $event['campaign_id'];
            if (!isset($eventsByCampaign[$campaignId])) {
                continue;
            }

            $eventsByCampaign[$campaignId][] = [
                'id'   => (int) $event['id'],
                'name' => $event['name'] ?: $event['type'],
            ];
        }

        $builder->add('campaignId', ChoiceType::class, [
            'label'       => 'plugin.campaign_restart.form.campaign',
            'label_attr'  => ['class' => 'control-label'],
            'choices'     => $campaignChoices,
            'attr'        => [
                'class'                => 'form-control',
                'data-onload-callback' => 'campaignRestartInitCascade',
            ],
            'constraints' => [new NotBlank(['message' => 'mautic.core.value.required'])],
        ]);

        $builder->add('eventId', ChoiceType::class, [
            'label'       => 'plugin.campaign_restart.form.event',
            'label_attr'  => ['class' => 'control-label'],
            'choices'     => [],
            'attr'        => [
                'class'               => 'form-control',
                'data-events-json'    => json_encode($eventsByCampaign, JSON_THROW_ON_ERROR),
                'data-selected-event' => $selectedEventId ?: '',
            ],
            'constraints' => [new NotBlank(['message' => 'mautic.core.value.required'])],
        ]);

        // Options are filled client-side per campaign, so the submitted ID is never in the static choices
        $builder->get('eventId')->resetViewTransformers();
    }

    public function getBlockPrefix(): string
    {
        return 'campaignevent_jump_to_campaign';
    }
}

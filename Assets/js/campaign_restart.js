Mautic = window.Mautic || {};

/**
 * Fills the "Target event" select with the actions of the chosen campaign.
 * Wired up via data-onload-callback on the campaign select (JumpToCampaignEventType).
 */
Mautic.campaignRestartInitCascade = function (el) {
    var campaignSelect = mQuery(el);
    var eventSelect    = mQuery('#campaignevent_properties_eventId');

    if (!eventSelect.length) {
        return;
    }

    var eventsJson    = eventSelect.attr('data-events-json');
    var eventsData    = eventsJson ? JSON.parse(eventsJson) : {};
    var selectedEvent = String(eventSelect.attr('data-selected-event') || '');

    function updateEvents() {
        var events = eventsData[String(campaignSelect.val())] || [];

        eventSelect.empty();

        mQuery.each(events, function (i, event) {
            var option = mQuery('<option/>').val(event.id).text(event.name);
            if (String(event.id) === selectedEvent) {
                option.prop('selected', true);
            }
            eventSelect.append(option);
        });

        if (typeof mQuery.fn.chosen !== 'undefined' && eventSelect.data('chosen')) {
            eventSelect.trigger('chosen:updated');
        }

        selectedEvent = ''; // only restore the saved event on the first load
    }

    campaignSelect.off('change.campaignRestart').on('change.campaignRestart', updateEvents);
    updateEvents();
};

# Campaign Restart

A **Mautic 5** plugin for contacts who have reached the end of a campaign: find
them with a segment filter, and send them into a specific step of another
campaign.

[![Licence: MIT](https://img.shields.io/badge/licence-MIT-blue.svg)](LICENSE)
![Mautic 5](https://img.shields.io/badge/Mautic-5.x-4e5e9e.svg)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)

Once a contact has run through every step of a campaign, the campaign engine
stops looking at them. They are still a member, but nothing is scheduled, so
nothing happens. This plugin makes those contacts visible and gives you a way
to move them on.

## Segment filter: Finished campaign

**Finished campaign (no pending actions)** appears in the segment filter list.

| Operator | Matches |
| --- | --- |
| **equals** | Contacts who are in the chosen campaign, were not manually removed, and have no scheduled events left in it. |
| **not equal** | Everyone else: contacts who were never in the campaign, still have scheduled steps, or were manually removed. |

Only published campaigns are offered.

## Campaign action: Jump to event in campaign

Schedules a chosen **action** in another campaign for the contact, right away.
Pick the target campaign, then the action within it.

- The contact must **already be a member** of the target campaign. Contacts who
  are not are passed with a note in the event log ("contact is not a member of
  the target campaign") and nothing is scheduled for them.
- Before scheduling, the contact's **rotation** in the target campaign is
  increased, so they can run the same step again without colliding with their
  earlier run.
- If the target event has been deleted, or no longer belongs to the chosen
  campaign, the action fails with a clear message.

Typical setup: a segment using **Finished campaign equals A** feeds campaign B,
and B uses **Jump to event in campaign** to restart A at the step you choose.

## Install

1. Copy this folder to `plugins/MauticCampaignRestartBundle` (the folder name
   must match).
2. Clear the cache **as the web server user**, so the cache does not end up
   owned by root:

   ```bash
   sudo -u www-data php bin/console cache:clear
   ```

3. In Mautic, open **Settings → Plugins** and click **Install/Upgrade Plugins**.

The plugin has no database tables and no settings.

## Requirements

- Mautic 5.x (tested on 5.2.1)
- PHP 8.1 or newer

## Licence

MIT, see [LICENSE](LICENSE).

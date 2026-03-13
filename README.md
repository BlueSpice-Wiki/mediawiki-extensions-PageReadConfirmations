# PageReadConfirmations

PageReadConfirmations is a MediaWiki extension that lets teams request and track explicit confirmation that assigned users have read a wiki page revision.

It is useful for policy pages, operating procedures, release notes, or any other page where readers must acknowledge that they reviewed a specific version.

## Features

- Assign read confirmations to individual users and user groups.
- Request confirmation for a specific page revision.
- Let assigned users confirm that they have read the requested revision.
- Show pending and completed confirmations in a page-level panel.
- Send reminders to users who have not confirmed yet.
- Cancel requests or remove stored confirmations when needed.
- Keep an audit trail in the MediaWiki log.
- Integrate with automation and workflow tooling.

## Requirements

- MediaWiki 1.43 or later
- `OOJSPlus`
- `StandardDialogs`

Optional integrations supported by the extension:

- `WikiAutomations`
- `Workflows`
- `NotifyMe`
- `BlueSpiceDiscovery`

## Installation

Install the extension in your MediaWiki `extensions/` directory and enable it in `LocalSettings.php`.

### From Git

```php
wfLoadExtension( 'PageReadConfirmations' );
```

Then run:

```bash
php maintenance/run.php update
```

### With Composer

```bash
composer require mediawiki/page-read-confirmations
php maintenance/run.php update
```

Database tables:

- `page_read_confirmations`
- `page_read_confirmations_assignments`
- `page_read_confirmations_requests`

## How it works

### For administrators and page maintainers

1. Open an existing page.
2. Use the **Read confirmations** action.
3. Assign users and/or groups that must acknowledge the page.
4. Trigger a confirmation request for the relevant revision.
5. Monitor pending and completed confirmations in the panel.
6. Send reminders or cancel the request if required.

The extension stores confirmations per page revision, so you can ask users to confirm a newly published version after a page changes.

### For readers

Assigned users can open the page, review the requested revision, and confirm that they have read it. The panel shows whether confirmation is still pending and which page version is currently requested.

If a confirmation request targets an older revision instead of the current page version, the interface shows a warning.

## Permissions and behavior

- Users must be assigned before they can confirm reading.
- Only registered users can confirm reading.
- Managing assignments and removing confirmations follows `edit` permission checks on the page.
- Triggering requests and sending reminders follows `read` permission checks on the page.

## User interface

The extension adds a **Read confirmations** action for supported pages and loads a panel that shows:

- the requested revision
- pending confirmation count
- assigned users
- each user’s latest confirmed revision
- confirmation timestamps

From the same interface, authorized users can edit assignments, send reminders, and cancel active requests.

## Logging and notifications

PageReadConfirmations writes actions to the `PageReadConfirmations` debug log, as well as to Special:Log log, including:

- confirmations
- confirmation removals
- assignment changes
- request creation
- request cancellation

When `NotifyMe` is available, reminder events can be emitted for users who still have pending confirmations.

## Automation and workflow integration

The extension exposes integration points for automation-oriented installations:

- `WikiAutomations` action: `page-read-confirmations-trigger`
- `Workflows` activity: `trigger_read_confirmation`

The workflow activity can target the current workflow page or a specific page name, and it supports selecting users and groups as the audience.

Example from `docs/workflowActivity.xml`:

```xml
<bpmn:task id="TriggerConfirmation" name="trigger-rc">
    <bpmn:extensionElements>
        <wf:type>trigger_read_confirmation</wf:type>
    </bpmn:extensionElements>
    <bpmn:property name="audience_users">UserA,UserB</bpmn:property>
    <bpmn:property name="audience_groups">sysop</bpmn:property>
</bpmn:task>
```

## REST API

The extension registers REST endpoints for assignment management, request status, confirmation submission, reminders, and request cancellation. See `extension.json` for the full route list.


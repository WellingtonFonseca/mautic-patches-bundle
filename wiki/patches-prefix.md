# Patches: required Prefixo of segments and campaigns

The required Prefixo on segments (own table, Segment Settings, form select, list column with sorting, automatic alias) and on campaigns (same model), and the PREFIX - Name shown in titles and lists.

## What it does

```json
{
  "segment prefix": "ADDITION, plugin 0.4.0: a required Prefixo on every segment (own table segment_prefix, registered in Configuration > Segment Settings), a form select, a sortable list column and an automatic alias from prefix + name. See \"Segment prefix (field, column, sort, alias)\" below."
}
```

## Segment prefix (field, column, sort, alias)

A segment has no custom fields, so the prefix (Compras, Vendas, an IES, any business unit) is kept by the plugin in its own table. Plugin version 0.4.0.

```json
{
  "registered in": "Configuration > Segment Settings > Prefixos (one per line). Parameter `segment_prefixes` in local.php. Form extension on core's SegmentConfigType (SegmentPrefixConfigExtension); the parameter is added to core's `segment_config` form by SegmentPrefixConfigSubscriber (priority -10, after core's listener). A prefix cannot have spaces or `|`, and cannot repeat.",
  "storage": "table `segment_prefix` (segment_id PK, prefix), entity Entity/SegmentPrefixValue, access by Service/SegmentPrefixStore (upsert/delete by SQL). Created by MauticPatchesBundle::onPluginUpdate (only when missing), so run `mautic:plugins:reload` after deploying. Written on LeadEvents::LIST_POST_SAVE, removed on LIST_POST_DELETE (SegmentPrefixSaveSubscriber).",
  "form and API": "SegmentPrefixFormExtension adds an unmapped ChoiceType `prefix` to core's ListType. Core's template does not list it, form_end() renders it at the bottom and SegmentPrefixFieldSubscriber's script moves it before Nome. API key `prefix`: missing or not registered = 400. PATCH without `prefix` keeps the stored one. A prefix the segment has but was removed from the list stays valid for that segment only. The API answer does not return `prefix` yet.",
  "list column": "SegmentPrefixColumnSubscriber: each row gets a hidden marker (core's customContent('segment.name') + Segment/prefix_marker.html.twig) with the prefix and the sort state; a script adds the header and the cells (plain text) before Nome, again after every ajax page load.",
  "sort": "Sorted by the server. SORT_KEY `segment_prefix` is the value core stores as the list order. Entity/SegmentListRepository extends core's LeadListRepository and overrides buildOrderByClause: hidden sub-select of the prefix + name as tie-break. Doctrine/SegmentRepositoryListener (doctrine.event_listener, loadClassMetadata) swaps the repository class of LeadList; the repository is a service tagged doctrine.repository_service with the entity class given (core's CommonRepository would guess a wrong entity from the class name). Arrow up = ascending (A to Z), same as every core column.",
  "alias": "Service/SegmentAlias, called from SegmentPrefixFormExtension (POST_SUBMIT) when the alias is empty: prefix + name, transliterated, lower case, only a-z and 0-9. Core then makes it unique (a number suffix). A typed alias is kept, but core's cleanAlias still strips dashes/underscores from it.",
  "public name": "Service/SegmentPublicName, called from SegmentPrefixFormExtension (POST_SUBMIT): the segment's publicName is ALWAYS `prefix - name` (name = the Nome field), whatever is typed or sent, on every save, form and API. Uses the prefix just submitted, or the stored one on a PATCH without `prefix`. Segments saved before this keep their old public name until saved again."
}
```

**Gotcha**: after changing the repository class or the services, `cache:clear` is not enough on a running container: the web workers kept the old container and the segment API answered 500 ("repository ... service could not be found") until Apache was reloaded (`apachectl graceful`). A fresh container start is fine.

**Existing segments** have no prefix (empty column); the form asks for one when they are edited.

**Production checklist**

```json
{
  "deploy": "the plugin's main, then `mautic:plugins:reload` (creates the segment_prefix table), `cache:clear`, and restart/recreate the web container.",
  "settings": "Configuration > Segment Settings > Prefixos: register the prefixes BEFORE users create segments (with an empty list no segment can be created).",
  "integrations": "anything that creates segments through the API (n8n, scripts) must send `prefix`. The scripts in mautic/scripts that create segments (test-segment-rebuild-api.sh, test-segment-filter-operators.sh, seed-segment-merge-test.sh) do not send it yet.",
  "data": "segments that already exist have no prefix: edit each one and pick it."
}
```

## Campaign prefix (same model as the segment prefix)

The campaign prefix came with plugin version 0.5.0. A required Prefixo on every campaign, built as a copy of the segment prefix classes (names `Campaign*`), without alias and public name (a campaign has neither; the name stays as typed).

```json
{
  "registered in": "Configuration > Campaign Settings > Prefixos (one per line). Parameter `campaign_prefixes`. Form extension on core's campaign ConfigType (alias `campaignconfig`). Core's theme of that tab lists its fields one by one, so CampaignPrefixConfigSubscriber sets the form theme to Resources/views/Campaign/config_theme.html.twig, which extends core's and adds the box. Rules (no space, no |, no repeat) are SegmentPrefixes::problem.",
  "storage": "table `campaign_prefix` (campaign_id PK, prefix), entity CampaignPrefixValue, access by CampaignPrefixStore. Written on CampaignEvents::CAMPAIGN_POST_SAVE, removed on CAMPAIGN_POST_DELETE (deletedId is used when the id was cleared). Created by MauticPatchesBundle::onPluginUpdate (run mautic:plugins:reload).",
  "form and API": "CampaignPrefixFormExtension adds an unmapped required ChoiceType `prefix` to core's CampaignType, moved before Nome by CampaignPrefixFieldSubscriber (`#campaign_prefix`, `#campaign_name`). API key `prefix`: missing or not registered = 400, PATCH without it keeps the stored one. The campaigns API needs at least one event and one source segment: a test creating a campaign must send both.",
  "list column and sort": "CampaignPrefixColumnSubscriber: hidden marker per row (customContent 'campaign.name'), script adds the header and cells to #campaignTable before th.col-campaign-name. SORT_KEY `campaign_prefix`; Entity/CampaignPrefixRepository extends core's CampaignRepository (buildOrderByClause, hidden sub-select, tie-break c.name); Doctrine/CampaignRepositoryListener sets it on the Campaign entity.",
  "tests of the prefix": "scripts/5/test-campaign-prefix.sh (registers Cp and Cq, creates and deletes two campaigns). scripts/lib/campaign-prefix.sh registers Cf for the seeds (seed-campaign.sh, seed-n8n.sh), like the segment seed.",
  "segment names in the campaign": "the contact source shows segments as `PREFIX - Name` (select of the source form, canvas node, list filter). They all come from CampaignModel::getSourceLists, so Model/CampaignPrefixModel (extends core's model, prefixes through Service/SegmentSourceNames, prefixes read with SegmentPrefixStore) replaces core's service id in Config/services.php; it needs cache:clear and a web restart after deploy. The Preview tab of the campaign page prints list.name from the entities and does not use getSourceLists: CampaignPreviewSourceSubscriber injects a script on the hook `tabs.content` that rewrites the node text (only when it equals the names joined with ', ').",
  "campaign page title": "CampaignTitlePrefixSubscriber injects a script on `page.header.left` that rewrites `h1.page-header-title` and document.title to `PREFIX - Name` (only when the heading is exactly the campaign name; the saved name does not change). Core's details template prints the name from the entity, so there is no server hook for it.",
  "tests": "scripts/5/test-campaign-source-prefix.sh (source select, canvas node, Preview tab) and scripts/5/test-campaign-prefix.sh (also the page title, by full page and ajax).",
  "deploy": "mautic:plugins:reload (creates campaign_prefix), cache:clear, restart the web container. Register the prefixes BEFORE users create campaigns: with an empty list no campaign can be created. Anything that creates campaigns through the API (n8n, scripts) must send `prefix`. Existing campaigns have no prefix (empty column); the form asks for one when they are edited."
}
```

# Kaydee Business Schema

One place for a business's details. It:

- **Outputs LocalBusiness structured data** by extending the single Organization entity from Yoast, Rank Math or AIOSEO (standalone JSON-LD when none is active), so there is never a second competing entity.
- **Provides shortcodes** so the visible Contact page and footer read from the same settings: `[kbs field="phone" link="1"]`, `[kbs field="email" link="1"]`, `[kbs field="address" format="multiline"]`, `[kbs field="hours"]`, `[kbs field="legal_name"]`, `[kbs field="company_number"]`.
- **Has a settings page** (Settings → Business Details) with hours, special/holiday hours, memberships, profiles, people, and a read-only JSON-LD preview.
- **Registers WordPress Abilities** (WP 6.9+) so agents/tools can read and set details through the plugin's own validation:
  `kaydee-business-schema/get-details`, `…/set-details`, `…/preview`.

Requires WordPress 6.0+, PHP 7.4+. Abilities need WordPress 6.9+.

## What it deliberately does not do
- No `aggregateRating`/`review` markup (self-served ratings aren't eligible and can trigger manual actions).
- No `geo` coordinates unless real ones are added.
- Does not edit your Google Business Profile. Keep hours there in step.

## Install
Download `kaydee-business-schema.zip` from the latest GitHub Release and install it (Plugins → Add New → Upload), or with WP-CLI:

    wp plugin install https://github.com/Kaydee-Web/kaydee-business-schema/releases/latest/download/kaydee-business-schema.zip --activate

Pin a specific tag in automation: `…/releases/download/v0.1.0/kaydee-business-schema.zip`.

## Set details programmatically
Ability `kaydee-business-schema/set-details` takes only the keys to change. Hours accept `"08:30-17:30"`, `"closed"`, or `[{opens, closes}]`:

    {
      "business_type": "AutoRepair",
      "legal_name": "Example Limited",
      "telephone": "01234 567890",
      "hours": { "Monday": "08:30-17:30", "Saturday": "closed" },
      "special_hours": [{ "label": "Christmas", "from": "2026-12-24", "to": "2027-01-02", "closed": true }],
      "same_as": ["https://www.facebook.com/example"],
      "people": [{ "name": "Jane Smith", "job_title": "Owner", "page_slug": "about-us", "founder": true }]
    }

Over plain REST (any client with an application password): `POST /wp-json/wp-abilities/v1/abilities/kaydee-business-schema/set-details/run` with body `{"input": { ... }}`; `GET …/preview/run` and `GET …/get-details/run` for reads.

The result lists any values that were dropped as invalid (`notes`) and a `preview` of the output.

## Updates from GitHub
The plugin checks this repo's GitHub Releases (via the bundled [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker), MIT) and shows new versions on the normal Plugins/Updates screens. Sites update from the release asset `kaydee-business-schema.zip`. WordPress checks about every 12 hours; use "Check for updates" on the Plugins page to force it.

- Auto-updates for one site: `wp plugin auto-updates enable kaydee-business-schema`, or the toggle in wp-admin. Consider updating in batches rather than all at once.
- Point a site at a fork, or switch checks off: use the `kbs_update_repo` filter (return an empty string to disable), or define `KBS_UPDATE_REPO`.
- Private repo: add the checker's `setAuthentication()` token in a small mu-plugin; do not commit tokens.
- Sites on 0.1.0 have no updater: upload 0.2.0 manually once, and updates are automatic after that.

## Releasing
1. Bump `Version:` in `kaydee-business-schema.php` and `KBS_VERSION` (the workflow fails if the tag doesn't match the header).
2. Publish a GitHub Release with tag `vX.Y.Z` (or `git tag vX.Y.Z && git push --tags`). The workflow checks the tag matches the plugin version and attaches `kaydee-business-schema.zip` (root folder `kaydee-business-schema/`).

## Filters
`kbs_types` (business type map), `kbs_update_repo`.

## Notes
Business facts live in one option, `kbs_details`. Uninstalling the plugin deletes it.

## Licence
GPL-3.0-or-later. Copyright (c) Kaydee. See LICENSE.

## Third-party code
`vendor/plugin-update-checker/` is [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 6.0 by Janis Elsts, MIT licensed (see `vendor/plugin-update-checker/license.txt`).

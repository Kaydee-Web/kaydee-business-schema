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

    wp plugin install https://github.com/<org>/kaydee-business-schema/releases/latest/download/kaydee-business-schema.zip --activate

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

The result lists any values that were dropped as invalid (`notes`) and a `preview` of the output.

## Updates from GitHub (optional)
Bundle [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) at `vendor/plugin-update-checker/` and set, e.g. in `wp-config.php`:

    define( 'KBS_UPDATE_REPO', 'https://github.com/<org>/kaydee-business-schema/' );

Private repo: also add the checker's `setAuthentication()` token in a small mu-plugin; do not commit tokens.

## Releasing
1. Bump `Version:` in `kaydee-business-schema.php` and `KBS_VERSION`.
2. `git tag v0.1.1 && git push --tags`. The workflow checks the tag matches the plugin version and attaches `kaydee-business-schema.zip` (root folder `kaydee-business-schema/`).

## Filters
`kbs_types` (business type map), `kbs_update_repo`.

## Notes
Business facts live in one option, `kbs_details`. Uninstalling the plugin deletes it.

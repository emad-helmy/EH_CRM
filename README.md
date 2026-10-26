<div align="center">

<img src="logo.png" width="140" alt="EH CRM logo">

# EH CRM — UserSpice Plugin

A CRM plugin for UserSpice, built for a real-estate marketing company.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![UserSpice](https://img.shields.io/badge/UserSpice-6.1.7-4B8BBE)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-✓-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![i18n](https://img.shields.io/badge/UI-Arabic%20%7C%20English-blueviolet)

**Author:** Emad Helmy

</div>

---

## How it works

```
CSV import (template + review) ──► equal distribution to sales team
        ──► sales rep calls & logs the result ──► manager monitors / transfers
```

## Features

- Lead import from CSV (downloadable template + review before registration)
- Equal distribution of leads across the sales team
- Per-lead call logging with results and follow-up dates
- Sales manager tools: monitoring and transferring batches of leads to another employee
- Reports with KPIs and charts
- Admin settings: call results, sources, projects, team profiles
- Supervisor control panel (user management)
- Bilingual UI (Arabic / English) with automatic RTL/LTR

## Requirements

- UserSpice 6.1.7
- PHP 8.x
- MySQL / MariaDB

## Installation

1. Copy the `eh_crm/` folder into `usersc/plugins/`.
2. Open `usersc/plugins/eh_crm/install.php` in the browser (as the master account).
   This registers the plugin, deploys the pages to `usersc/`, creates the `plg_ehcrm_*`
   tables, registers the `eh_*` pages in the UserSpice `pages` table, and merges the
   language keys — everything automatically.
   (`sql/database.sql` is idempotent — safe to run multiple times, manually via
   phpMyAdmin too if you prefer.)
3. Activate the plugin from **Admin → Plugins**.
4. Open the plugin **Configure** page:
   - Click **Create Permissions** — creates the 4 CRM permissions from `sql/permissions.sql`
     (idempotent: existing permissions are never duplicated), then lists them with a user count.
   - Click **Create Menu** — adds the CRM dropdown menu from `sql/us_menu_items.sql`.
     Pick the target menu(s) with the checkboxes first. Menu permissions are filled
     **dynamically** from the permissions created in the previous step (the hardcoded IDs
     in the SQL file are replaced by the real ones).

## Pages (deployed to `usersc/`)

| File | Purpose |
|---|---|
| `eh_dashboard.php` | CRM dashboard (entry point for all roles) |
| `eh_lead_import.php` | CSV lead import with review |
| `eh_batches.php` | Import batches |
| `eh_leads.php` | All leads |
| `eh_lead_view.php` | Lead details |
| `eh_my_leads.php` | Sales rep: my leads |
| `eh_my_calls.php` | Sales rep: my call log |
| `eh_distribute.php` | Distribution / transfer |
| `eh_reports.php` | Reports |
| `eh_settings.php` | CRM settings |
| `eh_ceo_control.php` | Supervisor control panel |
| `eh_no_access.php` | No-access fallback page |

## Permissions

The plugin uses 4 named permissions (created from the Configure page, or pre-existing):

| Permission | Role |
|---|---|
| CRM Data Entry | Data entry operator |
| CRM Sales | Sales rep |
| CRM Sales Manager | Sales manager (sees all, distributes, transfers) |
| CRM Supervisor | Supervisor (manages users via CEO Control) |

Permission IDs are resolved by **name**, so they work regardless of the actual ID numbers
in your UserSpice install.

## Database tables (prefix: `plg_ehcrm_`)

`plg_ehcrm_leads`, `plg_ehcrm_lead_assignments`, `plg_ehcrm_call_logs`,
`plg_ehcrm_call_results`, `plg_ehcrm_import_batches`, `plg_ehcrm_import_staging`,
`plg_ehcrm_projects`, `plg_ehcrm_sources`, `plg_ehcrm_sales_profile`,
`plg_ehcrm_settings`, `plg_ehcrm_activity_log`

No foreign keys are used (same style as UserSpice core) — integrity is enforced in code.

## Language

- `lang/ar-EG.php` and `lang/en-US.php`.
- On install, missing keys are appended to `usersc/lang/*.php`.
  **Existing keys are never modified or removed** — user edits are safe.
- Keys are also merged into `$lang` at runtime by `crm_load_lang()`.
- Menu items use lang keys as labels, so they follow the site language.

## Structure

```
eh_crm/
├── info.xml              (plugin metadata, author)
├── logo.png              (shown in Admin → Plugins)
├── functions.php         (auto-loaded: loads lang, SQL runner)
├── install.php           (registers plugin, deploys pages, runs SQL, merges lang)
├── activate.php          (activation + re-deploy)
├── uninstall.php         (removes pages, menu, pages-table rows, tables, lang keys)
├── migrate.php / update.php  (re-deploy pages + re-run SQL, idempotent)
├── configure.php         (Create Permissions / Create Menu buttons)
├── plugin_info.php / footer.php
├── files/                (12 eh_* pages → copied to usersc/ on install)
├── assets/
│   ├── includes/         (crm_* libraries + data_in_.php / show_data_.php AJAX endpoints)
│   ├── crm.js            (front-end JS)
│   ├── leads_template.csv
│   └── plugins/          (flatpickr, sweetalert2 — served from the plugin folder)
├── lang/                 (ar-EG, en-US)
└── sql/                  (database.sql, permissions.sql, us_menu_items.sql)
```

## Update

```bash
cd usersc/plugins/eh_crm
git pull origin main
```

Then open `update.php` (or `migrate.php`) as the master account — it re-deploys the
pages and re-runs the (idempotent) SQL.

## Uninstall

Removes everything the install created:
- the deployed `eh_*` pages from `usersc/`
- the CRM menu items (dropdown + links)
- the CRM page rows from the UserSpice `pages` table
- **all `plg_ehcrm_*` database tables** (⚠️ this deletes all CRM data — leads, calls, batches…)
- the language keys it added to `usersc/lang/*.php`

The 4 CRM permissions created from the Configure page are **kept** (users may still be
assigned to them) — remove them manually from **Admin → Permissions** if you want.

## License

MIT

---

<div align="center">

**Emad Helmy**

</div>

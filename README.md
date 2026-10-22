# CRM Plugin

CRM for a real-estate marketing company.

## Structure

```
usersc/plugins/eh_crm/
├── info.xml
├── functions.php        (auto-loaded; defines CRM_PLUGIN_DIR, deploys pages, loads lang)
├── activate.php / install.php / migrate.php / update.php / uninstall.php / configure.php
├── footer.php
├── assets/
│   ├── pages/           (13 pages — deployed to usersc/ on install)
│   ├── js/crm.js        (deployed to usersc/assets/crm.js)
│   └── leads_template.csv
├── ajax/                (data_in_.php, show_data_.php — deployed to usersc/scripts/)
├── lib/                 (crm_boot, crm_func, crm_admin, crm_assign, crm_csv, crm_calls, crm_reports, crm_leads, SimpleXLSXGen)
├── lang/                (ar-EG, ar-SA, en-US — 441 CRM_ keys)
└── sql/                 (001..008)
```

## Install

1. Copy the plugin folder to `usersc/plugins/eh_crm/`.
2. Run `usersc/plugins/eh_crm/install.php` (master account) — registers the plugin + deploys pages.
3. Run the SQL files in `sql/` (001..008) manually via phpMyAdmin.
4. Activate the plugin from the Plugin Manager.
5. (Optional) Configure permissions via Admin → Permissions.

## Notes

- Tables use the `eh_crm_` prefix (same convention as the rest of the CRM).
- Language keys are `CRM_*` (441 keys) — stored in `usersc/lang/*.php` (merged at runtime by `crm_load_lang()`).
- Menu items use lang keys as labels (resolved by `parseMenuLabel()`).
- Pages are deployed to `usersc/` and reference the plugin's lib via absolute path.

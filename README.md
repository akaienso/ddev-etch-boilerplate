# uartf.org — wp-content repository

This repository contains the custom and configuration code for the uartf.org WordPress site. It maps directly to the `wp-content/` directory of a WordPress installation.

## Stack

| Layer | Tool |
|-------|------|
| Theme / Builder | EtchWP (plugin + theme) |
| Custom fields | Advanced Custom Fields Pro |
| CSS framework | AutomaticCSS Pro |
| Forms | WS Form Pro |
| Grid / Facets | WP Grid Builder Pro |
| Code snippets | wPCodebox2 (preferred for custom PHP) |
| Functionality plugin | uartf-functionality (CPT registration, ACF JSON, heavier code) |
| Multilingual | WPML + String Translation + Media Translation |

> **Important:** EtchWP does not support child themes — they are incompatible with the plugin. All custom PHP goes into wPCodebox2 snippets (preferred for small additions) or the `uartf-functionality` plugin (preferred for CPT registration, hooks, and anything that needs version control).

## What's in this repo

- `plugins/uartf-functionality/` — functionality plugin with ACF JSON save path wired up
- `plugins/uartf-functionality/acf-json/` — ACF field group JSON exports (auto-saved here)
- `mu-plugins/` — must-use plugins
- `config/wpml-config.xml` — WPML field and CPT translation rules
- `config/grid-builder-export.json` — WP Grid Builder settings export

## What's NOT in this repo

- `themes/` — EtchWP is installed manually; no child theme exists or should be created
- All premium plugin directories — installed manually via zip upload
- `uploads/`, `languages/`, `cache/` — runtime-generated

## Setup

### 1. Install WordPress core

Install WordPress one directory above this repo. This repo's root becomes your `wp-content/` directory.

```
wordpress-root/
├── wp-admin/
├── wp-includes/
├── wp-config.php
└── wp-content/          ← clone this repo here
```

### 2. Clone this repo into wp-content

```bash
git clone git@github.com:uartf/uartf-org.git wp-content
```

### 3. Copy .env.example and fill in values

```bash
cp wp-content/.env.example wp-content/.env
```

### 4. Install plugins in order

Upload each premium plugin zip via **WP Admin > Plugins > Add New > Upload Plugin**, then activate the theme via **Appearance > Themes**:

| Order | Item | Notes |
|-------|------|-------|
| 1 | **Advanced Custom Fields Pro** | Must be active before EtchWP and AutomaticCSS |
| 2 | **AutomaticCSS Pro** | Configure your palette/tokens before building pages |
| 3 | **EtchWP plugin** | Install and activate the plugin first |
| 4 | **EtchWP theme** | Then activate the theme via Appearance > Themes |
| 5 | **WP Grid Builder Pro** | Set up indexing before content accumulates |
| 6 | **WS Form Pro** | No ordering constraint |
| 7 | **wPCodebox2** | Activate after other plugins so snippets can reference their hooks |
| 8 | **WPML** + String Translation + Media Translation | Always last — restructures URLs and content relationships |

### 5. Activate the functionality plugin

Go to **Plugins** and activate **UARTF Functionality**. This wires up the ACF JSON save/load path.

### 6. ACF JSON sync

Field groups are stored as JSON in `plugins/uartf-functionality/acf-json/`. After cloning on a new environment, go to **ACF > Field Groups** and click **Sync** to import them into the database.

### 7. WP Grid Builder

Import settings via **Grid Builder > Tools > Import** using `config/grid-builder-export.json`.

### 8. WPML

Run the WPML setup wizard. After configuring languages and CPT translation rules, update `config/wpml-config.xml`.

## Development workflow

1. **ACF field groups** — create/edit in WP Admin; JSON auto-saves to `plugins/uartf-functionality/acf-json/`. Commit the JSONs.
2. **Custom PHP (small)** — add as a wPCodebox2 snippet in WP Admin. Not version-controlled unless you export.
3. **Custom PHP (substantial)** — add a file under `plugins/uartf-functionality/includes/` and commit.
4. **Grid Builder changes** — export and overwrite `config/grid-builder-export.json`, then commit.
5. **WPML config changes** — update `config/wpml-config.xml` and commit.

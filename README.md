# DDEV + EtchWP WordPress Boilerplate

A `wp-content`-only repository scaffold for WordPress sites built with EtchWP, AutomaticCSS Pro, and a curated premium plugin stack. Designed for local development with [DDEV](https://ddev.com) and [OrbStack](https://orbstack.dev).

## Stack

| Layer | Tool |
|-------|------|
| Local environment | DDEV + OrbStack |
| Theme / Builder | [EtchWP](https://etchwp.com) |
| Custom fields | [Advanced Custom Fields Pro](https://www.advancedcustomfields.com) |
| CSS framework | [AutomaticCSS Pro](https://automaticcss.com) |
| Forms | [WS Form Pro](https://wsform.com) |
| Grid / Facets | [WP Grid Builder Pro](https://wpgridbuilder.com) |
| Code snippets | [wPCodebox2](https://wpcodebox.com) |
| Multilingual | [WPML](https://wpml.org) + String Translation + Media Translation |
| ACF JSON | Stored in `plugins/uartf-functionality/acf-json/` |

> **Note:** EtchWP does not support child themes — this is a hard architectural constraint, not a preference. All custom PHP goes into wPCodebox2 snippets or the included functionality plugin.

## Prerequisites

- [OrbStack](https://orbstack.dev) (or Docker Desktop)
- [DDEV](https://ddev.com/get-started/) `>= 1.23`
- [WP-CLI](https://wp-cli.org) (`brew install wp-cli`)
- [mkcert](https://github.com/FiloSottile/mkcert) (`brew install mkcert nss && mkcert -install`)
- License keys for all premium plugins listed above

## Repository Structure

This repo maps directly to `wp-content/`. Clone it into the `wp-content/` directory of a WordPress installation.

```
wp-content/
├── plugins/
│   └── uartf-functionality/        # Functionality plugin (ACF JSON save path wired up)
│       ├── acf-json/               # ACF field group JSON exports — commit these
│       └── includes/               # Custom PHP includes
├── mu-plugins/                     # Must-use plugins
└── config/
    ├── wpml-config.xml             # WPML translation rules — update as CPTs are added
    └── grid-builder-export.json    # WP Grid Builder settings export
```

## Setup

### 1. Create the DDEV project

```bash
mkdir my-project && cd my-project
ddev config --project-type=wordpress --project-name=my-project --docroot=.
ddev start
ddev auth ssh
```

### 2. Download WordPress core

```bash
ddev wp core download
```

### 3. Clone this repo as wp-content

```bash
rm -rf wp-content
git clone git@github.com:akaienso/ddev-etch-boilerplate.git wp-content
```

### 4. Install WordPress

DDEV creates `wp-config.php` automatically. Run the install:

```bash
ddev wp core install \
  --url=https://my-project.ddev.site \
  --title="My Project" \
  --admin_user=admin \
  --admin_email=you@example.com \
  --prompt=admin_password
```

### 5. Fix wp-content permissions

DDEV needs write access to install themes and plugins:

```bash
ddev ssh
chmod 755 /var/www/html/wp-content
mkdir -p /var/www/html/wp-content/themes && chmod 755 /var/www/html/wp-content/themes
mkdir -p /var/www/html/wp-content/plugins && chmod 755 /var/www/html/wp-content/plugins
mkdir -p /var/www/html/wp-content/uploads && chmod 755 /var/www/html/wp-content/uploads
exit
```

### 6. Disable debug output

```bash
ddev wp config set WP_DEBUG false --raw
```

### 7. Install premium plugins in order

Upload each zip via **WP Admin > Plugins > Add New > Upload Plugin**. Order matters.

| # | Item | Location |
|---|------|----------|
| 1 | **Advanced Custom Fields Pro** | Plugins > Upload |
| 2 | **AutomaticCSS Pro** | Plugins > Upload |
| 3 | **EtchWP plugin** | Plugins > Upload |
| 4 | **EtchWP theme** | Appearance > Themes > Upload, then activate |
| 5 | **WP Grid Builder Pro** | Plugins > Upload |
| 6 | **WS Form Pro** | Plugins > Upload |
| 7 | **wPCodebox2** | Plugins > Upload |
| 8 | **WPML Multilingual CMS** | Plugins > Upload |
| 9 | **WPML String Translation** | Plugins > Upload |
| 10 | **WPML Media Translation** | Plugins > Upload, then run WPML setup wizard |

> Install WPML last. It restructures URL patterns and content relationships — installing it mid-build causes rework.

### 8. Activate the functionality plugin

Go to **Plugins** and activate **UARTF Functionality**. This wires up the ACF JSON save/load path so field group exports land in version control automatically.

## Development Workflow

### ACF field groups
Create and edit field groups in WP Admin. JSON files auto-save to `plugins/uartf-functionality/acf-json/`. Commit them.

On a new environment, go to **ACF > Field Groups** and click **Sync** to import from JSON.

### Custom PHP
- **Small additions** — add as a wPCodebox2 snippet in WP Admin
- **Substantial code** — add a file to `plugins/uartf-functionality/includes/` and commit

### WP Grid Builder
After configuring grids and facets, export via **Grid Builder > Tools > Export**. Overwrite `config/grid-builder-export.json` and commit.

Import on a new environment via **Grid Builder > Tools > Import**.

### WPML
Update `config/wpml-config.xml` with field and CPT translation rules as you add custom post types and ACF fields. Commit after each update.

## Snapshots

Take a DDEV snapshot after reaching a stable state:

```bash
ddev snapshot --name=my-snapshot
```

Restore with:

```bash
ddev snapshot restore my-snapshot
```

Snapshots capture the database. Files are captured by git. Together they give you a complete restore point.

## License

This boilerplate scaffold is MIT licensed. The premium plugins it references are commercial products — you must hold valid licenses for each.

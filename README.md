# Simpull Plugin Developer Documentation

## Overview

Simpull plugins use a custom update system powered by GitHub Releases. This ensures a license-free, frictionless experience for users while giving developers full control over release workflows, changelogs, and deployment artifacts.

---

## 1. Including the Simpull Updater

In your plugin’s root directory:

1. Copy the `SimpullUpdater.php` file into an `includes/` folder (or similar).
2. Include and instantiate the updater in your main plugin file:

```php
require_once plugin_dir_path(__FILE__) . 'includes/SimpullUpdater.php';

new SimpullUpdater(__FILE__, 'your-plugin-slug/your-plugin.php', 'simpull/your-plugin-repo');
```

* `your-plugin-slug/your-plugin.php` → the full path to the plugin file in `wp-content/plugins`
* `simpull/your-plugin-repo` → your GitHub `org/repo`

> ⚠️ Make sure your plugin's version number in the plugin header matches the latest release tag on GitHub (e.g., `v1.2.3`).

---

## 2. Creating a GitHub Release

When you're ready to release:

1. Ensure your main branch is up to date.
2. Run any necessary build steps (see GitHub Actions below).
3. Tag your release with the format `vX.Y.Z` (e.g., `v1.0.0`).
4. Use the **release body** as your changelog (Markdown supported).
5. Upload a ZIP file of the plugin (`your-plugin.zip`) as the release asset.

---

## 3. Changelog Display

The changelog shown to users in the WordPress admin is pulled from the GitHub Release body. Use clear headings and bullet points.

**Example:**

```markdown
### New
- Added admin settings page

### Fixed
- Compatibility with WordPress 6.5
```

---

## 4. Stats Dashboard on simpull.co

The main site runs a WordPress plugin called **Simpull Plugin Release Stats** that:

* Lists all plugins by repo slug
* Pulls latest version + download count + changelog

To add a plugin:

* Edit `simpull_stats_get_plugins()` and append `'simpull/your-plugin'`

---

## 5. GitHub Actions (Optional but Recommended)

To build and package plugins (e.g., compile SCSS, run `composer install`, zip the output), use this GitHub Action:

```yaml
name: Build and Release
on:
  push:
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Install Dependencies
        run: |
          npm ci
          composer install --no-dev --optimize-autoloader
      - name: Zip Plugin
        run: |
          zip -r your-plugin.zip . -x '*.git*' '*.github*'
      - name: Upload Release Asset
        uses: softprops/action-gh-release@v1
        with:
          files: your-plugin.zip
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

> This automatically builds and uploads the ZIP file whenever you push a tag like `v1.0.0`.

---

## 6. Legal & API Considerations

Using GitHub Releases as your plugin update delivery method is fully compliant with GitHub’s Terms of Service, provided that:

* You are publishing your own content.
* You do not exceed GitHub’s API rate limits (60/hour unauthenticated, 5,000/hour authenticated).
* You are not using GitHub as a general-purpose CDN or abusing their infrastructure.

**Best Practices:**

* Cache GitHub API responses using transients (e.g. for 6–12 hours).
* Use a GitHub token for authenticated requests (increases rate limits).
* Never commit private tokens to public repositories.

---

## 7. Plugin Scaffolding with `simpull-plugin-init`

To streamline plugin development, Simpull provides a CLI tool that scaffolds new plugin projects with best practices out of the box.

### How to Use:

```bash
npx github:zao-web/simpull-plugin-init my-plugin-name
```

This will create a new folder `my-plugin-name/` with:

* ✅ `my-plugin-name.php` — modern PHP 8.1+ main plugin file
* ✅ `includes/SimpullUpdater.php` — pulled from GitHub
* ✅ `src/Plugin.php` — namespaced plugin bootstrap
* ✅ `composer.json` and `package.json`
* ✅ `.github/workflows/release.yml` — GitHub Actions workflow

The updater is pulled automatically from:

```
https://raw.githubusercontent.com/zao-web/simpull-updater/main/SimpullUpdater.php
```

No Composer packaging is required. This ensures the plugin is lightweight and GitHub-native.

---

## 8. Summary Checklist

* [ ] Scaffold plugin with `npx github:zao-web/simpull-plugin-init <name>`
* [ ] Include `SimpullUpdater` in `includes/`
* [ ] Tag release as `vX.Y.Z`
* [ ] Match plugin version with tag
* [ ] Write changelog in release body
* [ ] Upload built `.zip` asset to release
* [ ] Add repo to simpull.co stats dashboard
* [ ] (Optional) Enable GitHub Actions workflow

---

For questions, reach out in the Simpull dev Slack or message @Justin.

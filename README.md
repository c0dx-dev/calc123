# Calc123

[Русская версия](README.ru.md)

A lightweight formula-based calculator plugin for WordPress.

Current plugin version: **1.3**.

Calc123 lets an administrator create calculators, define input variables and formulas, and embed each calculator in a page or post with a shortcode. Formula execution uses a tokenizer, Reverse Polish Notation and a stack evaluator instead of `eval()`.

## Project status

Version 1.3 is the current cleaned public baseline. The older experimental licensing layer is intentionally not included in this repository.

The plugin is working in its current scope. Further refactoring and feature expansion are planned only when justified by practical use, testing or compatibility requirements.

## Features

- Multiple calculators managed from the WordPress admin area.
- Create, edit, duplicate and delete calculators.
- Shortcode output: `[calc123 id="N"]`.
- Formula operators: `+`, `-`, `*`, `/`, `^`, parentheses and comparisons.
- Supported functions: `IF(cond,a,b)`, `MAX(a,b)`, `MIN(a,b)` and `ROUND(a,decimals)`.
- Number and select variables with optional hidden output.
- Select options in `Label:Value` format.
- Variable ordering and field widths: `1/1`, `1/2`, `1/3` and `1/4`.
- Optional text or currency marker before or after the result.
- Optional custom wrapper ID and CSS class.
- Optional simple math captcha.
- AJAX calculation without page reload.
- Client-side and server-side validation.
- No `eval()`, Composer packages, npm packages or external services.
- No custom database tables; calculators are stored through the WordPress options API.

## Tested environment

The current public baseline has been manually tested on:

- WordPress 7.0
- PHP 8.4
- MySQL 5.7

Other WordPress, PHP and database versions may work, but have not all been tested. Test the plugin on a staging copy before using it on a production website.

## Installation

### Installable release ZIP

Use the `calc123.zip` asset attached to a GitHub Release when one is available. GitHub's automatically generated **Source code** archives contain the entire repository and are not intended to be uploaded directly through the WordPress plugin installer.

### Manual installation from the repository

1. Copy the `calc123` directory to `/wp-content/plugins/`.
2. Activate **Calc123** in **Plugins → Installed Plugins**.
3. Open **Calc123** in the main WordPress admin menu.
4. Create a calculator, define its variables and enter a formula.
5. Add the generated shortcode to a page or post.

## Basic usage

Create a calculator in the WordPress admin area and place its shortcode in content:

```text
[calc123 id="1"]
```

Example formula:

```text
IF(distance > 100, 1000 + weight * 50, 500 + weight * 30)
```

Example select options:

```text
Standard:10000,Premium:20000
```

Variable codes should use Latin letters, digits and underscores so they can be referenced predictably in formulas.

## Bundled help

The `calc123/about.html` file contains the help and examples displayed in the plugin admin modal. The accompanying `.htaccess` blocks direct HTTP access to this file on Apache-based servers, while WordPress loads it internally through PHP.

## Reporting issues and contributing

Bug reports and focused improvement proposals are welcome through GitHub Issues. Include:

- plugin, WordPress and PHP versions;
- the calculator formula and variable definitions using non-sensitive sample data;
- the shortcode and relevant wrapper settings;
- minimal steps to reproduce;
- expected and actual behavior;
- relevant browser-console or PHP log messages with private data removed.

See [CONTRIBUTING.md](CONTRIBUTING.md) before preparing a pull request.

## Security

Do not publish credentials, private URLs, customer data, database exports or complete production logs in issues. Use synthetic calculator values and formulas when reporting problems. See [SECURITY.md](SECURITY.md) for the reporting policy.

## Documentation

- [Russian README](README.ru.md)
- [Changelog](CHANGELOG.md)
- the bundled `calc123/about.html` help document

## Repository layout

```text
README.md
README.ru.md
LICENSE
CHANGELOG.md
CONTRIBUTING.md
SECURITY.md
.gitattributes
.gitignore
.github/
  ISSUE_TEMPLATE/
  PULL_REQUEST_TEMPLATE.md
calc123/
  .htaccess
  about.html
  calc123.php
  calc123-frontend.css
  calc123-frontend.js
```

Installable ZIP archives are release artifacts and are not stored in the source tree.

## Disclaimer

This plugin is provided as is, without any warranty. Test it on a staging site before production use. The authors and maintainers are not responsible for errors, data loss, downtime or other damage caused by using the plugin.

## License

MIT. See [LICENSE](LICENSE).

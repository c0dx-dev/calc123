# Calc123

[Русская версия](README.ru.md)

Calc123 is a lightweight formula-based calculator plugin for WordPress.

It lets you create calculators in the WordPress admin area, define variables, enter a formula in familiar math notation, and embed the result on a page with a shortcode.

The project is kept intentionally small: no visual builder, no external service dependency, and no `eval()` for formula execution.

## Features

- Multiple calculators managed from the WordPress admin area.
- Shortcode output: `[calc123 id="N"]`.
- Formula operators: `+`, `-`, `*`, `/`, `^`, parentheses and comparisons.
- Supported functions: `IF(cond,a,b)`, `MAX(a,b)`, `MIN(a,b)`, `ROUND(a,decimals)`.
- Variable types: number, select list and hidden fields.
- Optional result suffix/prefix text, for example currency labels.
- Optional simple math captcha before calculation.
- AJAX calculation without page reload.
- Client-side and server-side validation.
- Formula parsing through tokenizer → RPN → stack evaluator, without `eval()`.

## Installation

Upload the `calc123` plugin folder to:

```text
/wp-content/plugins/calc123/
```

Then activate **Calc123** in **Plugins → Installed Plugins**.

The plugin folder should contain:

```text
calc123.php
calc123-frontend.js
calc123-frontend.css
about.html
.htaccess
```

The bundled `about.html` file is used by the plugin admin screen for the help/instruction modal. The `.htaccess` file blocks direct HTTP access to that file on Apache-based servers. The plugin still reads it internally from PHP.

## Usage

Create a calculator in **Calc123** admin menu, define variables and a formula, then place the shortcode on a page or post:

```text
[calc123 id="1"]
```

Example formula:

```text
IF(distance > 100, 1000 + weight * 50, 500 + weight * 30)
```

Example select options format:

```text
Standard:10000,Premium:20000
```

## Notes

This repository is a cleaned public baseline of the plugin. The older experimental licensing layer is not included.

Compatibility should be tested on a staging site before production use.

## License

MIT. See [LICENSE](LICENSE).

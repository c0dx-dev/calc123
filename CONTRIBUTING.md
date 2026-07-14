# Contributing

Contributions, reproducible bug reports and focused improvement proposals are welcome.

Calc123 is maintained as a compact WordPress plugin. Changes should preserve the current scope and avoid unnecessary architectural complexity or external dependencies.

## Before opening an issue

Check the current documentation and existing issues first. Include enough information to reproduce the problem:

- plugin version;
- WordPress and PHP versions;
- calculator formula;
- variable names, types and sample values;
- shortcode and relevant wrapper settings;
- minimal steps to reproduce;
- expected and actual behavior;
- relevant browser-console or PHP log output with private data removed.

Use synthetic calculator values. Do not publish customer data, credentials, private URLs or complete production logs.

Issues may be written in English or Russian.

## Pull requests

Keep pull requests focused on one problem or improvement. Large refactors should be discussed in an issue before implementation.

Please preserve the following constraints:

- keep the plugin compatible with the currently documented WordPress and PHP baseline unless a version change is explicitly discussed;
- use the existing `calc123` identifiers and storage keys unless a migration is planned;
- do not use `eval()` for formula execution;
- preserve the tokenizer → RPN → stack evaluator approach unless an alternative is reviewed first;
- do not add Composer packages, npm dependencies or external services without prior discussion;
- do not add custom database tables unless there is a demonstrated need and a migration plan;
- use WordPress capability checks, nonces, sanitization and output escaping for administrative and AJAX actions;
- keep frontend JavaScript framework-free;
- preserve existing calculators stored in the WordPress options API;
- update English and Russian documentation when behavior changes.

Do not change the plugin version or prepare release archives in a pull request unless explicitly requested. Release versioning and packaging are handled by the maintainers.

## Testing

At minimum, check:

1. PHP syntax with `php -l`.
2. JavaScript syntax with `node --check` when Node.js is available.
3. Plugin activation on a clean WordPress installation.
4. Creating, editing, duplicating and deleting a calculator.
5. Saving number and select variables.
6. Variable ordering, hidden fields and field widths.
7. Formula parsing and validation.
8. AJAX calculation with valid and invalid input.
9. Optional captcha behavior.
10. Shortcode output and custom wrapper ID/class behavior.
11. No PHP warnings or browser-console errors.

Use synthetic values and formulas in tests, commits, issues and pull requests.

## License

By contributing, you agree that your contribution may be distributed under the repository's MIT License.

# Contributing to OpenKOS

Thanks for helping improve OpenKOS. Before participating, please read the [Code of Conduct](CODE_OF_CONDUCT.md).

## Get started

1. Check existing issues for related work, or open an issue before starting a large change.
2. Fork the repository and create a focused branch.
3. Follow the [README setup instructions](README.md#quick-start).
4. Keep changes focused and follow the existing conventions.

For plugin work, read the [Plugin Development guide](docs/platform.md). For architectural changes, read the [architecture guide](docs/architecture.md) and include an ADR when the decision has lasting trade-offs.

## Verify changes

Run the checks relevant to your change before opening a pull request:

```bash
php artisan test --compact
npm run lint:check
npm run format:check
npm run types:check
```

Run `vendor/bin/pint --dirty --format agent` after changing PHP files.

## Pull requests

- Explain the problem and the approach taken.
- Include or update tests for behavior changes.
- Mention any migration, configuration, or deployment steps.
- Keep unrelated cleanup out of the pull request.

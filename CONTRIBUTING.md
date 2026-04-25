# Contributing

This package is intentionally narrow.

Good contributions make Squish parsing more correct. That is the job.

## Scope

Good fits:

- Squish frame or header parsing fixes
- body and control-line parsing fixes for real FTN messages
- charset handling that flows through `golded-dev/laravel-ftn`
- safer handling of malformed or partial Squish files
- tests using small fixtures or real-world edge cases
- documentation fixes

Usually not a fit:

- `.MSG`, JAM, Hudson, packet, or area discovery readers
- Squish writers or repair tools
- Laravel service providers, facades, config publishing, or app bootstrapping
- database models or migrations
- importer orchestration
- broad abstractions without another reader proving the need

If a change needs a paragraph to justify why it belongs here, it probably belongs somewhere else.

## Development Setup

```bash
composer install
```

## Quality Gates

Run the full suite before opening a pull request:

```bash
composer test:all
```

For focused work:

```bash
composer test
composer test:types
composer test:refactor
```

## Coding Style

- Use strict types.
- Keep `SquishReader` small and literal.
- Add explicit return types.
- Prefer boring parsing over clever parsing.
- Do not edit `vendor/`.
- Keep `composer.lock` in sync when `composer.json` changes.

## Public API Changes

Be careful with these:

- `SquishReader::read()` signature
- parsed field semantics
- charset fallback behavior
- dependency constraints

Those can be breaking changes. Say so plainly in the pull request and changelog.

## Tests

Add tests for behavior, especially around:

- malformed or short Squish files
- upper-case and lower-case extensions
- packed Squish dates
- frame and index offsets
- `MSGID` extraction
- synthetic ID fallback
- charset conversion

Binary parser bugs are impressively dull until they eat an archive. Test them.

## Pull Requests

Use a clear title and explain:

- what changed
- which Squish behavior it fixes
- whether public API changed
- which commands passed

Keep pull requests focused. Unrelated cleanup can wait.

## Security Reports

Do not report security issues in public tickets. Use the private reporting path in [SECURITY.md](SECURITY.md).

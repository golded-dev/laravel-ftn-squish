# Agent Instructions

## Project Shape

- This is `golded-dev/laravel-ftn-squish`, a small PHP 8.4 library.
- Purpose: read FTN/FidoNet Squish message bases and return `Golded\Ftn\ParsedMessage` objects.
- Namespace: `Golded\Ftn\Squish\`.
- Runtime dependency: `golded-dev/laravel-ftn` for contracts, DTOs, charset detection, text helpers, and control-line extraction.
- Despite the name, this is not a Laravel app. Do not add service providers, config publishing, facades, container assumptions, or other framework furniture unless the package explicitly grows that surface.

## Boundaries

- Keep this package focused on Squish `.SQD` and `.SQI` files.
- `.MSG`, JAM, Hudson, packet parsing, and area discovery belong in other packages unless the task explicitly says otherwise.
- `SquishReader` should stay a concrete reader, not an importer pipeline.
- Avoid runtime dependencies beyond `golded-dev/laravel-ftn` unless there is a real parsing reason.
- Do not move shared parsing helpers from `golded-dev/laravel-ftn` into this package.

## Coding Style

- Use strict types in every PHP file.
- Follow the existing style: final classes, explicit return types, small private methods, and literal names.
- Keep parsing readable. Binary formats are already hostile enough.
- Preserve public API compatibility unless the task is explicitly a breaking change.
- Prefer adding focused tests over adding abstraction.

## Squish Notes

- Squish uses byte-level `.SQD` data files and `.SQI` index files. Offsets matter.
- Missing `.SQD` or `.SQI` files should yield no messages, not explode.
- Preserve case-insensitive extension lookup for Squish files.
- Skip invalid frame IDs, non-normal frame types, short reads, and unusable index offsets.
- Header strings are fixed-width binary fields. Convert them through `Text::toUtf8()`.
- Message bodies may contain FTN control lines starting with `\x01`. Keep meaningful kludges and body content.
- Treat `MSGID` as external identity when present; use synthetic IDs only as fallback.
- Old encodings are part of the domain, not a bug. Use `CharsetDetector` and `ReaderOptions` instead of assuming UTF-8.

## Tests And Quality Gates

- Run the focused test when touching the reader:
  - `vendor/bin/pest tests/Feature/SquishReaderTest.php`
- Run the full suite before handing off code changes:
  - `composer test:all`
- The Composer scripts are:
  - `composer test`
  - `composer test:types`
  - `composer test:refactor`
  - `composer test:all`
- PHPStan is configured at max level through `phpstan.neon`.
- Rector uses `odinns/coding-style`; do not fight it by hand-formatting around it.

## Dependency And File Hygiene

- Do not edit `vendor/`.
- Do not commit generated caches or local artifacts.
- Keep `composer.lock` in sync if `composer.json` changes.
- Do not add a local path repository to `composer.json`.
- Keep `golded-dev/laravel-ftn` on a stable constraint for public releases.
- `CLAUDE.md` and `GEMINI.md` should remain symlinks to `AGENTS.md`.

## When Changing Public Behavior

- Think about downstream importers before changing yielded message semantics.
- Changing `SquishReader::read()` signature, parsed field meanings, charset fallback behavior, or dependency constraints can be breaking.
- Add tests around behavior, not just structure.

## Review Bias

- Watch for scope creep. This repo should stay a thin Squish reader.
- Watch for encoding assumptions. UTF-8-only thinking will lie to you here.
- Watch for importer logic trying to sneak in. That part smells a bit; keep it outside.

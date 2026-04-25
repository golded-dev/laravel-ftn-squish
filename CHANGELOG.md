# Changelog

Notable changes to `golded-dev/laravel-ftn-squish`.

This project uses semantic versioning.

## 1.0.0 - 2026-04-25

Initial stable release.

### Added

- Add Squish message-base reader for `.sqd`/`.SQD` and `.sqi`/`.SQI` files.
- Add parsing for Squish index records, message frames, header names, subject, posted date, raw attributes, and message body.
- Add reply-to and first-reply message number extraction.
- Add charset detection through `golded-dev/laravel-ftn`.
- Add `MSGID` extraction with stable synthetic IDs as fallback.
- Add Pest, PHPStan, and Rector quality gates.
- Add public package documentation, security policy, code of conduct, archive hygiene, and CI workflow.

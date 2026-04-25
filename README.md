# Laravel FTN Squish

FTN/FidoNet Squish message-base reader for PHP 8.4.

This package reads Squish `.SQD` and `.SQI` files and returns normalized `ParsedMessage` objects from `golded-dev/laravel-ftn`.

It does not write Squish files, repair broken message bases, discover areas, read `.MSG`, JAM, Hudson, or packet files, or add Laravel framework bootstrapping. The package name says Laravel because it belongs to the GoldED.dev Laravel package family. The runtime code is plain PHP.

## Installation

```bash
composer require golded-dev/laravel-ftn-squish:^1.0
```

Requires PHP 8.4+.

## Reading A Message Area

Pass the Squish base path without an extension:

```php
<?php

declare(strict_types=1);

use Golded\Ftn\Squish\SquishReader;

$reader = new SquishReader();

foreach ($reader->read('/path/to/messages/SQUISH/STEST1') as $message) {
    echo $message->msgno.PHP_EOL;
    echo $message->fromName.' -> '.$message->toName.PHP_EOL;
    echo $message->subject.PHP_EOL;
    echo $message->bodyText.PHP_EOL;
}
```

`SquishReader::read()` looks for `.sqd` or `.SQD` and `.sqi` or `.SQI` next to the base path. Missing or unreadable files produce an empty result.

## Reader Options

```php
use Golded\Ftn\ReaderOptions;
use Golded\Ftn\Squish\SquishReader;

$messages = new SquishReader()->read(
    path: '/path/to/messages/SQUISH/NETMAIL',
    options: new ReaderOptions(fallbackCharset: 'CP437'),
);
```

The fallback charset is used when the message body does not declare a usable FTN charset control line. The default comes from `golded-dev/laravel-ftn`.

## What Gets Parsed

The reader extracts:

- message number from the Squish index
- from name
- to name
- subject
- body text converted to UTF-8
- raw attribute bitfield
- posted date when the packed Squish date can be parsed
- external ID from `MSGID` when present
- reply-to message number
- first reply message number

When a message has no `MSGID`, the reader creates a stable synthetic ID from the parsed message fields. Old message bases are rarely polite. Downstream imports still need a handle.

## What You Do Not Get

- No area discovery.
- No packet parsing.
- No `.MSG`, JAM, Hudson, or other message-base readers.
- No Squish writer or repair tool.
- No Laravel service provider.
- No database models.
- No queues, commands, config publishing, or framework bootstrapping.

Pair this package with your own source locator or import pipeline.

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer test:types
```

Run Rector dry-run:

```bash
composer test:refactor
```

Run everything:

```bash
composer test:all
```

## Versioning

This package starts at `1.0.0` and uses semantic versioning.

Versions come from Git tags. Do not add a `version` field to `composer.json`.

Breaking changes include:

- changing `SquishReader::read()` behavior in a way that drops messages previously yielded
- changing parsed field semantics
- changing charset fallback behavior
- changing the required PHP version
- changing the `golded-dev/laravel-ftn` public contract this reader returns

Adding support for more Squish header fields is usually a minor release when existing fields keep their meaning.

## Contributing

Contributions are welcome when they make Squish parsing more correct without turning this into a framework package. See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Do not report security issues in public tickets. See [SECURITY.md](SECURITY.md).

## Code Of Conduct

Be direct, useful, and not a pain on purpose. See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).

<?php

use Golded\Ftn\Squish\SquishReader;
use Golded\Ftn\ParsedMessage;

function squishFixtureBase(): string
{
    $base = sys_get_temp_dir().'/laravel-ftn-squish-tests/stest1';

    if (! is_dir(dirname($base))) {
        mkdir(dirname($base), recursive: true);
    }

    $control = "\x01MSGID: 2:230/150 12345678\x00";
    $body = "I want this Squish body preserved.\r\n";
    $header = squishHeader(
        fromName: 'Odinn Sorensen',
        toName: 'Gregory ThroatWobbler',
        subject: 'Keep on the good work..',
        dateWritten: squishDate('2024-01-01 12:34:56'),
        replyTo: 7,
        firstReply: 9,
    );
    $frameOffset = 256;
    $frame = squishFrame(totsize: strlen($header.$control.$body), controlSize: strlen($control));

    file_put_contents($base.'.sqd', str_repeat("\0", $frameOffset).$frame.$header.$control.$body);
    file_put_contents($base.'.sqi', pack('lVV', $frameOffset, 1, 0));

    return $base;
}

it('reads Squish messages', function (): void {
    $messages = array_values(iterator_to_array(new SquishReader()->read(squishFixtureBase())));
    $first = firstSquishMessage($messages);

    expect($first->fromName)->toBe('Odinn Sorensen')
        ->and($first->toName)->toBe('Gregory ThroatWobbler')
        ->and($first->subject)->toBe('Keep on the good work..')
        ->and($first->bodyText)->toContain('Squish body preserved')
        ->and($first->postedAt)->not->toBeNull()
        ->and($first->externalId)->toBe('2:230/150 12345678')
        ->and($first->replyToMsgno)->toBe(7)
        ->and($first->reply1stMsgno)->toBe(9);
});

/**
 * @param list<ParsedMessage> $messages
 */
function firstSquishMessage(array $messages): ParsedMessage
{
    if ($messages === []) {
        throw new RuntimeException('Expected at least one parsed Squish message.');
    }

    return $messages[0];
}

function squishFrame(int $totsize, int $controlSize): string
{
    return pack(
        'VllVVVvv',
        0xAFAE4453,
        0,
        0,
        $totsize,
        $totsize,
        $controlSize,
        0,
        0,
    );
}

function squishHeader(
    string $fromName,
    string $toName,
    string $subject,
    int $dateWritten,
    int $replyTo,
    int $firstReply,
): string {
    return pack('V', 0)
        .str_pad($fromName, 36, "\0")
        .str_pad($toName, 36, "\0")
        .str_pad($subject, 72, "\0")
        .str_repeat("\0", 16)
        .pack('V', $dateWritten)
        .pack('V', 0)
        .pack('v', 0)
        .pack('V', $replyTo)
        .pack('V9', $firstReply, 0, 0, 0, 0, 0, 0, 0, 0)
        .pack('V', 0)
        .str_repeat("\0", 20);
}

function squishDate(string $date): int
{
    $postedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);

    if (! $postedAt instanceof DateTimeImmutable) {
        throw new RuntimeException('Invalid Squish fixture date.');
    }

    return (int) $postedAt->format('d')
        | ((int) $postedAt->format('m') << 5)
        | (((int) $postedAt->format('Y') - 1980) << 9)
        | (intdiv((int) $postedAt->format('s'), 2) << 16)
        | ((int) $postedAt->format('i') << 21)
        | ((int) $postedAt->format('H') << 27);
}

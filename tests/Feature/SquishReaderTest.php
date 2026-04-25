<?php

use Golded\Ftn\Squish\SquishReader;
use Golded\Ftn\ParsedMessage;

function squishFixtureBase(): string
{
    return __DIR__.'/../../../archive/messages/SQUISH/TEST/STEST1';
}

it('reads real Squish messages', function (): void {
    $messages = array_values(iterator_to_array(new SquishReader()->read(squishFixtureBase())));
    $first = firstSquishMessage($messages);

    expect($first->fromName)->toBe('Odinn Sorensen')
        ->and($first->toName)->not->toBeEmpty()
        ->and($first->postedAt)->not->toBeNull()
        ->and($first->externalId)->not->toStartWith('hash:');
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

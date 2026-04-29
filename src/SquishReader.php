<?php

declare(strict_types=1);

namespace Golded\Ftn\Squish;

use DateTimeImmutable;
use Golded\Ftn\Contracts\MessageBaseReader;
use Golded\Ftn\MessageProvenance;
use Golded\Ftn\ParsedMessage;
use Golded\Ftn\ReaderOptions;
use Golded\Ftn\Support\CharsetDetector;
use Golded\Ftn\Support\ControlLines;
use Golded\Ftn\Support\Text;

final class SquishReader implements MessageBaseReader
{
    private const int SQFRAMEID = 0xAFAE4453;
    private const int BASE_SIZE = 256;
    private const int FRAME_SIZE = 28;
    private const int HDR_SIZE = 238;
    private const int IDX_SIZE = 12;

    /**
     * @return iterable<ParsedMessage>
     */
    public function read(string $path, ?ReaderOptions $options = null): iterable
    {
        $options ??= new ReaderOptions();
        $sqdPath = $this->findFile($path, 'sqd');
        $sqiPath = $this->findFile($path, 'sqi');

        if ($sqdPath === null || $sqiPath === null) {
            return;
        }

        $dataHandle = fopen($sqdPath, 'rb');
        $indexHandle = fopen($sqiPath, 'rb');

        if ($dataHandle === false || $indexHandle === false) {
            return;
        }

        try {
            yield from $this->readMessages($dataHandle, $indexHandle, $sqdPath, $options);
        } finally {
            fclose($dataHandle);
            fclose($indexHandle);
        }
    }

    /**
     * @param resource $dataHandle
     * @param resource $indexHandle
     *
     * @return iterable<ParsedMessage>
     */
    private function readMessages($dataHandle, $indexHandle, string $sourcePath, ReaderOptions $options): iterable
    {
        $baseRaw = fread($dataHandle, self::BASE_SIZE);

        if ($baseRaw === false || strlen($baseRaw) < self::BASE_SIZE) {
            return;
        }

        while (! feof($indexHandle)) {
            $indexRaw = fread($indexHandle, self::IDX_SIZE);

            if ($indexRaw === false || strlen($indexRaw) < self::IDX_SIZE) {
                break;
            }

            $index = $this->unpackIndex($indexRaw);

            if ($index === null) {
                continue;
            }

            if ($index['offset'] <= 0) {
                continue;
            }

            fseek($dataHandle, $index['offset']);
            $frameRaw = fread($dataHandle, self::FRAME_SIZE);
            if ($frameRaw === false) {
                continue;
            }
            if (strlen($frameRaw) < self::FRAME_SIZE) {
                continue;
            }

            $frame = $this->unpackFrame($frameRaw);
            if ($frame === null) {
                continue;
            }
            if ($frame['id'] !== self::SQFRAMEID) {
                continue;
            }
            if ($frame['type'] !== 0) {
                continue;
            }

            $headerRaw = fread($dataHandle, self::HDR_SIZE);
            if ($headerRaw === false) {
                continue;
            }
            if (strlen($headerRaw) < self::HDR_SIZE) {
                continue;
            }

            $header = $this->unpackHeader($headerRaw);

            if ($header === null) {
                continue;
            }

            $controlRaw = $frame['ctlsize'] > 0 ? fread($dataHandle, $frame['ctlsize']) : '';
            $controlRaw = $controlRaw === false ? '' : $controlRaw;
            $textSize = $frame['totsize'] - self::HDR_SIZE - $frame['ctlsize'];
            $bodyRaw = $textSize > 0 ? fread($dataHandle, $textSize) : '';
            $bodyRaw = $bodyRaw === false ? '' : $bodyRaw;
            $charset = CharsetDetector::detect($controlRaw.$bodyRaw, $options->fallbackCharset);
            $controlText = $this->parseControlBlock($controlRaw);
            $body = $controlText.Text::parseBody($bodyRaw);
            $replies = $this->unpackReplies($header['replies']);
            $reply1stMsgno = $replies[0] ?? 0;
            $fromName = Text::toUtf8($header['from'], $charset);
            $toName = Text::toUtf8($header['to'], $charset);
            $subject = Text::toUtf8($header['subj'], $charset);
            $postedAt = $this->parseDate($header['date_written']);

            yield new ParsedMessage(
                msgno: $index['msgno'],
                fromName: $fromName,
                toName: $toName,
                subject: $subject,
                bodyText: Text::toUtf8($body, $charset),
                attributesRaw: $header['attr'],
                postedAt: $postedAt,
                externalId: ControlLines::extractMsgid($controlRaw)
                    ?? Text::syntheticId($fromName, $toName, $subject, $postedAt?->format(DATE_ATOM), Text::parseBody($bodyRaw)),
                replyToMsgno: $header['replyto'] ?: null,
                reply1stMsgno: $reply1stMsgno ?: null,
                controlLines: ControlLines::parseMessage($controlText.Text::parseBody($bodyRaw)),
                provenance: new MessageProvenance(
                    sourceType: 'squish',
                    sourcePath: $sourcePath,
                    sourceId: (string) $index['msgno'],
                    sourceOffset: $index['offset'],
                ),
            );
        }
    }

    private function parseControlBlock(string $raw): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length && $raw[$offset] === "\x01" && isset($raw[$offset + 1]) && $raw[$offset + 1] !== "\x00") {
            $isArea = substr($raw, $offset + 1, 5) === 'AREA:';
            $offset++;
            $text = '';

            while ($offset < $length && $raw[$offset] !== "\x01" && $raw[$offset] !== "\x00") {
                $text .= $raw[$offset];
                $offset++;
            }

            if (! $isArea && $text !== '') {
                $result .= "\x01".$text."\n";
            }
        }

        return $result;
    }

    private function parseDate(int $raw): ?DateTimeImmutable
    {
        if ($raw === 0) {
            return null;
        }

        $day = $raw & 0x1F;
        $month = ($raw >> 5) & 0x0F;
        $year = 1980 + (($raw >> 9) & 0x7F);
        $second = (($raw >> 16) & 0x1F) * 2;
        $minute = ($raw >> 21) & 0x3F;
        $hour = ($raw >> 27) & 0x1F;

        if ($month < 1 || $month > 12 || $day < 1) {
            return null;
        }

        return DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
        ) ?: null;
    }

    private function findFile(string $basePath, string $extension): ?string
    {
        foreach ([$extension, strtoupper($extension)] as $candidate) {
            $path = "{$basePath}.{$candidate}";

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{offset: int, msgno: int}|null
     */
    private function unpackIndex(string $raw): ?array
    {
        $index = unpack('loffset/Vmsgno/Vhash', $raw);

        if ($index === false) {
            return null;
        }

        return [
            'offset' => $this->integer($index, 'offset'),
            'msgno' => $this->integer($index, 'msgno'),
        ];
    }

    /**
     * @return array{id: int, totsize: int, ctlsize: int, type: int}|null
     */
    private function unpackFrame(string $raw): ?array
    {
        $frame = unpack('Vid/lnext/lprev/Vlength/Vtotsize/Vctlsize/vtype/vreserved', $raw);

        if ($frame === false) {
            return null;
        }

        return [
            'id' => $this->integer($frame, 'id'),
            'totsize' => $this->integer($frame, 'totsize'),
            'ctlsize' => $this->integer($frame, 'ctlsize'),
            'type' => $this->integer($frame, 'type'),
        ];
    }

    /**
     * @return array{attr: int, from: string, to: string, subj: string, date_written: int, replyto: int, replies: string}|null
     */
    private function unpackHeader(string $raw): ?array
    {
        $header = unpack(
            'Vattr/a36from/a36to/a72subj/a8orig/a8dest/Vdate_written/Vdate_arrived/vutc_offset/Vreplyto/a36replies/Vumsgid/a20ftsc_date',
            $raw,
        );

        if ($header === false) {
            return null;
        }

        return [
            'attr' => $this->integer($header, 'attr'),
            'from' => $this->string($header, 'from'),
            'to' => $this->string($header, 'to'),
            'subj' => $this->string($header, 'subj'),
            'date_written' => $this->integer($header, 'date_written'),
            'replyto' => $this->integer($header, 'replyto'),
            'replies' => $this->string($header, 'replies'),
        ];
    }

    /**
     * @return list<int>
     */
    private function unpackReplies(string $raw): array
    {
        $replies = unpack('V9', $raw);

        if ($replies === false) {
            return [];
        }

        return array_map(
            fn (mixed $reply): int => is_int($reply) ? $reply : 0,
            array_values($replies),
        );
    }

    /**
     * @param array<mixed> $values
     */
    private function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<mixed> $values
     */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}

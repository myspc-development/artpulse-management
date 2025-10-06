<?php

namespace ArtPulse\Integration\Recurrence;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use function wp_parse_list;

class RecurrenceExpander
{
    public const MAX_INSTANCES = 1000;

    /**
     * @param array<string, mixed> $event
     * @return array{occurrences: array<int, array<string, mixed>>, truncated: bool}
     */
    public static function expand(array $event, ?DateTimeImmutable $rangeStart, ?DateTimeImmutable $rangeEnd): array
    {
        $startUtc = self::parseUtc($event['startUtc'] ?? $event['start'] ?? null);
        if (!$startUtc) {
            return ['occurrences' => [], 'truncated' => false];
        }

        $timezone = self::resolveTimezone($event['timezone'] ?? null);
        $endUtc   = self::parseUtc($event['endUtc'] ?? $event['end'] ?? null);

        if (!$endUtc) {
            $endUtc = self::fallbackEnd($startUtc, !empty($event['allDay']));
        }

        $baseStartLocal = $startUtc->setTimezone($timezone);
        $baseEndLocal   = $endUtc->setTimezone($timezone);
        $durationSeconds = max(0, $baseEndLocal->getTimestamp() - $baseStartLocal->getTimestamp());
        if (0 === $durationSeconds) {
            $durationSeconds = !empty($event['allDay']) ? DAY_IN_SECONDS : HOUR_IN_SECONDS;
        }

        $occurrenceSet = self::buildOccurrenceSet(
            $event['recurrence'] ?? null,
            $baseStartLocal,
            $timezone,
            $rangeStart,
            $rangeEnd
        );

        $occurrences      = [];
        $truncated        = false;
        $rangeStartUtc    = $rangeStart;
        $rangeEndUtc      = $rangeEnd;
        $totalOccurrences = 0;

        foreach ($occurrenceSet['instances'] as $localStart) {
            $localEnd = $localStart->modify('+' . $durationSeconds . ' seconds');
            $start    = $localStart->setTimezone(new DateTimeZone('UTC'));
            $end      = $localEnd->setTimezone(new DateTimeZone('UTC'));

            if (!self::overlaps($start, $end, $rangeStartUtc, $rangeEndUtc)) {
                continue;
            }

            $occurrences[] = [
                'id'         => $event['id'] ?? null,
                'title'      => $event['title'] ?? '',
                'allDay'     => !empty($event['allDay']),
                'timezone'   => $timezone->getName(),
                'location'   => $event['location'] ?? '',
                'cost'       => $event['cost'] ?? '',
                'url'        => $event['url'] ?? '',
                'favorite'   => !empty($event['favorite']),
                'start'      => $start->format(DateTimeInterface::ATOM),
                'end'        => $end->format(DateTimeInterface::ATOM),
                'startLocal' => $localStart->format(DateTimeInterface::ATOM),
                'endLocal'   => $localEnd->format(DateTimeInterface::ATOM),
            ];

            $totalOccurrences++;

            if ($totalOccurrences >= self::MAX_INSTANCES) {
                $truncated = true;
                break;
            }
        }

        if ($occurrenceSet['limited']) {
            $truncated = true;
        }

        return [
            'occurrences' => array_values($occurrences),
            'truncated'   => $truncated,
        ];
    }

    /**
     * @param null|string|array<mixed> $recurrence
     * @return array{instances: array<int, DateTimeImmutable>, limited: bool}
     */
    private static function buildOccurrenceSet($recurrence, DateTimeImmutable $baseStartLocal, DateTimeZone $timezone, ?DateTimeImmutable $rangeStart, ?DateTimeImmutable $rangeEnd): array
    {
        $starts  = [$baseStartLocal->format(DateTimeInterface::ATOM) => $baseStartLocal];
        $limited = false;

        $definition = self::parseRecurrenceDefinition($recurrence);

        if ($definition['rrule']) {
            $rule = self::parseRrule($definition['rrule'], $timezone);
            if ($rule) {
                $current      = $baseStartLocal;
                $occurrenceNo = 1; // base occurrence counted.
                $iterations   = 0;
                $rangeLimit   = $rangeEnd ? $rangeEnd->setTimezone($timezone)->modify('+1 month') : null;

                while (true) {
                    if ($rule['count'] && $occurrenceNo >= $rule['count']) {
                        break;
                    }

                    $next = self::advance($current, $rule['freq'], $rule['interval']);
                    if (!$next) {
                        break;
                    }

                    $current = $next;

                    if ($rule['until'] && $current > $rule['until']->setTimezone($timezone)) {
                        break;
                    }

                    if ($rangeLimit && $current > $rangeLimit) {
                        $limited = true;
                        break;
                    }

                    $starts[$current->format(DateTimeInterface::ATOM)] = $current;
                    $occurrenceNo++;
                    $iterations++;

                    if ($iterations >= self::MAX_INSTANCES * 5) {
                        $limited = true;
                        break;
                    }
                }
            }
        }

        if ($definition['rdates']) {
            foreach ($definition['rdates'] as $rdateString) {
                $rdate = self::parseRdate($rdateString, $timezone, $baseStartLocal);
                if ($rdate) {
                    $starts[$rdate->format(DateTimeInterface::ATOM)] = $rdate;
                }
            }
        }

        ksort($starts);

        return [
            'instances' => array_values($starts),
            'limited'   => $limited,
        ];
    }

    private static function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, ?DateTimeImmutable $rangeStart, ?DateTimeImmutable $rangeEnd): bool
    {
        if ($rangeStart && $end < $rangeStart) {
            return false;
        }

        if ($rangeEnd && $start > $rangeEnd) {
            return false;
        }

        return true;
    }

    private static function parseUtc($value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'));
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);

            return $date->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function resolveTimezone($timezone): DateTimeZone
    {
        if (is_string($timezone) && '' !== $timezone) {
            try {
                return new DateTimeZone($timezone);
            } catch (\Exception $e) {
                // Fall through to defaults.
            }
        }

        if (function_exists('wp_timezone')) {
            $wpTimezone = \wp_timezone();
            if ($wpTimezone instanceof DateTimeZone) {
                return $wpTimezone;
            }
        }

        return new DateTimeZone('UTC');
    }

    private static function fallbackEnd(DateTimeImmutable $start, bool $allDay): DateTimeImmutable
    {
        try {
            return $start->add(new DateInterval($allDay ? 'P1D' : 'PT1H'));
        } catch (\Exception $e) {
            return $start;
        }
    }

    /**
     * @return array{rrule: ?string, rdates: array<int, string>}
     */
    private static function parseRecurrenceDefinition($recurrence): array
    {
        $rrule  = null;
        $rdates = [];

        if (is_string($recurrence)) {
            $lines = preg_split('/[\r\n]+/', trim($recurrence)) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ('' === $line) {
                    continue;
                }

                if (str_starts_with($line, 'RRULE')) {
                    $rrule = self::valueAfterColon($line);
                } elseif (str_starts_with($line, 'RDATE')) {
                    $rdates = array_merge($rdates, self::extractListAfterColon($line));
                }
            }
        } elseif (is_array($recurrence)) {
            $rruleCandidate = $recurrence['rrule'] ?? $recurrence['RRULE'] ?? null;
            if (is_array($rruleCandidate)) {
                $rruleCandidate = implode("\n", array_map('strval', $rruleCandidate));
            }
            if (is_string($rruleCandidate) && '' !== trim($rruleCandidate)) {
                $rrule = trim($rruleCandidate);
            }

            $rdateCandidate = $recurrence['rdate'] ?? $recurrence['RDATE'] ?? null;
            if (is_string($rdateCandidate)) {
                $rdates = array_merge($rdates, self::extractListAfterColon($rdateCandidate));
            } elseif (is_array($rdateCandidate)) {
                foreach ($rdateCandidate as $candidate) {
                    if (is_string($candidate)) {
                        $rdates = array_merge($rdates, self::extractListAfterColon($candidate));
                    }
                }
            }
        }

        return [
            'rrule'  => $rrule,
            'rdates' => array_values(array_filter(array_map('trim', $rdates))),
        ];
    }

    private static function valueAfterColon(string $value): string
    {
        $parts = explode(':', $value, 2);

        return isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);
    }

    /**
     * @return array<int, string>
     */
    private static function extractListAfterColon(string $value): array
    {
        $list = self::valueAfterColon($value);
        $items = array_map('trim', explode(',', $list));

        return array_filter($items, static fn($item) => '' !== $item);
    }

    /**
     * @return array{freq: string, interval: int, count: ?int, until: ?DateTimeImmutable}|null
     */
    private static function parseRrule(?string $rule, DateTimeZone $timezone): ?array
    {
        if (null === $rule || '' === trim($rule)) {
            return null;
        }

        if (str_starts_with($rule, 'RRULE:')) {
            $rule = substr($rule, 6);
        }

        $parts = wp_parse_list($rule);
        $data  = [
            'freq'     => 'DAILY',
            'interval' => 1,
            'count'    => null,
            'until'    => null,
        ];

        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $keyUpper = strtoupper($key);

            switch ($keyUpper) {
                case 'FREQ':
                    $allowed = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
                    $valueUpper = strtoupper($value);
                    if (in_array($valueUpper, $allowed, true)) {
                        $data['freq'] = $valueUpper;
                    }
                    break;
                case 'INTERVAL':
                    $interval = max(1, (int) $value);
                    $data['interval'] = $interval;
                    break;
                case 'COUNT':
                    $count = (int) $value;
                    $data['count'] = $count > 0 ? $count : null;
                    break;
                case 'UNTIL':
                    $until = self::parseUntil($value, $timezone);
                    if ($until) {
                        $data['until'] = $until;
                    }
                    break;
            }
        }

        return $data;
    }

    private static function parseUntil(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            if (str_ends_with($value, 'Z')) {
                return new DateTimeImmutable($value, new DateTimeZone('UTC'));
            }

            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function advance(DateTimeImmutable $date, string $frequency, int $interval): ?DateTimeImmutable
    {
        $frequency = strtoupper($frequency);

        try {
            switch ($frequency) {
                case 'DAILY':
                    return $date->add(new DateInterval('P' . $interval . 'D'));
                case 'WEEKLY':
                    return $date->add(new DateInterval('P' . $interval . 'W'));
                case 'MONTHLY':
                    return $date->add(new DateInterval('P' . $interval . 'M'));
                case 'YEARLY':
                    return $date->add(new DateInterval('P' . $interval . 'Y'));
                default:
                    return $date->add(new DateInterval('P' . $interval . 'D'));
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function parseRdate(string $value, DateTimeZone $timezone, DateTimeImmutable $baseStartLocal): ?DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        if (str_contains($value, ':')) {
            $value = self::valueAfterColon($value);
        }

        try {
            if (preg_match('/^\d{8}$/', $value)) {
                $date = DateTimeImmutable::createFromFormat('Ymd H:i:s', $value . ' ' . $baseStartLocal->format('H:i:s'), $timezone);

                if ($date instanceof DateTimeImmutable) {
                    return $date;
                }
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = new DateTimeImmutable($value, $timezone);

                return $date->setTime(
                    (int) $baseStartLocal->format('H'),
                    (int) $baseStartLocal->format('i'),
                    (int) $baseStartLocal->format('s')
                );
            }

            if (str_ends_with($value, 'Z')) {
                return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone);
            }

            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception $e) {
            return null;
        }
    }
}


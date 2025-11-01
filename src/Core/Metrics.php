<?php

namespace ArtPulse\Core;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use wpdb;
use function get_option;

final class Metrics
{
    public const ALLOWED_RANGES = [7, 30, 90];
    private const DEFAULT_RANGE = 7;
    private const DATE_FORMAT = 'Y-m-d';

    public static function normalize_range(?int $range): int
    {
        $range = (int) $range;
        if (!in_array($range, self::ALLOWED_RANGES, true)) {
            return self::DEFAULT_RANGE;
        }

        return $range;
    }

    /**
     * @return array{dates: array<int, string>, start: string, end: string}
     */
    public static function build_period(int $range): array
    {
        $range = self::normalize_range($range);

        $now    = new DateTimeImmutable('now', self::site_timezone());
        $end    = $now->setTime(23, 59, 59);
        $start  = $end->sub(new DateInterval('P' . max(0, $range - 1) . 'D'));
        $period = new DatePeriod($start->setTime(0, 0, 0), new DateInterval('P1D'), $end->add(new DateInterval('P1D')));

        $dates = [];
        foreach ($period as $day) {
            $dates[] = $day->format(self::DATE_FORMAT);
        }

        return [
            'dates' => $dates,
            'start' => $start->format('Y-m-d 00:00:00'),
            'end'   => $end->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * @param array<string>        $conditions
     * @param array<int|string>    $params
     * @return array<string, int>
     */
    public static function collect_counts(string $table, string $date_column, array $conditions, array $params, string $start, string $end): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($date_column)) {
            return [];
        }

        $where   = array_filter(array_map('trim', $conditions), static fn($condition) => '' !== $condition);
        $where[] = sprintf('%s BETWEEN %%s AND %%s', $date_column);

        $params[] = $start;
        $params[] = $end;

        $sql = sprintf(
            'SELECT DATE(%1$s) AS day, COUNT(*) AS total FROM %2$s WHERE %3$s GROUP BY DATE(%1$s) ORDER BY day ASC',
            $date_column,
            $table,
            implode(' AND ', array_map(static fn($part) => '(' . $part . ')', $where))
        );

        $prepared = $wpdb->prepare($sql, $params);
        if (false === $prepared) {
            return [];
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $day = isset($row['day']) ? (string) $row['day'] : '';
            if ('' === $day) {
                continue;
            }

            $counts[$day] = isset($row['total']) ? (int) $row['total'] : 0;
        }

        return $counts;
    }

    /**
     * @param array<int, string>   $dates
     * @param array<string, array<string, int>> $metrics
     * @return array{series: array<int, array<string, int|string>>, totals: array<string, int>}
     */
    public static function build_series(array $dates, array $metrics): array
    {
        $series = [];
        $totals = [];

        foreach ($metrics as $metric => $map) {
            $totals[$metric] = 0;
            if (!is_array($map)) {
                $metrics[$metric] = [];
            }
        }

        foreach ($dates as $date) {
            $entry = ['date' => $date];
            foreach ($metrics as $metric => $map) {
                $value             = isset($map[$date]) ? (int) $map[$date] : 0;
                $entry[$metric]    = $value;
                $totals[$metric]  += $value;
            }

            $series[] = $entry;
        }

        return [
            'series' => $series,
            'totals' => $totals,
        ];
    }

    private static function site_timezone(): DateTimeZone
    {
        $timezone_string = get_option('timezone_string');
        if (is_string($timezone_string) && '' !== $timezone_string) {
            try {
                return new DateTimeZone($timezone_string);
            } catch (\Exception $exception) {
                // Fallback to offset handling below.
            }
        }

        $offset = (float) get_option('gmt_offset', 0);
        $hours  = (int) $offset;
        $minutes = abs(($offset - $hours) * 60);
        $sign   = $offset >= 0 ? '+' : '-';
        $formatted = sprintf('%s%02d:%02d', $sign, abs($hours), $minutes);

        try {
            return new DateTimeZone($formatted);
        } catch (\Exception $exception) {
            return new DateTimeZone('UTC');
        }
    }

    private static function is_safe_identifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\\.]+$/', $value);
    }
}

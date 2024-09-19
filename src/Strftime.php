<?php

namespace MmNijas\Strftime;

use DateTime;
use DateTimeZone;
use DateTimeInterface;
use Exception;
use IntlDateFormatter;
use IntlGregorianCalendar;
use InvalidArgumentException;
use Locale;

class Strftime
{
    /**
     * Locale-formatted strftime using IntlDateFormatter (PHP 8.1 compatible).
     * Provides a cross-platform alternative to strftime().
     *
     * @param string $format
     * @param mixed $timestamp
     * @param string|null $locale
     * @return string
     */
    public static function strftime(string $format, $timestamp = null, ?string $locale = null): string
    {
        if (!($timestamp instanceof DateTimeInterface)) {
            $timestamp = is_int($timestamp) ? '@' . $timestamp : (string) $timestamp;

            try {
                $timestamp = new DateTime($timestamp);
            } catch (Exception $e) {
                throw new InvalidArgumentException('$timestamp argument is not valid.', 0, $e);
            }

            $timestamp->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }

        $locale = Locale::canonicalize($locale ?? (Locale::getDefault() ?? setlocale(LC_TIME, '0')));

        $intl_formats = [
            '%a' => 'ccc',
            '%A' => 'EEEE',
            '%b' => 'LLL',
            '%B' => 'MMMM',
            '%h' => 'MMM',
        ];

        $intl_formatter = function (DateTimeInterface $timestamp, string $format) use ($intl_formats, $locale) {
            $tz = $timestamp->getTimezone();
            $date_type = IntlDateFormatter::FULL;
            $time_type = IntlDateFormatter::FULL;
            $pattern = '';

            switch ($format) {
                case '%c':
                    $date_type = IntlDateFormatter::LONG;
                    $time_type = IntlDateFormatter::SHORT;
                    break;
                case '%x':
                    $date_type = IntlDateFormatter::SHORT;
                    $time_type = IntlDateFormatter::NONE;
                    break;
                case '%X':
                    $date_type = IntlDateFormatter::NONE;
                    $time_type = IntlDateFormatter::MEDIUM;
                    break;
                default:
                    $pattern = $intl_formats[$format];
            }

            $calendar = IntlGregorianCalendar::createInstance();
            if ($calendar instanceof IntlGregorianCalendar) {
                $calendar->setGregorianChange(PHP_INT_MIN);
            }

            return (new IntlDateFormatter($locale, $date_type, $time_type, $tz, $calendar, $pattern))->format($timestamp);
        };

        $translation_table = [
            '%a' => $intl_formatter,
            '%A' => $intl_formatter,
            '%d' => 'd',
            '%e' => fn($timestamp) => sprintf('% 2u', $timestamp->format('j')),
            '%j' => fn($timestamp) => sprintf('%03d', $timestamp->format('z') + 1),
            '%u' => 'N',
            '%w' => 'w',
            '%U' => fn($timestamp) => sprintf('%02u', 1 + ($timestamp->format('z') - (new DateTime(sprintf('%d-01 Sunday', $timestamp->format('Y'))))->format('z')) / 7),
            '%V' => 'W',
            '%W' => fn($timestamp) => sprintf('%02u', 1 + ($timestamp->format('z') - (new DateTime(sprintf('%d-01 Monday', $timestamp->format('Y'))))->format('z')) / 7),
            '%b' => $intl_formatter,
            '%B' => $intl_formatter,
            '%h' => $intl_formatter,
            '%m' => 'm',
            '%C' => fn($timestamp) => floor($timestamp->format('Y') / 100),
            '%g' => fn($timestamp) => substr($timestamp->format('o'), -2),
            '%G' => 'o',
            '%y' => 'y',
            '%Y' => 'Y',
            '%H' => 'H',
            '%k' => fn($timestamp) => sprintf('% 2u', $timestamp->format('G')),
            '%I' => 'h',
            '%l' => fn($timestamp) => sprintf('% 2u', $timestamp->format('g')),
            '%M' => 'i',
            '%p' => 'A',
            '%P' => 'a',
            '%r' => 'h:i:s A',
            '%R' => 'H:i',
            '%S' => 's',
            '%T' => 'H:i:s',
            '%X' => $intl_formatter,
            '%z' => 'O',
            '%Z' => 'T',
            '%c' => $intl_formatter,
            '%D' => 'm/d/Y',
            '%F' => 'Y-m-d',
            '%s' => 'U',
            '%x' => $intl_formatter,
        ];

        $out = preg_replace_callback('/(?<!%)%([_#-]?)([a-zA-Z])/', function ($match) use ($translation_table, $timestamp) {
            $prefix = $match[1];
            $char = $match[2];
            $pattern = '%' . $char;

            if (!isset($translation_table[$pattern])) {
                throw new InvalidArgumentException("Format $pattern is unknown.");
            }

            $replace = $translation_table[$pattern];
            $result = is_string($replace) ? $timestamp->format($replace) : $replace($timestamp, $pattern);

            return match ($prefix) {
                '_' => preg_replace('/\G0(?=.)/', ' ', $result),
                '#', '-' => preg_replace('/^[0\s]+(?=.)/', '', $result),
                default => $result,
            };
        }, $format);

        return str_replace('%%', '%', $out);
    }
}

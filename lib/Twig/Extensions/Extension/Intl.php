<?php

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\Environment;

class Twig_Extensions_Extension_Intl extends AbstractExtension
{
    public function __construct()
    {
        if (!class_exists('IntlDateFormatter')) {
            throw new RuntimeException('The native PHP intl extension (https://www.php.net/manual/en/book.intl.php) is needed to use intl-based filters.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('localizeddate', 'twig_localized_date_filter', ['needs_environment' => true]),
            new TwigFilter('localizednumber', 'twig_localized_number_filter'),
            new TwigFilter('localizedcurrency', 'twig_localized_currency_filter'),
        ];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'intl';
    }
}

/**
 * @param Environment     $env
 * @param string|DateTime $date
 * @param string          $dateFormat
 * @param string          $timeFormat
 * @param null            $locale
 * @param null            $timezone
 * @param null            $format
 * @param string          $calendar
 *
 * @return false|string
 */
function twig_localized_date_filter(
    Environment $env,
    $date,
    $dateFormat = 'medium',
    $timeFormat = 'medium',
    $locale = null,
    $timezone = null,
    $format = null,
    $calendar = 'gregorian'
) {
    $date = twig_date_converter($env, $date, $timezone);

    $formatValues = [
        'none'   => IntlDateFormatter::NONE,
        'short'  => IntlDateFormatter::SHORT,
        'medium' => IntlDateFormatter::MEDIUM,
        'long'   => IntlDateFormatter::LONG,
        'full'   => IntlDateFormatter::FULL,
    ];

    if (PHP_VERSION_ID < 50500 || !class_exists('IntlTimeZone')) {
        $formatter = IntlDateFormatter::create(
            $locale,
            $formatValues[$dateFormat],
            $formatValues[$timeFormat],
            $date->getTimezone()->getName(),
            'gregorian' === $calendar ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
            $format
        );

        return $formatter->format($date->getTimestamp());
    }

    $formatter = IntlDateFormatter::create(
        $locale,
        $formatValues[$dateFormat],
        $formatValues[$timeFormat],
        IntlTimeZone::createTimeZone($date->getTimezone()->getName()),
        'gregorian' === $calendar ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
        $format
    );

    return $formatter->format($date->getTimestamp());
}

/**
 * @param int|float $number
 * @param string    $style
 * @param string    $type
 * @param null      $locale
 *
 * @return false|string
 * @throws SyntaxError
 */
function twig_localized_number_filter($number, $style = 'decimal', $type = 'default', $locale = null)
{
    static $typeValues = [
        'default'  => NumberFormatter::TYPE_DEFAULT,
        'int32'    => NumberFormatter::TYPE_INT32,
        'int64'    => NumberFormatter::TYPE_INT64,
        'double'   => NumberFormatter::TYPE_DOUBLE,
        'currency' => NumberFormatter::TYPE_CURRENCY,
    ];

    $formatter = twig_get_number_formatter($locale, $style);

    if (!isset($typeValues[$type])) {
        throw new SyntaxError(sprintf('The type "%s" does not exist. Known types are: "%s"', $type,
            implode('", "', array_keys($typeValues))));
    }

    return $formatter->format($number, $typeValues[$type]);
}

/**
 * @param float|null  $number
 * @param string|null $currency
 * @param string|null $locale
 *
 * @return string
 * @throws SyntaxError
 */
function twig_localized_currency_filter(?float $number, ?string $currency = null, ?string $locale = null)
{
    $formatter = twig_get_number_formatter($locale, 'currency');

    return $formatter->formatCurrency($number, $currency);
}

/**
 * Gets a number formatter instance according to given locale and formatter.
 *
 * @param null|string $locale Locale in which the number would be formatted
 * @param string      $style  Style of the formatting
 *
 * @return NumberFormatter A NumberFormatter instance
 * @throws SyntaxError
 */
function twig_get_number_formatter(?string $locale, string $style)
{
    static $formatter, $currentStyle;

    $locale = $locale ?? Locale::getDefault();

    if ($formatter && $currentStyle === $style && $formatter->getLocale() === $locale) {
        // Return same instance of NumberFormatter if parameters are the same
        // to those in previous call
        return $formatter;
    }

    static $styleValues = [
        'decimal'    => NumberFormatter::DECIMAL,
        'currency'   => NumberFormatter::CURRENCY,
        'percent'    => NumberFormatter::PERCENT,
        'scientific' => NumberFormatter::SCIENTIFIC,
        'spellout'   => NumberFormatter::SPELLOUT,
        'ordinal'    => NumberFormatter::ORDINAL,
        'duration'   => NumberFormatter::DURATION,
    ];

    if (!isset($styleValues[$style])) {
        throw new SyntaxError(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style,
            implode('", "', array_keys($styleValues))));
    }

    $currentStyle = $style;

    $formatter = NumberFormatter::create($locale, $styleValues[$style]);

    return $formatter;
}

class_alias('Twig_Extensions_Extension_Intl', 'Twig\Extensions\IntlExtension', false);

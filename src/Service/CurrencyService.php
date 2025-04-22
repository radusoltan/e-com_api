<?php

namespace App\Service;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Money\Parser\IntlMoneyParser;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CurrencyService
{

    private string $defaultCurrencyCode;
    private array $exchangeRates;

    public function __construct(
        private CacheService $cacheService,
        private ParameterBagInterface $parameterBag,
        ?string $defaultCurrencyCode = null
    ) {
        $this->defaultCurrencyCode = $defaultCurrencyCode ?? 'USD';
        $this->exchangeRates = $this->loadExchangeRates();
    }

    /**
     * Get the default currency code
     */
    public function getDefaultCurrencyCode(): string
    {
        return $this->defaultCurrencyCode;
    }

    /**
     * Format money object using the locale settings
     */
    public function format(Money $money, ?string $locale = null): string
    {
        $locale = $locale ?? $this->parameterBag->get('app.locale') ?? 'en_US';
        $currencies = new ISOCurrencies();

        $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, $currencies);

        return $moneyFormatter->format($money);
    }

    /**
     * Parse a formatted money string into a Money object
     */
    public function parse(string $formatted, string $currencyCode, ?string $locale = null): Money
    {
        $locale = $locale ?? $this->parameterBag->get('app.locale') ?? 'en_US';
        $currencies = new ISOCurrencies();

        $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $moneyParser = new IntlMoneyParser($numberFormatter, $currencies);

        return $moneyParser->parse($formatted, new Currency($currencyCode));
    }

    /**
     * Convert money from one currency to another
     */
    public function convert(Money $money, Currency $toCurrency): Money
    {
        $fromCurrency = $money->getCurrency()->getCode();
        $toCurrencyCode = $toCurrency->getCode();

        // If currencies are the same, no conversion needed
        if ($fromCurrency === $toCurrencyCode) {
            return $money;
        }

        // Get exchange rate
        $rate = $this->getExchangeRate($fromCurrency, $toCurrencyCode);

        // Convert amount
        $amount = (int) round($money->getAmount() * $rate);

        return new Money($amount, $toCurrency);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        // If currencies are the same, rate is 1
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Check direct rate
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";
        return $this->cacheService->get(
            $cacheKey,
            function() use ($fromCurrency, $toCurrency) {
                // Check direct rate
                $directKey = "{$fromCurrency}_{$toCurrency}";
                if (isset($this->exchangeRates[$directKey])) {
                    return $this->exchangeRates[$directKey];
                }

                // Check inverse rate
                $inverseKey = "{$toCurrency}_{$fromCurrency}";
                if (isset($this->exchangeRates[$inverseKey])) {
                    return 1 / $this->exchangeRates[$inverseKey];
                }

                // Try via default currency
                if ($fromCurrency !== $this->defaultCurrencyCode && $toCurrency !== $this->defaultCurrencyCode) {
                    $fromToDefault = $this->getExchangeRate($fromCurrency, $this->defaultCurrencyCode);
                    $defaultToTo = $this->getExchangeRate($this->defaultCurrencyCode, $toCurrency);
                    return $fromToDefault * $defaultToTo;
                }

                // Fallback to 1:1 if no rate is found (should log this)
                return 1.0;
            },
            3600 // 1 hour cache
        );
    }

    /**
     * Update exchange rates from an external source
     */
    public function updateExchangeRates(): void
    {
        // In a real implementation, this would fetch rates from an API
        // For example:
        // $rates = $this->httpClient->get('https://api.exchangerate-api.com/v4/latest/USD');

        // For this example, we'll just use hardcoded rates
        $baseRates = [
            'USD_EUR' => 0.92,
            'USD_GBP' => 0.79,
            'USD_JPY' => 150.45,
            'USD_CAD' => 1.35,
            'USD_AUD' => 1.52,
            // Add more rates as needed
        ];

        // Store rates
        foreach ($baseRates as $key => $rate) {
            $this->exchangeRates[$key] = $rate;
            $this->cacheService->get(
                "exchange_rate_{$key}",
                fn() => $rate,
                86400, // 24 hours
                true // force refresh
            );
        }
    }

    /**
     * Load exchange rates from cache or default values
     */
    private function loadExchangeRates(): array
    {
        // In a real application, this would load from cache first, then fall back to defaults
        // For simplicity, we're just using hardcoded values here
        return [
            'USD_EUR' => 0.92,
            'USD_GBP' => 0.79,
            'USD_JPY' => 150.45,
            'USD_CAD' => 1.35,
            'USD_AUD' => 1.52,
            // Add more rates as needed
        ];
    }

    /**
     * Get all available currencies
     */
    public function getAvailableCurrencies(): array
    {
        $isoObjCurrencies = new ISOCurrencies();
        $currencies = [];

        foreach ($isoObjCurrencies as $currency) {
            $code = $currency->getCode();
            $currencies[$code] = $code;
        }

        return $currencies;
    }

}
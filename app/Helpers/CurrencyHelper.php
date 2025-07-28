<?php

namespace App\Helpers;

/**
 * Currency Helper for Indonesian Rupiah (IDR)
 * Format: Rp25.000 or Rp25.000,50
 * - No space between Rp and number
 * - Dot (.) as thousand separator
 * - Comma (,) as decimal separator
 */
class CurrencyHelper
{
    /**
     * Format number to Indonesian Rupiah format
     *
     * @param float|int|string|null $amount The amount to format
     * @param array $options Formatting options
     * @return string Formatted currency string
     */
    public static function formatCurrency($amount, array $options = []): string
    {
        $showDecimals = $options['show_decimals'] ?? false;
        $decimalPlaces = $options['decimal_places'] ?? 2;
        $forceDecimals = $options['force_decimals'] ?? false;

        // Handle null, empty, or invalid values
        if ($amount === null || $amount === '' || (!is_numeric($amount) && !is_string($amount))) {
            return 'Rp0';
        }

        // Convert to float
        if (is_string($amount)) {
            // Remove any existing formatting
            $cleanAmount = preg_replace('/[^\d.,-]/', '', $amount);
            $cleanAmount = str_replace(',', '.', $cleanAmount); // Handle comma as decimal
            $numAmount = (float) $cleanAmount;
        } else {
            $numAmount = (float) $amount;
        }

        // Handle negative numbers
        $isNegative = $numAmount < 0;
        $absAmount = abs($numAmount);

        // Check if number has decimals
        $hasDecimals = fmod($absAmount, 1) !== 0.0;
        
        // Determine if we should show decimals
        $shouldShowDecimals = $forceDecimals || ($showDecimals && $hasDecimals);

        if ($shouldShowDecimals) {
            // Format with decimals
            $formattedNumber = number_format($absAmount, $decimalPlaces, ',', '.');
            
            // Remove trailing zeros if not forcing decimals
            if (!$forceDecimals) {
                $formattedNumber = rtrim(rtrim($formattedNumber, '0'), ',');
            }
        } else {
            // Format without decimals
            $roundedAmount = round($absAmount);
            $formattedNumber = number_format($roundedAmount, 0, ',', '.');
        }

        // Add currency symbol and handle negative
        $result = 'Rp' . $formattedNumber;
        return $isNegative ? '-' . $result : $result;
    }

    /**
     * Format currency with default settings (no decimals for whole numbers)
     *
     * @param float|int|string|null $amount The amount to format
     * @return string Formatted currency string
     */
    public static function formatRupiah($amount): string
    {
        return self::formatCurrency($amount);
    }

    /**
     * Format currency always showing 2 decimal places
     *
     * @param float|int|string|null $amount The amount to format
     * @return string Formatted currency string with decimals
     */
    public static function formatRupiahWithDecimals($amount): string
    {
        return self::formatCurrency($amount, ['force_decimals' => true]);
    }

    /**
     * Parse formatted currency string back to float
     *
     * @param string $formattedAmount The formatted currency string
     * @return float Parsed number value
     */
    public static function parseCurrency(string $formattedAmount): float
    {
        if (empty($formattedAmount)) {
            return 0.0;
        }

        // Check if negative
        $isNegative = strpos($formattedAmount, '-') === 0;
        
        // Remove Rp symbol and negative sign
        $cleanAmount = preg_replace('/^-?Rp/', '', $formattedAmount);
        
        // Replace thousand separators (dots) with empty string
        $cleanAmount = str_replace('.', '', $cleanAmount);
        
        // Replace decimal separator (comma) with dot
        $cleanAmount = str_replace(',', '.', $cleanAmount);
        
        $parsed = (float) $cleanAmount;
        return $isNegative ? -$parsed : $parsed;
    }

    /**
     * Format number for input fields (without Rp symbol)
     *
     * @param float|int|string|null $amount The amount to format
     * @param bool $allowDecimals Whether to allow decimal input
     * @return string Formatted number string for input
     */
    public static function formatNumberInput($amount, bool $allowDecimals = false): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $numAmount = is_string($amount) ? (float) preg_replace('/[^\d.,-]/', '', $amount) : (float) $amount;

        if ($allowDecimals && fmod($numAmount, 1) !== 0.0) {
            return number_format($numAmount, 2, ',', '.');
        }

        return number_format(round($numAmount), 0, ',', '.');
    }

    /**
     * Validate if string is a valid currency format
     *
     * @param string $value The value to validate
     * @return bool True if valid currency format
     */
    public static function isValidCurrencyFormat(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Pattern for Indonesian Rupiah format: Rp25.000 or Rp25.000,50
        $pattern = '/^-?Rp\d{1,3}(\.\d{3})*(?:,\d{1,2})?$/';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Convert from old format to new format
     * Useful for migration of existing data
     *
     * @param string $oldFormat Old format like "Rp 25.000" or "Rp 25000.00"
     * @return string New format "Rp25.000"
     */
    public static function convertFromOldFormat(string $oldFormat): string
    {
        if (empty($oldFormat)) {
            return 'Rp0';
        }

        // Parse the old format
        $amount = self::parseOldFormat($oldFormat);
        
        // Return in new format
        return self::formatRupiah($amount);
    }

    /**
     * Parse old currency formats
     *
     * @param string $oldFormat Old format currency string
     * @return float Parsed amount
     */
    public static function parseOldFormat(string $oldFormat): float
    {
        if (empty($oldFormat)) {
            return 0.0;
        }

        // Remove Rp and spaces
        $cleanAmount = preg_replace('/^-?\s*Rp\s*/', '', $oldFormat);
        
        // Handle different old formats:
        // "25.000" (Indonesian thousand separator)
        // "25000.00" (English decimal format)
        // "25,000.00" (English with comma thousand separator)
        
        // Check if it uses comma as thousand separator and dot as decimal
        if (preg_match('/^\d{1,3}(,\d{3})*\.\d{2}$/', $cleanAmount)) {
            // English format: 25,000.00
            $cleanAmount = str_replace(',', '', $cleanAmount);
            return (float) $cleanAmount;
        }
        
        // Check if it uses dot as thousand separator (Indonesian style)
        if (preg_match('/^\d{1,3}(\.\d{3})*$/', $cleanAmount)) {
            // Indonesian format without decimals: 25.000
            $cleanAmount = str_replace('.', '', $cleanAmount);
            return (float) $cleanAmount;
        }
        
        // Check if it has decimals with dot (like 25000.00)
        if (preg_match('/^\d+\.\d{2}$/', $cleanAmount)) {
            // Simple decimal format: 25000.00
            return (float) $cleanAmount;
        }
        
        // Fallback: remove all non-numeric characters except last dot/comma
        $cleanAmount = preg_replace('/[^\d.,]/', '', $cleanAmount);
        
        // If multiple dots/commas, assume last one is decimal separator
        if (substr_count($cleanAmount, '.') > 1 || substr_count($cleanAmount, ',') > 1) {
            // Multiple separators, clean up
            $lastDot = strrpos($cleanAmount, '.');
            $lastComma = strrpos($cleanAmount, ',');
            
            if ($lastDot !== false && $lastComma !== false) {
                // Both exist, use the last one as decimal
                if ($lastDot > $lastComma) {
                    $cleanAmount = str_replace(',', '', $cleanAmount);
                } else {
                    $cleanAmount = str_replace('.', '', $cleanAmount);
                    $cleanAmount = str_replace(',', '.', $cleanAmount);
                }
            }
        } else {
            // Single separator, assume Indonesian format (dot as thousand, comma as decimal)
            $cleanAmount = str_replace(',', '.', $cleanAmount);
        }
        
        return (float) $cleanAmount;
    }

    /**
     * Get currency symbol only
     *
     * @return string Currency symbol
     */
    public static function getCurrencySymbol(): string
    {
        return 'Rp';
    }

    /**
     * Format array of amounts
     *
     * @param array $amounts Array of amounts to format
     * @param array $options Formatting options
     * @return array Array of formatted currency strings
     */
    public static function formatMultiple(array $amounts, array $options = []): array
    {
        return array_map(function ($amount) use ($options) {
            return self::formatCurrency($amount, $options);
        }, $amounts);
    }

    /**
     * Calculate percentage and format result
     *
     * @param float $part The part value
     * @param float $total The total value
     * @param int $decimals Number of decimal places for percentage
     * @return array Array with formatted currency and percentage
     */
    public static function formatWithPercentage(float $part, float $total, int $decimals = 1): array
    {
        $percentage = $total > 0 ? round(($part / $total) * 100, $decimals) : 0;
        
        return [
            'amount' => self::formatRupiah($part),
            'percentage' => $percentage . '%',
            'raw_amount' => $part,
            'raw_percentage' => $percentage
        ];
    }
}
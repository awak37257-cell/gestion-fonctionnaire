<?php
require_once __DIR__ . '/../vendor/autoload.php';

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

function indicatifs_telephoniques(): array
{
    static $regions = null;
    if ($regions !== null) {
        return $regions;
    }

    $util = PhoneNumberUtil::getInstance();
    $regions = [];
    foreach ($util->getSupportedRegions() as $region) {
        $metadata = $util->getMetadataForRegion($region);
        if (!$metadata || !$metadata->getGeneralDesc()) {
            continue;
        }

        $countryCode = $util->getCountryCodeForRegion($region);
        if ($countryCode < 1) {
            continue;
        }

        $possibleLengths = array_filter(
            $metadata->getGeneralDesc()->getPossibleLength(),
            static fn(int $length): bool => $length > 0
        );
        if (!$possibleLengths) {
            continue;
        }

        $trunkPrefixLength = strlen(preg_replace('/\D/', '', (string)$metadata->getNationalPrefix()));
    // Le préfixe national peut être saisi en plus du numéro significatif.
        $maxDigits = min(15, max($possibleLengths) + $trunkPrefixLength);
        $countryName = Locale::getDisplayRegion('en_' . $region, 'fr');
        if (!$countryName || $countryName === $region) {
            $countryName = $region;
        }

        $regions[$region] = [
            'region' => $region,
            'name' => $countryName,
            'calling_code' => '+' . $countryCode,
            'max_digits' => $maxDigits,
        ];
    }

    uasort($regions, static fn(array $a, array $b): int => strcoll($a['name'], $b['name']));
    return $regions;
}

function numero_telephone_e164(string $numeroNational, string $region): ?string
{
    $region = strtoupper(trim($region));
    $regions = indicatifs_telephoniques();
    if (!isset($regions[$region])) {
        return null;
    }

    $digits = preg_replace('/\D/', '', $numeroNational);
    if ($digits === '' || strlen($digits) > $regions[$region]['max_digits']) {
        return null;
    }
    if ($region === 'CI' && !preg_match('/\A(?:07|01|05)/', $digits)) {
        return null;
    }

    try {
        $util = PhoneNumberUtil::getInstance();
        $number = $util->parse($digits, $region);
        if (!$util->isValidNumberForRegion($number, $region)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    } catch (Throwable $exception) {
        return null;
    }
}

function decomposer_telephone(string $numeroStocke): array
{
    $util = PhoneNumberUtil::getInstance();
    $regions = indicatifs_telephoniques();
    $region = 'CI';
    $national = preg_replace('/\D/', '', $numeroStocke);

    try {
        $number = $util->parse($numeroStocke, str_starts_with(trim($numeroStocke), '+') ? null : 'CI');
        $regionDetectee = $util->getRegionCodeForNumber($number);
        if ($regionDetectee !== null && isset($regions[$regionDetectee])) {
            $region = $regionDetectee;
            $national = preg_replace('/\D/', '', $util->format($number, PhoneNumberFormat::NATIONAL));
        }
    } catch (Throwable $exception) {
        // Keep the national digits fallback for legacy values that are not parseable.
    }

    $national = substr($national, 0, $regions[$region]['max_digits']);
    return ['region' => $region, 'national' => $national];
}

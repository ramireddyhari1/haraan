<?php

declare(strict_types=1);

namespace App\Support;

/**
 * GST state codes, as printed in the first two digits of a GSTIN. Used to decide
 * whether commission GST is CGST + SGST (same state) or IGST (inter-state), and to
 * print the place of supply on a tax invoice.
 */
final class GstStates
{
    public const OPTIONS = [
        '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura',
        '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand',
        '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra & Nagar Haveli and Daman & Diu', '27' => 'Maharashtra', '29' => 'Karnataka',
        '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
        '34' => 'Puducherry', '35' => 'Andaman & Nicobar Islands', '36' => 'Telangana',
        '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];

    public static function name(?string $code): ?string
    {
        return $code !== null ? (self::OPTIONS[$code] ?? null) : null;
    }

    /** The state a GSTIN is registered in, or null if it isn't a valid-looking GSTIN. */
    public static function fromGstin(?string $gstin): ?string
    {
        $code = $gstin !== null && preg_match('/^(\d{2})[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/', strtoupper(trim($gstin)), $m) ? $m[1] : null;

        return $code !== null && isset(self::OPTIONS[$code]) ? $code : null;
    }
}

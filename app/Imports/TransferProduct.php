<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TransferProduct implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection): void
    {
    }

    public static function serialNumber(array $row): string
    {
        foreach (['imei_no', 'imei_number', 'serial_no', 'serial_number', 'mobile_number'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                $serialNumber = $row[$key];

                if (is_float($serialNumber) && floor($serialNumber) === $serialNumber) {
                    $serialNumber = sprintf('%.0f', $serialNumber);
                }

                return trim((string) $serialNumber);
            }
        }

        return '';
    }

    public static function description(array $row): string
    {
        foreach (['item_description', 'description'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    public static function transferQuantity(array $row): mixed
    {
        foreach (['transfer_quantity', 'transfer_qty', 'quantity'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                return $row[$key];
            }
        }

        return null;
    }

    public static function hasRequiredColumns(array $row): bool
    {
        return self::hasAnyColumn($row, ['imei_no', 'imei_number', 'serial_no', 'serial_number', 'mobile_number'])
            && self::hasAnyColumn($row, ['transfer_quantity', 'transfer_qty', 'quantity']);
    }

    private static function hasAnyColumn(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                return true;
            }
        }

        return false;
    }
}

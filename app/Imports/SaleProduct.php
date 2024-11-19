<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SaleProduct implements ToCollection , WithBatchInserts,WithChunkReading
{
    
    public function collection(Collection $rows)
    {
      

        $filteredData = $rows->map(function ($row) {
            $price = $row['price'] ?? null; // Check if 'price' column exists in the row

            // If 'price' column exists and has a value, use it, otherwise, fetch from product
            if ($price !== null) {
                return [
                    'serial_no' => $row['serial_no'],
                    'discount_name' => $row['discount_name'],
                    'quantity' => $row['quantity'],
                    'price' => $price, // Use price from Excel
                ];
            } else {
                return [
                    'serial_no' => $row['serial_no'],
                    'discount_name' => $row['discount_name'],
                    'quantity' => $row['quantity'],
                ];
            }
        });

        // You can now work with $filteredData, which contains only the required columns
        // For example, you can pass it to your controller, return a response, or perform other actions
    }
    public function batchSize(): int
    {
        return 500; // Reduce batch size for better performance
    }

    public function chunkSize(): int
    {
        return 500; // Adjust chunk size to manage memory effectively
    }
}

<?php

namespace App\Imports;

use Illuminate\Support\Facades\Validator;
use illuminate\Support\Facades\log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Hash;
use Illuminate\Support\Facades\DB;

class ProductImport implements ToModel, WithHeadingRow
{

    private $request;
    private $filePath;
    private $batchNo;
    private $validationErrors = [];
    private $serialNovalidation = [];
    private $rowCount = 0;

    public function __construct(Request $request, $filePath, $batchNo)
    {
        $this->request = $request;
        $this->filePath = $filePath;
        $this->batchNo = $batchNo;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    public function model(array $row)
    {
        $existingProduct = Product::where('serial_no', $row['imei_number'])->first();

        if ($existingProduct) {
            // Serial number already exists, return an error
            $this->serialNovalidation[] = $row['imei_number'];

            return null;
        }
        // Compare product_sub_category with request->product_sub_category_name.
        if ($row['product_type'] !== request()->input('product_category_name ') && $row['sub_category'] !== request()->input('product_sub_category_name')) {
            // Handle the case where sub_category does not match the product_sub_category_name.

            // Log the error for debugging or record-keeping.
            // Log::error('Validation failed for a row: ' . json_encode($row));
            $this->validationErrors[] = 'Validation failed for this row. The product type or sub category does not match the expected values.';
            return null;
        } else {
            if (!empty($row['item_number'])) {
                $this->rowCount++;
                $product = Product::create([
                    'item_number' => $row['item_number'],
                    'description' => $row['item_description'],
                    'sale_type_id' => $this->request->input('product_category'),
                    'category_id' => $this->request->input('product_category'),
                    'sub_category_id' => $this->request->input('product_sub_category'),
                    'color_id' => $this->request->input('color'),
                    'total_quantity' => $row['qty'],
                    'batch_no' => $this->batchNo,
                    'created_date' => now()->format('Y-m-d'),
                    'main_store_qty' => $row['qty'],
                    'serial_no' => $row['imei_number'],
                    'sub_inventory' => $row['sub_inventory'] ?? null,
                    'locator' => $row['locator'] ?? null,
                    'price' => $row['price'],
                    'status' => 'new',
                    'sale_status' => 'stock',
                    'created_by' => auth()->user()->id,
                ]);

                // Ensure product is saved before inserting into another table
                DB::table('transaction_audits')->insert([
                    'store_id' => 1,
                    'sales_type_id' => $product->sale_type_id, // Corrected variable name
                    'product_id' => $product->id,
                    'item_number' => $product->item_number,
                    'description' => $product->description,
                    'received' =>  $product->main_store_qty,
                    'stock' =>  $product->main_store_qty,
                    'created_date' => now(),
                    'status' => 'upload',
                    'created_at' => now(),
                    'created_by' => auth()->user()->id,
                ]);

                return $product;
            }
        }
    }
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }
    public function serialNovalidation()
    {
        return $this->serialNovalidation;
    }
    public function getRowCount()
    {
        return $this->rowCount;
    }
}

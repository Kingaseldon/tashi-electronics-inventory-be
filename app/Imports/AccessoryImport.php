<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Services\SerialNumberGenerator;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccessoryImport implements ToModel, WithHeadingRow
{
    private $request;
    private $filePath;
    private $batchNo;
    private $validationErrors = [];
    private $addedQuantity = [];
    private $processedSerialNumbers = [];

    public $totalQuantity = 0;

    public function __construct(Request $request, $filePath, $batchNo)
    {
        $invoice = new SerialNumberGenerator();
        $this->request = $request;
        $this->filePath = $filePath;
        $this->batchNo = $invoice->batchNumber('Product', 'created_date');
    }

    /**
     * @param Collection $collection
     */
    public function model(array $row)
    {

        // Create a new Product instance with the retrieved values
        if ($row['product_type'] !== request()->input('product_category_name ') && $row['sub_category'] !== request()->input('product_sub_category_name')) {
            // Handle the case where sub_category does not match the product_sub_category_name.

            // Log the error for debugging or record-keeping.
            // Log::error('Validation failed for a row: ' . json_encode($row));
            $this->validationErrors[] = 'Validation failed for this row. The product type or sub category does not match the expected values.';
            return null;
        } else {

            if (!empty($row['item_number'])) {
                $this->totalQuantity += $row['qty'];
                $existingProduct = Product::where('serial_no', $row['item_number'])->first();



                if ($existingProduct) {
                    // Product with the same serial number already exists, update the quantity
                    $existingProduct->total_quantity += $row['qty'];
                    $existingProduct->main_store_qty += $row['qty'];
                    $existingProduct->price = $row['price_per_unit'];
                    $existingProduct->save();

                    DB::table('transaction_audits')->insert([
                        'store_id' => 1,
                        'sales_type_id' =>  $existingProduct->sale_type_id, // Corrected variable name
                        'product_id' =>  $existingProduct->id,
                        'item_number' =>  $existingProduct->item_number,
                        'description' =>  $existingProduct->description,
                        'received' =>   $row['qty'],
                        'stock' =>   $row['qty'],
                        'created_date' => now(),
                        'status' => 'upload',
                        'created_at' => now(),
                        'created_by' => auth()->user()->id,
                    ]);

                    $this->addedQuantity[] = $row['qty'] . ' Quantity added to existing product with serial number: ' . $row['item_number'];
                } else {

                    $product = Product::create([
                        'item_number' => $row['item_number'],
                        'serial_no' => $row['item_number'],
                        'description' => $row['description'],
                        'price' => $row['price_per_unit'],
                        'sale_type_id' => $this->request->input('product_category'),
                        'category_id' => $this->request->input('product_category'),
                        'sub_category_id' => $this->request->input('product_sub_category'),
                        'color_id' => $this->request->input('color'),
                        'total_quantity' => $row['qty'],
                        'batch_no' => $this->batchNo,
                        'created_date' => date('Y-m-d', strtotime(Carbon::now())),
                        'main_store_qty' => $row['qty'],
                        'status' => 'new',
                        'sale_status' => 'stock',
                        'sub_inventory' => $row['sub_inventory'] ?? null,
                        'locator' => $row['locator'] ?? null,
                        'created_by' => auth()->user()->id,
                    ]);

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
    }
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }
    public function getTotalQuantity()
    {
        return $this->totalQuantity;
    }
    public function getQuantity()
    {
        return $this->addedQuantity;
    }
}

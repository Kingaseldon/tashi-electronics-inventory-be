<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Http\Request;
use App\Models\Product;
use Carbon\Carbon;


class SimImport implements ToModel, WithHeadingRow
{
    private $request;
    private $filePath;
    private $batchNo;
    private $validationErrors = [];
    private $validation = [];
    private $rowCount = 0;

    public function __construct(Request $request, $filePath, $batchNo)
    {
        $this->request = $request;
        $this->filePath = $filePath;
        $this->batchNo = $batchNo;
    }
    /**
     * @param Collection $collection
     */
    public function model(array $row)
    {
        $existingProduct = Product::where('serial_no', $row['mobile_number'])->first();

        if ($existingProduct) {
            // Serial number already exists, return an error
            $this->validation[] = $row['mobile_number'];
            return null;
        }
        if ($row['product_type'] !== request()->input('product_category_name ') && $row['sub_category'] !== request()->input('product_sub_category_name')) {
            // Handle the case where sub_category does not match the product_sub_category_name.

            // Log the error for debugging or record-keeping. 
            // Log::error('Validation failed for a row: ' . json_encode($row));
            $this->validationErrors[] = 'Validation failed for this row. The product type or sub category does not match the expected values.';
            return null;
        } else {

            if (!empty($row['mobile_number'])) {
                $this->rowCount++;
                // Create a new Product instance with the retrieved values
                return new Product([
                    'item_number' => 'Sim',
                    'description' => 'Subscriber Identity Module',
                    'sale_type_id' => $this->request->input('product_category'),
                    'category_id' => $this->request->input('product_category'),
                    'sub_category_id' => $this->request->input('product_sub_category'),
                    'total_quantity' => $row['qty'],
                    'main_store_qty' => $row['qty'],
                    'batch_no' => $this->batchNo,
                    'created_date' => date('Y-m-d', strtotime(Carbon::now())),
                    'price' => $row['price'],
                    'serial_no' => $row['mobile_number'],
                    'iccid' => $row['iccid'],
                    'status' => 'new',
                    'sale_status' => 'stock',
                    'created_by' => auth()->user()->id,
                ]);
            }
        }
    }
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }
    public function getValidation()
    {
        return $this->validation;
    }
    public function getRowCount()
    {
        return $this->rowCount;
    }
}
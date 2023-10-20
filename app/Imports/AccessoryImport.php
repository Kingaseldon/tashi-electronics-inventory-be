<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Services\SerialNumberGenerator;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AccessoryImport implements ToModel, WithHeadingRow
{
    private $request;
    private $filePath;
    private $batchNo;

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
        return new Product([
            'item_number' => $row['part_number'],
            'serial_no' => $row['part_number'],
            'description' => $row['description'],
            'price' => $row['price_per_unit'],
            'sale_type_id' => $this->request->input('product_category'),
            'category_id' => $this->request->input('product_category'),
            'sub_category_id' => $this->request->input('product_sub_category'),
            'color_id' => $this->request->input('color'),
            'total_quantity' => $row['quantity'],
            'batch_no' => $this->batchNo,
            'created_date' => date('Y-m-d', strtotime(Carbon::now())),
            'quantity' => $row['quantity'],
            'status' => 'new',
            'sale_status' => 'stock', 
            'created_by' => auth()->user()->id,
        ]);
    }
}


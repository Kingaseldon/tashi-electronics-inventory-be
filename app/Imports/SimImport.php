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
        // Create a new Product instance with the retrieved values
        return new Product([
            'item_number' => 'Sim',
            'description' => 'Subscriber Identity Module',
            'sale_type_id' => $this->request->input('product_category'),
            'category_id' => $this->request->input('product_category'),
            'sub_category_id' => $this->request->input('product_sub_category'),
            'total_quantity' => $row['qty'],
            'quantity' => $row['qty'],
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
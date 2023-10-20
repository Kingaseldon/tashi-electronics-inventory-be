<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Hash;

class ProductImport implements ToModel, WithHeadingRow
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
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */    
    public function model(array $row)
    {  
        return new Product([
            'item_number' => $row['item_number'],
            'description' => $row['item_description'],
            'sale_type_id' => $this->request->input('product_category'),
            'category_id' => $this->request->input('product_category'),
            'sub_category_id' => $this->request->input('product_sub_category'),
            'color_id' => $this->request->input('color'),
            'total_quantity' => $row['qty'],
            'batch_no' => $this->batchNo,
            'created_date' => date('Y-m-d', strtotime(Carbon::now())),
            'quantity' => $row['qty'],
            'serial_no' => $row['imei_number'],
            'sub_inventory' => $row['sub_inventory'],
            'locator' => $row['locator'],
            'price' => $row['price'],
            'status' => 'new',      
            'sale_status' => 'stock',      
            'created_by' => auth()->user()->id,      
        ]);
    }
}
<?php 

namespace App\Services;

// use App\Models\SaleVoucher;

class SerialNumberGenerator
{
    private $startDate;
    private $endDate;

    public function __construct()
    {
        $this->startDate = date('Y')."-01-01";
        $this->endDate = date('Y')."-12-31";
    }

    public function requisitionNumber($modelClass, $dateColumn)
    {
        $model = 'App\Models\\' . $modelClass;

        $lastRow = $model::orderBy('id', 'desc')->first();
        if ($lastRow && !empty($lastRow->requisition_number)) {
            $requestedNo = $lastRow->requisition_number;
            $strToBeRemoved = 'REQ-' . date('d-m-Y') . '-';
            $invoiceNo = (int) explode('-', str_replace($strToBeRemoved, '', $requestedNo))[0];
            $serialInvoiceNo = $invoiceNo + 1;
        } else {
            $serialInvoiceNo = 1;
        }

        return 'REQ-' . date('d-m-Y') . '-' . str_pad($serialInvoiceNo, 4, '0', STR_PAD_LEFT);
    }



    public function movementNumber($modelClass, $dateColumn)
    {

        $model = 'App\Models\\' . $modelClass;

        $totalRows = $model::orderBy('id', 'desc')->first();
        if ($totalRows) {
            if($totalRows->product_movement_no != ""){
                $strToBeRemoved = substr($totalRows->product_movement_no, 0, 5);
                $InvoiceNo = explode($strToBeRemoved, $totalRows->product_movement_no)[1];
                $serialInvoiceNo = (int) $InvoiceNo + 1;
            }else{
                $serialInvoiceNo = '0001';
            }
        } else {
            $serialInvoiceNo = 1;
        }
        return $serialInvoiceNo = 'TEMN-' .str_pad($serialInvoiceNo, 4, '0', STR_PAD_LEFT);
    }

    //invoice generation in main store
    public function mainInvoiceNumber($modelClass, $dateColumn)
    {
        $model = 'App\Models\\' . $modelClass;
        $totalRows = $model::where('regional_id','=', null)->orderBy('id', 'desc')->first();
        
        if ($totalRows) {
            if($totalRows->invoice_no != ""){
                // $strToBeRemoved = substr($totalRows->invoice_no, 0, 5);
                $strToBeRemoved = substr($totalRows->invoice_no, -4);
                // $InvoiceNo = explode($strToBeRemoved, $totalRows->invoice_no)[1];
                $invoiceNo = (int) $strToBeRemoved + 1;
            }else{
                $invoiceNo = '0001';
            }
        } else {
            $invoiceNo = 1;
        }
        return $invoiceNo = 'Inv-'.str_pad($invoiceNo, 4, '0', STR_PAD_LEFT);
    }

    //inv0ice generation in regionals
    public function invoiceNumber($modelClass, $dateColumn, $regionId, $firstWord)
    {
        $model = 'App\Models\\' . $modelClass;
        $totalRows = $model::with('region')->where('regional_id', $regionId)->orderBy('id', 'desc')->first();
        if ($totalRows) {
            if($totalRows->invoice_no != ""){
                $strToBeRemoved = substr($totalRows->invoice_no, -4);
                // $InvoiceNo = explode($strToBeRemoved, $totalRows->invoice_no)[1];
                $invoiceNo = (int) $strToBeRemoved + 1;
            }else{
                $invoiceNo = '0001';
            }
        } else {
            $invoiceNo = 1;
        }
        return $invoiceNo = 'Inv-'. $firstWord.'-'.str_pad($invoiceNo, 4, '0', STR_PAD_LEFT);
    }

    //inv0ice generation in regionals
    public function extensionInvoiceNumber($modelClass, $dateColumn, $extensionId, $firstWord)
    {
        $model = 'App\Models\\' . $modelClass;
        $totalRows = $model::with('extension')->where('region_extension_id', $extensionId)->orderBy('id', 'desc')->first();
        if ($totalRows) {
            if($totalRows->invoice_no != ""){
                $strToBeRemoved = substr($totalRows->invoice_no, -4);
                // $InvoiceNo = explode($strToBeRemoved, $totalRows->invoice_no)[1];
                $invoiceNo = (int) $strToBeRemoved + 1;
            }else{
                $invoiceNo = '0001';
            }
        } else {
            $invoiceNo = 1;
        }
        return $invoiceNo ='Inv-'. $firstWord. '-' .str_pad($invoiceNo, 4, '0', STR_PAD_LEFT);
    }

    //receipt for main store sale
    public function mainReceiptNumber($modelClass, $dateColumn)
    {
        $model = 'App\Models\\' . $modelClass;
        $totalRows = $model::orderBy('id', 'desc')->first();
        if ($totalRows) {
            if($totalRows->receipt_no != ""){
                $strToBeRemoved = substr($totalRows->receipt_no, -4);
                // $receiptNo = explode($strToBeRemoved, $totalRows->receipt_no)[1];
                $receiptNo = (int) $strToBeRemoved + 1;
            }else{
                $receiptNo = '0001';
            }
        } else {
            $receiptNo = 1;
        }

        return $receiptNo = 'Recei-Main-' .str_pad($receiptNo, 5, '0', STR_PAD_LEFT);
    }

    //receipt for regional store sale
    public function receiptNumber($modelClass, $dateColumn, $firstWord)
    {
        $model = 'App\Models\\' . $modelClass;
        $user = auth()->user();
        $roles = $user->roles;

        $isSuperUser = false;

        foreach ($roles as $role) {
            if ($role->is_super_user == 1) {
                $isSuperUser = true;
                break;
            }
        }
         $totalRows="";
        if($isSuperUser){
            $totalRows = $model::with('saleVoucher')->whereHas('saleVoucher', function ($query) {
                $query->where('regional_id', 'saleVoucher.region.id' );
            })->orderBy('id', 'desc')->first();
        }
        else{
            $totalRows = $model::with('saleVoucher')->whereHas('saleVoucher', function ($query) {
                $query->where('regional_id', auth()->user()->assignAndEmployee->regional_id);
            })->orderBy('id', 'desc')->first();
        }

        if ($totalRows) {
            if($totalRows->receipt_no != ""){
                $strToBeRemoved = substr($totalRows->receipt_no,-4);
                // $receiptNo = explode($strToBeRemoved, $totalRows->receipt_no)[1];
                $receiptNo = (int) $strToBeRemoved + 1;
            }else{
                $receiptNo = '0001';
            }
        }
        else {
            $receiptNo = 1;
        }

        return $receiptNo ='Recei-'. $firstWord. '-'.str_pad($receiptNo, 5, '0', STR_PAD_LEFT);
    }

    //receipt for regional store sale
    public function extensionReceiptNumber($modelClass, $dateColumn, $firstWord)
    {
        $model = 'App\Models\\' . $modelClass;
        $user = auth()->user();
        $roles = $user->roles;

        $isSuperUser = false;

        foreach ($roles as $role) {
            if ($role->is_super_user == 1) {
                $isSuperUser = true;
                break;
            }
        }
        $totalRows="";
        if ($isSuperUser) {
            $totalRows = $model::with('saleVoucher')->whereHas('saleVoucher', function ($query) {
                $query->where('region_extension_id', 'saleVoucher.extension.id');
            })->orderBy('id', 'desc')->first();
        }
        else{
            $totalRows = $model::with('saleVoucher')->whereHas('saleVoucher', function ($query) {
                $query->where('region_extension_id', auth()->user()->assignAndEmployee->extension_id);
            })->orderBy('id', 'desc')->first();
        }       
 
        if ($totalRows) {
            if($totalRows->receipt_no != ""){
                $strToBeRemoved = substr($totalRows->receipt_no, -4);
                // $receiptNo = explode($strToBeRemoved, $totalRows->receipt_no)[1];
                $receiptNo = (int) $strToBeRemoved + 1;
            }else{
                $receiptNo = '0001';
            }
        } else {
            $receiptNo = 1;
        }

        return $receiptNo = 'Recei-'.$firstWord.'-' .str_pad($receiptNo, 5, '0', STR_PAD_LEFT);
    }

    ///batch number is generated in batch when uploading the products
    public function batchNumber($modelClass, $dateColumn)
    {
        $model = 'App\Models\\' . $modelClass;

        $lastRow = $model::orderBy('id', 'desc')->first();
        if ($lastRow && !empty($lastRow->batch_no)) {
            $lastBatchNumber = $lastRow->batch_no;
            $strToBeRemoved = substr($lastBatchNumber, 0, 6);
            $invoiceNo = (int) explode($strToBeRemoved, $lastBatchNumber)[1];
            $serialInvoiceNo = $invoiceNo + 1;

        } else {
            $serialInvoiceNo = 1;
        }

        return 'Batch-' . str_pad($serialInvoiceNo, 4, '0', STR_PAD_LEFT);
    }
}


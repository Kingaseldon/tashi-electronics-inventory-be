<?php

namespace App\Http\Controllers\Replacement;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Extension;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\Replace;
use App\Models\ReturnProduct;
use App\Models\SaleVoucher;
use App\Models\Store;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use DB;
use Carbon\Carbon;
class ReplaceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:replacements.view')->only('index', 'show');
        $this->middleware('permission:replacements.update')->only('update');


    }
    public function index()
    {
        try {

            $replace = Replace::where('status', '=', 'pending')->get();


            return response([
                'message' => 'success',
                'replace' => $replace,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {

            $replace = Replace::where('id', '=', $id)->first();

            return response([
                'message' => 'success',
                'replace' => $replace,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, SerialNumberGenerator $invoice)
    {

        $oldDetails = $request->input('oldDetails');
        $newDetails = $request->input('newDetails');
        $region = $request->input('region');
        $extension = $request->input('extension');
        $customerType = $request->input('customerType');
        $customerName = $request->input('customerName');
        $customerNo = $request->input('customerNo');
        $user = auth()->user();
        $returnID=$request->input('returnID');

        DB::beginTransaction();

        try {
            $productID = Product::where('serial_no', '=', $newDetails['serialNumber'])->first();

            if ($productID == null) {
                return response()->json([
                    'message' => 'Serial number not found',
                ], 203);
            }


            $replace = Replace::where('return_id','=', $returnID);      
            $replace->status = "replaced";
            $replace->save();

            $regionName = Region::where('id', '=', $region)->first();
            $extensionName = Extension::where('id', '=', $extension)->first();
            $storeID = "";

            //unique number generator
            $invoiceNo = "";
            if ($region === null && $extension === null) {
                $invoiceNo = $invoice->mainInvoiceNumber('SaleVoucher', 'invoice_date');
                $storeID = 1;

            } elseif ($region != null) {
                $wordArray = explode(' ', $regionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $regionID = $regionName->id;
                $invoiceNo = $invoice->invoiceNumber('SaleVoucher', 'invoice_date', $regionID, $regionName->name);
                $store = Store::where('region_id', '=', $region)->first();
                $storeID = $store->id;

            } else {
                $wordArray = explode(' ', $extensionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $extensionID = $extensionName->id;
                $invoiceNo = $invoice->extensionInvoiceNumber('SaleVoucher', 'invoice_date', $extensionID, $extensionName->name);
                $store = Store::where('extension_id', '=', $extension)->first();
                $storeID = $store->id;

            }
          

            $customer = "";
            if ($customerType == 2) {
                $customer = Customer::where('customer_name', '=', $customerName)->where('contact_no', '=', $customerNo)->first();
            }

            $saleVoucher = new SaleVoucher;
            $saleVoucher->invoice_no = $invoiceNo;
            $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
            $saleVoucher->customer_id = $customerType == 2 ? $customer->id : null;
            $saleVoucher->sale_type = $newDetails['category'];
            $saleVoucher->regional_id = $region;
            $saleVoucher->region_extension_id = $extension;
            $saleVoucher->walk_in_customer = $customerType == 2 ? null : $customerName;
            $saleVoucher->contact_no = $customerType == 2 ? null : $customerNo;
            $saleVoucher->gross_payable = 0;
            $saleVoucher->net_payable = 0;
            $saleVoucher->status = "closed";
            $saleVoucher->remarks = $newDetails['description'];
            $saleVoucher->save();
            $saleOrderDetails = "";


            $saleOrderDetails = [
                'sale_voucher_id' => $saleVoucher->id,
                'product_id' => $productID->id,
                'quantity' => 1,
                'price' => 0,
                'total' => 0,
                'discount_type_id' => null,
            ];

            // Insert sale order details
            $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
            //replaces table

            $productTable = Product::where('serial_no', '=', $newDetails['serialNumber'])->first();

            if ($region === null && $extension === null) {
                $mainStoreQty = $productTable->main_store_qty;
                $mainStoreSoldQty = $productTable->main_store_sold_qty;

                $productTable->update([
                    'main_store_qty' => $mainStoreQty - $newDetails['qty'],
                    'main_store_sold_qty' => $mainStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);

            } elseif ($region != null) {
                $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->loggedInAssignRegion()->first();
                if ($Transaction == null) {
                    return response()->json([
                        'message' => 'Failed to locate this product: ' . $newDetails['serialNumber'],
                    ], 203);
                }
                $regionStoreQty = $productTable->region_store_qty;
                $regionStoreSoldQty = $productTable->region_store_sold_qty;
                $productTable->update([
                    'region_store_qty' => $regionStoreQty - $newDetails['qty'],
                    'region_store_sold_qty' => $regionStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
                $regionQty = $Transaction->region_store_quantity;
                $soldQty = $Transaction->sold_quantity;

                $Transaction->update([
                    'region_store_quantity' => $regionQty - $newDetails['qty'],
                    'sold_quantity' => $soldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);



            } else {
                $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->LoggedInAssignExtension()->first();

                if ($Transaction == null) {
                    return response()->json([
                        'message' => 'Failed to locate this product: ' . $newDetails['serialNumber'],
                    ], 203);
                }

                $extensionStoreQty = $productTable->extension_store_qty;
                $extensionStoreSoldQty = $productTable->extension_store_sold_qty;
                $productTable->update([
                    'extension_store_qty' => $extensionStoreQty - $newDetails['qty'],
                    'extension_store_sold_qty' => $extensionStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
                $Qty = $Transaction->store_quantity;
                $soldQty = $Transaction->sold_quantity;

                $Transaction->update([
                    'store_quantity' => $Qty - $newDetails['qty'],
                    'sold_quantity' => $soldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);

            }


        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();

        return response()->json([
            'message' => 'Product has been replaced Successfully',
            'invoice_no' => $invoiceNo,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

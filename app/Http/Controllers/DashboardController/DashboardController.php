<?php

namespace App\Http\Controllers\DashboardController;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Extension;
use App\Models\PaymentHistory;
use App\Models\Product;
use App\Models\Region;
use App\Models\SaleVoucher;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboards.view')->only('index');
        $this->middleware('permission:dashboards.invoices')->only('invoice');
        $this->middleware('permission:dashboards.payments')->only('payment');
        $this->middleware('permission:dashboards.sales')->only('sale');

    }
    public function index()
    {
        try {
            $regions = Region::count();
            $extensions = Extension::count();
            $customers= Customer::count();
            $employees= User::count();
            $invoices= SaleVoucher::count();
           
            $stores = $regions+ $extensions+1;
            $statusData = [
                [
                    'icon' => 'nc-icon nc-cart-simple text-warning',
                    'title' => 'Stores',
                    'number' => $stores,
                ],
                [
                    'icon' => 'nc-icon nc-single-02 text-success',
                    'title' => 'Customers',
                    'number' => $customers,
                ],
                [
                    'icon' => 'nc-icon nc-paper-2 text-danger',
                    'title' => 'Invoices',
                    'number' => $invoices,
                ],
                [
                    'icon' => 'nc-icon nc-circle-09 text-primary',
                    'title' => 'Employees',
                    'number' => $employees,
                ],
            ];
            return response([
                'message' => 'success',
                
                'status'=> $statusData,
              
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
     public function invoice(){
        try {
            $invoice = SaleVoucher::with('saleVoucherDetails','customer')->orderBy('id', 'desc')->get();      

            return response([
                'message' => 'success',
                'invoices' => $invoice,
               

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
     }
    public function payment()
    {
        try {
            $payment = PaymentHistory::with('saleVoucher')->orderBy('id', 'desc')->get();

            return response([
                'message' => 'success',
                'payments' => $payment,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function sale(Request $request)
    {
        try {
            $sales = Product::query()
                ->whereYear('invoice_date', $request->year)
                ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                ->groupBy('month', 'st.name')
                ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                ->orderBy('month', 'asc')
                ->orderBy('st.name', 'asc')
                ->get();


            return response([
                'message' => 'success',
                'sales' => $sales,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

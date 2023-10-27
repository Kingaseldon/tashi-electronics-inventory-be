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
use App\Http\Controllers\DashboardController\DB;

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
            $customers = Customer::count();
            $employees = User::count();
            $invoices = SaleVoucher::count();

            $stores = $regions + $extensions + 1;
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
                    'number' => $employees - 1,
                ],
            ];
            return response([
                'message' => 'success',

                'status' => $statusData,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function invoice()
    {
        try {
            $user = auth()->user();

            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $invoice = [];
            if ($isSuperUser) {
                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer')->orderBy('id', 'desc')->get();
            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer')->orderBy('id', 'desc')->LoggedInAssignRegion()->get();

            } else {

                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer')->orderBy('id', 'desc')->LoggedInAssignExtension()->get();

            }

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
            $user = auth()->user();

            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $payment = [];
            if ($isSuperUser) {

                $payment = PaymentHistory::with('saleVoucher')->orderBy('id', 'desc')->get();

            } elseif ($employee->assignAndEmployee->regional_id != null) {

                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->where('sale_vouchers.regional_id', auth()->user()->assignAndEmployee->regional_id)
                    ->get();
            } else {

                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->where('sale_vouchers.region_extension_id', auth()->user()->assignAndEmployee->extension_id)
                    ->get();

            }

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
            $user = auth()->user();

            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $sales = [];
            
            if ($isSuperUser) {
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
            } 
            elseif ($employee->assignAndEmployee->regional_id != null) {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->where('sv.regional_id', auth()->user()->assignAndEmployee->regional_id)
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            } 
            else {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->where('sv.region_extension_id', auth()->user()->assignAndEmployee->extension_id)
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            }



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

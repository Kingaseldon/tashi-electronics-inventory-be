<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reports.postedsalesinvoice')->only('salesinvoice');
        $this->middleware('permission:reports.onhanditems')->only('onhand');
        $this->middleware('permission:reports.salesandstocks')->only('stock');
        $this->middleware('permission:reports.salesorderlist')->only('orderlist');
        $this->middleware('permission:reports.cashreceipt')->only('cash');
        $this->middleware('permission:reports.onlinereceipt')->only('online');

    }
    public function salesinvoice()
    {
        try {
            $sales = DB::table('sale_vouchers as sv')
                ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                ->select(
                    'sv.invoice_no',
                    'sv.invoice_date',
                    DB::raw('IF(sv.customer_id IS NOT NULL, c.customer_name, sv.walk_in_customer) AS customerName'),
                    DB::raw('IF(sv.customer_id IS NOT NULL, c.contact_no, sv.contact_no) AS contactNo'),
                    DB::raw('COALESCE(ph.payment_mode, "--") AS paymentMode'),
                    DB::raw('COALESCE(ph.receipt_no, "--") AS receiptNo'),
                    DB::raw('COALESCE(b.name, "--") AS bankName'),
                    DB::raw('COALESCE(ph.reference_no, "--") AS referenceNo'),
                    DB::raw('COALESCE(ph.paid_at, "--") AS paidAt'),
                    'u.name',
                    'sv.status'
                )
                ->get();

            if ($sales->isEmpty()) {
                $sales = [];
            }
            return response([
                'message' => 'success',
                'sales' => $sales,

            ], 200);
        }
        catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function onhand()
    {
        try {
            $onhand = DB::table('products as p')
                ->leftJoin('product_transactions as pt', 'p.id', '=', 'pt.product_id')
                ->select(
                    'p.id',
                    DB::raw('GROUP_CONCAT(p.item_number) as item_number'),
                    DB::raw('GROUP_CONCAT(p.description) as description'),
                    DB::raw('GROUP_CONCAT(p.serial_no) as serial_no'),
                    DB::raw('COALESCE(GROUP_CONCAT(p.sub_inventory), "--") AS subInventory'),
                    DB::raw('COALESCE(GROUP_CONCAT(p.locator), "--") AS locator'),
                    DB::raw('COALESCE(GROUP_CONCAT(p.iccid), "--") AS iccid'),
                    DB::raw('SUM(COALESCE(p.quantity, 0) + COALESCE(pt.store_quantity, 0) + COALESCE(pt.region_store_quantity, 0)) AS total_qty')
                )
                ->groupBy('p.id')
                ->havingRaw('total_qty != 0')->orderBy('p.id')
                ->get();


            if ($onhand->isEmpty()) {
                $onhand = [];
            }
            return response([
                'message' => 'success',
                'onhand' => $onhand,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function stock()
    {
        try {
            $stock = DB::table('products')
                ->select('item_number', 'serial_no', 'description', 'total_quantity', 'price')
                ->get();


            if ($stock->isEmpty()) {
                $stock = [];
            }
            return response([
                'message' => 'success',
                'stock' => $stock,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function orderlist()
    {
        try {
            $orderlist = DB::table('sale_vouchers as sv')
                ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                ->select(
                    'sv.invoice_no',
                    'sv.invoice_date',
                    DB::raw('IF(sv.customer_id IS NOT NULL, c.customer_name, sv.walk_in_customer) AS customerName'),
                    DB::raw('IF(sv.customer_id IS NOT NULL, c.contact_no, sv.contact_no) AS contactNo'),
                    DB::raw('COALESCE(ph.payment_mode, "--") AS paymentMode'),
                    DB::raw('COALESCE(sv.net_payable, "--") AS netPayable'),
                    'u.name as createdBy',
                    'sv.status'
                )
                ->get();

            if ($orderlist->isEmpty()) {
                $orderlist = [];
            }
            return response([
                'message' => 'success',
                'orderlist' => $orderlist,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function cash()
    {
        try {
            $cash = DB::table('sale_vouchers as sv')
                ->select(
                    'ph.cash_amount_paid',
                    'ph.online_amount_paid',
                    'ph.total_amount_paid',
                    'ph.payment_mode',
                    'b.name as bankName',
                    'ph.reference_no',
                    'sv.status',
                    'sv.invoice_date',
                    'u.name AS createdBy'
                )
                ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                ->where(function ($query) {
                    $query->where('ph.payment_mode', 'cash')
                        ->orWhere('ph.payment_mode', 'both');
                })
                ->get();

            if ($cash->isEmpty()) {
                $cash = [];
            }
            return response([
                'message' => 'success',
                'cash' => $cash,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function online()
    {
        try {
            $online = DB::table('sale_vouchers as sv')
                ->select(
                    'ph.cash_amount_paid',
                    'ph.online_amount_paid',
                    'ph.total_amount_paid',
                    'ph.payment_mode',
                    'b.name as bankName',
                    'ph.reference_no',
                    'sv.status',
                    'sv.invoice_date',
                    'u.name AS createdBy'
                )
                ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                ->where(function ($query) {
                    $query->where('ph.payment_mode', 'online')
                        ->orWhere('ph.payment_mode', 'both');
                })
                ->get();

            if ($online->isEmpty()) {
                $online = [];
            }
            return response([
                'message' => 'success',
                'online' => $online,

            ], 200);
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

}
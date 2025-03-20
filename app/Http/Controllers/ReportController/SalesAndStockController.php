<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SalesAndStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {

        $this->middleware('permission:salesandstocks.view')->only('index');
    }
    public function index(Request $request)
    {
        try {
            // Get filter parameters from request
            $salesType = $request->input('category_id');
            $regionId = $request->input('regional_id');
            $extensionId = $request->input('region_extension_id');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            // Raw SQL query with bindings
            $query = "
        WITH Previous_Closing AS (
            SELECT
                a.item_number,
                a.description,
                a.store_id,
                s.store_name,
                COALESCE(SUM(a.received) - SUM(a.sales) - SUM(a.transfer), 0) AS prev_stock
            FROM transaction_audits a
            LEFT JOIN stores s ON a.store_id = s.id
            WHERE DATE(a.created_date) < ?
                AND (? = 'ALL' OR a.sales_type_id = ?)
                AND ((? = 'ALL' OR s.region_id = ?) AND (? = 'ALL' OR s.extension_id = ?))
            GROUP BY a.item_number, a.description, a.store_id, s.store_name
        ),
        Current_Transactions AS (
            SELECT
                a.item_number,
                a.description,
                a.store_id,
                s.store_name,
                COALESCE(SUM(a.received), 0) AS received,
                COALESCE(SUM(a.transfer), 0) AS transfer,
                COALESCE(SUM(a.sales), 0) AS sales
            FROM transaction_audits a
            LEFT JOIN stores s ON a.store_id = s.id
            WHERE DATE(a.created_date) BETWEEN ? AND ?
                AND (? = 'ALL' OR a.sales_type_id = ?)
                AND ((? = 'ALL' OR s.region_id = ?) AND (? = 'ALL' OR s.extension_id = ?))
            GROUP BY a.item_number, a.description, a.store_id, s.store_name
        )
        SELECT
            COALESCE(ct.item_number, pc.item_number) AS item_number,
            COALESCE(ct.description, pc.description) AS description,
            COALESCE(ct.store_id, pc.store_id) AS store_id,
            COALESCE(ct.store_name, pc.store_name) AS store_name,
            COALESCE(pc.prev_stock, 0) AS opening,
            COALESCE(ct.received, 0) AS received,
            COALESCE(ct.transfer, 0) AS transfer,
            COALESCE(ct.sales, 0) AS sales,
            (COALESCE(pc.prev_stock, 0) + COALESCE(ct.received, 0) - (COALESCE(ct.sales, 0) + COALESCE(ct.transfer, 0))) AS closing
        FROM Previous_Closing pc
        LEFT JOIN Current_Transactions ct
            ON pc.item_number = ct.item_number
            AND pc.description = ct.description
            AND pc.store_id = ct.store_id
        UNION ALL
        SELECT
            ct.item_number,
            ct.description,
            ct.store_id,
            ct.store_name,
            COALESCE(pc.prev_stock, 0) AS opening,
            ct.received,
            ct.transfer,
            ct.sales,
            (COALESCE(pc.prev_stock, 0) + ct.received - (ct.sales + ct.transfer)) AS closing
        FROM Current_Transactions ct
        LEFT JOIN Previous_Closing pc
            ON ct.item_number = pc.item_number
            AND ct.description = pc.description
            AND ct.store_id = pc.store_id
        WHERE pc.item_number IS NULL;
    ";

            // Execute query with bindings
            $result = DB::select($query, [
                $fromDate,
                $salesType,
                $salesType,
                $regionId,
                $regionId,
                $extensionId,
                $extensionId,
                $fromDate,
                $toDate,
                $salesType,
                $salesType,
                $regionId,
                $regionId,
                $extensionId,
                $extensionId
            ]);
            // Return response
            return response([
                'message' => 'success',
                'stock' => $result, // Use $finalResults here
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage(),
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
        //
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
    public function update(Request $request, $id)
    {
        //
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

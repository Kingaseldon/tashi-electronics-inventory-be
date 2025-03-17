<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterWarrantyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:master-warranties.view')->only('index', 'show');
        $this->middleware('permission:master-warranties.store')->only('store');
        $this->middleware('permission:master-warranties.update')->only('update');
        $this->middleware('permission:master-warranties.edit-master-warranties')->only('editWarranty');
        // $this->middleware('permission:master-warranties.get-master-warranties')->only('getWarranty');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {

            $warranty = Warranty::with('saleType')->orderBy('id')->get();

            if ($warranty->isEmpty()) {
                $warranty = [];
            }
            return response([
                'message' => 'success',
                'warranty' => $warranty,

            ], 200);
        } catch (\Exception $e) {
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
        $this->validate($request, [

        ]);

        DB::beginTransaction();

        try{
            $warranty = new Warranty;
            $warranty->sale_type_id = $request->sale_type_id;
            $warranty->no_of_years = $request->no_of_years;
            $warranty->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Warranty has been created Successfully'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editWarranty($id)
    {
        try {
            $warranty = Warranty::with('saleType')->find($id);
            //    $extensions = Extension::orderBy('id')->get();
            //    $regions = Region::orderBy('id')->get();

            if (!$warranty) {
                return response()->json([
                    'message' => 'The warranty you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'warranty' => $warranty,
                //    'extension' => $extensions,
                //    'region' => $regions,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
        $this->validate($request, [

        ]);
        DB::beginTransaction();
        try {
            $warranty = warranty::find($id);

            if (!$warranty) {
                return response()->json([
                    'message' => 'The Warranty you are trying to update doesn\'t exist.'
                ], 404);
            }

            $warranty->sale_type_id = $request->sale_type_id;
            $warranty->no_of_years = $request->no_of_years;

            $warranty->save();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Warranty has been updated Successfully'
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
        try{
            Warranty::find($id)->delete();
            return response()->json([
                'message'=>'Warranty Deleted successfully'
            ],200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Warranty cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

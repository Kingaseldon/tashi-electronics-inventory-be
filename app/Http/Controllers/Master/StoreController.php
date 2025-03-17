<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Dzongkhag;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function __construct()
    {
        //  $this->middleware('permission:stores.view')->only('index', 'show');
        //  $this->middleware('permission:stores.store')->only('store');
        //  $this->middleware('permission:stores.update')->only('update');
        //  $this->middleware('permission:stores.edit-stores')->only('editStore');
    }
    public function index()
    {
        try{

            $stores = Store::with('dzongkhag')->orderBy('id')->get();
            $dzongkhags = Dzongkhag::orderBy('id')->get();
            if($stores->isEmpty()){
                $stores = [];
            }
                return response([
                    'message' => 'success',
                    'store' =>$stores,
                    'dzongkhag' =>$dzongkhags,
                ],200);
        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
            'store_name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $store = new Store;
            $store->store_name = $request->store_name;
            $store->dzongkhag_id = $request->dzongkhag;
            $store->code = $request->code;
            $store->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Store has been created Successfully'
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editStore($id)
    {
        try{
            $store = Store::with('dzongkhag')->find($id);
            $dzongkhags = Dzongkhag::orderBy('id')->get();

            if(!$store){
                return response()->json([
                    'message' => 'The Store you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'store' =>$store,
                'dzongkhag' =>$dzongkhags,
            ],200);
        }catch(\Exception $e){
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
            'store_name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $store = Store::find($id);

            if(!$store){
                return response()->json([
                    'message' => 'The Srore you are trying to update doesn\'t exist.'
                ], 404);
            }

            $store->store_name = $request->store_name;
            $store->dzongkhag_id = $request->dzongkhag;
            $store->code = $request->code;
            $store->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Store has been updated Successfully'
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
        try {

            Store::find($id)->delete();

            return response()->json([
                'message' => 'Store deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Store cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

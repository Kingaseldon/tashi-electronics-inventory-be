<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
// use App\Models\Dzongkhag;
use App\Models\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VillageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:villages.view')->only('index', 'show');
        $this->middleware('permission:villages.store')->only('store');
        $this->middleware('permission:villages.update')->only('update');
        $this->middleware('permission:villages.edit-villages')->only('editVillage');
    }
    public function index()
    {
        try{
            // $dzongkhags = Dzongkhag::with('gewogs:id,dzongkhag_id,name', 'gewogs.villages:gewog_id,id,name')->orderBy('name')->get(['id', 'name']);
            $villages= Village::with('gewog.dzongkhag')->orderBy('id')->get();
            if ($villages->isEmpty()) {
                $villages = [];
            }
            return response([
                'message' => 'success',
                'villages' => $villages,
                // 'dzongkhags' => $dzongkhags,
            ], 200);

        }
        catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try Again later'
            ], 500);
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
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $villages = new Village;
            $villages->name = $request->name;
            $villages->gewog_id = $request->gewog_id;
            $villages->code = $request->code;
            $villages->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Village has been created Successfully'
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
    public function editVillage($id)
    {
        try{
            $villages = Village::with('gewog.dzongkhag')->find($id);
            // $dzongkhags = Dzongkhag::with('gewogs:id,dzongkhag_id,name', 'gewogs.villages:gewog_id,id,name')->orderBy('name')->get(['id', 'name']);

            if(!$villages){
                return response()->json([
                    'message' => 'The Village you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'village' =>$villages,
                // 'dzongkhags' =>$dzongkhags,
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
            'name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $villages = Village::find($id);

            if(!$villages){
                return response()->json([
                    'message' => 'The village you are trying to update doesn\'t exist.'
                ], 404);
            }

            $villages->name = $request->name;
            $villages->gewog_id = $request->gewog_id;
            $villages->code = $request->code;
            $villages->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Village has been updated Successfully'
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

            Village::find($id)->delete();

            return response()->json([
                'message' => 'Village has been deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Village cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Gewog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GewogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:gewogs.view')->only('index', 'show');
        $this->middleware('permission:gewogs.store')->only('store');
        $this->middleware('permission:gewogs.update')->only('update');
        $this->middleware('permission:gewogs.edit-gewogs')->only('editGewog');
    }
    public function index()
    {
        try {

            $gewogs = Gewog::with('dzongkhag')->orderBy('id')->get();
            if ($gewogs->isEmpty()) {
                $gewogs = [];
            }
            return response([
                'message' => 'success',
                'gewog' => $gewogs
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

        try {
            $gewog = new Gewog;
            $gewog->name = $request->name;
            $gewog->dzongkhag_id = $request->dzongkhag_id;
            $gewog->code = $request->code;
            $gewog->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Gewog has been created Successfully'
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
    public function editGewog($id)
    {
        try {
            $gewog = Gewog::with('dzongkhag')->find($id);

            if (!$gewog) {
                return response()->json([
                    'message' => 'The gewog you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'gewog' => $gewog
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
            'name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $gewogs = Gewog::find($id);

            if(!$gewogs){
                return response()->json([
                    'message' => 'The Gewog you are trying to update doesn\'t exist.'
                ], 404);
            }

            $gewogs->name = $request->name;
            $gewogs->dzongkhag_id = $request->dzongkhag_id;
            $gewogs->code = $request->code;
            $gewogs->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'gewogs has been updated Successfully'
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

            Gewog::find($id)->delete();

            return response()->json([
                'message' => 'Gewog deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gewog cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use Illuminate\Http\Request;
use DB;

class ExtensionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        // $this->middleware('permission:extensions.view')->only('index', 'show');
        // $this->middleware('permission:extensions.store')->only('store');
        // $this->middleware('permission:extensions.update')->only('update');       
        // $this->middleware('permission:extensions.edit-extensions')->only('editExension');       
    }
    public function index()
    {
        try{

            $extensions = Extension::with('region.dzongkhag')->orderBy('id')->get();
            if($extensions->isEmpty()){
                $extensions = [];
            }   
                return response([
                    'message' => 'success',
                    'extension' =>$extensions
                ],200);
        }catch(Exception $e){
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
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $extensions = new Extension;
            $extensions->regional_id = $request->regional_id;
            $extensions->name = $request->name;        
            $extensions->description = $request->description;
            $extensions->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Extension has been created Successfully'
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
    public function editExtension($id)
    {
        try{
            $extension = Extension::with('region.dzongkhag')->find($id);
                
            if(!$extension){
                return response()->json([
                    'message' => 'The Extension you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'extension' =>$extension
            ],200);
        }catch(Exception $e){
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
            $extension = Extension::find($id);
            
            if(!$extension){
                return response()->json([
                    'message' => 'The Extension you are trying to update doesn\'t exist.'
                ], 404);
            }

            $extension->regional_id = $request->regional_id;
            $extension->name = $request->name;
            $extension->description = $request->description;
            $extension->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'extension has been updated Successfully'
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

            Extension::find($id)->delete(); 

            return response()->json([
                'message' => 'Extension has been deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([                           
                'message' => 'Extension cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

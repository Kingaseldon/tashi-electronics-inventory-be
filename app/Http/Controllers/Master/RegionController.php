<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Dzongkhag;
use App\Models\Extension;
use App\Models\Region;
use DB;


class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:regions.view')->only('index', 'show');
        $this->middleware('permission:regions.store')->only('store');
        $this->middleware('permission:regions.update')->only('update');       
        $this->middleware('permission:regions.edit-regions')->only('editRegion');       
    }
    public function index()
    {
        try{
            $regions = Region::with('extensions:id,regional_id,name,description','dzongkhag')->get(['id', 'name', 'dzongkhag_id','description']);
            $dzongkhags = Dzongkhag::orderBy('id')->get();
            if($regions->isEmpty()){
                $regions = [];
            }   
                return response([
                    'message' => 'success',
                    'region' =>$regions,
                    'dzongkhags' =>$dzongkhags,
                ],200);
        }catch(Exception $e){
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
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $region = new Region();

            $region->dzongkhag_id = $request->dzongkhag_id;
            $region->name = $request->name;
            $region->description = $request->description;
            $region->save();

            $regionalExtensions = [];
            foreach ($request->extensions as $key => $value) {
                $regionalExtensions[$key]['regional_id'] = $region->id;
                $regionalExtensions[$key]['name'] = $value['name'];
                $regionalExtensions[$key]['description'] = isset($value['description']) == true ? $value['description'] : null ;                
                $regionalExtensions[$key]['created_by'] = auth()->user()->id;
            }

            $region->extensions()->insert($regionalExtensions);

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Region and Extension has been created Successfully'
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
    public function editRegion($id)
    {
        try{
            $region = Region::with('extensions', 'dzongkhag')->find($id);
                
            if(!$region){
                return response()->json([
                    'message' => 'The region you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'Region' =>$region
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
        // return response()->json($request->all());
        DB::beginTransaction();
        try{
            $region = Region::findOrFail($id);
            
            if(!$region){
                return response()->json([
                    'message' => 'The Region you are trying to update doesn\'t exist.'
                ], 404);
            }

            $region->dzongkhag_id = $request->dzongkhag_id;
            $region->name = $request->name;
            $region->description = $request->description;
            $region->save();

            $subModuleIdsFromTheDatabase = Extension::where('regional_id', $region->id)->pluck('id')->toArray();
            $subModuleIdsFromTheDatabaseCount = sizeof($subModuleIdsFromTheDatabase);

            //count the total number of values in the array
            $subModuleIdsFromRequestCount = sizeof($request->extensions);

            $subModuleIdsFromRequest = [];
            //store the incoming form values in an array but only the sub modules/menus id
            foreach ($request->extensions as $value) {
                if(isset($value['extension_id'])) {
                $subModuleIdsFromRequest [] = (int) $value['extension_id'];
                }
            }

            //get the unique ids by comparing the above two arrays [subModuleIdsFromTheDatabase, subModuleIdsFromRequest]
            $uniqueSubModuleIds = array_merge(array_diff($subModuleIdsFromTheDatabase, $subModuleIdsFromRequest), array_diff($subModuleIdsFromRequest, $subModuleIdsFromTheDatabase));

            //after that remove the deleted sub module from the system_sub_menus table
            Extension::whereIn('id', $uniqueSubModuleIds)->delete();

            // return response()->json($request->extensions);
            foreach($request->extensions as $key => $value){
    
                if(isset($value['extension_id'])) {
                    $region->extensions()->updateOrCreate(
                        ['id' => $value['extension_id']],
                        [
                            'name' => $value['name'],
                            'description' => isset($value['description']) == true ? $value['description'] : null ,
                            'created_by' => auth()->user()->id,
                        ]
                    );
                } else {
                    $region->extensions()->create([
                        'name' => $value['name'],
                        'description' => isset($value['description']) == true ? $value['description'] : null ,
                        'created_by' => auth()->user()->id,
                    ]);

                }
            }

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Region and Extension has been updated Successfully'
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

            Region::find($id)->delete(); 

            return response()->json([
                'message' => 'Region has been deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([                          
                'message' => 'Region cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

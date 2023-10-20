<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomerType;
use App\Models\Employee;
use Illuminate\Http\Request;
use DB;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employees.view')->only('index', 'show');
        $this->middleware('permission:employees.store')->only('store');
        $this->middleware('permission:employees.update')->only('update');
        $this->middleware('permission:employees.edit-employees')->only('editEmployee');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $employee = Employee::with('customerType')->orderBy('id')->get();
            $customerTypes = CustomerType::orderBy('id')->get();
            if ($employee->isEmpty()) {
                $employee = [];
            }
            return response([
                'message' => 'success',
                'employee' => $employee,
                'customerType' => $customerTypes,
            ], 200);
        } catch (Execption $e) {
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
            'employee_name' => 'required',
            'employee_id' => 'required',
            'contact_no' => 'required',
      
        ]);

        DB::beginTransaction();

        try {
            $employee = new Employee();
            $employee->employee_name = $request->employee_name;
            $employee->employee_id = $request->employee_id;
            $employee->contact_no = $request->contact_no;
            $employee->email = $request->email;
            $employee->description = $request->description;
            $employee->customer_type_id = $request->customer_type_id; 
            $employee->save();
        } 
        catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Employee has been created Successfully'
        ], 200);
    }
    public function editEmployee($id)
    {
        try {
            $employee = Employee::with('customerType')->find($id);
            $customerTypes = CustomerType::orderBy('id')->get();

            if (!$employee) {
                return response()->json([
                    'message' => 'The Employee you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'employee' => $employee,
                'CustomerType' => $customerTypes,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
        $this->validate($request, [
            'employee_name' => 'required',
            'employee_id' => 'required',
            'contact_no' => 'required',
        ]);
        DB::beginTransaction();
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'message' => 'The Employee you are trying to update doesn\'t exist.'
                ], 404);
            }

            $employee->employee_name = $request->employee_name;
            $employee->employee_id = $request->employee_id;
            $employee->contact_no = $request->contact_no;
            $employee->email = $request->email;
            $employee->description = $request->description;
            $employee->customer_type_id = $request->customer_type_id;
            $employee->save();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Employee has been updated Successfully'
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

            Employee::find($id)->delete();

            return response()->json([
                'message' => 'Employee deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Employee cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}

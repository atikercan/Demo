<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Department;
use Session;
use App\Helpers\GeneralHelper;

class DepartmentsController extends Controller {
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct( )
    { 
        $this->middleware('auth');
       
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    { 
        $me = Auth::user();  
		
		if(!GeneralHelper::checkMenuPrivilege("departments")) {
			return view('unauthorized');
		}
		
        $filters = $request->input('filters',[]);
        $currencies = Department::orderBy('departmentName','asc');
		 
        
        if(isset($filters['s']) && !empty($filters['s'])) {
            $currencies->where(function($q) use ($filters) {
                $q->where('departmentName','LIKE','%'.$filters['s'].'%'); 
            });
        } 
        
        return view('departments.index',[
            'records'=>$currencies->get(),
            'menu_active' => 'departments',
            'title' => trans('kpds.departments.departments')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {  
		if(!GeneralHelper::checkPrivilege("departments.create")) {
			return view('unauthorized');
		}
		
        return view('departments.create-edit',[  
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {  
		if(!GeneralHelper::checkPrivilege("departments.create")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
        $data = $request->input('data'); 
   
        $me = Auth::user();   
        
        $admin = Department::create($data);   
		
        Session::flash('result', trans('kpds.departments.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
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
		if(!GeneralHelper::checkPrivilege("departments.update")) {
			return view('unauthorized');
		}
		
        $admin = Department::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('kpds.couldnt_find_record'));
            return redirect(route('departments.index'));
        } 
        
        return view('departments.create-edit',[
            'record'=>$admin
        ]);
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
        if(!GeneralHelper::checkPrivilege("departments.update")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
        $admin = Department::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('kpds.error').'");';
        }
        
        $data = $request->input('data');
		
        $admin->update($data);  
        
        Session::flash('result', trans('kpds.departments.update_success_text')); //<--FLASH MESSAGE
        
		return 'closeModal();window.location.reload();';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $admin = Department::find($id); 
        if(!GeneralHelper::checkPrivilege("departments.destroy")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		} 
		
        $admin->delete();
        
        Session::flash('result', trans('kpds.departments.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Shift;
use Session;
use App\Helpers\GeneralHelper;

class ShiftsController extends Controller {
    
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
		
		if(!GeneralHelper::checkMenuPrivilege("shifts")) {
			return view('unauthorized');
		}
		
        $filters = $request->input('filters',[]);
        $currencies = Shift::orderBy('shiftName','asc');
        
        if(isset($filters['s']) && !empty($filters['s'])) {
            $currencies->where(function($q) use ($filters) {
                $q->where('shiftName','LIKE','%'.$filters['s'].'%'); 
            });
        } 
        
        return view('shifts.index',[
            'records'=>$currencies->get(),
            'menu_active' => 'shifts',
            'title' => trans('kpds.shifts.shifts')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {  
		if(!GeneralHelper::checkPrivilege("shifts.create")) {
			return view('unauthorized');
		}
		
        return view('shifts.create-edit',[  
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
		if(!GeneralHelper::checkPrivilege("shifts.create")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $data = $request->input('data');
   
        $me = Auth::user();   
        
		for($a=1;$a<8;$a++) {
			if(!isset($data['shiftDay'.$a])) {
				$data['shiftDay'.$a] = 0;
			}
			if($data['shiftDay'.$a]==0) {
				$data['shiftDay'.$a."StartTime"] = "00:00:00";
				$data['shiftDay'.$a."EndTime"] = "00:00:00";
			}
		}
		
		
        $admin = Shift::create($data);  
        
        Session::flash('result', trans('kpds.shifts.create_success_text')); //<--FLASH MESSAGE
        
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
		if(!GeneralHelper::checkPrivilege("shifts.update")) {
			return view('unauthorized');
		}
		
        $admin = Shift::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('kpds.couldnt_find_record'));
            return redirect(route('shifts.index'));
        } 
        
        return view('shifts.create-edit',[
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
        if(!GeneralHelper::checkPrivilege("shifts.update")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $admin = Shift::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('kpds.error').'");';
        }
        
        $data = $request->input('data');
		
		for($a=1;$a<8;$a++) {
			if(!isset($data['shiftDay'.$a])) {
				$data['shiftDay'.$a] = 0;
			}
			if($data['shiftDay'.$a]==0) {
				$data['shiftDay'.$a."StartTime"] = "00:00:00";
				$data['shiftDay'.$a."EndTime"] = "00:00:00";
			}
		}
		
        $admin->update($data); 
        
        Session::flash('result', trans('kpds.shifts.update_success_text')); //<--FLASH MESSAGE
        
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
        $admin = Shift::find($id); 
        
		if(!GeneralHelper::checkPrivilege("shifts.destroy")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		} 
		
        $admin->delete();
        
        Session::flash('result', trans('kpds.shifts.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
    }
}
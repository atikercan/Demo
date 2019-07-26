<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Entry;
use App\Models\Device;
use App\Models\Staff;
use App\Models\StaffType;
use Session;
use App\Helpers\GeneralHelper;

class EntriesController extends Controller {
    
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
		
		if(!GeneralHelper::checkMenuPrivilege("entries")) {
			return view('unauthorized');
		}
		
        $filters = $request->input('filters',[]);
        $currencies = Entry::orderBy('entryDate','desc')
				->orderBy("entryCheckInTime","desc")
				->orderBy("entryCheckOutTime","desc");
		
        //var_dump($filters);
        if(isset($filters['staffId']) && $filters['staffId']!="") {
			$currencies->where("staffId","=",$filters['staffId']);
		} 
		if(isset($filters['entryDate']) && isset($filters['entryDate'][0]) && $filters['entryDate'][0]!="") {
			$tmp = date("Y-m-d", strtotime($filters['entryDate'][0]));
			$currencies->where("entryDate",">=",$tmp);
		}
		if(isset($filters['entryDate']) && isset($filters['entryDate'][1]) && $filters['entryDate'][1]!="") {
			$tmp = date("Y-m-d", strtotime($filters['entryDate'][1]));
			$currencies->where("entryDate","<=",$tmp);
		}
		if(isset($filters['entryCheckInTime']) && isset($filters['entryCheckInTime'][0]) && $filters['entryCheckInTime'][0]!="") { 
			$currencies->where("entryCheckInTime",">=",$filters['entryCheckInTime'][0].":00");
		}
		if(isset($filters['entryCheckInTime']) && isset($filters['entryCheckInTime'][1]) && $filters['entryCheckInTime'][1]!="") { 
			$currencies->where("entryCheckInTime","<=",$filters['entryCheckInTime'][1].":59");
		}
		if(isset($filters['entryCheckOutTime']) && isset($filters['entryCheckOutTime'][0]) && $filters['entryCheckOutTime'][0]!="") { 
			$currencies->where("entryCheckOutTime",">=",$filters['entryCheckOutTime'][0].":00");
		}
		if(isset($filters['entryCheckOutTime']) && isset($filters['entryCheckOutTime'][1]) && $filters['entryCheckOutTime'][1]!="") { 
			$currencies->where("entryCheckOutTime","<=",$filters['entryCheckOutTime'][1].":59");
		}
		
		$depIds = Auth::user()->departments()->pluck("departmentId")->all();
		
		if(count($depIds)==0) {
			$depIds[] = 0;
		}  
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName")->get();
		
		
		$staffs = Staff::whereIn("departmentId",$depIds)->whereIn("typeId",$typeIds)->orderBy("staffName")->get();
        
		$currencies->whereHas("staff",function($q) use($depIds,$typeIds) {
			$q->whereIn("departmentId",$depIds);
			$q->whereIn("typeId",$typeIds);
		});
		
		
        return view('entries.index',[
            'records'=>$currencies->paginate(50),
			'staffs'=>$staffs,
            'menu_active' => 'entries',
			'filters'=>$filters,
            'title' => trans('kpds.entries.entries')
        ]);
    } 

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {  
		if(!GeneralHelper::checkPrivilege("entries.update")) {
			return view('unauthorized');
		}
		
        $admin = Entry::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('kpds.couldnt_find_record'));
            return redirect(route('entries.index'));
        }
		
		$devices = Device::orderBy('deviceName','asc')->get();
		
        return view('entries.create-edit',[
            'record'=>$admin,
			'devices'=>$devices
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
        if(!GeneralHelper::checkPrivilege("entries.update")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $admin = Entry::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('kpds.error').'");';
        }
        
        $data = $request->input('data'); 
		
		$data['entryDate'] = date("Y-m-d", strtotime($data['entryDate']));
		
		if(empty($data['entryCheckOutDate']) || $data['entryCheckOutDate']=="") {
			$data['entryCheckOutDate'] = null;
		} else {
			$data['entryCheckOutDate'] = date("Y-m-d", strtotime($data['entryCheckOutDate']));
		}
        $admin->update($data); 
        
        Session::flash('result', trans('kpds.entries.update_success_text')); //<--FLASH MESSAGE
        
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
        $admin = Entry::find($id); 
        
		if(!GeneralHelper::checkPrivilege("entries.destroy")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		} 
		
        $admin->delete();
        
        Session::flash('result', trans('kpds.entries.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
    }
}
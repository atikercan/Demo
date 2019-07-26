<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Staff;
use App\Models\StaffOff;
use App\Models\StaffType;
use Session;
use App\Helpers\GeneralHelper;

class StaffOffsController extends Controller {
    
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
		
		if(!GeneralHelper::checkMenuPrivilege("offs")) {
			return view('unauthorized');
		}
		
        $filters = $request->input('filters',[]);
        $currencies = StaffOff::orderBy('offStartDate','desc')
				->orderBy('offEndDate','desc');
        
		if(isset($filters['offType']) && $filters['offType']!='') {
			if($filters['offType']=="0") {
				//tüm personele
				$currencies->whereNull("staffId");
				$currencies->whereNull("typeId");
				$filters['staffId']="";
				$filters['typeId']="";
			} else if($filters['offType']=="1") {
				// belirli personele
				$currencies->whereNull("typeId"); 
				if(isset($filters['staffId']) && $filters['staffId']!='') {
					$currencies->where("staffId","=",$filters['staffId']);
				}
				$filters['typeId']="";
			} else if($filters['offType']=="2") {
				// belirli personel türüne
				$currencies->whereNull("staffId");
				if(isset($filters['typeId']) && $filters['typeId']!='') {
					$currencies->where("typeId","=",$filters['typeId']);
				}
				$filters['staffId']="";
			}
		} else { 
			$filters['staffId']="";
			$filters['typeId']="";
		}
		if(isset($filters['offIsDaily']) && $filters['offIsDaily']!='') {
			if($filters['offIsDaily']=="1") {
				if(isset($filters['offStartDate']) && isset($filters['offStartDate'][0]) && $filters['offStartDate'][0]!="") {
					$tmp = date("Y-m-d", strtotime($filters['offStartDate'][0]));
					$currencies->where("offStartDate",">=",$tmp." 00:00:00");
				}
				if(isset($filters['offStartDate']) && isset($filters['offStartDate'][1]) && $filters['offStartDate'][1]!="") {
					$tmp = date("Y-m-d", strtotime($filters['offStartDate'][1]));
					$currencies->where("offStartDate","<=",$tmp." 23:59:59");
				}
				if(isset($filters['offEndDate']) && isset($filters['offEndDate'][0]) && $filters['offEndDate'][0]!="") {
					$tmp = date("Y-m-d", strtotime($filters['offEndDate'][0]));
					$currencies->where("offEndDate",">=",$tmp." 00:00:00");
				}
				if(isset($filters['offEndDate']) && isset($filters['offEndDate'][1]) && $filters['offEndDate'][1]!="") {
					$tmp = date("Y-m-d", strtotime($filters['offEndDate'][1]));
					$currencies->where("offEndDate","<=",$tmp." 23:59:59");
				}
			} else {
				if(isset($filters['hourlyStartDate']) && isset($filters['hourlyStartDate'][0]) && $filters['hourlyStartDate'][0]!="") {
					$tmp = date("Y-m-d", strtotime($filters['hourlyStartDate'][0]));
					$currencies->whereRaw("DATE(offStartDate)>='".$tmp."'"); 
				}
				if(isset($filters['hourlyStartDate']) && isset($filters['hourlyStartDate'][1]) && $filters['hourlyStartDate'][1]!="") {
					$tmp = date("Y-m-d", strtotime($filters['hourlyStartDate'][1]));
					$currencies->whereRaw("DATE(offStartDate)<='".$tmp."'");
				}
			}
		} else {
			$filters['offStartDate'][0] = "";
			$filters['offStartDate'][1] = "";
			$filters['offEndDate'][0] = "";
			$filters['offEndDate'][1] = "";
			$filters['hourlyStartDate'][0] = "";
			$filters['hourlyStartDate'][1] = "";
		} 
		
		
		
		$depIds = Auth::user()->departments()->pluck("departmentId")->all();
		
		if(count($depIds)==0) {
			$depIds[] = 0;
		}  
		
		if(!GeneralHelper::checkPrivilege("offs.general_create")) {
			$currencies->where(function($q) {
				$q->whereNotNull("staffId")
					->orWhereNotNull("typeId");
			});
		}
		if(!GeneralHelper::checkPrivilege("offs.type_create")) {
			$currencies->whereNull("typeId");
		}
		
		$currencies->where(function($q) use ($depIds){
			$q->whereNull("staffId")
					->orWhereHas("staff", function($q1) use ($depIds){
						$q1->whereIn("departmentId",$depIds);
					});
		});
		
		$staffs = Staff::whereIn("departmentId",$depIds)->orderBy("staffName")->get();
		
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName")->get();
        
		$currencies->where(function($q) use ($typeIds){
			$q->whereNull("typeId")
					->orWhereIn("typeId", $typeIds);
		});
		
        return view('offs.index',[
            'records'=>$currencies->paginate(50),
            'menu_active' => 'offs',
            'title' => trans('kpds.offs.offs'),
			'staffs'=>$staffs,
			'types'=>$types,
			'filters'=>$filters
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {  
		if(!GeneralHelper::checkPrivilege("offs.create")) {
			return view('unauthorized');
		}
		
		$depIds = Auth::user()->departments()->pluck("departmentId")->all();
		
		if(count($depIds)==0) {
			$depIds[] = 0;
		}  
		
		$staffs = Staff::whereIn("departmentId",$depIds)->orderBy("staffName")->get();
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName")->get();
		
        return view('offs.create-edit',[  
			'staffs'=>$staffs,
			'types'=>$types
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
		if(!GeneralHelper::checkPrivilege("offs.create")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $data = $request->input('data');
   
        $me = Auth::user();    
		
		if($data['offType']=='0') {
			$data['staffId'] = null;
			$data['typeId'] = null;
		}
		if($data['offType']=='1') {
			$data['typeId'] = null;
		}
		if($data['offType']=='2') {
			$data['staffId'] = null;
		}
		if($data['offIsDaily']=='1') {
			$tmp1 = date("Y-m-d", strtotime($data['offStartDate']));
			$tmp2 = date("Y-m-d", strtotime($data['offEndDate']));
			$data['offStartDate'] = $tmp1." 00:00:00";
			$data['offEndDate'] = $tmp2." 23:59:59";
		} else {
			$tmp1 = date("Y-m-d", strtotime($data['hourlyStartDate']));
			$tmp2 = date("Y-m-d", strtotime($data['hourlyStartDate']));
			$data['offStartDate'] = $tmp1." ".$data['hourlyStartTime'].":00";
			$data['offEndDate'] = $tmp2." ".$data['hourlyStartTime'].":59";
		}
		
        $admin = StaffOff::create($data);  
        
        Session::flash('result', trans('kpds.offs.create_success_text')); //<--FLASH MESSAGE
        
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
		if(!GeneralHelper::checkPrivilege("offs.update")) {
			return view('unauthorized');
		}
		
        $admin = StaffOff::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('kpds.couldnt_find_record'));
            return redirect(route('offs.index'));
        } 
        
		$depIds = Auth::user()->departments()->pluck("departmentId")->all();
		
		if(count($depIds)==0) {
			$depIds[] = 0;
		}  
		
		$staffs = Staff::whereIn("departmentId",$depIds)->orderBy("staffName")->get();
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName")->get();
		
        return view('offs.create-edit',[
            'record'=>$admin,
            'staffs'=>$staffs,
            'types'=>$types,
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
        if(!GeneralHelper::checkPrivilege("offs.update")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $admin = StaffOff::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('kpds.error').'");';
        }
        
        $data = $request->input('data');
	 
		if($data['offType']=='0') {
			$data['staffId'] = null;
			$data['typeId'] = null;
		}
		if($data['offType']=='1') {
			$data['typeId'] = null;
		}
		if($data['offType']=='2') {
			$data['staffId'] = null;
		}
		if($data['offIsDaily']=='1') {
			$tmp1 = date("Y-m-d", strtotime($data['offStartDate']));
			$tmp2 = date("Y-m-d", strtotime($data['offEndDate']));
			$data['offStartDate'] = $tmp1." 00:00:00";
			$data['offEndDate'] = $tmp2." 23:59:59";
		} else {
			$tmp1 = date("Y-m-d", strtotime($data['hourlyStartDate']));
			$tmp2 = date("Y-m-d", strtotime($data['hourlyStartDate']));
			$data['offStartDate'] = $tmp1." ".$data['hourlyStartTime'].":00";
			$data['offEndDate'] = $tmp2." ".$data['hourlyEndTime'].":59";
		}
		
        $admin->update($data); 
        
        Session::flash('result', trans('kpds.offs.update_success_text')); //<--FLASH MESSAGE
        
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
        $admin = StaffOff::find($id); 
        
		if(!GeneralHelper::checkPrivilege("offs.destroy")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		} 
		
        $admin->delete();
        
        Session::flash('result', trans('kpds.offs.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
    }
}
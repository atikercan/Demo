<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Staff;
use App\Models\Provider;
use App\Models\Department;
use App\Models\Shift;
use App\Models\StaffType;
use App\Models\StaffProvider;
use App\Models\StaffDevice;
use App\Models\Device;
use Session;
use App\Helpers\GeneralHelper;
use Image;

class StaffsController extends Controller {
    
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
		
		if(!GeneralHelper::checkMenuPrivilege("staffs")) {
			return view('unauthorized');
		}
		
        $filters = $request->input('filters',[]);
        $currencies = Staff::orderBy('staffName','asc');
        
        if(isset($filters['staffFirstName']) && !empty($filters['staffFirstName'])) {
            $currencies->where('staffFirstName','LIKE','%'.$filters['staffFirstName'].'%'); 
        } 
		if(isset($filters['staffLastName']) && !empty($filters['staffLastName'])) {
            $currencies->where('staffLastName','LIKE','%'.$filters['staffLastName'].'%'); 
        } 
		if(isset($filters['staffGender']) && $filters['staffGender']!="") {
            $currencies->where('staffGender','=',$filters['staffGender']); 
        } 
		if(isset($filters['staffEmail']) && !empty($filters['staffEmail'])) {
            $currencies->where('staffEmail','LIKE','%'.$filters['staffEmail'].'%'); 
        }
		if(isset($filters['staffPhone']) && !empty($filters['staffPhone'])) {
            $currencies->where('staffPhone','LIKE','%'.$filters['staffPhone'].'%'); 
        }
		if(isset($filters['staffPhone']) && !empty($filters['staffPhone'])) {
            $currencies->where('staffPhone','LIKE','%'.$filters['staffPhone'].'%'); 
        }
		if(isset($filters['staffTc']) && !empty($filters['staffTc'])) {
            $currencies->where('staffTc','LIKE','%'.$filters['staffTc'].'%'); 
        }
		if(isset($filters['staffCardNo']) && !empty($filters['staffCardNo'])) {
            $currencies->where('staffCardNo','LIKE','%'.$filters['staffCardNo'].'%'); 
        }
		
        if(isset($filters['typeId']) && $filters['typeId']!="") {
            $currencies->where('typeId','=',$filters['typeId']); 
        } 
		if(isset($filters['departmentId']) && $filters['departmentId']!="") {
            $currencies->where('departmentId','=',$filters['departmentId']); 
        } 
		if(isset($filters['shiftId']) && $filters['shiftId']!="") {
            $currencies->where('shiftId','=',$filters['shiftId']); 
        } 
		if(isset($filters['staffStatus']) && $filters['staffStatus']!="") {
            $currencies->where('staffStatus','=',$filters['staffStatus']); 
        } 
		if(isset($filters['staffBirthday']) && isset($filters['staffBirthday'][0]) && $filters['staffBirthday'][0]!="") {
			$tmp = date("Y-m-d", strtotime($filters['staffBirthday'][0]));
			$currencies->where("staffBirthday",">=",$tmp);
		}
		if(isset($filters['staffBirthday']) && isset($filters['staffBirthday'][1]) && $filters['staffBirthday'][1]!="") {
			$tmp = date("Y-m-d", strtotime($filters['staffBirthday'][1]));
			$currencies->where("staffBirthday","<=",$tmp);
		}
		if(isset($filters['staffBirthday']) && isset($filters['staffBirthday'][0]) && $filters['staffBirthday'][0]!="") {
			$tmp = date("Y-m-d", strtotime($filters['staffBirthday'][0]));
			$currencies->where("staffBirthday",">=",$tmp);
		}
		if(isset($filters['staffHireDate']) && isset($filters['staffHireDate'][1]) && $filters['staffHireDate'][1]!="") {
			$tmp = date("Y-m-d", strtotime($filters['staffHireDate'][1]));
			$currencies->where("staffHireDate","<=",$tmp);
		}
		
		//$departments = Department::orderBy("departmentName","asc")->get();
		
		$shifts = Shift::orderBy("shiftName","asc")->get();
		$types = StaffType::orderBy("typeName","asc")->get();
		
		$departments = Auth::user()->departments()->orderBy("departmentName","asc")->get();
		
		$depIds = Auth::user()->departments()->pluck("departmentId")->all();
		if(count($depIds)==0) {
			$depIds[] = 0;
		}
		$currencies->whereIn("departmentId",$depIds);
		
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$currencies->whereIn("typeId",$typeIds);
		
        return view('staffs.index',[
            'records'=>$currencies->paginate(50),
            'menu_active' => 'staffs',
            'title' => trans('kpds.staffs.staffs'),
			'departments'=>$departments,
			'types'=>$types,
			'shifts'=>$shifts,
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
		if(!GeneralHelper::checkPrivilege("staffs.create")) {
			return view('unauthorized');
		}
		
		$departments = Department::orderBy("departmentName","asc")->get();
		$shifts = Shift::orderBy("shiftName","asc")->get();
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName","asc")->get();
		if($types->count()==0) {
			return view('unauthorized');
		}
		
		$providers = Provider::orderBy('providerName',"asc")->get(); 
		
        return view('staffs.create-edit',[
			'departments'=>$departments,
			'types'=>$types,
			'shifts'=>$shifts,
			'providers'=>$providers
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
		if(!GeneralHelper::checkPrivilege("staffs.create")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $data = $request->input('data');
   
        $me = Auth::user();    
		
		if(isset($data['staffCardNo']) && !empty($data['staffCardNo'])) {
			$data['staffCardNo'] = str_repeat("0",10-strlen($data['staffCardNo'])).$data['staffCardNo'];
			
		} else {
			$data['staffCardNo'] = null;
		}
		
		if( isset($data['staffCardNo']) && !empty($data['staffCardNo']) ) {
			$check = Staff::where("staffCardNo","LIKE",$data['staffCardNo'])->count();
			if($check>0) {
				return 'alert("Bu kart numarası başka bir personel tarafından kullanılmaktadır. Lütfen kontrol edip tekrar deneyin.");';
			}
		}
		
		$first = ""; $last = "";
		if(isset($data['staffFirstName'])) {
			$first = $data['staffFirstName'];
		}
		if(isset($data['staffLastName'])) {
			$last = $data['staffLastName'];
		}
		$data['staffName'] = $first." ".$last;
		
		if(isset($data['staffExpirationDate']) && empty($data['staffExpirationDate'])) {
			$data['staffExpirationDate'] = null;
		} else if(isset($data['staffExpirationDate'])) {
			$data['staffExpirationDate'] = date("Y-m-d", strtotime($data['staffExpirationDate']));
		}
		
		if(isset($data['staffBirthday']) && empty($data['staffBirthday'])) {
			$data['staffBirthday'] = null;
		} else if(isset($data['staffBirthday'])) {
			$data['staffBirthday'] = date("Y-m-d", strtotime($data['staffBirthday']));
		}
		
		
		if(isset($data['staffHireDate']) && empty($data['staffHireDate'])) {
			$data['staffHireDate'] = null;
		} else if(isset($data['staffHireDate'])) {
			$data['staffHireDate'] = date("Y-m-d", strtotime($data['staffHireDate']));
		}
		
		if($request->hasFile("image") ) {
			$extension = $request->image->extension();
			$fname = rand(0,1000).time();
			$request->image->storeAs('', $fname.".".$extension, 'staff');
			$data['staffImage'] = $fname.".".$extension;
			
			$img = Image::make(public_path("images/".$fname.".".$extension));
			$img->fit(600, 600);
			$img->orientate(); 
			$img->save(public_path("images/".$fname.".".$extension), 100); 
		} 
		
        $admin = Staff::create($data);  
        
        $providers = $request->input('providers', []);
		
		foreach($providers as $id => $status) {
			StaffProvider::create([
				'staffId'=>$admin->staffId,
				'providerId'=>$id,
				'staffProviderStatus'=>$status,
				'isProviderUpdated'=>0
			]);
			
			$devices = Device::where("providerId","=",$id)->get();
			foreach($devices as $device) {
				StaffDevice::create([
					'staffId'=>$admin->staffId,
					'deviceId'=>$device->deviceId,
					'staffProviderStatus'=>$status,
					'isDeviceUpdated'=>0
				]);	
			}
		}
		
        Session::flash('result', trans('kpds.staffs.create_success_text')); //<--FLASH MESSAGE
        
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
		if(!GeneralHelper::checkPrivilege("staffs.update")) {
			return view('unauthorized');
		}
		
        $admin = Staff::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('kpds.couldnt_find_record'));
            return redirect(route('staffs.index'));
        } 
         
		$departments = Department::orderBy("departmentName","asc")->get();
		$shifts = Shift::orderBy("shiftName","asc")->get();
		$providers = Provider::orderBy('providerName',"asc")->get(); 
		
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orWhere("typeId","=",$admin->typeId)->orderBy("typeName","asc")->get();
		if($types->count()==0) {
			return view('unauthorized');
		}
		
        return view('staffs.create-edit',[
            'record'=>$admin,
			'departments'=>$departments,
			'types'=>$types,
			'shifts'=>$shifts,
			'providers'=>$providers
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
        if(!GeneralHelper::checkPrivilege("staffs.update")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		}
		
        $admin = Staff::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('kpds.error').'");';
        }
        
        $data = $request->input('data');
        $providers = $request->input('providers', []);
		 
		$first = ""; $last = "";
		if(isset($data['staffFirstName'])) {
			$first = $data['staffFirstName'];
		}
		if(isset($data['staffLastName'])) {
			$last = $data['staffLastName'];
		}
		$data['staffName'] = $first." ".$last;
		
		if(isset($data['staffExpirationDate']) && empty($data['staffExpirationDate'])) {
			$data['staffExpirationDate'] = null;
		} else if(isset($data['staffExpirationDate'])) {
			$data['staffExpirationDate'] = date("Y-m-d", strtotime($data['staffExpirationDate']));
		}
		
		if(isset($data['staffBirthday']) && empty($data['staffBirthday'])) {
			$data['staffBirthday'] = null;
		} else if(isset($data['staffBirthday'])) {
			$data['staffBirthday'] = date("Y-m-d", strtotime($data['staffBirthday']));
		}
		if(isset($data['staffHireDate']) && empty($data['staffHireDate'])) {
			$data['staffHireDate'] = null;
		} else if(isset($data['staffHireDate'])) {
			$data['staffHireDate'] = date("Y-m-d", strtotime($data['staffHireDate']));
		}
		
		if(isset($data['staffCardNo']) && !empty($data['staffCardNo']) && strlen($data['staffCardNo'])<10) {
			$data['staffCardNo'] = str_repeat("0",10-strlen($data['staffCardNo'])).$data['staffCardNo'];
			 
			
		}
		
		if(isset($data['staffCardNo']) && !empty($data['staffCardNo']) && strlen($data['staffCardNo'])<10) {
			$check = Staff::where("staffId","!=",$id)->where("staffCardNo","LIKE",$data['staffCardNo'])->count();
			if($check>0) {
				return 'alert("Bu kart numarası başka bir personel tarafından kullanılmaktadır. Lütfen kontrol edip tekrar deneyin.");';
			}
		}
		
		if($request->hasFile("image") ) {
			$extension = $request->image->extension();
			$fname = rand(0,1000).time();
			$request->image->storeAs('', $fname.".".$extension, 'staff');
			$data['staffImage'] = $fname.".".$extension;
			
			$img = Image::make(public_path("images/".$fname.".".$extension));
			$img->fit(600, 600);
			$img->orientate(); 
			$img->save(public_path("images/".$fname.".".$extension), 100); 
			
			if(!is_null($admin->staffImage) && !empty($admin->staffImage)) {
				unlink(public_path("images/".$admin->staffImage));
			}
		}
		
        $admin->update($data); 
		
		
		
		$admin->providers()->delete();
		//$admin->devices()->delete();
		 
		foreach($providers as $id => $status) {
			
			StaffProvider::create([
				'staffId'=>$admin->staffId,
				'providerId'=>$id,
				'staffProviderStatus'=>$status,
				'isProviderUpdated'=>0
			]);
			$devices = Device::where("providerId","=",$id)->get();
			foreach($devices as $device) {
				$check = $admin->devices()->where("deviceId","=",$device->deviceId)->first();
				if($check && $check->staffDeviceStatus==$status) {
					$check->update([
						'isDeviceUpdated'=>0
					]);
				} elseif($check) {
					$check->update([
						'staffDeviceStatus'=>$status,
						'isDeviceUpdated'=>0
					]);
				} else {
					StaffDevice::create([
						'staffId'=>$admin->staffId,
						'deviceId'=>$device->deviceId,
						'staffDeviceStatus'=>$status,
						'isDeviceUpdated'=>0
					]);
				}	
			}
		}
        
        Session::flash('result', trans('kpds.staffs.update_success_text')); //<--FLASH MESSAGE
        
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
        $admin = Staff::find($id); 
        
		if(!GeneralHelper::checkPrivilege("staffs.destroy")) {
			return 'alert("'.trans('kpds.unauthorized_detailed').'")';
		} 
		$admin->update(['staffCardNo'=>null]);
		$admin->providers()->update(["isProviderUpdated"=>0]);
		$admin->devices()->update(["isDeviceUpdated"=>0]);
		
        $admin->delete();
        
        Session::flash('result', trans('kpds.staffs.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();window.location.reload();';
    }
}
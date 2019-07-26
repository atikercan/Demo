<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Session;
use App\User;
use App\Models\Device;
use App\Models\Staff;
use App\Models\Entry;
use App\Models\StaffProvider;
use App\Models\StaffDevice;

class WebServiceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
		
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function listDevices($providerId)
    {
		$devices = Device::where("providerId","=",$providerId)->get();
		
		return $devices;
    }
	public function getCardInformation($cardNo)
    {
		$cardNo = str_repeat("0",10- strlen($cardNo)).$cardNo;
		$staff = Staff::where("staffCardNo","LIKE",$cardNo)
				->where(function($q) {
					$q->whereNull("staffExpirationDate")
							->orWhere("staffExpirationDate",">=",date("Y-m-d"));
				})->first();
		if(!$staff) {
			return ['result'=>'error'];
		}
		$img = url("images/no-image.png");
		if(!is_null($staff->staffImage) && !empty($staff->staffImage)) {
			$img = url("images/".$staff->staffImage);
		}
		return ["result"=>"success","image"=>$img,"name"=>$staff->staffName];
    }
	public function getUserInformation($enrollNo)
    {
		 
		$staff = Staff::where("staffEnrollNumber","=",$enrollNo)->first();
		if(!$staff) {
			return ['result'=>'error'];
		}
		$img = url("images/no-image.png");
		if(!is_null($staff->staffImage) && !empty($staff->staffImage)) {
			$img = url("images/".$staff->staffImage);
		}
		return ["result"=>"success","image"=>$img,"name"=>$staff->staffName];
    }
	public function logEntry(Request $request)
    {
		//$devices = Device::where("providerId","=",$providerId)->get();
		$enrollNo = $request->input("enrollNumber",false);
		$date = $request->input("entryDate",false);
		$deviceId = $request->input("deviceId",false);
		$providerId = $request->input("providerId",false);
		//var_dump($enrollNo);
		$staff = Staff::where("staffEnrollNumber","=",$enrollNo)->first();
		if(!$staff) {
			return ['result'=>'error'];
		}
		
		$day = date("Y-m-d",strtotime($date));
		$time = date("H:i:s",strtotime($date));
		
		$tolerance = config('kpds.duplicate_tolarence'); //minutes
		$toleranceTime = date("H:i:s",strtotime($date)-($tolerance*60));
		
		
		
		$checkDublicate = Entry::where("entryDate","=",$day)
				->where("staffId","=",$staff->staffId)
				->where("entryCheckInTime",">=",$toleranceTime)
				->whereNull("entryCheckOutTime")->first();
		
		$checkDublicate2 = Entry::where("entryDate","=",$day)
				->where("staffId","=",$staff->staffId)
				->where("entryCheckOutTime",">=",$toleranceTime)->first();
		
		if($checkDublicate || $checkDublicate2) {
			return ["result"=>"success"];
		} 
		
		$check = Entry::where("entryDate","=",$day)
				->where("staffId","=",$staff->staffId)
				->whereNull("entryCheckOutTime")->first();
		
		if($check) {
			$check->update([
				'checkOutDeviceId'=>$deviceId,
				'entryCheckOutTime'=>$time,
				'entryCheckOutDate'=>$day
			]);
		} else {
			Entry::create([
				'staffId'=>$staff->staffId,
				'checkInDeviceId'=>$deviceId,
				'entryDate'=>$day,
				'entryCheckInTime'=>$time
			]);
		}
		
		return ["result"=>"success"];
    }
	
	public function providerUpdates($providerId) {
		
		
		
		$expires = Staff::withTrashed()->whereNotNull("staffExpirationDate")->where("staffExpirationDate","<",date("Y-m-d"));
		$expires->update(['staffExpirationDate'=>null,'staffCardNo'=>""]);
		foreach($expires->get() as $exp) {
			$exp->providers()->update(['isProviderUpdated'=>0]);
		}
		
		
		$staffs = StaffProvider::where("providerId","=",$providerId)->where("isProviderUpdated","=",0);
		$devices = Device::where("providerId","=", $providerId)->where("isProviderUpdated","=",0);
		
	 
		$deletesStaffData = [];
		$staffData = [];
		
		foreach($staffs->get() as $staffProvider) {
			$staff = $staffProvider->staff;
			
			$staffStatus = $staff->staffStatus;
			if($staffProvider->staffProviderStatus==1 && is_null($staff->deletedAt)) {
				$staffData[] = [
					'enrollNumber'=>$staff->staffEnrollNumber,
					'name'=>$staff->staffFirstName,
					'password'=>"",
					'enabled'=>(is_null($staff->deletedAt))?$staffStatus:0,
					'cardNo'=>$staff->staffCardNo
				];	
			} else {
				$deletesStaffData[] = [
					'enrollNumber'=>$staff->staffEnrollNumber,
					'name'=>$staff->staffFirstName,
					'password'=>"",
					'enabled'=>0,
					'cardNo'=>""
				];	
			}
		}
		
		$staffs->update(["isProviderUpdated"=>1]);
		$devices->update(["isProviderUpdated"=>1]);
		
		$data = [
			'enrollments'=>$staffData,
			'deletes'=>$deletesStaffData,
			'deviceRefresh'=>$devices->count()
		];
		
		return $data;
	}
	
	public function deviceUpdates($providerId) {
		
		
		
		$expires = Staff::withTrashed()->whereNotNull("staffExpirationDate")->where("staffExpirationDate","<",date("Y-m-d"));
		$expires->update(['staffExpirationDate'=>null,'staffCardNo'=>""]);
		foreach($expires->get() as $exp) {
			$exp->providers()->update(['isProviderUpdated'=>0]);
		}
		
		
		$staffs = StaffDevice::whereHas("device", function($q) use($providerId) {
			$q->where("providerId","=",$providerId);
		})->where("isDeviceUpdated","=",0);
		$devices = Device::where("providerId","=", $providerId)->where("isProviderUpdated","=",0);
	 
		$deletesStaffData = [];
		$staffData = [];
		
		foreach($staffs->get() as $staffDevice) {
			$staff = $staffDevice->staff;
			$staffStatus = $staff->staffStatus;
			
			if($staffDevice->staffDeviceStatus==1 && is_null($staff->deletedAt)) {
				$staffData[] = [
					'id'=>$staffDevice->id,
					'deviceId'=>$staffDevice->deviceId,
					'deviceName'=>$staffDevice->device->deviceName,
					'staffId'=>$staff->staffId,
					'enrollNumber'=>$staff->staffEnrollNumber,
					'name'=>$staff->staffFirstName,
					'password'=>"",
					'enabled'=>(is_null($staff->deletedAt))?$staffStatus:0,
					'cardNo'=>$staff->staffCardNo
				];	
			} else {
				$deletesStaffData[] = [
					'id'=>$staffDevice->id,
					'deviceId'=>$staffDevice->deviceId,
					'deviceName'=>$staffDevice->device->deviceName,
					'staffId'=>$staff->staffId,
					'enrollNumber'=>$staff->staffEnrollNumber,
					'name'=>$staff->staffFirstName,
					'password'=>"",
					'enabled'=>0,
					'cardNo'=>""
				];	
			}
		}
		
		//$staffs->update(["isDeviceUpdated"=>1]);
		$devices->update(["isProviderUpdated"=>1]);
		
		$resets = [];
		
		$resetQ = Device::where("providerId","=",$providerId)->where("mustResetDate","=",1)->get();
		foreach($resetQ as $r) {
			$resets[] = [
				'id'=>$r->deviceId
			];
		}
		$data = [
			'enrollments'=>$staffData,
			'deletes'=>$deletesStaffData,
			'deviceRefresh'=>$devices->count(),
			'resets'=>$resets
		];
		
		return $data;
	} 
	
	public function updateCompleted(Request $request) {
		$ids = $request->input("ids","0");
		//$deviceIds = array_values($deviceIds);
	 
		$ids = explode("|", $ids);
		
		StaffDevice::whereIn("id",$ids)->update(["isDeviceUpdated"=>1]);
		
		return ['result'=>'success'];
	}
	public function resetCompleted(Request $request) {
		$ids = $request->input("ids","0");
		//$deviceIds = array_values($deviceIds);
		$ids = explode("|", $ids);
		
		Device::whereIn("deviceId",$ids)->update(["mustResetDate"=>0]);
		
		return ['result'=>'success'];
	}
}

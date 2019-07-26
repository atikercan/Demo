<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Staff extends Model
{
	use SoftDeletes;
	
	public static function boot() {
        parent::boot();  
		self::created(function ($staff) {  
			$staff->assignEnrollNumber();
			return $staff;
        });
    }
	
	protected $dates = ['deletedAt'];
	
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	const DELETED_AT = 'deletedAt';
	
	protected $table = 'staffs';
    protected $primaryKey = 'staffId';
	 
	
	protected $fillable = [
		'staffId',
		'departmentId',
		'typeId',
		'shiftId',
		'staffEnrollNumber',
		'staffImage',
		'staffName',
		'staffFirstName',
		'staffLastName',
		'staffGender',
		'staffEmail',
		'staffPhone',
		'staffHireDate',
		'staffBirthday',
		'staffNotes',
		'staffStatus',
		'staffCardNo',
		'staffExpirationDate',
		'staffTc'
    ];
	
	public function devices() {
        return $this->hasMany('App\Models\StaffDevice', 'staffId', 'staffId');
	}
	public function providers() {
        return $this->hasMany('App\Models\StaffProvider', 'staffId', 'staffId');
	}
	public function offs() {
        return $this->hasMany('App\Models\StaffOff', 'staffId', 'staffId');
	}
	public function entries() {
        return $this->hasMany('App\Models\Entry', 'staffId', 'staffId');
	}
	public function department() {
        return $this->belongsTo('App\Models\Department', 'departmentId', 'departmentId');
    }
	public function type() {
        return $this->belongsTo('App\Models\StaffType', 'typeId', 'typeId');
    }
	public function shift() {
        return $this->belongsTo('App\Models\Shift', 'shiftId', 'shiftId');
    }
	public function assignEnrollNumber() {
		$x = \DB::select("SELECT GetAvailableEnrollNumber() as nextEnrollNumber;")[0];
	 
		$this->staffEnrollNumber = $x->nextEnrollNumber;
		$this->save();
	}
	
	public function getWorkStatus($date) {
		
		$shift = $this->shift; 
		
		$lunch_time = $shift->lunchBreak;
		$option_start = $shift->startOption;
		$option_end = $shift->endOption;
		$worktime_tolerance = $option_start + $option_end;
		
		$staffId = $this->staffId;
		$typeId = $this->typeId;
		
		$data = [
			'workedHours'=>0,
			'offHours'=>0,
			'dayStatus'=>'shift_day', // shift_day, vacation, 
			'staffStatus'=>false,
			'vacationStatus'=>false, // HT, İ, RT
			'extraWorkedHours'=>0,
			'shiftHours'=>0,
			'shiftHoursWithoutLunch'=>0,
			'fewWork'=>false,
			'lateIn'=>false,
			'earlyOut'=>false
		];
		
		$dayDifferent = false;
		
		$weekDay = date("w", strtotime($date));
		
		$data['workedHours'] += $this->entries()->where("entryDate","=",$date)->orderBy("entryCheckInTime", "asc")->orderBy("entryCheckOutTime", "asc")->sum('entryDuration');
		
		$offs = StaffOff::where(function($q) use($staffId,$typeId,$date) {
					$q->where("staffId","=",$staffId)
							->orWhere("typeId","=",$typeId);
				})
				->where("offIsDaily","=","0")
				->whereRaw("DATE(offStartDate) = '".$date."'")->get();
				
		foreach($offs as $off) {
			$tmp1 = strtotime($off->offStartDate);
			$tmp2 = strtotime($off->offEndDate);
			$tmp3 = round(abs($tmp2 - $tmp1) / 3600,2);
			$data['offHours'] += $tmp3;
		}
			
		if($data['workedHours']>0) {
			$data['staffStatus'] = true; 
		}
			
		if($shift->{"shiftDay".$weekDay}==0) {
			// o gün mesai yok
			$data['dayStatus'] = 'vacation';
			$data['vacationStatus'] = 'HT'; 
			
			if($data['workedHours']>0) {
				$data['staffStatus'] = true;
				$data['extraWorkedHours'] += $data['workedHours'];
			}
		} else {  
			
			$tmp1 = strtotime($shift->{"shiftDay".$weekDay."StartTime"});
			$tmp2 = strtotime($shift->{"shiftDay".$weekDay."EndTime"});
			if($tmp2<$tmp1) {
				$dayDifferent = true;
				$tmp2 += 86400;
			}
			$data['shiftHours'] = round(abs($tmp2 - $tmp1) / 3600,2);
			$data['shiftHoursWithoutLunch'] = $data['shiftHours'] - $lunch_time;
			
			if($data['shiftHoursWithoutLunch']<$data['workedHours']) {
				$data['extraWorkedHours'] += $data['workedHours'] - $data['shiftHoursWithoutLunch'];
			} else if(abs($data['workedHours']-$data['shiftHoursWithoutLunch'])>$worktime_tolerance) {
				$data['fewWork'] = true; 
			}
			
			$offs1 = StaffOff::where("staffId","=", $staffId)->where("offIsDaily","=","1")
			->where("offStartDate","<=",$date." 00:00:00")
			->where("offEndDate",">=",$date." 23:59:59")->get();

			foreach($offs1 as $of) {
				//tam gün mesai
				$data['dayStatus'] = 'vacation';
				$data['vacationStatus'] = $of->offSType;
				$data['extraWorkedHours'] = $data['workedHours'];
				$data['staffStatus'] = true;
			} 
			
			$offs2 = StaffOff::where(function($q) use($staffId,$typeId) {
				$q->where("typeId","=",$typeId)
						->orWhere(function($q1) {
							$q1->whereNull("typeId")
							->whereNull("staffId");
						});
			})->where("offIsDaily","=","1")
			->where("offStartDate","<=",$date." 00:00:00")
			->where("offEndDate",">=",$date." 23:59:59")->get();
			
			foreach($offs2 as $of) {
				//tam gün mesai
				$data['dayStatus'] = 'vacation';
				$data['vacationStatus'] = $of->offSType;
				$data['extraWorkedHours'] = $data['workedHours'];
				$data['staffStatus'] = true;
			}
			
			if($offs1->count()==0 && $offs2->count()==0) {
				if($data['workedHours']==0) {
					$data['staffStatus'] = false;
				}
			}
			
			$tmp = $this->entries()->where("entryDate","=",$date)->orderBy("entryCheckInTime", "asc")->first();
			if($tmp) {
				$tmp1 = strtotime($tmp->entryCheckInTime);
				$tmp2 = strtotime($shift->{"shiftDay".$weekDay."StartTime"});
				if($tmp1>$tmp2 && round(($tmp1 - $tmp2) / 3600,2)>$option_start) {
					$data['lateIn'] = round(($tmp1 - $tmp2) / 3600,2);
				}
			}
			
			$tmp = $this->entries()->where("entryDate","=",$date)->orderBy("entryCheckOutTime", "desc")->first();
			if($tmp) { 
				$x = $tmp->entryDate;
				if($dayDifferent) {
					$x = date("Y-m-d", strtotime($x)+86400);
				}
				$tmp1 = strtotime($tmp->entryCheckOutDate." ".$tmp->entryCheckOutTime);
				$tmp2 = strtotime($x." ".$shift->{"shiftDay".$weekDay."EndTime"});
				
				if($tmp2>$tmp1 && round(($tmp2 - $tmp1) / 3600,2)>$option_end) {
					$data['earlyOut'] = round(($tmp2 - $tmp1) / 3600,2);
				}
			}
		}
		
		return $data;
	}
}

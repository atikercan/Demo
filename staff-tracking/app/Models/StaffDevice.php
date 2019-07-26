<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StaffDevice extends Model
{ 
	
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	
	protected $table = 'staffs_devices';
    protected $primaryKey = 'id';
	 
	
	protected $fillable = [
		'id',
		'staffId',
		'deviceId',
		'staffDeviceStatus',
		'isDeviceUpdated'
    ]; 
	public function staff() {
        return $this->belongsTo('App\Models\Staff', 'staffId', 'staffId')->withTrashed();
    } 
	public function device() {
        return $this->belongsTo('App\Models\Device', 'deviceId', 'deviceId');
    } 
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StaffOff extends Model
{ 
	
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	
	protected $table = 'staffs_offs';
    protected $primaryKey = 'offId';
	 
	
	protected $fillable = [
		'offId',
		'staffId',
		'offIsDaily',
		'offStartDate',
		'offEndDate',
		'offDescription',
		'offSType'
    ]; 
	public function staff() {
        return $this->belongsTo('App\Models\Staff', 'staffId', 'staffId')->withTrashed();
    } 
}

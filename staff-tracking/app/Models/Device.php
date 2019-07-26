<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Device extends Model
{ 
	use SoftDeletes;
	
	protected $dates = ['deletedAt'];
	
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	const DELETED_AT = 'deletedAt';
	
	protected $table = 'devices';
    protected $primaryKey = 'deviceId';
	 
	
	protected $fillable = [
		'deviceId',
		'providerId',
		'deviceName',
		'deviceIp',
		'devicePort',
		'deviceStatus'
    ];
	
	public function provider() {
        return $this->belongsTo('App\Models\Provider', 'providerId', 'providerId');
    }
}

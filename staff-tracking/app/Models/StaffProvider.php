<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StaffProvider extends Model
{ 
	
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	
	protected $table = 'staffs_providers';
    protected $primaryKey = 'id';
	 
	
	protected $fillable = [
		'id',
		'staffId',
		'providerId',
		'staffProviderStatus',
		'isProviderUpdated'
    ]; 
	public function staff() {
        return $this->belongsTo('App\Models\Staff', 'staffId', 'staffId')->withTrashed();
    } 
	public function provider() {
        return $this->belongsTo('App\Models\Provider', 'providerId', 'providerId');
    } 
}

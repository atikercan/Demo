<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StaffType extends Model
{
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	
	protected $table = 'staff_types';
    protected $primaryKey = 'typeId';
	 
	
	protected $fillable = [
		'typeId',
		'typeName'
    ];
}

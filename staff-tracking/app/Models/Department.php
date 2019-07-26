<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Department extends Model
{
    const CREATED_AT = 'createdAt';
	const UPDATED_AT = 'updatedAt';
	
	protected $table = 'departments';
    protected $primaryKey = 'departmentId';
	 
	
	protected $fillable = [
		'departmentId',
		'departmentName'
    ];
}

<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class Branch extends Model
{
    use SoftDeletes;
	
	protected $dates = ['deleted_at'];
	
	
    protected $table = 'branches';
    protected $primaryKey = 'branch_id'; 
	
	protected $casts = [
        'branch_sentences' => 'array',
    ];
	
    protected $fillable = array(
		'branch_id',
		'account_id',
		'branch_name',
		'branch_phone',
		'branch_address',
		'branch_country',
		'branch_region',
		'branch_city',
		'branch_zip',
		'branch_web',
		'branch_email',
		'branch_logo',
		'currency_id',
		'branch_details',
		'branch_order',
		'quote_banner',
		'quote_banner_link',
		'overdue_remind_mail',
		'invoice_notify_mail',
		'branch_sentences',
		'branch_franchise_fee',
		'branch_payment_notification_mail',
		'mail_background'
	); 
	
	public function scopeCurrentAccount($query) {
		$acc_id = RouteHelper::getCurrentAccount()->account_id;
        return $query->where('branches.account_id', '=', $acc_id);
    }
	public function account() {
        return $this->belongsTo('App\Models\Account', 'account_id', 'account_id');
    }
	public function getLocationAttribute(){
		
        return $this->branch_city.", ".$this->branch_region.", ".$this->branch_country;
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function assignedStudents() {
        return $this->belongsToMany('App\Models\Student', 'students_branches', 'branch_id', 'student_id');
	}
	public function pipelines() {
        return $this->hasMany('App\Models\Pipeline', 'branch_id', 'branch_id')->orderBy('pipeline_order','asc');
    }
} 
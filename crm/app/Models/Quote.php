<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class Quote extends Model
{
	use Loggable;
	
    protected $table = 'quotes';
    protected $primaryKey = 'quote_id'; 
	
	protected $casts = [
		'quote_status'=>'integer'
	];
	
    protected $fillable = array(
		'quote_id',
		'account_id',
		'branch_id',
		'student_id',
		'admin_id',
		'quote_no',
		'language',
		'currency_id',
		'issue_date',
		'due_date',
		'quote_notes',
		'quote_status',
		'guid',
		'selected_option_id',
		'selected_at',
		'viewed_at',
		'quote_is_notified'
	); 
	public function scopeCurrentAccount($query) {
		$acc_id = RouteHelper::getCurrentAccount()->account_id;
        return $query->where('quotes.account_id', '=', $acc_id);
    }
	public function admin() {
        return $this->belongsTo('App\Admin', 'admin_id', 'id');
    }
	public function account() {
        return $this->belongsTo('App\Models\Account', 'account_id', 'account_id');
    }
	public function student() {
        return $this->belongsTo('App\Models\Student', 'student_id', 'student_id');
    }
	public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id', 'branch_id');
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function options() {
        return $this->hasMany('App\Models\QuoteOption', 'quote_id', 'quote_id')->orderBy('option_order','asc');
    }
	public function selectedOption() {
        return $this->belongsTo('App\Models\QuoteOption', 'selected_option_id', 'option_id');
	}
	
	public function mustLoadRelations(){
		$this->load(["student"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>'#'.$this->quote_id
		];
	}
}
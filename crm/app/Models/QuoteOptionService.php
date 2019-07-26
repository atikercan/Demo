<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class QuoteOptionService extends Model
{
	//use Loggable;
	
    protected $table = 'quotes_options_services';
    protected $primaryKey = 'option_service_id'; 
	public $isLoggable = true;
	
    protected $fillable = array(
		'option_service_id',
		'option_id',
		'service_id',
		'option_course_id',
		'option_service_name',
		'option_service_partner',
		'option_service_campus',
		'option_service_start_date',
		'option_service_duration',
		'option_service_duration_unit',
		'option_service_price',
		'option_service_gross_price',
		'option_service_price_type',
		'option_service_quantity',
		'option_service_description',
		'option_currency_code',
		'currency_id',
		'option_service_description'
	); 
	public function option() {
        return $this->belongsTo('App\Models\QuoteOption', 'option_id', 'option_id');
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function service() {
        return $this->belongsTo('App\Models\PartnerCampusService', 'service_id', 'service_id');
    }
	public function option_course() {
        return $this->belongsTo('App\Models\QuoteOptionCourse', 'option_course_id', 'option_course_id');
    }
	public function promotions() {
        return $this->hasMany('App\Models\QuoteOptionPromotion', 'option_service_id', 'option_service_id')->orderBy("created_at","asc");
    }
	
	public function mustLoadRelations(){
		$this->load(["option","option.quote"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>$this->option_service_name,
			'log_record_parent_id'=>$this->option_id,
			'log_record_parent_name'=>$this->option->option_name,
			'log_record_parent_parent_id'=>$this->option->quote_id,
			'log_record_parent_parent_name'=>'#'.$this->option->quote->quote_id
		];
	} 
	public function isLoggable($event) {
 
		
		return true;
	}
}
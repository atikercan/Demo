<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;

class QuoteOptionPromotion extends Model
{
    protected $table = 'quotes_options_promotions';
    protected $primaryKey = 'option_promotion_id'; 
	public $isLoggable = true;
	
    protected $fillable = array(
		'option_promotion_id',
		'option_id',
		'promotion_id',
		'option_course_id',
		'option_service_id',
		'promotion_name',
		'promotion_percentage',
		'promotion_fixed',
		'promotion_amount',
		'promotion_free_duration',
		'promotion_free_duration_unit',
		'currency_id'
	); 
	public function option() {
        return $this->belongsTo('App\Models\QuoteOption', 'option_id', 'option_id');
    } 
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function course() {
        return $this->belongsTo('App\Models\QuoteOptionCourse', 'option_course_id', 'option_course_id');
    } 
	public function service() {
        return $this->belongsTo('App\Models\PartnerCampusService', 'option_service_id', 'service_id');
    }
	public function getReadableName() {
		if(!is_null($this->promotion_free_duration)) {
			return trans('admin.promotions.promotion_free_duration_description',[
				'duration'=>$this->promotion_free_duration. " ".trans('admin.prices.'.$this->course->option_course_duration_unit)
			]);
		}
		if(!is_null($this->promotion_percentage)) {
			return trans('admin.promotions.promotion_percentage_description',[
				'percentage'=>$this->promotion_percentage
			]);
		}
		if(!is_null($this->promotion_fixed)) {
			return trans('admin.promotions.promotion_fixed_description');
		}
	}
	public function isLoggable($event) {
		
		return $this->isLoggable;
	}
	
	public function mustLoadRelations(){
		$this->load(["option","option.quote","course","service"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>'#'.$this->option_promotion_id,
			'log_record_parent_id'=>$this->option_id,
			'log_record_parent_name'=>$this->option->option_name,
			'log_record_parent_parent_id'=>$this->option->quote_id,
			'log_record_parent_parent_name'=>'#'.$this->option->quote->quote_id
		];
	}
}
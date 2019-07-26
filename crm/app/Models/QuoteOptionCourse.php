<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class QuoteOptionCourse extends Model
{
	//use Loggable;
	
    protected $table = 'quotes_options_courses';
    protected $primaryKey = 'option_course_id'; 
	
    protected $fillable = array(
		'option_course_id',
		'option_id',
		'course_id',
		'place_id',
		'option_course_name',
		'option_course_partner',
		'option_course_campus',
		'option_course_duration',
		'option_course_duration_unit',
		'option_course_price',
		'option_course_price_type',
		'option_course_gross_price',
		'option_currency_code',
		'option_course_intensity',
		'option_course_start_date',
		'currency_id',
		'option_course_description',
		'option_course_campus_image',
		'option_course_campus_logo',
		'option_course_course_type',
		'option_course_course_language',
		'campus_id'
	); 
	public function option() {
        return $this->belongsTo('App\Models\QuoteOption', 'option_id', 'option_id');
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function place() {
        return $this->belongsTo('App\Models\Place', 'place_id', 'place_id');
    }
	public function course() {
        return $this->belongsTo('App\Models\PartnerCampusCourse', 'course_id', 'course_id')->withTrashed();
    }
	public function campus() {
        return $this->belongsTo('App\Models\PartnerCampus', 'campus_id', 'campus_id')->withTrashed();
    } 
	public function promotions() {
        return $this->hasMany('App\Models\QuoteOptionPromotion', 'option_course_id', 'option_course_id')->orderBy("created_at","asc");
    }
	
	public function mustLoadRelations(){
		$this->load(["option","option.quote"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>$this->option_course_name,
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
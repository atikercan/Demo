<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class QuoteOptionAccommodation extends Model
{
	//use Loggable;
	
    protected $table = 'quotes_options_accommodations';
    protected $primaryKey = 'option_accommodation_id'; 
	
    protected $fillable = array(
		'option_accommodation_id',
		'option_id',
		'accommodation_id',
		'option_accommodation_name',
		'option_accommodation_start_date',
		'option_accommodation_duration',
		'option_accommodation_duration_unit',
		'option_accommodation_price',
		'option_accommodation_price_type',
		'option_currency_code',
		'currency_id',
		'option_accommodation_description',
		'type_id',
		'option_accommodation_type',
		'option_accommodation_partner',
		'option_accommodation_campus'
	); 
	public function option() {
        return $this->belongsTo('App\Models\QuoteOption', 'option_id', 'option_id');
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    }
	public function accommodation() {
        return $this->belongsTo('App\Models\PartnerCampusAccommodation', 'accommodation_id', 'accommodation_id')->withTrashed();
    }
	public function type() {
        return $this->belongsTo('App\Models\AccommodationType', 'type_id', 'type_id');
    }
	
	public function mustLoadRelations(){
		$this->load(["option","option.quote"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>$this->option_accommodation_name,
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
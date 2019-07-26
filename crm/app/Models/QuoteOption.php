<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Traits\Loggable;

class QuoteOption extends Model
{   
	use Loggable;
	
	protected $casts = [
        'option_deleted_fees' => 'array',
        'option_deleted_promotions' => 'array'
    ]; 
	
    protected $table = 'quotes_options';
    protected $primaryKey = 'option_id'; 
	
    protected $fillable = array(
		'option_id',
		'quote_id',
		'option_name',
		'option_order',
		'option_deleted_fees',
		'option_deleted_promotions',
		'option_notes'
	); 
	public function quote() {
        return $this->belongsTo('App\Models\Quote', 'quote_id', 'quote_id');
    }
	public function courses() {
        return $this->hasMany('App\Models\QuoteOptionCourse', 'option_id', 'option_id')->orderBy('created_at','asc');
    }
	public function accommodations() {
        return $this->hasMany('App\Models\QuoteOptionAccommodation', 'option_id', 'option_id')->orderBy('created_at','asc');
    }
	public function services() {
        return $this->hasMany('App\Models\QuoteOptionService', 'option_id', 'option_id')->orderBy('created_at','asc');
    } 
	public function promotions() {
        return $this->hasMany('App\Models\QuoteOptionPromotion', 'option_id', 'option_id')->orderBy('created_at','asc');
    }
	public function services_fees() {
        return $this->services()->whereHas("service",function($q) {
			$q->where("service_type","LIKE","fee");
		})->get();
    } 
	public function services_services() {
        return $this->services()->where(function($qqq) {
			$qqq->whereHas("service",function($q) {
				$q->where("service_type","LIKE","service");
			})->orWhereNull("service_id");
		})->get();
    } 
	public function fixPromotions() {
		$this->promotions()->where(function($q) {
			$q->whereNotNull("option_course_id");
			$q->orWhereNotNull("option_service_id");
		})->delete();
		foreach($this->courses as $optCourse) {
			if(!$optCourse->campus)
				continue; 
			
			
			//check price
			$d = $optCourse->option_course_duration;
			$dt = $optCourse->option_course_duration_unit;
			$today = date("Y-m-d", strtotime($optCourse->created_at));
					
			$optCourse->option_course_price = $optCourse->option_course_gross_price;
			
			//ücretsiz hafta sorgula
			$promotions = Promotion::where("campus_id","=",$optCourse->campus_id)
					->where("promotion_start",'<=',$today)
					->where("promotion_end",'>=',$today)
					->where("promotion_min_duration",'<=',$d)
					->where("promotion_max_duration",'>=',$d)
					->where("promotion_min_max_unit",'LIKE',$dt)
					->where("promotion_type","LIKE","free_duration")
					->whereHas('courses',function($q) use($optCourse) {
						$q->where("promotions_courses.course_id","=",$optCourse->course_id);
					})->orderBy("created_at","asc")->get();
			foreach($promotions as $promotion) {
				if( $this->checkBlockedPromotion($optCourse->option_course_id, $promotion->promotion_id) ) {
					continue;
				}
				$tmpD = $d - $promotion->promotion_free_duration;
				$newPriceQ = \DB::select("SELECT calculateCoursePrice('".$optCourse->course_id."','$tmpD','$dt') as price");
				$newPrice = $newPriceQ[0]->price;
				$discount = $optCourse->option_course_price - $newPrice;
				$optCourse->option_course_price = $newPrice;
				QuoteOptionPromotion::create([
					'option_id'=>$this->option_id,
					'promotion_id'=>$promotion->promotion_id,
					'option_course_id'=>$optCourse->option_course_id,
					'promotion_amount'=>$discount,
					'currency_id'=>$optCourse->currency_id,
					'promotion_free_duration'=>$promotion->promotion_free_duration,
					'promotion_free_duration_unit'=>$dt
				]);
			}
			
			//indirim sorgula
			$promotions = Promotion::where("campus_id","=",$optCourse->campus_id)
					->where("promotion_start",'<=',$today)
					->where("promotion_end",'>=',$today)
					->where(function($q) use($d) {
						$q->where("promotion_min_duration",'<=',$d)
								->orWhereNull("promotion_min_duration");
					})
					->where(function($q) use($d) {
						$q->where("promotion_max_duration",'>=',$d)
								->orWhereNull("promotion_max_duration");
					})
					->where("promotion_type","LIKE","discount")
					->whereHas('courses',function($q) use($optCourse) {
						$q->where("promotions_courses.course_id","=",$optCourse->course_id);
					})->orderBy("created_at","asc")->get();
			foreach($promotions as $promotion) {
				if( $this->checkBlockedPromotion($optCourse->option_course_id, $promotion->promotion_id) ) {
					continue;
				}
				
				$per = null;
				$fix = null;
				if($promotion->promotion_discount_type=='percentage') {
					$per = $promotion->promotion_discount_amount;
					$discount = $optCourse->option_course_price * ($promotion->promotion_discount_amount / 100);
					$discount = number_format($discount,2,".",""); 
				} else if($promotion->promotion_discount_type=='fixed'){
					$fix = $promotion->promotion_discount_amount;
					$discount = $promotion->promotion_discount_amount;
				}
				
				$optCourse->option_course_price = $optCourse->option_course_price - $discount;
				
				QuoteOptionPromotion::create([
					'option_id'=>$this->option_id,
					'promotion_id'=>$promotion->promotion_id,
					'option_course_id'=>$optCourse->option_course_id,
					'promotion_amount'=>$discount,
					'currency_id'=>$optCourse->currency_id,
					'promotion_percentage'=>$per,
					'promotion_fixed'=>$fix
				]);
			}
			/*
			$proms = $this->promotions()->where(function($q) {
				$q->whereNull("option_course_id");
				$q->whereNull("option_service_id");
			})->get();
			
			foreach($proms as $prom) {
				
			} */
			
			$optCourse->save();
		}
	}
	public function addBlockedPromotion($option_course_id, $promotion_id) {
		if(!is_array($this->option_deleted_promotions)) {
			$this->option_deleted_promotions = [];
		}
		
		$promid = $option_course_id."-".$promotion_id;
		
		$x = $this->option_deleted_promotions;
		if(!in_array($promid,$this->option_deleted_promotions)) {
			$x[] = $promid;
		}
		$this->option_deleted_promotions = $x;
		$this->save();
	}
	public function checkBlockedPromotion($option_course_id, $promotion_id) {
		if(!is_array($this->option_deleted_promotions)) {
			$this->option_deleted_promotions = [];
		}
		$promid = $option_course_id."-".$promotion_id; 
		//var_dump($fee_id); var_dump($this->option_deleted_fees); 
		return in_array($promid,$this->option_deleted_promotions);
	}
	public function addBlockedFee($fee_id) {
		if(!is_array($this->option_deleted_fees)) {
			$this->option_deleted_fees = [];
		}
		$x = $this->option_deleted_fees;
		if(!in_array($fee_id,$this->option_deleted_fees)) {
			$x[] = $fee_id;
		}
		$this->option_deleted_fees = $x;
		$this->save();
	}
	public function checkBlockedFee($fee_id) {
		if(!is_array($this->option_deleted_fees)) {
			$this->option_deleted_fees = [];
		}
		//var_dump($fee_id); var_dump($this->option_deleted_fees); 
		return in_array($fee_id,$this->option_deleted_fees);
	}
	public function fixFees() {
		$this->services()->whereHas("service",function($q) {
			$q->where("service_type","LIKE","fee");
		})->delete();
		
		$feeIds = [];
		foreach($this->courses as $optCourse) {
			if(!$optCourse->course) {
				continue;
			}
			$dbCourse = $optCourse->course;
			
			$curr = $dbCourse->campus->getCurrency();
			
			$dbFees = $dbCourse->campus->services()->where("service_type","LIKE","fee")->where("fee_type","LIKE","course")->get();
			foreach($dbFees as $dbFee) {
				if(!in_array($dbFee->service_id,$feeIds)) {
					$pr2 = \DB::select("SELECT calculateServicePrice('".$dbFee->service_id."','".$optCourse->option_course_duration."','".$optCourse->option_course_duration_unit."') as price" );
					 
					$pr2 = reset($pr2);
					//var_dump($pr2); die();
					if((!is_null($pr2->price)) && (!$this->checkBlockedFee($dbFee->service_id))) {
						QuoteOptionService::create([
							'option_id'=>$this->option_id,
							'service_id'=>$dbFee->service_id,
							'option_course_id'=>$optCourse->option_course_id,
							'option_service_name'=>$dbFee->service_name,
							'option_service_partner'=>$dbFee->campus->partner->partner_name,
							'option_service_campus'=>$dbFee->campus->campus_name, 
							'option_service_price'=>$pr2->price,
							'option_service_gross_price'=>$pr2->price,
							'option_service_price_type'=>'total',
							'option_service_description'=>$dbFee->service_description,
							'option_service_quantity'=>1,
							'option_currency_code'=>$curr->currency_code,
							'currency_id'=>$curr->currency_id,
							'option_service_start_date'=>$dbFee->option_course_start_date
						]);
					}
					$feeIds[] = $dbFee->service_id;
				}
			}	
		}
		
			 
		foreach($this->accommodations as $optAccommodation) {
			
			if(!$optAccommodation->accommodation || !$optAccommodation->accommodation->campus) {
				continue;
			}
			$dbAccommodation = $optAccommodation->accommodation;
			
			$curr = $dbAccommodation->campus->getCurrency();
	 
			$dbFees = $dbAccommodation->campus->services()->where("service_type","LIKE","fee")->where("fee_type","LIKE","accommodation")->get();
			foreach($dbFees as $dbFee) {
				if(!in_array($dbFee->service_id,$feeIds)) {
					$pr2 = \DB::select("SELECT calculateServicePrice('".$dbFee->service_id."','".$optAccommodation->option_accommodation_duration."','".$optAccommodation->option_accommodation_duration_unit."') as price" );
					$pr2 = reset($pr2);
					//var_dump($pr2); die();
					if((!is_null($pr2->price)) && (!$this->checkBlockedFee($dbFee->service_id))) {
						QuoteOptionService::create([
							'option_id'=>$this->option_id,
							'service_id'=>$dbFee->service_id, 
							'option_service_name'=>$dbFee->service_name,
							'option_service_partner'=>$dbFee->campus->partner->partner_name,
							'option_service_campus'=>$dbFee->campus->campus_name, 
							'option_service_price'=>$pr2->price,
							'option_service_gross_price'=>$pr2->price,
							'option_service_price_type'=>'total',
							'option_service_description'=>$dbFee->service_description,
							'option_service_quantity'=>1,
							'option_currency_code'=>$curr->currency_code,
							'currency_id'=>$curr->currency_id,
							'option_service_start_date'=>$dbFee->option_course_start_date
						]);
					}
					$feeIds[] = $dbFee->service_id;
				}
			}	
		}
		// die();
		$fees = $this->services()->whereHas("service",function($q) {
			$q->where("service_type","LIKE","fee");
		})->get();
		// process fee discounts
		foreach($fees as $optFee) {
			
			$today = date("Y-m-d", strtotime($optFee->created_at));
			
			$optFee->option_service_price = $optFee->option_service_gross_price;
					
			$promotions = Promotion::whereHas("fees",function($q) use($optFee) {
				$q->where("promotions_services.service_id","=",$optFee->service_id);
			})
					->where("promotion_start",'<=',$today)
					->where("promotion_end",'>=',$today)
					->where("promotion_type","LIKE","fee")->orderBy("created_at","asc")->get();
			foreach($promotions as $promotion) {
				if( $this->checkBlockedPromotion("0", $promotion->promotion_id) ) {
					continue;
				}
				$per = null;
				$fix = null;
				if($promotion->promotion_discount_type=='percentage') {
					$per = $promotion->promotion_discount_amount;
					$discount = $optFee->option_service_price * ($promotion->promotion_discount_amount / 100);
					$discount = number_format($discount,2,".",""); 
				} else if($promotion->promotion_discount_type=='fixed'){
					$fix = $promotion->promotion_discount_amount;
					$discount = $promotion->promotion_discount_amount;
				}
				
				$optFee->option_service_price = $optFee->option_service_price - $discount;
				
				QuoteOptionPromotion::create([
					'option_id'=>$this->option_id,
					'promotion_id'=>$promotion->promotion_id,
					'option_service_id'=>$optFee->option_service_id,
					'promotion_amount'=>$discount,
					'currency_id'=>$optFee->currency_id,
					'promotion_percentage'=>$per,
					'promotion_fixed'=>$fix
				]);
			}
			
			$optFee->save();
		}
	}
	
	public function mustLoadRelations(){
		$this->load(["quote"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>$this->option_name,
			'log_record_parent_id'=>$this->quote_id,
			'log_record_parent_name'=>'#'.$this->quote_id
		];
	}
	
	public function isLoggable($event) {
		
		if($event=='updated') { 
			$dirty = $this->getDirty();

			if (!is_array($dirty))
			{ 
				$dirty = [];
			}
			unset($dirty['updated_at']);
			if(count($dirty)==1 && isset($dirty['option_deleted_fees'])) {
				return false;
			}
			if(count($dirty)==1 && isset($dirty['option_deleted_promotions'])) {
				return false;
			}
		} 
		
		return true;
	}
} 
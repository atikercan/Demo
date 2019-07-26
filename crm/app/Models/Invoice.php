<?php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model; 
use App\Helpers\RouteHelper;
use App\Models\ExchangeRate;
use App\Traits\Loggable;

class Invoice extends Model
{   
	use Loggable;
	
	protected $casts = [
        'invoice_deleted_fees' => 'array',
        'invoice_deleted_promotions' => 'array',
		'invoice_status'=>'integer'
    ]; 
	
    protected $table = 'invoices';
    protected $primaryKey = 'invoice_id'; 
	
    protected $fillable = array(
		'invoice_id',
		'account_id',
		'branch_id',
		'student_id',
		'admin_id',
		'invoice_no',
		'language',
		'currency_id',
		'issue_date',
		'due_date',
		'invoice_notes',
		'invoice_status',
		'guid',
		'invoice_is_canceled',
		'invoice_cancel_reason',
		'canceled_at',
		'installment',
		'installment_count',
		'deposit_required',
		'deposit_currency_id',
		'deposit_due_date',
		'deposit_amount',
		'price',
		'gross_price',
		'paid',
		'outstanding',
		'cost',
		'commission_amount',
		'instant_commission_account',
		'invoice_is_sent',
		'viewed_at',
		'invoice_deleted_fees',
		'invoice_deleted_promotions',
		'invoice_tax_type',
		'invoice_tax_name',
		'invoice_tax_office',
		'invoice_tax_no',
		'invoice_tax_address',
		'invoice_is_notified',
		'contract_confirmed',
		'contract_confirmed_at'
	); 
	public function scopeCurrentAccount($query) {
		$acc_id = RouteHelper::getCurrentAccount()->account_id;
        return $query->where('invoices.account_id', '=', $acc_id);
    }
	public function admin() {
        return $this->belongsTo('App\Admin', 'admin_id', 'id');
    }
	public function student() {
        return $this->belongsTo('App\Models\Student', 'student_id', 'student_id')->withTrashed();
    }
	public function account() {
        return $this->belongsTo('App\Models\Account', 'account_id', 'account_id')->withTrashed();
    }
	public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id', 'branch_id')->withTrashed();
    }
	public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id', 'currency_id');
    } 
	public function partners() {
        return $this->hasMany('App\Models\InvoicePartner', 'invoice_id', 'invoice_id')->orderBy('invoices_partners.created_at','asc');
    }
	public function courses() {
        return $this->hasMany('App\Models\InvoiceCourse', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function accommodations() {
        return $this->hasMany('App\Models\InvoiceAccommodation', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function services() {
        return $this->hasMany('App\Models\InvoiceService', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function promotions() {
        return $this->hasMany('App\Models\InvoicePromotion', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function services_fees() {
        return $this->services()->whereHas("service",function($q) {
			$q->where("service_type","LIKE","fee");
		})->get();
    } 
	public function services_services() {
        return $this->services()->where(function($q) {
			$q->whereHas("service",function($q) {
				$q->where("service_type","LIKE","service");
			})->orWhere("service_id");
		})->get();
    } 
	public function payments() {
        return $this->hasMany('App\Models\InvoicePayment', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function getLastPayment() {
		return $this->paymentsUnordered()->orderBy("due_date","desc")->first();
	}
	public function commissions() {
        return $this->hasMany('App\Models\InvoiceCommission', 'invoice_id', 'invoice_id')->orderBy('created_at','asc');
    }
	public function getAllDurations() {
		$x=$this->partners()->whereNotNull("duration")->where("duration","!=","")->groupBy("duration_unit")->selectRaw("duration_unit,SUM(duration) as duration")->
				orderByRaw(
						"(duration_unit='week') DESC, (duration_unit='month') DESC, (duration_unit='year') DESC,(duration_unit='term') DESC,(duration_unit='semester') DESC"
						)->get();
		$ret = [];
		foreach($x as $rec) {
			$ret[] = [
				'duration'=>$rec->duration,
				'duration_unit'=>$rec->duration_unit,
			];
		}
		return $ret;
	}
	public function paymentsUnordered() {
        return $this->hasMany('App\Models\InvoicePayment', 'invoice_id', 'invoice_id');
    }
	public function recordedPayments() {
        return $this->hasMany('App\Models\InvoiceRecordedPayment', 'invoice_id', 'invoice_id');
    }
	public function paymentsOrdered() {
        return $this->paymentsUnordered()->orderByRaw("(payment_type LIKE 'deposit') DESC, (payment_type LIKE 'installment') DESC, (payment_type LIKE 'payment') DESC, due_date ASC");
    }
	
	public function getTotalPriceByCurrencyCode() {
		$totalprices = [];
		foreach($this->courses as $course) {
			if($course->currency) {
				if(!isset($totalprices[$course->currency->currency_code])) {
					$totalprices[$course->currency->currency_code] = $course->invoice_course_price;
				} else {
					$totalprices[$course->currency->currency_code] = $totalprices[$course->currency->currency_code] + $course->invoice_course_price;
				}
			}
		}
		foreach($this->accommodations as $course) {
			if($course->currency) {
				if(!isset($totalprices[$course->currency->currency_code])) {
					$totalprices[$course->currency->currency_code] = $course->invoice_accommodation_price;
				} else {
					$totalprices[$course->currency->currency_code] = $totalprices[$course->currency->currency_code] + $course->invoice_accommodation_price;
				}
			}
		}
		foreach($this->services as $course) {
			if($course->currency) {
				if(!isset($totalprices[$course->currency->currency_code])) {
					$totalprices[$course->currency->currency_code] = $course->invoice_service_price;
				} else {
					$totalprices[$course->currency->currency_code] = $totalprices[$course->currency->currency_code] + $course->invoice_service_price;
				}
			}
		}
		foreach($this->promotions()->whereNull("invoice_course_id")->whereNull("invoice_service_id")->get() as $service) {
			if(!isset($totalprices[$service->currency->currency_code])) {
				$totalprices[$service->currency->currency_code] = 0 - $service->promotion_fixed;
			} else {
				$totalprices[$service->currency->currency_code] = $totalprices[$service->currency->currency_code] - $service->promotion_fixed;
			} 
		}
		
		return $totalprices;
	} 
	public function getAllPayments() {
		$payments = $this->paymentsUnordered()->orderByRaw("(payment_type LIKE 'deposit') DESC, (payment_type LIKE 'installment') DESC, (payment_type LIKE 'payment') DESC, due_date ASC");
		return $payments->get();
	}
	
	public function nextPayment() {
		$nextPayment = false;
		$taksitno = 0;
		foreach($this->getAllPayments() as $payment) { 
			$payment->name = trans('admin.invoices.full_payment');
			if($payment->payment_type=='deposit') {
				$payment->name = trans('admin.invoices.deposit');
			} elseif($payment->payment_type=='installment') {
				$taksitno++;
				$payment->name = $taksitno.". ".trans('admin.invoices.payment');
				if($taksitno==$this->installment_count) {
					$payment->name = trans('admin.invoices.final_payment');
				}
			}
			if(is_null($payment->paid_at)) {
				return $payment;
			}
		}
	}
	
	public function updateInvoicePrice() {
		if(is_null($this->currency_id)) {
			return;
		}
		$currency = $this->currency;
		$allrates = [];
		
		$rates = ExchangeRate::where("id","!=",0);
		
		$allrates = $this->getRates($this->issue_date);
		
		$total = 0;
		$gross_total = 0;
		foreach($this->courses as $course) {
			if(isset($allrates[$course->invoice_currency_code][$currency->currency_code])) {
				$r = $allrates[$course->invoice_currency_code][$currency->currency_code];
				$total = $total + ($course->invoice_course_price * $r);
				$gross_total = $gross_total + ($course->invoice_course_gross_price * $r);
			}
		}
		
		foreach($this->accommodations as $accommodation) {
			if(isset($allrates[$accommodation->invoice_currency_code][$currency->currency_code])) {
				$r = $allrates[$accommodation->invoice_currency_code][$currency->currency_code];
				$total = $total + ($accommodation->invoice_accommodation_price * $r);
				$gross_total = $gross_total + ($accommodation->invoice_accommodation_price * $r);
			}
		}
		foreach($this->services as $service) {
			if(isset($allrates[$service->invoice_currency_code][$currency->currency_code])) {
				$r = $allrates[$service->invoice_currency_code][$currency->currency_code];
				$total = $total + ($service->invoice_service_price * $r);
				$gross_total = $gross_total + ($service->invoice_service_gross_price * $r);
			}
		}
		
		foreach($this->promotions()->whereNull("invoice_course_id")->whereNull("invoice_service_id")->get() as $service) {
			if(isset($allrates[$service->currency->currency_code][$currency->currency_code])) {
				$r = $allrates[$service->currency->currency_code][$currency->currency_code];
				$total = $total - ($service->promotion_fixed * $r);
			}
		}
	 
		$this->price = number_format($total,2,'.','');
		$this->gross_price = number_format($gross_total,2,'.','');
		$this->outstanding = $this->price - $this->paid;
		$this->save();
		$this->updatePayments();
	}
	public function updatePayments() {
		$total = $this->price;
		if($this->deposit_required==1) {
			$deposit = $this->payments()->where('payment_type','LIKE','deposit')->first();
			if($deposit) {
				$total = $total - $deposit->payment_amount;
			}
		}
		if($this->installment==1) {
			$payments = $this->payments()->where('payment_type','LIKE','installment')->get();
			foreach($payments as $k=>$payment) {
				if(($k+1)<$payments->count()) {
					$total = $total - $payment->payment_amount;
				} else {
					$payment->payment_amount = $total;
					$payment->save();
					$payment->updatePaymentStatus();
				}
			}
		} else {
			//fullpayment
			$payment = $this->payments()->where('payment_type','LIKE','payment')->first();
			if($payment) {
				$payment->payment_amount = $total;
				$payment->save();
				$payment->updatePaymentStatus();
			} else {
				InvoicePayment::create([
					'invoice_id'=>$this->invoice_id,
					'payment_type'=>'payment',
					'payment_amount'=>$total,
					'paid_amount'=>0,
					'outstanding_amount'=>$total,
					'due_date'=>$this->due_date
				]);
			}
		}
	}
	public function refreshPaymentStatuses() {
		$recordeds = $this->recordedPayments()->orderBy('recorded_payment_date','asc')->get();
		\DB::statement("UPDATE invoices_payments SET outstanding_amount = payment_amount, paid_amount = 0, paid_at = NULL WHERE invoice_id = '".$this->invoice_id."'");
		$total = 0;
		foreach($recordeds as $recorded) {
			$total = $total + $recorded->recorded_payment_amount;
			$payments = $this->paymentsOrdered()->get();
			foreach($payments as $payment) {
				if($recorded->recorded_payment_amount==0) {
					continue;
				}
				if($payment->outstanding_amount>$recorded->recorded_payment_amount) {
					// yetmiyor sadece düş
					$payment->outstanding_amount = $payment->outstanding_amount - $recorded->recorded_payment_amount;
					$payment->paid_amount = $payment->paid_amount + $recorded->recorded_payment_amount;
					$recorded->recorded_payment_amount = 0;
				} else {
					
					$payment->paid_amount = $payment->paid_amount + $payment->outstanding_amount; 
					$payment->paid_at = $recorded->recorded_payment_date;
					
					$recorded->recorded_payment_amount = $recorded->recorded_payment_amount - $payment->outstanding_amount;
					
					$payment->outstanding_amount = 0;
				}
				$payment->save();
			}
		}
		
		$this->paid = $total;
		$this->outstanding = $this->price - $this->paid;
		
		if($this->deposit_required==1) {
			$paid_deposit = $this->payments()->where("payment_type","LIKE","deposit")->whereNotNull("paid_at")->first();
		} else {
			$paid_deposit = false;
		}
		
		$this->updatePartnerPaymentStatuses();
		
		if($this->outstanding<0) {
			$this->outstanding = 0;
			$this->invoice_status = 5;
		} elseif($this->outstanding==0 && $this->price>0) {
			$this->invoice_status = 3;
		} elseif($this->outstanding>0 && $this->paid>0) {
			$this->invoice_status = 2; 
			if($paid_deposit && $this->paid == $paid_deposit->paid_amount) {
				$this->invoice_status = 1;
			}
		} elseif($this->paid == 0) {
			$this->invoice_status =0;
		}
		
		$this->save(); 
		
		$this->storeCommissions();
		$this->storeInstantCommission();
	}
	
	public function updatePartnerPaymentStatuses() {
		$recordeds = $this->recordedPayments()->orderBy('created_at','asc')->get();
		
		$rates = $this->getRates($this->issue_date);
		
		$currencyIdCodes = Currency::getIdCodeTranslationArray(); 
		
		$scode = ( isset($currencyIdCodes[$this->currency_id]) )?$currencyIdCodes[$this->currency_id]:"";
		
		\DB::statement("UPDATE invoices_partners SET outstanding = price, paid = 0, paid_in_currency=0,outstanding_in_currency = price_in_currency WHERE invoice_id = '".$this->invoice_id."'");
		\DB::statement("UPDATE invoices_partners SET outstanding = price, paid = 0-price, paid_in_currency=0-price,outstanding_in_currency = price WHERE invoice_id = '".$this->invoice_id."' AND price<0");
		foreach($recordeds as $recorded) { 
			$partners = $this->partners()->get();
			foreach($partners as $partner) {
				if($recorded->recorded_payment_amount==0) {
					continue;
				}
				$dcode = ( isset($currencyIdCodes[$partner->currency_id]) )?$currencyIdCodes[$partner->currency_id]:"";
				
				$rate = ( isset($rates[$scode][$dcode]) )?$rates[$scode][$dcode]:1;
				if($partner->outstanding_in_currency>$recorded->recorded_payment_amount) {
					// yetmiyor sadece düş
					
					$partner->outstanding = $partner->outstanding - ($recorded->recorded_payment_amount * $rate);
					$partner->paid = $partner->paid + ($recorded->recorded_payment_amount * $rate);
					
					$partner->outstanding_in_currency = $partner->outstanding_in_currency - $recorded->recorded_payment_amount;
					$partner->paid_in_currency = $partner->paid_in_currency + $recorded->recorded_payment_amount;
					$recorded->recorded_payment_amount = 0;
				} else {
					
					//$partner->outstanding = $partner->outstanding - ($recorded->recorded_payment_amount * $rate);
					$partner->paid = $partner->paid + $partner->outstanding;
					$partner->outstanding = 0;
					
					$partner->paid_in_currency = $partner->paid_in_currency + $partner->outstanding_in_currency;  
					
					$recorded->recorded_payment_amount = $recorded->recorded_payment_amount - $partner->outstanding_in_currency;
					
					$partner->outstanding_in_currency = 0;
				}
				$partner->save();
			}
		} 
	}
	public function calculateCommissions() {
		$commissions = [];
		foreach($this->courses as $tmp) {
			$curr = $tmp->currency_id;
			if(!isset($commissions[$curr])) {
				$commissions[$curr] = 0;
			}
			//var_dump($tmp->invoice_course_id."-".$tmp->calculateCommission());
			$commissions[$curr] = $commissions[$curr] + $tmp->calculateCommission();
		}
		foreach($this->accommodations as $tmp) {
			$curr = $tmp->currency_id;
			if(!isset($commissions[$curr])) {
				$commissions[$curr] = 0;
			}
			//var_dump($tmp->invoice_accommodation_id."-".$tmp->calculateCommission());
			$commissions[$curr] = $commissions[$curr] + $tmp->calculateCommission();
		} 
		foreach($this->services as $tmp) {
			$curr = $tmp->currency_id;
			if(!isset($commissions[$curr])) {
				$commissions[$curr] = 0;
			}
			//var_dump($tmp->invoice_service_id."-".$tmp->calculateCommission());
			$commissions[$curr] = $commissions[$curr] + $tmp->calculateCommission();
		} 
	 
		return $commissions;
	}
	
	public function updatePartners() {
		$data = [];
		//courses
		$recs = \DB::select("SELECT
				t.partner_id,
				t.campus_id,
				t.currency_id,
				t.partner_name,
				t.item_name,
				SUM( t.price ) AS price,
				SUM( t.comm ) as commission,
				type,
				type_detail,
				duration,
				durationunit,
				COUNT( t.partner_id ) AS cont
			FROM
				(
			SELECT
				p.partner_id,
				pc.campus_id,
				invoices_courses.currency_id,
				invoices_courses.invoice_course_name as item_name,
				invoices_courses.invoice_course_partner as partner_name,
				SUM( invoice_course_price ) AS price,
				SUM( calculateInvoiceCourseCommission(invoices_courses.invoice_course_id)) as comm,
				'course' as type,
				invoices_courses.invoice_course_course_type as type_detail,
				invoices_courses.invoice_course_duration as duration,
				invoices_courses.invoice_course_duration_unit as durationunit
			FROM
				invoices_courses
				LEFT JOIN partners_campuses_courses pcc ON pcc.course_id = invoices_courses.course_id
				LEFT JOIN partners_campuses pc ON pc.campus_id = pcc.campus_id
				LEFT JOIN partners p ON p.partner_id = pc.partner_id 
			WHERE
				invoices_courses.invoice_id = '".$this->invoice_id."' UNION
			SELECT
				p.partner_id,
				pc.campus_id,
				invoices_accommodations.currency_id,
				invoices_accommodations.invoice_accommodation_name as item_name,
				invoices_accommodations.invoice_accommodation_partner as partner_name,
				SUM( invoice_accommodation_price ) AS price,
				SUM( calculateInvoiceAccommodationCommission(invoices_accommodations.invoice_accommodation_id)) as comm,
				'accommodation' as type,
				'' as type_detail,
				0 as duration,
				'' as durationunit
			FROM
				invoices_accommodations
				LEFT JOIN partners_campuses_accommodations pcc ON pcc.accommodation_id = invoices_accommodations.accommodation_id
				LEFT JOIN partners_campuses pc ON pc.campus_id = pcc.campus_id
				LEFT JOIN partners p ON p.partner_id = pc.partner_id 
			WHERE
				invoices_accommodations.invoice_id = '".$this->invoice_id."' UNION
			SELECT
				p.partner_id,
				pc.campus_id,
				invoices_services.currency_id,
				invoices_services.invoice_service_name as item_name,
				invoices_services.invoice_service_partner as partner_name,
				SUM( invoice_service_price ) AS price,
				SUM( calculateInvoiceServiceCommission(invoices_services.invoice_service_id)) as comm,
				'service' as type,
				'' as type_detail,
				0 as duration,
				'' as durationunit
			FROM
				invoices_services
				LEFT JOIN partners_campuses_services pcc ON pcc.service_id = invoices_services.service_id
				LEFT JOIN partners_campuses pc ON pc.campus_id = pcc.campus_id
				LEFT JOIN partners p ON p.partner_id = pc.partner_id 
			WHERE
				invoices_services.invoice_id = '".$this->invoice_id."' 
				) AS t GROUP BY t.partner_id,t.campus_id,t.currency_id,t.type,t.duration
			  ORDER BY (ISNULL(partner_id)) ASC");
		
		 
		$this->partners()->delete();
		
		$currencyIdCodes = Currency::getIdCodeTranslationArray();
		
		$rates = $this->getRates($this->issue_date);
		
		$scode = ( isset($currencyIdCodes[$this->currency_id]) )?$currencyIdCodes[$this->currency_id]:"";
		
		foreach($recs as $rec) {
			if(is_null($rec->currency_id))
				continue;

			$ccode = ( isset($currencyIdCodes[$rec->currency_id]) )?$currencyIdCodes[$rec->currency_id]:"";
			
			if($rec->type=='course') {
				$type = trans("admin.invoices.course");
				if(!is_null($rec->type_detail) && $rec->type_detail!="") {
					$type = $rec->type_detail;
				}
			} else if($rec->type=='accommodation') {
				$type = trans("admin.invoices.accommodation");
				$rec->duration = null;
				$rec->durationunit = null;
			} else if($rec->type=='service') {
				$type = trans("admin.invoices.service");
				$rec->duration = null;
				$rec->durationunit = null;
			}
			
			$rate = ( isset($rates[$ccode][$scode]) )?$rates[$ccode][$scode]:1;
			InvoicePartner::create([
				'invoice_id'=>$this->invoice_id,
				'partner_id'=>$rec->partner_id,
				'campus_id'=>$rec->campus_id,
				'partner_name'=>$rec->partner_name,
				'currency_id'=>$rec->currency_id,
				'price'=>$rec->price,
				'price_in_currency'=>($rec->price * $rate),
				'commission'=>$rec->commission,
				'invoice_count'=>$rec->cont,
				'cost'=>($rec->price-$rec->commission),
				'type'=>$type,
				'duration'=>$rec->duration,
				'duration_unit'=>$rec->durationunit
			]);
		}
		
		//indirimleri işle
		
		$discounts = $this->promotions()->whereNull("invoice_course_id")->whereNull("invoice_service_id")->get();
		foreach($discounts as $discount) {
			$ccode = ( isset($currencyIdCodes[$discount->currency_id]) )?$currencyIdCodes[$discount->currency_id]:""; 
			$rate = ( isset($rates[$ccode][$scode]) )?$rates[$ccode][$scode]:1;
			InvoicePartner::create([
				'invoice_id'=>$this->invoice_id,
				'partner_id'=>null,
				'partner_name'=>trans('admin.invoices.promotion').( ( !is_null($discount->promotion_name) && !empty($discount->promotion_name) )?' - '.$discount->promotion_name:'' ),
				'currency_id'=>$discount->currency_id,
				'price'=>0-$discount->promotion_fixed,
				'paid'=>$discount->promotion_fixed,
				'price_in_currency'=>0-($discount->promotion_fixed * $rate),
				'commission'=>0-$discount->promotion_fixed,
				'invoice_count'=>1,
				'cost'=>$discount->promotion_fixed,
				'type'=>trans('admin.invoices.promotion'),
				'duration'=>null,
				'duration_unit'=>null
			]);
			
			
			 
		}

	}
	
	public function storeCommissions() {
		
		$rates = $this->getRates( $this->issue_date ); 
		
		$this->commissions()->delete();
		
		$currenciesQ = Currency::currentAccount()->orderBy("currency_order","asc")->get();
		$currencies = [];
		
		foreach($currenciesQ as $cur) {
			$currencies[$cur->currency_id] = $cur->currency_code;
		}
		
		if($this->currency_id!="" && !is_null($this->currency_id)) {
			$invoice_currency_code = $currencies[$this->currency_id];

			$commissions = $this->calculateCommissions();

			
 
			
			$total_commission = 0; 

			foreach($commissions as $cur_id=>$commission) {
				if(is_null($cur_id))
					continue;

				InvoiceCommission::create([
					'invoice_id'=>$this->invoice_id,
					'currency_id'=>$cur_id,
					'commission_amount'=>$commission
				]);

				$item_currency_code = $currencies[$cur_id];

				$rate = 1;
				if( isset($rates[$item_currency_code][$invoice_currency_code]) ) {
					$rate = $rates[$item_currency_code][$invoice_currency_code];
				}

				$total_commission += $commission * $rate;
			}

			$discounts = $this->promotions()->whereNull("invoice_course_id")->whereNull("invoice_service_id")->get();
			foreach($discounts as $discount) {
				$discount_currency_code = $currencies[$discount->currency_id];
				
				$commission = 0-$discount->promotion_fixed;
				
				InvoiceCommission::create([
					'invoice_id'=>$this->invoice_id,
					'currency_id'=>$discount->currency_id,
					'commission_amount'=>$commission
				]); 
				
				$rate = 1;
				if( isset($rates[$discount_currency_code][$invoice_currency_code]) ) {
					$rate = $rates[$discount_currency_code][$invoice_currency_code];
				}

				$total_commission += $commission * $rate;
			}
			
			$this->cost = $this->price - $total_commission;
			
			if($this->paid>$this->price) {
				$total_commission += ($this->paid-$this->price);
			} 
			$this->commission_amount = $total_commission;
			
			/* Instant Commission */  
			$this->save();
		}
	}
	
	public function storeInstantCommission() {
		
		$instant_commission = 0;
		$total_commission = $this->commission_amount;
		$total_cost = $this->cost;
		$total_paid = $this->paid;
		$deposit_amount = (!is_null($this->deposit_amount))?$this->deposit_amount:0;
		
		 
		
		if($this->deposit_required>0 && $total_paid>=$deposit_amount) {
			$instant_commission = $deposit_amount;
		} 
		
		/*
		if($this->deposit_required>0 && $instant_commission<$deposit_amount && $deposit_amount<$total_cost) {
			$instant_commission = $deposit_amount;
		}
		*/
		if($total_paid>=$total_cost) {
			$instant_commission = $total_paid-$total_cost;
			if($this->deposit_required>0 && $instant_commission<$deposit_amount && $deposit_amount<$total_cost) {
				$instant_commission = $deposit_amount;
			}
			if($total_paid>$this->price) {
				$instant_commission = $total_paid - $total_cost;
			} else if($instant_commission>=$total_commission && $this->deposit_required>0) {
				$instant_commission = $total_commission;
			} 
		} elseif($this->deposit_required>0 && $total_paid>$deposit_amount && $deposit_amount<$total_cost && $total_commission>=0) {
			$instant_commission = $deposit_amount;
		} elseif($this->deposit_required>0 && $total_paid>$deposit_amount && $deposit_amount<$total_cost && $total_commission>=0) {
			$instant_commission = $deposit_amount;
		} elseif($total_commission<0 && $this->deposit_required>0 && $total_paid>$deposit_amount) {
			$instant_commission = $total_commission;
		} elseif($total_commission<0 && ($this->deposit_required==0 || $total_paid<$deposit_amount)) {
			$instant_commission = $total_commission;
		}
		
		if($instant_commission>$total_commission) {
			//$this->commission_amount = $instant_commission; 
		}
		$this->instant_commission_amount = $instant_commission;
	  
		$total_paid = 0;
		$total_paid_deposit = 0;
		
		if($this->deposit_required>0) {
			$total_cost += $deposit_amount;
		}  
		
		$this->save();
		
		/* Anlık Kar */
		foreach($this->recordedPayments()->orderBy("recorded_payment_date","asc")->get() as $recordedPayment ) {
			$tmp_total_paid = $total_paid + $recordedPayment->recorded_payment_amount;
			if($this->deposit_required>0 && $tmp_total_paid<=$deposit_amount) {
				$recordedPayment->instant_profit_amount = $recordedPayment->recorded_payment_amount;
			} else if($this->deposit_required>0 && $total_paid<=$deposit_amount) {
				$recordedPayment->instant_profit_amount = $deposit_amount - $total_paid;
			} else if($tmp_total_paid<$total_cost) {
				$recordedPayment->instant_profit_amount = 0;
			} else if($total_paid<$total_cost) {
				$tmp = $tmp_total_paid - $total_cost;
				$recordedPayment->instant_profit_amount = $tmp;
			} else if($total_paid>$total_cost) { 
				$recordedPayment->instant_profit_amount = $recordedPayment->recorded_payment_amount;
			}
			
			$total_paid += $recordedPayment->recorded_payment_amount;
			$recordedPayment->save();
		}
	}
	public function getRates($date=null) {
	
		$rates = false;
		if(!empty($date) && !is_null($date)) {
			$rates = ExchangeRateHistory::where("history_date","=",date("Y-m-d",strtotime($date)))->first();
			if($rates) {
				$return = [];
				foreach($rates->history_rates as $sk=>$sd) {
					
					$return[$sk] = [];
							
					foreach($sd as $dk=>$dd) {
						
						$return[$sk][$dk] = $dd;
					}
				} 
			}
		} 
		if(!$rates) {
			$rates = ExchangeRate::where("id","!=",0);

			$return = [];

			$all = $rates->get();

			foreach($all as $tek) {
				if(!isset($return[$tek->source_currency])) {
					$return[$tek->source_currency] = [];
				}
				$return[$tek->source_currency][$tek->destination_currency] = $tek->rate;
			}
		}
		
		
		
		return $return;
	}
	
	public function addBlockedPromotion($invoice_course_id, $promotion_id) {
		if(!is_array($this->invoice_deleted_promotions)) {
			$this->invoice_deleted_promotions = [];
		}
		
		$retailid = $invoice_course_id."-".$promotion_id;
		
		$x = $this->invoice_deleted_promotions;
		if(!in_array($retailid,$this->invoice_deleted_promotions)) {
			$x[] = $retailid;
		}
		$this->invoice_deleted_promotions = $x;
		$this->save();
	}
	public function checkBlockedPromotion($invoice_course_id, $promotion_id) {
		if(!is_array($this->invoice_deleted_promotions)) {
			$this->invoice_deleted_promotions = [];
		}
		
		$retailid = $invoice_course_id."-".$promotion_id;
				
		//var_dump($fee_id); var_dump($this->option_deleted_fees); 
		return in_array($retailid,$this->invoice_deleted_promotions);
	}
	
	public function addBlockedFee($fee_id) {
		if(!is_array($this->invoice_deleted_fees)) {
			$this->invoice_deleted_fees = [];
		}
		$x = $this->invoice_deleted_fees;
		if(!in_array($fee_id,$this->invoice_deleted_fees)) {
			$x[] = $fee_id;
		}
		$this->invoice_deleted_fees = $x;
		$this->save();
	}
	public function checkBlockedFee($fee_id) {
		if(!is_array($this->invoice_deleted_fees)) {
			$this->invoice_deleted_fees = [];
		}
		//var_dump($fee_id); var_dump($this->option_deleted_fees); 
		return in_array($fee_id,$this->invoice_deleted_fees);
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
					$pr2 = \DB::select("SELECT calculateServicePrice('".$dbFee->service_id."','".$optCourse->invoice_course_duration."','".$optCourse->invoice_course_duration_unit."') as price" );
					$pr2 = reset($pr2); 
					
					//var_dump($pr2); die();
					if((!is_null($pr2->price)) && (!$this->checkBlockedFee($dbFee->service_id))) {
						InvoiceService::create([
							'invoice_id'=>$this->invoice_id,
							'service_id'=>$dbFee->service_id,
							'invoice_course_id'=>$optCourse->invoice_course_id,
							'invoice_service_name'=>$dbFee->service_name,
							'invoice_service_partner'=>$dbFee->campus->partner->partner_name,
							'invoice_service_campus'=>$dbFee->campus->campus_name, 
							'invoice_service_price'=>$pr2->price,
							'invoice_service_gross_price'=>$pr2->price,
							'invoice_service_price_type'=>'total',
							'invoice_service_description'=>$dbFee->service_description,
							'invoice_service_quantity'=>1,
							'invoice_currency_code'=>$curr->currency_code,
							'currency_id'=>$curr->currency_id,
							'invoice_service_start_date'=>$dbFee->invoice_course_start_date,
							'commission'=>$dbFee->commission,
							'commission_type'=>$dbFee->commission_type,
						]);
					}
					$feeIds[] = $dbFee->service_id;
				}
			}	
		}
		
		foreach($this->accommodations as $optAcc) {
			if(!$optAcc->accommodation) {
				continue;
			}
			$dbAcc = $optAcc->accommodation;
			
			$curr = $dbAcc->campus->getCurrency();
			
			$dbFees = $dbAcc->campus->services()->where("service_type","LIKE","fee")->where("fee_type","LIKE","accommodation")->get();
			foreach($dbFees as $dbFee) {
				if(!in_array($dbFee->service_id,$feeIds)) {
					$pr2 = \DB::select("SELECT calculateServicePrice('".$dbFee->service_id."','".$optAcc->invoice_accommodation_duration."','".$optAcc->invoice_accommodation_duration_unit."') as price" );
					$pr2 = reset($pr2); 
				 
					//var_dump($pr2); die();
					if((!is_null($pr2->price)) && (!$this->checkBlockedFee($dbFee->service_id))) {
						InvoiceService::create([
							'invoice_id'=>$this->invoice_id,
							'service_id'=>$dbFee->service_id,
							'invoice_service_name'=>$dbFee->service_name,
							'invoice_service_partner'=>$dbFee->campus->partner->partner_name,
							'invoice_service_campus'=>$dbFee->campus->campus_name, 
							'invoice_service_price'=>$pr2->price,
							'invoice_service_gross_price'=>$pr2->price,
							'invoice_service_price_type'=>'total',
							'invoice_service_description'=>$dbFee->service_description,
							'invoice_service_quantity'=>1,
							'invoice_currency_code'=>$curr->currency_code,
							'currency_id'=>$curr->currency_id,
							'invoice_service_start_date'=>$dbFee->invoice_course_start_date,
							'commission'=>$dbFee->commission,
							'commission_type'=>$dbFee->commission_type,
						]);
					}
					$feeIds[] = $dbFee->service_id;
				}
			}	
		}
		
		$fees = $this->services()->whereHas("service",function($q) {
			$q->where("service_type","LIKE","fee");
		})->get();
		
		// process fee discounts
		foreach($fees as $optFee) {
			
			$d = false;
			
			if($optFee->invoice_course) {
				$today = date("Y-m-d", strtotime($optFee->invoice_course->created_at));
			} else {
				$today = date("Y-m-d", strtotime($optFee->created_at));
			} 
			
			
			$optFee->invoice_service_price = $optFee->invoice_service_gross_price;
			$optFee->invoice_commission_base_price = $optFee->invoice_service_gross_price;
			
			$promotions = Promotion::whereHas("fees",function($q) use($optFee) {
				$q->where("promotions_services.service_id","=",$optFee->service_id);
			})
					->where("promotion_start",'<=',$today)
					->where("promotion_end",'>=',$today)
					->where("promotion_type","LIKE","fee")->orderBy("created_at","asc")->get();
			foreach($promotions as $promotion) { 
				$per = null;
				$fix = null;
				if($promotion->promotion_discount_type=='percentage') {
					$per = $promotion->promotion_discount_amount;
					$discount = $optFee->invoice_service_price * ($promotion->promotion_discount_amount / 100);
					$discount = number_format($discount,2,".",""); 
				} else if($promotion->promotion_discount_type=='fixed'){
					$fix = $promotion->promotion_discount_amount;
					$discount = $promotion->promotion_discount_amount;
				}
				
				$optFee->invoice_commission_base_price = $optFee->invoice_commission_base_price - $discount;
				if( $this->checkBlockedPromotion("0", $promotion->promotion_id) ) {
					continue;
				}
				$optFee->invoice_service_price = $optFee->invoice_service_price - $discount;
				
				InvoicePromotion::create([
					'invoice_id'=>$this->invoice_id,
					'promotion_id'=>$promotion->promotion_id,
					'invoice_service_id'=>$optFee->invoice_service_id,
					'promotion_amount'=>$discount,
					'currency_id'=>$optFee->currency_id,
					'promotion_percentage'=>$per,
					'promotion_fixed'=>$fix
				]);
			}
			
			$optFee->save();
		}
	}
	public function fixPromotions() {
		$this->promotions()->where(function($q) {
			$q->whereNotNull("invoice_course_id");
			$q->orWhereNotNull("invoice_service_id");
		})->delete();
		foreach($this->courses as $optCourse) {
			$optCourse->invoice_course_price = $optCourse->invoice_course_gross_price;
			$optCourse->save();
			
			if(!$optCourse->campus)
				continue;  
			//check price
			$d = $optCourse->invoice_course_duration;
			$dt = $optCourse->invoice_course_duration_unit;
			$today = date("Y-m-d", strtotime($optCourse->created_at));
					
			$optCourse->invoice_course_price = $optCourse->invoice_course_gross_price;
			$optCourse->invoice_commission_base_price = ( !is_null($optCourse->orginal_price) && $optCourse->orginal_price>0 )?$optCourse->orginal_price:$optCourse->invoice_course_price;
			
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
				
				$tmpD = $d - $promotion->promotion_free_duration;
				$newPriceQ = \DB::select("SELECT calculateCoursePrice('".$optCourse->course_id."','$tmpD','$dt') as price");
				$newPrice = $newPriceQ[0]->price;
				
				$discount = $optCourse->invoice_course_price - $newPrice;
				$optCourse->invoice_commission_base_price = $newPrice; 
					
				if( $this->checkBlockedPromotion($optCourse->invoice_course_id, $promotion->promotion_id) ) {
					continue;
				}
				
				$discount = $optCourse->invoice_course_price - $newPrice;
				$optCourse->invoice_course_price = $newPrice;
				InvoicePromotion::create([
					'invoice_id'=>$this->invoice_id,
					'promotion_id'=>$promotion->promotion_id,
					'invoice_course_id'=>$optCourse->invoice_course_id,
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
				
				
				$per = null;
				$fix = null;
				if($promotion->promotion_discount_type=='percentage') {
					$per = $promotion->promotion_discount_amount;
					$discount = $optCourse->invoice_course_price * ($promotion->promotion_discount_amount / 100);
					$discount = number_format($discount,2,".",""); 
				} else if($promotion->promotion_discount_type=='fixed'){
					$fix = $promotion->promotion_discount_amount;
					$discount = $promotion->promotion_discount_amount;
				}
				
				
				$optCourse->invoice_commission_base_price = $optCourse->invoice_commission_base_price - $discount;
				if( $this->checkBlockedPromotion($optCourse->invoice_course_id, $promotion->promotion_id) ) {
					continue;
				}
				
				$optCourse->invoice_course_price = $optCourse->invoice_course_price - $discount;
				
				
				InvoicePromotion::create([
					'invoice_id'=>$this->invoice_id,
					'promotion_id'=>$promotion->promotion_id,
					'invoice_course_id'=>$optCourse->invoice_course_id,
					'promotion_amount'=>$discount,
					'currency_id'=>$optCourse->currency_id,
					'promotion_percentage'=>$per,
					'promotion_fixed'=>$fix
				]);
			}
			
			$optCourse->save();
		}
	}
	
	public function processAllReqs($promotions = true, $fees = true,$price = true, $commission = true, $partners = true, $paymentStatuses = true) {
		if($promotions) {
			$this->fixPromotions();
		}
		if($fees) {
			$this->fixFees();
		}
		if($price) {
		$this->updateInvoicePrice(); 
		}
		if($commission && $this->invoice_is_canceled<1) {
			$this->storeCommissions();
			$this->storeInstantCommission();
		} elseif($commission) {
			$this->storeInstantCommission();
		}
		if($partners && $this->invoice_is_canceled<1) {
		$this->updatePartners();
		}
		if($paymentStatuses) {
		$this->refreshPaymentStatuses();
		}
		if($this->invoice_is_canceled==1) {
			$this->processCanceled();
		}
	}
	
	public function processCanceled() {
		$totalCommission = 0;
		$currencyIdCodes = Currency::getIdCodeTranslationArray();
		
		$rates = $this->getRates($this->issue_date);
		
		$scode = ( isset($currencyIdCodes[$this->currency_id]) )?$currencyIdCodes[$this->currency_id]:"";
		
		foreach($this->recordedPayments as $payment) {
			$dcode = ( isset($currencyIdCodes[$payment->currency_id]) )?$currencyIdCodes[$payment->currency_id]:"";
				
			$rate = ( isset($rates[$scode][$dcode]) )?$rates[$scode][$dcode]:1;
			
			$totalCommission += ( $payment->recorded_payment_amount * $rate);
		}
		
		$this->commission_amount = $totalCommission;
		$this->outstanding = 0;
		$this->price = $totalCommission;
		$this->save();
		
		$this->payments()->delete();
		
		
		$this->commissions()->delete();
		
		InvoiceCommission::create([
			'invoice_id'=>$this->invoice_id,
			'currency_id'=>$this->currency_id,
			'commission_amount'=>$totalCommission
		]);
		
		$this->partners()->delete();
		InvoicePartner::create([
			'invoice_id'=>$this->invoice_id,
			'partner_id'=>null,
			'partner_name'=>null,
			'currency_id'=>$this->currency_id,
			'price'=>$totalCommission,
			'price_in_currency'=>$totalCommission,
			'commission'=>$totalCommission,
			'invoice_count'=>1,
			'cost'=>0,
			'type'=>trans('admin.invoices.cancel_fee'),
			'duration'=>null,
			'duration_unit'=>null
		]);
		//InvoicePayment::where("invoice_id","=",$this->invoice_id)->delete();
	}
	
	public function mustLoadRelations(){
		$this->load(["student"]);
	}
	public function getLoggableFields() {
		return [
			'log_record_name'=>'#'.$this->invoice_id
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
			if(count($dirty)==1 && isset($dirty['invoice_deleted_promotions'])) {
				return false;
			}
			if(count($dirty)==1 && isset($dirty['invoice_deleted_fees'])) {
				return false;
			}
		} 
		
		return true;
	}
} 
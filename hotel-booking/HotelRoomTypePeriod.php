<?php

namespace App\Models\Hotel;

use \Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

class HotelRoomTypePeriod extends Model
{   
    
    use SoftDeletes;
    
    public static function boot()
    {
        parent::boot(); 
        // Attach event handler, on deleting of the user
        /*
        HotelRoomTypePeriod::deleting(function($period)
        {    
            HotelRoomTypePeriod::flushEventListeners();
            $period->deleted_user_id = Auth::user()->id; 
            $period->period_status = 'deleted';
            $period->save(['timestamps' => false]); 
            
            return true;
        });
        HotelRoomTypePeriod::updating(function($period)
        {    
            //HotelRoomTypePeriod::flushEventListeners();
            
            if($period->discountedPeriods)
            
            return false;
        });
        */
        
    }
    
    protected $table = 'hotels_roomtypes_periods';
    protected $primaryKey = 'period_id';
    
    
    
    protected $dates = ['deleted_at', 'period_start', 'period_end', 'period_sale_start', 'period_sale_end']; 
 
            
    protected $fillable = array('hotel_id','roomtype_id', 'concept_id','parent_id', 'period_start', 'period_end', 'period_sale_start', 'period_sale_end', 'period_mindays', 'period_commission', 'period_commission_cumulative', 'period_eb', 'period_eb_cumulative', 'period_kickback', 'period_kickback_cumulative', 'period_tolerance', 'period_doorprice', 'period_costprice', 'period_saleprice', 'period_displayedprice', 'period_discountrate', 'period_profitrate', 'period_showonsite', 'period_calc_from_displayedprice', 'period_day1', 'period_day2', 'period_day3', 'period_day4', 'period_day5', 'period_day6', 'period_day7', 'created_user_id', 'period_status','discount_id','discounted_from','period_showonsale','period_discounts_cumulative_inh','period_discounts_inh','period_extradiscount','period_extradiscount_cumulative','discount_name','discount_order','period_system_discountrate','period_system_saleprice'); 
 
    public function concept() {
        return $this->belongsTo('App\Models\Hotel\Concept', 'concept_id', 'concept_id');
    }
    public function roomtype() {
        return $this->belongsTo('App\Models\Hotel\HotelRoomType', 'roomtype_id', 'roomtype_id');
    }
    public function hotel() {
        return $this->belongsTo('App\Models\Hotel\Hotel', 'hotel_id', 'hotel_id');
    }
    public function discountedFrom() {
        return $this->belongsTo('App\Models\Hotel\HotelRoomTypePeriod', 'discounted_from', 'period_id');
    }
    
    public function user() {
        return $this->belongsTo('App\User', 'created_user_id', 'id');
    }  
    
    public function deleted_user() {
        return $this->belongsTo('App\User', 'deleted_user_id', 'id');
    }  
    
    public function olderversion() {
        return $this->belongsTo('App\Models\Hotel\HotelRoomTypePeriod', 'parent_id', 'period_id')->withTrashed();
    }
    
    
    public function setPeriodStartAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['period_start'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['period_start'] = null; 
        }
    }
    
    public function setPeriodEndAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['period_end'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['period_end'] = null; 
        }
    } 
    
    public function setPeriodSaleStartAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['period_sale_start'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['period_sale_start'] = null; 
        }
    }
    
    public function setPeriodSaleEndAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['period_sale_end'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['period_sale_end'] = null; 
        }
    } 
    
    public function getAllVersions() {
       $allversions = [];
       
       $tmp = $this->olderversion;
       
       while($tmp) {
           $allversions[]=$tmp;
           $tmp = $tmp->olderversion;
       }
       return $allversions;
    } 
    
    public function discountedPeriods()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypePeriod', 'discounted_from', 'period_id')
                ->orderBy('hotels_roomtypes_periods.discount_order','asc')
                ->orderBy('hotels_roomtypes_periods.period_sale_start','asc')
                ->orderBy('hotels_roomtypes_periods.period_sale_end','asc')    
                ->orderBy('hotels_roomtypes_periods.created_at','asc');
    } 
    
    public function getDiscountedRows() {
        return HotelRoomTypePeriod:: 
                where('hotels_roomtypes_periods.discounted_from','=', $this->period_id)
                ->orderBy('hotels_roomtypes_periods.discount_order','asc')
                ->orderBy('hotels_roomtypes_periods.period_sale_start','asc')
                ->orderBy('hotels_roomtypes_periods.period_sale_end','asc')    
                ->orderBy('hotels_roomtypes_periods.created_at','asc')
                ->get();
    }
    
    public function availableMinRow() {
        $period = $this;
        $th =  HotelRoomTypePeriod::
                where(function($q1) use ($period) {
                    $q1->where('period_status','LIKE','active');
                    $q1->whereNull('deleted_at');
                    $q1->where(function($q2) {
                        $q2->whereNull('period_sale_start');
                        $q2->orWhere('period_sale_start','<=',date("Y-m-d"));
                    });
                    $q1->where(function($q2) {
                        $q2->whereNull('period_sale_end');
                        $q2->orWhere('period_sale_end','>=',date("Y-m-d"));
                    });
                    
                })
                ->where(function($q1) use($period) {
                    $q1->where('period_id','=', $period->period_id);
                    $q1->orWhere('discounted_from','=',$period->period_id);
                })->orderBy('period_saleprice','asc')->first();
          
        return $th;        
    }
     
    public function reCalculateDiscountedRows() {
        $discounted_rows = $this->getDiscountedRows();
        
        $inds = [
            'commission'=>$this->period_commission,
            'eb'=>$this->period_eb,
            'kb'=>$this->period_kickback,
            'discount1_cumulative_inh'=>( $this->period_extradiscount_cumulative=='1' )?$this->period_extradiscount:0,
            'discount1_inh'=>( $this->period_extradiscount_cumulative!='1' )?$this->period_extradiscount:0
        ]; 
         
        
        if($discounted_rows->count()>0) {
            foreach($discounted_rows as $discounted_row) { 
                if($discounted_row->discount_order == 1) {
                    //indirim ise mevcut eb bilgilerini al
                    $eb = $this->getEbDiscountForPeriod($discounted_row);
                    
                    $discount_key = $discounted_row->period_sale_start.'_'.$discounted_row->period_sale_end;
                    
                    if(!isset($inds[$discount_key.'_cumulative'])) {
                        $inds[$discount_key.'_cumulative'] = 0;
                    }
                    if(!isset($inds[$discount_key])) {
                        $inds[$discount_key] = 0;
                    }
               
                    if($eb) {
                        $discounted_row->period_eb = $eb->period_eb;
                        $discounted_row->period_eb_cumulative = $eb->period_eb_cumulative;
                        $discounted_row->period_discounts_cumulative_inh = $inds[$discount_key.'_cumulative'];
                        $discounted_row->period_discounts_inh = $inds[$discount_key];
                    } else {
                        $discounted_row->period_eb = 0;
                        $discounted_row->period_eb_cumulative = 0;
                    }
                    if($discounted_row->period_extradiscount_cumulative=='1') {
                        $inds[$discount_key.'_cumulative'] += $discounted_row->period_extradiscount;
                    } else {
                        $inds[$discount_key] = (100 - ( (100-$inds[$discount_key]) * ( (100 - $discounted_row->period_extradiscount)/100 ) ) );
                    }
                      
                }
                
                
                $discounted_row->calculatePriceRow(); 
                $discounted_row->save();
            }
        }
    }
    
    
    public function getEbDiscountForPeriod($period) {
       
        $eb_row = HotelRoomTypePeriod::where(function($q1) use ($period) {
            $q1->where(function($q2) use ($period) {
                $q2->where('discounted_from','=',$period->discounted_from)
                        ->orWhere('period_id','=',$period->discounted_from);
            })->where('period_eb','>','0')->where('discount_order','=','0')->where('roomtype_id', '=', $period->roomtype_id);
            if(is_null($period->period_sale_start)) {
                $q1->whereNull('period_sale_start');
            } else {
                $q1->where(function($q4) use($period) {
                    $q4->where('period_sale_start', '<=', $period->period_sale_start)
                            ->orWhereNull('period_sale_start');
                }); 
            }
            if(is_null($period->period_sale_end)) {
                $q1->whereNull('period_sale_end');
            } else {
                $q1->where(function($q3) use($period) {
                    $q3->where('period_sale_end', '>=', $period->period_sale_end)
                            ->orWhereNull('period_sale_end');
                });
            } 
        })->first(); 
        return $eb_row;
    }
    
    public function calculatePriceRow() {
        $door_price = $this->period_doorprice;
        $com = $this->period_commission;
        $com_cum = $this->period_commission_cumulative;
        $kb = $this->period_kickback;
        $kb_cum = $this->period_kickback_cumulative;
        $eb = $this->period_eb;
        $eb_cum = $this->period_eb_cumulative;
        
        $discount_cum_inh = $this->period_discounts_cumulative_inh;
        $discount_inh = $this->period_discounts_inh;
        
        $extra_discount = $this->period_extradiscount;
        $extra_discount_iscum = $this->period_extradiscount_cumulative;
                 
        $profitrate = $this->period_profitrate;
        $sitediscount = $this->period_discountrate;
        $tolerance = $this->period_tolerance;
        
        $total_cum = 0;
        $total = 0;
        
        if($com_cum==1) {
            $total_cum += $com;
        } else {
            $total = (100 - ( (100-$total) * ( (100 - $com)/100 ) ) );
        }
        if($kb_cum==1) {
            $total_cum += $kb;
        } else {
            $total = (100 - ( (100-$total) * ( (100 - $kb)/100 ) ) );
        }
        if($eb_cum==1) {
            $total_cum += $eb;
        } else {
            $total = (100 - ( (100-$total) * ( (100 - $eb)/100 ) ) );
        }
        
        $total = (100 - ( (100-$total) * ( (100 - $discount_inh)/100 ) ) ); 
        $total_cum += $discount_cum_inh;
        
         
        if($extra_discount_iscum==1) {
            $total_cum += $extra_discount;
        } else {
            $total = (100 - ( (100-$total) * ( (100 - $extra_discount)/100 ) ) );
        }
        
       
        
        $cost_price = $door_price * ((100-$total_cum)/100);
        $cost_price = $cost_price * ((100-$total)/100);
        
        if($this->period_calc_from_displayedprice=='1') {
            //tersten
            $displayed_price = $door_price; 
            $sale_price = $displayed_price * ((100 - $sitediscount)/100); 
      
            $profitrate =( ($sale_price - $cost_price) / $cost_price) * 100;
            
            $profitrate = number_format($profitrate,2,'.','');
            
        } else {
            $sale_price = $cost_price * (  ($profitrate + 100)/100 );    

            $sale_price = $sale_price + $tolerance;

            $displayed_price = ($sale_price / (100-$sitediscount))*100;
        }
        
        $this->period_costprice = number_format($cost_price,2,'.','');
        $this->period_saleprice = number_format($sale_price,2,'.','');
        $this->period_displayedprice = number_format($displayed_price,2,'.','');
        $this->period_discountrate = $sitediscount;
        $this->period_profitrate = $profitrate; 
        
        $ind = $this->period_system_discountrate + $this->period_discountrate;
        $this->period_system_saleprice = $this->period_displayedprice * ((100-$ind) /100);
        $this->period_system_saleprice = number_format($this->period_system_saleprice,2,'.','');
    }
    
    public function addDiscount($discount) {
        
        $new_period = $this->replicate();
        
        $new_period->discounted_from=$this->period_id;
        $new_period->discount_data = json_encode($discount->toArray()); 
        $new_period->period_discountrate = $discount->discount_displayedrate;
        
        $new_period->period_sale_start = (!is_null($discount->discount_sale_start))?date("d.m.Y", strtotime($discount->discount_sale_start)):null;
        $new_period->period_sale_end = (!is_null($discount->discount_sale_end))?date("d.m.Y", strtotime($discount->discount_sale_end)):null;
        $new_period->discount_name = $discount->discount_name;
        
        if($discount->discount_type=='eb') {
            $new_period->period_eb = $discount->discount_rate;
            $new_period->period_eb_cumulative = $discount->discount_cumulative;
            $new_period->discount_order = 0;
        } else if($discount->discount_type=='discount') {
            $new_period->period_extradiscount = $discount->discount_rate;
            $new_period->period_extradiscount_cumulative = $discount->discount_cumulative;
            $new_period->discount_order = 1;
        }
        
        $new_period->save();
        $this->reCalculateDiscountedRows();
    }
    
    public function syncDiscountedRows($price_old) {
        
        if($this->discountedPeriods->count()==0) {
            return false;
        }
        foreach($this->discountedPeriods as $discount) {
            if($discount->period_doorprice==$price_old->period_doorprice) {
                $discount->period_doorprice = $this->period_doorprice;
            }
            if($discount->period_commission==$price_old->period_commission) {
                $discount->period_commission = $this->period_commission;
            }
            if($discount->period_commission_cumulative==$price_old->period_commission_cumulative) {
                $discount->period_commission_cumulative = $this->period_commission_cumulative;
            }
            if($discount->period_kickback==$price_old->period_kickback) {
                $discount->period_kickback = $this->period_kickback;
            }
            if($discount->period_kickback_cumulative==$price_old->period_kickback_cumulative) {
                $discount->period_kickback_cumulative = $this->period_kickback_cumulative;
            }
            if($discount->period_calc_from_displayedprice == $price_old->period_calc_from_displayedprice) {
                $discount->period_calc_from_displayedprice = $this->period_kickback_cumulative;
            }
            if($discount->period_calc_from_displayedprice != '1' && $discount->period_profitrate == $price_old->period_profitrate){
                $discount->period_profitrate = $this->period_profitrate;
            }
            if($discount->period_discountrate==$price_old->period_discountrate) {
                $discount->period_discountrate = $this->period_discountrate;
            }
            if($discount->period_extradiscount==$price_old->period_extradiscount) {
                $discount->period_extradiscount = $this->period_extradiscount;
            }
            if($discount->period_extradiscount_cumulative==$price_old->period_extradiscount_cumulative) {
                $discount->period_extradiscount_cumulative = $this->period_extradiscount_cumulative;
            } 
            
            if($discount->period_kickback==$price_old->period_kickback) {
                $discount->period_kickback = $this->period_kickback;
            }
            
            if($discount->period_mindays==$price_old->period_mindays) {
                $discount->period_mindays = $this->period_mindays;
            }
            if($discount->period_day1==$price_old->period_day1) {
                $discount->period_day1 = $this->period_day1;
            }
            if($discount->period_day2==$price_old->period_day2) {
                $discount->period_day2 = $this->period_day2;
            }
            if($discount->period_day3==$price_old->period_day3) {
                $discount->period_day3 = $this->period_day3;
            }
            if($discount->period_day4==$price_old->period_day4) {
                $discount->period_day4 = $this->period_day4;
            }
            if($discount->period_day5==$price_old->period_day5) {
                $discount->period_day5 = $this->period_day5;
            }
            if($discount->period_day6==$price_old->period_day6) {
                $discount->period_day6 = $this->period_day6;
            }
            if($discount->period_day7==$price_old->period_day7) {
                $discount->period_day7 = $this->period_day7;
            }
            if($discount->concept_id==$price_old->concept_id) {
                $discount->concept_id = $this->concept_id;
            }
            
            
            
            $discount->save();
        } 
        $this->reCalculateDiscountedRows();
    }
}
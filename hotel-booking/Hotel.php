<?php

namespace App\Models\Hotel;

use \Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use \Illuminate\Database\Eloquent\SoftDeletes;
use DB;
use App\Models\Location\Country;
use App\Models\Location\District;
use App\Models\Location\Town;
use App\Models\Location\Province;

class Hotel extends Model
{   
    use SoftDeletes;
    
    protected $table = 'hotels';
    protected $primaryKey = 'hotel_id';
    
    protected $casts = [
        'hotel_tags' => 'json'
    ];
    
    protected $dates = ['deleted_at' ];
    
    protected $fillable = array('hotel_id','hotel_code','hotel_name', 'hotel_stars', 'hotel_popularity', 'hotel_rating', 'hotel_points', 'hotel_phone', 'hotel_phone2', 'hotel_fax', 'hotel_email', 'country_id', 'province_id', 'town_id', 'district_id', 'hotel_address','hotel_postcode', 'hotel_latitude', 'hotel_longitude', 'hotel_commission', 'hotel_commission_cumulative', 'hotel_eb', 'hotel_eb_cumulative', 'hotel_kickback', 'hotel_kickback_required', 'hotel_kickback_cumulative', 'hotel_calc_from_strikethrough', 'tags', 'hotel_status', 'hotel_oldlink', 'link','hotel_image','hotel_content','hotel_freechild_begin','hotel_freechild_end','hotel_tags','hotel_extra_content','hotel_alternative_names','hotel_risk');
    
 
    
    public function district()
    {
        return $this->belongsTo('App\Models\Location\District', 'district_id', 'district_id');
    }
    public function province()
    {
        return $this->belongsTo('App\Models\Location\Province', 'province_id', 'province_id');
    }
    public function town()
    {
        return $this->belongsTo('App\Models\Location\Town', 'town_id', 'town_id');
    }
    
    public function property_groups()
    {
        return $this->belongsToMany('App\Models\Hotel\PropertyGroup', 'hotels_property_groups', 'hotel_id', 'group_id')->orderBy('group_order', 'asc');
    }  
    public function payment_plans()
    {
        return $this->hasMany('App\Models\PaymentPlan', 'hotel_id', 'hotel_id')->orderBy('payment_date', 'asc');
    }  
    public function bonuses()
    {
        return $this->hasMany('App\Models\Hotel\HotelBonus', 'hotel_id', 'hotel_id')->orderBy('created_at', 'asc');
    }  
    public function properties()
    {
        $a = $this->belongsToMany('App\Models\Hotel\Property', 'hotels_properties', 'hotel_id', 'property_id')->withPivot(['is_paid', 'detail', 'value', 'begin_of_use', 'end_of_use']); 
        
        return $a;
    }  
    
    public function categories()
    { 
        return $this->belongsToMany('App\Models\Hotel\Category', 'hotels_categories', 'hotel_id', 'category_id')->withPivot(['sort']);
    }  
    
    public function comments()
    { 
        return $this->hasMany('App\Models\Hotel\HotelComment', 'hotel_id', 'hotel_id')->orderBy('created_at', 'desc');
    }
    
    public function approvedComments()
    { 
        return $this->comments()->where('comment_status','=','1')->orderBy('created_at', 'desc');
    }
    
    public function propertiesByGroup($group_id)
    {
        return $this->properties()->having('properties.group_id','=',$group_id)->get(); 
    }  
    
    public function galleries()
    {
        return $this->hasMany('App\Models\Hotel\HotelGallery', 'hotel_id', 'hotel_id')->orderBy('gallery_order', 'asc');
    } 
    
    public function images()
    {
        return $this->hasManyThrough('App\Models\Hotel\HotelGalleryImage', 'App\Models\Hotel\HotelGallery', 'hotel_id', 'gallery_id')->with('gallery')->orderBy(\DB::Raw("(ISNULL(hotels_galleries.parent_id))"),'desc')->orderBy('hotels_galleries.gallery_order', 'asc')->orderBy('hotels_images.image_order', 'asc');
    } 
    
    public function discounts()
    {
        return $this->hasMany('App\Models\Hotel\HotelDiscount', 'hotel_id', 'hotel_id')
                ->orderBy('discount_start', 'asc')
                ->orderBy('discount_end', 'asc')
                ->orderBy('discount_sale_start', 'asc')
                ->orderBy('discount_sale_end', 'asc');
    } 
    public function active_roomtypes()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomType', 'hotel_id', 'hotel_id')->where('roomtype_showinsite','=','1')->orderBy('roomtype_order', 'asc');
    } 
    public function roomtypes()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomType', 'hotel_id', 'hotel_id')->orderBy('roomtype_order', 'asc');
    } 
    
    public function periods()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypePeriod', 'hotel_id', 'hotel_id')->orderBy('period_sale_start', 'asc');
    } 
    public function futurePeriods()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypePeriod', 'hotel_id', 'hotel_id')
                ->where('period_sale_start', '<=', date("Y-m-d"))
                ->where('period_sale_end', '>=', date("Y-m-d"))
                ->where('period_end', '>=', date("Y-m-d"))
                ->orderBy('period_start', 'asc')
                ->orderBy('period_end', 'asc');
    } 
    
    public function scopeModelJoin($query) {
 
            $fields = ['hotels.*']; 
            
            $query = $query->leftJoin("districts", "districts.district_id","=","hotels.district_id"); 
            $query = $query->leftJoin("towns", "towns.town_id","=","districts.town_id");
            $query = $query->leftJoin("provinces", "provinces.province_id","=","towns.province_id");
            $query = $query->addSelect( "hotels.*" );
            $query = $query->addSelect( DB::Raw("provinces.province_name, provinces.link as province_link"));
            $query = $query->addSelect( DB::Raw("towns.town_name, towns.link as town_link"));
            $query = $query->addSelect( DB::Raw("districts.district_name, districts.link as district_link")); 
   
        return $query;
    }
    
    public function scopeListPrice($query,$filters=[]) { 
        $query = $query->leftJoin("hotels_roomtypes_periods as period","period.period_id",'=',DB::Raw(
                    "( SELECT hrp.period_id FROM hotels_roomtypes_periods hrp INNER JOIN hotels_roomtypes as hrr2 ON ISNULL(hrr2.pricelist_room_id) AND hrr2.roomtype_id=hrp.roomtype_id  WHERE hrr2.roomtype_showinsite=1 AND hrp.hotel_id = hotels.hotel_id AND hrp.period_end >= '".date("Y-m-d")."' AND ( hrp.period_sale_start <= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND ( hrp.period_sale_end >= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND hrp.period_status LIKE 'active' AND ISNULL(hrp.deleted_at) AND hrp.period_showonsite = '1' ORDER BY hrp.period_saleprice ASC LIMIT 1 )"
                    ))
                ->leftJoin("concepts", 'concepts.concept_id', '=', 'period.concept_id');
        $query = $query->addSelect(['period.period_displayedprice','period.period_saleprice','period.period_discountrate','concepts.concept_name']);
        
        $query = $query->addSelect(DB::Raw(
                "( SELECT MIN(pax_rate) FROM hotels_roomtypes_paxes pax WHERE pax.roomtype_id = period.roomtype_id AND ( pax.pax_sale_start <= '".date("Y-m-d")."' OR ISNULL(pax.pax_sale_start) ) AND ( pax.pax_sale_end >= '".date("Y-m-d")."' OR ISNULL(pax.pax_sale_end) ) AND pax_adults=2 AND pax_status LIKE 'active' AND ISNULL(pax.deleted_at) ) as paxoran"
                ));
        return $query;
    } 
    
    public function scopeApplyKeywords($hotels, $keywords) { 
        if(!$keywords || !is_array($keywords)) {
            return $hotels;
        }
        $hotels->where(function($q) use ($keywords) {
            $feData = [];
            $func = 'where';
            foreach($keywords as $kw) {
                if(substr($kw,0,1)=='C') {
                    $kw2 = (int)str_replace('C','',$kw);
                    $q->$func('hotels.country_id','=',$kw2);
                    $func = 'orWhere';
                    $feData[$kw] = Country::find($kw2);
                    if($feData[$kw]) 
                        $feData[$kw] = $feData[$kw]->country_name;
                    else
                        unset($feData[$kw]);
                } else if(substr($kw,0,1)=='P') {
                    $kw2 = (int)str_replace('P','',$kw);
                    $q->$func('hotels.province_id','=',$kw2);
                    $func = 'orWhere';
                    $feData[$kw] = Province::find($kw2);
                    if($feData[$kw]) 
                        $feData[$kw] = $feData[$kw]->province_name;
                    else
                        unset($feData[$kw]);
                } else if(substr($kw,0,1)=='T') {
                    $kw2 = (int)str_replace('T','',$kw);
                    $q->$func('hotels.town_id','=',$kw2); 
                    $func = 'orWhere';
                    $feData[$kw] = Town::find($kw2); 
                    if($feData[$kw]) 
                        $feData[$kw] = $feData[$kw]->province->province_name.'/'.$feData[$kw]->town_name;
                    else
                        unset($feData[$kw]);
                } else if(substr($kw,0,1)=='D') {
                    $kw2 = (int)str_replace('D','',$kw);
                    $q->$func('hotels.district_id','=',$kw2);
                    $func = 'orWhere';
                    $feData[$kw] = District::find($kw2);
                    if($feData[$kw]) 
                        $feData[$kw] = $feData[$kw]->town->province->province_name.'/'.$feData[$kw]->town->town_name.'/'.$feData[$kw]->district_name;
                    else
                        unset($feData[$kw]);
                } else if(substr($kw,0,1)=='H') {
                    $kw2 = (int)str_replace('H','',$kw);
                    $q->$func('hotels.hotel_id','=',$kw2);
                    $func = 'orWhere';
                    $feData[$kw] = Hotel::find($kw2);
                    if($feData[$kw]) 
                        $feData[$kw] = $feData[$kw]->hotel_name;
                    else
                        unset($feData[$kw]);
                }
            } 
            
            View()->share ( 'keywords', $feData );
        });
        
        
        return $hotels;
    }
    
    public function scopeApplyFilters($hotels, $filters) {
        if(!$filters || !is_array($filters)) {
            return $hotels;
        }
        
        if(isset($filters['properties']) && is_array($filters['properties'])) {
            $hotels->whereHas('properties', function($q) use ($filters) {
                foreach ($filters['properties'] as $prop) {
                    $q->where('hotels_properties.property_id', '=', $prop);
                }
            });
        }
        
        
        
        return $hotels;
    }
   
    
    public function scopeFilterLocation($hotels, $bolge) {
        if(!$bolge) {
            return $hotels;
        } 
        
        $tip = $bolge->tip;
        $field = $tip.'_id';
        
        $hotels->where('hotels.'.$field, '=', $bolge->$field);
        
        return $hotels;
    }
    
    public function scopeListCalculation($hotels, $calc, $filters = false) {
        if(!$calc || !is_array($calc)) {
            return $hotels;
        }
        $date = date("Y-m-d", strtotime($calc['date']));
        $day = (int)$calc['day'];
        $adults = (int)$calc['adults'];
        $children = (int)$calc['children'];
        $c1_yas = 0;
        $c2_yas = 0;
        $c3_yas = 0;
        if($children>0) {
            $c1_dt = date("Y-m-d", strtotime($calc['c1']));
            $c1_yas = calculate_child_age($c1_dt, $date);
        }
        if($children>1) {
            $c2_dt = date("Y-m-d", strtotime($calc['c2']));
            $c2_yas = calculate_child_age($c2_dt, $date);
        }
        if($children>2) {
            $c3_dt = date("Y-m-d", strtotime($calc['c3']));
            $c3_yas = calculate_child_age($c3_dt, $date);
        }
        $hotels->selectRaw(" @calculation_tmp := HotelListPrice(hotels.hotel_id, '".date("Y-m-d")."', '$date', '$day', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field, @calculated_price_tmp := SPLIT_STR (@calculation_tmp, '|', 1) AS calculated_price, SPLIT_STR (@calculation_tmp, '|', 2) AS calculated_displayed_price, SPLIT_STR (@calculation_tmp, '|', 3) AS calculated_discount, SPLIT_STR (@calculation_tmp, '|', 4) AS calculated_concept_id");
        if(isset($filters) && $filters && isset($filters['price_min']) && $filters['price_min']!="") {
            //$hotels->having( \DB::Raw("SPLIT_STR ( HotelListPrice(hotels.hotel_id, '".date("Y-m-d")."', '$date', '$day', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') , '|', 1) >= '".$filters['price_min']."'") );
        }
        if(isset($filters) && $filters && isset($filters['price_max']) && $filters['price_max']!="") {
            //$hotels->having( \DB::Raw("SPLIT_STR ( HotelListPrice(hotels.hotel_id, '".date("Y-m-d")."', '$date', '$day', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') , '|', 1) <= '".$filters['price_max']."'") );
        }
        return $hotels;
    }
    
    public function scopeApplySort($hotels, $order = 'popularity') {
        if($order=='popularity') {
            $hotels->orderBy('hotels.hotel_popularity','desc');
        } elseif($order=='discount') {
            $hotels->orderBy('period.period_discountrate','desc');
        } elseif($order=='minprice') {
            $hotels->orderBy(DB::Raw("ISNULL(period.period_saleprice)"),'asc');
            $hotels->orderBy('period.period_saleprice','asc');
        } elseif($order=='maxprice') {
            $hotels->orderBy('period.period_saleprice','desc');
        }
        return $hotels;
    } 
    
    public function getTabbedGroups() { 
        return$this->property_groups()->having('property_groups.display_type','=', 0)->orderBy('property_groups.group_order','asc');  
    }
    public function getSeperateTabbedGroups() {  
        return $this->property_groups()->having('property_groups.display_type','=', 1)->orderBy('property_groups.group_order','asc'); 
    }
}
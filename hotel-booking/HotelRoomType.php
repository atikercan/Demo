<?php
namespace App\Models\Hotel;
use App\Models\Hotel\HotelRoomTypePax;
use App\Models\Hotel\HotelRoomTypePeriod;
use Carbon\Carbon;
use \Illuminate\Database\Eloquent\SoftDeletes;
use \Illuminate\Database\Eloquent\Model;

class HotelRoomType extends Model
{   
    use SoftDeletes;
    protected $table = 'hotels_roomtypes';
    protected $primaryKey = 'roomtype_id';
    
    protected $dates = ['deleted_at' ];
    
    protected $fillable = array('roomtype_id','hotel_id', 'roomtype_name', 'roomtype_order','baseroom_id','old_roomtype_id','room_showchildage');
 
    
    public function hotel() {
        return $this->belongsTo('App\Models\Hotel\Hotel', 'hotel_id', 'hotel_id');
    }
    
    public function properties() { 
        return $this->belongsToMany('App\Models\Hotel\RoomTypeProperty', 'hotels_roomtypes_properties', 'roomtype_id', 'property_id')->withPivot(['is_paid', 'detail', 'value', 'begin_of_use', 'end_of_use'])->orderBy('roomtype_properties.property_order','asc'); 
    } 
    
    public function paxes()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypePax', 'roomtype_id', 'roomtype_id')->orderBy('pax_adults', 'asc');
    } 
    
    public function quotas()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypeQuota', 'roomtype_id', 'roomtype_id')->orderBy('quota_start', 'asc')->orderBy('quota_end', 'asc');
    } 
    
    public function periods()
    {
        return $this->hasMany('App\Models\Hotel\HotelRoomTypePeriod', 'roomtype_id', 'roomtype_id')->orderBy('period_sale_start', 'asc');
    } 
    
    public function availableMainPeriods() {
        if(isset($this->data_available_main_periods)) {
            return $this->data_available_main_periods;
        }
        
        $rt_id = $this->roomtype_id; 
        if($this->pricelist_room_id>0) {
            $rt_id = $this->pricelist_room_id;
        }
        
        $periods = HotelRoomTypePeriod::where('hotels_roomtypes_periods.roomtype_id','=',$rt_id)
                ->whereNull('hotels_roomtypes_periods.discounted_from')
                ->where("hotels_roomtypes_periods.period_status","LIKE",'active')
                ->where(function($sq1) { 
                    $sq1->where("hotels_roomtypes_periods.period_sale_start","<=", date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_sale_start");
                }) 
                ->where(function($sq1){ 
                    $sq1->where("hotels_roomtypes_periods.period_sale_end",">=", date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_sale_end");
                })
                ->where(function($sq1){ 
                    $sq1->where("hotels_roomtypes_periods.period_end",">=",  date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_end");
                })
                ->whereNull('hotels_roomtypes_periods.deleted_at')
                ->where('hotels_roomtypes_periods.period_showonsite','=',1)
                        ->orderBy("hotels_roomtypes_periods.period_start","asc")
                        ->orderBy("hotels_roomtypes_periods.period_end","asc")
                        ->get();
        $this->data_available_main_periods =  $periods;
        return $periods;       
    }
    public function availablePeriods()
    {
        $rt_id = $this->roomtype_id;
        if($this->pricelist_room_id>0) {
            $rt_id = $this->pricelist_room_id;
        }
        
        $periods = HotelRoomTypePeriod::where('hotels_roomtypes_periods.roomtype_id','=',$rt_id)
                ->where("hotels_roomtypes_periods.period_status","LIKE",'active')
                ->where(function($sq1) { 
                    $sq1->where("hotels_roomtypes_periods.period_sale_start","<=", date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_sale_start");
                }) 
                ->where(function($sq1){ 
                    $sq1->where("hotels_roomtypes_periods.period_sale_end",">=", date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_sale_end");
                })
                ->where(function($sq1){ 
                    $sq1->where("hotels_roomtypes_periods.period_end",">=",  date("Y-m-d"));
                    $sq1->orWhereNull("hotels_roomtypes_periods.period_end");
                })
                ->whereNull('hotels_roomtypes_periods.deleted_at')
                ->where('hotels_roomtypes_periods.period_showonsite','=',1)
                        ->groupBy('hotels_roomtypes_periods.period_start')
                        ->groupBy('hotels_roomtypes_periods.period_end')
                        ->groupBy('hotels_roomtypes_periods.concept_id')
                        ->select(['hotels_roomtypes_periods.period_start','hotels_roomtypes_periods.period_end','hotels_roomtypes_periods.concept_id'])
                        ->get();
        
            
    } 
    
    public function generatePaxForPriceList() {
        if($this->pax_data_for_price_list) {
            return $this->pax_data_for_price_list;
        }
        $build1 = HotelRoomTypePax::where('roomtype_id', '=', $this->roomtype_id) 
            ->where("pax_status","LIKE",'active')
            ->whereNull('deleted_at')
            ->where(function($sq1){ 
                $sq1->where("pax_sale_start","<=", date("Y-m-d"));
                $sq1->orWhereNull("pax_sale_start");
            })
            ->where(function($sq1){ 
                $sq1->where("pax_sale_end",">=", date("Y-m-d"));
                $sq1->orWhereNull("pax_sale_end");
            });
         
        $build2 = clone $build1;
        $build3 = clone $build1;
        
        $kisi1 = $build1->where('pax_adults','=',1)->where('pax_children','=','0')->orderBy('pax_rate','asc')->first();
        $kisi2 = $build2->where('pax_adults','=',2)->where('pax_children','=','0')->orderBy('pax_rate','asc')->first();
        $kisi3 = $build3->where('pax_adults','=',3)->where('pax_children','=','0')->orderBy('pax_rate','asc')->first();
        
        $this->pax_data_for_price_list = [
            ($kisi1)?$kisi1->pax_rate:0, ($kisi2)?($kisi2->pax_rate/2):0, ($kisi3)?($kisi3->pax_rate/3):0
        ];
        return $this->pax_data_for_price_list;
    }
    
    public function getFreeChild($child) {
        $rt_id = $this->roomtype_id;
        
        if($this->room_showchildage==0) {
            return false;
        }
        if($this->pax_for_child) {
            $paxes = $this->pax_for_child;
        } else {
            $paxes = $this->paxes()->where("pax_adults",'=','2')->orderBy('pax_rate','asc')->get();
            $this->pax_for_child = $paxes;
        }
        $frees=[];
        $paids=[];
        if($paxes) {
            foreach($paxes as $pax) {
                if($pax->pax_children==$child) {
                    $key_free = "pax_child".$child."_free";
                    $key_bas = "pax_child".$child."_start_age";
                    $key_son = "pax_child".$child."_end_age";
                    
                    $bas = ceil($pax->$key_bas);
                    if($bas<0) $bas = 0;
                    $son = ceil($pax->$key_son)-1;
                    if($pax->$key_free==0) {
                        $frees[] = $bas." - ".$son." yaş ücretsiz";
                        #$frees[]=
                    } else if($pax->$key_free==1) {
                        $pax_rate = $pax->pax_rate;
                        $norm = floor($pax_rate);
                        $rate = number_format(($pax_rate-$norm)*100, 0, '','');
                        if($rate==0) {
                            $paids[] = $bas." - ".$son." yaş ücretli";
                        } else {
                            $paids[] = $bas." - ".$son." yaş %".$rate." ind.";
                        } 
                    }
                }
            }
        }
        $alls = array_merge($frees, $paids);
        if(count($alls)==0) {
            return false;
        }
        return implode(", ", $alls); 
    }
    
    public function calculatePax($data) { 
        
        $pax = HotelRoomTypePax::where('roomtype_id', '=', $this->roomtype_id) 
                ->where("pax_status","LIKE",'active')
                ->where(function($sq1) use ($data){ 
                    $sq1->where("pax_sale_start","<=", Carbon::createFromFormat('d.m.Y', $data['sale_date'])->format('Y-m-d'));
                    $sq1->orWhereNull("pax_sale_start");
                })
                
                ->where(function($sq1) use ($data){ 
                    $sq1->where("pax_sale_end",">=", Carbon::createFromFormat('d.m.Y', $data['sale_date'])->format('Y-m-d'));
                    $sq1->orWhereNull("pax_sale_end");
                })
                ->where(function($sq1) use ($data){ 
                    $sq1->where("pax_start","<=", Carbon::createFromFormat('d.m.Y', $data['start'])->format('Y-m-d'));
                    $sq1->orWhereNull("pax_start");
                })
                ->where(function($sq1) use ($data){ 
                    $sq1->where("pax_end",">=", Carbon::createFromFormat('d.m.Y', $data['end'])->format('Y-m-d'));
                    $sq1->orWhereNull("pax_end");
                })->where(function($query) use ($data) {

                    $children_count = (int)$data['children'];
                    $adults_count = (int)$data['adults'];

                    $query->where(function($q1) use ($data, $adults_count, $children_count) { 
                        $q1->where("pax_adults","=",$adults_count);
                        $q1->where("pax_children","=",$children_count);
                        if($children_count>0) {
                            for($gg=1;$gg<=$children_count;$gg++) {
                                $q1->where("pax_child".$gg."_start_age","<=",$data['age_child'.$gg]);
                                $q1->where("pax_child".$gg."_end_age",">=",$data['age_child'.$gg]);
                            }
                        }
                    });
                    $start=0;
                    while($children_count>0) {
                        $children_count--;
                        $adults_count++;
                        $start++;
                        $query->orWhere(function($q1) use ($data, $adults_count, $children_count, $start) { 
                            $q1->where("pax_adults","=",$adults_count);
                            $q1->where("pax_children","=",$children_count);
                            if($children_count>0) {
                                for($gg=1;$gg<=$children_count;$gg++) {
                                    $q1->where("pax_child".$gg."_start_age","<=",$data['age_child'.($gg + $start)]);
                                    $q1->where("pax_child".$gg."_end_age",">=",$data['age_child'.($gg + $start)]);
                                }
                            }
                        });
                    }

                })->orderBy('pax_rate', 'asc')->first();  
        
                return $pax; 
    }
    
    public function calculatePriceAndCost($data) {
        $data['sale_date'] = Carbon::createFromFormat('d.m.Y', $data['sale_date']);
        $start = Carbon::createFromFormat('d.m.Y', $data['start']);
        $end = Carbon::createFromFormat('d.m.Y', $data['end']);
        $end = $end->subDay();
        
        $day_count = $start->diff($end)->days;
  
        $rets = [ ];
        
        $missings = [];
        
        for($date = $start; $date->lte($end); $date->addDay()) {
            $current = $date->format('Y-m-d');
            $dayofweek = ($date->dayOfWeek==0)?7:$date->dayOfWeek;
         
            $prices = HotelRoomTypePeriod::leftJoin('concepts', 'concepts.concept_id', '=', 'hotels_roomtypes_periods.concept_id')->addSelect(['hotels_roomtypes_periods.*','concepts.concept_name','concepts.concept_code'])
                    ->where('roomtype_id', '=', $this->roomtype_id)  
                ->where('period_day'.$dayofweek, '=', '1')
                ->where('period_mindays', '<=', $day_count)
                ->where("period_status","LIKE",'active')
                ->where(function($sq1) use ($data, $current){ 
                    $sq1->where("period_sale_start","<=", $data['sale_date']);
                    $sq1->orWhereNull("period_sale_start");
                }) 
                ->where(function($sq1) use ($data, $current){ 
                    $sq1->where("period_sale_end",">=", $data['sale_date']);
                    $sq1->orWhereNull("period_sale_end");
                })
                ->where(function($sq1) use ($data, $current){ 
                    $sq1->where("period_start","<=", $current);
                    $sq1->orWhereNull("period_start");
                })
                ->where(function($sq1) use ($data, $current){ 
                    $sq1->where("period_end",">=", $current);
                    $sq1->orWhereNull("period_end");
                })->groupBy('hotels_roomtypes_periods.concept_id')->orderBy('period_saleprice', 'asc')->get();
                
            if($prices->count()==0) {
                return false;
            }   
            $tmp_concepts = [];
            foreach($prices as $price) {
                $concept = $price->concept_id;
          
                if(in_array($concept, $missings)) {
                    continue;
                }
                $tmp_concepts[]=$concept;
                if(!isset($rets[$concept])) {
                    $rets[$concept]=[ 
                        'price'=>0,
                        'cost'=>0,
                        'concept_id'=>$price->concept_id,
                        'concept_name'=>$price->concept_name,
                        'concept_code'=>$price->concept_code
                    ];
                }
                $rets[$concept]['price'] += $price->period_saleprice;
                $rets[$concept]['cost'] += $price->period_costprice;
   
            } 
            
            foreach($rets as $cid=>$cdata) {
                if(!in_array($cid,$tmp_concepts)) {
                    $missings[] = $cid;
                    unset($rets[$cid]);
                }
            }
        } 
        
        return $rets;
    }
    
    public function checkQuota($checkin, $checkout) {
        $start = Carbon::createFromFormat('d.m.Y', $checkin);
        $end = Carbon::createFromFormat('d.m.Y', $checkout);
       
        $quota =[];
        for($date = $start; $date->lt($end); $date->addDay()) {
            #stop
            $total = 0;
            $stop = $this->quotas()->where('quota_start','<=',$date->format("Y-m-d"))->where('quota_end','>=',$date->format("Y-m-d"))->where('quota_type','LIKE','stop')->count();
            
            if($stop==0) {
                $total = $this->quotas()->where('quota_start','<=',$date->format("Y-m-d"))->where('quota_end','>=',$date->format("Y-m-d"))->where('quota_type','LIKE','quota')->sum('quota_amount'); 
                $used = \App\Models\ReservationRoom::where('roomtype_id','=',$this->roomtype_id)
                        ->join('reservations',"reservations.reservation_id",'=','reservations_rooms.reservation_id')
                        ->where(function($q) {
                            $q->where("reservations.reservation_status",'LIKE','onaylandi')
                                    ->orWhere("reservations.reservation_status",'LIKE','onaybekliyor');
                        })
                        ->where('reservations.reservation_start','<=',$date->format("Y-m-d"))
                        ->where('reservations.reservation_end','>',$date->format("Y-m-d"))->count();
                $total = $total-$used;
            } 
            if($total<0) {
                $total = 0;
            }
            $quota[] = $total;
        }
        return min($quota);
    }
}
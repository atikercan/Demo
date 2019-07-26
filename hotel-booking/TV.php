<?php

namespace App\Models;
use DB;
use App\Models\Location\Country;
use App\Models\Location\Province;
use App\Models\Location\Town;
use App\Models\Location\District;


class TV 
{    
    
    public static function searchHotelsWithListPrice($params = [], $page = 1, $items_per_page = 9) {
        extract($params);
        
        $selects = [
           'h.hotel_id', 'h.hotel_code', 'h.currency_code', 'h.hotel_name', 'h.hotel_popularity', 'h.hotel_image', 'h.country_id', 'h.province_id', 'h.town_id', 'h.district_id', 'h.hotel_latitude', 'h.hotel_longitude', 'h.hotel_tags', 'h.hotel_status', 'h.link',"provinces.province_name", "provinces.link as province_link", "towns.town_name", "towns.link as town_link", "districts.district_name", "districts.link as district_link","h.hotel_content","h.hotel_stars","h.tmp_min_price",DB::raw("h.tmp_min_price as tmp_min_price_discounted"),"h.tmp_min_price_expiry","h.tmp_min_price_freechild"
        ];
        $raw_selects = [];
        
        $hotels = DB::table('hotels as h')
                ->where('h.hotel_status','=','1')
                ->leftJoin("districts", "districts.district_id","=","h.district_id")
                ->leftJoin("towns", "towns.town_id","=","districts.town_id")
                ->leftJoin("provinces", "provinces.province_id","=","towns.province_id");
        
        self::filterCategory($hotels, (( isset($category_id) )?$category_id:null) );
        self::filterKeywords($hotels, (( isset($keywords) )?$keywords:null) );
        self::filterLocation($hotels, (( isset($location) )?$location:null) ); 
		if(isset($location2)) {
			self::filterLocation2($hotels, (( isset($location2) )?$location2:null) ); 
		}
        self::filterCriterias($hotels, (( isset($filters) )?$filters:null) );
        
        $hotels->leftJoin("hotels_roomtypes_periods as period","period.period_id",'=',DB::Raw(
                    "( SELECT hrp.period_id FROM hotels_roomtypes_periods hrp INNER JOIN hotels_roomtypes hrson ON hrson.roomtype_id = hrp.roomtype_id AND hrson.roomtype_showinsite = 1 WHERE hrp.hotel_id = h.hotel_id AND hrp.period_end >= '".date("Y-m-d")."' AND ( hrp.period_sale_start <= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND ( hrp.period_sale_end >= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND hrp.period_status LIKE 'active' AND ISNULL(hrp.deleted_at) AND hrp.period_showonsite = '1' ORDER BY hrp.period_saleprice ASC LIMIT 1 )"
                    ));
        $hotels->leftJoin("concepts", 'concepts.concept_id', '=', 'period.concept_id');
        
        $selects = array_merge($selects, ['period.period_displayedprice','period.period_saleprice','period.period_discountrate','concepts.concept_name',"( SELECT MIN(pax_rate) FROM hotels_roomtypes_paxes pax WHERE pax.roomtype_id = period.roomtype_id AND ( pax.pax_sale_start <= '".date("Y-m-d")."' OR ISNULL(pax.pax_sale_start) ) AND ( pax.pax_sale_end >= '".date("Y-m-d")."' OR ISNULL(pax.pax_sale_end) ) AND pax_adults=2 AND pax_status LIKE 'active' AND ISNULL(pax.deleted_at) ) as paxoran"]);
        
		$hotels->whereNull("h.deleted_at");
		
        #sort
        if(isset($order) && !empty($order)) {
          
            if($order=='popularity') {
                if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                    foreach($keywords as $keyword) {
                        if(substr($keyword,0,1)=='H') {
                            $kw2 = (int)str_replace('H','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.hotel_id='$kw2')"),'desc'); 
                        }
                        if(substr($keyword,0,1)=='K') {
                            $kw2 = (int)str_replace('K','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.hotel_code='$kw2')"),'desc'); 
                        }
                    }
                }
                $hotels->orderBy('h.hotel_popularity','desc');
            } elseif($order=='discount') { 
                $hotels->orderBy(DB::Raw("ISNULL(period.period_discountrate)"),'asc');
                $hotels->orderBy('period.period_discountrate','desc');
            } elseif($order=='minprice') {
                $hotels->orderBy(DB::Raw("ISNULL(period.period_saleprice)"),'asc');
                $hotels->orderBy(DB::Raw("( period.period_saleprice * paxoran )"),'asc');
            } elseif($order=='maxprice') {
                $hotels->orderBy(DB::Raw("ISNULL(period.period_saleprice)"),'asc');
                $hotels->orderBy(DB::Raw("( period.period_saleprice * paxoran )"),'desc');
            }
        } else {
            if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                foreach($keywords as $keyword) {
                    if(substr($keyword,0,1)=='H') {
                        $kw2 = (int)str_replace('H','',$keyword); 
                        $hotels->orderBy(DB::Raw("(h.hotel_id='$kw2')"),'desc'); 
                    }
                }
            }
            $hotels->orderBy('h.hotel_popularity','desc');
        }
        
         
           // $hotels->where("asd","=",1);
        $selects[] = DB::Raw("( SELECT GROUP_CONCAT(p.property_name SEPARATOR ';') FROM hotels_properties hp INNER JOIN properties p ON p.property_id = hp.property_id AND p.group_id = '14' WHERE hp.hotel_id = h.hotel_id ORDER BY p.property_order LIMIT 4 ) AS featured_properties");
     
        
        #add selections
        $hotels->selectRaw(DB::Raw("SQL_CALC_FOUND_ROWS ".implode(", ",$selects)));
        
        #results
        $results = $hotels->skip( ($page-1)*$items_per_page )->take($items_per_page)->get();
        
		$tmp =  (DB::select( DB::raw("SELECT FOUND_ROWS() AS total") )) ;
        $count = end($tmp);
        
        $total_rows = $count->total;
        
        return [
            'pagination'=>[
                'page'=>$page,
                'items_per_page'=>$items_per_page,
                'total_rows'=>$total_rows,
                'first_row'=>($page-1)*$items_per_page,
                'last_row'=>($page)*$items_per_page,
                'total_pages'=>  ceil(($total_rows/$items_per_page))
            ],
            'items'=>$results
        ];
    }
    
    /*
     * bölge, özellik, keyword, 
     * 
     * $calc array
     * $filters array       +
     * $category_id int     +
     * $keywords array      +
     * $location object     +
     * $order string        + 
     * 
     */
    public static function searchHotelsWithCalculatedPrice($params = [], $page = 1, $items_per_page = 9) {
        extract($params);
        
    
        $selects = [
            'h.hotel_id', 'h.currency_code', 'h.hotel_code', 'h.hotel_name', 'h.hotel_popularity', 'h.hotel_image', 'h.country_id', 'h.province_id', 'h.town_id', 'h.district_id', 'h.hotel_latitude', 'h.hotel_longitude', 'h.hotel_tags', 'h.hotel_status', 'h.link',"provinces.province_name", "provinces.link as province_link", "towns.town_name", "towns.link as town_link", "districts.district_name", "districts.link as district_link","h.hotel_content","h.hotel_stars"
        ];
        $raw_selects = [];
        
        $hotels = DB::table('hotels as h')
                ->where('h.hotel_status','=','1')
                ->leftJoin("districts", "districts.district_id","=","h.district_id")
                ->leftJoin("towns", "towns.town_id","=","districts.town_id")
                ->leftJoin("provinces", "provinces.province_id","=","towns.province_id");
        
        self::filterCategory($hotels, (( isset($category_id) )?$category_id:null) );
        self::filterKeywords($hotels, (( isset($keywords) )?$keywords:null) );
        self::filterLocation($hotels, (( isset($location) )?$location:null) ); 
        if(isset($location2)) {
			self::filterLocation2($hotels, (( isset($location2) )?$location2:null) ); 
		}
        self::filterCriterias($hotels, (( isset($filters) )?$filters:null) );
        
	 
        #calc
        if(isset($calc) && is_array($calc)) {
            $date = date("Y-m-d", strtotime($calc['checkin']));
            $date2 = date("Y-m-d", strtotime($calc['checkout']));
			$ttt = strtotime($calc['checkout']) - strtotime($calc['checkin']);
			$day = ceil($ttt/86400);
		  
            $adults = (int)$calc['adults'];
            $children = (int)$calc['children'];
            $c1_yas = 0;
            $c2_yas = 0;
            $c3_yas = 0;
            if($children>0) {
                $c1_dt = date("Y-m-d", strtotime($calc['c1']));
                //$c1_yas = calculate_child_age($c1_dt, $date);
				$c1_yas = $calc['c1'];
            }
            if($children>1) {
                $c2_dt = date("Y-m-d", strtotime($calc['c2']));
                //$c2_yas = calculate_child_age($c2_dt, $date);
				$c2_yas = $calc['c2'];
            }
            if($children>2) {
                $c3_dt = date("Y-m-d", strtotime($calc['c3']));
                //$c3_yas = calculate_child_age($c3_dt, $date);
				$c3_yas = $calc['c3'];
            }
			
			if(!isset($is_logged_in)) {
				$is_logged_in = 0;
			}
			
            $selects[] = "@calculation_tmp := HotelListPrice(h.hotel_id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '$date', '$day', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field";
            $selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 1) as DECIMAL(10,2) ) AS tmp_min_price";
            $selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 2) as DECIMAL(10,2) ) AS tmp_min_price_discounted"; 
            $selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 3) as DECIMAL(10,2) ) AS calculated_concept_id"; 
            
            //$selects[] = DB::Raw("( SELECT GROUP_CONCAT(p.property_name SEPARATOR ';') FROM hotels_properties hp INNER JOIN properties p ON p.property_id = hp.property_id AND p.group_id = '14' WHERE hp.hotel_id = h.hotel_id ORDER BY p.property_order LIMIT 4 ) AS featured_properties");
            
            $hotels->groupBy("h.hotel_id");
            
            if(isset($filters) && isset($filters['price_min']) && !empty($filters['price_min'])) {
                $hotels->having(DB::Raw("tmp_min_price_discounted"), ">=", $filters['price_min']);
            }
            if(isset($filters) && isset($filters['price_max']) && !empty($filters['price_max']) && $filters['price_max']!="3000+") {
                $hotels->having(DB::Raw("tmp_min_price_discounted"), "<=", $filters['price_max']);
            }
            if(isset($filters) && isset($filters['concept']) && is_array($filters['concept'])) {
                $tmp=[];
                foreach($filters['concept'] as $concept) {
                    $tmp[] = "calculated_concept_id = '".$concept."'";
                }
                $hotels->havingRaw(DB::Raw("(".implode(" OR ",$tmp) .")"));
            }
         
        }
        $hotels->whereNull("h.deleted_at");
        #sort 
        if(isset($order) && !empty($order)) {
            if($order=='popularity') {
                if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                    foreach($keywords as $keyword) {
                        if(substr($keyword,0,1)=='H') {
                            $kw2 = (int)str_replace('H','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.hotel_id='$kw2')"),'desc'); 
                        }
                        if(substr($keyword,0,1)=='K') {
                            $kw2 = (int)str_replace('K','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.hotel_code='$kw2')"),'desc'); 
                        }
                    }
                }
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('h.hotel_popularity','desc');
            } elseif($order=='discount') { 
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('calculated_discount','desc');
            } elseif($order=='minprice') {
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('tmp_min_price_discounted','asc');
            } elseif($order=='maxprice') {
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('tmp_min_price_discounted','desc');
            }
        } else {
            if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                foreach($keywords as $keyword) {
                    if(substr($keyword,0,1)=='H') {
                        $kw2 = (int)str_replace('H','',$keyword); 
                        $hotels->orderBy(DB::Raw("(h.hotel_id='$kw2')"),'desc'); 
                    }
                }
            }
            
            $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
            $hotels->orderBy('h.hotel_popularity','desc');
        }
        
        #add selections
        $hotels->selectRaw(DB::Raw("SQL_CALC_FOUND_ROWS ".implode(", ",$selects)));
        
        #results
        $results = $hotels->skip( ($page-1)*$items_per_page )->take($items_per_page)->get();
        
		$tmp = (DB::select( DB::raw("SELECT FOUND_ROWS() AS total") ));
        $count = end( $tmp );
        
        $queries = DB::getQueryLog();
 
        $total_rows = $count->total;
        
        return [
            'pagination'=>[
                'page'=>$page,
                'items_per_page'=>$items_per_page,
                'total_rows'=>$total_rows,
                'first_row'=>($page-1)*$items_per_page,
                'last_row'=>($page)*$items_per_page,
                'total_pages'=>  ceil(($total_rows/$items_per_page))
            ],
            'items'=>$results
        ];
    }
	
    public static function filterTourCategory(&$hotels, $category_id) {
        #category
        if(isset($category_id) && (int)$category_id>1) {
            $hotels->join('tours_categories as hc', function($q) use($category_id) {
                $q->on("hc.tour_id","=","h.tour_id");
                $q->on("hc.category_id","=", DB::Raw($category_id));
            });
        } 
    }
	
    public static function filterCategory(&$hotels, $category_id) {
        #category
        if(isset($category_id) && (int)$category_id>1) {
            $hotels->join('hotels_categories as hc', function($q) use($category_id) {
                $q->on("hc.hotel_id","=","h.hotel_id");
                $q->on("hc.category_id","=", DB::Raw($category_id));
            });
        } 
    }
    
    public static function filterKeywords(&$hotels, $keywords) {
        #keywords
        if(isset($keywords) && is_array($keywords)) {
            $hotels->where(function($q) use ($keywords) {
                $func = 'where';
                $feData = [];
                foreach($keywords as $kw) {
                    if(substr($kw,0,1)=='C') {
                        $kw2 = (int)str_replace('C','',$kw);
                        $q->$func('h.country_id','=',$kw2);
                        $func = 'orWhere';
                        $feData[$kw] = Country::find($kw2);
                        if($feData[$kw]) 
                            $feData[$kw] = $feData[$kw]->country_name." Otelleri";
                        else
                            unset($feData[$kw]);
                    } else if(substr($kw,0,1)=='P') {
                        $kw2 = (int)str_replace('P','',$kw);
                        $q->$func('h.province_id','=',$kw2);
                        $func = 'orWhere';
                        $feData[$kw] = Province::find($kw2);
                        if($feData[$kw]) 
                            $feData[$kw] = $feData[$kw]->province_name." Otelleri";
                        else
                            unset($feData[$kw]);
                    } else if(substr($kw,0,1)=='T') {
                        $kw2 = (int)str_replace('T','',$kw);
                        $q->$func('h.town_id','=',$kw2); 
                        $func = 'orWhere';
                        $feData[$kw] = Town::find($kw2); 
                        if($feData[$kw]) 
                            //$feData[$kw] = $feData[$kw]->province->province_name.'/'.$feData[$kw]->town_name." Otelleri";
                            $feData[$kw] = $feData[$kw]->town_name." Otelleri";
                        else
                            unset($feData[$kw]);
                    } else if(substr($kw,0,1)=='D') {
                        $kw2 = (int)str_replace('D','',$kw);
                        $q->$func('h.district_id','=',$kw2);
                        $func = 'orWhere';
                        $feData[$kw] = District::find($kw2);
                        if($feData[$kw]) 
                            //$feData[$kw] = $feData[$kw]->town->province->province_name.'/'.$feData[$kw]->town->town_name.'/'.$feData[$kw]->district_name." Otelleri";
                            $feData[$kw] = $feData[$kw]->district_name." Otelleri";
                        else
                            unset($feData[$kw]);
                    } else if(substr($kw,0,1)=='H') {
                        $kw2 = (int)str_replace('H','',$kw);
                        $q->$func('h.hotel_id','=',$kw2);
                        $func = 'orWhere';
                        $feData[$kw] = Hotel\Hotel::find($kw2);
                        if($feData[$kw]) 
                            $feData[$kw] = $feData[$kw]->hotel_name;
                        else
                            unset($feData[$kw]);
                    } else if(substr($kw,0,1)=='Z') {
                        $kw2 = (int)str_replace('Z','',$kw);
                        
                        $feData[$kw] = Hotel\GroupHotel::find($kw2);
                        if($feData[$kw]) {
                            $hotelsIn = $feData[$kw]->hotels()->lists('hotels.hotel_id')->all();
                            $func_ozel = $func."In"; 
                            $q->$func_ozel('h.hotel_id',$hotelsIn);
                            $func = 'orWhere';
                            
                            $feData[$kw] = $feData[$kw]->group_name;
                        } else {
                            unset($feData[$kw]);
                        }
                    } else if(substr($kw,0,1)=='K') {
                        $kw2 = (int)str_replace('K','',$kw);
                        $q->$func('h.hotel_code','=',$kw2);
                        $func = 'orWhere';
                        $feData[$kw] = Hotel\Hotel::where('hotel_code','=',$kw2)->first();
                        if($feData[$kw]) 
                            $feData[$kw] = $feData[$kw]->hotel_name." [Otel Kodu: ".$feData[$kw]->hotel_code."]";
                        else
                            unset($feData[$kw]);
                    }
                } 
                View()->share ( 'keywords', $feData );
            });
        }
    }
    
    public static function filterLocation(&$hotels, $location) {
        #location 
        if(isset($location) && is_object($location)) {
            $tip = $location->tip;
            $field = $tip.'_id';

            $hotels->where('h.'.$field, '=', $location->$field); 
        }
    }
    public static function filterLocation2(&$hotels, $location) {
        #location 
        if(isset($location) && !empty($location)) {
			$id = substr($location,1,strlen($location)-1);
			if(substr($location,0,1)=='C') {
				$hotels->where('h.country_id', '=', $id); 
			}
			if(substr($location,0,1)=='P') {
				$hotels->where('h.province_id', '=', $id); 
			}
			if(substr($location,0,1)=='T') {
				$hotels->where('h.town_id', '=', $id); 
			}
			if(substr($location,0,1)=='D') {
				$hotels->where('h.district_id', '=', $id); 
			} 
        }
    }
    public static function filterCriterias(&$hotels, $filters) {
        #filter - properties
        if(isset($filters) && is_array($filters)) {
            if(isset($filters['properties']) && is_array($filters['properties'])) {
            
                $hotels->join('hotels_properties as hp', function($q) use($filters) {
                    $q->on("hp.hotel_id","=","h.hotel_id"); 
                    $q->where(function($q1) use($filters) {
                        $func = "where";
                        foreach($filters['properties'] as $prop) {
                            $q1->$func("hp.property_id","=",$prop); 
                            $func = "orWhere";
                        } 
                    }); 
                }); 
            }  
        }
    }
	
	public static function filterTourCriterias2(&$hotels, $filters) {
        #filter - properties
        if(isset($filters) && is_array($filters)) {
			if(isset($filters['amount']) && !empty($filters['amount'])) {
				$t = $filters['amount'];
				$t = str_replace([' ','₺','TL','USD','SAR'],['','','','',''],$t);
				list($min,$max) = explode("-",$t);
				
				//$currentCurrency = session('currency','TL');
				$currentCurrency = 'SAR';
				if(isset($filters['curr'])) {
					$currentCurrency = $filters['curr'];
				}
				
				$hotels->where(DB::Raw("(hhhh.tmp_min_price_discounted*GetExchangeRate(hhhh.currency_code,'".$currentCurrency."'))"), ">=", $min);
				$hotels->where(DB::Raw("(hhhh.tmp_min_price_discounted*GetExchangeRate(hhhh.currency_code,'".$currentCurrency."'))"), "<=", $max);
			}
			if(isset($filters['months']) && is_array($filters['months']) && count($filters['months'])>0) {
				$hotels->where(function($q) use($filters) {
					foreach($filters['months'] as $k=>$m) {
						if($k == 0) {
							$func = "whereRaw";
						} else {
							$func = "orWhereRaw";
						}

						$st = $m;
						$en = date("Y-m", strtotime($m));
						$q->{$func}("DATE_FORMAT(hhhh.start_date,'%Y-%m')='$en'");
					}  
				});
				/*
				$hotels->whereExists(function($query) use($filters) {
					$query->select(DB::raw(1))
                      ->from('tours_startdates')
                      ->whereRaw('tours_startdates.tour_id = h.tour_id')
					  ->where(function($q) use($filters) {
							foreach($filters['months'] as $k=>$m) {
								if($k == 0) {
									$func = "whereRaw";
								} else {
									$func = "orWhereRaw";
								}

								$st = $m;
								$en = date("Y-m", strtotime($m));
								$q->{$func}("DATE_FORMAT(start_date,'%Y-%m')='$en'");
							}  
					  }); 
					
				}); 
				*/
			} 
			if(isset($filters['daycounts']) && is_array($filters['daycounts']) && count($filters['daycounts'])>0) {
				$hotels->where(function($q) use ($filters){
					foreach($filters['daycounts'] as $k=>$m) {
						if($k == 0) {
							$func = "where";
						} else {
							$func = "orWhere";
						}
						$q->{$func}("hhhh.tour_datecount","=",$m);
					}
				}); 
			}
        }
    }
	
	
	public static function filterTourCriterias(&$hotels, $filters) {
        #filter - properties
        if(isset($filters) && is_array($filters)) {
			if(isset($filters['amount']) && !empty($filters['amount'])) {
				$t = $filters['amount'];
				$t = str_replace([' ','₺','TL','USD','SAR'],['','','','',''],$t);
				list($min,$max) = explode("-",$t);
				
				//$currentCurrency = session('currency','TL');
				$currentCurrency = 'SAR';
				if(isset($filters['curr'])) {
					$currentCurrency = $filters['curr'];
				}
				
				$hotels->having(DB::Raw("(tmp_min_price_discounted*GetExchangeRate(h.currency_code,'".$currentCurrency."'))"), ">=", $min);
				$hotels->having(DB::Raw("(tmp_min_price_discounted*GetExchangeRate(h.currency_code,'".$currentCurrency."'))"), "<=", $max);
			}
			if(isset($filters['months']) && is_array($filters['months']) && count($filters['months'])>0) {
				$hotels->where(function($q) use($filters) {
					foreach($filters['months'] as $k=>$m) {
						if($k == 0) {
							$func = "whereRaw";
						} else {
							$func = "orWhereRaw";
						}

						$st = $m;
						$en = date("Y-m", strtotime($m));
						$q->{$func}("DATE_FORMAT(ts.start_date,'%Y-%m')='$en'");
					}  
				});
				/*
				$hotels->whereExists(function($query) use($filters) {
					$query->select(DB::raw(1))
                      ->from('tours_startdates')
                      ->whereRaw('tours_startdates.tour_id = h.tour_id')
					  ->where(function($q) use($filters) {
							foreach($filters['months'] as $k=>$m) {
								if($k == 0) {
									$func = "whereRaw";
								} else {
									$func = "orWhereRaw";
								}

								$st = $m;
								$en = date("Y-m", strtotime($m));
								$q->{$func}("DATE_FORMAT(start_date,'%Y-%m')='$en'");
							}  
					  }); 
					
				}); 
				*/
			} 
			if(isset($filters['daycounts']) && is_array($filters['daycounts']) && count($filters['daycounts'])>0) {
				$hotels->where(function($q) use ($filters){
					foreach($filters['daycounts'] as $k=>$m) {
						if($k == 0) {
							$func = "where";
						} else {
							$func = "orWhere";
						}
						$q->{$func}("h.tour_datecount","=",$m);
					}
				}); 
			}
        }
    }
	
	public static function searchToursWithListPriceNew($params = [], $page = 1, $items_per_page = 9) {
		extract($params);
		
		if(isset($calc) && is_array($calc)) { 
		  
            $adults = (int)$calc['adults'];
            $children = (int)$calc['children'];
            $c1_yas = 0;
            $c2_yas = 0;
            $c3_yas = 0;
            if($children>0) {
                $c1_dt = date("Y-m-d", strtotime($calc['c1']));
                //$c1_yas = calculate_child_age($c1_dt, $date);
				$c1_yas = $calc['c1'];
            }
            if($children>1) {
                $c2_dt = date("Y-m-d", strtotime($calc['c2']));
                //$c2_yas = calculate_child_age($c2_dt, $date);
				$c2_yas = $calc['c2'];
            }
            if($children>2) {
                $c3_dt = date("Y-m-d", strtotime($calc['c3']));
                //$c3_yas = calculate_child_age($c3_dt, $date);
				$c3_yas = $calc['c3'];
            }
			
			if(!isset($is_logged_in)) {
				$is_logged_in = 0;
			} 
		} else {
			$adults = 2;
			$children = 0;
			$c1_yas = 0;
			$c2_yas = 0;
			$c3_yas = 0;
		}
        $selects = [
            'h.*','ts.id','ts.code','ts.start_date','ts.end_date','ts.transitional_date'
        ]; 
				
		
		
		$selects[] = "@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field";
		$selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 1) as DECIMAL(10,2) )/2 AS tmp_min_price";
		$selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 2) as DECIMAL(10,2) )/2 AS tmp_min_price_discounted";
		
		$where = "h.tour_status=1 AND ts.start_date>='".date("Y-m-d")."' AND EXISTS(SELECT 1 FROM tours_startdates WHERE tours_startdates.tour_id=h.tour_id AND tours_startdates.start_date>='".date("Y-m-d")."')"; 
		
		$sql = "SELECT [selects] FROM tours h ";
		$sql.= "INNER JOIN tours_startdates as ts ON ts.tour_id=h.tour_id ";
		if(isset($category_id) && !is_null($category_id) && !empty($category_id)) {
			
			$sql.= "INNER JOIN tours_categories as hc ON hc.tour_id=h.tour_id AND hc.category_id='".$category_id."'";
		}
		
		$sql.=" WHERE $where ";
		
		$tcat = TourCategory::find($category_id);
		
		$currentCurrency = session('currency',false);
		
		//var_dump($hotels->orders);
		if(!$currentCurrency || true) {
			$currentCurrency = $tcat->currency_code;
		}
		//$selects = "SQL_CALC_FOUND_ROWS ".implode(", ",$selects);
		
		
 
		$sel1 = implode(", ",[
			"@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '2', '0', '0', '0', '0') as price_field",
			"MIN((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) / 2) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as minP"
		]);
		$sql1 = str_replace('[selects]',$sel1,$sql)
				." GROUP BY h.tour_id ORDER BY minP asc LIMIT 1"; 
		$minP = DB::select($sql1); 
		if(count($minP)==0) {
			$minP = 0;
		} else {
			$minP = $minP[0]->minP;
		}
		
		$sel1 = implode(", ",[
			"@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '2', '0', '0', '0', '0') as price_field",
			"MAX((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) / 2) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as minP"
		]);
		$sql1 = str_replace('[selects]',$sel1,$sql)
				." GROUP BY h.tour_id ORDER BY minP desc LIMIT 1"; 
		$maxP = DB::select($sql1); 
		if(count($maxP)==0) {
			$maxP = 0;
		} else {
			$maxP = $maxP[0]->minP;
		} 
		
		$sql1 = str_replace('[selects]','h.tour_datecount',$sql)
				." GROUP BY h.tour_datecount ORDER BY h.tour_datecount asc"; 
		$dayCounts = DB::select($sql1); 
		if(count($dayCounts)==0) {
			$dayCounts = [];
		} else {
			$t = [];
			foreach($dayCounts as $c) {
				$t[] = $c->tour_datecount;
			}
			$dayCounts = $t;
		} 
		
		$sql1 = str_replace('[selects]','h.tour_id',$sql)
				." GROUP BY h.tour_id ORDER BY h.tour_id asc"; 
		$tourIds = DB::select($sql1); 
		if(count($tourIds)==0) {
			$tourIds = [];
		} else {
			$t = [];
			foreach($tourIds as $c) {
				$t[] = $c->tour_id;
			}
			$tourIds = $t;
		} 
        
		
		$tourIds[]=0; 
		$months = TourStartDate::whereIn("tour_id",$tourIds)
				->selectRaw("CONCAT_WS('-',YEAR(start_date),MONTH(start_date),'1') as date")->groupBy(DB::raw("YEAR(start_date),MONTH(start_date)"))->where("start_date",">=",date("Y-m-d"))->pluck("date")->all();
		
		$filters['curr'] = $currentCurrency;
		
		
		//self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
	 
		//self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
		
		$sql1 = str_replace('[selects]', implode(",",$selects), $sql);
        #results
        $results = DB::table(DB::raw("(".$sql1.") as hhhh"))->selectRaw("SQL_CALC_FOUND_ROWS hhhh.*")->skip( ($page-1)*$items_per_page )->take($items_per_page);
				
		$filters['curr'] = $currentCurrency;
		
		//self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
	 
		self::filterTourCriterias2($results, (( isset($filters) )?$filters:null) );
				
		$results = $results->orderBy("hhhh.start_date","asc")->where("hhhh.tmp_min_price_discounted",">",0)->get();
		
		
		$tmp = (DB::select( DB::raw("SELECT FOUND_ROWS() AS total") ));
        $count = end( $tmp );
        
        $total_rows = $count->total;
        
        return [
            'pagination'=>[
                'page'=>$page,
                'items_per_page'=>$items_per_page,
                'total_rows'=>$total_rows,
                'first_row'=>($page-1)*$items_per_page,
                'last_row'=>($page)*$items_per_page,
                'total_pages'=>  ceil(($total_rows/$items_per_page))
            ],
			'daycounts'=>$dayCounts,
			'months'=>$months,
			'minPrice'=>$minP,
			'maxPrice'=>$maxP,
            'items'=>$results
        ];
		
		//$hotels->orderBy("ts.start_date","asc"); 
	}
	public static function searchToursWithListPrice($params = [], $page = 1, $items_per_page = 9) {
		if($_SERVER['REMOTE_ADDR']=='92.45.131.114' || true) {
			return self::searchToursWithListPriceNew($params, $page, $items_per_page);
		}
        extract($params);
        $selects = [
            'h.*','ts.id','ts.code','ts.start_date','ts.end_date','ts.transitional_date'
        ]; 
				
		$selects[] = "@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '1', '0', '0', '0', '0') as price_field";
		$selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 1) as DECIMAL(10,2) ) AS tmp_min_price";
		$selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 2) as DECIMAL(10,2) ) AS tmp_min_price_discounted";
			
        $raw_selects = [];
        
        $hotels = DB::table('tours as h')
				->join("tours_startdates as ts","ts.tour_id","=","h.tour_id")
                ->where('h.tour_status','=','1') 
				->where("ts.start_date",">",date("Y-m-d"))
				->whereNull("ts.deleted_at")
				->whereExists(function($query) {
					$query->select(DB::raw(1))
                      ->from('tours_startdates')
                      ->whereRaw('tours_startdates.tour_id = h.tour_id')
					  ->where("start_date",">=",date("Y-m-d")); 
				});
        
        self::filterTourCategory($hotels, (( isset($category_id) )?$category_id:null) );
        //self::filterKeywords($hotels, (( isset($keywords) )?$keywords:null) );
        //self::filterLocation($hotels, (( isset($location) )?$location:null) ); 
		if(isset($location2)) {
			//self::filterLocation2($hotels, (( isset($location2) )?$location2:null) ); 
		}
        //self::filterCriterias($hotels, (( isset($filters) )?$filters:null) );
        /*
        $hotels->leftJoin("hotels_roomtypes_periods as period","period.period_id",'=',DB::Raw(
                    "( SELECT hrp.period_id FROM hotels_roomtypes_periods hrp INNER JOIN hotels_roomtypes hrson ON hrson.roomtype_id = hrp.roomtype_id AND hrson.roomtype_showinsite = 1 WHERE hrp.hotel_id = h.hotel_id AND hrp.period_end >= '".date("Y-m-d")."' AND ( hrp.period_sale_start <= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND ( hrp.period_sale_end >= '".date("Y-m-d")."' OR ISNULL(hrp.period_sale_start) ) AND hrp.period_status LIKE 'active' AND ISNULL(hrp.deleted_at) AND hrp.period_showonsite = '1' ORDER BY hrp.period_saleprice ASC LIMIT 1 )"
                    ));*/
        //$hotels->leftJoin("concepts", 'concepts.concept_id', '=', 'period.concept_id');
        
		
        #sort
        if(isset($order) && !empty($order)) {
            if($order=='popularity') {
                if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                    foreach($keywords as $keyword) {
                        if(substr($keyword,0,1)=='Z') {
                            $kw2 = (int)str_replace('Z','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.tour_id='$kw2')"),'desc'); 
                        }
                        if(substr($keyword,0,1)=='Y') {
                            $kw2 = (int)str_replace('Y','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.tour_code='$kw2')"),'desc'); 
                        }
                    }
                }
                //$hotels->orderBy('h.tour_popularity','desc');
            } elseif($order=='discount') {
                //$hotels->orderBy(DB::Raw("ISNULL(period.period_discountrate)"),'asc');
                //$hotels->orderBy('period.period_discountrate','desc');
            } elseif($order=='minprice') {
                //$hotels->orderBy(DB::Raw("ISNULL(period.period_saleprice)"),'asc');
                //$hotels->orderBy(DB::Raw("( period.period_saleprice * paxoran )"),'asc');
            } elseif($order=='maxprice') {
                //$hotels->orderBy(DB::Raw("ISNULL(period.period_saleprice)"),'asc');
                //$hotels->orderBy(DB::Raw("( period.period_saleprice * paxoran )"),'desc');
            }
        } else {
            if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                foreach($keywords as $keyword) {
                    if(substr($keyword,0,1)=='Z') {
                        $kw2 = (int)str_replace('Z','',$keyword); 
                        //$hotels->orderBy(DB::Raw("(h.tour_id='$kw2')"),'desc'); 
                    }
                }
            }
			$hotels->orderBy("ts.start_date","asc");
            //$hotels->orderBy('h.tour_popularity','desc');
        }
        $hotels->orderBy("ts.start_date","asc"); 
        #add selections
		//(CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) / 2) * GetExchangeRate(h.currency_code,'TL') as minP
		//$currentCurrency = session('currency','TL');
		
		$tcat = TourCategory::find($category_id);
		
		//var_dump($hotels->orders);
		$currentCurrency = $tcat->currency_code;
		
        $hotels->selectRaw(DB::Raw("SQL_CALC_FOUND_ROWS ".implode(", ",$selects)));
        
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null; 
		$tmpHotels->selectRaw(DB::Raw(implode(", ",[
			"@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '2', '0', '0', '0', '0') as price_field",
			"MIN((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) / 2) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as minP"
		])));
		$minP = $tmpHotels->orderBy("minP","asc")->groupBy("h.tour_id")->take(1)->pluck('minP');
		if(count($minP)==0) {
			$minP = 0;
		} else {
			$minP = $minP[0];
		}
		 
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null;
		$tmpHotels->selectRaw(DB::Raw(implode(", ",[
			"@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '2', '0', '0', '0', '0') as price_field",
			"MAX((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) / 2) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as minP"
		])));
		$maxP = $tmpHotels->orderBy("minP","desc")->groupBy("h.tour_id")->take(1)->pluck('minP');
		if(count($maxP)==0) {
			$maxP = 0;
		} else {
			$maxP = $maxP[0];
		}
		
        $tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null;
		$dayCounts = $tmpHotels->select("h.tour_datecount")->groupBy("h.tour_datecount")->pluck('h.tour_datecount'); 	
        
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null;
		$tourIds = $tmpHotels->select("h.tour_id")->groupBy("h.tour_id")->pluck('h.tour_id');
		
		$months = TourStartDate::whereIn("tour_id",$tourIds)
				->selectRaw("CONCAT_WS('-',YEAR(start_date),MONTH(start_date),'1') as date")->groupBy(DB::raw("YEAR(start_date),MONTH(start_date)"))->where("start_date",">=",date("Y-m-d"))->pluck("date")->all();
		
		$filters['curr'] = $currentCurrency;
		
		//self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
	 
			self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
		 
		
        #results
        $results = $hotels->skip( ($page-1)*$items_per_page )->take($items_per_page)->get();
        
		$tmp = (DB::select( DB::raw("SELECT FOUND_ROWS() AS total") ));
        $count = end( $tmp );
        
        $total_rows = $count->total;
        
        return [
            'pagination'=>[
                'page'=>$page,
                'items_per_page'=>$items_per_page,
                'total_rows'=>$total_rows,
                'first_row'=>($page-1)*$items_per_page,
                'last_row'=>($page)*$items_per_page,
                'total_pages'=>  ceil(($total_rows/$items_per_page))
            ],
			'daycounts'=>$dayCounts,
			'months'=>$months,
			'minPrice'=>$minP,
			'maxPrice'=>$maxP,
            'items'=>$results
        ];
    }
	
	public static function searchToursWithCalculatedPrice($params = [], $page = 1, $items_per_page = 9) {
		if($_SERVER['REMOTE_ADDR']=='92.45.131.114' || true) {
			return self::searchToursWithListPriceNew($params, $page, $items_per_page);
		}
		
        extract($params);
     
		$tcat = TourCategory::find($category_id);
		$currentCurrency = $tcat->currency_code; 
		
        $selects = [
            'h.*',DB::raw("ts.code"),'ts.id','ts.start_date','ts.end_date','ts.transitional_date'
        ];
        $raw_selects = [];
		
        $hotels = DB::table('tours as h')
				->join("tours_startdates as ts","ts.tour_id","=","h.tour_id")
                ->where('h.tour_status','=','1') 
				->where("ts.start_date",">",date("Y-m-d"))
				->whereNull("ts.deleted_at")
				->whereExists(function($query) {
					$query->select(DB::raw(1))
                      ->from('tours_startdates')
                      ->whereRaw('tours_startdates.tour_id = h.tour_id')
					  ->where("start_date",">=",date("Y-m-d")); 
				});
        
        self::filterTourCategory($hotels, (( isset($category_id) )?$category_id:null) );
        self::filterKeywords($hotels, (( isset($keywords) )?$keywords:null) );
        //self::filterLocation($hotels, (( isset($location) )?$location:null) ); 
        if(isset($location2)) {
			//self::filterLocation2($hotels, (( isset($location2) )?$location2:null) ); 
		}
        
        
	 
        #calc
        if(isset($calc) && is_array($calc)) { 
		  
            $adults = (int)$calc['adults'];
            $children = (int)$calc['children'];
            $c1_yas = 0;
            $c2_yas = 0;
            $c3_yas = 0;
            if($children>0) {
                $c1_dt = date("Y-m-d", strtotime($calc['c1']));
                //$c1_yas = calculate_child_age($c1_dt, $date);
				$c1_yas = $calc['c1'];
            }
            if($children>1) {
                $c2_dt = date("Y-m-d", strtotime($calc['c2']));
                //$c2_yas = calculate_child_age($c2_dt, $date);
				$c2_yas = $calc['c2'];
            }
            if($children>2) {
                $c3_dt = date("Y-m-d", strtotime($calc['c3']));
                //$c3_yas = calculate_child_age($c3_dt, $date);
				$c3_yas = $calc['c3'];
            }
			
			if(!isset($is_logged_in)) {
				$is_logged_in = 0;
			} 
			
            $selects[] = "@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field";
            $selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 1) as DECIMAL(10,2) ) AS tmp_min_price";
            $selects[] = "CAST( SPLIT_STR (@calculation_tmp, '|', 2) as DECIMAL(10,2) ) AS tmp_min_price_discounted";  
             
            //$hotels->groupBy("h.tour_id");
            
            if(isset($filters) && isset($filters['price_min']) && !empty($filters['price_min'])) {
                $hotels->having(DB::Raw("tmp_min_price_discounted"), ">=", $filters['price_min']);
            }
            if(isset($filters) && isset($filters['price_max']) && !empty($filters['price_max']) && $filters['price_max']!="3000+") {
                $hotels->having(DB::Raw("tmp_min_price_discounted"), "<=", $filters['price_max']);
            }
        }
        
        #sort 
        if(isset($order) && !empty($order)) {
            if($order=='popularity') {
                if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                    foreach($keywords as $keyword) {
                        if(substr($keyword,0,1)=='Z') {
                            $kw2 = (int)str_replace('Z','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.tour_id='$kw2')"),'desc'); 
                        }
                        if(substr($keyword,0,1)=='Y') {
                            $kw2 = (int)str_replace('Y','',$keyword); 
                            $hotels->orderBy(DB::Raw("(h.tour_code='$kw2')"),'desc'); 
                        }
                    }
                }
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
				$hotels->orderBy('ts.start_date','asc');
                //$hotels->orderBy('h.tour_popularity','desc');
            } elseif($order=='discount') { 
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('calculated_discount','desc');
            } elseif($order=='minprice') {
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('tmp_min_price_discounted','asc');
            } elseif($order=='maxprice') {
                $hotels->orderBy(DB::Raw("ISNULL(price_field)"),'asc');
                $hotels->orderBy('tmp_min_price_discounted','desc');
            }
        } else {
            if(isset($keywords) && is_array($keywords) && count($keywords)>0) {
                foreach($keywords as $keyword) {
                    if(substr($keyword,0,1)=='Z') {
                        $kw2 = (int)str_replace('Z','',$keyword); 
                        $hotels->orderBy(DB::Raw("(h.tour_id='$kw2')"),'desc'); 
                    }
                }
            }
            
            
            $hotels->orderBy('ts.start_date','asc');
			
			
        }
        $hotels->orderBy("ts.start_date","asc");
		//$hotels->having(DB::Raw("tmp_min_price_discounted"), ">", "0");
		
        #add selections
        $hotels->selectRaw(DB::Raw("SQL_CALC_FOUND_ROWS ".implode(", ",$selects)));
        
		//$currentCurrency = session('currency','TL');
		
		
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null; 
		$tmpHotels->groups = null; 
		$tmpHotels->havings = null; 
		$tmpHotels->selectRaw(DB::Raw(implode(", ",[
			$selects[] = "@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field",
			"MIN((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) ) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as minP"
		])));
		$minP = $tmpHotels->orderBy("minP","asc")->take(1)->pluck('minP');
		if(count($minP)==0) {
			$minP = 0;
		} else {
			$minP = $minP[0];
		}
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null; 
		$tmpHotels->havings = null; 
		$tmpHotels->selectRaw(DB::Raw(implode(", ",[
			$selects[] = "@calculation_tmp := TourStartDateListPriceNew(h.tour_id,ts.id, '$is_logged_in', '".date("Y-m-d H:i:s")."', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas') as price_field",
			"MAX((CAST( SPLIT_STR ( @calculation_tmp, '|', 2 ) AS DECIMAL ( 10, 2 ) ) ) * GetExchangeRate(h.currency_code,'".$currentCurrency."')) as maxP"
		])));
		$maxP = $tmpHotels->orderBy("tmp_min_price_discounted","desc")->groupBy("h.tour_id")->take(1)->pluck('maxP');
		if(count($maxP)==0) {
			$maxP = 0;
		} else {
			$maxP = $maxP[0];
		}
		
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null;
		$tmpHotels->havings = null;
		$dayCounts = $tmpHotels->select("h.tour_datecount")->groupBy("h.tour_datecount")->pluck('h.tour_datecount'); 		
		
		$tmpHotels = clone $hotels;
		$tmpHotels->orders = null;
		$tmpHotels->groups = null; 
		$tmpHotels->havings = null; 
		$tourIds = $tmpHotels->select("h.tour_id")->groupBy("h.tour_id")->pluck('h.tour_id');
		
		$months = TourStartDate::whereIn("tour_id",$tourIds)
				->selectRaw("CONCAT_WS('-',DATE_FORMAT(start_date,'%Y-%m'),'01') as date")->groupBy(DB::raw("DATE_FORMAT(start_date,'%Y-%m')"))->where("start_date",">=",date("Y-m-d"))->pluck("date")->all();
		
		$filters['curr'] = $currentCurrency;
		
		self::filterTourCriterias($hotels, (( isset($filters) )?$filters:null) );
		
	 
		
        #results
        $results = $hotels->skip( ($page-1)*$items_per_page )->take($items_per_page)->get();
        
		$tmp = (DB::select( DB::raw("SELECT FOUND_ROWS() AS total") ));
        $count = end( $tmp );
        
        //$queries = DB::getQueryLog();
 
        $total_rows = $count->total;
        
        return [
            'pagination'=>[
                'page'=>$page,
                'items_per_page'=>$items_per_page,
                'total_rows'=>$total_rows,
                'first_row'=>($page-1)*$items_per_page,
                'last_row'=>($page)*$items_per_page,
                'total_pages'=>  ceil(($total_rows/$items_per_page))
            ],
			'daycounts'=>$dayCounts,
			'months'=>$months,
			'minPrice'=>$minP,
			'maxPrice'=>$maxP,
            'items'=>$results
        ];
    }
	
} 
<?php

namespace App\Http\Controllers;

use App\Http\Requests; 
use Illuminate\Http\Request; 
use App\Models\Hotel\Hotel;  
use DB;
use Session; 
use App\Models\Hotel\HotelRoomType;
use App\Models\Hotel\Concept;
use App\Models\Hotel\Category;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\ReservationRoomGuest;
use App\Models\ExchangeRate;
use App\Models\Account\CreditCard;

class ReservationController extends Controller
{
    private $request;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request; 
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function getOnline()
    { 
        $hotel_id = $this->request->input('hid', false);
        $date = $this->request->input('checkin', false);
        $date2 = $this->request->input('checkout', false);
        $ttt = strtotime($date2) - strtotime($date);
		$days = ceil($ttt/86400);
        $roomtype_id = $this->request->input('rid', false);
        $concept_id = $this->request->input('cid', false);
        $adults = $this->request->input('adults', false);
        $children = $this->request->input('children', 0);
        $c1 = $this->request->input('c1', 0);
        $c2 = $this->request->input('c2', 0);
        $c3 = $this->request->input('c3', 0);
        $isLoggedIn = (checkFrontEndLogin())?1:0;
        $price = $this->request->input('price', false);
        
        if( ! ($hotel_id && $date && $days && $roomtype_id && $concept_id && $adults && $price)  ) {
            abort(404);
        }
        
		$date = date("Y-m-d", strtotime($date));
		$date2 = date("Y-m-d", strtotime($date2));
		
        $hotel = Hotel::ModelJoin()->find($hotel_id);
        $roomtype = HotelRoomType::find($roomtype_id);
        if($roomtype->hotel_id != $hotel_id) {
            abort(404);
        }
        
        $concept = Concept::find($concept_id);
        
        $roomtype->tmp_price = null;
        
        $c1_yas = 0;
        $c2_yas = 0;
        $c3_yas = 0;
        
        if($children>0) {
            $c1_dt = date("Y-m-d", strtotime($c1));
            $c1_yas = calculate_child_age($c1_dt, $date);
			$c1_yas = $c1;
        }
        if($children>1) {
            $c2_dt = date("Y-m-d", strtotime($c2));
            $c2_yas = calculate_child_age($c2_dt, $date);
			$c2_yas = $c2; 
        }
        if($children>2) {
            $c3_dt = date("Y-m-d", strtotime($c3));
            $c3_yas = calculate_child_age($c3_dt, $date);
			$c3_yas = $c3;
        }
           
		$sonuc = DB::select("CALL RoomTypeListPriceNew(".$hotel_id.",".$roomtype->roomtype_id.", $concept->concept_id ,".$isLoggedIn.",'".date("Y-m-d H:i:s")."', '$date', '$days', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas');");
		
        if(is_array($sonuc) && count($sonuc)>0) {
            $tmp = $sonuc[0];
			 
            if(!is_null($tmp->totalDiscountedPrice) && $tmp->hasStop<1) {
                $roomtype->calculatedPrice = $tmp;
            }
        }
         
        if(is_null($roomtype->calculatedPrice) || $roomtype->calculatedPrice->totalDiscountedPrice!=$price) {
            abort(404);
        }
        
        return view('reservation.online',[
            'category'=> Category::find(1),
            'hotel'=>$hotel,
            'roomtype'=>$roomtype,
            'concept'=>$concept,
            'date'=>$date,
            'days'=>$days,
			'checkin'=>$date,
			'checkout'=>$date2,
            'adults'=>$adults,
            'children'=>$children,
            'c1'=>$c1,
            'c2'=>$c2,
            'c3'=>$c3,
			'currencies'=>config('tv.currencies'),
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]); 
    } 
    
    public function getModify($res_code = false) {
        if(!$res_code) {
            abort(404); 
        }
       
        $reservation = Reservation::where('reservation_status', 'LIKE', 'kayit')->where('reservation_code', 'LIKE', $res_code)->first();
        if(!$reservation) {
            abort(404);
        }
        $isLoggedIn = (checkFrontEndLogin())?1:0;
		
        $total_price = 0;
        $total_cost = 0;
        $total_displayedprice = 0;
        
        if($reservation->rooms->count()>0) {
            foreach($reservation->rooms as $room) {
                $yetiskin = $room->adults;
                $cocuk = $room->children;
                
                $c1_dt = ""; $c2_dt = ""; $c3_dt = ""; 
                $c1_yas=0; $c2_yas=0; $c3_yas=0;

                if($cocuk>0) {
                    $c1_dt = $room->child1_bdate;
                    $c1_yas = calculate_child_age($c1_dt, $reservation->reservation_start); 
                }
                if($cocuk>1) {
                    $c2_dt = $room->child2_bdate;
                    $c2_yas = calculate_child_age($c2_dt, $reservation->reservation_start);

                }
                if($cocuk>2) {
                    $c3_dt = $room->child3_bdate;
                    $c3_yas = calculate_child_age($c3_dt, $reservation->reservation_start);
                }
				$sonuc = DB::select("CALL RoomTypeListPriceNew(".$reservation->hotel_id.",".$room->roomtype_id.", $room->concept_id ,".$isLoggedIn.",'".date("Y-m-d H:i:s")."', '".$reservation->reservation_start."', '".$reservation->reservation_days."', '$yetiskin', '$cocuk', '$c1_yas', '$c2_yas', '$c3_yas');"); 
				
                if(is_array($sonuc) && count($sonuc)>0) {
                    $tmp = $sonuc[0];
                    if(!is_null($tmp->totalDiscountedPrice)) {
                        $total_price += $tmp->totalDiscountedPrice;
                        $total_cost += $tmp->totalCost;
                        $total_displayedprice += $tmp->totalDiscountedPrice;
                        if($tmp->totalDiscountedPrice>$room->room_price && $tmp->hasStop<1) {
                            $room->room_price = number_format($tmp->totalDiscountedPrice, 2, '.','');
                            $room->room_costprice = number_format($tmp->totalCost, 2, '.','');
                            $room->save();
                        }
                    }
                }
            }
        }
        
        $total_price = number_format($total_price,2,'.','');
        $total_cost = number_format($total_cost,2,'.','');
        $total_displayedprice = number_format($total_displayedprice,2,'.','');
        
        $price_updated = false;
        if($total_price>$reservation->reservation_price) {
            $reservation->reservation_price = $total_price;
            $reservation->reservation_costprice = $total_cost;
            $reservation->reservation_displayedprice = $total_displayedprice;
            $reservation->save();
            
            $price_updated = true;
        }
        
        return view('reservation.modify',[
            'reservation'=>$reservation,
            'total_price'=>$total_price,
            'total_cost'=>$total_cost,
            'price_updated'=>$price_updated,
			'currencies'=>config('tv.currencies'),
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]);
    }
    
    public function postModify() {
        $res_code = $this->request->input('reservation_code', false);
        $data = $this->request->input('data', []);
        
        if(!$res_code) {
            abort(404); 
        }
        
        $reservation = Reservation::where('reservation_status', 'LIKE', 'kayit')->where('reservation_code', 'LIKE', $res_code)->first();
        if(!$reservation) {
            abort(404); 
        }
        
		
		
        $new_row = [
            'reservation_gender'=>$data['rez']['cinsiyet'],
            'reservation_name'=>$data['rez']['ad'],
            'reservation_lastname'=>$data['rez']['soyad'],
            'reservation_tc'=>$data['rez']['tc'],
            'reservation_email'=>$data['rez']['email'],
            'reservation_mobile'=>$data['rez']['mobile'],
            'reservation_phone'=> ( (isset($data['rez']['phone']))?$data['rez']['phone']:null ),
            'reservation_fax'=> ( (isset($data['rez']['phone']))?$data['rez']['faks']:null ),
            'reservation_address'=>$data['rez']['adres'],
            'reservation_city'=>$data['rez']['il'],
            'reservation_town'=>$data['rez']['ilce'],
            'reservation_notes'=>$data['not']
        ]; 
        
        if( !isset($data['rez']['faturabilgiayni']) ) {
            $new_row['reservation_invoice_title'] = $data['fatura']['unvan'];
            $new_row['reservation_invoice_taxoffice'] = $data['fatura']['vd'];
            $new_row['reservation_invoice_taxno'] = $data['fatura']['adres'];
            $new_row['reservation_invoice_address'] = $data['fatura']['vno'];
            $new_row['reservation_invoice_city'] = $data['fatura']['il'];
            $new_row['reservation_invoice_town'] = $data['fatura']['ilce'];
        }  else {
            $new_row['reservation_invoice_title'] = null;
            $new_row['reservation_invoice_taxoffice'] = null;
            $new_row['reservation_invoice_taxno'] = null;
            $new_row['reservation_invoice_address'] = null;
            $new_row['reservation_invoice_city'] = null;
            $new_row['reservation_invoice_town'] = null;
        }
        
        
        DB::beginTransaction();
        
        $reservation->update($new_row); 
        
        #adults    
        if(isset($data['adult']) && count($data['adult'])>0) {
            foreach($data['adult'] as $gid=>$person) {
                $parts = array_reverse($person['dtarihi']); 
                $guest = ReservationRoomGuest::find($gid);
                if(!$guest) {
                    return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
                }
                $guest->update([ 
                    'guest_gender'=>$person['cinsiyet'],
                    'guest_name'=>$person['ad'],
                    'guest_lastname'=>$person['soyad'],
                    'guest_bdate'=>implode("-",$parts),
                    'guest_type'=>'0'
                ]);
            }
        }
       
        #children    
        if(isset($data['child']) && count($data['child'])>0) {
            foreach($data['child'] as $gid=>$person) {
                $parts = explode(".",$person['dtarihi']);
                $parts = array_reverse($parts);
                $guest = ReservationRoomGuest::find($gid);
                $guest->update([
                    'guest_gender'=>$person['cinsiyet'],
                    'guest_name'=>$person['ad'],
                    'guest_lastname'=>$person['soyad'],
                    'guest_bdate'=>implode("-",$parts),
                    'guest_type'=>'1'
                ]);
                if(!$guest) {
                    return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
                }
            }
        }
        
        DB::commit(); 
        
        return 'document.location.href="'.url('reservation/confirmation/'.$reservation->reservation_code).'"';
    }
    
    public function postOnline() {
        $hotel_id = $this->request->input('hid', false);
        $checkin = $this->request->input('checkin', false);
        $checkout = $this->request->input('checkout', false);
        $days = $this->request->input('days', false);
        $roomtype_id = $this->request->input('rid', false);
        $concept_id = $this->request->input('cid', false); 
        
		
        $isLoggedIn = (checkFrontEndLogin())?1:0;
        
		
		$hotel = Hotel::find($hotel_id);
        
        $data = $this->request->input('data', []);
        
        $adults = ( isset($data['adult']) && is_array($data['adult']) )?count($data['adult']):false;
        $children = ( isset($data['child']) && is_array($data['child']) )?count($data['child']):0;
        
        
        $c1 = ( isset($data['child'][1]['dtarihi']) && ($data['child'][1]['dtarihi']) )?$data['child'][1]['dtarihi']:null;
        $c2 = ( isset($data['child'][2]['dtarihi']) && ($data['child'][2]['dtarihi']) )?$data['child'][2]['dtarihi']:null;
        $c3 = ( isset($data['child'][3]['dtarihi']) && ($data['child'][3]['dtarihi']) )?$data['child'][3]['dtarihi']:null;
      
        
        
        if(!$adults || !$hotel_id || !$checkin || !$checkout || !$days || !$roomtype_id || !$concept_id) {
            return 'alert("Lütfen tüm gerekli alanları doğru bir şekilde doldurduğunuza emin olun.");';
        }  
        
        $hotel = Hotel::find($hotel_id);
        
        if((int)$hotel->hotel_onlymale==0) {
            $bayan_var = false;
            if(isset($data['adult']) && count($data['adult'])>0) {
                foreach($data['adult'] as $person) {
                    if($person['cinsiyet']=='bayan') {
                        $bayan_var = true;
                    }
                }
            }
            if(!$bayan_var) {
                return 'alert("Seçmiş olduğunuz otel tek bay konaklamalarına müsade etmemektedir.");';
            }
        }
        
        $today_ts = strtotime(date("Y-m-d")." 00:00:00");
        $twolayer_ts = strtotime("+2 days",$today_ts);
        $date_ts = strtotime($checkin." 00:00:00");
        if($date_ts<$today_ts) {
            return 'alert("En az 2 gün ilerisi için rezervasyon yapabilirsiniz.");';
        }
        
        #hesaplama
        $c1_yas = 0; $c1_dt = null;
        $c2_yas = 0; $c2_dt = null;
        $c3_yas = 0; $c3_dt = null;
        
        if($children>0) {
            $c1_dt = date("Y-m-d", strtotime($c1));
            $c1_yas = calculate_child_age($c1_dt, $checkin);
     
        }
        if($children>1) {
            $c2_dt = date("Y-m-d", strtotime($c2));
            $c2_yas = calculate_child_age($c2_dt, $checkin);
  
        }
        if($children>2) {
            $c3_dt = date("Y-m-d", strtotime($c3));
            $c3_yas = calculate_child_age($c3_dt, $checkin);
 
        }
        
        $calculatedPrice = false;
        
		$sonuc = DB::select("CALL RoomTypeListPriceNew(".$hotel_id.",".$roomtype_id.", $concept_id ,".$isLoggedIn.",'".date("Y-m-d H:i:s")."', '$checkin', '$days', '$adults', '$children', '$c1_yas', '$c2_yas', '$c3_yas');");  
         
        if(is_array($sonuc) && count($sonuc)>0) {
            $tmp = $sonuc[0];
            if(!is_null($tmp->totalDiscountedPrice) && $tmp->hasStop<1) {
                $calculatedPrice = $tmp;
            }
        }
        
        if(!$calculatedPrice) {
            return 'alert("Seçmiş olduğunuz dönem ve oda tipi için müsaitlik bulunmuyor. Lütfen konaklamanızı tekrar hesaplayıp sonra deneyin.");';
        } 
        
		
		$kur_cost = $calculatedPrice->totalCost;
		$kur_pr = $calculatedPrice->totalDiscountedPrice;
		
		$rates = $this->getRates();
		
		$calculatedPrice->totalCost = $calculatedPrice->totalCost * $rates[$hotel->currency_code]['TL'];
		$calculatedPrice->totalDiscountedPrice = $calculatedPrice->totalDiscountedPrice * $rates[$hotel->currency_code]['TL'];  
		
		
		$logged_in_member = checkFrontEndLogin();
		
        $new_row = [
			'member_id'=>($logged_in_member)?$logged_in_member->member_id:null,
            'reservation_code'=>uniqid('R'.time()),
            'hotel_id'=>$hotel_id, 
            'user_id'=>config('tv.online_user_id'),
            'reservation_start'=>$checkin,
            'reservation_end'=>$checkout,
            'reservation_days'=>$days,
            'reservation_rooms'=>1,
            'reservation_adults'=>$adults,
            'reservation_children'=>$children,
            'reservation_gender'=>$data['rez']['cinsiyet'],
            'reservation_name'=>$data['rez']['ad'],
            'reservation_lastname'=>$data['rez']['soyad'],
            'reservation_tc'=>$data['rez']['tc'],
            'reservation_email'=>$data['rez']['email'],
            'reservation_mobile'=>$data['rez']['mobile'],
            'reservation_phone'=> ( (isset($data['rez']['phone']))?$data['rez']['phone']:null ),
            'reservation_fax'=> ( (isset($data['rez']['phone']))?$data['rez']['faks']:null ),
            'reservation_address'=>$data['rez']['adres'],
            'reservation_city'=>$data['rez']['il'],
            'reservation_town'=>$data['rez']['ilce'],
            'reservation_notes'=>$data['not'],
            
            'reservation_costprice'=>number_format($calculatedPrice->totalCost,2,'.',''),
            'reservation_bank_commission'=>null,
            'reservation_price'=>number_format($calculatedPrice->totalDiscountedPrice,2,'.',''),
            'reservation_installment_commission'=>null,
            'reservation_total_price'=>number_format($calculatedPrice->totalDiscountedPrice,2,'.',''),
			
            'reservation_type'=>0,
            'reservation_status'=>'kayit',
			
			'reservation_currency_costprice'=>number_format($kur_cost,2,".",""),
            'reservation_currency_price'=>number_format($kur_pr,2,".",""),
            'currency_code'=>$hotel->currency_code,
        ]; 
         
        
        if( !isset($data['rez']['faturabilgiayni']) ) {
            $new_row['reservation_invoice_title'] = $data['fatura']['unvan'];
            $new_row['reservation_invoice_taxoffice'] = $data['fatura']['vd'];
            $new_row['reservation_invoice_taxno'] = $data['fatura']['adres'];
            $new_row['reservation_invoice_address'] = $data['fatura']['vno'];
            $new_row['reservation_invoice_city'] = $data['fatura']['il'];
            $new_row['reservation_invoice_town'] = $data['fatura']['ilce'];
        }  
         
        //var_dump($new_row); die();
  
        DB::beginTransaction();
        
        $rez = Reservation::create($new_row);
        if(!$rez) {
            return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
        } 
        #odayı oluştur
        
        $room = ReservationRoom::create([ 
            'reservation_id'=>$rez->reservation_id,
            'roomtype_id'=>$roomtype_id,
            'concept_id'=>$concept_id, 
            'adults'=>(int)$adults,
            'children'=>(int)$children,
            'child1_bdate'=>$c1_dt,
            'child2_bdate'=>$c2_dt,
            'child3_bdate'=>$c3_dt,
            'room_costprice'=>number_format($calculatedPrice->totalCost,2,'.',''),
            'room_price'=>number_format($calculatedPrice->totalDiscountedPrice,2,'.','')
        ]);
        if(!$room) {
            return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
        }   
        
    
        #adults    
        if(isset($data['adult']) && count($data['adult'])>0) {
            foreach($data['adult'] as $person) {
                $parts = array_reverse($person['dtarihi']);
                $guest = ReservationRoomGuest::create([
                    'room_id'=>$room->room_id,
                    'guest_gender'=>$person['cinsiyet'],
                    'guest_name'=>$person['ad'],
                    'guest_lastname'=>$person['soyad'],
                    'guest_bdate'=>implode("-",$parts),
                    'guest_type'=>'0'
                ]);
                if(!$guest) {
                    return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
                }
            }
        }
       
        #children     
        if(isset($data['child']) && count($data['child'])>0) {
            foreach($data['child'] as $person) {
                $parts = explode(".",$person['dtarihi']);
                $parts = array_reverse($parts);
                $guest = ReservationRoomGuest::create([
                    'room_id'=>$room->room_id,
                    'guest_gender'=>$person['cinsiyet'],
                    'guest_name'=>$person['ad'],
                    'guest_lastname'=>$person['soyad'],
                    'guest_bdate'=>implode("-",$parts),
                    'guest_type'=>'1'
                ]);
                if(!$guest) {
                    return 'alert("İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.");';
                }
            }
        }
            
        DB::commit(); 
     
        return 'document.location.href="'.url('reservation/confirmation/'.$rez->reservation_code).'"';
    } 
    public function getRates() {
		$rates = [];
		$ratesQ = ExchangeRate::get();
		foreach($ratesQ as $r) {
			if(!isset($rates[$r->source])) {
				$rates[$r->source] = [];
			}
			$rates[$r->source][$r->dest] = $r->rate;
		}
		
		return $rates;
	}
	
    public function getConfirmation($reservation_code=false) {
        if(!$reservation_code) {
            abort(404);
        }
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','kayit')->first();
        if(!$reservation) {
            abort(404);
        }
        
        return view('reservation.confirmation',[
            'reservation'=>$reservation,
			'currencies'=>config('tv.currencies'),
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]);
    }
    
    public function getPayment($reservation_code=false) {
        if(!$reservation_code) {
            abort(404);
        }
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','kayit')->first();
        if(!$reservation) {
            abort(404);
        }
        
        
        $isLoggedIn = (checkFrontEndLogin())?1:0;
		
        $total_price = 0;
        $total_cost = 0;
        $total_displayedprice = 0;
        
        if($reservation->rooms->count()>0) {
            foreach($reservation->rooms as $room) {
                $yetiskin = $room->adults;
                $cocuk = $room->children;
                
                $c1_dt = ""; $c2_dt = ""; $c3_dt = ""; 
                $c1_yas=0; $c2_yas=0; $c3_yas=0;

                if($cocuk>0) {
                    $c1_dt = $room->child1_bdate;
                    $c1_yas = calculate_child_age($c1_dt, $reservation->reservation_start); 
                }
                if($cocuk>1) {
                    $c2_dt = $room->child2_bdate;
                    $c2_yas = calculate_child_age($c2_dt, $reservation->reservation_start);

                }
                if($cocuk>2) {
                    $c3_dt = $room->child3_bdate;
                    $c3_yas = calculate_child_age($c3_dt, $reservation->reservation_start);
                }
				
				$sonuc = DB::select("CALL RoomTypeListPriceNew(".$reservation->hotel_id.",".$room->roomtype_id.", $room->concept_id ,".$isLoggedIn.",'".date("Y-m-d H:i:s")."', '".$reservation->reservation_start."', '".$reservation->reservation_days."', '$yetiskin', '$cocuk', '$c1_yas', '$c2_yas', '$c3_yas');"); 
				
                if(is_array($sonuc) && count($sonuc)>0) {
                    $tmp = $sonuc[0];
                    if(!is_null($tmp->totalDiscountedPrice)) {
                        $total_price += $tmp->totalDiscountedPrice;
                        $total_cost += $tmp->totalCost;
                        $total_displayedprice += $tmp->totalDiscountedPrice;
                        if($tmp->totalDiscountedPrice>$room->room_price) {
                            $room->room_price = number_format($tmp->totalDiscountedPrice, 2, '.','');
                            $room->room_costprice = number_format($tmp->totalCost, 2, '.','');
                            $room->save();
                        }
                    }
                } 
            }
        }
        
        $total_price = number_format($total_price,2,'.','');
        $total_cost = number_format($total_cost,2,'.','');
        $total_displayedprice = number_format($total_displayedprice,2,'.','');
        
        $price_updated = false;
        if($total_price>$reservation->reservation_price) {
            $reservation->reservation_price = $total_price;
            $reservation->reservation_costprice = $total_cost;
            $reservation->reservation_displayedprice = $total_displayedprice;
            $reservation->save();
            
            $price_updated = true;
        }   
        
        
        $cards = CreditCard::where('card_online','=','1')->where('card_status','=','1')->orderBy('card_order','asc')->get();
        
        if($this->request->ip()=='92.44.135.244' && false) {
            $cards = CreditCard::where('card_id','=','13')->orderBy('card_order','asc')->get();
        }
        
        $default_card = CreditCard::where('card_online','=','1')->where('card_status','=','1')->where('card_default','=','1')->orderBy('card_order','asc')->first();
  
        $default_card->card_id = 0;
        
        return view('reservation.payment',[
            'reservation'=>$reservation,
            'cards'=>$cards,
            'card'=>$default_card,
            'price_updated'=>$price_updated,
			'currencies'=>config('tv.currencies'),
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]);
    }
    
    public function getContract($reservation_code=false) {
        if(!$reservation_code) {
            abort(404);
        }
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->first();
        if(!$reservation) {
            abort(404);
        }
        
        return view('reservation.contract', [
            'reservation'=>$reservation
        ]);
    }
    public function postPayment() {
        $card_id = $this->request->input('card_id',false);
        $reservation_code = $this->request->input('reservation_code',false);
        $taksit =$this->request->input('taksit',1);
        
        $adsoyad =$this->request->input('name',false);
        $kartno =$this->request->input('kartno',false);
        $skt_ay =$this->request->input('skt1',false);
        $skt_yil =$this->request->input('skt2',false);
        $cv2 =$this->request->input('cv2',false);
        
        if(!$reservation_code) {
            abort(404);
        }
        
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','kayit')->first();
        if(!$reservation) {
            abort(404);
        }
        
        if($card_id==0) {
            $card = CreditCard::where('card_online','=','1')->where('card_status','=','1')->orderBy('card_default','desc')->orderBy('card_order','asc')->first();
		 
        } else {
            $card = CreditCard::find($card_id);
        }
        $new_price = $card->getInstallmentPrice( $reservation->reservation_price,$taksit );
        
        
        $pos_name = $card->card_pos_model;
        
        $pos = new $pos_name([
            'mode'=>'PROD',
            'reservation_id'=>$reservation->reservation_id,
            'card_id'=>$card_id, 
            'Installment'=>$taksit, 
            'cc_name'=>$adsoyad,
            'cc_number'=>$kartno,
            'cc_exp_month'=>$skt_ay,
            'cc_exp_year'=>$skt_yil,
            'cc_cv2'=>$cv2
        ]);
        
        return $pos->Pay();
    }
    
    public function getSuccess($reservation_code = false) {
        $t = $this->request->all();
        if(!$reservation_code) {
            abort(404);
        }
        
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','onaylandi')->first();
        if(!$reservation) {
            abort(404);
        }
        return view('reservation.success',[
            'reservation'=>$reservation,
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]);
    }
    
    public function getError($reservation_code = false, $hata = '') {
        $t = $this->request->all();
        if(!$reservation_code) {
            abort(404);
        }
        
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','kayit')->first();
        if(!$reservation) {
            abort(404);
        }
        
        return view('reservation.error',[
            'reservation'=>$reservation,
            'hata'=>  base64_decode($hata),
            'home_seo'=>[
                'title'=>'Online Rezervasyon - Elçi Tur',
                'description'=>''
            ]
        ]);
    }
    
    public function postResult($reservation_code = '', $card_id = '') {
        $post = $this->request->all();
       
        if($reservation_code=='' || $card_id=='') {
            abort(404);
        } 
        $reservation = Reservation::where('reservation_code', 'LIKE', $reservation_code)->where('reservation_status','LIKE','kayit')->first();
        if(!$reservation) {
            abort(404);
        }
        
        if($card_id == 0) {
            $card = $this->card = CreditCard::where('card_online','=','1')->where('card_status','=','1')->orderBy('card_default','desc')->orderBy('card_order','asc')->first();
        } else {
            $card = CreditCard::find($card_id);
        }
        
        $pos_name = $card->card_pos_model;
        
        $pos = new $pos_name([ ]);
         
            
        $result = $pos->PaymentResult($post);
        if(!is_array($result)) {
            abort(404);
        }

        $bank_special_code = "";
        if(isset($post["campaignchooselink"])) {
            $bank_special_code = $post["campaignchooselink"];
        }
     
        if($result['result']=='success') {

            $amount = $result['amount'];
            $authcode = $result['authcode'];
            $installment = $result['installment'];
            if (isset($result['extra'])) {
                $extra = $result['extra'];
            } else {
                $extra = ['name' => null, 'pan' => null, 'exp1' => null, 'exp2' => null, 'cv2' => null];
            }
            #check 
            $check = \App\Models\ReservationPayment::where('payment_authcode', 'LIKE', $authcode)->where('card_id', '=', $card_id)->count();
            if ($check > 0) {
                return redirect(url(''));
            }
            if ($installment == '') {
                $installment = 1;
            }

            $installment_commission_rate = $card->getInstallmentRate($installment);
            $bank_commission_rate = $card->getBankCommissionRate($installment);
            $bank_commission = number_format($amount * ($bank_commission_rate / 100), 2, '.', '');

            $payment = new \App\Models\ReservationPayment([
                'reservation_id' => $reservation->reservation_id,
                'payment_type' => 'online',
                'user_id' => config('tv.online_user_id'),
                'card_id' => $card->card_id,
                'payment_name' => $extra['name'],
                'payment_masked_pan' => $extra['pan'],
                'payment_cvv2' => $extra['cv2'],
                'payment_edate' => ($extra['exp1'] != "" && $extra['exp2'] != "") ? $extra['exp1'] . '/' . $extra['exp2'] : null,

                'payment_amount' => $reservation->reservation_price,
                'payment_installment_commission' => ($amount - $reservation->reservation_price),
                'payment_installment_commission_rate' => $installment_commission_rate,
                'payment_total_amount' => $amount,
                'payment_status' => 1,
                'payment_bank_commission' => $bank_commission,
                'payment_bank_commission_rate' => $bank_commission_rate,
                'payment_installment' => (int)$installment,
                'payment_authcode' => $authcode,
                'payed_at' => date("Y-m-d"),
                'approved_user_id' => config('tv.online_user_id'),
                'approved_at' => date("Y-m-d H:i:s"),
                'payment_details' => base64_encode(json_encode($post)),
                'payment_invoice_title' => ((!is_null($reservation->reservation_invoice_title) && !empty($reservation->reservation_invoice_title)) ? $reservation->reservation_invoice_title : $reservation->reservation_name . " " . $reservation->reservation_lastname),

                'payment_invoice_taxoffice' => ((!is_null($reservation->reservation_invoice_taxoffice) && !empty($reservation->reservation_invoice_taxoffice)) ? $reservation->reservation_invoice_taxoffice : null),

                'payment_invoice_taxno' => ((!is_null($reservation->reservation_invoice_taxno) && !empty($reservation->reservation_invoice_taxno)) ? $reservation->reservation_invoice_taxno : null),

                'payment_invoice_tc' => ((is_null($reservation->reservation_invoice_taxno) || empty($reservation->reservation_invoice_taxno)) ? $reservation->reservation_tc : null),

                'payment_invoice_address' => ((!is_null($reservation->reservation_invoice_address) && !empty($reservation->reservation_invoice_address)) ? $reservation->reservation_invoice_address : $reservation->reservation_address),

                'payment_invoice_city' => ((!is_null($reservation->reservation_invoice_city) && !empty($reservation->reservation_invoice_city)) ? $reservation->reservation_invoice_city : $reservation->reservation_city),

                'payment_invoice_town' => ((!is_null($reservation->reservation_invoice_town) && !empty($reservation->reservation_invoice_town)) ? $reservation->reservation_invoice_town : $reservation->reservation_town),

                'bank_special_code'=>$bank_special_code,
            ]);
            $payment->save();

            \App\Models\ReservationPaymentLog::create([
                'charge' => $payment->payment_total_amount,
                'credit' => 0,
                'payment_id' => $payment->payment_id,
                'user_id' => config('tv.online_user_id'),
                'name' => 'Tahsilat'
            ]);

            if ($reservation->hotel) {

                $otelfisi_form = $reservation->createForm('otelfisi');
            }

            $contract_form = $reservation->createForm('contract');
            
            $reservation->reservation_bank_commission = $bank_commission;
            $reservation->reservation_installment_commission = $amount - $reservation->reservation_price;
            $reservation->reservation_total_price = $amount;
            
            $reservation->reservation_voucher = Reservation::NewVoucherNo();
            $reservation->reservation_status = 'onaylandi';
            $reservation->created_at = date("Y-m-d H:i:s");
            $voucher_form = $reservation->createForm('voucher'); 
            $reservation->save();
            
			$reservation->calculateTotalPrice();
			
            $g = sendSMS($reservation->reservation_mobile, "Bilgilendirme: Rezervasyonunuz ".$reservation->reservation_voucher." voucher numarasi ile olusturulmustur. Iyi tatiller dileriz. Elçi Tur / 444 71 44");
            
            try {
                if($reservation->reservation_email && $reservation->reservation_email!='' && $contract_form && $voucher_form) {
                    \Mail::send('emails.contract-and-voucher', ['form'=>$contract_form, 'form2'=>$voucher_form  ], function ($message) use ($contract_form,$voucher_form,$reservation) {
                        $message->subject(''.$reservation->reservation_voucher.' Numaralı Rezervasyonunuzun Detayları')
                                ->to($reservation->reservation_email) 
                                ->from('info@endigitals.com', 'Elçi Tur') ;
                                //->replyTo("konaklama@tatilvitrini.com");

                        if($contract_form->form_file=='' || is_null($contract_form->form_file)) {
                            $contract_form->form_file = $reservation->createContract( config('tv.online_user_id') );
                            $contract_form->save();
                        }
                        
                        if($voucher_form->form_file=='' || is_null($voucher_form->form_file)) {
                            $voucher_form->form_file = $reservation->createVoucher( config('tv.online_user_id') );
                            $voucher_form->save();
                        }
                        
                        $file = public_path($contract_form->form_file);
                        
                        if($file) {
                            $message->attach($file, array('as' => $reservation->reservation_voucher.' - Sözleşme', 'mime' => 'application/pdf'));  
                        }
                        
                        $file = public_path($voucher_form->form_file);
                        
                        if($file) {
                            $message->attach($file, array('as' => $reservation->reservation_voucher.' - Voucher', 'mime' => 'application/pdf'));  
                        }
                    });
                } 
            } catch (\Exception $ex) {
                //echo $ex->getFile().":".$ex->getLine()." ||| ".$ex->getMessage();
                //die();
                echo '';
            }
            
            return redirect(url('reservation/success/'.$reservation->reservation_code)); 
            
        } else if($result['result']=='error') {
            return redirect(url('reservation/error/'.$reservation->reservation_code.'/'.  base64_encode($result['message'])));
        }
        
        die();
    }
}

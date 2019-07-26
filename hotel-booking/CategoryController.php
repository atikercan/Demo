<?php

namespace App\Http\Controllers;

use App\Http\Requests; 
use Illuminate\Http\Request;
use App\Models\Hotel\Category;
use App\Models\Hotel\Hotel; 
use App\Models\Hotel\Property;
use DB;
use Session;
use App\Models\Location\Country;
use App\Models\Location\Town;
use App\Models\Location\Province;
use App\Models\Location\District;
use App\Models\TV;
use App\Models\Hotel\Concept;

class CategoryController extends Controller
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
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        //return view('home');
    } 
    
    public function getViewByLocation($bolge_link='', $kategori_link='') {
        # bolgeyi bul
        $bolge = Province::where('link','LIKE',$bolge_link)->first();
        if($bolge) {
            $bolge->tip = 'province';
            
        } 
        if(!$bolge) { 
            $bolge = Town::where('link','LIKE',$bolge_link)->first();
            if($bolge) {
                $bolge->tip = 'town';
            }
        }
        if(!$bolge) { 
            $bolge = District::where('link','LIKE',$bolge_link)->first();
            if($bolge) {
                $bolge->tip = 'district';
            }
            
        }
        if(!$bolge) { 
            $bolge = Country::where('link','LIKE',$bolge_link)->first();
            if($bolge) {
                $bolge->tip = 'country';
            }
            
        } 
        if(!$bolge) {
            abort(404);
        }
        
        $keywords =  $this->request->input('keywords',false);
        
        if(true) { 
            if(isset($keywords) && is_array($keywords)) {
                $redirect = false;
                $category = Category::where('location_link','LIKE','{bolgelink}-'.$kategori_link)->where('category_status','=','1')->first();
                if(!$category) {
                    abort(404);
                }  
                
                $params = $this->request->all();
                
                
                $will_be_check_hotel =[];
                $will_be_check_hotel2 = [];
                $will_be_check_province = [];
                
                foreach($keywords as $keyword) {
                    if(substr($keyword,0,1)=='H') {
                        $kw2 = (int)str_replace('H','',$keyword);
                        $will_be_check_hotel[] = $kw2;
                    }
                    if(substr($keyword,0,1)=='K') {
                        $kw2 = (int)str_replace('K','',$keyword);
                        $will_be_check_hotel2[] = $kw2;
                    }
                    if(substr($keyword,0,1)=='P') {
                        $kw2 = (int)str_replace('P','',$keyword); 
                        if($bolge->tip=='province') {
                            if($kw2!=$bolge->province_id) {
                                $redirect=true;
                            }
                        }
                        if($bolge->tip=='town') {
                            if($kw2!=$bolge->province_id) {
                                $redirect=true;
                            }
                        }
                        if($bolge->tip=='district') {
                            if($bolge->town && $kw2!=$bolge->town->province_id) {
                                $redirect=true;
                            }
                        }
                    }
                    if(substr($keyword,0,1)=='T') {
                        $kw2 = (int)str_replace('T','',$keyword); 
                        $tmp_bolge = Town::find($kw2);
                        if($tmp_bolge) {
                            if($bolge->tip=='province') {
                                if($tmp_bolge->province_id!=$bolge->province_id) {
                                    $redirect=true;
                                }
                            }
                            if($bolge->tip=='town') {
                                if($tmp_bolge->town_id!=$bolge->town_id) {
                                    $redirect=true;
                                }
                            }
                            if($bolge->tip=='district') {
                                if($tmp_bolge->town_id!=$bolge->town_id) {
                                    $redirect=true;
                                }
                            }
                        } 
                    }
                    if(substr($keyword,0,1)=='D') {
                        $kw2 = (int)str_replace('D','',$keyword); 
                        $tmp_bolge = District::find($kw2);
                        if($tmp_bolge) {
                            if($bolge->tip=='province') {
                                if($tmp_bolge->town && $tmp_bolge->town->province_id!=$bolge->province_id) {
                                    $redirect=true;
                                }
                            }
                            if($bolge->tip=='town') {
                                if($tmp_bolge->town_id!=$bolge->town_id) {
                                    $redirect=true;
                                }
                            }
                            if($bolge->tip=='district') {
                                if($tmp_bolge->district_id!=$bolge->district_id) {
                                    $redirect=true;
                                }
                            }
                        } 
                    }
                }
                
                if(count($will_be_check_hotel)>0) {
                    $check = Hotel::whereIn('hotel_id',$will_be_check_hotel)->where($bolge->tip.'_id','=',$bolge->{$bolge->tip.'_id'})->count();
                    if($check!=count($will_be_check_hotel)) {  
                        $redirect = true; 
                    }
                }  
                
                if(count($will_be_check_hotel2)>0) {
                    $check = Hotel::whereIn('hotel_code',$will_be_check_hotel2)->where($bolge->tip.'_id','=',$bolge->{$bolge->tip.'_id'})->count();
                    if($check!=count($will_be_check_hotel2)) {  
                        $redirect = true; 
                    }
                } 
                
                
                if($redirect) {
                    $key = strtoupper(substr($bolge->tip,0,1)).$bolge->{$bolge->tip.'_id'};
                    $params['keywords']=array_merge(array($key),$params['keywords']);
                    
                    $qs = http_build_query($params);
                    $link = $category->getLink().'?'.$qs;

                    return redirect($link); 
                }
            } 
        }
        return $this->getView($kategori_link, $bolge, $bolge_link);
    }
    
    public function getView($link='', $bolge = false, $bolge_link = false) { 
        $calc = $this->request->input('calc',false);
        $filters = $this->request->input('filters',false); 
        $order =  $this->request->input('ord','popularity'); 
        $keywords =  $this->request->input('keywords',false);
        $page =  $this->request->input('page',1);
        
        # Session Ayarla
        if($calc && is_array($calc) && isset($calc['adults']) && !empty($calc['adults']) && isset($calc['day']) && !empty($calc['day']) && isset($calc['date']) && !empty($calc['date'])) {
            Session::put('TvCalculation', $calc);
        } elseif(Session::has('TvCalculation') && !isset($calc['adults'])  && !isset($calc['children'])  && !isset($calc['day']) && !isset($calc['date']) ) {
            $calc = Session::get('TvCalculation');
            $calc = array();
        } else {
       
            Session::put('TvCalculation', []);
            
        }
        /*
        if($filters && is_array($filters)) {
            Session::put('TvFilters', $filters);
        } elseif(Session::has('TvFilters')) {
            $filters = Session::get('TvFilters');
        }*/
        if(!$bolge_link) {
            $category = Category::where('link','LIKE',$link)->where('category_status','=','1')->first(); 
        } else {
            $category = Category::where('location_link','LIKE','{bolgelink}-'.$link)->where('category_status','=','1')->first();
        }
        if(!$category) {
            abort(404);
        } 
         
        
        ########
        $params = [];
        $params['location'] = $bolge;
        $params['category_id'] = $category->category_id; 
        $params['order'] = $order;
        $params['page'] = $page;
        $params['keywords']=$keywords;
        
        if($filters && is_array($filters)) {
            $params['filters'] = $filters;
        }
        
        $left_locations = $category->getProvincesTowns(); 
        $left_districts = false;
        

        if($bolge && ($bolge->tip=='town' || $bolge->tip=='district')) {
            $t_id = $bolge->town_id;
            $left_districts = $category->getDistricts($t_id);
        } 
     
        if($calc && is_array($calc) && isset($calc['adults']) && !empty($calc['adults']) && isset($calc['day']) && !empty($calc['day']) && isset($calc['date']) && !empty($calc['date'])) { 
            $params['calc'] = $calc;
            $hotels = TV::searchHotelsWithCalculatedPrice($params, $page); 
            return view('category.view',[
                'category'=>$category,
                'hotels'=>$hotels,
                'bolge'=>$bolge,
                'calc'=>$calc,
                'filters'=>$filters, 
                'concepts'=>  Concept::orderBy('concept_order','asc')->get(),
                'locations'=>$left_locations,
                'left_districts'=>$left_districts,
                'properties'=>Property::whereNotNull('property_popularity')->where('property_popularity','>','0')->orderBy('property_popularity', 'desc')->get()
            ]); 
        } else { 
            $hotels = TV::searchHotelsWithListPrice($params, $page); 
            return view('category.view',[
                'category'=>$category,
                'hotels'=>$hotels,
                'bolge'=>$bolge,
                'calc'=>$calc,
                'filters'=>$filters, 
                'concepts'=>  Concept::orderBy('concept_order','asc')->get(),
                'locations'=>$left_locations,
                'left_districts'=>$left_districts,
                'properties'=>Property::whereNotNull('property_popularity')->where('property_popularity','>','0')->orderBy('property_popularity', 'desc')->get()
            ]); 
        }
        
    }
}

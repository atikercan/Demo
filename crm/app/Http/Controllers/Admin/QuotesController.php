<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Student;
use App\Models\ExchangeRate; 
use App\Models\Application;
use App\Models\Nationality;
use App\Admin;
use App\Models\CourseLanguage;
use App\Models\CourseType;
use App\Models\Quote;
use App\Models\Currency;
use App\Models\QuoteOption;
use App\Models\QuoteOptionCourse;
use App\Models\QuoteOptionService;
use App\Models\QuoteOptionAccommodation;
use App\Models\PartnerCampusCourse;
use App\Models\PartnerCampusAccommodation;
use App\Models\QuoteOptionPromotion;
use App\Models\PartnerCampusService;
use App\Models\PartnerCampus;
use App\Models\System\Images;
use App\Models\AccommodationType;
use App\Models\CountryEmail;
use Session; 
use App\Helpers\RouteHelper;
use App\Helpers\StringHelper;
use App\Helpers\LogHelper;
use App\Helpers\GeneralHelper;
use Mail;
use App\Models\Notification;

class QuotesController extends Controller {
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct( )
    { 
        $this->middleware('admin');
       
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {  
		if(!GeneralHelper::checkPrivilege("quotes.view")) {
			return view('admin.unauthorized');
		}
		$branch_id = \App\Helpers\GeneralHelper::currentBranchId();
        $me = Auth::guard('admin')->user();
        
        $current_status = $request->input('status',null);
        $search = $request->input('search',false);
		
		$quotes = Quote::currentAccount()
				->where("branch_id","=",$branch_id)->orderBy("quote_id","desc");
		
		
		if(!is_null($current_status) && $current_status!="null") {
			$quotes->where('quote_status','=',$current_status);
			$current_status = (int)$current_status;
		} else {
			$current_status = null;
		}
		
		if(!is_null($search) && $search!="null" && $search) {
			$quotes->where(function($q1) use ($search) {
				$q1->whereHas('student', function($q2) use ($search) {
					$q2->where("student_name","LIKE", "%".$search."%");
				});
				$q1->orWhere("quote_id","=",(int)$search);
				$q1->orWhereHas('admin', function($q2) use ($search) {
					$q2->where("name","LIKE", "%".$search."%");
				});
			});
		} else {
			$search = false;
		}
		
        return view('admin.quotes.index',[
            'records'=>$quotes->paginate(30),
            'menu_active' => 'quotes',
            'title' => trans('admin.quotes.quotes'),
			'current_status'=>$current_status,
			'search'=>$search
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($account, $student_id)
    {   
		
		$account = RouteHelper::getCurrentAccount();
		
		$nationalities = Nationality::orderBy("nationality_name","asc")->get();
		
        return view('admin.applications.create',[
            'menu_active' => 'applications',
			'nationalities'=>$nationalities,
            'title' => trans('admin.applications.create')
        ]);
    }

	
	public function optionsStoreEmptyOption($account,$quote_id) {
		$quote = Quote::find($quote_id);
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$currentOptionCount = $quote->options()->count();
		
		$option = QuoteOption::create([
			'option_name'=>trans('admin.quotes.option')." ".($currentOptionCount+1),
			'option_order'=>$currentOptionCount,
			'quote_id'=>$quote->quote_id
		]);
		
		Session::flash('result', trans('admin.quotes.add_option_success_text'));
        return 'reloadState();';
	}
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
		$me = Auth::guard('admin')->user();
		
		$courses = $request->input('courses',[]);
		if(!is_array($courses)) {
			$courses = [];
		}
		
		$student = $request->input('student',null);
		
		$quote_id = $request->input('quote',false);
		
		$quote_no = Quote::max("quote_no");
		
		
		$currentOptionCount = 0;
		
		if($quote_id) {
			$quote = Quote::find($quote_id);
		} else {
			$quote = false;
		}
		
		if(!$quote){
			$quote = Quote::create([
				'admin_id'=>$me->id,
				'student_id'=>$student,
				'language'=>'tr',
				'quote_no'=>($quote_no + 1)
			]);
		}
		
		$currentOptionCount = $quote->options()->count();
		
		$feeIds = [];
		
		
		foreach($courses as $index=>$course) {
			$dbCourse = PartnerCampusCourse::find($course['course_id']);
			if(!$dbCourse) {
				continue;
			} 
			
			$option = QuoteOption::create([
				'option_name'=>trans('admin.quotes.option')." ".($index+$currentOptionCount+1),
				'option_order'=>$index+$currentOptionCount,
				'quote_id'=>$quote->quote_id
			]);
			
			$curr = $dbCourse->campus->getCurrency();
			 
			$ctype = null;
			$clang = null;
			
			if($dbCourse->type) {
				$ctype = $dbCourse->type->type_name;
			}
			if($dbCourse->language) {
				$clang = $dbCourse->language->language_name;
			}
			
			$optCourse = QuoteOptionCourse::create([
				'option_id'=>$option->option_id,
				'course_id'=>$dbCourse->course_id,
				'option_course_name'=>$dbCourse->course_name,
				'option_course_partner'=>$dbCourse->campus->partner->partner_name,
				'option_course_campus'=>$dbCourse->campus->campus_name,
				'option_course_duration'=>$course['duration'],
				'option_course_duration_unit'=>$course['duration_unit'],
				'option_course_price'=>$course['price'],
				'option_course_price_type'=>'total',
				'option_course_gross_price'=>$course['price'],
				'option_course_description'=>$dbCourse->course_description,
				'option_course_course_type'=>$ctype,
				'option_course_course_language'=>$clang,
				'option_course_intensity'=>$dbCourse->course_intensity,
				'option_currency_code'=>$curr->currency_code,
				'currency_id'=>$curr->currency_id,
				'place_id'=>$dbCourse->campus->place_id,
				'campus_id'=>$dbCourse->campus_id
			]); 
			
			LogHelper::logEvent('created', $optCourse);
			
			$option->fixPromotions();
			$option->fixFees();
		}
		
		
			
        return 'closeModal();window.location.hash="'.RouteHelper::route("admin.quotes.show", ['quote'=>$quote->quote_id], false).'";';
    }
	
	public function storeEmpty(Request $request)
    {
		$me = Auth::guard('admin')->user();
		
	 
		
		$student = $request->input('student',null);
		
		$quote_id = $request->input('quote',false);
		
		$quote_no = Quote::max("quote_no");
		
		
		$currentOptionCount = 0;
		
		if($quote_id) {
			$quote = Quote::find($quote_id);
		} else {
			$quote = false;
		}
		
		if(!$quote){
			$quote = Quote::create([
				'admin_id'=>$me->id,
				'student_id'=>$student,
				'language'=>'tr',
				'quote_no'=>($quote_no + 1)
			]);
		}  
		
        return 'closeModal();window.location.hash="'.RouteHelper::route("admin.quotes.show", ['quote'=>$quote->quote_id], false).'";';
    }
	
	public function show($account, $id)
    {
		if(!GeneralHelper::checkPrivilege("quotes.view")) {
			return view('admin.unauthorized');
		} 
		
	 
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $admin = Quote::currentAccount()->where("quote_id","=",$id)
				->first(); 
		//->where("branch_id","=",$currentBranch)
        
        if(!$admin ) {
            Session::flash('fail', trans('admin.quotes.couldnt_find_record'));
			return '<script>window.location.hash="'.RouteHelper::route("admin.quotes.index", [], false).'";</script>';
        }
				
		$admins = Admin::whereHas("branches", function($q) use ($currentBranch){
			$q->where("admins_branches.branch_id","=",$currentBranch);
		})->orderBy("name","asc")->get();
		
		$canEdit = false;
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			$canEdit = true;
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $admin->admin_id == Auth::guard('admin')->user()->id) {
			$canEdit = true;
		}
		
        return view('admin.quotes.show',[
            'record'=>$admin,
			'admins'=>$admins,
			'canEdit'=>$canEdit,
            'menu_active' => 'quotes',
            'title' => trans('admin.quotes.show')
        ]);
    }
	
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($account, $id)
    {   
        $admin = Application::currentAccount()->where("application_id","=",$id)->first(); 
        
        if(!$admin  ) {
            abort(404);
        }
		
        
		$nationalities = Nationality::orderBy("nationality_name","asc")->get();
		
        return view('admin.applications.create-edit',[
            'record'=>$admin,
            'menu_active' => 'applications',
			'nationalities'=>$nationalities,
			'student'=>$admin->student,
            'title' => trans('admin.applications.edit')
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Images $imagesModel, $account, $id)
    { 
        $me = Auth::guard('admin')->user();
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$id)
				->where("branch_id","=",$currentBranch)->first(); 
        
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$data = $request->input("data",[]);
		
		if(!isset($data['quote_no']) || empty($data['quote_no'])) {
			$data['quote_no'] = (int)Quote::where("student_id","=",$data['student_id'])->where("quote_id","!=",$quote->quote_id)->max('quote_no');
			$data['quote_no']++;
		}
		if(!isset($data['admin_id']) || empty($data['admin_id'])) {
			$data['admin_id'] = null;
		}
		if(isset($data['due_date']) && !empty($data['due_date'])) {
			$data['due_date'] = date("Y-m-d", strtotime($data['due_date']));
		} else {
			$data['due_date'] = null;
		}
		if(isset($data['issue_date']) && !empty($data['issue_date'])) {
			$data['issue_date'] = date("Y-m-d", strtotime($data['issue_date']));
		} else {
			$data['issue_date'] = null;
		}
		if(!isset($data['currency_id']) || empty($data['currency_id'])) {
			$data['currency_id'] = null;
		}
		
		if($data['admin_id']!=$quote->admin_id) {
			if(!is_null($quote->admin_id)) {
				Notification::create([
					'receiver_admin_id'=>$quote->admin_id,
					'creator_admin_id'=>$me->id,
					'notification_type'=>'unassignedQuote',
					'related_quote_id'=>$id
				]);
			}
			if(!is_null($data['admin_id'])) {
				Notification::create([
					'receiver_admin_id'=>$data['admin_id'],
					'creator_admin_id'=>$me->id,
					'notification_type'=>'assignedQuote',
					'related_quote_id'=>$id,
					'notification_link'=>RouteHelper::route("admin.quotes.show", ["quote"=>$id])
				]);
			}
		}
		
		$quote->update($data);
		
		
		Session::flash('result', trans('admin.quotes.update_success_text'));
        return 'reloadState();';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Images $imagesModel, $account, $id)
    {
        $admin = Quote::currentAccount()->where("quote_id","=",$id)->first(); 
        if($admin) { 
			if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $admin->admin_id == Auth::guard('admin')->user()->id) ) {
				 return 'alert("'.trans('admin.no_action_privilege').'")';
			}
			
			$admin->delete();
			
			Session::flash('result', trans('admin.quotes.delete_success_text')); //<--FLASH MESSAGE
		}
		 
        return 'closeModal();reloadState();';
    }
	
	public function findCourses(Request $request, $account, $studentid=null) {
		if(!GeneralHelper::checkPrivilege("quotes.create")) {
			return view('admin.unauthorized');
		} 
		$course_types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
		$student = false;
		
		if(!is_null($student)) {
			$student = Student::currentAccount()->where("student_id","=",$studentid)->first(); 
		}
		
		$quote_id = $request->input('quote',false);
		$quote = false; 
		if($quote_id) {
			$quote = Quote::find($quote_id);
		}
		
		return view('admin.quotes.find-courses',[
            'menu_active' => 'quotes',
			'languages'=>$languages,
			'course_types'=>$course_types,
			'student'=>$student,
			'quote'=>$quote,
            'title' => trans('admin.quotes.find-courses')
        ]);
	}
	
	public function findCoursesResults(Request $request, $account) {
		
		$courses = \App\Models\PartnerCampusCourse::whereHas("campus", function($q) {
			$q->whereHas("partner",function($q1) {
				$q1->currentAccount();
			});
		});
		
		
		$search_type = $request->input('search_type','location');
		
		if($search_type=='location') {
			$data = $request->input('data',[]);
		} else {
			$data = $request->input('data2',[]);
		}
		//lokasyonlar
		if(isset($data['location']) && is_array($data['location']) && count($data['location'])>0) {
			$courses->whereHas("campus", function($q) use ($data) {
				$q->where(function($q1) use ($data) {
					foreach($data['location'] as $k=>$loc) {
						$func = 'orWhereHas';
						if($k == 0) 
							$func = 'whereHas';

						$q1->{$func}("place",function($q3) use ($loc) {
							$q3->where(function($q4) use ($loc) {
								$q4->where("place_id","=",$loc)
										->orWhere("parent_id","=",$loc)
										->orWhere("parent2_id","=",$loc);
							});
						});
					}
				});
			});
		}
		
		//okullar
		if(isset($data['partner']) && is_array($data['partner']) && count($data['partner'])>0) {
			$courses->whereHas("campus", function($q) use ($data) {
				$q->where(function($q1) use ($data) {
					foreach($data['partner'] as $k=>$part) {
						$func = 'orWhere';
						if($k == 0) 
							$func = 'where';
						
						$q1->{$func}("partner_id","=",$part);
					}
				});
			});
		}
		if(isset($data['course_type']) && is_array($data['course_type']) && count($data['course_type'])>0) {
			$courses->where(function($q1) use ($data) {
				foreach($data['course_type'] as $k=>$part) {
					$func = 'orWhere';
					if($k == 0) 
						$func = 'where';

					$q1->{$func}("type_id","=",$part);
				}
			});
		}
		if(isset($data['intensity_start']) && $data['intensity_start']!="" ) {
			$courses->where("course_intensity",">=",$data['intensity_start']);
		}
		if(isset($data['intensity_end']) && $data['intensity_end']!="" ) {
			$courses->where("course_intensity","<=",$data['intensity_end']);
		}
		if(isset($data['language']) && $data['language']!="" ) {
			$courses->where("language_id","=",$data['language']);
		}
		if(!isset($data['duration_amount']) || empty($data['duration_amount'])) {
			//$data['duration_amount'] = 4;
		}
	 
		if(!empty($data['duration_amount'])) {
			$results = $courses->select()->addSelect(\DB::Raw("calculateCoursePrice(partners_campuses_courses.course_id, ".$data['duration_amount'].", '".$data['duration_type']."') as price, NULL AS tmp_duration, NULL AS tmp_duration_unit"))
				->groupBy("course_id")->havingRaw(\DB::Raw("price IS NOT NULL"))->with(['type'])->get();
		} else {
			$results = $courses->has("prices")->select()->addSelect(\DB::Raw(
					"@calculation_tmp := getCourseMinDuration(partners_campuses_courses.course_id),"
					. "CAST( SPLIT_STR (@calculation_tmp, '|', 1) as UNSIGNED ) AS tmp_duration,"
					. "SPLIT_STR (@calculation_tmp, '|', 2) AS tmp_duration_unit,"
					. "calculateCoursePrice(partners_campuses_courses.course_id, CAST( SPLIT_STR (@calculation_tmp, '|', 1) as UNSIGNED ), SPLIT_STR (@calculation_tmp, '|', 2) ) as price"))
				->groupBy("course_id")->get();//
			
	 
		}
		$course_types = collect();
		$partners = collect();
		$locations = collect();
		
		$intensity_min = 0;
		$intensity_max = 0;
		
		$results->each(function($course) use(&$course_types, &$partners, &$locations, &$intensity_min, &$intensity_max) {
			$course_types->push($course->type);
			$partners->push($course->campus->partner); 
			if($course->campus && $course->campus->place) {
			$locations->push($course->campus->place);
			}
			
			if(!is_null($course->course_intensity>0) && !empty($course->course_intensity>0)) {
				if($intensity_min>$course->course_intensity) {
					$intensity_min = $course->course_intensity;
				}
				if($intensity_max<$course->course_intensity) {
					$intensity_max = $course->course_intensity;
				}
			}
		});
		
		$course_types = $course_types->unique(); 
		$partners = $partners->unique(); 
		$locations = $locations->unique(); 
		
		
		$rates = $this->getRates($account);
		
		return view('admin.quotes.find-courses-result',[
            'menu_active' => 'quotes',
			'records'=>$results,
			'course_types'=>$course_types,
			'partners'=>$partners,
			'locations'=>$locations,
			'duration'=>$data['duration_amount'],
			'duration_type'=>$data['duration_type'],
			'intensity_min'=>$intensity_min,
			'intensity_max'=>$intensity_max,
			'rates'=>$rates
        ]);
	}
	
	public function addToQuote(Request $request, $account)
    {   
        return view('admin.quotes.add-to-quote',[  
        ]);
    }
	public function storeToQuote(Request $request, $account)
    {   
        
		$quote_id = $request->input('quote_id',false);
		
		return 'closeModal();$(\'<input type="hidden" name="quote" class="preventDelete" value="'.$quote_id.'" />\').appendTo("#creatorform");$("#new_quote").click();';
    }
	public function optionsSort(Request $request) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
        foreach($items as $order=>$prop_id) {
            $group = QuoteOption::find($prop_id);
            if($group) {
                $group->option_order = $order;
                $group->save();
            }
        }
        return "";
    }
	
	
	public function optionsDestroy(Images $imagesModel, $account, $quote_id, $option_id)
    {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			 return 'alert("'.trans('admin.no_action_privilege').'")';
		} 
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
        $option->delete();
			
		Session::flash('result', trans('admin.quotes.options_delete_success_text')); //<--FLASH MESSAGE
        
		
		
        return 'reloadState();';
    }
	public function optionsEdit($account, $quote_id, $option_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		//->where("branch_id","=",$currentBranch)
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		 
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			 
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-edit',[
            'record'=>$option,   
            'title' => trans('admin.quotes.edit')
        ]);
    }
	public function optionsDuplicate($account, $quote_id, $option_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$ord = $option->quote->options()->count() + 1;
		
		$newoptionname = trans("admin.quotes.option")." ".$ord;
		
		$newoption = $option->replicate();
		$newoption->push();
		$newoption->option_name = $newoptionname;
		$newoption->save();
		
		foreach($option->courses as $course) {
			$newcourse = $course->replicate();
			$newcourse->push();
			$newcourse->option_id = $newoption->option_id;
			$newcourse->save();
		}
        foreach($option->accommodations as $course) {
			$newcourse = $course->replicate();
			$newcourse->push();
			$newcourse->option_id = $newoption->option_id;
			$newcourse->save();
		}
		foreach($option->services as $course) {
			$newcourse = $course->replicate();
			$newcourse->push();
			$newcourse->option_id = $newoption->option_id;
			$newcourse->save();
		}
		return 'reloadState();';
    }
	public function optionsAddCourse($account, $quote_id, $option_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			 return view('admin.unauthorized');
		} 
		
        return view('admin.quotes.options-add-course',[
            'option'=>$option,   
            'title' => trans('admin.quotes.add-course'),
			'currencies'=>$currencies,
			'coursetypes'=>$types,
			'languages'=>$languages
        ]);
    }
	
	public function optionsStoreCourse(Request $request, $account, $quote_id, $option_id)
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$newItem = false;
		
		if($type==0) {
			$newItem = $this->optionsStoreCourseAuto($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsStoreCourseManual($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		
 
		$extraoptions = $request->input('extraoptions',[]);
		foreach($extraoptions as $extra) {
			if($type==0) {
				$newItem = $this->optionsStoreCourseAuto($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
			if($type==1) {
				$newItem = $this->optionsStoreCourseManual($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
		} 
		
		$option->fixPromotions();
		$option->fixFees();
		
		
		
		Session::flash('result', trans('admin.quotes.options_course_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsStoreCourseAuto(Request $request, $account, $quote_id, $option_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		$courseId = isset($data['course'])?$data['course']:false;
		$dbCourse = PartnerCampusCourse::find($courseId);
		if(!$dbCourse) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		
		
		$curr = $dbCourse->campus->getCurrency();
			 
		$ctype = null;
		$clang = null;

		if($dbCourse->type) {
			$ctype = $dbCourse->type->type_name;
		}
		if($dbCourse->language) {
			$clang = $dbCourse->language->language_name;
		}
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}

		$newItem = QuoteOptionCourse::create([
			'option_id'=>$option->option_id,
			'course_id'=>$dbCourse->course_id,
			'option_course_name'=>$dbCourse->course_name,
			'option_course_partner'=>$dbCourse->campus->partner->partner_name,
			'option_course_campus'=>$dbCourse->campus->campus_name,
			'option_course_duration'=>$duration,
			'option_course_duration_unit'=>$durationUnit,
			'option_course_price'=>$price,
			'option_course_gross_price'=>$price,
			'option_course_price_type'=>'total',
			'option_course_course_type'=>$ctype,
			'option_course_intensity'=>$dbCourse->course_intensity,
			'option_course_course_language'=>$clang,
			'option_currency_code'=>$curr->currency_code,
			'option_course_description'=>$dbCourse->course_description,
			'currency_id'=>$curr->currency_id,
			'place_id'=>$dbCourse->campus->place_id,
			'campus_id'=>$dbCourse->campus_id,
			'option_course_start_date'=>$startDate
		]);
		
		$option->fixPromotions();
		$option->fixFees();
		
		return $newItem;
	}
	
	public function optionsStoreCourseManual(Request $request, $account, $quote_id, $option_id) {
		 
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		$campus = PartnerCampus::find($data['campus']);
		
		
		$curr = $campus->getCurrency();
 
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		}

		if(isset($data2['course_language']) && empty($data2['course_language'])) {
			$data2['course_language'] = null;
		}
		
		$newItem = QuoteOptionCourse::create([
			'option_id'=>$option->option_id,
			'option_course_name'=>$data2['course_name'],
			'option_course_partner'=>$campus->partner->partner_name,
			'option_course_campus'=>$campus->campus_name,
			'option_course_duration'=>$duration,
			'option_course_duration_unit'=>$durationUnit,
			'option_course_price'=>$price,
			'option_course_gross_price'=>$price,
			'option_course_price_type'=>'total',
			'option_course_intensity'=>$data2['course_intensity'],
			'option_course_course_type'=>$data2['course_type'],
			'option_course_course_language'=>$data2['course_language'],
			'option_currency_code'=>$curr->currency_code,
			'option_course_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'place_id'=>$campus->place_id,
			'campus_id'=>$campus->campus_id,
			'option_course_start_date'=>$startDate
		]);
		 
		return $newItem;
	}
	
	public function optionsEditCourse($account, $quote_id, $option_id, $option_course_id)
    {   
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$admin = $option->courses()->where("option_course_id","=",$option_course_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-add-course',[
			'record'=>$admin,
            'option'=>$option,   
            'title' => trans('admin.quotes.add-course'),
			'currencies'=>$currencies,
			'coursetypes'=>$types,
			'languages'=>$languages
        ]);
    }
	
	public function optionsUpdateCourse(Request $request, $account, $quote_id, $option_id, $option_course_id )
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$newItem=false;
		if($type==0) {
			$newItem = $this->optionsUpdateCourseAuto($request, $account, $quote_id, $option_id, $option_course_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsUpdateCourseManual($request, $account, $quote_id, $option_id, $option_course_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		
		$option->fixPromotions();
		$option->fixFees();
		
		
		
		Session::flash('result', trans('admin.quotes.options_course_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsUpdateCourseAuto(Request $request, $account, $quote_id, $option_id, $option_course_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$optionCourse = $option->courses()->where("option_course_id","=",$option_course_id)->first(); 
		if(!$option) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
		} 
		
		
		$data = $request->input('data',false);
		
		$courseId = isset($data['course'])?$data['course']:false;
		$dbCourse = PartnerCampusCourse::find($courseId);
		if(!$dbCourse) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		
		
		$curr = $dbCourse->campus->getCurrency();
			 
		$ctype = null;
		$clang = null;

		if($dbCourse->type) {
			$ctype = $dbCourse->type->type_name;
		}
		if($dbCourse->language) {
			$clang = $dbCourse->language->language_name;
		}
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}

		$optionCourse->update([
			'option_id'=>$option->option_id,
			'course_id'=>$dbCourse->course_id,
			'option_course_name'=>$dbCourse->course_name,
			'option_course_partner'=>$dbCourse->campus->partner->partner_name,
			'option_course_campus'=>$dbCourse->campus->campus_name,
			'option_course_duration'=>$duration,
			'option_course_duration_unit'=>$durationUnit,
			'option_course_price'=>$price,
			'option_course_gross_price'=>$price,
			'option_course_price_type'=>'total',
			'option_course_intensity'=>$dbCourse->course_intensity,
			'option_course_course_type'=>$ctype,
			'option_course_course_language'=>$clang,
			'option_currency_code'=>$curr->currency_code,
			'option_course_description'=>$dbCourse->course_description,
			'currency_id'=>$curr->currency_id,
			'place_id'=>$dbCourse->campus->place_id,
			'campus_id'=>$dbCourse->campus_id,
			'option_course_start_date'=>$startDate
		]); 
		
		$option->fixPromotions();
		$option->fixFees();
		
		return $optionCourse;
	}
	public function optionsUpdateCourseManual(Request $request, $account, $quote_id, $option_id, $option_course_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$optionCourse = $option->courses()->where("option_course_id","=",$option_course_id)->first(); 
		if(!$option) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
		} 
		
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		$campus = PartnerCampus::find($data['campus']);
		
		$curr = $campus->getCurrency();
		
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		}

		if(isset($data2['course_language']) && empty($data2['course_language'])) {
			$data2['course_language'] = null;
		}

		$optionCourse->update([
			'option_course_name'=>$data2['course_name'],
			'option_course_partner'=>$campus->partner->partner_name,
			'option_course_campus'=>$campus->campus_name,
			'option_course_duration'=>$duration,
			'option_course_duration_unit'=>$durationUnit,
			'option_course_price'=>$price,
			'option_course_gross_price'=>$price,
			'option_course_price_type'=>'total',
			'option_course_intensity'=>$data2['course_intensity'],
			'option_course_course_type'=>$data2['course_type'],
			'option_course_course_language'=>$data2['course_language'],
			'option_currency_code'=>$curr->currency_code,
			'option_course_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'place_id'=>$campus->place_id,
			'campus_id'=>$campus->campus_id,
			'option_course_start_date'=>$startDate
		]); 
		
		return $optionCourse;
	}
	public function optionsDestroyCourse(Images $imagesModel, $account, $quote_id, $option_id, $course_option_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->courses()->where("option_course_id","=",$course_option_id)->first(); 
        if($admin) { 
			if($admin) {
				LogHelper::logEvent('deleting', $admin);
			}
			$admin->delete(); 
			Session::flash('result', trans('admin.quotes.options_course_delete_success_text'));
		}
		 
		$option->fixPromotions();
		$option->fixFees();
		
        return 'reloadState();';
    }
	
	public function optionsAddAccommodation($account, $quote_id, $option_id)
    {   
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		}
		
		$firstCourse = false;
		
		if($option->courses->count()>0) {
			$firstCourse =  $option->courses->first();
		}
		
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$accommodation_types = AccommodationType::orderBy('type_order','asc')->get();
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			 
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-add-accommodation',[
            'option'=>$option,   
            'title' => trans('admin.quotes.add-accommodation'),
			'currencies'=>$currencies,
			'accommodation_types'=>$accommodation_types,
			'firstCourse'=>$firstCourse
        ]);
    }
	
	public function optionsStoreAccommodation(Request $request, $account, $quote_id, $option_id)
    {  
		$type = $request->input('type',0);
		
		$quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$newItem = false;
		if($type==0) {
			$newItem = $this->optionsStoreAccommodationAuto($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsStoreAccommodationManual($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		
		$extraoptions = $request->input('extraoptions',[]);
		foreach($extraoptions as $extra) {
			if($type==0) {
				$newItem = $this->optionsStoreAccommodationAuto($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
			if($type==1) {
				$newItem = $this->optionsStoreAccommodationManual($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
		}
		
		$option->fixPromotions();
		$option->fixFees();
		
		Session::flash('result', trans('admin.quotes.options_accommodation_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsStoreAccommodationAuto(Request $request, $account, $quote_id, $option_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		$accommodationId = isset($data['accommodation'])?$data['accommodation']:false;
		$dbAccommodation = PartnerCampusAccommodation::find($accommodationId);
		if(!$dbAccommodation) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		
		
		$curr = $dbAccommodation->campus->getCurrency();
			 
		$ctype = null; 

		if($dbAccommodation->type) {
			$ctype = $dbAccommodation->type->type_name;
		} 
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}

		$newItem = QuoteOptionAccommodation::create([
			'option_id'=>$option->option_id,
			'accommodation_id'=>$dbAccommodation->accommodation_id,
			
			'option_accommodation_name'=>$dbAccommodation->accommodation_name,
			'option_accommodation_partner'=>$dbAccommodation->campus->partner->partner_name,
			'option_accommodation_campus'=>$dbAccommodation->campus->campus_name,
			'option_accommodation_start_date'=>$startDate,
			'option_accommodation_duration'=>$duration,
			'option_accommodation_duration_unit'=>$durationUnit,
			'option_accommodation_price'=>$price,
			'option_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'option_currency_code'=>$curr->currency_code,
			'option_accommodation_description'=>$dbAccommodation->option_accommodation_description,
			'type_id'=>$dbAccommodation->type_id,
			'option_accommodation_type'=>$ctype,
		]);
		
		$option->fixPromotions();
		$option->fixFees();
		
		return $newItem;
	}
	
	public function optionsStoreAccommodationManual(Request $request, $account, $quote_id, $option_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $option->courses()->first();
		
		$curr = $first->campus->getCurrency();
		
		$ctype=null;
		
		$type = AccommodationType::find($data2['accommodation_type']);
		if($type) {
			$ctype = $type->type_name;
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$newItem = QuoteOptionAccommodation::create([
			'option_id'=>$option->option_id,
			'accommodation_id'=>null,
			
			'option_accommodation_name'=>$data2['accommodation_name'],
			'option_accommodation_partner'=>null,
			'option_accommodation_campus'=>null,
			'option_accommodation_start_date'=>$startDate,
			'option_accommodation_duration'=>$duration,
			'option_accommodation_duration_unit'=>$durationUnit,
			'option_accommodation_price'=>$price,
			'option_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'option_currency_code'=>$curr->currency_code,
			'option_accommodation_description'=>$data2['description'],
			'type_id'=>$data2['accommodation_type'],
			'option_accommodation_type'=>$ctype,
		]);
		
		
		
		return $newItem;
	}
	
	public function optionsEditAccommodation($account, $quote_id, $option_id, $option_accommodation_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$admin = $option->accommodations()->where("option_accommodation_id","=",$option_accommodation_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$accommodation_types = AccommodationType::orderBy('type_order','asc')->get();
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-add-accommodation',[
			'record'=>$admin,
            'option'=>$option,   
            'title' => trans('admin.quotes.edit-accommodation'),
			'currencies'=>$currencies,
			'accommodation_types'=>$accommodation_types,
			'firstCourse'=>false
        ]);
    }
	
	public function optionsUpdateAccommodation(Request $request, $account, $quote_id, $option_id, $option_accommodation_id )
    {  
		$type = $request->input('type',0);
		
		$quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$newItem = false;
		if($type==0) {
			$newItem = $this->optionsUpdateAccommodationAuto($request, $account, $quote_id, $option_id, $option_accommodation_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsUpdateAccommodationManual($request, $account, $quote_id, $option_id, $option_accommodation_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		
		$option->fixPromotions();
		$option->fixFees();
		
		Session::flash('result', trans('admin.quotes.options_accommodation_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	
	public function optionsUpdateAccommodationAuto(Request $request, $account, $quote_id, $option_id, $option_accommodation_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$dbOptionAccommodation = $option->accommodations()->where("option_accommodation_id", "=", $option_accommodation_id)->first();
		if(!$dbOptionAccommodation) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		$accommodationId = isset($data['accommodation'])?$data['accommodation']:false;
		$dbAccommodation = PartnerCampusAccommodation::find($accommodationId);
		if(!$dbAccommodation) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		
		
		$curr = $dbAccommodation->campus->getCurrency();
			 
		$ctype = null; 

		if($dbAccommodation->type) {
			$ctype = $dbAccommodation->type->type_name;
		} 
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}

		$dbOptionAccommodation->update([
			'accommodation_id'=>$dbAccommodation->accommodation_id,
			
			'option_accommodation_name'=>$dbAccommodation->accommodation_name,
			'option_accommodation_partner'=>$dbAccommodation->campus->partner->partner_name,
			'option_accommodation_campus'=>$dbAccommodation->campus->campus_name,
			'option_accommodation_start_date'=>$startDate,
			'option_accommodation_duration'=>$duration,
			'option_accommodation_duration_unit'=>$durationUnit,
			'option_accommodation_price'=>$price,
			'option_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'option_currency_code'=>$curr->currency_code,
			'option_accommodation_description'=>$dbAccommodation->option_accommodation_description,
			'type_id'=>$dbAccommodation->type_id,
			'option_accommodation_type'=>$ctype,
		]); 
		
		$option->fixPromotions();
		$option->fixFees();
		
		return $dbOptionAccommodation;
	}
	
	public function optionsUpdateAccommodationManual(Request $request, $account, $quote_id, $option_id, $option_accommodation_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$dbOptionAccommodation = $option->accommodations()->where("option_accommodation_id", "=", $option_accommodation_id)->first();
		if(!$dbOptionAccommodation) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
 
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $option->courses()->first();
		
		$curr = $first->campus->getCurrency();
		
		$ctype=null;
		
		$type = AccommodationType::find($data2['accommodation_type']);
		if($type) {
			$ctype = $type->type_name;
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$dbOptionAccommodation->update([  
			
			'option_accommodation_name'=>$data2['accommodation_name'],
			'option_accommodation_partner'=>null,
			'option_accommodation_campus'=>null,
			'option_accommodation_start_date'=>$startDate,
			'option_accommodation_duration'=>$duration,
			'option_accommodation_duration_unit'=>$durationUnit,
			'option_accommodation_price'=>$price,
			'option_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'option_currency_code'=>$curr->currency_code,
			'option_accommodation_description'=>$data2['description'],
			'type_id'=>$data2['accommodation_type'],
			'option_accommodation_type'=>$ctype, 
		]);
		
		
		return $dbOptionAccommodation;
	}
	
	public function optionsDestroyAccommodation(Images $imagesModel, $account, $quote_id, $option_id, $option_accommodation_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
			
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->accommodations()->where("option_accommodation_id","=",$option_accommodation_id)->first(); 
        if($admin) { 
			if($admin) {
				LogHelper::logEvent('deleting', $admin);
			}
			$admin->delete();
			
			$option->fixPromotions();
			$option->fixFees();
			
			Session::flash('result', trans('admin.quotes.options_accommodation_delete_success_text'));
		}
        return 'reloadState();';
    }
	public function optionsAddService($account, $quote_id, $option_id)
    {   
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		$firstCourse = false;
		
		if($option->courses->count()>0) {
			$firstCourse =  $option->courses->first();
		}
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			
		} else {
			return view('admin.unauthorized');
		}
		
		return view('admin.quotes.options-add-service-multi',[
				'option'=>$option,   
				'title' => trans('admin.quotes.add-service'),
				'firstCourse'=>$firstCourse,
				'currencies'=>$currencies
			]);	
		
        return view('admin.quotes.options-add-service',[
            'option'=>$option,   
            'title' => trans('admin.quotes.add-service'),
			'currencies'=>$currencies,
			'firstCourse'=>$firstCourse
        ]);
    }
	
	public function optionsStoreService(Request $request, $account, $quote_id, $option_id)
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		} 
		$newItem = false;
		if($type==0) {
			$newItem = $this->optionsStoreServiceAuto($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsStoreServiceManual($request, $account, $quote_id, $option_id);
			if($newItem) {
				LogHelper::logEvent('created', $newItem);
			}
		}
		
		$extraoptions = $request->input('extraoptions',[]);
		foreach($extraoptions as $extra) {
			if($type==0) {
				$newItem = $this->optionsStoreServiceAuto($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
			if($type==1) {
				$newItem = $this->optionsStoreServiceManual($request, $account, $quote_id, $extra);
				if($newItem) {
					LogHelper::logEvent('created', $newItem);
				}
			}
		}
		
		$option->fixPromotions();
		$option->fixFees(); 
			
		Session::flash('result', trans('admin.quotes.options_service_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsStoreServiceAuto(Request $request, $account, $quote_id, $option_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		if(isset($data['services']) && is_array($data['services'])) {
			foreach($data['services'] as $tmp) {
				$serviceId = isset($tmp['service'])?$tmp['service']:false;
				$dbService = PartnerCampusService::find($serviceId);
				if(!$dbService) {
					continue;
				}

				$duration = ( isset($tmp['duration']) && !empty($tmp['duration']) )?$tmp['duration']:null;
				$durationUnit = ( isset($tmp['duration_unit']) && !empty($tmp['duration_unit']) )?$tmp['duration_unit']:null;
				$price = isset($tmp['price'])?$tmp['price']:0;


				$curr = $dbService->campus->getCurrency();  

				$startDate = null;

				if(isset($tmp['start_date']) && !empty($tmp['start_date'])) {
					$startDate = date("Y-m-d", strtotime($tmp['start_date']));
				}

				$quantity = 1;
				if(isset($tmp['quantity']) && !empty($tmp['quantity'])) {
					$quantity = $tmp['quantity'];
				}

				$newItem = QuoteOptionService::create([
					'option_id'=>$option->option_id,
					'service_id'=>$dbService->service_id,

					'option_service_name'=>$dbService->service_name,
					'option_service_partner'=>$dbService->campus->partner->partner_name,
					'option_service_campus'=>$dbService->campus->campus_name,
					'option_service_start_date'=>$startDate,
					'option_service_duration'=>$duration,
					'option_service_duration_unit'=>$durationUnit,
					'option_service_price'=>$price,
					'option_service_gross_price'=>$price,
					'option_service_price_type'=>'total',
					'option_service_description'=>$dbService->service_description,
					'option_service_quantity'=>$quantity,
					'option_currency_code'=>$curr->currency_code,
					'currency_id'=>$curr->currency_id 
				]);		
			}
		} else {
			$serviceId = isset($data['service'])?$data['service']:false;
			$dbService = PartnerCampusService::find($serviceId);
			if(!$dbService) {
				return 'alert("'.trans('admin.quotes.save_error').'");';
			}

			$duration = ( isset($data['duration']) && !empty($data['duration']) )?$data['duration']:null;
			$durationUnit = ( isset($data['duration_unit']) && !empty($data['duration_unit']) )?$data['duration_unit']:null;
			$price = isset($data['price'])?$data['price']:0;


			$curr = $dbService->campus->getCurrency();  

			$startDate = null;

			if(isset($data['start_date']) && !empty($data['start_date'])) {
				$startDate = date("Y-m-d", strtotime($data['start_date']));
			}

			$quantity = 1;
			if(isset($data['quantity']) && !empty($data['quantity'])) {
				$quantity = $data['quantity'];
			}

			$newItem = QuoteOptionService::create([
				'option_id'=>$option->option_id,
				'service_id'=>$dbService->service_id,

				'option_service_name'=>$dbService->service_name,
				'option_service_partner'=>$dbService->campus->partner->partner_name,
				'option_service_campus'=>$dbService->campus->campus_name,
				'option_service_start_date'=>$startDate,
				'option_service_duration'=>$duration,
				'option_service_duration_unit'=>$durationUnit,
				'option_service_price'=>$price,
				'option_service_gross_price'=>$price,
				'option_service_price_type'=>'total',
				'option_service_description'=>$dbService->service_description,
				'option_service_quantity'=>$quantity,
				'option_currency_code'=>$curr->currency_code,
				'currency_id'=>$curr->currency_id 
			]);	
		}
		
		
		
		return $newItem;
	}
	
	public function optionsStoreServiceManual(Request $request, $account, $quote_id, $option_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = ( isset($data2['duration']) && !empty($data2['duration']) )?$data2['duration']:null;
		$durationUnit = ( isset($data2['duration_unit']) && !empty($data2['duration_unit']) )?$data2['duration_unit']:null;
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $option->courses()->first();
		
		$curr = $first->campus->getCurrency();
		
		if(isset($data2['currency_id'])) {
			$curr = Currency::find($data2['currency_id']);
		}
		
		$quantity = 1;
		if(isset($data2['quantity']) && !empty($data2['quantity'])) {
			$quantity = $data2['quantity'];
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$newItem = QuoteOptionService::create([
			
			'option_id'=>$option->option_id,
			'option_service_name'=>$data2['service_name'],
			'option_service_partner'=>null,
			'option_service_campus'=>null,
			'option_service_start_date'=>$startDate,
			'option_service_duration'=>$duration,
			'option_service_duration_unit'=>$durationUnit,
			'option_service_price'=>$price,
			'option_service_gross_price'=>$price,
			'option_service_price_type'=>'total',
			'option_service_quantity'=>$quantity,
			'option_currency_code'=>$curr->currency_code,
			'option_service_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			
		]);
		
		return $newItem;
	}
	
	public function optionsEditService($account, $quote_id, $option_id, $option_service_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		$admin = $option->services()->where("option_service_id","=",$option_service_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get(); 
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-add-service',[
			'record'=>$admin,
            'option'=>$option,   
            'title' => trans('admin.quotes.edit-service'),
			'currencies'=>$currencies,
			'firstCourse'=>false
        ]);
    }
	
	public function optionsUpdateService(Request $request, $account, $quote_id, $option_id, $option_service_id )
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		if($type==0) {
			$newItem = $this->optionsUpdateServiceAuto($request, $account, $quote_id, $option_id, $option_service_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		if($type==1) {
			$newItem = $this->optionsUpdateServiceManual($request, $account, $quote_id, $option_id, $option_service_id);
			if($newItem) {
				LogHelper::logEvent('updated', $newItem);
			}
		}
		$option->fixPromotions();
		$option->fixFees();
		
		
		
		Session::flash('result', trans('admin.quotes.options_service_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	
	public function optionsUpdateServiceAuto(Request $request, $account, $quote_id, $option_id, $option_service_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$dbOptionService = $option->services()->where("option_service_id", "=", $option_service_id)->first();
		if(!$dbOptionService) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		$serviceId = isset($data['service'])?$data['service']:false;
		$dbService = PartnerCampusService::find($serviceId);
		if(!$dbService) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$duration = ( isset($data['duration']) && !empty($data['duration']) )?$data['duration']:null;
		$durationUnit = ( isset($data['duration_unit']) && !empty($data['duration_unit']) )?$data['duration_unit']:null;
		$price = isset($data['price'])?$data['price']:0;
		
		
		$curr = $dbService->campus->getCurrency();  
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}
		
		$quantity = 1;
		if(isset($data['quantity']) && !empty($data['quantity'])) {
			$quantity = $data['quantity'];
		}

		$dbOptionService->update([
			'option_id'=>$option->option_id,
			'service_id'=>$dbService->service_id,
			 
			'option_service_name'=>$dbService->service_name,
			'option_service_partner'=>$dbService->campus->partner->partner_name,
			'option_service_campus'=>$dbService->campus->campus_name,
			'option_service_start_date'=>$startDate,
			'option_service_duration'=>$duration,
			'option_service_duration_unit'=>$durationUnit,
			'option_service_price'=>$price,
			'option_service_gross_price'=>$price,
			'option_service_description'=>$dbService->service_description,
			'option_service_price_type'=>'total',
			'option_service_quantity'=>$quantity,
			'option_currency_code'=>$curr->currency_code,
			'currency_id'=>$curr->currency_id 
		]); 
		
		$option->fixPromotions();
		$option->fixFees();
		
		return $dbOptionService;
	}
	
	public function optionsUpdateServiceManual(Request $request, $account, $quote_id, $option_id, $option_service_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$dbOptionService = $option->services()->where("option_service_id", "=", $option_service_id)->first();
		if(!$dbOptionService) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = ( isset($data2['duration']) && !empty($data2['duration']) )?$data2['duration']:null;
		$durationUnit = ( isset($data2['duration_unit']) && !empty($data2['duration_unit']) )?$data2['duration_unit']:null;
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $option->courses()->first();
		
		$curr = $first->campus->getCurrency();
		
		if(isset($data2['currency_id'])) {
			$curr = Currency::find($data2['currency_id']);
		}
		
		$quantity = 1;
		if(isset($data2['quantity']) && !empty($data2['quantity'])) {
			$quantity = $data2['quantity'];
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$dbOptionService->update([
			
			'option_id'=>$option->option_id,
			 
			'option_service_name'=>$data2['service_name'],
			'option_service_partner'=>null,
			'option_service_campus'=>null,
			'option_service_start_date'=>$startDate,
			'option_service_duration'=>$duration,
			'option_service_duration_unit'=>$durationUnit,
			'option_service_price'=>$price,
			'option_service_gross_price'=>$price,
			'option_service_price_type'=>'total',
			'option_service_quantity'=>$quantity,
			'option_currency_code'=>$curr->currency_code,
			'option_service_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			
		]);
		
		return $dbOptionService;
	}
	
	public function optionsDestroyService(Images $imagesModel, $account, $quote_id, $option_id, $option_service_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->services()->where("option_service_id","=",$option_service_id)->first(); 
        if($admin) { 
			if($admin) {
				LogHelper::logEvent('deleting', $admin);
			}
			$admin->delete(); 
			Session::flash('result', trans('admin.quotes.options_service_delete_success_text'));
			$option->fixPromotions();
			$option->fixFees();
		}
        return 'reloadState();';
    }
	public function optionsDestroyAutoPromotion(Images $imagesModel, $account, $quote_id, $option_id, $option_promotion_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) { 
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->promotions()->where("option_promotion_id","=",$option_promotion_id)->first(); 
        if($admin) { 
			if($admin->course) {
				$admin->course->option_course_price = $admin->course->option_course_gross_price;
				$admin->course->save();
			}
			
			if($admin->service) {
				$admin->service->option_service_price = $admin->service->option_service_gross_price;
				$admin->service->save();
			}
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			$relaid = $admin->option_course_id;
			if(is_null($relaid) || $relaid == "") {
				$relaid = "0";
			}
			
			Session::flash('result', trans('admin.quotes.options_auto_promotion_delete_success_text'));
			$option->addBlockedPromotion($relaid, $admin->promotion_id);
			
			$option->fixPromotions();
			$option->fixFees();
		}
		
		
		
        return 'reloadState();';
    }
	public function optionsDestroyFee(Images $imagesModel, $account, $quote_id, $option_id, $option_service_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->services()->where("option_service_id","=",$option_service_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			Session::flash('result', trans('admin.quotes.options_fee_delete_success_text'));
			$option->addBlockedFee($admin->service_id);
			$option->fixPromotions();
			$option->fixFees();
		}
		
		
		
        return 'reloadState();';
    }
	public function changeStartDates($account, $quote_id) {
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return view('admin.unauthorized');
		} 
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return abort(404);
        } 
		
		return view('admin.quotes.change-start-dates',[
			'record'=>$quote
        ]);
	}
	public function setStartDates(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        } 
		
		$due_date = $request->input('due_date',false);
		
		$due_date = date("Y-m-d", strtotime($due_date));
		
		$courses = $request->input('courses',false);
		$accommodations = $request->input('accommodations',false);
		$services = $request->input('services',false);
		
		$quote->due_date = $due_date;
		
		foreach($quote->options as $option) {
			if($courses) {
				foreach($option->courses as $course) {
					$course->option_course_start_date = $due_date;
					$course->save();
				}
			}
			if($accommodations) {
				foreach($option->accommodations as $accommodation) {
					$accommodation->option_accommodation_start_date = $due_date;
					$accommodation->save();
				}
			}
			if($services) {
				foreach($option->services as $service) {
					$service->option_service_start_date = $due_date;
					$service->save();
				}
			}
		}
		
		Session::flash('result', trans('admin.quotes.change_start_dates_success'));
		
        return 'closeModal(); reloadState();';
	}
	public function sendQuoteForm($account, $quote_id) {
		if(!GeneralHelper::checkPrivilege("quotes.send")) {
			return view('admin.unauthorized');
		}
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return abort(404);
        }
		
		if(!$quote->student) {
            return abort(404);
		}
		
		return view('admin.quotes.quote-send',[
			'record'=>$quote
        ]);
	}
	
	public function sendQuote(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!$quote->student) {
			return 'alert("'.trans('admin.quotes.student_not_selected').'")';
		}
		
		
		
		$emails = $request->input('emails',[]);
		$subject = $request->input('subject','');
		$message = $request->input('message','');
		
		$countries = [];
		foreach($quote->options as $option) {
			foreach($option->courses as $course) {
				if($course->campus && !is_null($course->campus->country_id)) {
					$countries[] = $course->campus->country_id;
				}
			}	
		}
		
		foreach($emails as $email) { 
			//Notification::route('mail', $email)->notify(new \App\Notifications\SendQuote($quote, $subject, $message)); 
			//Notification::send(collect($email), new \App\Notifications\SendQuote($quote, $subject, $message));
			Mail::to($email)->send(new \App\Mail\SendQuote($quote, $subject, $message));
		} 
		
		
		if(count($countries)>0) {
			$country_emails = CountryEmail::whereIn("country_id",$countries)->get();
			
			foreach($emails as $email) { 
				//Notification::route('mail', $email)->notify(new \App\Notifications\SendQuote($quote, $subject, $message)); 
				//Notification::send(collect($email), new \App\Notifications\SendQuote($quote, $subject, $message));
				foreach($country_emails as $country_email) {
					Mail::to($email)->send(new \App\Mail\SendQuoteCountryEmail($quote, $country_email));
				}
			} 
			
		}
		
		
		/*
		$country_emails = CountryEmail::where("country_id","=",$quote_country)->get();
		
		
		*/
		if($quote->quote_status<2) {
			$quote->quote_status = 2;
			$quote->save();
		}
		
		Session::flash('result', trans('admin.quotes.send_quotes_success'));
		
        return 'closeModal(); reloadState();';
	}
	public function markAsIssued(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		
		$quote->quote_status = 1;
		$quote->issue_date = date("Y-m-d");
		$quote->save();
		return 'reloadState();';
	}
	public function markAsRejected(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		
		$quote->quote_status = 4; 
		$quote->save();
		return 'reloadState();';
	}
	public function revertToDraft(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$quote->quote_status = 0;
		$quote->issue_date = null;
		$quote->save();
		return 'reloadState();';
	}
	public function revertToSent(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		
		$quote->quote_status = 2; 
		$quote->selected_option_id = null;
		$quote->selected_at = null;
		$quote->save();
		return 'reloadState();';
	}
	public function viewLink(Request $request, $account, $quote_id) {
		if(!GeneralHelper::checkPrivilege("quotes.send")) {
			return view('admin.unauthorized');
		}
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		return view('admin.quotes.view-link',[
			'record'=>$quote
        ]);
	}
	public function markAsAccepted($account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return abort(404);
        }
		
		if(!$quote->student) {
            return abort(404);
		}
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		return view('admin.quotes.quote-mark-as-accepted',[
			'record'=>$quote
        ]);
	}
	public function updateAsAccepted(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$opt_id = $request->input('option',false);
		
		if($opt_id) {
			$quote->selected_option_id = $opt_id;
			$quote->selected_at = date("Y-m-d H:i:s");
			$quote->quote_status = 3;
		}
		$quote->save();
		return 'closeModal(); reloadState();';
	}
	public function selectOption($account, $quote_id, $option_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$quote->selected_option_id = $option_id;
		$quote->quote_status = 3;
		$quote->save();
		
		Session::flash('result', trans('admin.quotes.select_option_success'));
		
		return 'reloadState();';
	}
	public function unSelectOption($account, $quote_id, $option_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$quote->selected_option_id = null;
		$quote->quote_status = 2;
		$quote->save();
		
		Session::flash('result', trans('admin.quotes.unselect_option_success'));
		
		return 'reloadState();';
	}
	
	public function optionsAddPromotion($account, $quote_id, $option_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		}  
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			 return view('admin.unauthorized');
		} 
		
		$active_currency_id = false;
		
		$fc = $option->courses->first();
		
		if($fc) {
			$active_currency_id = $fc->currency_id;
		}
		
        return view('admin.quotes.options-add-promotion',[
            'option'=>$option,   
            'title' => trans('admin.quotes.add-promotion'),
			'currencies'=>$currencies,
			'active_currency_id'=>$active_currency_id
        ]);
    }
	
	public function optionsStorePromotion(Request $request, $account, $quote_id, $option_id)
    {  
		$type = $request->input('type',0);
		
		$data = $request->input('data',[]);
		
		$data['option_id'] = $option_id;
		
		$xx = QuoteOptionPromotion::create($data);
		
		LogHelper::logEvent('created', $xx);
		
		$extraoptions = $request->input('extraoptions',[]);
		foreach($extraoptions as $extra) {
			$data['option_id'] = $extra;
			$xx = QuoteOptionPromotion::create($data);
			LogHelper::logEvent('created', $xx);
		}
		
		Session::flash('result', trans('admin.quotes.options_promotion_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsEditPromotion($account, $quote_id, $option_id, $option_promotion_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		}  
		
		$promotion = $option->promotions()->where("option_promotion_id","=",$option_promotion_id)->first();
		if( !$promotion) {
			abort(404);
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			 return view('admin.unauthorized');
		} 
		
		
		
        return view('admin.quotes.options-add-promotion',[
            'option'=>$option,   
			'record'=>$promotion,
            'title' => trans('admin.quotes.edit-promotion'),
			'currencies'=>$currencies
        ]);
    }
	
	public function optionsUpdatePromotion(Request $request, $account, $quote_id, $option_id, $option_promotion_id)
    {  
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
		$data = $request->input('data',[]);
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
		}  
		
		$promotion = $option->promotions()->where("option_promotion_id","=",$option_promotion_id)->first();
		if( !$promotion) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$promotion->update($data);
		
		LogHelper::logEvent('updated', $promotion);
		
		Session::flash('result', trans('admin.quotes.options_promotion_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function optionsDestroyPromotion(Images $imagesModel, $account, $quote_id, $option_id, $option_promotion_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("quotes.others.update") && !(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) ) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$admin = $option->promotions()->where("option_promotion_id","=",$option_promotion_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			Session::flash('result', trans('admin.quotes.options_promotion_delete_success_text'));
		} 
		
        return 'reloadState();';
    }
	
	public function optionsEditNotes($account, $quote_id, $option_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
            abort(404);
		} 
		
		if(GeneralHelper::checkPrivilege("quotes.others.update")) {
			 
		} elseif(GeneralHelper::checkPrivilege("quotes.update") && $quote->admin_id == Auth::guard('admin')->user()->id) {
			
		} else {
			return view('admin.unauthorized');
		}
		
        return view('admin.quotes.options-edit-notes',[
			'record'=>$option,  
            'title' => trans('admin.quotes.edit-notes')
        ]);
    }
	
	public function optionsUpdateNotes(Request $request, $account, $quote_id, $option_id )
    {   
		$data = $request->input('data', []);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$option = $quote->options()->where("option_id","=",$option_id)->first();
		if(!$option) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		 
		$option->update($data);
		
		
		
		Session::flash('result', trans('admin.quotes.options_notes_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	public function cloneQuoteSelectStudent(Request $request,$account,$quote_id) {
		$student_id = $request->input('student', false);
		
		//$student = Student::find($student_id);
		
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$quote) {
            abort(404);
        }
		
		return view('admin.quotes.clone',[
			'quote'=>$quote
		]);
	}
	
	public function cloneQuote(Request $request,$account,$quote_id) {
		$student_id = $request->input('student', false);
		$me = Auth::guard('admin')->user();
		
		//$student = Student::find($student_id);
		
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $source = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->first();
		
		if(!$source) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$quote_no = Quote::max("quote_no"); 
		
		$dest = Quote::create([
			'admin_id'=>$me->id,
			'student_id'=>$student_id,
			'language'=>'tr',
			'quote_no'=>($quote_no + 1)
		]);
		
		
		foreach($source->options as $sourceOpt) {
			$sourceOpt->load(['courses','accommodations','services']);
			$destOpt = $sourceOpt->replicate();
			$destOpt->quote_id = $dest->quote_id;
			$destOpt->save();
			
			foreach($sourceOpt->getRelations() as $relation => $items){
				foreach($items as $item){
					$destItem = $item->replicate();
					$destItem->option_id = $destOpt->option_id;
					$destItem->save();
					
					if($relation=='courses') {
						
						foreach($item->promotions as $sourceProm) {
							
							$destProm = $sourceProm->replicate();
							$destProm->option_id = $destOpt->option_id;
							$destProm->option_course_id = $destItem->option_course_id;
							$destProm->save(); 
						}
					}
					if($relation=='services') {
						foreach($item->promotions as $sourceProm) {
							$destProm = $sourceProm->replicate();
							$destProm->option_id = $destOpt->option_id;
							$destProm->option_service_id = $destItem->option_service_id;
							$destProm->save();
						}
					}
				}
			}
			
			$manualPromotions = $sourceOpt->promotions()->where(function($q) {
				$q->whereNull("option_course_id");
				$q->orWhereNull("option_service_id");
			})->get();
			
			foreach($manualPromotions as $manualProm) {
				$destProm = $manualProm->replicate();
				$destProm->option_id = $destOpt->option_id;
				$destProm->save();
			}
		}
		
		return 'closeModal();window.location.hash="'.RouteHelper::route("admin.quotes.show", ['quote'=>$dest->quote_id], false).'";';
	}
	
	public function getRates($account,$source = false,$destination=false) {
	 
		$rates = ExchangeRate::where("id","!=",0);
		
		if($source) {
			$rates->where("source_currency","LIKE",$source);
		}
		if($destination) {
			$rates->where("destination_currency","LIKE",$destination);
		}
		
		$return = [];
		
		$all = $rates->get();
		
		foreach($all as $tek) {
			if(!isset($return[$tek->source_currency])) {
				$return[$tek->source_currency] = [];
			}
			$return[$tek->source_currency][$tek->destination_currency] = $tek->rate;
		}
		
		return $return;
	}
}
<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Nationality;
use App\Admin;
use App\Models\CourseLanguage;
use App\Models\CourseType;
use App\Models\Quote;
use App\Models\Currency;
use App\Models\QuoteOptionService;
use App\Models\PartnerCampusCourse;
use App\Models\PartnerCampusAccommodation;
use App\Models\PartnerCampusService;
use App\Models\PartnerCampus;
use App\Models\Partner;
use App\Models\Student;
use App\Models\System\Images;
use App\Models\AccommodationType;
use App\Models\Invoice;
use App\Models\InvoiceCourse;
use App\Models\InvoiceAccommodation;
use App\Models\InvoiceService;
use App\Models\InvoicePayment;
use App\Models\InvoicePromotion;
use App\Models\PaymentMethod;
use App\Models\Log;
use App\Models\InvoiceRecordedPayment;
use Session; 
use App\Helpers\RouteHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\LogHelper;
use App\Models\Notification;
use Mail;

class InvoicesController extends Controller {
     
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
		if(!GeneralHelper::checkPrivilege("invoices.view")) {
			return view('admin.unauthorized');
		}
		
		$filters = [];
		
		$branch_id = \App\Helpers\GeneralHelper::currentBranchId();
        $me = Auth::guard('admin')->user();
        
        $current_status = $request->input('status',null);
		$search = $request->input('search',false);
		
		
		$fid = $request->input('id',false);
		$fstart = $request->input('start',false);
		$fend = $request->input('end',false);
		$fstudents = $request->input('students',false);
		$fpartners = $request->input('partners',false);
		$fcampuses = $request->input('campuses',false);
		
		if($fid) {
			$filters['id'] = $fid;
		}
		if($fstart) {
			$filters['start'] = $fstart;
			$fstart = date("Y-m-d", strtotime($fstart));
		}
		if($fend) {
			$filters['end'] = $fend;
			$fend = date("Y-m-d", strtotime($fend));
		}
		if($fstudents) {
			$fstudents = explode(",",$fstudents);
			$filters['students'] = Student::whereIn("student_id",$fstudents)->get();
		}
		if($fpartners) {
			$fpartners = explode(",",$fpartners);
			$filters['partners'] = Partner::whereIn("partner_id",$fpartners)->get();
		}
		if($fcampuses) {
			$fcampuses = explode(",",$fcampuses);
			$filters['campuses'] = PartnerCampus::whereIn("campus_id",$fcampuses)->get();
		}
		
		$invoices = Invoice::currentAccount()
				->where("branch_id","=",$branch_id)->orderBy("invoice_id","desc");
		
		 
		if($fid) {
			$invoices->where("invoice_id","=",$fid);
		}
		if($fstart) {
			$invoices->where("issue_date",">=",$fstart);
		}
		if($fend) {
			$invoices->where("issue_date","<=",$fend);
		}
		if($fstudents) {
			$invoices->whereHas("student",function($q) use ($fstudents) {
				foreach($fstudents as $k=>$sid) {
					$func = "where";
					if($k>0) {
						$func = "orWhere";
					}
					$q->{$func}("student_id","=",$sid);
				}
			});
		}
		if($fpartners) {
			$invoices->whereHas("courses",function($q) use ($fpartners) {
				$q->whereHas("campus",function($q1) use ($fpartners) {
					foreach($fpartners as $k=>$sid) {
						$func = "where";
						if($k>0) {
							$func = "orWhere";
						}
						$q1->{$func}("partner_id","=",$sid);
					}
				});
			});
		}
		if($fcampuses) {
			$invoices->whereHas("courses",function($q) use ($fcampuses) {
				foreach($fcampuses as $k=>$sid) {
					$func = "where";
					if($k>0) {
						$func = "orWhere";
					}
					$q->{$func}("campus_id","=",$sid);
				}
			});
		}
		
		if(!is_null($current_status) && $current_status!="null") {
			$invoices->where('invoice_status','=',$current_status);
			$current_status = (int)$current_status;
		} else {
			$current_status = null;
		}
		
		if(!is_null($search) && $search!="null" && $search) {
			$invoices->where(function($q1) use ($search) {
				$q1->whereHas('student', function($q2) use ($search) {
					$q2->where("student_name","LIKE", "%".$search."%");
				});
				$q1->orWhere("invoice_id","=",(int)$search);
				$q1->orWhereHas('admin', function($q2) use ($search) {
					$q2->where("name","LIKE", "%".$search."%");
				});
			});
		} else {
			$search = false;
		}
		
        return view('admin.invoices.index',[
            'records'=>$invoices->paginate(30),
            'menu_active' => 'invoices',
            'title' => trans('admin.invoices.invoices'),
			'current_status'=>$current_status,
			'search'=>$search,
			'filters'=>$filters
        ]);
    }

	public function selectStudent($account)
    {  
		if(!GeneralHelper::checkPrivilege("invoices.create") && !GeneralHelper::checkPrivilege("invoices.others.create")) {
			return view('admin.unauthorized');
		}
		
		$account = RouteHelper::getCurrentAccount();
		
        return view('admin.invoices.select-student',[
            'menu_active' => 'invoices', 
            'title' => trans('admin.invoices.create')
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
		$me = Auth::guard('admin')->user();

		$student = $request->input('student',null);
		
		$next_invoice_no = ((int)Invoice::currentAccount()->max("invoice_no")) + 1;
		
		$invoice = Invoice::create([
			'admin_id'=>$me->id,
			'student_id'=>$student,
			'language'=>'tr',
			'invoice_no'=>$next_invoice_no
		]);
		
        return 'closeModal();window.location.hash="'.RouteHelper::route("admin.invoices.edit", ['invoice'=>$invoice->invoice_id], false).'";';
    }
	
	public function show($account, $id)
    {
		if(!GeneralHelper::checkPrivilege("invoices.view")) {
			return view('admin.unauthorized');
		} 
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $admin = Invoice::currentAccount()->where("invoice_id","=",$id)
				->first(); //->where("branch_id","=",$currentBranch)
        
        if(!$admin ) {
            Session::flash('fail', trans('admin.invoices.couldnt_find_record'));
			return '<script>window.location.hash="'.RouteHelper::route("admin.invoices.index", [], false).'";</script>';
        }
				
		$admins = Admin::whereHas("branches", function($q) use ($currentBranch){
			$q->where("admins_branches.branch_id","=",$currentBranch);
		})->orderBy("name","asc")->get();
		
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		$methods = PaymentMethod::orderBy("payment_method_order","asc")->get();
		//$admin->refreshPaymentStatuses();
		//die();
		
		$logs = Log::where(function($q) use ($id){
	        $q->where("log_type","LIKE","Invoice")
	        	->where("log_record_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","InvoiceCourse")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","InvoiceAccommodation")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","InvoiceService")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","InvoiceRecordedPayment")
					->where("log_record_parent_id","=",$id);
			})->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","InvoicePromotion")
					->where("log_record_parent_id","=",$id);
        })->orderBy("created_at","desc")->get();

        return view('admin.invoices.show',[
            'record'=>$admin,
			'admins'=>$admins,
			'currencies'=>$currencies,
			'payment_methods'=>$methods,
            'menu_active' => 'invoices',
            'logs'=>$logs,
            'title' => trans('admin.invoices.show')
        ]);
    }
	public function printInvoice($account, $id)
    {
		if(!GeneralHelper::checkPrivilege("invoices.view")) {
			return view('admin.unauthorized');
		}
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $admin = Invoice::currentAccount()->where("invoice_id","=",$id)
				->where("branch_id","=",$currentBranch)->first(); 
        
        if(!$admin ) {
            Session::flash('fail', trans('admin.invoices.couldnt_find_record'));
			return '<script>window.location.hash="'.RouteHelper::route("admin.invoices.index", [], false).'";</script>';
        }
				
		$admins = Admin::whereHas("branches", function($q) use ($currentBranch){
			$q->where("admins_branches.branch_id","=",$currentBranch);
		})->orderBy("name","asc")->get();
		
		
		//$admin->refreshPaymentStatuses();
		//die();
		
        return view('admin.invoices.print',[
            'record'=>$admin,
			'admins'=>$admins
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
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $admin = Invoice::currentAccount()->where("invoice_id","=",$id)
				->first(); //->where("branch_id","=",$currentBranch)
        
        if(!$admin ) {
            Session::flash('fail', trans('admin.invoices.couldnt_find_record'));
			return '<script>window.location.hash="'.RouteHelper::route("admin.invoices.index", [], false).'";</script>';
        }
			
	
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $admin->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$admins = Admin::whereHas("branches", function($q) use ($currentBranch){
			$q->where("admins_branches.branch_id","=",$currentBranch);
		})->orderBy("name","asc");
		
		if(!GeneralHelper::checkPrivilege("invoices.others.create")) {
			$admins->where(function($q) use($admin) {
				$q->where("id","=",Auth::guard('admin')->user()->id)
						->orWhere("id","=",$admin->admin_id);
			});
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		
        
        
		
        return view('admin.invoices.edit',[
            'record'=>$admin,
			'admins'=>$admins->get(),
            'menu_active' => 'invoices',
			'currencies'=>$currencies,
            'title' => trans('admin.invoices.show')
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
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$id)
				->first(); //->where("branch_id","=",$currentBranch)
        
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		$data = $request->input("data",[]);
		$installments = $request->input("installment",[]);
		 
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
		if(isset($data['deposit_due_date']) && !empty($data['deposit_due_date'])) {
			$data['deposit_due_date'] = date("Y-m-d", strtotime($data['deposit_due_date']));
		} else {
			$data['deposit_due_date'] = null;
		}
		
		if($data['admin_id']!=$invoice->admin_id) {
			if(!is_null($invoice->admin_id)) {
				Notification::create([
					'receiver_admin_id'=>$invoice->admin_id,
					'creator_admin_id'=>$me->id,
					'notification_type'=>'unassignedInvoice',
					'related_invoice_id'=>$id
				]);
			}
			if(!is_null($data['admin_id'])) {
				Notification::create([
					'receiver_admin_id'=>$data['admin_id'],
					'creator_admin_id'=>$me->id,
					'notification_type'=>'assignedInvoice',
					'related_invoice_id'=>$id,
					'notification_link'=>RouteHelper::route("admin.invoices.show", ["invoice"=>$id])
				]);
			}
		} 
		$invoice->update($data);
		
		$mentioneds = GeneralHelper::getMentionedIds($data['invoice_notes']);
		foreach($mentioneds as $mentioned) {
			Notification::create([
				'receiver_admin_id'=>$mentioned,
				'creator_admin_id'=>$me->id,
				'notification_type'=>'mentionedAtInvoiceNote',
				'related_student_id'=>$invoice->student_id,
				'related_invoice_id'=>$invoice->invoice_id,
				'notification_link'=>RouteHelper::route("admin.invoices.show", ["invoice"=>$invoice->invoice_id])
			]);
		}
		
		$invoice->payments()->delete();
		
		if(isset($data['deposit_required'])) {
			InvoicePayment::create([
				'invoice_id'=>$invoice->invoice_id,
				'payment_type'=>'deposit',
				'payment_amount'=>$data['deposit_amount'],
				'due_date'=>$data['deposit_due_date']
			]);
		} else {
			$data['deposit_amount'] = 0;
		}
		
		if(isset($data['installment'])) {
			//taksit var
			array_splice($installments,$data['installment_count']);
			foreach($installments as $veri) {
				InvoicePayment::create([
					'invoice_id'=>$invoice->invoice_id,
					'payment_type'=>'installment',
					'payment_amount'=>$veri['amount'],
					'due_date'=>date("Y-m-d",strtotime($veri['due_date']))
				]);
			}
		} else { 
			InvoicePayment::create([
				'invoice_id'=>$invoice->invoice_id,
				'payment_type'=>'payment',
				'payment_amount'=>$invoice->price - $data['deposit_amount'],
				'due_date'=>$data['due_date']
			]);
		} 
		
		$invoice->refresh();
		$invoice->updateInvoicePrice();
		$invoice->updatePartners();
		$invoice->refreshPaymentStatuses();
		$invoice->storeCommissions();
 
		
		if($invoice->invoice_is_notified==0) {
			if($invoice->branch && !is_null($invoice->branch->invoice_notify_mail) && !empty($invoice->branch->invoice_notify_mail)) {
				$receivers = explode(",",$invoice->branch->invoice_notify_mail);
				$receivers=array_map('trim',$receivers);
				if(is_array($receivers) && count($receivers)>0) {
					Mail::to($receivers)->send(new \App\Mail\InvoiceCreatedNotification($invoice));
				}
			}
			$invoice->invoice_is_notified = 1;
			$invoice->save();
		}
		
		
		Session::flash('result', trans('admin.invoices.update_success_text')); 
		return 'closeModal();window.location.hash="'.RouteHelper::route("admin.invoices.show", ['invoice'=>$invoice->invoice_id], false).'";';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Images $imagesModel, $account, $id)
    {
		$from = $request->input('from',false);
        $admin = Invoice::currentAccount()->where("invoice_id","=",$id)->first(); 
        if($admin) { 
			if(!GeneralHelper::checkPrivilege("quotes.others.delete") && !(GeneralHelper::checkPrivilege("quotes.delete") && $admin->admin_id == Auth::guard('admin')->user()->id) ) {
				 return 'alert("'.trans('admin.no_action_privilege').'")';
			}
			$admin->delete();
			
			Session::flash('result', trans('admin.invoices.delete_success_text')); //<--FLASH MESSAGE
		}
		
		if($from) {
			return 'closeModal();window.location.hash="'.RouteHelper::route("admin.invoices.index", [], false).'";';
		} else {
			return 'reloadState();';
		}
    }
	public function destroyMulti(Request $request, Images $imagesModel, $account)
    {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		 
		
		$invoices = $request->input('delete',[]);
		
		if(!is_array($invoices)) {
			$invoices = [];
		}
		
		if(count($invoices)>0) {
			Invoice::currentAccount()->where("branch_id","=",$currentBranch)
				->whereIn("invoice_id",$invoices)->delete();
			
			Session::flash('result', trans('admin.invoices.multi_delete_success_text')); //<--FLASH MESSAGE
		}
	
		 
        return 'reloadState();';
    }
	
	
	
	public function addCourse($account, $invoice_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
        return view('admin.invoices.invoices-add-course',[
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.add-course'),
			'currencies'=>$currencies,
			'coursetypes'=>$types,
			'languages'=>$languages
        ]);
    }
	
	public function storeCourse(Request $request, $account, $invoice_id)
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		$invoice = Invoice::currentAccount()->where("invoice_id", "=", $invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if($type==0) {
			$xx = $this->storeCourseAuto($request, $account, $invoice_id);
			if($xx) {
				LogHelper::logEvent('created', $xx);
			}
		}
		if($type==1) {
			$xx = $this->storeCourseManual($request, $account, $invoice_id);
			if($xx) {
				LogHelper::logEvent('created', $xx);
			}
		}
		
		if($invoice->courses->count()==1) {
			$invoice->currency_id = $xx->currency_id;
			$invoice->save();
		}
		
		
			
		Session::flash('result', trans('admin.invoices.invoices_course_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function storeCourseAuto(Request $request, $account, $invoice_id) {
		 
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id", "=", $invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$data = $request->input('data',false);
		
		$courseId = isset($data['course'])?$data['course']:false;
		$dbCourse = PartnerCampusCourse::find($courseId);
		if(!$dbCourse) {
			return 'alert("'.trans('admin.invoices.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		$orgPrice = isset($data['orginal_price'])?$data['orginal_price']:$price;
		
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

		$xx = InvoiceCourse::create([
			'invoice_id'=>$invoice->invoice_id,
			'course_id'=>$dbCourse->course_id,
			'invoice_course_name'=>$dbCourse->course_name,
			'invoice_course_partner'=>$dbCourse->campus->partner->partner_name,
			'invoice_course_campus'=>$dbCourse->campus->campus_name,
			'invoice_course_duration'=>$duration,
			'invoice_course_duration_unit'=>$durationUnit,
			'invoice_course_price'=>$price,
			'invoice_course_gross_price'=>$price,
			'invoice_commission_base_price'=>$orgPrice,
			'orginal_price'=>$orgPrice,
			'invoice_course_price_type'=>'total',
			'invoice_course_course_type'=>$ctype,
			'invoice_course_course_language'=>$clang,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_course_description'=>$dbCourse->course_description,
			'currency_id'=>$curr->currency_id,
			'place_id'=>$dbCourse->campus->place_id,
			'campus_id'=>$dbCourse->campus_id,
			'invoice_course_start_date'=>$startDate,
			'commission'=>$dbCourse->calculateCommission(),
			'commission_type'=>$dbCourse->calculateCommissionType()
		]); 
		
		$invoice->processAllReqs();
		
		return $xx;
	}
	
	public function storeCourseManual(Request $request, $account, $invoice_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		$orgPrice = isset($data2['orginal_price'])?$data2['orginal_price']:$price;
		
		
		$campus = PartnerCampus::find($data['campus']);
		
		$curr = $campus->getCurrency();
		
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		}

		if(isset($data2['course_language']) && empty($data2['course_language'])) {
			$data2['course_language'] = null;
		}
		
		$xx = InvoiceCourse::create([
			'invoice_id'=>$invoice->invoice_id,
			'invoice_course_name'=>$data2['course_name'],
			'invoice_course_partner'=>$campus->partner->partner_name,
			'invoice_course_campus'=>$campus->campus_name,
			'invoice_course_duration'=>$duration,
			'invoice_course_duration_unit'=>$durationUnit,
			'invoice_course_price'=>$price,
			'invoice_course_gross_price'=>$price, 
			'invoice_commission_base_price'=>$orgPrice,
			'orginal_price'=>$orgPrice,
			'invoice_course_price_type'=>'total',
			'invoice_course_course_type'=>$data2['course_type'],
			'invoice_course_course_language'=>$data2['course_language'],
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_course_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'place_id'=>$campus->place_id,
			'campus_id'=>$campus->campus_id,
			'invoice_course_start_date'=>$startDate,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'],
		]); 
		$invoice->processAllReqs(false,false);
		return $xx;
	}
	
	public function editCourse($account, $invoice_id, $invoice_course_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		
		$admin = $invoice->courses()->where("invoice_course_id","=",$invoice_course_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
        return view('admin.invoices.invoices-add-course',[
			'record'=>$admin,
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.add-course'),
			'currencies'=>$currencies,
			'coursetypes'=>$types,
			'languages'=>$languages
        ]);
    }
	
	public function updateCourse(Request $request, $account, $invoice_id, $invoice_course_id )
    {  
		$type = $request->input('type',0);
		
		if($type==0) {
			$xx = $this->updateCourseAuto($request, $account, $invoice_id, $invoice_course_id);
			if($xx) {
				LogHelper::logEvent('updated', $xx);
			}
		}
		if($type==1) {
			$xx = $this->updateCourseManual($request, $account, $invoice_id, $invoice_course_id);
			if($xx) {
				LogHelper::logEvent('updated', $xx);
			}
		}
		
		Session::flash('result', trans('admin.invoices.invoices_course_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function updateCourseAuto(Request $request, $account, $invoice_id, $invoice_course_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$invoiceCourse = $invoice->courses()->where("invoice_course_id","=",$invoice_course_id)->first(); 
		if(!$invoiceCourse) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
		} 
		
		
		$data = $request->input('data',false);
		
		$courseId = isset($data['course'])?$data['course']:false;
		$dbCourse = PartnerCampusCourse::find($courseId);
		if(!$dbCourse) {
			return 'alert("'.trans('admin.invoices.save_error').'");';
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

		$invoiceCourse->update([
			'invoice_id'=>$invoice->invoice_id,
			'course_id'=>$dbCourse->course_id,
			'invoice_course_name'=>$dbCourse->course_name,
			'invoice_course_partner'=>$dbCourse->campus->partner->partner_name,
			'invoice_course_campus'=>$dbCourse->campus->campus_name,
			'invoice_course_duration'=>$duration,
			'invoice_course_duration_unit'=>$durationUnit,
			'invoice_course_price'=>$price,
			'invoice_course_gross_price'=>$price,
			'invoice_course_price_type'=>'total',
			'invoice_course_course_type'=>$ctype,
			'invoice_course_course_language'=>$clang,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_course_description'=>$dbCourse->course_description,
			'currency_id'=>$curr->currency_id,
			'place_id'=>$dbCourse->campus->place_id,
			'campus_id'=>$dbCourse->campus_id,
			'invoice_course_start_date'=>$startDate,
			'commission'=>$dbCourse->calculateCommission(),
			'commission_type'=>$dbCourse->calculateCommissionType()
		]); 
		
		$invoice->processAllReqs();
		
		return $invoiceCourse;
	}
	public function updateCourseManual(Request $request, $account, $invoice_id, $invoice_course_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$invoiceCourse = $invoice->courses()->where("invoice_course_id","=",$invoice_course_id)->first(); 
		if(!$invoiceCourse) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
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

		$invoiceCourse->update([
			'invoice_course_name'=>$data2['course_name'],
			'invoice_course_partner'=>$campus->partner->partner_name,
			'invoice_course_campus'=>$campus->campus_name,
			'invoice_course_duration'=>$duration,
			'invoice_course_duration_unit'=>$durationUnit,
			'invoice_course_price'=>$price,
			'invoice_course_gross_price'=>$price,
			'invoice_course_price_type'=>'total',
			'invoice_course_course_type'=>$data2['course_type'],
			'invoice_course_course_language'=>$data2['course_language'],
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_course_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'place_id'=>$campus->place_id,
			'campus_id'=>$campus->campus_id,
			'invoice_course_start_date'=>$startDate,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'],
		]); 
		$invoice->processAllReqs(false,false);
		
		return $invoiceCourse;
	}
	public function destroyCourse(Images $imagesModel, $account, $invoice_id, $invoice_course_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }  
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$admin = $invoice->courses()->where("invoice_course_id","=",$invoice_course_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			Session::flash('result', trans('admin.invoices.invoices_course_delete_success_text'));
			
			$invoice->processAllReqs();
		}
		 
		 
        return 'reloadState();';
    }
	
	public function addAccommodation($account, $invoice_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first(); //->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$firstCourse = false;
		
		if($invoice->courses->count()>0) {
			$firstCourse =  $invoice->courses->first();
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$accommodation_types = AccommodationType::orderBy('type_order','asc')->get();
		
        return view('admin.invoices.invoices-add-accommodation',[
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.add-accommodation'),
			'currencies'=>$currencies,
			'accommodation_types'=>$accommodation_types,
			'firstCourse'=>$firstCourse
        ]);
    }
	
	public function storeAccommodation(Request $request, $account, $invoice_id)
    {  
		$type = $request->input('type',0);
		
		if($type==0) {
			$xx = $this->storeAccommodationAuto($request, $account, $invoice_id);
			if($xx) {
				LogHelper::logEvent('created', $xx);
			}
		}
		if($type==1) {
			$xx = $this->storeAccommodationManual($request, $account, $invoice_id);
			if($xx) {
				LogHelper::logEvent('created', $xx);
			}
		}
		
		Session::flash('result', trans('admin.invoices.invoices_accommodation_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function storeAccommodationAuto(Request $request, $account, $invoice_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$data = $request->input('data',false);
		
		$accommodationId = isset($data['accommodation'])?$data['accommodation']:false;
		$dbAccommodation = PartnerCampusAccommodation::find($accommodationId);
		if(!$dbAccommodation) {
			return 'alert("'.trans('admin.invoices.save_error').'");';
		}
		
		$duration = isset($data['duration'])?$data['duration']:false;
		$durationUnit = isset($data['duration_unit'])?$data['duration_unit']:"";
		$price = isset($data['price'])?$data['price']:0;
		$orgPrice = isset($data['orginal_price'])?$data['orginal_price']:$price;
		
		$curr = $dbAccommodation->campus->getCurrency();
			 
		$ctype = null; 

		if($dbAccommodation->type) {
			$ctype = $dbAccommodation->type->type_name;
		} 
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}

		$xx = InvoiceAccommodation::create([
			'invoice_id'=>$invoice->invoice_id,
			'accommodation_id'=>$dbAccommodation->accommodation_id,
			
			'invoice_accommodation_name'=>$dbAccommodation->accommodation_name,
			'invoice_accommodation_partner'=>$dbAccommodation->campus->partner->partner_name,
			'invoice_accommodation_campus'=>$dbAccommodation->campus->campus_name,
			'invoice_accommodation_start_date'=>$startDate,
			'invoice_accommodation_duration'=>$duration,
			'invoice_accommodation_duration_unit'=>$durationUnit,
			'invoice_accommodation_price'=>$price,
			'orginal_price'=>$orgPrice,
			'invoice_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_accommodation_description'=>$dbAccommodation->option_accommodation_description,
			'type_id'=>$dbAccommodation->type_id,
			'invoice_accommodation_type'=>$ctype,
			'commission'=>$dbAccommodation->calculateCommission(),
			'commission_type'=>$dbAccommodation->calculateCommissionType()
		]);
		$invoice->processAllReqs(true,true);
		
		return $xx;
	}
	
	public function storeAccommodationManual(Request $request, $account, $invoice_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		$orgPrice = isset($data2['orginal_price'])?$data2['orginal_price']:$price;
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $invoice->courses()->first();
		
		if($first) {
			$curr = $first->campus->getCurrency();
		} else {
			$curr = new \stdClass();
			$curr->currency_code = null;
			$curr->currency_id = null;
		}
		
		$curr = Currency::find($data2['currency_id']);
		
		$ctype=null;
		
		$type = AccommodationType::find($data2['accommodation_type']);
		if($type) {
			$ctype = $type->type_name;
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$xx = InvoiceAccommodation::create([
			'invoice_id'=>$invoice->invoice_id,
			'accommodation_id'=>null,
			
			'invoice_accommodation_name'=>$data2['accommodation_name'],
			'invoice_accommodation_partner'=>null,
			'invoice_accommodation_campus'=>null,
			'invoice_accommodation_start_date'=>$startDate,
			'invoice_accommodation_duration'=>$duration,
			'invoice_accommodation_duration_unit'=>$durationUnit,
			'invoice_accommodation_price'=>$price,
			'invoice_accommodation_price_type'=>'total',
			'orginal_price'=>$orgPrice,
			'currency_id'=>$curr->currency_id,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_accommodation_description'=>$data2['description'],
			'type_id'=>$data2['accommodation_type'],
			'invoice_accommodation_type'=>$ctype,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'],
		]);
		$invoice->processAllReqs(false,false);
		
		return $xx;
	}
	
	public function editAccommodation($account, $invoice_id, $invoice_accommodation_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$admin = $invoice->accommodations()->where("invoice_accommodation_id","=",$invoice_accommodation_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		$accommodation_types = AccommodationType::orderBy('type_order','asc')->get();
		
        return view('admin.invoices.invoices-add-accommodation',[
			'record'=>$admin,
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.edit_accommodation'),
			'currencies'=>$currencies,
			'accommodation_types'=>$accommodation_types,
			'firstCourse'=>false
        ]);
    }
	
	public function updateAccommodation(Request $request, $account, $invoice_id, $invoice_accommodation_id )
    {  
		$type = $request->input('type',0);
		
		if($type==0) {
			$xx = $this->updateAccommodationAuto($request, $account, $invoice_id, $invoice_accommodation_id);
			if($xx) {
				LogHelper::logEvent('updated', $xx);
			}
		}
		if($type==1) {
			$xx = $this->updateAccommodationManual($request, $account, $invoice_id, $invoice_accommodation_id);
			if($xx) {
				LogHelper::logEvent('updated', $xx);
			}
		}
		
		Session::flash('result', trans('admin.invoices.invoices_accommodation_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	
	public function updateAccommodationAuto(Request $request, $account, $invoice_id, $invoice_accommodation_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$dbOptionAccommodation = $invoice->accommodations()->where("invoice_accommodation_id", "=", $invoice_accommodation_id)->first();
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
			
			'invoice_accommodation_name'=>$dbAccommodation->accommodation_name,
			'invoice_accommodation_partner'=>$dbAccommodation->campus->partner->partner_name,
			'invoice_accommodation_campus'=>$dbAccommodation->campus->campus_name,
			'invoice_accommodation_start_date'=>$startDate,
			'invoice_accommodation_duration'=>$duration,
			'invoice_accommodation_duration_unit'=>$durationUnit,
			'invoice_accommodation_price'=>$price,
			'invoice_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_accommodation_description'=>$dbAccommodation->option_accommodation_description,
			'type_id'=>$dbAccommodation->type_id,
			'invoice_accommodation_type'=>$ctype,
			'commission'=>$dbAccommodation->calculateCommission(),
			'commission_type'=>$dbAccommodation->calculateCommissionType()
		]);
		$invoice->processAllReqs(true,true);
		
		return $dbOptionAccommodation;
	}
	
	public function updateAccommodationManual(Request $request, $account, $invoice_id, $invoice_accommodation_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$dbOptionAccommodation = $invoice->accommodations()->where("invoice_accommodation_id", "=", $invoice_accommodation_id)->first();
		if(!$dbOptionAccommodation) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
 
		$duration = isset($data2['duration'])?$data2['duration']:false;
		$durationUnit = isset($data2['duration_unit'])?$data2['duration_unit']:"";
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $invoice->courses()->first();
		
		if($first) {
		$curr = $first->campus->getCurrency();
		
		
		} else {
			$curr = new \stdClass();
			$curr->currency_id=null;
			$curr->currency_code=null;
		}
		
		$curr = Currency::find($data2['currency_id']);
		
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
			
			'invoice_accommodation_name'=>$data2['accommodation_name'],
			'invoice_accommodation_partner'=>null,
			'invoice_accommodation_campus'=>null,
			'invoice_accommodation_start_date'=>$startDate,
			'invoice_accommodation_duration'=>$duration,
			'invoice_accommodation_duration_unit'=>$durationUnit,
			'invoice_accommodation_price'=>$price,
			'invoice_accommodation_price_type'=>'total',
			'currency_id'=>$curr->currency_id,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_accommodation_description'=>$data2['description'],
			'type_id'=>$data2['accommodation_type'],
			'invoice_accommodation_type'=>$ctype,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'], 
		]);
		$invoice->processAllReqs(false, false);
		
		return $dbOptionAccommodation;
	}
	
	public function destroyAccommodation(Images $imagesModel, $account, $invoice_id, $invoice_accommodation_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$admin = $invoice->accommodations()->where("invoice_accommodation_id","=",$invoice_accommodation_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			
			$admin->delete(); 
			Session::flash('result', trans('admin.invoices.invoices_accommodation_delete_success_text'));
			$invoice->processAllReqs(false,false);
		}
		
		
		
        return 'reloadState();';
    }
	public function addService($account, $invoice_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        }
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$firstCourse = false;
		
		if($invoice->courses->count()>0) {
			$firstCourse =  $invoice->courses->first();
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
        return view('admin.invoices.invoices-add-service',[
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.add-service'),
			'currencies'=>$currencies,
			'firstCourse'=>$firstCourse
        ]);
    }
	
	public function storeService(Request $request, $account, $invoice_id)
    {  
		$type = $request->input('type',0);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		$invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if($type==0) {
			$ret = $this->storeServiceAuto($request, $account, $invoice_id);
			if($ret) {
				LogHelper::logEvent('created', $ret);
			}
		}
		if($type==1) {
			$ret = $this->storeServiceManual($request, $account, $invoice_id);
			if($ret) {
				LogHelper::logEvent('created', $ret);
			}
		}
		
		Session::flash('result', trans('admin.invoices.invoices_service_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function storeServiceAuto(Request $request, $account, $invoice_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
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
		$orgPrice = isset($data['orginal_price'])?$data['orginal_price']:$price;
		
		$curr = $dbService->campus->getCurrency();  
		
		$startDate = null;
		
		if(isset($data['start_date']) && !empty($data['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data['start_date']));
		}
		
		$quantity = 1;
		if(isset($data['quantity']) && !empty($data['quantity'])) {
			$quantity = $data['quantity'];
		}

		$xx = InvoiceService::create([
			'invoice_id'=>$invoice->invoice_id,
			'service_id'=>$dbService->service_id,
			 
			'invoice_service_name'=>$dbService->service_name,
			'invoice_service_partner'=>$dbService->campus->partner->partner_name,
			'invoice_service_campus'=>$dbService->campus->campus_name,
			'invoice_service_start_date'=>$startDate,
			'invoice_service_duration'=>$duration,
			'invoice_service_duration_unit'=>$durationUnit,
			'invoice_service_price'=>$price,
			'invoice_service_gross_price'=>$price,
			'invoice_commission_base_price'=>$orgPrice,
			'orginal_price'=>$orgPrice,
			'invoice_service_price_type'=>'total',
			'invoice_service_description'=>$dbService->service_description,
			'invoice_service_quantity'=>$quantity,
			'invoice_currency_code'=>$curr->currency_code,
			'currency_id'=>$curr->currency_id,
			'commission'=>$dbService->calculateCommission(),
			'commission_type'=>$dbService->calculateCommissionType()
		]);
		
		$invoice->processAllReqs();
		
		return $xx;
	}
	
	public function storeServiceManual(Request $request, $account, $invoice_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = ( isset($data2['duration']) && !empty($data2['duration']) )?$data2['duration']:null;
		$durationUnit = ( isset($data2['duration_unit']) && !empty($data2['duration_unit']) )?$data2['duration_unit']:null;
		$price = isset($data2['price'])?$data2['price']:0;
		
		$orgPrice = isset($data2['orginal_price'])?$data2['orginal_price']:$price;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $invoice->courses()->first();
		
		if($first) {
			$curr = $first->campus->getCurrency();
		} else {
			$curr = new \stdClass();
			$curr->currency_id = null;
			$curr->currency_code = null;
		}
		
		$curr = Currency::find($data2['currency_id']);
		
		$quantity = 1;
		if(isset($data2['quantity']) && !empty($data2['quantity'])) {
			$quantity = $data2['quantity'];
		}
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$xx =InvoiceService::create([
			
			'invoice_id'=>$invoice->invoice_id,
			 
			'invoice_service_name'=>$data2['service_name'],
			'invoice_service_partner'=>null,
			'invoice_service_campus'=>null,
			'invoice_service_start_date'=>$startDate,
			'invoice_service_duration'=>$duration,
			'invoice_service_duration_unit'=>$durationUnit,
			'invoice_service_price'=>$price,
			'invoice_service_gross_price'=>$price,
			'invoice_commission_base_price'=>$orgPrice,
			'orginal_price'=>$orgPrice,
			'invoice_service_price_type'=>'total',
			'invoice_service_quantity'=>$quantity,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_service_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'],
			
		]);
		
		$invoice->processAllReqs(false,false);
		
		return $xx;
	}
	
	public function editService($account, $invoice_id, $invoice_service_id)
    {  
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            abort(404);
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
		$admin = $invoice->services()->where("invoice_service_id","=",$invoice_service_id)->first(); 
		if(!$admin) {
            abort(404);
		} 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get(); 
		
        return view('admin.invoices.invoices-add-service',[
			'record'=>$admin,
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.edit-service'),
			'currencies'=>$currencies,
			'firstCourse'=>false
        ]);
    }
	
	public function updateService(Request $request, $account, $invoice_id, $invoice_service_id )
    {  
		$type = $request->input('type',0);
		
		if($type==0) {
			$ret = $this->updateServiceAuto($request, $account, $invoice_id, $invoice_service_id);
			if($ret) {
				LogHelper::logEvent('updated', $ret);
			}
		}
		if($type==1) {
			$ret = $this->updateServiceManual($request, $account, $invoice_id, $invoice_service_id);
			if($ret) {
				LogHelper::logEvent('updated', $ret);
			}
		}
		
		Session::flash('result', trans('admin.invoices.invoices_service_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	
	public function updateServiceAuto(Request $request, $account, $invoice_id, $invoice_service_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		$dbOptionService = $invoice->services()->where("invoice_service_id", "=", $invoice_service_id)->first();
		if(!$dbOptionService) {
           return 'alert("'.trans('admin.invoices.save_error').'");';
		}
		
		$data = $request->input('data',false);
		
		$serviceId = isset($data['service'])?$data['service']:false;
		$dbService = PartnerCampusService::find($serviceId);
		if(!$dbService) {
			return 'alert("'.trans('admin.invoices.save_error').'");';
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
			'service_id'=>$dbService->service_id,
			 
			'invoice_service_name'=>$dbService->service_name,
			'invoice_service_partner'=>$dbService->campus->partner->partner_name,
			'invoice_service_campus'=>$dbService->campus->campus_name,
			'invoice_service_start_date'=>$startDate,
			'invoice_service_duration'=>$duration,
			'invoice_service_duration_unit'=>$durationUnit,
			'invoice_service_price'=>$price,
			'invoice_service_gross_price'=>$price,
			'invoice_service_description'=>$dbService->service_description,
			'invoice_service_price_type'=>'total',
			'invoice_service_quantity'=>$quantity,
			'invoice_currency_code'=>$curr->currency_code,
			'currency_id'=>$curr->currency_id,
			'commission'=>$dbService->calculateCommission(),
			'commission_type'=>$dbService->calculateCommissionType()
		]);
		$invoice->processAllReqs();
		return $dbOptionService;
	}
	
	public function updateServiceManual(Request $request, $account, $invoice_id, $invoice_service_id) {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		$dbOptionService = $invoice->services()->where("invoice_service_id", "=", $invoice_service_id)->first();
		if(!$dbOptionService) {
           return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		
		$data = $request->input('data',false);
		$data2 = $request->input('data2',false); 
		 
		
		$duration = ( isset($data2['duration']) && !empty($data2['duration']) )?$data2['duration']:null;
		$durationUnit = ( isset($data2['duration_unit']) && !empty($data2['duration_unit']) )?$data2['duration_unit']:null;
		$price = isset($data2['price'])?$data2['price']:0;
		
		//$campus = PartnerCampus::find($data['campus']);
		
		$first = $invoice->courses()->first();
		
		$curr = $first->campus->getCurrency();
		
		$quantity = 1;
		if(isset($data2['quantity']) && !empty($data2['quantity'])) {
			$quantity = $data2['quantity'];
		}
		
		$curr = Currency::find($data2['currency_id']);
		
		$startDate = null;
		
		if(isset($data2['start_date']) && !empty($data2['start_date'])) {
			$startDate = date("Y-m-d", strtotime($data2['start_date']));
		} 
		
		$dbOptionService->update([
			'invoice_service_name'=>$data2['service_name'],
			'invoice_service_partner'=>null,
			'invoice_service_campus'=>null,
			'invoice_service_start_date'=>$startDate,
			'invoice_service_duration'=>$duration,
			'invoice_service_duration_unit'=>$durationUnit,
			'invoice_service_price'=>$price,
			'invoice_service_gross_price'=>$price,
			'invoice_service_price_type'=>'total',
			'invoice_service_quantity'=>$quantity,
			'invoice_currency_code'=>$curr->currency_code,
			'invoice_service_description'=>$data2['description'],
			'currency_id'=>$curr->currency_id,
			'commission'=>$data2['commission'],
			'commission_type'=>$data2['commission_type'],
			
		]);
		
		$invoice->processAllReqs(false,false);
		
		return $dbOptionService;
	}
	
	public function destroyService(Images $imagesModel, $account, $invoice_id, $invoice_service_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$admin = $invoice->services()->where("invoice_service_id","=",$invoice_service_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			Session::flash('result', trans('admin.invoices.invoices_service_delete_success_text'));
			$invoice->processAllReqs();
		}
		
		
		
		
        return 'reloadState();';
    }
	public function destroyFee(Images $imagesModel, $account, $invoice_id, $invoice_service_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->first();	//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        } 
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$admin = $invoice->services()->where("invoice_service_id","=",$invoice_service_id)->first(); 
        if($admin) { 
			LogHelper::logEvent('deleting', $admin);
			$admin->delete(); 
			Session::flash('result', trans('admin.invoices.invoices_fee_delete_success_text'));
			$invoice->addBlockedFee($admin->service_id);
			
			$invoice->processAllReqs();
		}
		
		
		
		
        return 'reloadState();';
    }
	public function sendQuoteForm($account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
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
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		if(!$quote->student) {
			return 'alert("'.trans('admin.quotes.student_not_selected').'")';
		}
		
		$emails = $request->input('emails',[]);
		$subject = $request->input('subject','');
		$message = $request->input('message','');
		
		foreach($emails as $email) { 
			//Notification::route('mail', $email)->notify(new \App\Notifications\SendQuote($quote, $subject, $message)); 
			//Notification::send(collect($email), new \App\Notifications\SendQuote($quote, $subject, $message));
			Mail::to($email)->send(new \App\Mail\SendQuote($quote, $subject, $message));
		} 
		
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
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		$quote->quote_status = 1;
		$quote->issue_date = date("Y-m-d");
		$quote->save();
		return 'reloadState();';
	}
	public function markAsRejected(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		$quote->quote_status = 4; 
		$quote->save();
		return 'reloadState();';
	}
	public function revertToDraft(Request $request, $account, $invoice_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("invoices.backtodraft")) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$invoice->invoice_is_sent = 0;
		$invoice->viewed_at = null;
		$invoice->save();
		return 'reloadState();';
	} 
	public function markAsSent(Request $request, $account, $invoice_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		if(!GeneralHelper::checkPrivilege("invoices.send")) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		$me = Auth::guard('admin')->user();
		//PDF
		$file2 = false;
        $html = (string) \View::make('admin.invoices.payment-contract', [
            'payment'=>$invoice,
            'user'=>$me
        ])->render();
        
        $folder = floor($invoice->invoice_id/1000);
        
        if(!is_dir(public_path('uploads/'.$invoice->account_id.'/payment-contract/'.$folder))) {
            mkdir(public_path('uploads/'.$invoice->account_id.'/payment-contract/'.$folder), 0777);
        }

        $pdf = \PDF::loadHTML($html, 'UTF-8');
        $file = public_path('uploads/'.$invoice->account_id.'/payment-contract/'.$folder).'/contract-'.$invoice->invoice_id.'.pdf';
        $pdf->setPaper('a4', 'portrait')->save($file);

        $file2 = 'uploads/'.$invoice->account_id.'/payment-contract/'.$folder.'/contract-'.$invoice->invoice_id.'.pdf';
        
        $receiver = $invoice->student->student_email;
        
        if($me->id==1){
        
        	Mail::to($receiver)->send(new \App\Mail\ContractPdf($invoice, $file2));
        
        }
		
		$invoice->invoice_is_sent = 1; 
		$invoice->save();
		return 'reloadState();';
	}
	
	public function recordPayment(Request $request, $account, $invoice_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
		$me = Auth::guard('admin')->user();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)->first();
 
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
	 
	 
		if(!GeneralHelper::checkPrivilege("invoices.record-payment")) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
		$amount = $request->input('amount',0);
		$amount2 = $request->input('amount2',0);
		$currency = $request->input('currency',null);
		$date = $request->input('payment_date','');
		$payment_method = $request->input('payment_method',null);
		
		if(empty($payment_method)) {
			$payment_method = null;
		}
		
		
		$date = date("Y-m-d", strtotime($date));
		
		$payment_made = InvoiceRecordedPayment::create([
			'invoice_id'=>$invoice_id,
			'admin_id'=>$me->id,
			'recorded_payment_amount'=>$amount,
			'recorded_payment_amount2'=>$amount2,
			'currency_id'=>$currency,
			'recorded_payment_date'=>$date,
			'payment_method_id'=>$payment_method
		]);
		
		$invoice->refreshPaymentStatuses();
		
		
        if(true){
			//PDF
			$file2 = false;
			$html = (string) \View::make('admin.invoices.payment-receipt', [
				'payment_made'=>$payment_made,
				'payment'=>$invoice,
				'user'=>$me
			])->render();

			$folder = floor($invoice->invoice_id/1000);

			if(!is_dir(public_path('uploads/'.$invoice->account_id.'/payment-receipts/'.$folder))) {
				mkdir(public_path('uploads/'.$invoice->account_id.'/payment-receipts/'.$folder), 0777);
			}

			$pdf = \PDF::loadHTML($html, 'UTF-8');
			$file = public_path('uploads/'.$invoice->account_id.'/payment-receipts/'.$folder).'/receipt-'.$invoice->invoice_id.'.pdf';
			$pdf->setPaper('a4', 'portrait')->save($file);

			$file2 = 'uploads/'.$invoice->account_id.'/payment-receipts/'.$folder.'/receipt-'.$invoice->invoice_id.'.pdf';

			$receiver = $invoice->student->student_email;

			if($receiver!="" && $me->id==1) {
				Mail::to($receiver)->send(new \App\Mail\InvoicePdf($invoice, $file2));
			}  
        }
		
		Session::flash('result', trans('admin.invoices.payment_record_success_text'));
		return 'reloadState();';
	}
	
	public function createInvoiceFromQuote(Request $request, $account, $quote_id) {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$quote) {
            return abort(404);
        }
		
		if(!$quote->student) {
            return abort(404);
		}
		
		if($quote->quote_status<3) {
            return abort(404);
		}
		
		return view('admin.invoices.create-invoice-from-quote',[
			'quote'=>$quote
        ]);
	}
	public function storeInvoiceFromQuote(Request $request, $account, $quote_id) {
		
		$me = Auth::guard('admin')->user();
		
		
		$coursesManualCommissions = $request->input('courses',[]);
		$accommodationsManualCommissions = $request->input('accommodations',[]);
		$servicesManualCommissions = $request->input('services',[]);
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $quote = Quote::currentAccount()->where("quote_id","=",$quote_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if((!$quote) || (!$quote->selectedOption)) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }
		
		$next_invoice_no = ((int)Invoice::currentAccount()->max("invoice_no")) + 1;
		
		//\DB::beginTransaction();
		
		$firstItem = $quote->selectedOption->courses->first();
		if(!$firstItem) {
			$firstItem = $quote->selectedOption->accommodations->first();
		}
		if(!$firstItem) {
			$firstItem = $quote->selectedOption->services->first();
		}
		
		$curr_id = $firstItem->currency_id;
		$invoice = Invoice::create([
			'admin_id'=>$me->id,
			'student_id'=>$quote->student_id,
			'language'=>'tr',
			'invoice_no'=>$next_invoice_no,
			'currency_id'=>$curr_id,
			'due_date'=>$quote->due_date,
			'issue_date'=>$quote->issue_date,
			'invoice_deleted_fees'=>$quote->selectedOption->option_deleted_fees,
			'invoice_deleted_promotions'=>$quote->selectedOption->option_deleted_promotions
		]);
		
	 
		
		foreach($quote->selectedOption->courses as $course) { 
			if($course->course) {
				$comm = $course->course->calculateCommission();
				$commtype = $course->course->calculateCommissionType();
			} else {  
				$comm = $coursesManualCommissions[$course->option_course_id]['commission'];
				$commtype = $coursesManualCommissions[$course->option_course_id]['commission_type'];
			}
			
		 
			
			$xInv = InvoiceCourse::create([ 
				'invoice_id'=>$invoice->invoice_id,
				'course_id'=>$course->course_id,
				'campus_id'=>$course->campus_id,
				'invoice_course_name'=>$course->option_course_name,
				'invoice_course_partner'=>$course->option_course_partner,
				'invoice_course_campus'=>$course->option_course_campus,
				'invoice_course_campus_image'=>$course->option_course_campus_image,
				'invoice_course_campus_logo'=>$course->option_course_campus_logo,
				'invoice_course_start_date'=>$course->option_course_start_date,
				'place_id'=>$course->place_id,
				'invoice_course_course_type'=>$course->option_course_course_type,
				'invoice_course_course_language'=>$course->option_course_course_language,
				'invoice_course_duration'=>$course->option_course_duration,
				'invoice_course_duration_unit'=>$course->option_course_duration_unit,
				'invoice_course_price'=>$course->option_course_price,
		 
				'orginal_price'=>$course->option_course_gross_price,
				'invoice_course_gross_price'=>$course->option_course_gross_price,
				'invoice_commission_base_price'=>$course->option_course_gross_price,
				'invoice_course_course_price'=>$course->option_course_price,
				'invoice_course_price_type'=>$course->option_course_price_type,
				'invoice_course_intensity'=>$course->option_course_intensity,
				'invoice_currency_code'=>$course->option_currency_code,
				'currency_id'=>$course->currency_id,
				'invoice_course_description'=>$course->option_course_description,
				'commission'=>$comm,
				'commission_type'=>$commtype
			]);
			
			$invoice->invoice_deleted_promotions = str_replace($course->option_course_id.'-',$xInv->invoice_course_id.'-',$invoice->invoice_deleted_promotions);
			$invoice->save();
			
			LogHelper::logEvent('created', $xInv);
		}
		
		foreach($quote->selectedOption->accommodations as $accommodation) { 
			if($accommodation->accommodation) {
				$comm = $accommodation->accommodation->calculateCommission();
				$commtype = $accommodation->accommodation->calculateCommissionType();
			} else {  
				$comm = $accommodationsManualCommissions[$accommodation->option_accommodation_id]['commission'];
				$commtype = $accommodationsManualCommissions[$accommodation->option_accommodation_id]['commission_type'];
			}
			
			$xx = InvoiceAccommodation::create([ 
				'invoice_id'=>$invoice->invoice_id,
				'accommodation_id'=>$accommodation->accommodation_id, 
				'invoice_accommodation_name'=>$accommodation->option_accommodation_name,
				'invoice_accommodation_partner'=>$accommodation->option_accommodation_partner,
				'invoice_accommodation_campus'=>$accommodation->option_accommodation_campus,
				'invoice_accommodation_start_date'=>$accommodation->option_accommodation_start_date,
				'invoice_accommodation_duration'=>$accommodation->option_accommodation_duration,
				'invoice_accommodation_duration_unit'=>$accommodation->option_accommodation_duration_unit,
				'invoice_accommodation_price'=>$accommodation->option_accommodation_price,
				'invoice_accommodation_price_type'=>$accommodation->option_accommodation_price_type,
				'orginal_price'=>$accommodation->option_accommodation_price,
				'invoice_currency_code'=>$accommodation->option_currency_code,
				'type_id'=>$accommodation->type_id,
				'invoice_accommodation_type'=>$accommodation->option_accommodation_type,
				'currency_id'=>$accommodation->currency_id,
				'invoice_accommodation_description'=>$accommodation->option_accommodation_description,
				'commission'=>$comm,
				'commission_type'=>$commtype
			]);
			LogHelper::logEvent('created', $xx);
		}
		
		foreach($quote->selectedOption->services as $service) { 
			if($service->service) {
				$comm = $service->service->calculateCommission();
				$commtype = $service->service->calculateCommissionType();
			} else {  
				$comm = $servicesManualCommissions[$service->option_service_id]['commission'];
				$commtype = $servicesManualCommissions[$service->option_service_id]['commission_type'];
			}
			
			$xx = InvoiceService::create([ 
				'invoice_id'=>$invoice->invoice_id,
				'service_id'=>$service->service_id, 
				'invoice_service_name'=>$service->option_service_name,
				'invoice_service_partner'=>$service->option_service_partner,
				'invoice_service_campus'=>$service->option_service_campus,
				'invoice_service_start_date'=>$service->option_service_start_date,
				'invoice_service_duration'=>$service->option_service_duration,
				'invoice_service_duration_unit'=>$service->option_service_duration_unit,
				'invoice_service_price'=>$service->option_service_price,
				'invoice_service_gross_price'=>$service->option_service_gross_price,
				'invoice_commission_base_price'=>$service->option_service_gross_price,
				'orginal_price'=>$service->option_service_gross_price,
				'invoice_service_price_type'=>$service->option_service_price_type,
				'invoice_service_quantity'=>$service->option_service_quantity,
				'invoice_currency_code'=>$service->option_currency_code,
				'currency_id'=>$service->currency_id,
				'invoice_service_description'=>$service->option_service_description,
				'commission'=>$comm,
				'commission_type'=>$commtype
			]);
			LogHelper::logEvent('created', $xx);
		}
		
		foreach($quote->selectedOption->promotions()->whereNull("option_course_id")->whereNull("option_service_id")->get() as $service) { 
			$xx = InvoicePromotion::create([
				'invoice_id'=>$invoice->invoice_id,
				'promotion_name'=>$service->promotion_name,
				'promotion_fixed'=>$service->promotion_fixed,
				'currency_id'=>$service->currency_id
			]);
			LogHelper::logEvent('created', $xx);
		}
		
		//\DB::rollBack();
		
		 
		$invoice->processAllReqs();
		
		return 'closeModal();window.location.hash="'.RouteHelper::route("admin.invoices.edit", ['invoice'=>$invoice->invoice_id], false).'";';
	}
	
	public function addPromotion($account, $invoice_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            abort(404);
        } 
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		}
		
        return view('admin.invoices.invoices-add-promotion',[
            'invoice'=>$invoice,   
            'title' => trans('admin.invoices.add-promotion'),
			'currencies'=>$currencies,
			'active_currency_id'=>$invoice->currency_id
        ]);
    }
	
	public function storePromotion(Request $request, $account, $invoice_id)
    {   
		 $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		 
		$invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		
		$data = $request->input('data',[]);
		
		$data['invoice_id'] = $invoice_id;
		
		$promotion = InvoicePromotion::create($data);
		
		$invoice->processAllReqs(); 
		
		if($promotion) {
			LogHelper::logEvent('created', $promotion);
		}
		
		Session::flash('result', trans('admin.invoices.invoices_promotion_create_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function editPromotion($account, $invoice_id, $invoice_promotion_id)
    {   
		
        $currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            abort(404);
        }  
		
		$promotion = $invoice->promotions()->where("invoice_promotion_id","=",$invoice_promotion_id)->first();
		if( !$promotion) {
			abort(404);
		}
		
		$currencies = Currency::currentAccount()->orderBy('currency_order','asc')->get();
		
		if(!GeneralHelper::checkPrivilege("invoices.others.update") && !(GeneralHelper::checkPrivilege("invoices.update") && $invoice->admin_id == Auth::guard('admin')->user()->id)) {
			return view('admin.unauthorized');
		} 
		
		
		
        return view('admin.invoices.invoices-add-promotion',[
            'invoice'=>$invoice,   
			'record'=>$promotion,
            'title' => trans('admin.invoices.edit-promotion'),
			'currencies'=>$currencies
        ]);
    }
	
	public function updatePromotion(Request $request, $account, $invoice_id, $invoice_promotion_id)
    {  
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
		$data = $request->input('data',[]);
		
		$invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)
				->where("branch_id","=",$currentBranch)->first();
		
		if(!$invoice) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }  
		
		$promotion = $invoice->promotions()->where("invoice_promotion_id","=",$invoice_promotion_id)->first();
		if( !$promotion) {
			return 'alert("'.trans('admin.quotes.save_error').'");';
		}
		 
		
		$promotion->update($data);
		
		
		$invoice->processAllReqs(); 
		
		if($promotion) {
			LogHelper::logEvent('updated', $promotion);
		}
		
		Session::flash('result', trans('admin.invoices.invoices_promotion_update_success_text'));
		return 'closeModal(); reloadState();';
    }
	
	public function destroyPromotion(Images $imagesModel, $account, $invoice_id, $invoice_promotion_id)
    {
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
		$invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)->first();
				//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.quotes.save_error').'");';
        }
		
		$admin = $invoice->promotions()->where("invoice_promotion_id","=",$invoice_promotion_id)->first(); 
        if($admin) {  
			LogHelper::logEvent('deleting', $admin); 
			$admin->delete(); 
			Session::flash('result', trans('admin.invoices.invoices_promotion_delete_success_text'));
		} 
		
		
		$invoice->processAllReqs(); 
		
        return 'reloadState();';
    }
	
	public function destroyAutoPromotion(Images $imagesModel, $account, $invoice_id, $invoice_promotion_id)
    {
		
		$currentBranch = \App\Helpers\GeneralHelper::currentBranchId();
		
        $invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)->first();
				//->where("branch_id","=",$currentBranch)
		
		if(!$invoice) {
            return 'alert("'.trans('admin.invoices.save_error').'");';
        }  
		
		$admin = $invoice->promotions()->where("invoice_promotion_id","=",$invoice_promotion_id)->first(); 
        if($admin) { 
			if($admin->course) {
				$admin->course->invoice_course_price = $admin->course->invoice_course_gross_price;
				$admin->course->save();
			}
			if($admin->service) {
				$admin->service->invoice_service_price = $admin->service->invoice_service_gross_price;
				$admin->service->save();
			}
			if($admin) {
				LogHelper::logEvent('deleting', $admin);
			}
			$admin->delete(); 
			
			$relaid = $admin->invoice_course_id;
			if(is_null($relaid) || $relaid == "") {
				$relaid = "0";
			}
			
			Session::flash('result', trans('admin.invoices.auto_promotion_delete_success_text'));
			$invoice->addBlockedPromotion($relaid, $admin->promotion_id);
			$invoice->processAllReqs();
		}
		
		
		
        return 'reloadState();';
    }
	
	public function cancel(Request $request, $account, $invoice_id) {
		$invoice = Invoice::currentAccount()->where("invoice_id","=",$invoice_id)->first();
		
		$reason = $request->input('invoice_cancel_reason','');
		
		if(!$invoice) {
            abort(404);
        }  
		 
		$invoice->invoice_cancel_reason = $reason;
		$invoice->invoice_is_canceled = 1;
		$invoice->canceled_at = date("Y-m-d H:i:s");
		$invoice->save();
		
		$invoice->processAllReqs();
		 
		
		Session::flash('result', trans('admin.invoices.cancel_success_text'));
		return 'reloadState();';
	}
}
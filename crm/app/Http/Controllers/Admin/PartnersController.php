<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Partner;
use App\Models\PartnerNote;
use App\Models\PartnerCampus;
use App\Models\PartnerCampusImage;
use App\Models\PartnerCampusAward;
use App\Models\PartnerCampusCourse;
use App\Models\PartnerCampusCoursePrice;
use App\Models\PartnerCampusAccommodation;
use App\Models\PartnerCampusAccommodationPrice;
use App\Models\PartnerCampusService;
use App\Models\PartnerCampusServicePrice;
use App\Models\CourseType;
use App\Models\Country;
use App\Models\AccommodationType;
use App\Models\CourseLanguage;
use App\Models\SocialMedia;
use App\Models\Currency;
use App\Models\Promotion;
use App\Models\System\Images;
use App\Models\Log;
use Session; 
use App\Admin;
use App\Helpers\RouteHelper;
use App\Helpers\UploadHelper;
use App\Helpers\GeneralHelper;

class PartnersController extends Controller {
	
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
		if(!GeneralHelper::checkPrivilege("partners.view")) {
			return view('admin.unauthorized');
		}
		
        $me = Auth::guard('admin')->user();
		
        $search = $request->input('search',false);
		
		$partners = Partner::currentAccount()->orderBy("partner_name","asc");
		
		if($search) {
			$partners->where("partner_name","LIKE","%".$search."%");
		}
		
        return view('admin.partners.index',[
            'records'=>$partners->paginate(30),
            'menu_active' => 'partners',
			'search'=>$search,
            'title' => trans('admin.partners.partners')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {  
		$account = RouteHelper::getCurrentAccount();
		
		$currencies = Currency::currentAccount()->orderBy("currency_order","asc")->get();
		
        return view('admin.partners.create',[
            'menu_active' => 'partners',
			'currencies'=>$currencies,
            'title' => trans('admin.partners.create')
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
   
		$data = $request->input('data');
		
		$data = Partner::create($data);
        
        Session::flash('result', trans('admin.partners.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($account, $id)
    {
		if(!GeneralHelper::checkPrivilege("partners.view")) {
			return view('admin.unauthorized');
		}
		
        $admin = Partner::currentAccount()->where("partner_id","=",$id)->first(); 
        
        if(!$admin  ) {
            Session::flash('fail', trans('admin.partners.couldnt_find_record'));
            return redirect(\RouteHelper::route('admin.partners.index'));
        }
        
        $logs = Log::where(function($q) use ($id){
	        $q->where("log_type","LIKE","Partner")
	        	->where("log_record_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampus")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusCourse")
					->where("log_record_parent_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusAccommodation")
					->where("log_record_parent_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusService")
					->where("log_record_parent_parent_id","=",$id);
			})->orderBy("created_at","desc")->get();
				
        return view('admin.partners.show',[
            'record'=>$admin,
            'menu_active' => 'partners',
            'title' => trans('admin.partners.show'),
            'logs'=>$logs
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
		if(!GeneralHelper::checkPrivilege("partners.update")) {
			return view('admin.unauthorized');
		}
		
        $admin = Partner::currentAccount()->where("partner_id","=",$id)->first(); 
        
        if(!$admin  ) {
            abort(404);
        } 
        
		$currencies = Currency::currentAccount()->orderBy("currency_order","asc")->get();
		$countries = Country::orderBy("country_name","asc")->get();
		
        return view('admin.partners.create-edit',[
            'record'=>$admin,
            'menu_active' => 'partners',
			'currencies'=>$currencies,
			'countries'=>$countries,
            'title' => trans('admin.partners.edit')
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
        
        $admin = Partner::currentAccount()->where("partner_id","=",$id)->first(); 
        
        if(!$admin) {
            return 'alert("'.trans('admin.partners.save_error').'");';
        }
        
        $data = $request->input('data');
		
		
		if($admin->partner_logo != $data['partner_logo'] && !is_null($admin->partner_logo) && !empty($admin->partner_logo)) {
			$imagesModel->deleteSingleImage($admin->partner_logo);
		}
		if($admin->partner_image != $data['partner_image'] && !is_null($admin->partner_image) && !empty($admin->partner_image)) {
			$imagesModel->deleteSingleImage($admin->partner_image);
		}
		
        $admin->update($data); 
        
        Session::flash('result', trans('admin.partners.update_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($account, $id)
    {
        $branch = Partner::currentAccount()->where("partner_id","=",$id)->first();
        if($branch) {
			$branch->delete();
			Session::flash('result', trans('admin.partners.delete_success_text')); //<--FLASH MESSAGE
		}
        return 'reloadState();';
    }
	
	public function notesCreate($account, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.notes.create")) {
			return view('admin.unauthorized');
		}
		
		$me = Auth::guard('admin')->user();
		
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$admins = Admin::currentAccount()->where("id","!=",$me->id)->orderBy("name","asc")->get();
		
        return view('admin.partners.notes.create-edit',[ 
			'partner'=>$partner,
            'title' => trans('admin.partners.notes.create'),
			'admins'=>$admins,
			'uniqid' => uniqid('tmp_')
        ]);
    }
	
    public function notesStore(Request $request, $account, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
   
        $me = Auth::guard('admin')->user();
		
        $data = $request->input('data');
		
		$new = PartnerNote::create([
			'partner_id'=>$id,
			'admin_id'=>$me->id,
			'note_title'=>$data['title'],
			'note_text'=>$data['text'],
			'note_files'=>isset($data['note_files'])?$data['note_files']:[]
		]);
		
		if( is_array($new->note_files) ) {
			 
            $uid = $request->input('uniqid');
			$allfiles = [];
			foreach($new->note_files as $k=>$note_file) {
				$allfiles[] = UploadHelper::processFileBeforeSave($note_file,null,$uid,$new->note_id);
			} 
			$new->note_files = $allfiles;
            $new->save();
        }
		
        Session::flash('result', trans('admin.partners.notes.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function notesEdit($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.notes.update")) {
			return view('admin.unauthorized');
		}
		$me = Auth::guard('admin')->user();
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$admins = Admin::currentAccount()->where("id","!=",$me->id)->orderBy("name","asc")->get();
		
		$note = PartnerNote::where("partner_id","=",$partner_id)->where("note_id","=",$id)->first();
        if(!$note) {
			abort(404); 
		}
		
        return view('admin.partners.notes.create-edit',[ 
            'partner' => $partner,
			'admins'=>$admins,
			'record' => $note
        ]);
    }
	
	public function notesUpdate(Request $request, Images $imagesModel, $account, $partner_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$note = PartnerNote::where("partner_id","=",$partner_id)->where("note_id","=",$id)->first();
        if(!$note) {
			return 'alert("'.trans('admin.error').'");';
		}
        
		if(!is_array($note->note_files)) {
			$note->note_files = [];
		}
		
        $data = $request->input('data');
        
		if(!isset($data['note_files'])) {
			$data['note_files'] = [];
		} 
		
		$data['note_files'] = array_values($data['note_files']);
		
		$willbedeleted = array_diff($note->note_files,$data['note_files'] );
		if(is_array($willbedeleted)) {
			foreach($willbedeleted as $del) {
				$imagesModel->deleteSingleImage($del);
			}
		}
		
		$note->update([
			'note_title'=>$data['title'],
			'note_text'=>$data['text'],
			'note_files'=>isset($data['note_files'])?$data['note_files']:[]
		]);
		
        Session::flash('result', trans('admin.partners.notes.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function notesDestroy(Images $imagesModel, $account, $partner_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$note = PartnerNote::where("partner_id","=",$partner_id)->where("note_id","=",$id)->first();
        if(!$note) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		if(is_array($note->note_files)) {
			foreach($note->note_files as $del) {
				$imagesModel->deleteSingleImage($del);
			}
		}
			
        $note->delete();
        
        Session::flash('result', trans('admin.partners.notes.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesCreate($account, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.create")) {
			return view('admin.unauthorized');
		}
		
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$currencies = Currency::currentAccount()->orderBy("currency_order","asc")->get();
		$socialmedias = SocialMedia::orderBy("sm_order","asc")->get(); 
		
		$countries = Country::orderBy("country_name","asc")->get();
		
		
        return view('admin.partners.campuses.create-edit',[ 
			'partner'=>$partner,
			'currencies'=>$currencies,
			'countries'=>$countries,
            'title' => trans('admin.partners.campuses.create'),
			'uniqid'=>uniqid('tmp_'),
			'socialmedias'=>$socialmedias,
        ]);
    }
	
    public function campusesStore(Request $request, $account, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
   
        $me = Auth::guard('admin')->user();
		
        $data = $request->input('data');
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		$data['partner_id'] = $partner->partner_id;
		$data['campus_order'] = 99999;
		
		if(isset($data['currency_id']) && empty($data['currency_id'])) {
			$data['currency_id'] = null;
		}
		
		if(isset($data['country_id']) && empty($data['country_id'])) {
			$data['country_id'] = null;
		}
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$new = PartnerCampus::create($data);
		
        
        Session::flash('result', trans('admin.partners.campuses.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesShow($account, $partner_id, $id)
    {
		if(!GeneralHelper::checkPrivilege("partners.campuses.view")) {
			return view('admin.unauthorized');
		}
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404); 
		} 
				
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$logs = Log::where(function($q) use ($id){
	        $q->where("log_type","LIKE","PartnerCampus")
	        	->where("log_record_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusCourse")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusAccommodation")
					->where("log_record_parent_id","=",$id);
	        })->orWhere(function($q) use ($id) {
		        $q->where("log_type","LIKE","PartnerCampusService")
					->where("log_record_parent_id","=",$id);
			})->orderBy("created_at","desc")->get();
		
        return view('admin.partners.campuses.show',[
            'record'=>$campus,
            'menu_active' => 'partners',
            'title' => trans('admin.partners.campuses.show'),
            'logs'=>$logs
        ]);
    }
	
	public function campusesEdit($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$socialmedias = SocialMedia::orderBy("sm_order","asc")->get();
		$currencies = Currency::currentAccount()->orderBy("currency_order","asc")->get();
		
		$countries = Country::orderBy("country_name","asc")->get();
		
        return view('admin.partners.campuses.create-edit',[ 
            'partner' => $partner,
			'socialmedias'=>$socialmedias,
			'currencies'=>$currencies,
			'countries'=>$countries,
			'record' => $campus
        ]);
    }
	
	public function campusesUpdate(Request $request, Images $imagesModel, $account, $partner_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
        
        $data = $request->input('data');
		$usedefaultcommission = $request->input('usedefaultcommission',false);
        $socialmedias = $request->input('socialmedias', []);
        
		if(isset($data['currency_id']) && empty($data['currency_id'])) {
			$data['currency_id'] = null;
		}
		if(isset($data['country_id']) && empty($data['country_id'])) {
			$data['country_id'] = null;
		}
		if(isset($data['place_id']) && empty($data['place_id'])) {
			$data['place_id'] = null;
		}
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		if($campus->campus_logo != $data['campus_logo'] && !is_null($campus->campus_logo) && !empty($campus->campus_logo)) {
			$imagesModel->deleteSingleImage($campus->campus_logo);
		}
		
		if(!is_array($socialmedias)) {
			$socialmedias = [];
		}
		
		$data['campus_socialmedias'] = $socialmedias;
		
		$campus->update($data);
		
		
		
        Session::flash('result', trans('admin.partners.campuses.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function campusesHighlightsEdit($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
        return view('admin.partners.campuses.highlights-create-edit',[ 
            'partner' => $partner,
			'record' => $campus
        ]);
    }
	
	public function campusesHighlightsUpdate(Request $request, Images $imagesModel, $account, $partner_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        $highlights = $request->input('highlights', []);
		
		if(!is_array($highlights)) {
			$highlights = [];
		}
		
		$data['campus_highlights'] = $highlights;
		
		$campus->update($data);
		
		
		
        Session::flash('result', trans('admin.partners.campuses.highlights.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function campusesNationalityMixEdit($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
        return view('admin.partners.campuses.nationality-mix-create-edit',[ 
            'partner' => $partner,
			'record' => $campus
        ]);
    }
	
	public function campusesNationalityMixUpdate(Request $request, Images $imagesModel, $account, $partner_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        $highlights = $request->input('nationalities', []);
		
		if(!is_array($highlights)) {
			$highlights = [];
		}
		
		$data['campus_nationalitymix'] = $highlights;
		
		$campus->update($data);
		
		
		
        Session::flash('result', trans('admin.partners.campuses.nationality-mix.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function campusesDescriptionEdit($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
        return view('admin.partners.campuses.description-create-edit',[ 
            'partner' => $partner,
			'record' => $campus
        ]);
    }
	
	public function campusesDescriptionUpdate(Request $request, Images $imagesModel, $account, $partner_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        $data = $request->input('data', []);
		
		$campus->update($data);
		
        Session::flash('result', trans('admin.partners.campuses.description.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function campusesAwardsCreate($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
        return view('admin.partners.campuses.awards-create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'uniqid' => uniqid('tmp_')
        ]);
    }
	
	public function campusesAwardsStore(Request $request, $account, $partner_id, $id)
    {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");'; 
		}
   
		$data = $request->input('data');
		
		$data['campus_id'] = $id;
		
		$data['award_order'] = $id;
		
		$award = PartnerCampusAward::create($data);
        
		if(!empty($award->award_image)) {
            $uid = $request->input('uniqid');
            $award->award_image = UploadHelper::processFileBeforeSave($award->award_image,'',$uid,$award->award_id);
            $award->save();
        }
		
        Session::flash('result', trans('admin.partners.campuses.awards.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesAwardsEdit($account, $partner_id, $campus_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$award = PartnerCampusAward::find($id);
		
		if(!$award || $award->campus_id != $campus_id) {
			abort(404);
		}
		
		
        return view('admin.partners.campuses.awards-create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'record'=>$award
        ]);
    }
	
	public function campusesAwardsUpdate(Request $request, Images $imagesModel, $account, $partner_id, $campus_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$data = $request->input('data');
		
		$award = PartnerCampusAward::find($id);
		
		if(!$award || $award->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		}  
		 
		if($award->award_image != $data['award_image'] && !is_null($award->award_image) && !empty($award->award_image)) {
			$imagesModel->deleteSingleImage($award->award_image);
		}
		$award->update($data);
		
		
        Session::flash('result', trans('admin.partners.campuses.awards.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	public function campusesAwardsDestroy($account, $partner_id, $campus_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$award = PartnerCampusAward::find($id);
		
		if(!$award || $award->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		} 
		
		$imagesModel->deleteSingleImage($award->award_image);
		
        $award->delete();
        
        Session::flash('result', trans('admin.partners.campuses.awards.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesDestroy($account, $partner_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$note = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$note) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        $note->delete();
        
        Session::flash('result', trans('admin.partners.campuses.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesCoursesIndex($account, $partner_id, $campus_id) {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$campuses = PartnerCampusCourse::where('campus_id', '=', $campus_id)->orderBy("course_order","asc")->paginate(50);
		
		return view('admin.partners.campuses.courses.index',[ 
            'partner' => $partner,
			'record' => $campus,
			'courses' => $campuses
        ]);
	}
	
	public function campusesCoursesCreate($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
        return view('admin.partners.campuses.courses.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'types'=>$types,
			'languages'=>$languages,
			'uniqid' => uniqid('tmp_')
        ]);
    }
	public function campusesCoursesStore(Request $request, $account, $partner_id, $id)
    {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");'; 
		}
   
		$data = $request->input('data');
		$extra_free_services = $request->input('extra_free_services',[]);
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$data['campus_id'] = $id;
		
		if(isset($data['language_id']) && empty($data['language_id'])) {
			unset($data['language_id']);
		}
		
		$data['course_order'] = 99999;
		
		$data['extra_services'] = $extra_free_services;
		
		$award = PartnerCampusCourse::create($data); 
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			foreach($prices as $price) {
				$prData=[
					'course_id'=>$award->course_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type']
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} else {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				}
				
				PartnerCampusCoursePrice::create($prData);
			}
		}
		 
		
        Session::flash('result', trans('admin.partners.campuses.courses.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesCoursesEdit($account, $partner_id, $campus_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$course = PartnerCampusCourse::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			abort(404);
		}
		
		$types = CourseType::orderBy('type_order','asc')->get();
		$languages = CourseLanguage::orderBy('language_order','asc')->get();
		
		
        return view('admin.partners.campuses.courses.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'record'=>$course,
			'types'=>$types,
			'languages'=>$languages
        ]);
    }
	
	public function campusesCoursesUpdate(Request $request, Images $imagesModel, $account, $partner_id, $campus_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$data = $request->input('data');
		$extra_free_services = $request->input('extra_free_services',[]);
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$course = PartnerCampusCourse::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		}  
		
		if(isset($data['language_id']) && empty($data['language_id'])) {
			$data['language_id'] = null;
		}
		
		$data['extra_services'] = $extra_free_services;
				
		$course->update($data); 
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			$keys = array_keys($prices);
			
			PartnerCampusCoursePrice::where("course_id","=",$course->course_id)->whereNotIn("price_id",$keys)->delete();
			
			foreach($prices as $pid=>$price) {
				$prData=[
					'course_id'=>$course->course_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type'],
					'price_duration_unit'=>null,
					'price_duration_type'=>null,
					'price_duration'=>null,
					'price_duration_range_min'=>null,
					'price_duration_range_max'=>null
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} else {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				}
				
				$priceDB=PartnerCampusCoursePrice::find($pid);
				
				if($priceDB) {
					$priceDB->update($prData);
				} else {
					PartnerCampusCoursePrice::create($prData);
				}
			}
			
		}
		
        Session::flash('result', trans('admin.partners.campuses.courses.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	public function campusesCoursesDestroy($account, $partner_id, $campus_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$course = PartnerCampusCourse::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		} 
		
        $course->delete();
        
        Session::flash('result', trans('admin.partners.campuses.courses.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesCoursesSort(Request $request, $account, $partner_id, $campus_id) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        foreach($items as $order=>$prop_id) {
            $group = PartnerCampusCourse::find($prop_id);
            if($group && $group->campus_id == $campus_id) {
                $group->course_order = $order;
                $group->save();
            }
        }
        return "";
    }
	public function campusesAccommodationsIndex($account, $partner_id, $campus_id) {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$campuses = PartnerCampusAccommodation::where('campus_id', '=', $campus_id)->orderBy("accommodation_order","asc")->paginate(50);
		
		return view('admin.partners.campuses.accommodations.index',[ 
            'partner' => $partner,
			'record' => $campus,
			'accommodations' => $campuses
        ]);
	}
	
	public function campusesAccommodationsCreate($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$types = AccommodationType::orderBy('type_order','asc')->get(); 
		
        return view('admin.partners.campuses.accommodations.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'types'=>$types
        ]);
    }
	public function campusesAccommodationsStore(Request $request, $account, $partner_id, $id)
    {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");'; 
		}
   
		$data = $request->input('data');
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$data['campus_id'] = $id; 
		
		if(!isset($data['accommodation_images']) || !is_array($data['accommodation_images'])) {
			$data['accommodation_images'] = [];
		}
		
		$data['accommodation_order'] = 99999;
		
		$award = PartnerCampusAccommodation::create($data); 
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			foreach($prices as $price) {
				$prData=[
					'accommodation_id'=>$award->accommodation_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type']
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} else {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				}
				
				PartnerCampusAccommodationPrice::create($prData);
			}
		}
		
        Session::flash('result', trans('admin.partners.campuses.accommodations.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesAccommodationsEdit($account, $partner_id, $campus_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$course = PartnerCampusAccommodation::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			abort(404);
		}
		
		$types = AccommodationType::orderBy('type_order','asc')->get(); 
		
		
        return view('admin.partners.campuses.accommodations.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'record'=>$course,
			'types'=>$types
        ]);
    }
	
	public function campusesAccommodationsUpdate(Request $request, Images $imagesModel, $account, $partner_id, $campus_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$data = $request->input('data');
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$course = PartnerCampusAccommodation::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		}   
		
		if(!isset($data['accommodation_images']) || !is_array($data['accommodation_images'])) {
			$data['accommodation_images'] = [];
		}
		
		if(is_array($course->accommodation_images)) {
			foreach($course->accommodation_images as $img) {
				if(!in_array($img, $data['accommodation_images'])) {
					$imagesModel->deleteSingleImage($img);
				}
			}
		}
		
		$course->update($data);
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			$keys = array_keys($prices);
			
			PartnerCampusAccommodationPrice::where("accommodation_id","=",$course->accommodation_id)->whereNotIn("price_id",$keys)->delete();
			
			foreach($prices as $pid=>$price) {
				$prData=[
					'accommodation_id'=>$course->accommodation_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type'],
					'price_duration_unit'=>null,
					'price_duration_type'=>null,
					'price_duration'=>null,
					'price_duration_range_min'=>null,
					'price_duration_range_max'=>null
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} else {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				}
				
				$priceDB= PartnerCampusAccommodationPrice::find($pid);
				
				if($priceDB) {
					$priceDB->update($prData);
				} else {
					PartnerCampusAccommodationPrice::create($prData);
				}
			}
			
		}
		
        Session::flash('result', trans('admin.partners.campuses.accommodations.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	public function campusesAccommodationsDestroy($account, $partner_id, $campus_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$course = PartnerCampusAccommodation::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		} 
		
        $course->delete();
        
        Session::flash('result', trans('admin.partners.campuses.accommodations.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesAccommodationsSort(Request $request, $account, $partner_id, $campus_id) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        foreach($items as $order=>$prop_id) {
            $group = PartnerCampusAccommodation::find($prop_id);
            if($group && $group->campus_id == $campus_id) {
                $group->accommodation_order = $order;
                $group->save();
            }
        }
        return "";
    }
	
	public function campusesPromotionsIndex($account, $partner_id, $campus_id) {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$campuses = Promotion::where('campus_id', '=', $campus_id)->orderBy('promotion_start','asc')->orderBy("promotion_end","asc")->paginate(50);
		
		return view('admin.partners.campuses.promotions.index',[ 
            'partner' => $partner,
			'record' => $campus,
			'promotions' => $campuses
        ]);
	}
	
	public function campusesServicesIndex($account, $partner_id, $campus_id) {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$campuses = PartnerCampusService::where('campus_id', '=', $campus_id)->orderBy("service_order","asc")->paginate(50);
		
		return view('admin.partners.campuses.services.index',[ 
            'partner' => $partner,
			'record' => $campus,
			'services' => $campuses
        ]);
	}
	
	public function campusesServicesCreate($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
	 
        return view('admin.partners.campuses.services.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus 
        ]);
    }
	public function campusesServicesStore(Request $request, $account, $partner_id, $id)
    {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");'; 
		}
   
		$data = $request->input('data');
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$data['campus_id'] = $id; 
		
		$data['service_order'] = 99999;
		
		$award = PartnerCampusService::create($data); 
		
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			foreach($prices as $price) {
				$prData=[
					'service_id'=>$award->service_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type']
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} elseif($price['type'] == 'fixed') {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				} elseif($price['type'] == 'onetime') {
					//onetime
				}
				
				PartnerCampusServicePrice::create($prData);
			}
		}
		
		
        Session::flash('result', trans('admin.partners.campuses.services.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	
	public function campusesServicesEdit($account, $partner_id, $campus_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			abort(404); 
		}
		
		$course = PartnerCampusService::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			abort(404);
		}
		
	 
		
        return view('admin.partners.campuses.services.create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'record'=>$course
        ]);
    }
	
	public function campusesServicesUpdate(Request $request, Images $imagesModel, $account, $partner_id, $campus_id, $id)
    { 
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$data = $request->input('data');
		
		$usedefaultcommission = $request->input('usedefaultcommission',false);
		
		if($usedefaultcommission) {
			$data['commission']=null;
		}
		
		$course = PartnerCampusService::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		}   
		
		$course->update($data);
		
		$prices = $request->input('prices', []);
		if(is_array($prices)) {
			$keys = array_keys($prices);
			
			PartnerCampusServicePrice::where("service_id","=",$course->service_id)->whereNotIn("price_id",$keys)->delete();
			
			foreach($prices as $pid=>$price) {
				$prData=[
					'service_id'=>$course->service_id,
					'price_amount'=>$price['price'],
					'price_type'=>$price['type'],
					'price_duration_unit'=>null,
					'price_duration_type'=>null,
					'price_duration'=>null,
					'price_duration_range_min'=>null,
					'price_duration_range_max'=>null
				];
				
				if(substr($price['type'],0,4) == 'per_') {
					//per price
					$prData['price_duration_unit'] = str_replace('per_','',$price['type']);
					$prData['price_duration_type'] = $price['duration_type'];
					
					if($price['duration_type']=='longer_than') {
						$prData['price_duration_range_min'] = $price['duration_longer_than'];
					}
					if($price['duration_type']=='range') {
						$prData['price_duration_range_min'] = $price['duration_range']['min'];
						$prData['price_duration_range_max'] = $price['duration_range']['max'];
					}
					//price_duration_type
				} elseif($price['type']=='fixed') {
					//fixed price
					$prData['price_duration_range_min'] = $price['duration_fixed']['duration'];
					$prData['price_duration_range_max'] = $price['duration_fixed']['duration'];
					$prData['price_duration_unit'] = $price['duration_fixed']['unit'];
				} else {
					//one time
					
				}
				
				$priceDB= PartnerCampusServicePrice::find($pid);
				
				if($priceDB) {
					$priceDB->update($prData);
				} else {
					PartnerCampusServicePrice::create($prData);
				}
			}
			
		}
		
        Session::flash('result', trans('admin.partners.campuses.services.update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	public function campusesServicesDestroy($account, $partner_id, $campus_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$course = PartnerCampusService::find($id);
		
		if(!$course || $course->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		} 
		
        $course->delete();
        
        Session::flash('result', trans('admin.partners.campuses.services.delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesServicesSort(Request $request, $account, $partner_id, $campus_id) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
        foreach($items as $order=>$prop_id) {
            $group = PartnerCampusService::find($prop_id);
            if($group && $group->campus_id == $campus_id) {
                $group->service_order = $order;
                $group->save();
            }
        }
        return "";
    }
	public function campusesImagesCreate($account, $partner_id, $id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.update")) {
			return view('admin.unauthorized');
		}
		
        $partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			abort(404); 
		}
		
        return view('admin.partners.campuses.images-create-edit',[ 
            'partner' => $partner,
			'campus' => $campus,
			'uniqid' => uniqid('tmp_')
        ]);
    }
	public function campusesImagesStore(Request $request, $account, $partner_id, $id)
    {
		
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");'; 
		}
   
		$images = $request->input('images',[]);
		
		$uid = $request->input('uniqid');
		
		if(!is_array($images) || count($images)==0) {
			return 'alert("'.trans('admin.partners.campuses.images.no_image_selected').'");'; 
		}
		
		foreach($images as $image) {
			$data = [
				'campus_id'=>$id,
				'image_file'=>$image,
				'image_order'=>99999
			];
			
			$newImg = PartnerCampusImage::create($data);
            $newImg->image_file = UploadHelper::processFileBeforeSave($newImg->image_file,'',$uid, $id);
		} 
		
        Session::flash('result', trans('admin.partners.campuses.images.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function campusesImagesDestroy(Images $imagesModel, $account, $partner_id, $campus_id, $id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$partner_id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$campus = PartnerCampus::where("partner_id","=",$partner_id)->where("campus_id","=",$campus_id)->first();
        if(!$campus) {
			return 'alert("'.trans('admin.error').'");';
		}
		
		$image = PartnerCampusImage::find($id);
		
		if(!$image || $image->campus_id != $campus_id) {
			return 'alert("'.trans('admin.error').'");';
		} 
		
		$imagesModel->deleteSingleImage($image->image_file);
		
        $image->delete();
        
        Session::flash('result', trans('admin.partners.campuses.images.delete_success_text')); //<--FLASH MESSAGE
        return 'closeModal();$("#imagegal_'.$image->image_id.'").remove();';
    }
	
	public function campusesCopy($account, $id, $campus_id)
    {  
		if(!GeneralHelper::checkPrivilege("partners.campuses.create")) {
			return view('admin.unauthorized');
		}
		
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			abort(404);
		}
		
		$campus = $partner->campuses()->where("campus_id","=",$campus_id)->first();
		if(!$campus) {
			abort(404);
		}  
		
        return view('admin.partners.campuses.copy',[ 
			'campus'=>$campus
        ]);
    }
	
    public function campusesCopyAction(Request $request, $account, $id, $campus_id)
    {
		$partner = Partner::currentAccount()->where("partner_id","=",$id)->first();
		if(!$partner) {
			return 'alert("'.trans('admin.error').'");';
		}
   
        $campus = $partner->campuses()->where("campus_id","=",$campus_id)->first();
		if(!$campus) {
			abort(404);
		}  
		
		$new_campus_name = $request->input('campus_name','');
 
		$new_campus = $campus->replicate();
		$new_campus->campus_name = $new_campus_name;
		
		$max_ord = $partner->campuses()->max("campus_order");
		$new_campus->campus_order = $max_ord+1;
		$new_campus->save();
		
		 
		if(!empty($new_campus->campus_logo)) { 
			
			$newImg = UploadHelper::copyImages($new_campus->campus_logo,null, dirname($new_campus->campus_logo), $partner->account_id.'/campus/'.$new_campus->campus_id);
			$new_campus->campus_logo = $newImg;
			$new_campus->save();
		}
        
		
		foreach($campus->images as $img) {
			$ni = $img->replicate();
			$ni->campus_id = $new_campus->campus_id; 
			
			$ni->image_file = UploadHelper::copyImages($ni->image_file,null, dirname($ni->image_file), $partner->account_id.'/campus/'.$new_campus->campus_id);
			$ni->save();			
		} 
		foreach($campus->awards as $img) {
			$ni = $img->replicate();
			$ni->campus_id = $new_campus->campus_id; 
			$ni->save();
			
			$ni->award_image = UploadHelper::copyImages($ni->award_image,null, dirname($ni->award_image), $partner->account_id.'/award/'.$ni->award_id);
			$ni->save();			
		} 
		foreach($campus->courses as $item) {
			$ni = $item->replicate();
			$ni->campus_id = $new_campus->campus_id; 
			$ni->save();
			foreach($item->prices as $pr) {
				$np = $pr->replicate();
				$np->course_id = $ni->course_id;
				$np->save();
			}
		}
		foreach($campus->accommodations as $item) {
			$ni = $item->replicate();
			$ni->campus_id = $new_campus->campus_id; 
			$ni->save();
			foreach($item->prices as $pr) {
				$np = $pr->replicate();
				$np->accommodation_id = $ni->accommodation_id;
				$np->save();
			}
		}
		foreach($campus->services as $item) {
			$ni = $item->replicate();
			$ni->campus_id = $new_campus->campus_id; 
			$ni->save();
			foreach($item->prices as $pr) {
				$np = $pr->replicate();
				$np->service_id = $ni->service_id;
				$np->save();
			}
		}
		
        Session::flash('result', trans('admin.partners.campuses.copy_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
}
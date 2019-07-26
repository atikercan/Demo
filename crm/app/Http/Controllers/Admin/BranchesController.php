<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\TmpCountry;
use App\Models\TmpState;
use App\Models\Location;
use App\Models\Place;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\Pipeline;
use App\Models\PipelineStatus;
use App\Models\PipelineStatusChecklist;
use App\Models\CustomFieldOption;
use App\Models\System\Images;
use Session; 
use App\Helpers\GeneralHelper;

class BranchesController extends Controller {
    
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
		/*
		$countries = TmpCountry::orderBy("name","asc")->skip(250)->take(50)->get();
		
		$ord = 52679;
		foreach($countries as $country) {
			$name = $country->name;
			$newCid = Place::create([
				'place_name'=>$country->name,
				'place_longname'=>$name,
				'place_order'=>$ord
			]);
			$ord++;
			foreach($country->states as $state) {
				$name = $state->name.", ".$country->name;
				$newSid = Place::create([
					'place_name'=>$state->name,
					'parent_id'=>$newCid->place_id,
					'place_longname'=>$name,
					'place_order'=>$ord
				]);
				$ord++;
				foreach($state->cities as $city) {
					$name = $city->name.", ".$state->name.", ".$country->name;
					Place::create([
						'place_name'=>$city->name,
						'parent_id'=>$newSid->place_id,
						'place_longname'=>$name,
						'place_order'=>$ord
					]);	
					$ord++;
				}
			}
		}
		echo $ord;
		
		die(); */
        $me = Auth::guard('admin')->user();
        
        $filters = $request->input('filters',[]);
        $branches = Branch::currentAccount()->orderBy('branch_order','asc');
      
        
        if(isset($filters['s']) && !empty($filters['s'])) {
            $branches->where(function($q) use ($filters) {
            //    $q->where('currency_name','LIKE','%'.$filters['s'].'%'); 
            //    $q->orWhere('currency_code','LIKE','%'.$filters['s'].'%'); 
            });
        } 
        
        return view('admin.branches.index',[
            'records'=>$branches->get(),
            'menu_active' => 'branches',
            'title' => trans('admin.branches.branches')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {  
		if(!GeneralHelper::checkPrivilege("branches.create")) {
			return view('admin.unauthorized');
		}
		
        return view('admin.branches.create',[
            'menu_active' => 'branches', 
			'currencies'=>Currency::currentAccount()->orderBy('currency_order','asc')->get(),
            'title' => trans('admin.branches.create')
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
   
        $me = Auth::guard('admin')->user(); 
        
        $data['branch_order'] = 99999; 
        
		if(!isset($data['currency_id']) || empty($data['currency_id'])) {
			$data['currency_id'] = null;
		} 
        $admin = Branch::create($data);  
        
        Session::flash('result', trans('admin.branches.create_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($account, $id)
    {  
		$branches = \Auth::guard()->user()->branches()->pluck("branches.branch_id")->all();
		
		if( !(GeneralHelper::checkPrivilege("branches.update") || ( GeneralHelper::checkPrivilege("branches.selfupdate") && in_array($id, $branches) ))  ) {
			return view('admin.unauthorized');
		}
        $admin = Branch::find($id);
        
        if(!$admin  ) {
            Session::flash('fail', trans('admin.branches.couldnt_find_record'));
            return redirect(\RouteHelper::route('admin.branches.index'));
        } 
        
        return view('admin.branches.create-edit',[
            'record'=>$admin,
            'menu_active' => 'branches',
			'currencies'=>Currency::currentAccount()->orderBy('currency_order','asc')->get(),
            'title' => trans('admin.branches.edit')
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
        
        $admin = Branch::find($id);
        
        if(!$admin) {
            return 'alert("'.trans('admin.branches.save_error').'");';
        }
        
        $data = $request->input('data');
		
		
		if($admin->branch_logo != $data['branch_logo'] && !is_null($admin->branch_logo) && !empty($admin->branch_logo)) {
			$imagesModel->deleteSingleImage($admin->branch_logo);
		}
		
        $admin->update($data); 
        
        Session::flash('result', trans('admin.branches.update_success_text')); //<--FLASH MESSAGE
        
        return 'document.location.href="#'.\RouteHelper::route('admin.branches.index',[],false).'";';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($account, $id)
    {
		if(!GeneralHelper::checkPrivilege("branches.delete")) {
			return 'alert("'.trans('admin.no_action_privilege').'")';
		}
		
        $branch = Branch::currentAccount()->where("branch_id","=",$id)->first();
        if($branch) {
			$branch->delete();
			Session::flash('result', trans('admin.branches.delete_success_text')); //<--FLASH MESSAGE
		}
        return 'reloadState();';
    }
    
    public function sort(Request $request) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
        foreach($items as $order=>$prop_id) {
            $group = Branch::find($prop_id);
            if($group) {
                $group->branch_order = $order;
                $group->save();
            }
        }
        return "";
    }
	
	public function customFieldsIndex(Request $request, $account, $branch_id)
    { 
        $me = Auth::guard('admin')->user();
        
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			Session::flash('fail', trans('admin.branches.couldnt_find_record'));
            return redirect(\RouteHelper::route('admin.branches.index'));
		}
        
        $fields = CustomField::where("branch_id","=",$branch_id)->orderBy('field_order','asc');
        
		$filters = $request->input('filters',[]);
        if(isset($filters['s']) && !empty($filters['s'])) {
            $fields->where(function($q) use ($filters) {
            //    $q->where('currency_name','LIKE','%'.$filters['s'].'%'); 
            //    $q->orWhere('currency_code','LIKE','%'.$filters['s'].'%'); 
            });
        } 
        
        return view('admin.branches.custom-fields',[
            'records'=>$fields->get(),
			'branch'=>$branch,
            'menu_active' => 'branches',
            'title' => trans('admin.branches.custom-fields')
        ]);
    }
	
	public function customFieldsCreate($account, $branch_id)
    {  
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			abort(404);
		}
		
        return view('admin.branches.custom-fields-create-edit',[ 
            'branch' => $branch
        ]);
    }
	
	public function customFieldsStore(Request $request, $account, $branch_id)
    { 
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			return 'alert("'.trans('admin.error').'");';
		}
        $data = $request->input('data');
        $options = $request->input('options', []);
   
        $me = Auth::guard('admin')->user(); 
        
        $data['field_order'] = 99999;
		$data['branch_id'] = $branch_id;
		
        $admin = CustomField::create($data);  
        
		$all_options = [];
		if(is_array($options) && count($options)>0 && ($data['field_type']=='dropdown' || $data['field_type']=='multidropdown')) {
			$order = 0;
			foreach($options as $oid=>$option) {
				if(substr($oid,0,3) == 'new') {
					$newOpt = CustomFieldOption::create([
						'field_id'=>$admin->field_id,
						'option_name'=>$option,
						'option_order'=>$order
					]);
					$all_options[] = $newOpt->option_id;
				} else {
					$newOpt = CustomFieldOption::find($oid);
					if($newOpt) {
						$newOpt->update([
							'option_name'=>$option,
							'option_order'=>$order
						]);
						$all_options[] = $newOpt->option_id;
					} 
				}
				$order++;
			}
		}
		CustomFieldOption::where("field_id","=",$admin->field_id)->whereNotIn("option_id",$all_options)->delete();
		
        Session::flash('result', trans('admin.branches.custom_fields_create_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function customFieldsEdit($account, $branch_id, $id)
    {  
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
        
        if(!$branch) {
			abort(404); 
		}
		
		$field = CustomField::where("branch_id","=",$branch_id)->where("field_id","=",$id)->first();
        if(!$field) {
			abort(404); 
		}
		
        return view('admin.branches.custom-fields-create-edit',[ 
            'branch' => $branch,
			'record' => $field
        ]);
    }
	
	public function customFieldsUpdate(Request $request, $account, $branch_id, $id)
    { 
        
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
        
        if(!$branch) {
            return 'alert("'.trans('admin.branches.custom_fields_save_error').'");';
        }
		
		$field = CustomField::where("branch_id","=",$branch_id)->where("field_id","=",$id)->first();
        if(!$field) {
			return 'alert("'.trans('admin.branches.custom_fields_save_error').'");';
		}
        
        $data = $request->input('data');
        $field->update($data); 
		
		
        $options = $request->input('options', []);
		
		$all_options = [];
		if(is_array($options) && count($options)>0 && ($field->field_type=='dropdown' || $field->field_type=='multidropdown')) {
			$order = 0;
			foreach($options as $oid=>$option) {
				if(substr($oid,0,3) == 'new') {
					$newOpt = CustomFieldOption::create([
						'field_id'=>$field->field_id,
						'option_name'=>$option,
						'option_order'=>$order
					]);
					$all_options[] = $newOpt->option_id;
				} else {
					$newOpt = CustomFieldOption::find($oid);
					if($newOpt) {
						$newOpt->update([
							'option_name'=>$option,
							'option_order'=>$order
						]);
						$all_options[] = $newOpt->option_id;
					} 
				}
				$order++;
			}
		}
		CustomFieldOption::where("field_id","=",$field->field_id)->whereNotIn("option_id",$all_options)->delete();
        
        Session::flash('result', trans('admin.branches.custom_fields_update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function customFieldsDestroy($account, $branch_id, $id)
    {
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
            return 'alert("'.trans('admin.branches.custom_fields_save_error').'");';
        }
		
        $field = CustomField::where("branch_id","=",$branch_id)->where("field_id","=",$id)->first();
        
        $field->delete();
        
        Session::flash('result', trans('admin.branches.custom_fields_delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function customFieldsSort(Request $request) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
        foreach($items as $order=>$prop_id) {
            $group = CustomField::find($prop_id);
            if($group) {
                $group->field_order = $order;
                $group->save();
            }
        }
        return "";
    }
	
	public function pipelinesIndex(Request $request, $account, $branch_id)
    { 
        $me = Auth::guard('admin')->user();
        
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			Session::flash('fail', trans('admin.branches.couldnt_find_record'));
            return redirect(\RouteHelper::route('admin.branches.index'));
		}
        
        $fields = Pipeline::where("branch_id","=",$branch_id)->orderBy('pipeline_order','asc');
        
		$filters = $request->input('filters',[]);
        if(isset($filters['s']) && !empty($filters['s'])) {
            $fields->where(function($q) use ($filters) {
            //    $q->where('currency_name','LIKE','%'.$filters['s'].'%'); 
            //    $q->orWhere('currency_code','LIKE','%'.$filters['s'].'%'); 
            });
        } 
        
        return view('admin.branches.pipelines',[
            'records'=>$fields->get(),
			'branch'=>$branch,
            'menu_active' => 'branches',
            'title' => trans('admin.branches.pipelines')
        ]);
    }
	
	public function pipelinesCreate($account, $branch_id)
    {  
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			abort(404);
		}
		
        return view('admin.branches.pipelines-create-edit',[ 
            'branch' => $branch
        ]);
    }
	
	public function pipelinesStore(Request $request, $account, $branch_id)
    { 
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
			return 'alert("'.trans('admin.error').'");';
		}
        $data = $request->input('data');
        $stages = $request->input('stages', []);
		
        $data['pipeline_order'] = 99999;
		
		$data['branch_id'] = $branch_id;
		
		
        $admin = Pipeline::create($data);  
        
		$ord=0;
		foreach($stages as $stage) {
			$ord2 = 0;
			$dbStage = PipelineStatus::create([
				'pipeline_id'=>$admin->pipeline_id,
				'status_name'=>$stage['name'],
				'status_order'=>$ord
			]);
			
			if(isset($stage['checklists']) && is_array($stage['checklists'])) {
				$storedCheclistIDs = [];
				foreach($stage['checklists'] as $cid=>$checklist) {
					$checklistData = [
						'status_id'=>$dbStage->status_id,
						'checklist_name'=>$checklist,
						'checklist_order'=>$ord2
					];
					
					$dbChecklist = PipelineStatusChecklist::find($cid);
			 
					if($dbChecklist) {
						$dbChecklist->update($checklistData);
					} else {
						$dbChecklist = PipelineStatusChecklist::create($checklistData);
					}
					 
					$ord2++;
					//
				}
				
			}
			$ord++;
		}
		
        Session::flash('result', trans('admin.branches.pipelines_create_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function pipelinesEdit($account, $branch_id, $id)
    {  
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
        
        if(!$branch) {
			abort(404); 
		}
		
		$field = Pipeline::where("branch_id","=",$branch_id)->where("pipeline_id","=",$id)->first();
        if(!$field) {
			abort(404); 
		}
		
        return view('admin.branches.pipelines-create-edit',[ 
            'branch' => $branch,
			'record' => $field
        ]);
    }
	
	public function pipelinesUpdate(Request $request, $account, $branch_id, $id)
    { 
        
        $branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
        
        if(!$branch) {
            return 'alert("'.trans('admin.branches.pipelines_save_error').'");';
        }
		
		$admin = Pipeline::where("branch_id","=",$branch_id)->where("pipeline_id","=",$id)->first();
        if(!$admin) {
			return 'alert("'.trans('admin.branches.pipelines_save_error').'");';
		}
        
        $data = $request->input('data');
        $admin->update($data); 
		
		$stages = $request->input('stages', []);
		
		$ord=0;
		$storedIDs = [0];
		foreach($stages as $sid=>$stage) {
			$ord2 = 0;
			
			$dbStage = PipelineStatus::find($sid);
			
			$stageData = [
				'pipeline_id'=>$admin->pipeline_id,
				'status_name'=>$stage['name'],
				'status_order'=>$ord
			];
			
			if($dbStage) {
				$dbStage->update($stageData);
			} else {
				$dbStage = PipelineStatus::create($stageData);
			}
			$storedIDs[] = $dbStage->status_id;
			
			$storedCheclistIDs = [0];
			if(isset($stage['checklists']) && is_array($stage['checklists'])) {
				foreach($stage['checklists'] as $cid=>$checklist) {
					$checklistData = [
						'status_id'=>$dbStage->status_id,
						'checklist_name'=>$checklist,
						'checklist_order'=>$ord2
					];
					
					$dbChecklist = PipelineStatusChecklist::find($cid);
			 
					if($dbChecklist) {
						$dbChecklist->update($checklistData);
					} else {
						$dbChecklist = PipelineStatusChecklist::create($checklistData);
					}
					$storedCheclistIDs[] = $dbChecklist->checklist_id;
					
					$ord2++;
					//
				}
			}
			PipelineStatusChecklist::where('status_id','=',$dbStage->status_id)->whereNotIn('checklist_id',$storedCheclistIDs)->delete();
			
			$ord++;
		}
		PipelineStatus::where('pipeline_id','=',$admin->pipeline_id)->whereNotIn('status_id',$storedIDs)->delete();
		
        Session::flash('result', trans('admin.branches.pipelines_update_success_text')); //<--FLASH MESSAGE
        return 'closeModal();reloadState();';
    }
	
	public function pipelinesDestroy($account, $branch_id, $id)
    {
		$branch = Branch::currentAccount()->where("branch_id","=",$branch_id)->first();
		if(!$branch) {
            return 'alert("'.trans('admin.branches.pipelines_save_error').'");';
        }
		
        $field = Pipeline::where("branch_id","=",$branch_id)->where("pipeline_id","=",$id)->first();
        
        $field->delete();
        
        Session::flash('result', trans('admin.branches.pipelines_delete_success_text')); //<--FLASH MESSAGE
        
        return 'closeModal();reloadState();';
    }
	public function pipelinesSort(Request $request) { 
        $items = $request->input('item');
        if(!is_array($items)) {
            return "";
        }
        
        foreach($items as $order=>$prop_id) {
            $group = Pipeline::find($prop_id);
            if($group) {
                $group->pipeline_order = $order;
                $group->save();
            }
        }
        return "";
    }
	public function setBranch($account, $id)
    {  
		$me = Auth::guard('admin')->user();
		
        $admin = Branch::find($id);
        
        if(!$admin  ) {
            return '';
        } 
        
		if(!$me->branches()->where('admins_branches.branch_id','=', $id)->exists()) {
			return '';
		}
		
		session(['active_branch'=>$admin->branch_id]);
		
        return '$(".branch-selection .dropdown.open").removeClass("open").find(".dropdown-menu").hide();$("#current_branch").html("'.$admin->branch_name.'");reloadState();';
    }
}
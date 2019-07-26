<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Session;
use App\User;
use App\Models\Entry;
use App\Models\Staff;
use App\Models\StaffOff;
use App\Models\StaffType;
use App\Helpers\GeneralHelper;
use Excel;
use PDF;
use View;
use Response;
use App\Exports\PayrollExport;

class PayrollController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
		if(!GeneralHelper::checkMenuPrivilege("payroll")) {
			return view('unauthorized');
		}
		
		$me = Auth::user();
		
		$export = $request->input('export',false);
		$filters = $request->input('filters',[]);
		$month = ( isset($filters['month']) )?$filters['month']:false;
		$year = ( isset($filters['year']) )?$filters['year']:false;
		$department = ( isset($filters['departmentId']) )?$filters['departmentId']:false;
		$type = ( isset($filters['typeId']) )?$filters['typeId']:false;
		
		$records = false;
		$ranges = false;
		
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		
		if($month && $year && $department) {
			$records = [];
			
			$start = $year."-".(($month<10)?'0'.$month:'')."-01";
			$end = date("Y-m-t", strtotime($start));
			
			$ranges = GeneralHelper::getReportRanges($start, $end);
			
			$staffs = Staff::where("departmentId",'=',$department)->whereIn("typeId",$typeIds);
			if($type) {
				$staffs->where("typeId","=",$type);
			}
			
			$staffs = $staffs->get();
			foreach($staffs as $staff) {
				$dat = [
					'staff'=>$staff,
					'works'=>[]
				];
				foreach($ranges as $range) { 
					$dat['works'][$range['day']] = $staff->getWorkStatus($range['day']);
				}
				$records[] = $dat;
			}
			if($export && $export=='xlsx') {
				ini_set("max_execution_time", '125000000'); 
				ini_set("memory_limit", '1250000000'); 
				
				$dbDepartment = \App\Models\Department::find($department);
				
				$payrollExport = new PayrollExport();
				
				$payrollExport->department = $dbDepartment;
				$payrollExport->records = $records;
				$payrollExport->ranges = $ranges;
				$payrollExport->month = $month;
				$payrollExport->year = $year;
				
				return $payrollExport->download('puantaj.xlsx');
				 
				 
			}
			if($export && $export=='csv') {
				ini_set("max_execution_time", '125000000'); 
				ini_set("memory_limit", '1250000000'); 
				
				$dbDepartment = \App\Models\Department::find($department);
				
				$payrollExport = new PayrollExport();
				
				$payrollExport->department = $dbDepartment;
				$payrollExport->records = $records;
				$payrollExport->ranges = $ranges;
				$payrollExport->month = $month;
				$payrollExport->year = $year;
				$payrollExport->csv = true;
				
				return $this->csv([
					'department'=>$dbDepartment,
					'records'=>$records,
					'ranges'=>$ranges,
					'month'=>$month,
					'year'=>$year
				]);
				 
				 
			}
			if($export && $export=='pdf') {
				$dbDepartment = \App\Models\Department::find($department);
				$pdf = PDF::loadView('payroll-export-pdf', [
					'department'=>$dbDepartment,
					'records'=>$records,
					'ranges'=>$ranges,
					'month'=>$month,
					'year'=>$year
				])->setPaper('a4', 'landscape')->setWarnings(false);
				return $pdf->download('puantaj.pdf');
			}
			if($export && $export=='html') {
				$dbDepartment = \App\Models\Department::find($department);
				$html = View::make('payroll-export-html',[
					'department'=>$dbDepartment,
					'records'=>$records,
					'ranges'=>$ranges,
					'month'=>$month,
					'year'=>$year
				])->render();
				$filename = storage_path() . '/'.time().'.txt';
				
				$f = fopen($filename,'w');
				fputs($f, $html);
				fclose($f);
				return response()->download($filename, "puantaj.html")->deleteFileAfterSend(true);
			}
		}
		
		$typeIds = Auth::user()->available_staff_types;
		if(!is_array($typeIds) || count($typeIds)==0) {
			$typeIds[] = 0;
		}
		$types = StaffType::whereIn("typeId",$typeIds)->orderBy("typeName")->get();
		
		$departments = $me->departments()->orderBy("departmentName","asc")->get();
		
        return view('payroll',[
			'title'=>trans('kpds.payroll.payroll'),
			'menu_active'=>'payroll', 
			'records'=>$records,
			'types'=>$types,
			'departments'=>$departments,
			'ranges'=>$ranges,
			'filters'=>$filters
		]);
    } 
	
	public function csv($data) {
		$recs = [];
		
		$columns = array(trans('kpds.payroll.staff'));
		foreach($data['ranges'] as $range) {
			$columns[] = date("d", strtotime($range['day']));
		}
		$columns[] =trans('kpds.payroll.total_worked');
		$columns[] =trans('kpds.payroll.total_extraworked');
		$columns[] =trans('kpds.payroll.total_vacationworked');
		
		$recs[] = $columns;
		
		foreach($data['records'] as $record) {
			$total = 0;
			$total_extra = 0;
			$total_vacation = 0;
			
			$row = [$record['staff']->staffName];
			
			foreach($data['ranges'] as $range) {
				$day = $record['works'][$range['day']];
				$staffStatus = "-";
				$vacationStatus = false;
				if(isset($record['works'][$range['day']])) {
					$staffStatus = ($record['works'][$range['day']]['staffStatus'])?"+":"-";
					$vacationStatus = $record['works'][$range['day']]['vacationStatus'];
					$workedHours =  $record['works'][$range['day']]['workedHours'];
				}

				if($day['workedHours']>0) {
					$total += $day['workedHours'];
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='İ') {	
						$total_vacation += $day['extraWorkedHours'];
					}
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='RT') {	
						$total_vacation += $day['extraWorkedHours'];
					}
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='HT') {	
						$total_vacation += $day['extraWorkedHours'];
					}
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='B') {	
						$total_vacation += $day['extraWorkedHours'];
					}
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='İİ') {	
						$total_vacation += $day['extraWorkedHours'];
					}
					if($day['dayStatus']=='vacation' && $day['vacationStatus']=='Yİ') {	
						$total_vacation += $day['extraWorkedHours'];
					}
				}
				
				if($vacationStatus && $vacationStatus) {
					$row[] = $vacationStatus;
				} else {
					$row[] = $staffStatus;
				}
			}
			$row[] = $total. " sa";
			$row[] = $total_extra. " sa";
			$row[] = $total_vacation. " sa";
			
			$recs[] = $row;
		}
		
		$headers = array(
			"Content-Encoding" => "UTF-8",
			"Content-type" => "text/csv; charset=UTF-8",
			"Content-Disposition" => "attachment; filename=puantaj.csv",
			"Pragma" => "no-cache",
			"Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
			"Expires" => "0"
		);
		
		$callback = function() use ($recs)
		{
			$file = fopen('php://output', 'w');
			fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
			foreach($recs as $rec) {
			fputcsv($file, $rec);
			}
			 
			fclose($file);
		};
	
		return Response::stream($callback, 200, $headers);
	}
}

<?php

namespace App\Models\Hotel;

use \Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

class HotelRoomTypePax extends Model
{   
    
    use SoftDeletes;
    
    public static function boot()
    {
        parent::boot(); 
        // Attach event handler, on deleting of the user
        HotelRoomTypePax::deleting(function($pax)
        {    
            HotelRoomTypePax::flushEventListeners();
            $pax->deleted_user_id = Auth::user()->id; 
            $pax->pax_status = 'deleted';
            $pax->save(['timestamps' => false]); 
            
            return true;
        });
        HotelRoomTypePax::updating(function($pax)
        {    
            HotelRoomTypePax::flushEventListeners();
            $pax->pax_status = 'edited';
            $pax->save();
            $p2 = $pax->replicate();
            $p2->created_user_id = Auth::user()->id; 
            $p2->pax_status = 'active';
            $p2->save();
            $pax->delete();  
            
            return false;
        });
    }
    
    protected $table = 'hotels_roomtypes_paxes';
    protected $primaryKey = 'pax_id';
    
 
    protected $dates = ['deleted_at', 'pax_start', 'pax_end', 'pax_sale_start', 'pax_sale_end']; 
    
    protected $fillable = array('pax_id','roomtype_id', 'parent_id', 'created_user_id', 'pax_sale_start', 'pax_sale_end', 'pax_start', 'pax_end', 'pax_adults', 'pax_children', 'pax_child1_start_age', 'pax_child1_end_age', 'pax_child2_start_age', 'pax_child2_end_age', 'pax_child3_start_age', 'pax_child3_end_age', 'pax_rate', 'pax_status','pax_showinpricelist','pax_child1_free','pax_child2_free','pax_child3_free');
    
    public function roomtype() {
        return $this->belongsTo('App\Models\Hotel\HotelRoomType', 'roomtype_id', 'roomtype_id');
    }
    
    public function user() {
        return $this->belongsTo('App\User', 'created_user_id', 'id');
    }  
    
    public function deleted_user() {
        return $this->belongsTo('App\User', 'deleted_user_id', 'id');
    }  
    
    public function olderversion() {
        return $this->belongsTo('App\Models\Hotel\HotelRoomTypePax', 'parent_id', 'pax_id')->withTrashed();
    }
    
    
    public function setPaxStartAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['pax_start'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['pax_start'] = null; 
        }
    }
    
    public function setPaxEndAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['pax_end'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['pax_end'] = null; 
        }
    } 
    
    public function setPaxSaleStartAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['pax_sale_start'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['pax_sale_start'] = null; 
        }
    }
    
    public function setPaxSaleEndAttribute($value)
    {
        if(strlen($value)>0) {
            $this->attributes['pax_sale_end'] = Carbon::createFromFormat('d.m.Y', $value);
        } else {
           $this->attributes['pax_sale_end'] = null; 
        }
    } 
    
    public function getAllVersions() {
       $allversions = [];
       
       $tmp = $this->olderversion;
       
       while($tmp) {
           $allversions[]=$tmp;
           $tmp = $tmp->olderversion;
       }
       $allversions = array_reverse($allversions);
       return $allversions;
    }
}
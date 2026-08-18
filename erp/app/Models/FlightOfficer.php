<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FlightOfficer extends Model
{
    use SoftDeletes;
    protected $fillable=['user_id','airline_id','work_roles','contact','experience','status'];
    protected $casts=['work_roles'=>'array'];
    public function user(){return $this->belongsTo(User::class);}
    public function airline(){return $this->belongsTo(Airline::class);}
}

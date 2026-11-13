<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Audition extends Model {
    protected $fillable=['reference','phase_id','selection_id','first_name','last_name','email','phone','city','discipline','presentation','video_path','video_mime','photo_path','photo_mime','status','notes','portrait','is_demo','consented_at','adult_confirmed_at','privacy_version','application_details','cv_path','full_photo_path','full_photo_mime'];
    protected function casts(): array {return ['phase_id'=>'integer','is_demo'=>'boolean','consented_at'=>'datetime','adult_confirmed_at'=>'datetime','application_details'=>'array'];}
    public function videos(){return $this->hasMany(AuditionVideo::class)->orderBy('position');}
    public function comments(){return $this->hasMany(AuditionComment::class);}
    public function votes(){return $this->hasMany(AuditionVote::class);}
    public function phase(){return $this->belongsTo(SelectionPhase::class);}
    public function admissions(){return $this->hasMany(PhaseAdmission::class)->with('phase')->orderBy('id');}
    public function selection(){return $this->belongsTo(Selection::class);}
    public function materialRequests(){return $this->hasMany(MaterialRequest::class)->latest('id');}
}

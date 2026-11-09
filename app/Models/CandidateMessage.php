<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CandidateMessage extends Model {
    protected $guarded=['id'];
    /** I collegamenti di accesso nella posta locale restano cifrati nel database. */
    protected function casts(): array {return ['payload'=>'encrypted:array','sent_at'=>'datetime'];}
    public function candidate(){return $this->belongsTo(Candidate::class);}
    public function audition(){return $this->belongsTo(Audition::class);}
}

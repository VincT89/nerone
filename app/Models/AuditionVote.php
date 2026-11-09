<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditionVote extends Model {
    protected $fillable=['user_id','color'];
    public function audition(){return $this->belongsTo(Audition::class);}
    public function user(){return $this->belongsTo(User::class);}
}

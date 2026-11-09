<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditionComment extends Model {
    protected $fillable=['user_id','body'];
    public function audition(){return $this->belongsTo(Audition::class);}
    public function user(){return $this->belongsTo(User::class);}
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditionVideo extends Model {
    protected $fillable=['position','file_path','file_mime','bytes'];
    protected function casts(): array{return ['position'=>'integer','bytes'=>'integer'];}
    public function audition(){return $this->belongsTo(Audition::class);}
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SelectionPhase extends Model {
    protected $fillable=['selection_id','title','position','starts_at','ends_at'];
    protected function casts(): array{return ['starts_at'=>'datetime','ends_at'=>'datetime'];}
    public function selection(){return $this->belongsTo(Selection::class);}
    public function acceptsMaterials(): bool {return (!$this->starts_at || now()->gte($this->starts_at)) && (!$this->ends_at || now()->lte($this->ends_at));}
}

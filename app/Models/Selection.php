<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Selection extends Model {
    protected $fillable=['title','description','is_open'];
    protected function casts(): array{return ['is_open'=>'boolean'];}
    public function phases(){return $this->hasMany(SelectionPhase::class)->orderBy('position');}
    public function acceptsApplications(): bool {return $this->is_open && ($this->phases()->first()?->acceptsMaterials() ?? false);}
    public function auditions(){return $this->hasMany(Audition::class);}
}

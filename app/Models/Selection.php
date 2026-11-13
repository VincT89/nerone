<?php
namespace App\Models;
use App\Services\CastingCalendar;
use Illuminate\Database\Eloquent\Model;
class Selection extends Model {
    protected $fillable=['title','description','is_open'];
    protected function casts(): array{return ['is_open'=>'boolean'];}
    public function phases(){return $this->hasMany(SelectionPhase::class)->orderBy('position');}
    public function acceptsApplications(): bool
    {
        /** La data annunciata è il primo giorno utile anche quando il gestionale è già abilitato. */
        return $this->is_open
            && app(CastingCalendar::class)->permitsIntake()
            && ($this->phases->first()?->acceptsMaterials() ?? false);
    }
    public function auditions(){return $this->hasMany(Audition::class);}
}

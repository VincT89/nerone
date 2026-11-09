<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MaterialRequest extends Model {
    public const KINDS=['video'=>'Video','photo'=>'Foto','document'=>'Documento PDF','text'=>'Risposta scritta'];
    protected $guarded=['id'];
    protected function casts(): array {return ['phase_id'=>'integer','submitted_at'=>'datetime'];}
    public function phase(){return $this->belongsTo(SelectionPhase::class);}
    public function audition(){return $this->belongsTo(Audition::class);}
}

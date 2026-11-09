<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PhaseAdmission extends Model {protected $guarded=['id'];public function phase(){return $this->belongsTo(SelectionPhase::class);} }

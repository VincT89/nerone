<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Candidate extends Authenticatable {
    protected $fillable=['email','password','email_verified_at'];
    protected $hidden=['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed','email_verified_at'=>'datetime']; }
    public function auditions(){return $this->hasMany(Audition::class);}
    public function audition(){return $this->hasOne(Audition::class)->latestOfMany();}
}

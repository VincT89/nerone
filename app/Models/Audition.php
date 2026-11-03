<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Audition extends Model
{
    protected $fillable = ['reference','first_name','last_name','email','phone','city','discipline','presentation','video_path','video_mime','status','notes','portrait','is_demo','consented_at','adult_confirmed_at','privacy_version'];
    protected function casts(): array { return ['is_demo'=>'boolean','consented_at'=>'datetime']; }
}

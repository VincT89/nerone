<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditionVideo extends Model {
    public const QUEUED = 'queued';
    public const COMPRESSED = 'compressed';
    public const ORIGINAL = 'original';
    public const FAILED = 'failed';

    protected $fillable=['position','file_path','file_mime','bytes','compression_status','original_bytes','compression_source_path'];
    protected function casts(): array{return ['position'=>'integer','bytes'=>'integer','original_bytes'=>'integer'];}
    public function audition(){return $this->belongsTo(Audition::class);}
}

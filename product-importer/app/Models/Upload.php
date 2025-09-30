<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'upload_id','filename','mime','total_size','total_chunks','checksum','status','meta'
    ];
    protected $casts = ['meta'=>'array'];
    public function images() { return $this->hasMany(Image::class,'upload_id'); }
}

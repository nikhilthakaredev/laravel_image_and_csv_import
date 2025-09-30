<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'upload_id','path','variant_256','variant_512','variant_1024','checksum'
    ];
    public function upload() { return $this->belongsTo(Upload::class,'upload_id'); }
}

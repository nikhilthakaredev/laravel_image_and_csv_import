<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['sku','name'];
    public function images() { return $this->hasManyThrough(Image::class, ProductImage::class, 'product_id', 'id', 'id', 'image_id'); }
    public function productImages() { return $this->hasMany(ProductImage::class); }
}

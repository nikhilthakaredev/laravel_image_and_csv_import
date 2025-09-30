<?php
namespace App\Jobs;

use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Intervention\Image\Facades\Image as Intervention;
use Illuminate\Support\Facades\Storage;

class ProcessImageVariants implements ShouldQueue
{
    use Queueable;
    protected int $imageId;

    public function __construct(int $imageId){ $this->imageId = $imageId; }

    public function handle(){
        $image = Image::find($this->imageId);
        if(!$image) return;
        $disk = Storage::disk('public');
        $fullPath = storage_path('app/public/'.$image->path);
        if(!file_exists($fullPath)) return;

        $dir = pathinfo($image->path, PATHINFO_DIRNAME);
        $filename = pathinfo($fullPath, PATHINFO_FILENAME);
        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);

        $sizes = [256,512,1024];
        $variants = [];
        foreach($sizes as $s){
            $img = Intervention::make($fullPath);
            $img->resize($s, null, function($c){ $c->aspectRatio(); $c->upsize(); });
            $variantPath = "{$dir}/{$filename}_{$s}.{$ext}";
            $disk->put($variantPath, (string)$img->encode());
            $variants[$s] = $variantPath;
        }

        $image->update([
            'variant_256'=>$variants[256] ?? null,
            'variant_512'=>$variants[512] ?? null,
            'variant_1024'=>$variants[1024] ?? null,
        ]);
    }
}

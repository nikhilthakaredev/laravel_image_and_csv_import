<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Upload;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessImageVariants;

class UploadController extends Controller
{
    public function chunkUpload(Request $r)
    {
        $r->validate([
            'upload_id'=>'required|string',
            'chunk_index'=>'required|integer|min:0',
            'total_chunks'=>'required|integer|min:1',
            'file'=>'required|file'
        ]);

        $uploadId = $r->input('upload_id');
        $chunkIndex = $r->input('chunk_index');
        $totalChunks = $r->input('total_chunks');

        $upload = Upload::firstOrCreate(
            ['upload_id'=>$uploadId],
            ['filename'=>$r->file('file')->getClientOriginalName(), 'total_chunks'=>$totalChunks, 'mime'=>$r->file('file')->getClientMimeType()]
        );

        $chunkDir = storage_path("app/uploads/{$uploadId}/chunks");
        if(!is_dir($chunkDir)) mkdir($chunkDir, 0755, true);

        $chunkFile = $r->file('file')->getRealPath();
        $dest = "{$chunkDir}/{$chunkIndex}";
        move_uploaded_file($chunkFile, $dest); // overwrite if re-sent

        return response()->json(['status'=>'ok','upload_id'=>$uploadId,'chunk_index'=>$chunkIndex]);
    }

    public function status(Request $r)
    {
        $r->validate(['upload_id'=>'required|string']);
        $uploadId = $r->input('upload_id');
        $chunkDir = storage_path("app/uploads/{$uploadId}/chunks");
        $present = [];
        if(is_dir($chunkDir)) {
            foreach(scandir($chunkDir) as $f) if(is_numeric($f)) $present[] = (int)$f;
        }
        return response()->json(['chunks'=>$present]);
    }

    public function complete(Request $r)
    {
        $r->validate(['upload_id'=>'required|string','checksum'=>'required|string']);
        $uploadId = $r->input('upload_id');
        $checksum = $r->input('checksum');

        return DB::transaction(function() use($uploadId,$checksum) {
            $upload = Upload::where('upload_id',$uploadId)->lockForUpdate()->firstOrFail();
            if($upload->status === 'completed') return response()->json(['status'=>'already_completed']);

            $upload->update(['status'=>'assembling','checksum'=>$checksum]);

            $chunkDir = storage_path("app/uploads/{$uploadId}/chunks");
            $assembledPath = storage_path("app/uploads/{$uploadId}/assembled_".Str::random(8));

            $handle = fopen($assembledPath,'wb');
            $total = intval($upload->total_chunks);
            for($i=0;$i<$total;$i++){
                $part = "{$chunkDir}/{$i}";
                if(!file_exists($part)) {
                    fclose($handle);
                    $upload->update(['status'=>'failed']);
                    return response()->json(['status'=>'missing_chunk','missing_index'=>$i],422);
                }
                $data = file_get_contents($part);
                fwrite($handle,$data);
            }
            fclose($handle);

            $computed = hash_file('sha256',$assembledPath);
            if(!hash_equals($computed,$checksum)){
                @unlink($assembledPath);
                $upload->update(['status'=>'failed']);
                return response()->json(['status'=>'checksum_mismatch'],422);
            }

            $filename = $upload->filename ?? basename($assembledPath);
            $publicPath = "uploads/{$uploadId}/{$filename}";
            $disk = Storage::disk('public');
            $disk->putFileAs("uploads/{$uploadId}", new \Illuminate\Http\File($assembledPath), $filename);

            $image = Image::create([
                'upload_id'=>$upload->id,
                'path'=>$publicPath,
                'checksum'=>$computed
            ]);

            $upload->update(['status'=>'completed']);

            ProcessImageVariants::dispatch($image->id);

            // cleanup chunk files
            foreach(glob("{$chunkDir}/*") as $f) @unlink($f);
            @rmdir($chunkDir);

            return response()->json(['status'=>'ok','image_id'=>$image->id,'path'=>Storage::url($publicPath)]);
        });
    }

    public function attachToProduct(Request $r)
    {
        $r->validate([
            'image_id'=>'required|integer|exists:images,id',
            'product_sku'=>'required|string',
            'make_primary'=>'sometimes|boolean'
        ]);
        $image = Image::findOrFail($r->input('image_id'));
        $product = Product::firstOrCreate(['sku'=>$r->input('product_sku')], ['name'=>$r->input('product_name') ?? $r->input('product_sku')]);

        $exists = ProductImage::where('product_id',$product->id)->where('image_id',$image->id)->first();
        if($exists){
            if($r->boolean('make_primary')){
                DB::transaction(function() use($product,$image){
                    ProductImage::where('product_id',$product->id)->update(['is_primary'=>false]);
                    ProductImage::where('product_id',$product->id)->where('image_id',$image->id)->update(['is_primary'=>true]);
                });
            }
            return response()->json(['status'=>'already_attached']);
        }

        DB::transaction(function() use($product,$image,$r){
            if($r->boolean('make_primary')) ProductImage::where('product_id',$product->id)->update(['is_primary'=>false]);
            ProductImage::create(['product_id'=>$product->id,'image_id'=>$image->id,'is_primary'=>$r->boolean('make_primary')]);
        });

        return response()->json(['status'=>'attached']);
    }
}

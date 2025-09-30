<?php
namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\Image as ImageModel;
use App\Jobs\ProcessImageVariants;
use Intervention\Image\Facades\Image;


class ProcessImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_generated()
    {
        Storage::fake('public');

        // create a real image file in storage/app/public/uploads/test.jpg
        $dir = storage_path('app/public/uploads');
        if(!is_dir($dir)) mkdir($dir,0755,true);

        $img = Image::canvas(1200,800,'#ff0000');
        $img->save($dir.'/test.jpg');

        $image = ImageModel::create([
            'upload_id'=>null,
            'path'=>'uploads/test.jpg',
            'checksum'=>hash_file('sha256', $dir.'/test.jpg')
        ]);

        $job = new ProcessImageVariants($image->id);
        $job->handle();

        $this->assertDatabaseHas('images',['id'=>$image->id,'variant_256'=>'uploads/test_256.jpg']);
        $this->assertTrue(file_exists(storage_path('app/public/uploads/test_256.jpg')));
        $this->assertTrue(file_exists(storage_path('app/public/uploads/test_512.jpg')));
        $this->assertTrue(file_exists(storage_path('app/public/uploads/test_1024.jpg')));
    }
}

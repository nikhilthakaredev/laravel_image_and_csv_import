<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\UploadController;

use App\Http\Controllers\ImportController;

Route::get('/import', function () {
    return view('import');
})->name('import.view');

Route::post('/import/csv', [ImportController::class,'csvImport'])->name('import.csv');

Route::get('/image-upload', function(){ return view('image-upload'); })->name('image.upload.view');

Route::post('/upload/chunk', [UploadController::class,'chunkUpload'])->name('upload.chunk');
Route::post('/upload/status', [UploadController::class,'status'])->name('upload.status');
Route::post('/upload/complete', [UploadController::class,'complete'])->name('upload.complete');
Route::post('/upload/attach', [UploadController::class,'attachToProduct'])->name('upload.attach');

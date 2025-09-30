<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id')->index();
            $table->string('filename')->nullable();
            $table->string('mime')->nullable();
            $table->bigInteger('total_size')->nullable();
            $table->integer('total_chunks')->nullable();
            $table->string('checksum')->nullable(); // final sha256
            $table->enum('status',['pending','assembling','completed','failed'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('uploads'); }
};

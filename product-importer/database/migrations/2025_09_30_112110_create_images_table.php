<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->string('path'); // public disk path, e.g. uploads/{upload_id}/file.jpg
            $table->string('variant_256')->nullable();
            $table->string('variant_512')->nullable();
            $table->string('variant_1024')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('images'); }
};

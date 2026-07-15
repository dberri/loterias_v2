<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('draw_pages');
    }

    public function down(): void
    {
        Schema::create('draw_pages', function ($table) {
            $table->id();
            $table->foreignId('draw_id')->constrained();
            $table->string('title');
            $table->text('content');
            $table->string('url');
            $table->string('batch_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }
};

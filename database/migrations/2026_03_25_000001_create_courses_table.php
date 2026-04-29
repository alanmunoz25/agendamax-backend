<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('description');
            $table->longText('syllabus')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('instructor_name')->nullable();
            $table->text('instructor_bio')->nullable();
            $table->string('duration_text')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('enrollment_deadline')->nullable();
            $table->string('schedule_text')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('DOP');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('modality')->default('in-person');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

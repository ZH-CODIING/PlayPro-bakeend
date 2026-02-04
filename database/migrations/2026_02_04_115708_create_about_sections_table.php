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
    Schema::create('about_sections', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique(); // كود فرعي للقسم (مثل: academy_features)
        $table->string('title'); // العنوان الرئيسي (مثلاً: برامج الأكاديمية)
        $table->text('description')->nullable(); // النص التعريفي تحت العنوان
        $table->json('items')->nullable(); // مصفوفة النقاط (العنوان الفرعي + الوصف)
        $table->integer('order')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_sections');
    }
};

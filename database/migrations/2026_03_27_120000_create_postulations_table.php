<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Student
            $table->foreignId('offre_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->text('message')->nullable();
            $table->timestamps();
            
            // Ensure a student can only apply once to the same offer
            $table->unique(['user_id', 'offre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulations');
    }
};

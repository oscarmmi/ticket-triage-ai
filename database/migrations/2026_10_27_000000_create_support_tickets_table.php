<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('channel')->default('email');
            $table->string('customer_name');
            $table->string('predicted_category')->nullable();
            $table->string('predicted_priority')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->index(['status', 'predicted_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};

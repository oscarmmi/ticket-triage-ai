<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->longText('raw_json');
            $table->string('model_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_runs');
    }
};

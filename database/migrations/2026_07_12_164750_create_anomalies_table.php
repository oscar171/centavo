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
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public identifier
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type'); // duplicate_charge | charge_burst | recurring_subscription | reversal | unusual_amount | possible_fraud
            $table->string('severity'); // low | medium | high
            $table->string('title');
            $table->text('description');
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('transaction_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('open'); // open | dismissed | resolved
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};

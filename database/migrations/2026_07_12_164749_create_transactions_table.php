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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public identifier used in URLs
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('description');
            $table->decimal('amount', 15, 2); // siempre positivo
            $table->string('direction'); // credit | debit
            $table->decimal('running_balance', 15, 2)->nullable();
            $table->string('reference')->nullable();
            $table->string('merchant')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index(['statement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

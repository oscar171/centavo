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
        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public identifier used in URLs
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('beginning_balance', 15, 2)->nullable();
            $table->decimal('ending_balance', 15, 2)->nullable();
            $table->decimal('total_deposits', 15, 2)->nullable();
            $table->decimal('total_withdrawals', 15, 2)->nullable();
            $table->string('original_filename');
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending|processing|processed|needs_review|failed
            $table->boolean('is_reconciled')->default(false);
            $table->decimal('reconciliation_diff', 15, 2)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};

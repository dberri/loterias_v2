<?php

use App\Enums\PageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('filament-fabricator.table_name', 'pages'), function (Blueprint $table) {
            $table->foreignId('draw_id')->nullable()->unique()->constrained('draws')->nullOnDelete()->after('parent_id');
            $table->string('batch_id')->nullable()->index()->after('draw_id');
            $table->string('provider')->nullable()->after('batch_id');
            $table->string('status')->default(PageStatus::Generating->value)->index()->after('provider');
            $table->timestamp('generated_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table(config('filament-fabricator.table_name', 'pages'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('draw_id');
            $table->dropColumn(['batch_id', 'provider', 'status', 'generated_at']);
        });
    }
};

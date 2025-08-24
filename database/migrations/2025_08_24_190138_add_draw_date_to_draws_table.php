<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->date('draw_date')->nullable()->after('draw_number');
        });

        // Populate the draw_date column from raw_data->dataApuracao
        $draws = DB::table('draws')->whereNotNull('raw_data')->get();

        foreach ($draws as $draw) {
            $rawData = json_decode($draw->raw_data, true);
            
            if (isset($rawData['dataApuracao']) && !empty($rawData['dataApuracao'])) {
                try {
                    // Parse the date in d/m/Y format (e.g., "25/01/2025")
                    $drawDate = Carbon::createFromFormat('d/m/Y', $rawData['dataApuracao']);
                    
                    DB::table('draws')
                        ->where('id', $draw->id)
                        ->update(['draw_date' => $drawDate->format('Y-m-d')]);
                } catch (\Exception $e) {
                    // If date parsing fails, log it or handle gracefully
                    Log::warning("Failed to parse draw date for draw ID {$draw->id}: {$rawData['dataApuracao']}");
                }
            }
        }

        // Make the column NOT NULL after populating data
        Schema::table('draws', function (Blueprint $table) {
            $table->date('draw_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropColumn('draw_date');
        });
    }
};

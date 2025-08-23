<?php

namespace Database\Seeders;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Illuminate\Database\Seeder;

class DrawSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/lotteries/megasena/draws');
        $files = scandir($path);

        foreach ($files as $file) {
            if (is_file($path.'/'.$file)) {
                $draw = new Draw;
                $draw->type = GamesEnum::MEGA_SENA;
                $draw->raw_data = json_decode(file_get_contents($path.'/'.$file), true);
                $draw->draw_number = (int) pathinfo($file, PATHINFO_FILENAME);
                $draw->save();
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographySeeder extends Seeder
{
    /**
     * Seed Nepal's federal geography (7 provinces, 77 districts, 753 local levels)
     * from database/data/nepal_geography.json.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/nepal_geography.json')), true);
        $now = now();

        $stamp = fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now];

        DB::table('local_levels')->delete();
        DB::table('districts')->delete();
        DB::table('provinces')->delete();

        DB::table('provinces')->insert(array_map($stamp, $data['provinces']));
        DB::table('districts')->insert(array_map($stamp, $data['districts']));

        foreach (array_chunk($data['local_levels'], 250) as $chunk) {
            DB::table('local_levels')->insert(array_map($stamp, $chunk));
        }
    }
}

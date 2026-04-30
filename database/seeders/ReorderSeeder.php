<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReorderSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('items')->insert([
            ['code' => 'ITEM001', 'name' => 'Widget Type A', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ITEM002', 'name' => 'Widget Type B', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ITEM003', 'name' => 'Gadget Premium', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('models')->insert([
            ['mtype' => 'M', 'name' => 'MODEL-A', 'created_at' => now(), 'updated_at' => now()],
            ['mtype' => 'M', 'name' => 'MODEL-B', 'created_at' => now(), 'updated_at' => now()],
            ['mtype' => 'M', 'name' => 'MODEL-C', 'created_at' => now(), 'updated_at' => now()],
            ['mtype' => 'S', 'name' => 'SMALL', 'created_at' => now(), 'updated_at' => now()],
            ['mtype' => 'S', 'name' => 'MEDIUM', 'created_at' => now(), 'updated_at' => now()],
            ['mtype' => 'S', 'name' => 'LARGE', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('rotable')->insert([
            [
                'code' => 'ITEM001',
                'model' => 'MODEL-A',
                'size' => 'SMALL',
                'weight1' => 10.500,
                'weight2' => 50.000,
                'minqty' => 100,
                'maxqty' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ITEM001',
                'model' => 'MODEL-A',
                'size' => 'MEDIUM',
                'weight1' => 50.100,
                'weight2' => 100.000,
                'minqty' => 75,
                'maxqty' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ITEM002',
                'model' => 'MODEL-B',
                'size' => 'LARGE',
                'weight1' => 100.000,
                'weight2' => 200.000,
                'minqty' => 50,
                'maxqty' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}


<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::disableQueryLog();
        // User::factory(10)->create();

        $this->call(SeasonSeeder::class);
        $this->call(TeamSeeder::class);
        $this->call(RegattaSeeder::class);

        User::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'admin',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'system_role' => SystemRole::Admin,
        ]);

        foreach (range(1, 2) as $i) {
            User::factory(5)->create();

            gc_collect_cycles();
        }

        $this->call(TeamMemberSeeder::class);
        
        /*
        foreach (range(1, 3) as $i) {
            Team::factory(2)->create();

            gc_collect_cycles();
        }
        */
        /*
        foreach (range(1, 3) as $i) {

            TeamMember::factory(1)->create();


            gc_collect_cycles();
        }
        */

        $this->call(YachtSeeder::class);
        $this->call(RegattaEventsSeeder::class);
        $this->call(RegattaEntrySeeder::class);

       //$this->call(RaceResultSeeder::class);
        $this->call(RegattaResultSeeder::class);
    }
}

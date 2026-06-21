<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * NOT part of the app. Local browser-testing fixtures for the parent/child
 * slot sign-up behaviour. Run with: php artisan db:seed --class=DevBrowserSeeder
 */
class DevBrowserSeeder extends Seeder
{
    public function run(): void
    {
        // The squashed schema dump carries no row data, so make sure the Admin
        // role exists for the login user.
        if (!DB::table('role')->where('id', Role::ADMIN)->exists()) {
            DB::table('role')->insert(['id' => Role::ADMIN, 'title' => 'Admin', 'new_user_eligible' => 0]);
        }

        $year = date('Y');

        // Parent (Ridealong) -> Child (Mentee) positions.
        $ridealong = Position::factory()->create([
            'title' => 'Deep Desert Patrol Ridealong',
            'type' => 'Frontline',
            'active' => true,
        ]);
        $mentee = Position::factory()->create([
            'title' => 'Deep Desert Patrol Mentee',
            'type' => 'Frontline',
            'active' => true,
            'parent_position_id' => $ridealong->id,
        ]);

        // Two linked shift pairs:
        //   Aug 30 - sane pool (Ridealong max 4 / Mentee max 2)
        //   Aug 31 - the reported edge config (Ridealong max 1 / Mentee max 2)
        $this->makePair($ridealong->id, $mentee->id, "$year-08-30", 4, 2);
        $this->makePair($ridealong->id, $mentee->id, "$year-08-31", 1, 2);

        // Admin login user.
        $admin = Person::factory()->create([
            'callsign' => 'TestAdmin',
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'status' => 'active',
            'callsign_approved' => true,
        ]);
        $admin->changePassword('clubhouse');
        PersonRole::addIdsToPerson($admin->id, [Role::ADMIN], 'dev seed');
        PersonPosition::addIdsToPerson($admin->id, [$ridealong->id, $mentee->id], 'dev seed');

        // A handful of rangers holding both positions, available to add to shifts.
        for ($i = 1; $i <= 5; $i++) {
            $p = Person::factory()->create([
                'callsign' => "Rider$i",
                'first_name' => 'Rider',
                'last_name' => (string)$i,
                'email' => "rider$i@example.com",
                'status' => 'active',
                'callsign_approved' => true,
            ]);
            $p->changePassword('clubhouse');
            PersonPosition::addIdsToPerson($p->id, [$ridealong->id, $mentee->id], 'dev seed');
        }

        $this->command->info('Seeded: admin@example.com / clubhouse (callsign TestAdmin), 5 riders, 2 Ridealong/Mentee shift pairs.');
    }

    private function makePair(int $parentPositionId, int $childPositionId, string $day, int $parentMax, int $childMax): void
    {
        $begins = "$day 09:00:00";
        $ends = "$day 17:00:00";

        $parent = Slot::factory()->create([
            'position_id' => $parentPositionId,
            'begins' => $begins,
            'ends' => $ends,
            'description' => 'Ridealong',
            'max' => $parentMax,
            'min' => 0,
            'signed_up' => 0,
            'active' => true,
        ]);

        Slot::factory()->create([
            'position_id' => $childPositionId,
            'begins' => $begins,
            'ends' => $ends,
            'description' => 'Mentee',
            'max' => $childMax,
            'min' => 0,
            'signed_up' => 0,
            'active' => true,
            'parent_signup_slot_id' => $parent->id,
        ]);
    }
}

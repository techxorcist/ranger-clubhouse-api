<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonRole;
use App\Models\Position;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * NOT part of the app. Local browser-testing helper:
 *  - grants Tech Ninja (god mode) to TestAdmin
 *  - approves photos and marks the online course complete for the seeded
 *    accounts so they can sign up without forcing.
 * Run: php artisan db:seed --class=DevGrantSeeder
 */
class DevGrantSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure the role rows exist for the frontend to map titles.
        $roles = [
            Role::ADMIN => 'Admin',
            Role::TECH_NINJA => 'Tech Ninja',
            Role::EVENT_MANAGEMENT => 'Event Management Year Round',
        ];
        foreach ($roles as $id => $title) {
            if (!DB::table('role')->where('id', $id)->exists()) {
                DB::table('role')->insert(['id' => $id, 'title' => $title, 'new_user_eligible' => 0]);
            }
        }

        $admin = Person::where('email', 'admin@example.com')->firstOrFail();
        PersonRole::addIdsToPerson(
            $admin->id,
            [Role::ADMIN, Role::TECH_NINJA, Role::EVENT_MANAGEMENT],
            'dev grant'
        );

        // Fully enable the admin + every seeded rider.
        $callsigns = ['TestAdmin', 'Rider1', 'Rider2', 'Rider3', 'Rider4', 'Rider5'];
        foreach (Person::whereIn('callsign', $callsigns)->get() as $person) {
            $this->approvePhoto($person);
            $this->completeOnlineCourse($person);
        }

        $this->command->info('Granted Tech Ninja to TestAdmin; approved photos + online course for TestAdmin and Rider1-5.');
    }

    private function approvePhoto(Person $person): void
    {
        if ($person->person_photo_id) {
            DB::table('person_photo')->where('id', $person->person_photo_id)
                ->update(['status' => PersonPhoto::APPROVED]);
            return;
        }

        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::APPROVED,
        ]);
        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();
    }

    private function completeOnlineCourse(Person $person): void
    {
        $year = (int)date('Y');
        $exists = PersonOnlineCourse::where('person_id', $person->id)
            ->where('year', $year)
            ->where('position_id', Position::TRAINING)
            ->exists();
        if ($exists) {
            return;
        }

        $poc = new PersonOnlineCourse;
        $poc->person_id = $person->id;
        $poc->completed_at = now();
        $poc->position_id = Position::TRAINING;
        $poc->type = PersonOnlineCourse::TYPE_MOODLE;
        $poc->year = $year;
        $poc->saveWithoutValidation();
    }
}

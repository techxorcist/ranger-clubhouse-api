# Dev seeding for local browser testing

These seeders stand up a working admin plus exercisable test accounts on a
freshly (re)created dev container. They are **not** part of the application and
should never run in production.

```bash
php artisan migrate            # loads mysql-schema.dump (structure only)
php artisan db:seed --class=DevBrowserSeeder
php artisan db:seed --class=DevGrantSeeder
```

Result: `admin@example.com` / `clubhouse` (callsign `TestAdmin`) with Admin +
Event Management Year Round + Tech Ninja, five riders (`Rider1`..`Rider5`), and
two linked Ridealong/Mentee shift pairs. Every account can sign up to shifts
without being forced.

## Lessons learned (why each step exists)

1. **The schema dump carries no row data.** `migrate` loads `mysql-schema.dump`
   (structure only), so reference tables like `role` come up empty. Any role
   referenced by id must be inserted first or its title renders as "role #N"
   and frontend role checks misbehave. We seed `Role::ADMIN` (1),
   `Role::EVENT_MANAGEMENT` (12, the year-round HQ role — **not** 108
   `MANAGE_ON_PLAYA`, which is gated behind `EventManagementOnPlayaEnabled`),
   and `Role::TECH_NINJA` (1000, god mode).

2. **A login user needs several aligned fields, not just email/password:**
   `status = 'active'`, `callsign_approved = true`, a password set via
   `Person::changePassword()` (argon hash), and a non-empty `bpguid` (the
   factory supplies one). Login is `POST /auth/oauth2/token` with
   `grant_type=password`; the token comes back as `access_token`, not `token`.

3. **Holding the position is required to even see shifts.** `Schedule::findForQuery`
   only returns slots whose position the person holds (or is already signed up
   to). Admin and every rider get `PersonPosition` rows for both Ridealong and
   Mentee.

4. **Sign-up requirements block adds until satisfied.** An active Ranger still
   trips `missing-requirements (photo-unapproved)` until they have an approved
   `PersonPhoto` with `person_photo_id` set on the person. Online-course
   completion (`PersonOnlineCourse`, current year, `position_id = TRAINING`)
   only gates training shifts but is seeded so accounts are fully exercisable.
   Without these, every add forces the admin through the "force" modal.

5. **Attach via helpers, not raw inserts:** `PersonRole::addIdsToPerson()` and
   `PersonPosition::addIdsToPerson()` so audit/side-effects are handled.

6. **Parent/child linkage lives on the slot.** A child (Mentee) slot points at
   the parent (Ridealong) slot via `parent_signup_slot_id` at the same `begins`
   time. The seeder creates both a sane pool (max 4 / 2) and the reported edge
   config (max 1 / 2).

## Infra caveat

The stack runs on **MariaDB** (the dump uses MariaDB syntax — `TEXT DEFAULT`,
`bigint(20)`), so a MySQL 8 container fails to load it. The schema load goes
through the `mariadb` CLI, which needs SSL disabled (`skip-ssl`) against a
TLS-enabled server.

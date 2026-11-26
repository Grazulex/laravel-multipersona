<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateInterval;
use Grazulex\LaravelMultiPersona\Authorization\PersonaGate;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaExpired;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;
use Tests\Fixtures\PersonaUser;
use Tests\TestCase;

class TemporaryPersonaTest extends TestCase
{
    private PersonaManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
        }

        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));

        $this->manager = app(PersonaManager::class);
    }

    public function test_a_temporary_switch_stores_the_expiration_and_the_original_persona(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 600);

        $this->assertSame($admin->id, $this->manager->id());
        $this->assertTrue($this->manager->isTemporary());
        $this->assertSame('2026-01-15 10:10:00', $this->manager->expiresAt()?->format('Y-m-d H:i:s'));

        /** @var Persona $stored */
        $stored = $admin->fresh();

        $this->assertTrue($stored->is_active);
        $this->assertTrue($stored->isTemporary());
        $this->assertFalse($stored->hasExpired());
        $this->assertSame('2026-01-15 10:10:00', $stored->expiresAt()?->format('Y-m-d H:i:s'));
        $this->assertEquals($base->id, $stored->getRevertPersonaId());
        $this->assertFalse((bool) $base->fresh()?->is_active);
    }

    public function test_the_temporary_persona_stays_active_until_the_expiration(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 600);

        $this->travel(599)->seconds();

        $this->assertSame($admin->id, $this->manager->id());
        $this->assertSame($admin->id, (new PersonaManager)->id());
    }

    public function test_the_original_persona_is_restored_once_the_expiration_is_reached(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 600);

        $this->travel(600)->seconds();

        $this->assertSame($base->id, $this->manager->id());
        $this->assertFalse($this->manager->isTemporary());
        $this->assertNull($this->manager->expiresAt());
        $this->assertEquals($base->id, Session::get('active_persona_id'));

        /** @var Persona $expired */
        $expired = $admin->fresh();

        $this->assertFalse($expired->is_active);
        $this->assertNull($expired->expiresAt());
        $this->assertNull($expired->getRevertPersonaId());
        $this->assertTrue((bool) $base->fresh()?->is_active);
    }

    public function test_the_expiration_is_applied_on_a_later_request(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 600);

        $this->travel(15)->minutes();

        // A fresh manager only has the session pointer and the database
        $manager = new PersonaManager;

        $this->assertSame($base->id, $manager->id());
        $this->assertEquals($base->id, Session::get('active_persona_id'));
        $this->assertFalse((bool) $admin->fresh()?->is_active);
    }

    public function test_the_default_duration_comes_from_the_configuration(): void
    {
        config()->set('multipersona.temporary.ttl', 120);

        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActiveFor($admin);

        $this->assertSame('2026-01-15 10:02:00', $this->manager->expiresAt()?->format('Y-m-d H:i:s'));
    }

    public function test_the_duration_can_be_an_interval_or_a_date(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActiveFor($admin, new DateInterval('PT30M'));
        $this->assertSame('2026-01-15 10:30:00', $this->manager->expiresAt()?->format('Y-m-d H:i:s'));

        $this->manager->setActiveFor($admin, Carbon::parse('2026-01-15 12:00:00'));
        $this->assertSame('2026-01-15 12:00:00', $this->manager->expiresAt()?->format('Y-m-d H:i:s'));
    }

    public function test_an_expiration_that_is_not_in_the_future_is_rejected(): void
    {
        $admin = $this->createPersona(1, 'Admin');

        try {
            $this->manager->setActiveFor($admin, 0);
            $this->fail('A duration of zero seconds must be rejected');
        } catch (InvalidArgumentException) {
            $this->assertFalse((bool) $admin->fresh()?->is_active);
        }

        $this->expectException(InvalidArgumentException::class);

        $this->manager->setActiveFor($admin, Carbon::parse('2026-01-15 09:00:00'));
    }

    public function test_an_unknown_persona_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->setActiveFor(999, 60);
    }

    public function test_without_original_persona_no_persona_is_active_after_the_expiration(): void
    {
        Event::fake([PersonaDeactivated::class, PersonaExpired::class]);

        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActiveFor($admin, 60);
        $this->travel(61)->seconds();

        $this->assertNull($this->manager->current());
        $this->assertFalse($this->manager->hasActive());
        $this->assertFalse(Session::has('active_persona_id'));
        $this->assertFalse((bool) $admin->fresh()?->is_active);

        Event::assertDispatched(PersonaDeactivated::class, function (PersonaDeactivated $event) use ($admin): bool {
            return $event->getPersona()->getId() === $admin->id
                && $event->getContext() === ['method' => 'expire'];
        });
        Event::assertDispatched(PersonaExpired::class, function (PersonaExpired $event): bool {
            return ! $event->getRestoredPersona() instanceof Persona;
        });
    }

    public function test_a_deleted_original_persona_is_not_restored(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 60);
        $base->delete();

        $this->travel(61)->seconds();

        $this->assertNull($this->manager->current());
        $this->assertFalse((bool) $admin->fresh()?->is_active);
    }

    public function test_the_expiration_dispatches_the_events(): void
    {
        Event::fake([PersonaExpired::class, PersonaSwitched::class, PersonaDeactivated::class]);

        $user = $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 60);

        Event::assertDispatched(PersonaSwitched::class, function (PersonaSwitched $event) use ($admin): bool {
            return $event->getPersona()->getId() === $admin->id
                && $event->getContext()['method'] === 'setActiveFor'
                && $event->getContext()['expires_at'] === Carbon::parse('2026-01-15 10:01:00')->toIso8601String();
        });

        $this->travel(61)->seconds();
        $this->manager->current();
        $this->manager->current();

        Event::assertDispatchedTimes(PersonaExpired::class, 1);
        Event::assertDispatched(PersonaExpired::class, function (PersonaExpired $event) use ($user, $base, $admin): bool {
            return $event->getPersona()->getId() === $admin->id
                && $event->getRestoredPersona()?->getId() === $base->id
                && $event->getUser() === $user
                && $event->getContext() === ['method' => 'expire'];
        });
        Event::assertDispatched(PersonaSwitched::class, function (PersonaSwitched $event) use ($base, $admin): bool {
            return $event->getPersona()->getId() === $base->id
                && $event->getPreviousPersona()?->getId() === $admin->id
                && $event->getContext() === ['method' => 'expire'];
        });
        Event::assertNotDispatched(PersonaDeactivated::class);
    }

    public function test_a_regular_switch_to_the_temporary_persona_removes_its_expiration(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);

        // Activated through another instance: $admin is stale afterwards
        $this->manager->setActiveFor($admin->id, 60);
        $this->manager->setActive($admin);

        $this->assertFalse($this->manager->isTemporary());
        $this->assertNull($admin->fresh()?->expiresAt());

        $this->travel(10)->minutes();

        $this->assertSame($admin->id, $this->manager->id());
        $this->assertSame($admin->id, (new PersonaManager)->id());
    }

    public function test_a_regular_switch_to_another_persona_cancels_the_return(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');
        $other = $this->createPersona(1, 'Other');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 60);
        $this->manager->setActive($other);

        /** @var Persona $left */
        $left = $admin->fresh();

        $this->assertFalse($left->is_active);
        $this->assertNull($left->expiresAt());
        $this->assertNull($left->getRevertPersonaId());

        $this->travel(10)->minutes();

        $this->assertSame($other->id, (new PersonaManager)->id());
    }

    public function test_clearing_a_temporary_persona_removes_its_expiration(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 60);
        $this->manager->clear();

        $this->assertNull($admin->fresh()?->expiresAt());

        $this->travel(10)->minutes();

        $this->assertNull((new PersonaManager)->current());
        $this->assertFalse((bool) $base->fresh()?->is_active);
    }

    public function test_chained_temporary_switches_return_to_the_original_persona(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $manager = $this->createPersona(1, 'Manager');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($manager, 60);
        $this->manager->setActiveFor($admin, 60);

        $this->assertEquals($base->id, $admin->fresh()?->getRevertPersonaId());

        $this->travel(61)->seconds();

        $this->assertSame($base->id, $this->manager->id());
        $this->assertFalse((bool) $manager->fresh()?->is_active);
        $this->assertFalse((bool) $admin->fresh()?->is_active);
    }

    public function test_renewing_a_temporary_persona_moves_its_expiration(): void
    {
        $this->actingAsUser(1);
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->manager->setActive($base);
        $this->manager->setActiveFor($admin, 60);
        $this->manager->setActiveFor($admin, 600);

        $this->assertEquals($base->id, $admin->fresh()?->getRevertPersonaId());

        $this->travel(120)->seconds();
        $this->assertSame($admin->id, (new PersonaManager)->id());

        $this->travel(600)->seconds();
        $this->assertSame($base->id, (new PersonaManager)->id());
    }

    public function test_the_trait_switches_for_a_limited_time(): void
    {
        $user = $this->actingAsUser(1);
        $base = $user->createPersona('User');
        $admin = $user->createPersona('Admin');

        $user->switchToPersona($base);

        $this->assertTrue($user->switchToPersonaFor($admin->id, 300));
        $this->assertSame($admin->id, $user->activePersona()?->id);
        $this->assertTrue((bool) $user->activePersona()?->isTemporary());

        $this->travel(301)->seconds();

        $this->assertSame($base->id, $user->activePersona()?->id);
        $this->assertSame($base->id, $this->manager->id());
        $this->assertFalse((bool) $admin->fresh()?->is_active);
    }

    public function test_the_trait_refuses_a_persona_of_another_user(): void
    {
        $user = $this->actingAsUser(1);
        $foreign = $this->createPersona(2, 'Foreign admin');

        $this->assertFalse($user->switchToPersonaFor($foreign->id, 300));
        $this->assertFalse($user->switchToPersonaFor(999, 300));
        $this->assertFalse((bool) $foreign->fresh()?->is_active);
    }

    public function test_switch_to_for_checks_the_ownership(): void
    {
        $user = $this->actingAsUser(1);
        $own = $user->createPersona('Admin');
        $foreign = $this->createPersona(2, 'Foreign admin');

        $this->assertFalse($this->manager->switchToFor($foreign, 300));
        $this->assertFalse((bool) $foreign->fresh()?->is_active);

        $this->assertTrue($this->manager->switchToFor($own, 300));
        $this->assertTrue($this->manager->isTemporary());
    }

    public function test_release_expired_restores_the_personas_of_every_user(): void
    {
        DB::table('users')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);

        $baseOne = $this->createPersona(1, 'User one');
        $adminOne = $this->createPersona(1, 'Admin one');
        $adminTwo = $this->createPersona(2, 'Admin two');
        $baseThree = $this->createPersona(3, 'User three');
        $adminThree = $this->createPersona(3, 'Admin three');

        $adminOne->activateUntil(Carbon::now()->addMinute(), $baseOne->id);
        $adminTwo->activateUntil(Carbon::now()->addMinute());
        $adminThree->activateUntil(Carbon::now()->addHour(), $baseThree->id);

        $this->assertSame(0, $this->manager->releaseExpired());

        $this->travel(5)->minutes();

        Event::fake([PersonaExpired::class]);

        $this->assertSame(2, $this->manager->releaseExpired());
        $this->assertSame(0, $this->manager->releaseExpired());

        $this->assertFalse((bool) $adminOne->fresh()?->is_active);
        $this->assertTrue((bool) $baseOne->fresh()?->is_active);
        $this->assertFalse((bool) $adminTwo->fresh()?->is_active);
        $this->assertTrue((bool) $adminThree->fresh()?->is_active);
        $this->assertFalse((bool) $baseThree->fresh()?->is_active);

        // Nobody is authenticated: the owner is loaded with the user model
        Event::assertDispatchedTimes(PersonaExpired::class, 2);
        Event::assertDispatched(PersonaExpired::class, function (PersonaExpired $event) use ($adminOne): bool {
            $user = $event->getUser();

            return $event->getPersona()->getId() === $adminOne->id
                && $user instanceof PersonaUser
                && $user->getKey() === 1;
        });
    }

    public function test_the_gate_resolver_ignores_an_expired_persona(): void
    {
        $this->actingAsUser(1);
        DB::table('users')->insert(['id' => 2]);

        $other = (new PersonaUser)->forceFill(['id' => 2]);
        $other->exists = true;

        $base = $this->createPersona(2, 'User', ['permissions' => ['view_dashboard']]);
        $admin = $this->createPersona(2, 'Admin', ['permissions' => ['manage_users']]);
        $admin->activateUntil(Carbon::now()->addMinute(), $base->id);

        $gate = app(PersonaGate::class);

        $this->assertSame($admin->id, $gate->personaFor($other)?->getId());

        $this->travel(2)->minutes();

        $this->assertSame($base->id, $gate->personaFor($other)?->getId());
        $this->assertFalse((bool) $admin->fresh()?->is_active);
    }

    public function test_activate_until_and_activate_on_the_model(): void
    {
        $base = $this->createPersona(1, 'User');
        $admin = $this->createPersona(1, 'Admin');

        $this->assertNull($admin->expiresAt());
        $this->assertFalse($admin->isTemporary());
        $this->assertFalse($admin->hasExpired());
        $this->assertTrue($admin->supportsExpiration());

        $admin->activateUntil(Carbon::now()->addMinutes(5), $base->id);

        $this->assertTrue($admin->isTemporary());
        $this->assertEquals($base->id, $admin->getRevertPersonaId());

        $this->travel(5)->minutes();
        $this->assertTrue($admin->hasExpired());

        $admin->activate();

        $this->assertFalse($admin->isTemporary());
        $this->assertFalse($admin->hasExpired());
        $this->assertNull($admin->fresh()?->expiresAt());
        $this->assertTrue((bool) $admin->fresh()?->is_active);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('multipersona.user_model', PersonaUser::class);
    }

    /**
     * The queued bundled listeners restore the user from the database.
     */
    private function actingAsUser(int $id): PersonaUser
    {
        DB::table('users')->insert(['id' => $id]);

        $user = (new PersonaUser)->forceFill(['id' => $id]);
        $user->exists = true;

        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createPersona(int $userId, string $name, array $context = []): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => $context,
            'is_active' => false,
        ]);
    }
}

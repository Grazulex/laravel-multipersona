<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitchLogged;
use Grazulex\LaravelMultiPersona\Listeners\LogPersonaSwitch;
use Grazulex\LaravelMultiPersona\Listeners\RecordPersonaSwitch;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Models\PersonaSwitchLog;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Fixtures\PersonaUser;
use Tests\TestCase;

class PersonaSwitchAuditTest extends TestCase
{
    public function test_record_listener_is_registered_when_audit_is_enabled(): void
    {
        $listeners = Event::getRawListeners();

        $this->assertContains(RecordPersonaSwitch::class, $listeners[PersonaSwitched::class] ?? []);
    }

    public function test_a_switch_is_recorded_with_user_date_and_personas(): void
    {
        $user = $this->actingAsUser(5);
        $first = $this->createPersona(5, 'Customer');
        $second = $this->createPersona(5, 'Seller');

        $this->travelTo(Carbon::parse('2026-01-15 10:30:00'));

        $manager = app(PersonaManager::class);
        $manager->setActive($first);

        // The initial activation is not a switch
        $this->assertSame(0, PersonaSwitchLog::query()->count());

        $manager->setActive($second);

        $this->assertSame(1, PersonaSwitchLog::query()->count());

        /** @var PersonaSwitchLog $log */
        $log = PersonaSwitchLog::query()->firstOrFail();

        $this->assertSame(5, $log->user_id);
        $this->assertSame($user::class, $log->user_type);
        $this->assertSame($first->id, $log->from_persona_id);
        $this->assertSame('Customer', $log->from_persona_name);
        $this->assertSame($second->id, $log->to_persona_id);
        $this->assertSame('Seller', $log->to_persona_name);
        $this->assertSame(['method' => 'setActive'], $log->context);
        $this->assertSame('2026-01-15 10:30:00', $log->switched_at->format('Y-m-d H:i:s'));
        $this->assertTrue($log->fromPersona?->is($first));
        $this->assertTrue($log->toPersona?->is($second));
    }

    public function test_every_switch_is_recorded(): void
    {
        $this->actingAsUser(1);
        $first = $this->createPersona(1, 'A');
        $second = $this->createPersona(1, 'B');

        $manager = app(PersonaManager::class);
        $manager->setActive($first);
        $manager->setActive($second);
        $manager->setActive($first);

        $this->assertSame(
            [['A', 'B'], ['B', 'A']],
            PersonaSwitchLog::query()->orderBy('id')->get()
                ->map(fn (PersonaSwitchLog $log): array => [$log->from_persona_name, $log->to_persona_name])
                ->all()
        );
    }

    public function test_logged_event_is_dispatched_with_the_record(): void
    {
        Event::fake([PersonaSwitchLogged::class]);

        $user = $this->actingAsUser(1);
        $manager = app(PersonaManager::class);
        $manager->setActive($this->createPersona(1, 'A'));
        $manager->setActive($this->createPersona(1, 'B'));

        Event::assertDispatchedTimes(PersonaSwitchLogged::class, 1);
        Event::assertDispatched(PersonaSwitchLogged::class, function (PersonaSwitchLogged $event) use ($user): bool {
            return $event->getLog()->exists
                && $event->getLog()->to_persona_name === 'B'
                && $event->getUser() === $user;
        });
    }

    public function test_entries_stay_readable_after_the_persona_is_deleted(): void
    {
        $previous = $this->createPersona(1, 'Old');
        $current = $this->createPersona(1, 'New');

        (new RecordPersonaSwitch)->handle(new PersonaSwitched($current, $previous, 1));

        $previous->delete();

        /** @var PersonaSwitchLog $log */
        $log = PersonaSwitchLog::query()->firstOrFail();

        $this->assertSame('Old', $log->from_persona_name);
        $this->assertNull($log->fromPersona);
        $this->assertSame(1, $log->user_id);
        $this->assertNull($log->user_type);
        $this->assertNull($log->context);
    }

    public function test_switch_without_user_or_previous_persona_is_recorded(): void
    {
        $persona = $this->createPersona(1, 'Solo');

        (new RecordPersonaSwitch)->handle(new PersonaSwitched($persona));

        /** @var PersonaSwitchLog $log */
        $log = PersonaSwitchLog::query()->firstOrFail();

        $this->assertNull($log->user_id);
        $this->assertNull($log->user_type);
        $this->assertNull($log->from_persona_id);
        $this->assertNull($log->from_persona_name);
        $this->assertSame($persona->id, $log->to_persona_id);
    }

    public function test_scopes_and_user_relation(): void
    {
        $user = $this->actingAsUser(1);
        $a = $this->createPersona(1, 'A');
        $b = $this->createPersona(1, 'B');
        $c = $this->createPersona(2, 'C');
        $d = $this->createPersona(2, 'D');

        $listener = new RecordPersonaSwitch;
        $listener->handle(new PersonaSwitched($b, $a, $user));
        $listener->handle(new PersonaSwitched($d, $c, 2));

        $this->assertSame(1, PersonaSwitchLog::query()->forUser($user)->count());
        $this->assertSame(1, PersonaSwitchLog::query()->forUser(2)->count());
        $this->assertSame(1, PersonaSwitchLog::query()->forPersona($a)->count());
        $this->assertSame(1, PersonaSwitchLog::query()->forPersona($d->id)->count());
        $this->assertSame(['B'], $user->personaSwitchLogs()->pluck('to_persona_name')->all());
    }

    public function test_log_listener_writes_to_the_configured_channel(): void
    {
        config()->set('multipersona.audit.log_channel', 'audit');

        $previous = $this->createPersona(1, 'User');
        $current = $this->createPersona(1, 'Admin');

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('Persona switched', Mockery::on(function (array $context) use ($previous, $current): bool {
                return $context['previous_persona_id'] === $previous->id
                    && $context['new_persona_id'] === $current->id
                    && $context['new_persona'] === 'Admin';
            }));

        (new LogPersonaSwitch)->handle(new PersonaSwitched($current, $previous));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('multipersona.audit.enabled', true);
    }

    /**
     * The queued bundled listeners restore the user from the database.
     */
    private function actingAsUser(int $id): PersonaUser
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
        }

        DB::table('users')->insert(['id' => $id]);

        $user = (new PersonaUser)->forceFill(['id' => $id]);
        $user->exists = true;

        $this->actingAs($user);

        return $user;
    }

    private function createPersona(int $userId, string $name): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => [],
            'is_active' => false,
        ]);
    }
}

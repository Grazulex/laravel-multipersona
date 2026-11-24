<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\RecordPersonaSwitch;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Models\PersonaSwitchLog;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\PersonaUser;
use Tests\TestCase;

class PersonaSwitchAuditDisabledTest extends TestCase
{
    public function test_audit_is_disabled_by_default(): void
    {
        $this->assertFalse(config('multipersona.audit.enabled'));
        $this->assertTrue(Schema::hasTable('persona_switch_logs'));

        $listeners = Event::getRawListeners();
        $this->assertNotContains(RecordPersonaSwitch::class, $listeners[PersonaSwitched::class] ?? []);
    }

    public function test_switches_are_not_recorded_when_disabled(): void
    {
        // The queued bundled listeners restore the user from the database
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('users')->insert(['id' => 1]);

        $user = (new PersonaUser)->forceFill(['id' => 1]);
        $user->exists = true;
        $this->actingAs($user);

        $manager = app(PersonaManager::class);
        $manager->setActive($this->createPersona('A'));
        $manager->setActive($this->createPersona('B'));

        $this->assertSame(0, PersonaSwitchLog::query()->count());
    }

    private function createPersona(string $name): Persona
    {
        return Persona::create([
            'user_id' => 1,
            'name' => $name,
            'context' => [],
            'is_active' => false,
        ]);
    }
}

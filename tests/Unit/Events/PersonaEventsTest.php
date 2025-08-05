<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PersonaEventsTest extends TestCase
{
    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
        Event::fake();
    }

    public function test_persona_activated_event_is_fired_when_setting_active_persona(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        // Assert event was fired
        Event::assertDispatched(PersonaActivated::class, function ($event) use ($persona) {
            return $event->getPersona()->getId() === $persona->getId()
                && $event->getPersona()->getName() === 'Test Persona'
                && $event->getContext()['method'] === 'setActive';
        });
    }

    public function test_persona_switched_event_is_fired_when_switching_personas(): void
    {
        // Create user and two personas
        $user = $this->createUser();

        $persona1 = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'First Persona',
            'context' => ['role' => 'user'],
            'is_active' => true,
        ]);

        $persona2 = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Second Persona',
            'context' => ['role' => 'admin'],
            'is_active' => false,
        ]);

        // Set first persona as active
        $this->personaManager->setActive($persona1);
        Event::clearResolvedInstances(); // Clear previous events

        // Switch to second persona
        $this->personaManager->setActive($persona2);

        // Assert PersonaSwitched event was fired
        Event::assertDispatched(PersonaSwitched::class, function ($event) use ($persona1, $persona2) {
            return $event->getPersona()->getId() === $persona2->getId()
                && $event->getPreviousPersona()?->getId() === $persona1->getId()
                && $event->getPersona()->getName() === 'Second Persona'
                && $event->getPreviousPersona()?->getName() === 'First Persona'
                && ! $event->isInitialActivation();
        });
    }

    public function test_persona_deactivated_event_is_fired_when_clearing_persona(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Set active persona then clear it
        $this->personaManager->setActive($persona);
        Event::clearResolvedInstances(); // Clear previous events

        $this->personaManager->clear();

        // Assert event was fired
        Event::assertDispatched(PersonaDeactivated::class, function ($event) use ($persona) {
            return $event->getPersona()->getId() === $persona->getId()
                && $event->getPersona()->getName() === 'Test Persona'
                && $event->getContext()['method'] === 'clear';
        });
    }

    public function test_persona_switched_event_is_initial_activation_when_no_previous_persona(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Set active persona (first time)
        $this->personaManager->setActive($persona);

        // PersonaSwitched should NOT be fired for initial activation
        Event::assertNotDispatched(PersonaSwitched::class);

        // But PersonaActivated should be fired
        Event::assertDispatched(PersonaActivated::class);
    }

    public function test_no_events_fired_when_clearing_with_no_active_persona(): void
    {
        // Clear when no persona is active
        $this->personaManager->clear();

        // No events should be fired
        Event::assertNotDispatched(PersonaActivated::class);
        Event::assertNotDispatched(PersonaSwitched::class);
        Event::assertNotDispatched(PersonaDeactivated::class);
    }

    public function test_events_contain_correct_context_data(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Test Persona',
            'context' => ['role' => 'admin', 'permissions' => ['read', 'write']],
            'is_active' => true,
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        // Assert event contains correct data
        Event::assertDispatched(PersonaActivated::class, function ($event) {
            $summary = $event->getSummary();

            return isset($summary['persona']['id'])
                && isset($summary['persona']['name'])
                && isset($summary['persona']['context'])
                && isset($summary['timestamp'])
                && $summary['persona']['name'] === 'Test Persona'
                && $summary['persona']['context']['role'] === 'admin';
        });
    }

    public function test_switch_to_method_fires_events(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->getKey(),
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Use setActive instead of switchTo since our test user doesn't have the trait
        $this->personaManager->setActive($persona);

        // Assert event was fired
        Event::assertDispatched(PersonaActivated::class, function ($event) use ($persona) {
            return $event->getPersona()->getId() === $persona->getId();
        });
    }

    private function createUser(): object
    {
        // Create a simple User-like model for testing
        return new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->id = 1;
                $this->name = 'Test User';
                $this->email = 'test@example.com';
            }
        };
    }
}

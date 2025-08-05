<?php

declare(strict_types=1);

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Traits\HasPersonas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Create a test user model
class TestUser extends Model
{
    use HasPersonas;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}

beforeEach(function () {
    // Create personas table manually
    Schema::create('personas', function ($table) {
        $table->id();
        $table->string('name');
        $table->json('context')->nullable();
        $table->boolean('is_active')->default(false);
        $table->unsignedBigInteger('user_id');
        $table->string('user_type')->default('App\Models\User');
        $table->timestamps();

        $table->index(['user_id', 'user_type']);
        $table->index(['user_id', 'is_active']);
    });

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('can create personas for a user', function () {
    $persona = $this->user->createPersona('Admin', ['role' => 'admin']);

    expect($persona)->toBeInstanceOf(Persona::class);
    expect($persona->name)->toBe('Admin');
    expect($persona->context)->toBe(['role' => 'admin']);
    expect($persona->user_id)->toBe($this->user->id);
});

it('can retrieve user personas', function () {
    $this->user->createPersona('Admin', ['role' => 'admin']);
    $this->user->createPersona('User', ['role' => 'user']);

    $personas = $this->user->personas;

    expect($personas)->toHaveCount(2);
    expect($personas->pluck('name')->toArray())->toBe(['Admin', 'User']);
});

it('can switch between personas', function () {
    $adminPersona = $this->user->createPersona('Admin', ['role' => 'admin']);
    $userPersona = $this->user->createPersona('User', ['role' => 'user']);

    // Switch to admin persona
    $this->user->switchToPersona($adminPersona);

    expect($this->user->activePersona()->id)->toBe($adminPersona->id);
    expect($this->user->activePersona()->isActive())->toBeTrue();

    // Switch to user persona
    $this->user->switchToPersona($userPersona);

    expect($this->user->activePersona()->id)->toBe($userPersona->id);

    // Previous persona should be deactivated
    $adminPersona->refresh();
    expect($adminPersona->isActive())->toBeFalse();
});

it('can check if user has specific persona', function () {
    $this->user->createPersona('Admin', ['role' => 'admin']);

    expect($this->user->hasPersona('Admin'))->toBeTrue();
    expect($this->user->hasPersona('NonExistent'))->toBeFalse();
});

it('can get persona by name', function () {
    $adminPersona = $this->user->createPersona('Admin', ['role' => 'admin']);

    $foundPersona = $this->user->getPersonaByName('Admin');

    expect($foundPersona)->not->toBeNull();
    expect($foundPersona->id)->toBe($adminPersona->id);
});

it('returns null for non-existent persona name', function () {
    $foundPersona = $this->user->getPersonaByName('NonExistent');

    expect($foundPersona)->toBeNull();
});

it('can check persona permissions', function () {
    $persona = Persona::create([
        'name' => 'Admin',
        'context' => ['permissions' => ['read', 'write', 'delete']],
        'user_id' => $this->user->id,
        'user_type' => TestUser::class,
    ]);

    expect($persona->canAccess('read'))->toBeTrue();
    expect($persona->canAccess('write'))->toBeTrue();
    expect($persona->canAccess('execute'))->toBeFalse();
});

<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Scopes\PersonaScope;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use Tests\Fixtures\CompanyDocument;
use Tests\Fixtures\Document;
use Tests\TestCase;

class BelongsToPersonaTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('persona_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
        });
    }

    public function test_queries_only_return_the_rows_of_the_active_persona(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', [], true);
        $client = $this->createPersona(1, 'Client');
        $this->insertDocument('Admin report', ['persona_id' => $admin->id]);
        $this->insertDocument('Client invoice', ['persona_id' => $client->id]);
        $this->insertDocument('Orphan', ['persona_id' => null]);

        $this->assertSame(['Admin report'], Document::query()->pluck('title')->all());
        $this->assertSame(1, Document::query()->count());
        $this->assertNull(Document::query()->where('title', 'Client invoice')->first());
    }

    public function test_the_filter_follows_the_active_persona(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');
        $client = $this->createPersona(1, 'Client');
        $this->insertDocument('Admin report', ['persona_id' => $admin->id]);
        $this->insertDocument('Client invoice', ['persona_id' => $client->id]);
        $manager = app(PersonaManager::class);

        $manager->setActive($admin);
        $this->assertSame(['Admin report'], Document::query()->pluck('title')->all());

        $manager->setActive($client);
        $this->assertSame(['Client invoice'], Document::query()->pluck('title')->all());
    }

    public function test_queries_are_not_filtered_without_active_persona_by_default(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');
        $this->insertDocument('Admin report', ['persona_id' => $admin->id]);
        $this->insertDocument('Orphan', ['persona_id' => null]);

        $this->assertSame(2, Document::query()->count());
    }

    public function test_strict_mode_returns_no_row_without_active_persona(): void
    {
        config()->set('multipersona.scope.strict', true);

        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin');
        $this->insertDocument('Admin report', ['persona_id' => $admin->id]);
        $this->insertDocument('Orphan', ['persona_id' => null]);

        $this->assertSame(0, Document::query()->count());
        $this->assertSame(2, Document::query()->withoutPersonaScope()->count());

        app(PersonaManager::class)->setActive($admin);

        $this->assertSame(['Admin report'], Document::query()->pluck('title')->all());
    }

    public function test_the_column_is_configurable(): void
    {
        config()->set('multipersona.scope.column', 'owner_id');

        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', [], true);
        $client = $this->createPersona(1, 'Client');
        $this->insertDocument('Admin report', ['owner_id' => $admin->id, 'persona_id' => $client->id]);
        $this->insertDocument('Client invoice', ['owner_id' => $client->id, 'persona_id' => $admin->id]);

        $this->assertSame('owner_id', (new Document)->getPersonaColumn());
        $this->assertSame('documents.owner_id', (new Document)->getQualifiedPersonaColumn());
        $this->assertSame(['Admin report'], Document::query()->pluck('title')->all());
    }

    public function test_the_default_column_is_used_when_the_configuration_is_missing(): void
    {
        config()->set('multipersona.scope', null);

        $this->assertSame('persona_id', (new Document)->getPersonaColumn());
        $this->assertNull((new Document)->getPersonaContextKey());
        $this->assertSame(PersonaScope::DEFAULT_COLUMN, PersonaScope::column(new Document));
    }

    public function test_the_column_can_be_compared_with_a_value_of_the_persona_context(): void
    {
        config()->set('multipersona.scope.column', 'company_id');
        config()->set('multipersona.scope.context_key', 'company_id');

        $this->actingAsUser(1);
        $this->createPersona(1, 'Acme admin', ['company_id' => 10], true);
        $this->insertDocument('Acme contract', ['company_id' => 10]);
        $this->insertDocument('TechStart contract', ['company_id' => 20]);

        $this->assertSame(['Acme contract'], Document::query()->pluck('title')->all());
    }

    public function test_a_model_can_override_the_column_and_the_context_key(): void
    {
        $this->actingAsUser(1);
        $acme = $this->createPersona(1, 'Acme admin', ['company_id' => 10]);
        $techStart = $this->createPersona(1, 'TechStart user', ['company_id' => 20]);
        $this->insertDocument('Acme contract', ['company_id' => 10, 'persona_id' => $techStart->id]);
        $this->insertDocument('TechStart contract', ['company_id' => 20, 'persona_id' => $acme->id]);
        $manager = app(PersonaManager::class);

        $manager->setActive($acme);
        $this->assertSame(['Acme contract'], CompanyDocument::query()->pluck('title')->all());
        $this->assertSame(['TechStart contract'], Document::query()->pluck('title')->all());

        $manager->setActive($techStart);
        $this->assertSame(['TechStart contract'], CompanyDocument::query()->pluck('title')->all());
    }

    public function test_a_persona_without_the_context_value_sees_no_row(): void
    {
        $this->actingAsUser(1);
        $this->createPersona(1, 'No company', ['role' => 'user'], true);
        $this->insertDocument('Acme contract', ['company_id' => 10]);
        $this->insertDocument('Orphan', ['company_id' => null]);

        $this->assertSame(0, CompanyDocument::query()->count());
        $this->assertSame(2, CompanyDocument::query()->withoutPersonaScope()->count());
    }

    public function test_new_records_are_assigned_to_the_active_persona(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', ['company_id' => 10], true);

        $document = Document::query()->create(['title' => 'Admin report']);
        $companyDocument = CompanyDocument::query()->create(['title' => 'Acme contract']);

        $this->assertEquals($admin->id, $document->getAttribute('persona_id'));
        $this->assertEquals(10, $companyDocument->getAttribute('company_id'));
        $this->assertNull($companyDocument->getAttribute('persona_id'));
    }

    public function test_an_explicit_persona_is_kept_on_new_records(): void
    {
        $this->actingAsUser(1);
        $this->createPersona(1, 'Admin', [], true);
        $client = $this->createPersona(1, 'Client');

        $document = Document::query()->create(['title' => 'Client invoice', 'persona_id' => $client->id]);

        $this->assertEquals($client->id, $document->getAttribute('persona_id'));
    }

    public function test_new_records_are_left_untouched_without_active_persona(): void
    {
        $this->actingAsUser(1);

        $document = Document::query()->create(['title' => 'Orphan']);

        $this->assertNull($document->getAttribute('persona_id'));
    }

    public function test_the_filter_can_be_removed_or_targeted_at_another_persona(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', ['company_id' => 10], true);
        $client = $this->createPersona(1, 'Client', ['company_id' => 20]);
        $this->insertDocument('Admin report', ['persona_id' => $admin->id, 'company_id' => 10]);
        $this->insertDocument('Client invoice', ['persona_id' => $client->id, 'company_id' => 20]);

        $this->assertSame(2, Document::query()->withoutPersonaScope()->count());
        $this->assertSame(2, Document::query()->withoutGlobalScope(PersonaScope::class)->count());
        $this->assertSame(['Client invoice'], Document::query()->forPersona($client)->pluck('title')->all());
        $this->assertSame(['Client invoice'], Document::query()->forPersona($client->id)->pluck('title')->all());
        $this->assertSame(['Client invoice'], CompanyDocument::query()->forPersona($client)->pluck('title')->all());
        $this->assertSame(['Client invoice'], CompanyDocument::query()->forPersona(20)->pluck('title')->all());
    }

    public function test_the_persona_relationship_uses_the_persona_column(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', [], true);

        $document = Document::query()->create(['title' => 'Admin report']);

        $this->assertTrue($admin->is($document->persona));
        $this->assertSame('persona_id', $document->persona()->getForeignKeyName());
    }

    public function test_the_filter_applies_to_updates_and_deletes(): void
    {
        $this->actingAsUser(1);
        $admin = $this->createPersona(1, 'Admin', [], true);
        $client = $this->createPersona(1, 'Client');
        $this->insertDocument('Admin report', ['persona_id' => $admin->id]);
        $this->insertDocument('Client invoice', ['persona_id' => $client->id]);

        Document::query()->update(['title' => 'Renamed']);
        Document::query()->delete();

        $this->assertSame(['Client invoice'], DB::table('documents')->pluck('title')->all());
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // The bundled listeners are not needed here
        $app['config']->set('multipersona.register_listeners', false);
    }

    private function actingAsUser(int $id): Authenticatable
    {
        $user = (new Authenticatable)->forceFill(['id' => $id]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createPersona(int $userId, string $name, array $context = [], bool $active = false): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => $context,
            'is_active' => $active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertDocument(string $title, array $attributes): void
    {
        DB::table('documents')->insert(array_merge([
            'title' => $title,
            'persona_id' => null,
            'owner_id' => null,
            'company_id' => null,
        ], $attributes));
    }
}

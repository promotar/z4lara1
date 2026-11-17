<?php

namespace Tests\Feature;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Plugins\Uninstall\PluginTableDropper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PluginTableDropperTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('contract_external');
        Schema::dropIfExists('contract_child');
        Schema::dropIfExists('contract_parent');

        parent::tearDown();
    }

    public function test_owned_tables_are_dropped_in_foreign_key_dependency_order(): void
    {
        $this->createParentAndChild();

        $dropped = app(PluginTableDropper::class)->drop(
            $this->manifest(['contract_parent', 'contract_child']),
        );

        $this->assertSame(['contract_child', 'contract_parent'], $dropped);
        $this->assertFalse(Schema::hasTable('contract_child'));
        $this->assertFalse(Schema::hasTable('contract_parent'));
    }

    public function test_external_foreign_key_blocks_plugin_table_purge(): void
    {
        Schema::create('contract_parent', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('contract_external', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('contract_parent');
        });

        try {
            app(PluginTableDropper::class)->drop($this->manifest(['contract_parent']));
            $this->fail('Expected an external foreign key to block plugin purge.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('contract_external', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('contract_parent'));
        $this->assertTrue(Schema::hasTable('contract_external'));
    }

    private function createParentAndChild(): void
    {
        Schema::create('contract_parent', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('contract_child', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('contract_parent');
        });
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function manifest(array $tables): PluginManifest
    {
        return PluginManifest::fromArray([
            'name' => 'Contract Test',
            'slug' => 'contract-test',
            'version' => '1.0.0',
            'provider' => 'Tests\\Fixtures\\ContractProvider',
            'uninstall' => [
                'tables' => $tables,
            ],
        ]);
    }
}

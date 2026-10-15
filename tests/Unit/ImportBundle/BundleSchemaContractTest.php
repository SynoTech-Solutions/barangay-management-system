<?php

namespace Tests\Unit\ImportBundle;

use App\Domain\ImportBundle\BundleFormat;
use App\Enums\BundleEntity;
use App\Enums\LookupType;
use App\Enums\RoleScope;
use App\Enums\SystemRole;
use PHPUnit\Framework\TestCase;

class BundleSchemaContractTest extends TestCase
{
    private const string DIRECTORY = __DIR__.'/../../../resources/import-bundle/v1/';

    private const string ID_PREFIX = 'https://schemas.bms.local/import-bundle/v1/';

    public function test_every_entity_has_a_schema_with_a_matching_id_and_a_source_key(): void
    {
        foreach (BundleEntity::cases() as $entity) {
            $schema = $this->schema($entity->schemaFile());
            $this->assertSame(self::ID_PREFIX.$entity->schemaFile(), $schema['$id'] ?? null);
            $this->assertContains('source_key', $schema['required'] ?? []);
            $this->assertFalse($schema['additionalProperties'] ?? true, $entity->value);
        }

        $files = array_map('basename', glob(self::DIRECTORY.'*.schema.json') ?: []);
        sort($files);
        $expected = [...array_map(fn (BundleEntity $entity): string => $entity->schemaFile(), BundleEntity::cases()), 'common.schema.json', BundleFormat::MANIFEST_SCHEMA];
        sort($expected);
        $this->assertSame($expected, $files);
    }

    public function test_the_manifest_lists_every_entity_and_this_version(): void
    {
        $manifest = $this->schema(BundleFormat::MANIFEST_SCHEMA);

        $this->assertSame(BundleEntity::values(), $manifest['properties']['entities']['propertyNames']['enum']);
        $this->assertSame(BundleFormat::VERSION, $manifest['properties']['version']['const']);
        $this->assertSame(BundleFormat::NAME, $manifest['properties']['format']['const']);
    }

    public function test_shared_enums_match_the_application_enums(): void
    {
        $defs = $this->schema('common.schema.json')['$defs'];

        $this->assertSame(array_map(fn (LookupType $type): string => $type->value, LookupType::cases()), $defs['lookup_type']['enum']);
        $roles = array_values(array_filter(SystemRole::cases(), fn (SystemRole $role): bool => $role->scope() === RoleScope::Barangay));
        $this->assertSame(array_map(fn (SystemRole $role): string => $role->value, $roles), $defs['barangay_role']['enum']);
    }

    public function test_users_reject_a_password_field(): void
    {
        $this->assertFalse($this->schema(BundleEntity::Users->schemaFile())['properties']['password']);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $file): array
    {
        $decoded = json_decode((string) file_get_contents(self::DIRECTORY.$file), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}

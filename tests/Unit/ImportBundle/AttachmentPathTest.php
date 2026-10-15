<?php

namespace Tests\Unit\ImportBundle;

use App\Domain\ImportBundle\AttachmentPath;
use App\Domain\ImportBundle\UniqueName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttachmentPathTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function paths(): array
    {
        return [
            'nested' => ['residents/r-1.png', true],
            'flat' => ['photo.jpg', true],
            'parent' => ['../manifest.json', false],
            'inner parent' => ['residents/../../x.png', false],
            'absolute' => ['/etc/hosts', false],
            'backslash' => ['residents\\r.png', false],
            'drive' => ['C:/x.png', false],
            'empty segment' => ['residents//r.png', false],
            'dot segment' => ['./r.png', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('paths')]
    public function test_only_relative_paths_inside_the_files_folder_are_safe(string $path, bool $safe): void
    {
        $this->assertSame($safe, AttachmentPath::isSafe($path));
    }

    public function test_unique_names_and_usernames_keep_their_limits(): void
    {
        $this->assertSame('Store', UniqueName::candidate('Store', 1, 10));
        $this->assertSame('Store (2)', UniqueName::candidate('Store', 2, 10));
        $this->assertSame('Long (3)', UniqueName::candidate('Long name', 3, 8));
        $this->assertSame('admin-9901010001', UniqueName::username('admin', '9901010001', 1, 64));
        $this->assertSame('admin-9901010001-2', UniqueName::username('admin', '9901010001', 2, 64));
        $this->assertSame(16, mb_strlen(UniqueName::username(str_repeat('a', 20), '9901010001', 1, 16)));
    }
}

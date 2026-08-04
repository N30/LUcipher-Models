<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Models\LuModel;
use All1\LuModels\Tests\TestCase;

class LuModelTest extends TestCase
{
    public function test_get_namespace_from_file_extracts_namespace(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lu');
        file_put_contents($file, "<?php\n\nnamespace App\\Models\\Deep;\n\nclass Thing {}\n");

        $this->assertSame('App\\Models\\Deep', LuModel::getNamespaceFromFile($file));

        unlink($file);
    }

    public function test_get_namespace_from_file_returns_null_without_namespace(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lu');
        file_put_contents($file, "<?php\n\nclass Legacy {}\n");

        $this->assertNull(LuModel::getNamespaceFromFile($file));

        unlink($file);
    }

    public function test_lu_model_defaults_are_secure(): void
    {
        $model = new class extends LuModel {};

        $this->assertTrue($model->creation_safety);
        $this->assertTrue($model->update_safety);
        $this->assertTrue($model->read_safety);
        $this->assertTrue($model->delete_safety);
        $this->assertFalse($model->allow_public_access_by_id);
        $this->assertFalse($model->allow_public_access_by_slug);
        $this->assertFalse($model->allow_any_unencrypred_access);
        $this->assertSame([], $model->public_pages);
    }
}

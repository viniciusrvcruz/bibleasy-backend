<?php

use App\Enums\VersionTextSourceEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->string('external_version_id')
                ->nullable()
                ->after('copyright');
            $table->string('text_source')
                ->default(VersionTextSourceEnum::DATABASE->value)
                ->after('external_version_id');
            $table->unsignedInteger('cache_ttl')
                ->nullable()
                ->after('text_source');
        });
    }

    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropColumn([
                'external_version_id',
                'text_source',
                'cache_ttl',
            ]);
        });
    }
};

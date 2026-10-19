<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title', 120)->comment('Shown as the headline');
            $table->text('body')->nullable();
            $table->string('secret_token')->nullable();
            $table->timestamps();
        });

        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('body');
            $table->timestamps();
        });

        // every column kind the forms, the table and the writer have a branch for
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->date('starts_on')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->time('at')->nullable();
            $table->integer('seats')->nullable();
            $table->boolean('free')->default(false);
            $table->string('contact_email')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('memos');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');
    }
};

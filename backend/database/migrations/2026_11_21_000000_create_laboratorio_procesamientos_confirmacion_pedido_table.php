<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratorio_procesamientos_confirmacion_pedido', function (Blueprint $table): void {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->uuid('correlation_id')->index();
            $table->unsignedInteger('pedido_id')->index();
            $table->string('tipo', 100);
            $table->unsignedSmallInteger('version');
            $table->timestampTz('ocurrido_en');
            $table->string('resultado', 30);
            $table->timestampTz('procesado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratorio_procesamientos_confirmacion_pedido');
    }
};

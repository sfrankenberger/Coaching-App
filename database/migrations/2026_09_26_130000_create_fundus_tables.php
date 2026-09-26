<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachschlagen (Fundus): Werkzeuge fuer die Coach-Ausbildung, Sammlungen (die Coachin stellt
 * Inhalte zusammen und schickt sie), Suchverlauf je Person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('purpose')->nullable();      // Wofuer ist es da
            $table->text('fits_when')->nullable();    // Wann passt es
            $table->text('not_when')->nullable();     // Wann passt es nicht
            $table->text('steps')->nullable();        // So geht es
            $table->text('example')->nullable();      // Beispiel aus der Praxis
            $table->string('duration', 120)->nullable();
            $table->text('material')->nullable();     // Was es braucht
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('sammlungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // wer sie zusammengestellt hat
            $table->string('title');
            $table->text('greeting')->nullable();
            $table->json('items');                                            // [{art, id}]
            $table->string('key', 40);
            $table->json('recipients')->nullable();                           // user_ids
            $table->json('seen')->nullable();                                 // user_ids
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('text', 200);
            $table->string('kind', 12);                                       // wort, thema, frage
            $table->text('answer')->nullable();
            $table->json('items')->nullable();                                // [{art, id}]
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        // Themen in Gruppen (fuer die Auswahlliste im Nachschlagen)
        Schema::table('topics', function (Blueprint $table) {
            $table->string('group', 80)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('group'));
        Schema::dropIfExists('search_histories');
        Schema::dropIfExists('sammlungen');
        Schema::dropIfExists('tools');
    }
};

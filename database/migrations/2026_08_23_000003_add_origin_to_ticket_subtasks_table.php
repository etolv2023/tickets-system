<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells a subtask the system wrote from one a person wrote.
 *
 * F06.3 creates a "starter" subtask automatically the first time a role is
 * handed out, titled after the ticket. It is not work anybody chose to assign —
 * it is the role's own placeholder on the ticket — and the two need to be told
 * apart for one reason: a real unfinished subtask must block moving that role to
 * somebody else, while the generated starter must simply follow the role.
 *
 * Until now the only way to spot the starter was that its title equalled the
 * ticket's, which is not a fact about the row — it is a coincidence a user can
 * reproduce by naming a subtask after the ticket, and one a title edit destroys.
 * Deciding whether work blocks a hand-off on that basis would be guessing.
 *
 * Nullable, and existing rows stay null. Null means "treat as real work", which
 * is the conservative direction: an old starter keeps blocking until somebody
 * finishes it, rather than silently becoming invisible to a rule that protects
 * against stranded work. No backfill, because the only available backfill key
 * would be that same title heuristic.
 *
 * A string rather than a boolean so the other generated kind — the «مستني رد»
 * follow-up from createFollowUpSubtask — can be labelled later without a second
 * column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->string('origin', 24)->nullable()->after('role_id');
            // Every rule that reads this asks "the unfinished ones for this role,
            // and which of them are generated?"
            $table->index(['ticket_id', 'role_id', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'role_id', 'origin']);
            $table->dropColumn('origin');
        });
    }
};

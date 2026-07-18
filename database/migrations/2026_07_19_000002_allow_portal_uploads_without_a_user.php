<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A file uploaded by a client through the portal has no uploader: the person
 * on the other end is a contact, not a user in this system — the same reason
 * ticket_comments.user_id is already nullable.
 *
 * Raw SQL because doctrine/dbal (needed for Blueprint::change()) isn't
 * installed, and CLAUDE.md § 1 requires asking before adding a package.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ticket_attachments DROP FOREIGN KEY ticket_attachments_uploaded_by_foreign');
        DB::statement('ALTER TABLE ticket_attachments MODIFY uploaded_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE ticket_attachments ADD CONSTRAINT ticket_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES users (id)');
    }

    public function down(): void
    {
        // Portal uploads have no user to point at, so they must go before the
        // column can be NOT NULL again.
        DB::table('ticket_attachments')->whereNull('uploaded_by')->delete();

        DB::statement('ALTER TABLE ticket_attachments DROP FOREIGN KEY ticket_attachments_uploaded_by_foreign');
        DB::statement('ALTER TABLE ticket_attachments MODIFY uploaded_by BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE ticket_attachments ADD CONSTRAINT ticket_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES users (id)');
    }
};

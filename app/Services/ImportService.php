<?php

namespace App\Services;

use App\Enums\ImportType;
use App\Imports\SheetReader;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\ImportBatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use finfo;

/**
 * Excel import: template -> upload -> validate -> preview -> confirm -> report (F02).
 *
 * Nothing is written until commit(). analyse() is a dry run that reports exactly
 * what commit() would do, which is what makes the preview trustworthy.
 */
class ImportService
{
    public const MAX_ROWS = 5000;

    /** Above this, commit() belongs in a queue rather than a request. F02 */
    public const QUEUE_THRESHOLD = 500;

    private const DIR = 'imports';

    private const MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip', // .xlsx is a zip; some systems report the container
        'text/csv',
        'text/plain',
    ];

    public function storeUpload(UploadedFile $file, ImportType $type, int $userId): ImportBatch
    {
        $this->assertRealSheet($file);

        $stored = self::DIR . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('local')->put($stored, file_get_contents($file->getRealPath()));

        // created_at is set by Eloquent; the model only opts out of updated_at.
        return ImportBatch::create([
            'user_id' => $userId,
            'type' => $type->value,
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_name' => $stored,
            'status' => 'validating',
        ]);
    }

    /**
     * Dry run. Returns the same decisions commit() will make, so what the user
     * approves is what actually happens.
     *
     * @return array{rows: array<int, array<string, mixed>>, counts: array<string, int>, errors: array<int, array<string, mixed>>}
     */
    public function analyse(ImportBatch $batch): array
    {
        $raw = $this->readRows($batch);

        if (count($raw) > self::MAX_ROWS) {
            throw new RuntimeException(
                'الملف فيه ' . count($raw) . ' صف. الحد الأقصى ' . self::MAX_ROWS . ' صف.'
            );
        }

        $context = $this->context($batch->type);
        $rows = [];
        $errors = [];
        $counts = ['create' => 0, 'update' => 0, 'fail' => 0];
        // Tracks keys seen earlier in this same file, so a sheet that repeats a
        // code doesn't report two "create" rows for one eventual record.
        $seen = [];

        foreach ($raw as $index => $row) {
            // +2: row 1 is the header, and humans count from 1.
            $number = $index + 2;
            $row = $this->normaliseRow($batch->type, $row);

            $failure = $this->validateRow($batch->type, $row, $context, $seen);

            if ($failure !== null) {
                $counts['fail']++;
                $errors[] = ['row' => $number, 'column' => $failure['column'], 'reason' => $failure['reason']] + ['data' => $row];
                $rows[] = ['row' => $number, 'action' => 'fail', 'reason' => $failure['reason'], 'data' => $row];

                continue;
            }

            $key = $this->rowKey($batch->type, $row, $context);
            $exists = isset($context['existing'][$key]) || isset($seen[$key]);
            $seen[$key] = true;

            $counts[$exists ? 'update' : 'create']++;
            $rows[] = ['row' => $number, 'action' => $exists ? 'update' : 'create', 'reason' => null, 'data' => $row];
        }

        $batch->update([
            'total_rows' => count($raw),
            'failed_rows' => $counts['fail'],
            'errors' => $errors === [] ? null : array_slice($errors, 0, 500),
            'status' => 'previewing',
        ]);

        return ['rows' => $rows, 'counts' => $counts, 'errors' => $errors];
    }

    /**
     * Writes the valid rows. A failed row is skipped and counted; it never stops
     * the rest of the file (F02).
     */
    public function commit(ImportBatch $batch): void
    {
        $batch->update(['status' => 'importing']);

        try {
            $raw = $this->readRows($batch);
            $context = $this->context($batch->type);
            $created = $updated = $failed = 0;
            $errors = [];
            $seen = [];

            foreach ($raw as $index => $row) {
                $number = $index + 2;
                $row = $this->normaliseRow($batch->type, $row);

                $failure = $this->validateRow($batch->type, $row, $context, $seen);

                if ($failure !== null) {
                    $failed++;
                    $errors[] = ['row' => $number, 'column' => $failure['column'], 'reason' => $failure['reason']] + ['data' => $row];

                    continue;
                }

                $key = $this->rowKey($batch->type, $row, $context);
                $seen[$key] = true;

                // Each row is its own transaction: one bad row rolls back only itself.
                DB::transaction(function () use ($batch, $row, $context, &$created, &$updated) {
                    $this->writeRow($batch->type, $row, $context) === 'created' ? $created++ : $updated++;
                });
            }

            $batch->update([
                'total_rows' => count($raw),
                'created_rows' => $created,
                'updated_rows' => $updated,
                'failed_rows' => $failed,
                'errors' => $errors === [] ? null : array_slice($errors, 0, 500),
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'errors' => [['row' => 0, 'column' => '-', 'reason' => $e->getMessage()]],
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function readRows(ImportBatch $batch): array
    {
        $reader = new SheetReader();
        Excel::import($reader, Storage::disk('local')->path($batch->stored_name));

        return $reader->rows;
    }

    /** Lookup tables the row validators need, fetched once per run. */
    private function context(ImportType $type): array
    {
        return match ($type) {
            ImportType::Companies => ['existing' => Company::pluck('id', 'code')->all()],
            ImportType::Contacts => [
                'companies' => Company::pluck('id', 'code')->all(),
                'existing' => CompanyContact::query()
                    ->get(['id', 'company_id', 'erp_employee_id'])
                    ->mapWithKeys(fn ($c) => ["{$c->company_id}|{$c->erp_employee_id}" => $c->id])
                    ->all(),
            ],
            ImportType::Users => [
                'roles' => Role::pluck('id', 'key')->all(),
                'existing' => User::pluck('id', 'email')->all(),
            ],
        };
    }

    /** Trims, and turns the sheet's loose truthiness into a real boolean. */
    private function normaliseRow(ImportType $type, array $row): array
    {
        $clean = [];

        foreach ($type->columns() as $column) {
            $value = $row[$column] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $clean[$column] = $value === '' ? null : $value;
        }

        if (array_key_exists('is_active', $clean)) {
            $clean['is_active'] = $this->toBool($clean['is_active']);
        }

        if (array_key_exists('email', $clean) && is_string($clean['email'])) {
            $clean['email'] = Str::lower($clean['email']);
        }

        return $clean;
    }

    private function toBool(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return in_array(Str::lower((string) $value), ['1', 'true', 'yes', 'y', 'نعم', 'مفعل', 'active'], true);
    }

    /** @return array{column: string, reason: string}|null */
    private function validateRow(ImportType $type, array $row, array $context, array $seen): ?array
    {
        $rules = match ($type) {
            ImportType::Companies => [
                'name' => ['required', 'string', 'max:150'],
                'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            ImportType::Contacts => [
                'company_code' => ['required', 'string'],
                'name' => ['required', 'string', 'max:150'],
                'erp_employee_id' => ['required', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'],
            ],
            ImportType::Users => [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255'],
                'role_key' => ['required', 'string'],
            ],
        };

        $validator = Validator::make($row, $rules, [], __('validation.attributes'));

        if ($validator->fails()) {
            $column = array_key_first($validator->failed());

            return ['column' => $column, 'reason' => $validator->errors()->first($column)];
        }

        return $this->validateReferences($type, $row, $context);
    }

    /** Cross-references the sheet against what's already in the database. */
    private function validateReferences(ImportType $type, array $row, array $context): ?array
    {
        if ($type === ImportType::Contacts && ! isset($context['companies'][$row['company_code']])) {
            return [
                'column' => 'company_code',
                'reason' => "الشركة بكود «{$row['company_code']}» مش موجودة. استورد الشركات الأول.",
            ];
        }

        if ($type === ImportType::Users) {
            if (! isset($context['roles'][$row['role_key']])) {
                $known = implode('، ', array_keys($context['roles']));

                return [
                    'column' => 'role_key',
                    'reason' => "الدور «{$row['role_key']}» مش موجود. المتاح: {$known}",
                ];
            }
        }

        return null;
    }

    private function rowKey(ImportType $type, array $row, array $context): string
    {
        return match ($type) {
            ImportType::Companies => (string) $row['code'],
            ImportType::Contacts => $context['companies'][$row['company_code']] . '|' . $row['erp_employee_id'],
            ImportType::Users => (string) $row['email'],
        };
    }

    /** @return string 'created' or 'updated' */
    private function writeRow(ImportType $type, array $row, array $context): string
    {
        return match ($type) {
            ImportType::Companies => $this->writeCompany($row),
            ImportType::Contacts => $this->writeContact($row, $context),
            ImportType::Users => $this->writeUser($row, $context),
        };
    }

    private function writeCompany(array $row): string
    {
        $company = Company::firstOrNew(['code' => $row['code']]);
        $existed = $company->exists;

        $company->fill([
            'name' => $row['name'],
            'is_active' => $row['is_active'],
            'notes' => $row['notes'],
        ])->save();

        return $existed ? 'updated' : 'created';
    }

    private function writeContact(array $row, array $context): string
    {
        $contact = CompanyContact::firstOrNew([
            'company_id' => $context['companies'][$row['company_code']],
            'erp_employee_id' => $row['erp_employee_id'],
        ]);
        $existed = $contact->exists;

        $contact->fill([
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'is_active' => $row['is_active'],
        ])->save();

        return $existed ? 'updated' : 'created';
    }

    /**
     * The sheet never carries a password (F02). New users get a random one they
     * can't know and are forced to replace it; existing users keep theirs, so a
     * re-import can never reset someone's password behind their back.
     */
    private function writeUser(array $row, array $context): string
    {
        $user = User::firstOrNew(['email' => $row['email']]);
        $existed = $user->exists;

        $user->fill([
            'name' => $row['name'],
            'role_id' => $context['roles'][$row['role_key']],
            'is_active' => $row['is_active'],
        ]);

        if (! $existed) {
            $user->password = Str::password(16);
            $user->must_change_password = true;
        }

        $user->save();

        return $existed ? 'updated' : 'created';
    }

    /** The extension and browser-sent type are attacker-controlled; the bytes aren't. */
    private function assertRealSheet(UploadedFile $file): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! in_array($mime, self::MIMES, true)) {
            throw new RuntimeException("الملف ده مش شيت صالح (النوع الحقيقي: {$mime}). المسموح: xlsx أو xls أو csv.");
        }
    }
}

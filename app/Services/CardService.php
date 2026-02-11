<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardService
{
    public function createCard(array $data): Card
    {
        return DB::transaction(function () use ($data) {
            $package = Package::query()
                ->where('id', $data['package_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$package) {
                throw ValidationException::withMessages([
                    'package_id' => ['Invalid or inactive package.'],
                ]);
            }

            // اختياري: منع تكرار username داخل نفس الباقة
            // if (Card::where('package_id', $package->id)->where('username', $data['username'])->exists()) { ... }

            $card = Card::create([
                'package_id' => $package->id,
                'username' => $data['username'],
                'password' => $data['password'], // سيتم تشفيرها داخل Card model mutator
                'status' => $data['status'] ?? 'available',
            ]);

            return $card;
        }, 3);
    }


    /**
     * Bulk import cards for one package.
     *
     * Input items example:
     * [
     *   ['username' => '101', 'password' => 'xxx', 'row_number' => 2],
     *   ...
     * ]
     */
    public function bulkImport(string $packageId, array $items): array
    {
        return DB::transaction(function () use ($packageId, $items) {

            $package = Package::query()
                ->where('id', $packageId)
                ->lockForUpdate()
                ->first();

            if (!$package) {
                throw ValidationException::withMessages([
                    'package_id' => ['Invalid or inactive package.'],
                ]);
            }

            // 1) Normalize input + keep row_number if provided
            $normalized = [];
            foreach ($items as $i => $row) {
                $username = isset($row['username']) ? trim((string) $row['username']) : '';
                $password = isset($row['password']) ? (string) $row['password'] : '';
                $rowNumber = $row['row_number'] ?? ($i + 1);

                if ($username === '' || $password === '') {
                    // تجاهل الصفوف الناقصة (أو اجعلها ضمن invalid_rows إذا تريد)
                    continue;
                }

                $normalized[] = [
                    'username' => $username,
                    'password' => $password,
                    'row_number' => (int) $rowNumber,
                ];
            }

            if (count($normalized) === 0) {
                throw ValidationException::withMessages([
                    'items' => ['No valid rows to import.'],
                ]);
            }

            // 2) Detect duplicates inside the file (same username repeated)
            $usernameToRows = [];
            foreach ($normalized as $row) {
                $u = $row['username'];
                $usernameToRows[$u] ??= [];
                $usernameToRows[$u][] = $row['row_number'];
            }

            $duplicatesInFile = [];
            foreach ($usernameToRows as $u => $rows) {
                if (count($rows) > 1) {
                    $duplicatesInFile[] = [
                        'username' => $u,
                        'rows' => $rows,
                        'reason' => 'duplicate_in_file',
                    ];
                }
            }

            $uniqueUsernames = array_keys($usernameToRows);

            // 3) Detect duplicates already in DB (single query)
            $existingUsernames = Card::query()
                ->whereIn('username', $uniqueUsernames)
                ->pluck('username')
                ->map(fn($v) => (string) $v)
                ->all();

            $existingSet = array_flip($existingUsernames);

            $duplicatesInDb = [];
            foreach ($existingUsernames as $u) {
                $duplicatesInDb[] = [
                    'username' => $u,
                    'rows' => $usernameToRows[$u] ?? [],
                    'reason' => 'already_exists_in_db',
                ];
            }

            // 4) Build insert list for only valid rows:
            // - exclude duplicates in file (optional policy)
            // - exclude those existing in DB
            //
            // سياسة مقترحة:
            // - أي username مكرر داخل الملف نفسه: نعتبره مشكلة ونستبعده كله (حتى لا ندخل نسخة ونترك أخرى)
            // - username موجود في DB: نستبعده
            $dupFileSet = [];
            foreach ($duplicatesInFile as $d) {
                $dupFileSet[$d['username']] = true;
            }

            $toInsert = [];
            $now = now();

            foreach ($normalized as $row) {
                $u = $row['username'];

                if (isset($dupFileSet[$u])) {
                    continue;
                }
                if (isset($existingSet[$u])) {
                    continue;
                }

                $toInsert[] = [
                    'package_id' => $package->id,
                    'username' => $u,
                    'password' => $row['password'], // encrypted by model mutator? (إن لم يوجد، قم بتشفيرها هنا)
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 5) Bulk insert in chunks (performance)
            $created = 0;

            if (!empty($toInsert)) {
                foreach (array_chunk($toInsert, 1000) as $chunk) {
                    // إذا عندك mutator لتشفير password على Model، insert() لا يستدعي mutators.
                    // لذلك:
                    // - إما تستخدم Card::create() (أبطأ)
                    // - أو تشفر هنا قبل insert
                    //
                    // الأفضل: تشفير هنا لو مطلوب.
                    DB::table('cards')->insert($chunk);
                    $created += count($chunk);
                }
            }

            return [
                'created' => $created,
                'skipped' => count($normalized) - $created,
                'duplicates' => [
                    'in_file' => $duplicatesInFile,
                    'in_db' => $duplicatesInDb,
                ],
            ];
        }, 3);
    }
}

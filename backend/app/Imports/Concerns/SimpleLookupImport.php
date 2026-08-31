<?php

namespace App\Imports\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Shared onRow()/dry-run/duplicate-detection/mode plumbing for plain
 * lookup-table imports (Departments, Grade Levels, Sections, Subjects,
 * Rooms) — entities with no privilege/business-rule complexity beyond "does
 * this unique code already exist." StudentsImport/UsersImport aren't built
 * on this: they have real per-row business logic (enrollment, guardian
 * attachment, privilege-escalation checks) that doesn't fit a
 * one-size-fits-all row handler, and "update an existing student/staff
 * record via bulk file" has murkier semantics (which fields? re-attach
 * guardians? change someone's role?) that deserve their own design rather
 * than inheriting this one blindly.
 *
 * A subclass only needs to implement uniqueKeyField(), modelClass(),
 * mapRow(), and rules(). uniqueKeyField() alone covers a single-column key
 * (code, in most subclasses); an entity whose real uniqueness is composite
 * (Section: academic_year_id + grade_level_id + name, matching its DB
 * unique constraint) overrides uniqueKey() and findExisting() instead —
 * see SectionsImport.
 */
abstract class SimpleLookupImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use CapsImportRows, Importable, SkipsFailures;

    public const MODE_CREATE = 'create';

    public const MODE_UPDATE = 'update';

    public const MODE_UPSERT = 'upsert';

    public const MODES = [self::MODE_CREATE, self::MODE_UPDATE, self::MODE_UPSERT];

    private int $importedCount = 0;

    private int $updatedCount = 0;

    /** @var array<int, Model> rows genuinely created (not updated) by this import — see createdModels(). */
    private array $createdModels = [];

    /** @var array<string, true> normalized (trimmed, lowercased) unique-key values already seen in this file */
    private array $seenKeys = [];

    public function __construct(
        protected readonly bool $dryRun = false,
        protected readonly string $mode = self::MODE_CREATE,
    ) {}

    public function onRow(Row $row): void
    {
        if ($this->overRowCap($row)) {
            return;
        }

        $data = $row->toCollection();
        $key = $this->uniqueKey($data);

        if (isset($this->seenKeys[$key])) {
            $this->failures[] = new Failure(
                $row->getIndex(), $this->uniqueKeyField(),
                ['This is a duplicate of an earlier row in this file.'],
                $data->toArray(),
            );

            return;
        }

        $attributes = $this->mapRow($data, $row->getIndex());

        if ($attributes === null) {
            return; // mapRow() already recorded a Failure for this row.
        }

        $existing = $this->mode !== self::MODE_CREATE ? $this->findExisting($data, $attributes) : null;

        if ($this->mode === self::MODE_UPDATE && ! $existing) {
            $this->failures[] = new Failure(
                $row->getIndex(), $this->uniqueKeyField(),
                ['No existing record found to update — check the key matches an existing row exactly, or use "Create" / "Create or update" instead.'],
                $data->toArray(),
            );

            return;
        }

        $this->seenKeys[$key] = true;

        if (! $this->dryRun) {
            if ($existing) {
                $existing->update($attributes);
            } else {
                $this->createdModels[] = $this->modelClass()::query()->create($attributes);
            }
        }

        $existing ? $this->updatedCount++ : $this->importedCount++;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    /** @return array<int, Model> rows genuinely created (not updated) by this import — fed to ImportLogger so ImportUndoService can later reverse exactly this batch, never touching pre-existing rows this import only updated. */
    public function createdModels(): array
    {
        return $this->createdModels;
    }

    /** The column that must be unique both against the DB (via a `rules()` uniqueRuleUnlessUpdating()) and within this file (checked above) — also used as the in-file-duplicate Failure's `attribute` label. */
    abstract protected function uniqueKeyField(): string;

    /** Default: the single uniqueKeyField() column, normalized. Override for a composite key. */
    protected function uniqueKey(Collection $data): string
    {
        return mb_strtolower(trim((string) $data[$this->uniqueKeyField()]));
    }

    abstract protected function modelClass(): string;

    /**
     * Builds the ::create()/::update() attribute array for this row, or
     * records a Failure and returns null (e.g. a referenced foreign key
     * like department_code doesn't resolve to a real row).
     *
     * @return array<string, mixed>|null
     */
    abstract protected function mapRow(Collection $data, int $rowIndex): ?array;

    /**
     * Looks up an existing record for update/upsert modes. Default: single-
     * column uniqueKeyField() match against the raw row value. Override
     * when the real key is composite/resolved (see SectionsImport, which
     * matches on $attributes' already-resolved grade_level_id rather than
     * the row's raw grade_level_code).
     */
    protected function findExisting(Collection $data, array $attributes): ?Model
    {
        $field = $this->uniqueKeyField();

        return $this->modelClass()::query()->where($field, trim((string) $data[$field]))->first();
    }

    /**
     * A DB-uniqueness rule for $column on $table in 'create' mode — an
     * existing value must be rejected, same as before this feature existed.
     * Empty in update/upsert mode, where an existing value is the expected,
     * required case, not a validation error.
     */
    protected function uniqueRuleUnlessUpdating(string $table, string $column): array
    {
        return $this->mode === self::MODE_CREATE ? [Rule::unique($table, $column)] : [];
    }
}

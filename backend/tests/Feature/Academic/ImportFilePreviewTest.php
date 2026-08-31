<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * ImportFilePreviewController is entity-agnostic — it just reads a file's
 * header row and data rows back as plain strings, whatever those headers
 * happen to be (not the exact column names any specific entity's import
 * expects). The "map an arbitrary header to a canonical column" step lives
 * entirely client-side, in ImportForm.
 */
class ImportFilePreviewTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, array $headings, string $name = 'preview-test.xlsx'): UploadedFile
    {
        Storage::fake('local');

        Excel::store(new class($rows, $headings) implements FromArray, WithHeadings
        {
            public function __construct(private array $rows, private array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        }, $name, 'local');

        return new UploadedFile(
            Storage::disk('local')->path($name),
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_it_returns_arbitrary_headers_exactly_as_uploaded_and_the_data_rows(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [
                ['Mathematics & Science', 'MATSCI', 'Core science subjects'],
                ['Arts', 'ARTS', ''],
            ],
            ['Department Name', 'Dept Code', 'Notes'],
        );

        $response = $this->actingAsInSchool($user)->postJson('/api/v1/import-preview', ['file' => $file]);

        $response->assertOk();
        $this->assertEquals(['Department Name', 'Dept Code', 'Notes'], $response->json('data.headers'));
        $this->assertEquals([
            ['Mathematics & Science', 'MATSCI', 'Core science subjects'],
            ['Arts', 'ARTS', ''],
        ], $response->json('data.rows'));
        $this->assertFalse($response->json('data.truncated'));
    }

    public function test_it_works_for_csv_too(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'School Admin');
        Storage::fake('local');
        Storage::disk('local')->put('preview-test.csv', "Name,Code\nMathematics,MATH\n");
        $file = new UploadedFile(Storage::disk('local')->path('preview-test.csv'), 'preview-test.csv', 'text/csv', null, true);

        $response = $this->actingAsInSchool($user)->postJson('/api/v1/import-preview', ['file' => $file]);

        $response->assertOk();
        $this->assertEquals(['Name', 'Code'], $response->json('data.headers'));
        $this->assertEquals([['Mathematics', 'MATH']], $response->json('data.rows'));
    }

    public function test_it_requires_authentication(): void
    {
        // Tenant-zone-only route -- hitting it from the central domain (no
        // createSchool()) 404s regardless of auth state, since
        // tenancy.subdomain rejects the request before auth:sanctum even
        // runs. A real school's own subdomain is what actually exercises
        // the "authenticated but nobody's logged in" 401 path.
        $this->createSchool();
        $file = $this->uploadedFile([['a', 'b']], ['x', 'y']);

        $response = $this->postJson('/api/v1/import-preview', ['file' => $file]);

        $response->assertUnauthorized();
    }

    public function test_it_rejects_a_disallowed_file_type(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'School Admin');
        Storage::fake('local');
        Storage::disk('local')->put('not-a-spreadsheet.txt', 'just plain text');
        $file = new UploadedFile(Storage::disk('local')->path('not-a-spreadsheet.txt'), 'not-a-spreadsheet.txt', 'text/plain', null, true);

        $response = $this->actingAsInSchool($user)->postJson('/api/v1/import-preview', ['file' => $file]);

        $response->assertUnprocessable();
    }

    /**
     * A genuinely empty sheet still round-trips one blank cell (A1) through
     * PhpSpreadsheet's own writer/reader — this asserts the controller
     * doesn't error on it, not that PhpSpreadsheet somehow produces a
     * literal empty array back.
     */
    public function test_an_empty_file_does_not_error_and_has_no_data_rows(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([], []);

        $response = $this->actingAsInSchool($user)->postJson('/api/v1/import-preview', ['file' => $file]);

        $response->assertOk();
        $this->assertEquals([], $response->json('data.rows'));
    }
}

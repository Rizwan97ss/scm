<?php

namespace Tests\Feature\Certificates;

use App\Models\AcademicYear;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_create_a_certificate_template(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/certificate-templates', [
            'name' => 'Bonafide Certificate',
            'type' => 'Bonafide',
            'body' => 'This certifies that {{student_name}} ({{admission_number}}) studies at {{school_name}}.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Bonafide Certificate']);
    }

    public function test_teacher_cannot_create_a_certificate_template(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/certificate-templates', [
            'name' => 'X', 'type' => 'Y', 'body' => 'Z',
        ]);

        $response->assertStatus(403);
    }

    public function test_issuing_a_certificate_renders_placeholders_and_generates_a_sequential_number(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $template = CertificateTemplate::factory()->create([
            'body' => 'This certifies that {{student_name}} ({{admission_number}}) is a student of {{school_name}}.',
        ]);
        $student = $this->makeStudent($school);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/certificate-templates/{$template->id}/issue", [
            'student_id' => $student->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.certificate_number', 'CERT-'.now()->year.'-0001');
        $this->assertStringContainsString($student->full_name, $response->json('data.content'));
        $this->assertStringContainsString($student->admission_number, $response->json('data.content'));
        $this->assertStringNotContainsString('{{', $response->json('data.content'));
    }

    public function test_receptionist_cannot_issue_a_certificate(): void
    {
        $school = $this->createSchool();
        $receptionist = $this->createUserWithRole($school, 'Receptionist');
        tenancy()->initialize($school);
        $template = CertificateTemplate::factory()->create();
        $student = $this->makeStudent($school);

        $response = $this->actingAsInSchool($receptionist)->postJson("/api/v1/certificate-templates/{$template->id}/issue", [
            'student_id' => $student->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_student_sees_only_their_own_issued_certificates(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $template = CertificateTemplate::factory()->create();
        $studentA = $this->makeStudent($school);
        $studentB = $this->makeStudent($school);
        $studentAUser = $this->createUserWithRole($school, 'Student');
        $studentA->update(['user_id' => $studentAUser->id]);

        $this->actingAsInSchool($admin)->postJson("/api/v1/certificate-templates/{$template->id}/issue", ['student_id' => $studentA->id])->assertCreated();
        $this->actingAsInSchool($admin)->postJson("/api/v1/certificate-templates/{$template->id}/issue", ['student_id' => $studentB->id])->assertCreated();

        $response = $this->actingAsInSchool($studentAUser)->getJson('/api/v1/certificates?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_certificate_pdf_is_reachable(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $template = CertificateTemplate::factory()->create();
        $student = $this->makeStudent($school);

        $issue = $this->actingAsInSchool($admin)->postJson("/api/v1/certificate-templates/{$template->id}/issue", ['student_id' => $student->id]);
        $certificateId = $issue->json('data.id');

        $response = $this->actingAsInSchool($admin)->get("/api/v1/certificates/{$certificateId}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_student_id_card_pdf_is_reachable_for_a_permitted_staff_member(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $student = $this->makeStudent($school);

        $response = $this->actingAsInSchool($admin)->get("/api/v1/students/{$student->id}/id-card/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_staff_id_card_pdf_is_reachable_for_self(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->get("/api/v1/users/{$teacher->id}/id-card/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /** An unreachable/misconfigured branding.logo_url must not break card generation -- the card falls back to a monogram, same as a missing photo already does. */
    public function test_staff_id_card_still_renders_when_the_branding_logo_url_is_unreachable(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        app(\App\Services\SettingsService::class)->set('branding.logo_url', 'https://this-domain-does-not-exist.invalid/logo.png', $school->id, isPublic: true);

        $response = $this->actingAsInSchool($admin)->get("/api/v1/users/{$admin->id}/id-card/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    private function makeStudent(School $school): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();

        tenancy()->initialize($school);
        return Student::factory()->create(['academic_year_id' => $year->id]);
    }
}

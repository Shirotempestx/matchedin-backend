<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_fetch_own_profile()
    {
        $student = User::factory()->create([
            'role' => 'student',
            'title' => 'Student Headline',
            'city' => 'Casablanca',
        ]);

        $response = $this->actingAs($student)->getJson('/api/student-profiles/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.headline', 'Student Headline');
    }

    public function test_enterprise_can_fetch_own_profile()
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'company_name' => 'TechCorp',
            'industry' => 'Software',
        ]);

        $response = $this->actingAs($enterprise)->getJson('/api/enterprise-profiles/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'TechCorp');
    }

    public function test_student_can_follow_enterprise()
    {
        $student = User::factory()->create(['role' => 'student']);
        $enterprise = User::factory()->create(['role' => 'enterprise']);

        $response = $this->actingAs($student)->postJson("/api/enterprises/{$enterprise->id}/follow");

        $response->assertStatus(200)
                 ->assertJsonPath('isFollowing', true);

        $this->assertDatabaseHas('enterprise_followers', [
            'student_id' => $student->id,
            'enterprise_id' => $enterprise->id,
        ]);
        
        // Unfollow
        $response2 = $this->actingAs($student)->postJson("/api/enterprises/{$enterprise->id}/follow");
        $response2->assertStatus(200)
                  ->assertJsonPath('isFollowing', false);
                  
        $this->assertDatabaseMissing('enterprise_followers', [
            'student_id' => $student->id,
            'enterprise_id' => $enterprise->id,
        ]);
    }

    public function test_enterprise_can_save_student()
    {
        $student = User::factory()->create(['role' => 'student']);
        $enterprise = User::factory()->create(['role' => 'enterprise']);

        $response = $this->actingAs($enterprise)->postJson("/api/students/{$student->id}/save");

        $response->assertStatus(200)
                 ->assertJsonPath('isSaved', true);

        $this->assertDatabaseHas('saved_students', [
            'student_id' => $student->id,
            'enterprise_id' => $enterprise->id,
        ]);
        
        // Unsave
        $response2 = $this->actingAs($enterprise)->postJson("/api/students/{$student->id}/save");
        $response2->assertStatus(200)
                  ->assertJsonPath('isSaved', false);
                  
        $this->assertDatabaseMissing('saved_students', [
            'student_id' => $student->id,
            'enterprise_id' => $enterprise->id,
        ]);
    }

    public function test_roles_are_enforced_for_interactions()
    {
        $student = User::factory()->create(['role' => 'student']);
        $student2 = User::factory()->create(['role' => 'student']);
        
        $enterprise = User::factory()->create(['role' => 'enterprise']);

        // Student trying to save a student -> should fail
        $response = $this->actingAs($student)->postJson("/api/students/{$student2->id}/save");
        $response->assertStatus(403);

        // Enterprise trying to follow an enterprise -> should fail
        $response2 = $this->actingAs($enterprise)->postJson("/api/enterprises/{$enterprise->id}/follow");
        $response2->assertStatus(403);
    }
}
